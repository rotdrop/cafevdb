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

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Create the given path as folder, remove the directory when undo is requested.
 */
class UndoableFolderCreate extends AbstractFileSystemUndoable
{
  /** @var callable */
  protected $name;

  /** @var bool */
  protected $reusedExisting;

  /** @var string */
  protected $renamedName;

  /** @var bool */
  protected $gracefully;

  /**
   * Undoable folder rename, optionally ignoring non-existing source folder.
   *
   * @param string|Callable $folderName
   *
   * @param bool $gracefully Do not throw if folder already exists
   */
  public function __construct($name, bool $gracefully = false)
  {
    $this->name = $name;
    $this->gracefully = $gracefully;
    $this->reset();
  }

  static private function normalizePath($path)
  {
    return rtrim(
      preg_replace('|'.UserStorage::PATH_SEP.'+|', UserStorage::PATH_SEP, $path),
      UserStorage::PATH_SEP);
  }

  static private function renamedName($path, $time)
  {
    $pathInfo = pathinfo($path);
    $renamed = $pathInfo['dirname']
      . UserStorage::PATH_SEP
      . $pathInfo['filename'] . '-renamed-' . $time . '.' . $pathInfo['extension'];
    return $renamed;
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
      if (!$this->gracefully) {
        $l = $this->appContainer->get(IL10N::class);
        throw new \OCP\Files\AlreadyExistsException($l->t('The folder "%s" exists already.', $this->name));
      }
      // keep the folder or rename any existing file
      if ($existing->getType() != FileInfo::TYPE_FOLDER) {
        $this->renamedName = self::renamedName($this->name);
        $this->userStorage->rename($this->name, $this->renamedName);
        $existing = null;
      } else {
        $this->reusedExisting = true;
      }
    }

    if (empty($existing)) {
      // just create the folder
      $this->userStorage->ensureFolder($this->name);
    }
  }

  /** {@inheritdoc} */
  public function undo() {
    if ($this->reusedExisting) {
      return;
    }
    $this->userStorage->delete($this->name);
    if (!empty($this->renamedName)) {
      $this->userStorage->rename($this->renamedName, $this->name);
    }
  }

  /** {@inheritdoc} */
  public function reset()
  {
    $this->reusedExisting = false;
    $this->renamedName = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
