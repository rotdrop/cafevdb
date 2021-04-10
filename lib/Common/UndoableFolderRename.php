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

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Rename the given paths. The target path will be created if it does
 * not exist. If the source path is empty the target folder will be
 * created as empty folder.
 *
 * If the target folder is empty, then the source folder will be
 * deleted if it is empty. Non empty folders will bot be deleted.
 */
class UndoableFolderRename implements IUndoable
{
  /** @var UserStorage */
  protected $userStorage;

  /** @var string */
  protected $oldName;

  /** @var string */
  protected $newName;

  public function __construct(string $oldName, string $newName)
  {
    $this->oldName = self::normalizePath($oldName);
    $this->newName = self::normalizePath($newName);
    $this->userStorage = \OC::$server->get(UserStorage::class);
  }

  static private function normalizePath($path)
  {
    return rtrim(
      preg_replace('|'.UserStorage::PATH_SEP.'+|', UserStorage::PATH_SEP, $path),
      UserStorage::PATH_SEP);
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
        throw new \Exception('Cannot find old directory at location "'.$from.'".');
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
    $this->rename($this->oldName, $this->newName);
  }

  /** {@inheritdoc} */
  public function undo() {
    $this->rename($this->newName, $this->oldName);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
