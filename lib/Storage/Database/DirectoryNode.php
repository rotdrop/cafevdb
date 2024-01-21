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

namespace OCA\CAFEVDB\Storage\Database;

use DateTimeInterface;

/**
 * Simplistik directory node holding the basename of the directory and
 * an optional minimal directory modification time in order to track
 * deletions. Normally the directory modification time is just the
 * maximum of the entries. This, however, fails to invalidate the
 * file-cache of the cloud in case of deletions.
 */
class DirectoryNode
{
  /**
   * @param string $name
   *
   * @param  null|DateTimeInterface $minimalModificationTime
   */
  public function __construct(
    public string $name,
    public ?DateTimeInterface $minimalModificationTime = null,
  ) {
  }

  /**
   * @param null|DateTimeInterface $mtime
   *
   * @return DirectoryNode $this.
   */
  public function updateModificationTime(?DateTimeInterface $mtime = null):DirectoryNode
  {
    if (!empty($mtime)) {
      $this->minimalModificationTime = max($mtime, $this->minimalModificationTime);
    }
    return $this;
  }
}
