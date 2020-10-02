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

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  /** @var int */
  private $calendarUserId;

  public function __construct(
    ConfigService $configService,
    \OCP\Calendar\IManager $calendarManager,
    CalDavBackend $calDavBackend
  )
  {
    $this->configService = $configService;
    $this->calendarManager = $calendarManager;
    $this->calDavBackend = $calDavBackend;
    $this->calendarUserId = $this->userId();
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
   *
   * @bug This function uses internal APIs.
   */
  public function createCalendar($uri, $displayName = null, $userId = null) {
    empty($userId) && ($userId = $this->userId());
    empty($displayName)&& ($displayName = ucfirst($uri));
    $principal = "principals/users/$userId";

    $calendar = $this->calDavBackend->getCalendarByUri($principal, $uri);
    if (!empty($calendar))  {
      return $calendar['id'];
    } else {
      try {
        $calendarId = $this->calDavBackend->createCalendar($principal, $uri, [
          '{DAV:}displayname' => $displayName,
        ]);
        $this->refreshCalendarManager();
        return $calendarId;
      } catch(\Exception $e) {
        $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      }
    }
    return -1;
  }

  /**Delete the calendar with the given id.
   *
   * @bug This function uses internal APIs.
   */
  public function deleteCalendar($id) {
    $this->calDavBackend->deleteCalendar($id);
  }

  /**Share the given calendar with a group.
   *
   * @bug This function uses internal APIs.
   */
  public function groupShareCalendar($calendarId, $groupId, $readOnly = false) {
    $share = [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => $readOnly,
    ];
    $calendarInfo = $this->calDavBackend->getCalendarById($calendarId);
    if (empty($calendarInfo)) {
      return false;
    }
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
    $this->refreshCalendarManager();
    return true;
  }

  /** Get a calendar with the given display name. */
  public function calendarByName($displayName)
  {
    if ($this->calendarUserId != $this->userId()) {
      $this->refreshCalendarManager();
    }
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
    if ($this->calendarUserId != $this->userId()) {
      $this->refreshCalendarManager();
    }
    foreach($this->calendarManager->getCalendars() as $calendar) {
      if ((int)$id === (int)$calendar->getKey()) {
        return $calendar;
      }
    }
    return null;
  }

  /**Force OCP\Calendar\IManager to be refreshed.
   *
   * @bug This function uses internal APIs.
   */
  private function refreshCalendarManager()
  {
    $this->calendarManager->clear();
    \OC::$server->query(\OCA\DAV\AppInfo\Application::class)->setupCalendarProvider(
      $this->calendarManager, $this->userId());
    $this->calendarUserId = $this->userId();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
