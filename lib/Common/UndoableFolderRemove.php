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
 * Remove the given node, which must be a folder.
 */
class UndoableFolderRemove extends UndoableFileSystemNodeRemove
{
  /**
   * Undoable folder remove.
   *
   * @param string|callable $name
   *
   * @param bool $gracefully Do not complain if folders are non-empty or do not exist.
   *
   * @param bool $recursively Recursively remove folders. If not set only
   * non-empty folders will be removed.
   *
   * @param string $ignoredFiles A regular expression masking out ignored
   * files by their name in the decision whether a directory is empty or
   * not. It defaults to ignoring all variants of README.
   */
  public function __construct(
    mixed $name,
    bool $gracefully = false,
    bool $recursively = false,
    string $ignoredFiles = '/^[0-9]*-?README(.*)$/i',
  ) {
    parent::__construct($name, $gracefully, $recursively, $ignoredFiles, FileInfo::TYPE_FOLDER);
  }
}
