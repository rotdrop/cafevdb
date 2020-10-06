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

use OCP\Calendar\ICalendar;
use OCP\Constants;

// @@TODO: replace the stuff below by more persistent APIs. As it
// shows (Sep. 2020) the only option would be http calls to the dav
// service. Even the perhaps-forthcoming writable calendar API does
// not allow the creation of calendars or altering shring options.

// missing: move/delete calendar

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;

use Ramsey\Uuid\Uuid;

class CalDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const WRITE_PERMISSIONS = (Constants::PERMISSION_CREATE|Constants::PERMISSION_UPDATE);

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

  /**
   * @param string $pattern which should match within the $searchProperties
   * @param array $searchProperties defines the properties within the query pattern should match
   * @param array $options - optional parameters:
   * 	['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]]
   * @param integer|null $limit - limit number of search results
   * @param integer|null $offset - offset for paging of search results
   * @return array an array of events/journals/todos which are arrays of key-value-pairs
   * @since 13.0.0
   */
  public function search($pattern, array $searchProperties=[], array $options=[], $limit=null, $offset=null)
  {
    return $this->calendarManager->search($pattern, $searchProperties, $options, $limit, $offset);
  }

  /** Get a calendar with the given display name.
   *
   * @return ICalendar[]
   */
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

  /** Get a calendar with the given its id.
   *
   * @return ICalendar[]
   */
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

  /** Get the uri of the original calendar */
  public function calendarPrincipalUri($id)
  {
    $calendarInfo = $this->calDavBackend->getCalendarById($id);
    if (!empty($calendarInfo)) {
      return $calendarInfo['principaluri'];
    }
    return null;
  }

  /** Get a calendar with the given its uri.
   *
   * @return ICalendar[]
   *
   * @bug This function uses internal APIs.
   */
  public function calendarByUri($uri, $userId = null)
  {
    empty($userId) && $userId = $this->userId();
    $principal = "principals/users/$userId";

    $calendar = $this->calDavBackend->getCalendarByUri($principal, $uri);
    if (!empty($calendar) && isset($calendar['id']))  {
      return $this->calendarById($calendar['id']);
    }
    return null;
  }

  private function calendarWritable(ICalendar $calendar)
  {
    $perms = $calendar->getPermissions();
    return ($perms & self::WRITE_PERMISSIONS) == self::WRITE_PERMISSIONS;
  }

  /** Get the list of all calendars
   *
   * @param bool $writable If true return only calendars with write access.
   *
   * @return ICalendar[]
   */
  public function getCalendars(bool $writable = false)
  {
    $calendars = $this->calendarManager->getCalendars();
    if ($writable) {
      foreach($calendars as $idx => $calendar) {
        if (!$this->calendarWritable($calendar)) {
          unset($calendars[idx]);
        }
      }
      $calendars = array_values($calendars);
    }
    return $calendars;
  }

  /** Create an entry in the given calendar from either a VCalendar
   ** blob or a Sabre VCalendar object.
   *
   * @bug This function uses internal APIs.
   */
  public function createCalendarObject($calendarId, $object)
  {
    if (!is_string($object)) {
      $object = $object->serialize();
    }
    $localUri = strtoupper(Uuid::uuid4()->toString()).'.ics';
    $this->calDavBackend->createCalendarObject($calendarId, $localUri, $object);
    return $localUri;
  }

  /** Update an entry in the given calendar from either a VCalendar
   ** blob or a Sabre VCalendar object.
   *
   * @bug This function uses internal APIs.
   */
  public function updateCalendarObject($calendarId, $localUri, $object)
  {
    if (!is_string($object)) {
      $object = $object->serialize();
    }
    $this->calDavBackend->updateCalendarObject($calendarId, $localUri, $object);
  }

  /** Fetch an event object by its local URI.
   *
   * @bug This function uses internal APIs.
   */
  public function getCalendarObject($calendarId, $localUri)
  {
    return $this->calDavBackend->getCalendarObject($calendarId, $localUri);
  }

  /**Force OCP\Calendar\IManager to be refreshed.
   *
   * @bug This function uses internal APIs.
   */
  private function refreshCalendarManager()
  {
    $this->calendarManager->clear();
    \OC::$server->query(\OCA\DAV\CalDAV\CalendarManager::class)->setupCalendarProvider(
      $this->calendarManager, $this->userId());
    $this->calendarUserId = $this->userId();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
