<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use Exception;
use InvalidArgumentException;
use Sabre\DAV;

use OCP\Calendar\IManager as CalendarManager;
use OCP\Calendar\ICalendar;
use OCP\Constants;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Exceptions;

/**
 * Service class in order to interface to the dav app of Nextcloud
 *
 * Missing: move/delete calendar
 *
 * @todo: replace the stuff below by more persistent APIs. As it shows
 * (Sep. 2020) the only option would be http calls to the dav service. Even
 * the perhaps-forthcoming writable calendar API does not allow the creation
 * of calendars or altering sharing options.
 */
class CalDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const WRITE_PERMISSIONS = (Constants::PERMISSION_CREATE|Constants::PERMISSION_UPDATE);
  const URI_SUFFIX = '.ics';

  /** @var CalDavBackend */
  private $calDavBackend;

  /** @var CalendarManager */
  private $calendarManager;

  /** @var int */
  private $calendarUserId;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    CalendarManager $calendarManager,
    CalDavBackend $calDavBackend,
  ) {
    $this->configService = $configService;
    $this->calendarManager = $calendarManager;
    $this->calDavBackend = $calDavBackend;
    $this->calendarUserId = $this->userId();
    $this->l = $this->l10n();
  }

  /**
   * Get or create a calendar.
   *
   * @param string $uri Relative URI.
   *
   * @param null|string $displayName Display-name of the calendar.
   *
   * @param null|string $userId part of the principal name.
   *
   * @return int Calendar id.
   *
   * @bug This function uses internal APIs.
   */
  public function createCalendar(string $uri, ?string $displayName = null, ?string $userId = null):int
  {
    empty($userId) && ($userId = $this->userId());
    empty($displayName)&& ($displayName = ucfirst($uri));
    $principal = "principals/users/$userId";

    $calendar = $this->calDavBackend->getCalendarByUri($principal, $uri);
    if (!empty($calendar)) {
      return $calendar['id'];
    } else {
      try {
        $calendarId = $this->calDavBackend->createCalendar($principal, $uri, [
          '{DAV:}displayname' => $displayName,
        ]);
        $this->refreshCalendarManager();
        return $calendarId;
      } catch (Exception $e) {
        $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      }
    }
    return -1;
  }

  /**
   * Delete the calendar with the given id.
   *
   * @param int $id Calendar id.
   *
   * @return void
   *
   * @bug This function uses internal APIs.
   */
  public function deleteCalendar(int $id):void
  {
    $this->calDavBackend->deleteCalendar($id);
  }

  /**
   * @param string $groupId GID.
   *
   * @param bool $readOnly Share read-only.
   *
   * @return array
   */
  private static function makeGroupShare(string $groupId, bool $readOnly = false):array
  {
    return [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => $readOnly,
    ];
  }

  /**
   * Create a new calendar object.
   *
   * @param int $calendarId
   *
   * @return null|Calendar
   */
  private function makeCalendar(int $calendarId):?Calendar
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
   * @param int $calendarId Numeric calendar id.
   *
   * @param string $groupId Cloud group id.
   *
   * @param bool $readOnly Whether to share read-only.
   *
   * @return bool Whether the group-share request succeeded.
   *
   * @bug This function uses internal APIs.
   */
  public function groupShareCalendar(int $calendarId, string $groupId, bool $readOnly = false):bool
  {
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
   * Test if the given calendar is shared with the given group.
   *
   * @param int $calendarId Numeric calendar id.
   *
   * @param string $groupId Cloud group id.
   *
   * @param bool $readOnly Whether to share read-only.
   *
   * @return bool Result of the test.
   */
  public function isGroupSharedCalendar(int $calendarId, string $groupId, bool $readOnly = false):bool
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

  /**
   * Set the display name.
   *
   * @param int $calendarId Numeric calendar id.
   *
   * @param string $displayName The display name to set.
   *
   * @return bool Whether the attempt succeeded.
   */
  public function displayName(int $calendarId, string $displayName):bool
  {
    try {
      $propPatch = new DAV\PropPatch(['{DAV:}displayname' => $displayName]);
      $this->calDavBackend->updateCalendar($calendarId, $propPatch);
      $propPatch->commit();
    } catch (Exception $e) {
      $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      return false;
    }
    $this->refreshCalendarManager();
    return true;
  }

  /**
   * @param string $pattern which should match within the $searchProperties.
   *
   * @param array $searchProperties defines the properties within the query pattern should match.
   *
   * @param array $options - optional parameters:
   * ['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]].
   *
   * @param integer|null $limit - limit number of search results.
   *
   * @param integer|null $offset - offset for paging of search results.
   *
   * @return array an array of events/journals/todos which are arrays of key-value-pairs.
   *
   * @see CalendarManager::search()
   */
  public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null)
  {
    return $this->calendarManager->search($pattern, $searchProperties, $options, $limit, $offset);
  }

  /**
   * Get a calendar with the given display name.
   *
   * @param string $displayName Search criterion.
   *
   * @return null|ICalendar
   */
  public function calendarByName(string $displayName):?ICalendar
  {
    if ($this->calendarUserId != $this->userId()) {
      $this->refreshCalendarManager();
    }
    foreach ($this->calendarManager->getCalendars() as $calendar) {
      if ($displayName === $calendar->getDisplayName()) {
        return $calendar;
      }
    }
    return null;
  }

  /**
   * Get a calendar with the given id.
   *
   * @param int $id Numeric calendar id.
   *
   * @return ICalendar
   */
  public function calendarById(int $id):?ICalendar
  {
    if ($this->calendarUserId != $this->userId()) {
      $this->refreshCalendarManager();
    }
    foreach ($this->calendarManager->getCalendars() as $calendar) {
      if ((int)$id === (int)$calendar->getKey()) {
        return $calendar;
      }
    }
    return null;
  }

  /**
   * Get the uri of the original calendar
   *
   * @param int $id Numeric calendar id.
   *
   * @return null|string Calendar URI.
   */
  public function calendarPrincipalUri(int $id):?string
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
   * @param int $id Numeric calendar id.
   *
   * @return null|array
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
  public function calendarUris(int $id):?array
  {
    $calendarInfo = $this->calDavBackend->getCalendarById($id);
    if (!empty($calendarInfo)) {
      [,,$ownerId] = explode('/', $calendarInfo['principaluri']);
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

  /**
   * @param ICalendar $calendar Cloud calendar instance.
   *
   * @return bool Whether the calendar is writable.
   */
  private function calendarWritable(ICalendar $calendar):bool
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
      foreach ($calendars as $idx => $calendar) {
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
   * @param int $calendarId Numeric calendar id.
   *
   * @param null|string $localUri Local URI to use or null in which case an
   * URI based on a UUID will be generated.
   *
   * @param mixed $object Calendar data.
   *
   * @return string local URI of the calendar object, relative to the
   * calendar's URI.
   *
   * @bug This function uses internal APIs.
   */
  public function createCalendarObject(int $calendarId, ?string $localUri, mixed $object)
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
   * @param int $calendarId Numeric calendar id.
   *
   * @param null|string $localUri Local URI to use.
   *
   * @param mixed $object Calendar data.
   *
   * @return void
   *
   * @bug This function uses internal APIs.
   */
  public function updateCalendarObject(int $calendarId, string $localUri, mixed $object):void
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
   * @param int $calendarId Numeric calendar id.
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * other the UID.
   *
   * @return void
   *
   * @bug This function uses internal APIs.
   */
  public function deleteCalendarObject(int $calendarId, string $objectIdentifier):void
  {
    $localUri = $this->getObjectUri($calendarId, $objectIdentifier);
    if (empty($localUri)) {
      throw new Exceptions\CalendarEntryNotFoundException($this->l->t('Unable to find calendar entry with identifier "%1$s" in calendar with id "%2$s".', [ $calendarId, $objectIdentifier ]));
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
   * @param int $calendarId
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * otherwise the UID.
   *
   * @return array|null
   *
   * @bug This function uses internal APIs. This could be changed to a
   * CalDav call which would then only return the serialized data,
   * respectively an arry/proxy object with calendarId, uri and the
   * calendar data.
   */
  public function getCalendarObject(int $calendarId, string $objectIdentifier):?array
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
   * @param int $calendarId
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If $objectIdentifier ends with '.ics' it is assumed to be an URI,
   * otherwise it is treated as UID.
   *
   * @return string|null The local URI (basename).
   *
   * @bug This function uses internal APIs.
   */
  private function getObjectUri(int $calendarId, string $objectIdentifier):?string
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
   * Force \OCA\DAV\CalDAV\CalendarManager to be refreshed.
   *
   * @return void
   *
   * @bug This function uses internal APIs.
   */
  private function refreshCalendarManager():void
  {
    $this->calendarManager->clear();
    \OC::$server->query(\OCA\DAV\CalDAV\CalendarManager::class)->setupCalendarProvider(
      $this->calendarManager, $this->userId());
    $this->calendarUserId = $this->userId();
  }
}
