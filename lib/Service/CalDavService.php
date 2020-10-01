<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// @@TODO: replace the stuff below by more persistent APIs. As it
// shows (Sep. 2020) the only option would be http calls to the dav
// service. Even the perhaps-forthcoming writable calendar API does
// not allow the creation of calendars or altering shring options.

// missing: move/delete calendar

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;

class CalDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var CalDavBackend */
  private $calDavBackend;

  /** @var VCalendarService */
  private $vCalendarService;

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  public function __construct(
    ConfigService $configService,
    VCalendarService $vCalendarService,
    \OCP\Calendar\IManager $calendarManager,
    CalDavBackend $calDavBackend
  )
  {
    $this->configService = $configService;
    $this->vCalendarService = $vCalendarService;
    $this->calendarManager = $calendarManager;
    $this->calDavBackend = $calDavBackend;
  }

  /**Get or create a calendar.
   *
   * @param[in] $uri Relative URI
   *
   * @param[in] $userId part of the principal name.
   *
   * @param[in] $displayName Display-name of the calendar.
   *
   * @return int Calendar id.
   */
  public function createCalendar($uri, $displayName = null, $userId = null) {
    empty($userId) && ($userId = $this->userId());
    empty($displayName) && ($displayName = ucfirst($uri));
    $principal = "principals/users/$userId";

    $calendar = $this->calDavBackend->getCalendarByUri($principal, $name, [
      '{DAV:}displayname' => $displayName,
    ]);
    if (!empty($calendar))  {
      return $calendar['id'];
    } else {
      try {
        return $this->calDavBackend->createCalendar($principal, $name, []);
      } catch(\Exception $e) {
        $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      }
    }
    return -1;
  }

  /**Delete the calendar with the given id */
  public function deleteCalendar($id) {
    $this->calDavBackend->deleteCalendar($id);
  }

  public function groupShareCalendar($calendarId, $groupId, $readOnly = false) {
    $share = [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => $readOnly,
    ];
    $calendarInfo = $this->calDavBackend->getCalendarById($calendarId);
    //$calendarInfo = $this->calendarById($calendarId);
    if (empty($calendarInfo)) {
      return false;
    }
    //$this->logError("Calendar: " . print_r($calendarInfo, true));
    // convert to ISharable
    $calendar = new Calendar($this->calDavBackend, $calendarInfo, $this->l10n(), $this->appConfig());
    $this->calDavBackend->updateShares($calendar, [$share], []);
    $shares = $this->calDavBackend->getShares($calendarId);
    foreach($shares as $share) {
      if ($share['href'] === $share['href'] && $share['readOnly'] == $readOnly) {
        return true;
      }
    }
    return false;
  }

  public function displayName($calendarId, $displayName)
  {
    try {
      $this->calDavBackend->updateCalendar($calendarId, new \Sabre\DAV\PropPatch(['{DAV:}displayname' => $displayName]));
    } catch(\Exception $e) {
      $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      return false;
    }
    return true;
  }

  /** Get a calendar with the given display name. */
  public function calendarByName($displayName)
  {
    foreach($this->calendarManager->getCalendars() as $calendar) {
      if ($displayName === $calendar->getDisplayName()) {
        return $calendar;
      }
    }
    return null;
  }

  /** Get a calendar with the given its id. */
  public function calendarById($id)
  {
    foreach($this->calendarManager->getCalendars() as $calendar) {
      if ($id === $calendar->getKey()) {
        return $calendar;
      }
    }
    return null;
  }

  public function playground() {
    $this->vCalendarService->playground();
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
