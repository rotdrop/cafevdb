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

use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Rename the given paths. The target path will be created if it does
 * not exist. If the source path is empty the target folder will be
 * created as empty folder.
 *
 * If the target folder is empty, then the source folder will be
 * deleted if it is empty. Non empty folders will bot be deleted.
 */
class UndoableFileRename extends AbstractFileSystemUndoable
{
  /** @var string */
  protected $oldName = null;

  /** @var string */
  protected $newName = null;

  /** @var Callable */
  protected $generator;

  protected const GRACELESS = 0;
  protected const GRACEFULLY_REQUESTED = 1;
  protected const GRACEFULLY_PERFORMED = 2;

  /** @var int */
  protected $gracefully;

  /**
   * Undoable file rename, optionally ignoring non-existing source file. The
   * rename will greedily generate any missing destination directories.
   *
   * @param null|string $oldName Full path of the old file.
   *
   * @param null|string $newName Full path to the new location.
   *
   * @param bool $gracefully Do not throw if the source-file given as $oldName
   * does not exist.
   *
   * @param null|Callable $generator A callable returning an array
   * [NEW,OLD]. The generator will be called by the invocation of the
   * do() function.
   */
  public function __construct(?string $oldName = null, ?string $newName = null, bool $gracefully = false, ?Callable $generator = null)
  {
    parent::__construct();
    if ((empty($oldName) || empty($newName)) && empty($generator)) {
      throw new \InvalidArgumentException('Paramteter $oldName and $newName must be non-null when the file-name generator is null.');
    }
    if (empty($generator)) {
      $this->generator = function() use ($oldName, $newName) {
        return [ $oldName, $newName ];
      };
    } else {
      $this->generator = $generator;
    }
    $this->gracefully = $gracefully ? self::GRACEFULLY_REQUESTED : self::GRACELESS;
  }

  /**
   * Rename $from to $to.
   */
  protected function rename($from, $to)
  {
    $toComponents = explode(UserStorage::PATH_SEP, $to);
    if (empty($toComponents)) {
      throw new \Exception('Cannot rename to the root-node.');
    }

    // Generate the destination directories
    $toPrefix = array_slice($toComponents, 0, count($toComponents)-1);
    $this->userStorage->ensureFolderChain($toPrefix);

    $fromFile = $this->userStorage->get($from);
    if (empty($fromFile)) {
      throw new \OCP\Files\NotFoundException(sprintf('Cannot find old file at location "%s".', $from));
    }

    $fromComponents = explode(UserStorage::PATH_SEP, $from);
    if (empty($toComponents)) {
      throw new \Exception('Cannot rename the root-node.');
    }
    $fromPrefix = array_slice($fromComponents, 0, count($fromComponents)-1);
    $this->userStorage->rename($from, $to);

    // try to remove all empty parent folders of $from
    while (!empty($fromPrefix)) {
      $parentPath = implode(UserStorage::PATH_SEP, $fromPrefix);
      $parent = $this->userStorage->getFolder($parentPath);
      if (!empty($parent->getDirectoryListing())) {
        break; // stop on first non-empty folder
      }
      $parent->delete();
      array_pop($fromPrefix);
    }
  }


  /** {@inheritdoc} */
  public function do() {
    list($this->oldName, $this->newName) = call_user_func($this->generator);

    $this->oldName = self::normalizePath($this->oldName);
    $this->newName = self::normalizePath($this->newName);

    try {
      $this->rename($this->oldName, $this->newName);
    } catch (\OCP\Files\NotFoundException $e) {
      if ($this->gracefully === self::GRACEFULLY_REQUESTED) {
        $this->gracefully = self::GRACEFULLY_PERFORMED;
      } else {
        throw $e;
      }
    }
  }

  /** {@inheritdoc} */
  public function undo() {
    // use the path-names previously generated in self::do()
    if ($this->gracefully !== self::GRACEFULLY_PERFORMED) {
      $this->rename($this->newName, $this->oldName);
    }
  }

  /** {@inheritdoc} */
  public function reset() {
    $this->gracefully = $this->gracefully ? self::GRACEFULLY_REQUESTED : self::GRACELESS;
    $this->oldName = null;
    $this->newName = null;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
