<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Rename the given paths. The target path will be created if it does
 * not exist. If the source path is empty the target folder will be
 * created as empty folder.
 *
 * If the target folder is empty, then the source folder will be
 * deleted if it is empty. Non empty folders will bot be deleted.
 */
class UndoableFolderRename extends AbstractFileSystemUndoable
{
  /** @var string */
  protected $oldName;

  /** @var string */
  protected $newName;

  protected const GRACELESS = 0;
  protected const GRACEFULLY_REQUESTED = 1;
  protected const GRACEFULLY_PERFORMED = 2;

  /** @var int */
  protected $gracefully;

  /**
   * Undoable folder rename, optionally ignoring non-existing source folder.
   *
   * @param string $oldName
   *
   * @param string $newName
   *
   * @param bool $gracefully Do not throw if the source-folder given
   * as $oldName does not exist.
   */
  public function __construct(string $oldName, string $newName, bool $gracefully = false)
  {
    $this->oldName = self::normalizePath($oldName);
    $this->newName = self::normalizePath($newName);
    $this->gracefully = $gracefully ? self::GRACEFULLY_REQUESTED : self::GRACELESS;
  }

  /**
   * Rename $from to $to with the following conventions:
   *
   * - if empty($from) then just create $to
   * - if empty($to) then just delete $from
   * - if both are non empty, then rename
   */
  protected function rename($from, $to)
  {
    $toComponents = explode(UserStorage::PATH_SEP, $to);
    if (empty($toComponents)) {
      throw new \Exception('Cannot rename to the root-node.');
    }
    $toPrefix = array_slice($toComponents, 0, count($toComponents)-1);
    $this->userStorage->ensureFolderChain($toPrefix);

    $fromDir = null;
    if (!empty($from)) {
      $fromDir = $this->userStorage->get($from);
      if (empty($fromDir)) {
        throw new \OCP\Files\NotFoundException(sprintf('Cannot find old directory at location "%s".', $from));
      }
    }

    if (empty($to)) {
      // remove the $from folder
      if (!empty($fromDir)) {
        $fromDir->delete();
      }
    } else if (!empty($fromDir)) {
      $this->userStorage->rename($from, $to);
    } else {
      // otherwise just create the new folder.
      $this->userStorage->ensureFolder($to);
    }
  }


  /** {@inheritdoc} */
  public function do() {
    try {
      $this->rename($this->oldName, $this->newName);
    } catch (\OCP\Files\NotFoundException $e) {
      if ($this->gracefully === self::GRACEFULLY_REQUESTED) {
        $this->rename(null, $this->newName);
        $this->gracefully = self::GRACEFULLY_PERFORMED;
      } else {
        throw $e;
      }
    }
  }

  /** {@inheritdoc} */
  public function undo() {
    if ($this->gracefully === self::GRACEFULLY_PERFORMED) {
      $this->rename($this->newName, null);
    } else {
      $this->rename($this->newName, $this->oldName);
    }
  }

  /** {@inheritdoc} */
  public function reset() {
    $this->gracefully = $this->gracefully ? self::GRACEFULLY_REQUESTED : self::GRACELESS;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
