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
use OCP\Files;

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Remove the given path which may either point to a file or folder
 */
class UndoableFileSystemNodeRemove extends AbstractFileSystemUndoable
{
  /** @var callable */
  protected $name;

  /** @var string */
  protected $ignoredFiles;

  /** @var bool */
  protected $gracefully;

  /** @var bool */
  protected $recursively;

  /** @var null|string */
  protected $nodeType;

  /** @var bool */
  protected $nothingToUndo;

  /** @var array<int, int> */
  protected $doneInterval;

  /**
   * Undoable file system node remove.
   *
   * @param string|Callable $folderName
   *
   * @param bool $gracefully Do not complain if folders are non-empty or do not exist.
   *
   * @param bool $recursively Recursively remove folders. If not set only
   * non-empty folders will be removed. Ignored if the given node points to a file.
   *
   * @param string $ignoredFiles A regular expression masking out ignored
   * files by their name in the decision whether a directory is empty or
   * not. It defaults to ignoring all variants of README. Ignored if the given
   * path points to a file.
   *
   * @param string|null $nodeType Only remove matching node type.
   */
  public function __construct($name, bool $gracefully = false, bool $recursively = false, string $ignoredFiles = '/^[0-9]*-?README(.*)$/i', ?string $nodeType = null)
  {
    $this->name = $name;
    $this->gracefully = $gracefully;
    $this->recursively = $recursively;
    $this->ignoredFiles = $ignoredFiles;
    $this->nodeType = $nodeType;
    $this->reset();
  }

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer) {
    parent::initialize($appContainer);
  }

  /** {@inheritdoc} */
  public function do() {
    $startTime = $this->timeFactory->getTime();
    if (is_callable($this->name)) {
      $this->name = call_user_func($this->name);
    }
    $this->name = self::normalizePath($this->name);
    try {
      /** @var FileSystemNode $existing */
      $existing = $this->userStorage->get($this->name);
      if (empty($existing)) {
        throw new Files\NotFoundException($this->l->t('Cannot remove "%s", file-node not found.', $this->name));
      }
      if ($this->nodeType !== null && $existing->getType() !== $this->nodeType) {
        throw new Files\NotFoundException($this->l->t('Cannot remove "%1$s", node exists, but has not the request type "%2$s".', [
          $this->name,
          $this->nodeType == FileInfo::TYPE_FILE ? $this->l->t('file') : $this->l->t('folder')
        ]));
      }
      $listing = null;
      if ($existing->getType() == FileInfo::TYPE_FOLDER && !$this->recursively) {
        $listing = $existing->getDirectoryListing();
        if (!empty($this->ignoredFiles)) {
          $listing = array_filter(
            $listing,
            fn($node) => !preg_match($this->ignoredFiles, $node->getName())
          );
        }
      }
      if (!empty($listing)) {
        if ($this->gracefully) {
          $this->logInfo(
            'Folder "' . $this->name . '" not empty, not removing'
            . print_r(array_map(fn($x) => $x->getName(), $listing), true));
          $this->nothingToUndo = true;
        } else {
          throw new Files\InvalidContentException($this->l->t('Directory "%s" not empty.', $this->name));
        }
      } else {
        $existing->delete();
      }
    } catch (Files\NotFoundException $e) {
      if ($this->gracefully) {
        $this->logException($e, 'Path not found, cannot remove "' . $this->name . '".');
        $this->nothingToUndo =true;
      } else {
        throw $e;
      }
    }
    if ($startTime + 1 < $this->timeFactory->getTime()) {
      time_sleep_until($startTime + 1);
    }
    $endTime = $this->timeFactory->getTime();
    $this->doneInterval = [ $startTime, $endTime ];
  }

  /** {@inheritdoc} */
  public function undo() {
    if ($this->nothingToUndo) {
      return;
    }
    $this->userStorage->restore($this->name, $this->doneInterval);
  }

  /** {@inheritdoc} */
  public function reset()
  {
    $this->nothingToUndo = false;
    $this->doneInterval = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
