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

class AfterProjectDeletedEvent extends Event {

  /** @var int */
  private $projectId;

  /** @var string */
  private $projectName;

  /**
   * @var bool
   *
   * Set to true if the project was kept but disabled.
   */
  private $disabled;

  public function __construct(int $projectId, string $projectName, bool $disabled)
  {
    parent::__construct();
    $this->projectId = $projectId;
    $this->projectName = $projectName;
    $this->diabled = $disabled;
  }

  public function getProjectId():int
  {
    return $this->projectId;
  }

  public function getProjectName():string
  {
    return $this->projectName;
  }

  public function getDisabled():bool
  {
    return $this->disabled;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
