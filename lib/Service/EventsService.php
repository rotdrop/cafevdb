<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

/**Events and tasks handling. */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var DatabaseService */
  private $databaseService;

  public function __construct(
    ConfigService $configService,
    DatabaseService $databaseService
  ) {
    $this->configService = $configService;
    $this->databaseService = $databaseService;
  }




  private function eventProjects($eventId)
  {
    $query = "SELECT ProjectId
  FROM ProjectEvents WHERE EventId = ?
  ORDER BY Id ASC";

    return $this->databaseService->fetchArray($query, [$eventId]);
  }

  /**Fetch the related rows from the pivot-table (without calendar
   * data).
   *
   * @return A flat array with the associated event-ids. Note that
   * even in case of an error an (empty) array is returned.
   */
  private function projectEvents($projectId)
  {
    $query = "SELECT EventId
  FROM ProjectEvents
  WHERE ProjectId = ? AND Type = 'VEVENT'
  ORDER BY Id ASC";

    return $this->databaseService->fetchArray($query, [$projectId]);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
