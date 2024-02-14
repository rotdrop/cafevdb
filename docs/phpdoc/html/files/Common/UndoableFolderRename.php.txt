<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
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

use OCP\Files\Folder as CloudFolder;
use OCP\Files\NotFoundException as FileNotFoundException;

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Rename the given paths. The target path will be created if it does
 * not exist. If the source path is empty the target folder will be
 * created as empty folder.
 *
 * If the target folder is empty, then the source folder will be
 * deleted if it is empty. Non empty folders will bot be deleted.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class UndoableFolderRename extends AbstractFileSystemUndoable
{
  private const UNDO_DELETE = 'delete';
  private const UNDO_RENAME = 'rename';
  private const UNDO_RESTORE = 'restore';
  private const UNDO_NOTHING = 'nothing';

  /** @var string */
  protected $undoAction;

  /** @var array<int, int> */
  protected $doneInterval;

  /**
   * Undoable folder rename, optionally ignoring non-existing source folder.
   *
   * @param string|Closure $oldName
   *
   * @param string|Closure $newName
   *
   * @param bool $gracefully Do not throw if the source-folder given
   * as $oldName does not exist.
   *
   * @param bool $mkdir Create non-existing directories.
   */
  public function __construct(
    protected string|Closure $oldName,
    protected string|Closure $newName,
    protected bool $gracefully = false,
    protected bool $mkdir = true,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * Rename $from to $to with the following conventions:
   *
   * - if empty($from) then just create $to
   * - if empty($to) then just delete $from
   * - if both are non empty, then rename
   */
  public function do():void
  {
    $startTime = $this->timeFactory->getTime();
    if ($this->oldName instanceof Closure) {
      $this->oldName = $this->oldName();
    }
    $this->oldName = self::normalizePath($this->oldName);
    if ($this->newName instanceof Closure) {
      $this->newName = $this->newName();
    }
    $this->newName = self::normalizePath($this->newName);

    /** @var CloudFolder $oldDir */
    $oldDir = null;
    if (!empty($this->oldName)) {
      $oldDir = $this->userStorage->getFolder($this->oldName);
      if (empty($oldDir) && !$this->gracefully) {
        throw new FileNotFoundException(sprintf('Cannot find old directory at location "%s".', $this->oldName));
      }
    }

    if (empty($this->newName)) {
      // delete if it exists
      if (!empty($oldDir)) {
        $oldDir->delete();
        $this->undoAction = self::UNDO_RESTORE;
      }
    } elseif (!empty($oldDir)) {
      // rename it
      if ($this->mkdir) {
        $toComponents = explode(UserStorage::PATH_SEP, $this->newName);
        if (empty($toComponents)) {
          throw new Exception('Cannot rename to the root-node.');
        }
        $toPrefix = array_slice($toComponents, 0, count($toComponents) - 1);
        $this->userStorage->ensureFolderChain($toPrefix);
      }
      try {
        $this->logInfo('RENAME');
        $this->userStorage->rename($this->oldName, $this->newName);
        $this->undoAction = self::UNDO_RENAME;
      } catch (\Throwable $t) {
        if ($this->gracefully) {
          $this->logException($t);
        } else {
          throw $t;
        }
      }
    } elseif ($this->mkdir) {
      // otherwise just create the new folder.
      $this->userStorage->ensureFolder($this->newName);
      $this->undoAction = self::UNDO_DELETE;
    }

    $oldReporting = error_reporting();
    error_reporting($oldReporting & ~E_WARNING);
    if ($startTime + 1 < $this->timeFactory->getTime()) {
      time_sleep_until($startTime + 1);
    }
    error_reporting($oldReporting);
    $endTime = $this->timeFactory->getTime();
    $this->doneInterval = [ $startTime, $endTime ];
  }

  /** {@inheritdoc} */
  public function undo():void
  {
    switch ($this->undoAction) {
      case self::UNDO_DELETE:
        $this->userStorage->delete($this->newName);
        break;
      case self::UNDO_RENAME:
        $this->userStorage->renamew($this->newName, $this->oldName);
        break;
      case self::UNDO_RESTORE:
        $this->userStorage->restore($this->oldName, $this->doneInterval);
        break;
    }
  }

  /** {@inheritdoc} */
  public function reset():void
  {
    $this->undoAction = self::UNDO_NOTHING;
    $this->doneInterval = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
