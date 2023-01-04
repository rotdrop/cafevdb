<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use Exception;

use OCP\AppFramework\IAppContainer;
use OCP\Files\Node as FileSystemNode;
use OCP\Files\FileInfo;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IDateTimeFormatter;
use OCP\Files\AlreadyExistsException as FileAlreadyExistsException;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Create the given path as folder, remove the directory when undo is requested.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class UndoableFolderCreate extends AbstractFileSystemUndoable
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  /** @var IDateTimeFormatter */
  protected $dateTimeFormatter;

  /** @var callable */
  protected $name;

  /** @var string */
  protected $ignoredFiles;

  /** @var bool */
  protected $reusedExisting;

  /** @var string */
  protected $renamedName;

  /** @var bool */
  protected $gracefully;

  /**
   * Undoable folder rename, optionally ignoring non-existing source folder.
   *
   * @param string|callable $name
   *
   * @param bool $gracefully Do not throw if folder already exists.
   *
   * @param string $ignoredFiles
   */
  public function __construct(mixed $name, bool $gracefully = false, string $ignoredFiles = '/^[0-9]*-?README(.*)$/i')
  {
    $this->name = $name;
    $this->gracefully = $gracefully;
    $this->ignoredFiles = $ignoredFiles;
    $this->reset();
  }

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer):void
  {
    parent::initialize($appContainer);
    $this->dateTimeFormatter = $appContainer->get(IDateTimeFormatter::class);
  }

  /** {@inheritdoc} */
  public function do():void
  {
    if (is_callable($this->name)) {
      $this->name = call_user_func($this->name);
    }
    $this->name = self::normalizePath($this->name);

    $this->renamedName = null;
    // - $folderName does not exist
    //   -> just create the new folder, delete on undo
    // - $folderName exists and is a folder
    //   -> just keep, do not delete on undo
    // - $folderName exists and is a file
    //   -> delete, undelete on undo, or just rename
    $components = explode(UserStorage::PATH_SEP, $this->name);
    if (empty($components)) {
      throw new Exception('Cannot create the root-node.');
    }
    $prefix = array_slice($components, 0, count($components)-1);
    $this->userStorage->ensureFolderChain($prefix);

    /** @var FileSystemNode $existing */
    $existing = $this->userStorage->get($this->name);
    if (!empty($existing)) {
      if ($existing->getType() == FileInfo::TYPE_FOLDER) {
        if (!$this->gracefully) {
          // ignore essentially empty folders and reuse even without "gracefully"
          $listing = $existing->getDirectoryListing();
          if (!empty($this->ignoredFiles)) {
            $listing = array_filter(
              $listing,
              fn($node) => !preg_match($this->ignoredFiles, $node->getName())
            );
          }
          if (!empty($listing)) {
            throw new FileAlreadyExistsException($this->l->t('The folder "%s" exists already and is not empty.', $this->name));
          }
        }
        // empty or "gracefully", just reuse
        $this->reusedExisting = true;
      } else {
        if (!$this->gracefully) {
          throw new FileAlreadyExistsException($this->l->t('The folder or file "%s" exists already.', $this->name));
        }
        $this->renamedName = $this->renamedName($this->name);
        $this->userStorage->rename($this->name, $this->renamedName);
        $existing = null;
      }
    }

    if (empty($existing)) {
      // just create the folder
      $this->userStorage->ensureFolder($this->name);
    }
  }

  /** {@inheritdoc} */
  public function undo():void
  {
    if ($this->reusedExisting) {
      return;
    }
    $this->userStorage->delete($this->name);
    if (!empty($this->renamedName)) {
      $this->userStorage->rename($this->renamedName, $this->name);
    }
  }

  /** {@inheritdoc} */
  public function reset():void
  {
    $this->reusedExisting = false;
    $this->renamedName = null;
    $this->renamedReadMe = null;
    $this->oldReadMeContent = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
