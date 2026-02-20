<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage\Database;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;

/**
 * Factory interface for generating in-memory README nodes.
 */
interface ReadMeFactoryInterface
{
  public const MIME_TYPE = 'text/plain'; // markdown';

  /**
   * @param DatabaseStorageFolder|EmptyDirectoryNode $parent
   *
   * @param string $dirName
   *
   * @return null|InMemoryFileNode
   */
  public function generateReadMe(DatabaseStorageFolder|EmptyDirectoryNode $parent, string $dirName):?InMemoryFileNode;

  /**
   * Possibly populate and return the array of possible "readMe"
   * variations. We try to recurse to the "text" app and come up with suitable
   * fallback if that fails.
   *
   * @return array<int, string>
   */
  public function getReadMeFileNames():array;

  /**
   * Check whether the given file is a ReadMe.md file.
   *
   * @param string $path
   *
   * @return bool
   */
  public function isReadMe(string $path):bool;
}
