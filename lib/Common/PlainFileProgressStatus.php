<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Common;

use OCP\ILogger;
use OCP\IL10N;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Exceptions;

class PlainFileProgressStatus extends AbstractProgressStatus
{
  private const READ_RETRY_LIMIT = 10;

  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const DATA_DIR = 'progress-status';

  /** @var AppStorage */
  protected $storage;

  /** @var ISimpleFolder */
  protected $folder;

  /** @var ISimpleFile */
  protected $file;

  /** @var array */
  protected $data;

  /**
   * Find the given or create a new progress-status file.
   */
  public function __construct(
    $appName
    , ILogger $logger
    , IL10N $l10n
    , AppStorage $storage
  ) {

    $this->logger = $logger;
    $this->l = $l10n;
    $this->storage = $storage;
    $this->file = null;
    $this->folder = $this->storage->getFolder(self::DATA_DIR);
  }

  /**
   * Reset the initial state.
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
  public function bind($id = null)
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
    throw new \RuntimeException($this->l->t('Unable to read progress status file "%s" after %d retries.', $this->file->getName(), $i));
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
  public function getLastModified():\DateTimeinterface
  {
    return (new \DateTimeImmutable)->setTimestamp($this->data['lastModified']);
  }

  /** {@inheritdoc} */
  public function getData():?array
  {
    return $this->data['data'];
  }

}
