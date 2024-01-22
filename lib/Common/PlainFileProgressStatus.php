<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023, 2024 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Common;

use DateTimeImmutable;
use RuntimeException;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Exceptions;

/** Progress status with a plain local file as storage. */
class PlainFileProgressStatus extends AbstractProgressStatus
{
  private const READ_RETRY_LIMIT = 10;

  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const DATA_DIR = 'progress-status';

  /** @var ISimpleFolder */
  protected $folder;

  /** @var ISimpleFile */
  protected $file;

  /** @var array */
  protected $data;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    protected ILogger $logger,
    protected IL10N $l,
    protected AppStorage $storage,
  ) {
    $this->file = null;
    $this->folder = $this->storage->getFolder(self::DATA_DIR);
  }
  // phpcs:enable

  /**
   * Reset the initial state.
   *
   * @return void
   */
  protected function reset()
  {
    $this->data = [
      'current' => 0,
      'target' => 0,
      'data' => [],
      'lastModified' => time(),
    ];
  }

  /**
   * Flush the initial state to disk.
   *
   * @return bool true on success, false if the data-file has been
   * deleted.
   */
  protected function flush():bool
  {
    if ($this->folder->fileExists($this->file->getName())) {
      $this->data['lastModified'] = time();
      $this->file->putContent(json_encode($this->data));
      return true;
    } else {
      return false;
    }
  }

  /** {@inheritdoc} */
  public function delete()
  {
    if (!empty($this->file)) {
      $this->file->delete();
      $this->file = null;
    }
  }

  /** {@inheritdoc} */
  public function bind(mixed $id = null)
  {
    if (!empty($this->file) && $this->file->getName() == $id) {
      $this->sync();
      return;
    }

    if (!empty($this->file)) {
      try {
        $this->file->delete();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    $this->file = null;

    if (!empty($id)) {
      try {
        $this->file = $this->folder->getFile($id);
        $this->sync();
      } catch (\Throwable $t) {
        $this->reset();
        // $this->logException($t);
        throw (new Exceptions\ProgressStatusNotFoundException(
          $this->l->t('Unable to find progress status for job id "%s"', $id),
          $t->getCode(),
          $t));
      }
    } else {
      $this->file = $this->storage->newTemporaryFile(self::DATA_DIR);
      $this->reset();
      $this->file->putContent(json_encode($this->data));
    }
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->file->getName();
  }

  /** {@inheritdoc} */
  public function update(int $current, ?int $target = null, ?array $data = null):bool
  {
    $this->data['current'] = $current;
    if (!empty($target)) {
      $this->data['target'] = $target;
    }
    if (!empty($data)) {
      $this->data['data'] = $data;
    }
    return $this->flush();
  }

  /** {@inheritdoc} */
  public function sync()
  {
    for ($i = 0; $i < self::READ_RETRY_LIMIT; $i++) {
      $fileContent = $this->file->getContent();
      $fileData = json_decode($fileContent, true);
      if (is_array($fileData)) {
        $this->data = $fileData;
        return;
      }
    }
    throw new RuntimeException($this->l->t('Unable to read progress status file "%s" after %d retries.', $this->file->getName(), $i));
  }

  /** {@inheritdoc} */
  public function getCurrent():int
  {
    return $this->data['current'];
  }

  /** {@inheritdoc} */
  public function getTarget():int
  {
    return $this->data['target'];
  }

  /** {@inheritdoc} */
  public function getData():?array
  {
    return $this->data['data'];
  }
}
