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

/**
 * Dispatched after flushing entities to the data-base, but before the
 * final commit.
 */
class PostProjectUpdatedEvent extends Event {

  /** @var int */
  private $projectId;

  /**
   * @var array
   * ```
   * [ 'id' => PROJECT_ID, 'name' => NAME, 'year' => YEAR ]
   * ```
   */
  private $oldData;

  /**
   * @var array
   * ```
   * [ 'id' => PROJECT_ID, 'name' => NAME, 'year' => YEAR ]
   * ```
   */
  private $newData;

  public function __construct($projectId, $oldData, $newData) {
    parent::__construct();
    $this->projectId = $projectId;
    $this->oldData = $oldData;
    $this->newData = $newData;
  }

  public function getProjectId(): int {
    return $this->projectId;
  }

  public function getOldData(): array {
    return $this->oldData;
  }

  public function getNewData(): array {
    return $this->newData;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
