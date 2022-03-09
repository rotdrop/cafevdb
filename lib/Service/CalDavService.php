<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use OCP\Calendar\ICalendar;
use OCP\Constants;

// @@todo: replace the stuff below by more persistent APIs. As it
// shows (Sep. 2020) the only option would be http calls to the dav
// service. Even the perhaps-forthcoming writable calendar API does
// not allow the creation of calendars or altering shring options.

// missing: move/delete calendar

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;

use OCA\CAFEVDB\Common\Uuid;

class CalDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const WRITE_PERMISSIONS = (Constants::PERMISSION_CREATE|Constants::PERMISSION_UPDATE);
  const URI_SUFFIX = '.ics';

  /** @var CalDavBackend */
  private $calDavBackend;

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  /** @var int */
  private $calendarUserId;

  public function __construct(
    ConfigService $configService
    , \OCP\Calendar\IManager $calendarManager
    , CalDavBackend $calDavBackend
  ) {
    $this->configService = $configService;
    $this->calendarManager = $calendarManager;
    $this->calDavBackend = $calDavBackend;
    $this->calendarUserId = $this->userId();
    $this->l = $this->l10n();
  }

  /**Get or create a calendar.
   *
   * @param $uri Relative URI
   *
   * @param $userId part of the principal name.
   *
   * @param $displayName Display-name of the calendar.
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

  /**
   * Delete the calendar with the given id.
   *
   * @bug This function uses internal APIs.
   */
  public function deleteCalendar($id) {
    $this->calDavBackend->deleteCalendar($id);
  }

  static private function makeGroupShare(string $groupId, bool $readOnly = false)
  {
    return [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => $readOnly,
    ];
  }

  private function makeCalendar(int $calendarId)
  {
    $calendarInfo = $this->calDavBackend->getCalendarById($calendarId);
    if (empty($calendarInfo)) {
      return null;
    }
    return new Calendar($this->calDavBackend, $calendarInfo, $this->l10n(), $this->appConfig());
  }

  /**
   * Share the given calendar with a group.
   *
   * @bug This function uses internal APIs.
   */
  public function groupShareCalendar($calendarId, $groupId, $readOnly = false) {
    if ($this->isGroupSharedCalendar($calendarId, $groupId, $readOnly)) {
      return true;
    }
    $share = self::makeGroupShare($groupId, $readOnly);
    $calendar = $this->makeCalendar($calendarId);
    if (empty($calendar)) {
      return false;
    }
    $this->calDavBackend->updateShares($calendar, [$share], []);
    return $this->isGroupSharedCalendar($calendarId, $groupId, $readOnly);
  }

  /**
   * Test if the given calendar is shared with the given group
   */
  public function isGroupSharedCalendar($calendarId, $groupId, $readOnly = false)
  {
    $share = self::makeGroupShare($groupId, $readOnly);
    $shares = $this->calDavBackend->getShares($calendarId);
    foreach ($shares as $share) {
      if ($share['href'] === $share['href'] && $share['readOnly'] == $readOnly) {
        return true;
      }
    }
    return false;
  }

  public function displayName($calendarId, $displayName)
  {
    try {
      $propPatch = new \Sabre\DAV\PropPatch(['{DAV:}displayname' => $displayName]);
      $this->calDavBackend->updateCalendar($calendarId, $propPatch);
      $propPatch->commit();
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

  /**
   * Get a calendar with the given display name.
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

  /**
   * Get a calendar with the given id.
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

  /**
   * Get principal, shared and original uri from the calendar id, as well as
   * the owner-user-id.
   *
   * @return array
   * ```
   * [
   *   'principaluri' => principals/users/OWNER_ID,
   *   'owneruri' => URI_AS_SEEN_BY_OWNER,
   *   'shareuri' => URI_AS_SEEN_BY_CURRENT_USER,
   *   'ownerid' => OWNER_USER_ID,
   *   'userid' => CURRENT_USER_ID,
   * ]
   * ```
   *
   * @bug Users inernal APIs. The NC PHP API is just too incomplete.
   */
  public function calendarUris($id)
  {
    $calendarInfo = $this->calDavBackend->getCalendarById($id);
    if (!empty($calendarInfo)) {
      [,,$ownerId] = explode('/',  $calendarInfo['principaluri']);
      $userUri = ($ownerId != $this->calendarUserId)
        ? $calendarInfo['uri'] . '_shared_by_' . $ownerId
        : $calendarInfo['uri'];
      return [
        'principaluri' => $calendarInfo['principaluri'],
        'owernuri' => $calendarInfo['uri'],
        'shareuri' => $userUri,
        'ownerid' => $ownerId,
        'userid' => $this->calendarUserId,
      ];
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
          unset($calendars[$idx]);
        }
      }
      $calendars = array_values($calendars);
    }
    return $calendars;
  }

  /**
   * Create an entry in the given calendar from either a VCalendar
   * blob or a Sabre VCalendar object.
   *
   * @return string local URI of the calendar object, relative to the
   * calendar's URI.
   *
   * @bug This function uses internal APIs.
   */
  public function createCalendarObject($calendarId, $localUri, $object)
  {
    if (!is_string($object)) {
      $object = $object->serialize();
    }
    if (empty($localUri)) {
      $localUri = strtoupper(Uuid::create()->toString()).'.ics';
    }
    $this->calDavBackend->createCalendarObject($calendarId, $localUri, $object);
    return $localUri;
  }

  /**
   * Update an entry in the given calendar from either a VCalendar blob or a
   * Sabre VCalendar object.
   *
   * @bug This function uses internal APIs.
   */
  public function updateCalendarObject($calendarId, $localUri, $object)
  {
    if (!is_string($object)) {
      $object = $object->serialize();
    }
    $this->logError("calId: " . $calendarId . " uri " . $localUri);
    $this->calDavBackend->updateCalendarObject($calendarId, $localUri, $object);
  }

  /**
   * Remove the given calendar object.
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * other the UID.
   *
   * @bug This function uses internal APIs.
   */
  public function deleteCalendarObject($calendarId, $objectIdentifier)
  {
    $localUri = $this->getObjectUri($calendarId, $objectIdentifier);
    if (empty($localUri)) {
      throw new \InvalidArgumentException($this->l->t('Unable to find calendar entry with identifier "%1$s" in calendar with id "%2$s".', [ $calendarId, $objectIdentifier ]));
    }
    $this->calDavBackend->deleteCalendarObject($calendarId, $localUri);
  }

  /**
   * Fetch an event object by its local URI.
   *
   * The return value is an array with the following keys:
   *   * calendardata - The iCalendar-compatible calendar data
   *   * uri - a unique key which will be used to construct the uri. This can
   *     be any arbitrary string, but making sure it ends with '.ics' is a
   *     good idea. This is only the basename, or filename, not the full
   *     path.
   *   * lastmodified - a timestamp of the last modification time
   *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
   *   '"abcdef"')
   *   * size - The size of the calendar objects, in bytes.
   *   * component - optional, a string containing the type of object, such
   *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
   *     the Content-Type header.
   *   * calendarid - The passed argument $calendarId
   *
   * @param mixed $calendarId
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * other the UID.
   *
   * @return array|null
   *
   * @bug This function uses internal APIs. This could be changed to a
   * CalDav call which would then only return the serialized data,
   * respectively an arry/proxy object with calendarId, uri and the
   * calendar data.
   */
  public function getCalendarObject($calendarId, string  $objectIdentifier):?array
  {
    $localUri = $this->getObjectUri($calendarId, $objectIdentifier);
    if (empty($localUri)) {
      return null;
    }
    $result = $this->calDavBackend->getCalendarObject($calendarId, $localUri);
    if (!empty($result)) {
      $result['calendarid'] = $calendarId;
    }
    return $result;
  }

  /**
   * Convert an object UID to its local URI.
   *
   * @param mixed $calendarId
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * other the UID.
   *
   * @return string|null The local URI (basename).
   *
   * @bug This function uses internal APIs.
   */
  private function getObjectUri($calendarId, string $objectIdentifier):?string
  {
    if (str_ends_with($objectIdentifier, self::URI_SUFFIX)) {
      $localUri = $objectIdentifier;
    } else {
      $uid = $objectIdentifier;
      $principalUri = $this->calendarPrincipalUri($calendarId);
      if (empty($principalUri)) {
        return null;
      }
      $uri = $this->calDavBackend->getCalendarObjectByUID($principalUri, $uid);
      if (empty($uri)) {
        return null;
      }
      $localUri = basename($uri);
    }
    return $localUri;
  }

  /**
   * Force OCP\Calendar\IManager to be refreshed.
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
