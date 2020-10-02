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

use OCA\DAV\Events\CalendarUpdatedEvent;

/**Events and tasks handling. */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var DatabaseService */
  private $databaseService;

  /** @var CalDavService */
  private $calDavService;

  public function __construct(
    ConfigService $configService,
    DatabaseService $databaseService,
    CalDavService $calDavService
  ) {
    $this->configService = $configService;
    $this->databaseService = $databaseService;
    $this->calDavService = $calDavService;
  }

  public function onCalendarUpdate(CalendarUpdatedEvent $event)
  {
    if (!$this->inGroup()) {
      return;
    }
    foreach(ConfigService::CALENDARS as $cal) {
      $uri = $cal['uri'];
      $calendarId = $this->getCalendarId($uri);
      if ($event->getCalendarId() == $calendarId) {
        $displayName = $this->getCalendarDisplayName($uri);
        $calendarData = $event->getCalendarData();
        if (empty($displayName)) {
          $this->setCalendarDisplayName($uri, $calendarData['displayname']);
        } else if ($displayName != $calendarData['displayname']) {
          // revert the change
          $this->calDavService->displayName($calendarId, $displayName);
        }
        break;
      }
    }
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

  /**Return the IDs of the default calendars.
   */
  public function defaultCalendars($public = false)
  {
    $result = [];
    foreach (ConfigService::CALENDARS as $cal) {
      if ($public && !$cal['public']) {
        continue;
      }
      $result[] = $this->getConfigValue($cal['uri'].'calendar'.'id');
    }
    return $result;
  }

  /**Return the configured calendar id. */
  private function getCalendarId($uri)
  {
    return $this->getConfigValue($uri.'calendar'.'id');
  }

  /**Return the configured calendar display name. */
  private function getCalendarDisplayName($uri)
  {
    return $this->getConfigValue($uri.'calendar');
  }

  /**Return the configured calendar display name. */
  private function setCalendarDisplayName($uri, $displayName)
  {
    return $this->setConfigValue($uri.'calendar', $displayName);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
