<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2024, Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Internal wrapper class for a couple of closures.
 *
 * @see ProjectParticipantsStorage
 */
class ParticipantsStorageGenerator
{
  /**
   * Wrap the supplied data members and closures into class methods.
   *
   * @param array $closureData
   */
  public function __construct(
    protected array $closureData,
  ) {
  }

  /** @return array Path components relative to root. */
  public function pathChain():array
  {
    return $this->closureData['pathChain'];
  }

  /**
   * If > 0 skip this generator if the the current directory nesting level is
   * larger then the returned number.
   *
   * @return int
   */
  public function skipDepthIfOther():int
  {
    return $this->closureData['skipDepthIfOther'];
  }

  /** @return DateTimeInterface */
  public function parentModificationTime():?DateTimeInterface
  {
    return $this->closureData['parentModificationTime']();
  }

  /**
   * @return bool
   */
  public function hasLeafNodes():bool
  {
    return $this->closureData['hasLeafNodes']();
  }

  /**
   * @param string $dirName Current parent dir-name.
   *
   * @param string $subDirectoryPath
   *
   * @return void
   */
  public function createLeafNodes(string $dirName, string $subDirectoryPath):void
  {
    $this->closureData['createLeafNodes']($dirName, $subDirectoryPath);
  }
}
