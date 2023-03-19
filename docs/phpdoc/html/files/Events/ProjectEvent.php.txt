<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine
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

/** Base class for project events. */
class ProjectEvent extends Event
{

  /** @var int */
  private $projectId;

  /** @var string */
  private $projectName;

  /** @var int */
  private $projectYear;

  /** @var ProjectType */
  private $projectType;

  /**
   * @param int $projectId
   *
   * @param string $projectName
   *
   * @param int $projectYear
   *
   * @param ProjectType $projectType
   */
  public function __construct(
    int $projectId,
    string $projectName,
    int $projectYear,
    ProjectType $projectType,
  ) {
    parent::__construct();
    $this->projectId = $projectId;
    $this->projectName = $projectName;
    $this->projectYear = $projectYear;
    $this->projectType = $projectType;
  }

  /** @return int */
  public function getProjectId():int
  {
    return $this->projectId;
  }

  /** @return string */
  public function getProjectName():string
  {
    return $this->projectName;
  }

  /** @return int */
  public function getProjectYear():int
  {
    return $this->projectYear;
  }

  /** @return ProjectType */
  public function getProjectType():ProjectType
  {
    return $this->projectType;
  }
}
