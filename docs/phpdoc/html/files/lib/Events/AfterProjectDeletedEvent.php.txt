<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;

/** Event fired after project deletion. */
class AfterProjectDeletedEvent extends ProjectEvent
{
  /**
   * @param int $projectId
   *
   * @param string $projectName
   *
   * @param int $projectYear
   *
   * @param ProjectType $projectType
   *
   * @param bool $disabled Set to true if the project was kept but disabled.
   */
  public function __construct(
    int $projectId,
    string $projectName,
    int $projectYear,
    ProjectType $projectType,
    private bool $disabled
  ) {
    parent::__construct($projectId, $projectName, $projectYear, $projectType);
    $this->disabled = $disabled;
  }

  /** @return bool */
  public function getDisabled():bool
  {
    return $this->disabled;
  }
}
