<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine
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
 * Replace one file by another, maintaining an undo history in the cloud. Both
 * files may have different names.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class UndoableFileReplace extends AbstractFileSystemUndoable
{
  /** @var bool */
  protected $restoreOldName;

  /** @var bool */
  protected $restoreSameName;

  /** @var array<int, int> */
  protected $doneInterval;

  /**
   * @param string|Closure $name The name of the new file.
   *
   * @param string $content The content to place into the file system with name $name.
   *
   * @param null|string|Closure $oldName A name of an old file to replace.
   *
   * @param bool $gracefully
   */
  public function __construct(
    protected string|Closure $name,
    protected string $content,
    protected null|string|Closure $oldName = null,
    protected bool $gracefully = false,
  ) {
    $this->reset();
  }

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer):void
  {
    parent::initialize($appContainer);
  }

  /** {@inheritdoc} */
  public function do():void
  {
    $startTime = $this->timeFactory->getTime();
    if ($this->name instanceof Closure) {
      $this->name = call_user_func($this->name);
    }
    $this->name = self::normalizePath($this->name);
    if ($this->oldName) {
      if ($this->oldName instanceof Closure) {
        $this->oldName = call_user_func($this->oldName);
      }
      $this->oldName = self::normalizePath($this->oldName);
    }
    $existing = $this->userStorage->getFile($this->name);
    if (!empty($existing)) {
      $this->restoreSameName = true;
    }
    try {
      if (!empty($this->oldName) && $this->oldName != $this->name) {
        $this->userStorage->rename($this->oldName, $this->name);
        $this->restoreOldName = true;
      }
    } catch (\Throwable $t) {
      if ($this->gracefully) {
        $this->logException(
          $t,
          sprintf('Unable to rename the old file "%1$s" to the new name "%2$s".'
                  . ' There will be no undo-history in the cloud file-space for this file.', $this->oldName, $this->name));
      } else {
        throw $t;
      }
    }
    $this->userStorage->putContent($this->name, $this->content);
    if ($startTime + 1 < $this->timeFactory->getTime()) {
      time_sleep_until($startTime + 1);
    }
    $endTime = $this->timeFactory->getTime();
    $this->doneInterval = [ $startTime, $endTime ];
  }

  /** {@inheritdoc} */
  public function undo():void
  {
    if ($this->restoreOldName) {
      $this->userStorage->restore($this->oldName, $this->doneInterval);
    }
    if ($this->restoreSameName) {
      $this->userStorage->restore($this->name, $this->doneInterval);
    }
  }

  /** {@inheritdoc} */
  public function reset():void
  {
    $this->restoreSameName = false;
    $this->restoreOldName = false;
    $this->doneInterval = null;
  }
}
