<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Events;

use OCP\EventDispatcher\Event;

/**
 * Dispatched after flushing entities to the data-base, but before the
 * final commit.
 */
class ProjectUpdatedEvent extends Event
{
  /** @var int */
  private $projectId;

  /**
   * @var array
   * ```
   * [ 'id' => PROJECT_ID, 'name' => NAME, 'year' => YEAR, 'type' => TYPE ]
   * ```
   */
  private $oldData;

  /**
   * @var array
   * ```
   * [ 'id' => PROJECT_ID, 'name' => NAME, 'year' => YEAR, 'type' => TYPE ]
   * ```
   */
  private $newData;

  /**
   * @param int $projectId
   *
   * @param array $oldData
   *
   * @param array $newData
   */
  public function __construct(int $projectId, array $oldData, array $newData)
  {
    parent::__construct();
    $this->projectId = $projectId;
    $this->oldData = $oldData;
    $this->newData = $newData;
  }

  /** @return int Get project id. */
  public function getProjectId():int
  {
    return $this->projectId;
  }

  /** @return array Get old project data. */
  public function getOldData():array
  {
    return $this->oldData;
  }

  /** @return array Get new project data. */
  public function getNewData(): array
  {
    return $this->newData;
  }
}
