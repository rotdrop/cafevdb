<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\IAppContainer;
use OCP\Files\Node as FileSystemNode;
use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Create the given path as folder, remove the directory when undo is requested.
 */
class UndoableFolderCreate extends AbstractFileSystemUndoable
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const README_SEPARATOR = "\n\n----------------------\n\n";

  const README_NAME = Constants::README_NAME;

  /** @var IDateTimeFormatter */
  protected $dateTimeFormatter;

  /** @var callable */
  protected $name;

  /** @var string */
  protected $ignoredFiles;

  /** @var string */
  protected $readMe;

  /** @var bool */
  protected $reusedExisting;

  /** @var string */
  protected $renamedName;

  /** @var string */
  protected $renamedReadMe;

  /** @var string */
  protected $oldReadMeContent;

  /** @var bool */
  protected $gracefully;

  /**
   * Undoable folder rename, optionally ignoring non-existing source folder.
   *
   * @param string|Callable $folderName
   *
   * @param bool $gracefully Do not throw if folder already exists
   */
  public function __construct($name, bool $gracefully = false, string $readMe = null, string $ignoredFiles = '/^[0-9]*-?README(.*)$/i')
  {
    $this->name = $name;
    $this->gracefully = $gracefully;
    $this->readMe = $readMe;
    $this->ignoredFiles = $ignoredFiles;
    $this->reset();
  }

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer) {
    parent::initialize($appContainer);
    $this->dateTimeFormatter = $appContainer->get(IDateTimeFormatter::class);
  }

  /** {@inheritdoc} */
  public function do() {
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
      throw new \Exception('Cannot create the root-node.');
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
            throw new \OCP\Files\AlreadyExistsException($this->l->t('The folder "%s" exists already and is not empty.', $this->name));
          }
        }
        // empty or "gracefully", just reuse
        $this->reusedExisting = true;
      } else {
        if (!$this->gracefully) {
          throw new \OCP\Files\AlreadyExistsException($this->l->t('The folder or file "%s" exists already.', $this->name));
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

    $readMeContent = trim($this->readMe);

    /** @var FileSystemNode $existingReadMeNode */
    $readMePath = $this->name . UserStorage::PATH_SEP . self::README_NAME;
    $existingReadMeNode = $this->userStorage->get($readMePath);
    if (!empty($existingReadMeNode)) {
      if (!$existingReadMeNode->getType() == FileInfo::TYPE_FILE) {
        // garbled, rename the beast
        $this->renamedReadMe = $this->renamedName($readMePath);
        $this->userStorage->rename($readMePath, $this->renamedReadMe);
      } else {
        // Plain file. In order not to generate excessivly many README.md
        // files we just add to the content of the old README.md
        $this->oldReadMeContent = $existingReadMeNode->getContent();

        $oldReadMeHead = trim(substr($this->oldReadMeContent, 0, strpos($this->oldReadMeContent, self::README_SEPARATOR)));

        $this->logInfo('OLD HEAD ' . strpos($this->oldReadMeContent, self::README_SEPARATOR) . ' || ' . $oldReadMeHead);

        if ($readMeContent == $oldReadMeHead) {
          $readMeContent = null;
          $this->oldReadMeContent = null; // disable restore, is kept unchanged
        } else if (!empty($this->oldReadMeContent) && $readMeContent !== $oldReadMeHead) {
          // annotate the old content
          if (empty($readMeContent)) {
            $readMeContent = '';
          } else {
            $readMeContent .= self::README_SEPARATOR;
          }
          $now = new \DateTime;
          $readMeContent .= $this->l->t('The old ``README.md`` content on %1$s at %2$s was:', [
            $this->dateTimeFormatter->formatDate($now),
            $this->dateTimeFormatter->formatTime($now),
          ]);
          $readMeContent .= "\n\n" . $this->oldReadMeContent;
        }
      }
    }

    if (!empty($readMeContent)) {
      try {
        $this->userStorage->putContent($readMePath, $readMeContent);
      } catch (\Throwable $t) {
        if (!$this->gracefully) {
          throw $t;
        }
        $this->logException($t, 'Unable to store contents of readme-file');
      }
    }
  }

  /** {@inheritdoc} */
  public function undo() {
    if ($this->reusedExisting) {
      if (!empty($this->renamedReadMe)) {
        $readMePath = $this->name . UserStorage::PATH_SEP . self::README_NAME;
        $this->userStorage->rename($this->renamedReadMe, $readMePath);
      } else if (!empty($this->oldReadMeContent)) {
        $this->userStorage->putContent($readMePath, $this->oldReadMeContent);
      }
      return;
    }
    $this->userStorage->delete($this->name); // recursively, README.md will also be remove again.
    if (!empty($this->renamedName)) {
      $this->userStorage->rename($this->renamedName, $this->name);
    }
  }

  /** {@inheritdoc} */
  public function reset()
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
