<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Events;

use OCP\EventDispatcher\Event;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;

class ProjectCreatedEvent extends Event {

  /** @var int */
  private $projectId;

  /** @var string */
  private $projectName;

  /** @var int */
  private $projectYear;

  /** @var ProjectType */
  private $projectType;

  public function __construct(
    int $projectId
    , string $projectName
    , int $projectYear
    , ProjectType $projectType) {
    parent::__construct();
    $this->projectId = $projectId;
    $this->projectName = $projectName;
    $this->projectYear = $projectYear;
    $this->projectType = $projectType;
  }

  public function getProjectId():int
  {
    return $this->projectId;
  }

  public function getProjectName():string
  {
    return $this->projectName;
  }

  public function getProjectYear():int
  {
    return $this->projectYear;
  }

  public function getProjectType():ProjectType
  {
    return $this->projectType;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
