<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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
 * You should have received a copy of the GNU Affero General Publicsabare how to add multiple alarms License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use \DateTimeInterface;
use \DateTimeZone;
use \DateInterval;
use \DateTimeImmutable;
use \Exception;
use \InvalidArgumentException;

use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Component as VComponent;
use Sabre\VObject\Property\ICalendar;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType as VCalendarType;

/** Operation/Builder for VCalendar objects */
class VCalendarService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var OC_Calendar_Object */
  private $legacyCalendarObject;

  const VTODO = VCalendarType::VTODO;
  const VEVENT = VCalendarType::VEVENT;
  const VCARD = VCalendarType::VCARD;
  const VJOURNAL = VCalendarType::VJOURNAL;

  const ALARM_ACTION_DISPLAY = 'DISPLAY';
  const ALARM_ACTION_AUDIO = 'AUDIO';
  const ALARM_ACTION_EMAIL = 'EMAIL';
  const ALARM_ACTION_PROCEDURE = 'PROCEDURE';

  const VALARM_FROM_START = 'START';
  const VALARM_FROM_END = 'END';

  const VTODO_STATUS_IN_PROCESS = 'IN-PROCESS';
  const VTODO_STATUS_COMPLETED = 'COMPLETED';
  const VTODO_STATUS_NEEDS_ACTION = 'NEEDS-ACTION';

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    OC_Calendar_Object $legacyCalendarObject,
  ) {
    $this->configService = $configService;
    $this->legacyCalendarObject = $legacyCalendarObject;
  }

  /**
   * Validate the given request-data which is an associative array.
   *
   * @param mixed $eventData Legacy request event data.
   *
   * @param string $kind What kind of event to create.
   *
   * @return array|false Array of error messages or false on success
   */
  public function validateRequest(mixed $eventData, string $kind = self::VEVENT)
  {
    switch ($kind) {
      case self::VEVENT:
        return $this->legacyCalendarObject->validateRequest($eventData);
      case self::VTODO:
        return $this->validateVTodoRequest($eventData);
      default:
        return null;
    }
  }

  /**
   * @param mixed $vObjectData HTTP request submitted event data.
   *
   * @param string $kind What kind of object to create.
   * ```
   * $eventData = [
   *   'title' => 'Title',
   *   'description' => 'Text',
   *   'location' => 'Where',
   *   'categories' => 'Cat1,Cat2',
   *   'priority' => true,
   *   'from' => '01-11-2020',
   *   'fromtime' => '10:20:22',
   *   'to' => '30-11-2020',
   *   'totime' => '00:00:00',
   *   'calendar' => 'calendarId',
   *   'repeat' => 'doesnotrepeat',
   *   'alarm' => [ [ 'START => -3600 ], [ 'END' => 1800 ], ]
   *   'participants' => [
   *     'organizer' => [ 'name' => NAME, 'email' => EMAIL ],
   *     'attendees' => [
   *       [
   *         'name' => NAME,
   *         'email' => EMAIL,
   *         'role' => 'PARTICIPANT',
   *         'partstat' => 'NEEDS-ACTION',
   *         'language' => ...
   *       ], ...
   *     ]
   * ];
   *
   * $taskData = [
   *   'summary' => $title, // required
   *   'related' => other VComponent's UID // optional
   *   'due' => date('d-m-Y', $timeStamp), // required
   *   'status' => 'TASK_NEEDS_ACTION ', // e.g.
   *   'start' => date('d-m-Y'), // optional
   *   'location' => 'Cyber-Space', // optional
   *   'categories' => $categories, // optional
   *   'description' => $description, // optional
   *   'calendar' => $calendarId, // required
   *   'allday' => true, // optional
   *   'alarm' => $alarm,
   * ]; // optional
   * ```.
   *
   * @return null|VCalendar
   */
  public function createVCalendarFromRequest(mixed $vObjectData, string $kind = self::VEVENT):?VCalendar
  {
    switch ($kind) {
      case self::VEVENT:
        return $this->createVEventFromRequest($vObjectData);
      case 'VTODO':
        return $this->createVTodoFromRequest($vObjectData);
      default:
        return null;
    }
  }

  /**
   * @param mixed $objectData Calendar object description from HTTP request.
   *
   * @return null|VCalendar
   */
  private function createVEventFromRequest(mixed $objectData):?VCalendar
  {
    $vObject = $this->legacyCalendarObject->createVCalendarFromRequest($objectData);
    if (empty($vObject) || empty($vObject->VEVENT)) {
      return $vObject;
    }
    $this->addVAlarmsFromRequest($vObject, $vObject->VEVENT, $objectData);
    $this->addParticipants($vObject, $vObject->VEVENT, $objectData);
    $this->addRelations($vObject, $vObject->VEVENT, $objectData);

    return $vObject;
  }

  /**
   * Add one or multiple participans to the given VTodo or VEvent.
   *
   * @param VCalendar $vRoot Root document of the $vComponent.
   *
   * @param VComponent $vComponent A VTodo ar a VEvent.
   *
   * @param mixed $objectData
   * ```
   * [ ...
   *   'participants' => [
   *     'organizer' => [ 'name' => NAME, 'email' => EMAIL ],
   *     'attendees' => [
   *       [
   *         'name' => NAME,
   *         'email' => EMAIL,
   *         'role' => 'PARTICIPANT',
   *         'partstat' => 'NEEDS-ACTION',
   *         'language' => ...
   *       ], ...
   *     ],
   *   ], ...
   * ]
   * ```
   *
   * @return void
   */
  public function addParticipants(VCalendar $vRoot, VComponent $vComponent, mixed $objectData):void
  {
    $organizer = $objectData['participants']['organizer'] ?? [];
    if (!empty($organizer)) {
      $vComponent->ORGANIZER = 'mailto:' . $organizer['email'];
      $vComponent->ORGANIZER['CN'] = $organizer['name'];
    }
    $attendees = $objectData['participants']['attendees'] ?? [];
    if (empty($attendees)) {
      return;
    }
    unset($vComponent->ATTENDEE);
    foreach ($attendees as $attendee) {
      $vComponent->add(
        'ATTENDEE',
        'mailto:' . $attendee['email'], [
          'CN' => $attendee['name'],
          'CUTYPE' => $attendee['cutype'] ?? 'INDIVIDUAL',
          'PARTSTAT' => $attendee['partstat'] ?? 'NEEDS-ACTION',
          'ROLE' => $attendee['role'] ?? 'REQ-PARTICIPANT',
          'RSVP' => $attendee['rsvp'] ?? false,
          'LANGUAGE' => $attendee['language'] ?? $this->getLanguage($this->appLocale()),
        ]
      );
    }
  }

  /**
   * Add one or multiple relations to the given VTodo or VEvent.
   *
   * @param VCalendar $vRoot Root document of the $vComponent.
   *
   * @param VComponent $vComponent A VTodo ar a VEvent.
   *
   * @param mixed $objectData
   * ```
   * [ ..., 'related' => [ UIDs, ... ], ... ]
   * [ ...,
   *   'related' => [
   *     'SIBLING' => [ UIDs, ... ],
   *     'CHILD' => [ UIDs, ... ],
   *     'PARENT' => [ UIDs, ... ],
   *   ], ...
   * ]
   * ```
   *
   * @return void
   */
  public function addRelations(VCalendar $vRoot, VComponent $vComponent, mixed $objectData):void
  {
    if (empty($objectData['related'])) {
      return;
    }
    $related = $objectData['related'];
    if (empty(array_intersect(array_keys($related), ['PARENT', 'CHILD', 'SIBLING']))) {
      $related = [ 'PARENT' => $related ];
    }
    $ownUid = $vComponent->UID;
    unset($vComponent->{'RELATED-TO'});
    foreach ($related as $relType => $uids) {
      foreach ($uids as $uid) {
        if ($uid == $ownUid) {
          continue; // gracefully ignore
        }
        $vComponent->add(
          'RELATED-TO',
          $uid, [
            'RELTYPE' => $relType,
          ],
        );
      }
    }
  }

  /**
   * Add one or multiple alarms to the given VTodo or VEvent. Any existing
   * alarms will be removed.
   *
   * @param VCalendar $vRoot Root document of the $vComponent.
   *
   * @param VComponent $vComponent A VTodo ar a VEvent.
   *
   * @param mixed $objectData Calendar object description from HTTP
   * request. If it has an array element with key 'alarm' then this may be the
   * number of alarm seconds or an array of seconds in which case multiple
   * alarms will be added. Negative values are counted relative to the end of
   * the event and reach to the feature (<- is that true?).
   *
   * @return void
   */
  public function addVAlarmsFromRequest(VCalendar $vRoot, VComponent $vComponent, mixed $objectData):void
  {
    unset($vComponent->VALARM);
    if (!isset($objectData['alarm'])) {
      return;
    }
    $alarmData = is_array($objectData['alarm']) ? $objectData['alarm'] : [ $objectData['alarm'] ?? null ];
    $this->logInfo('ALRAM DATA ' . print_r($alarmData, true));
    foreach ($alarmData as $alarmDatum) {
      if (is_array($alarmDatum)) {
        foreach ($alarmDatum as $related => $seconds) {
        }
      } else {
        $seconds = $alarmDatum;
        $related = null;
      }
      $vAlarm = $this->createVAlarmFromSeconds($vRoot, $seconds, $related, $objectData['summary']);
      if (!empty($vAlarm)) {
        $vComponent->add($vAlarm);
      }
    }
  }

  /**
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @param mixed $objectData Calendar object description from HTTP request.
   *
   * @param string $kind What kind of object to create.
   *
   * @return null|VCalendar
   */
  public function updateVCalendarFromRequest(VCalendar $vCalendar, mixed $objectData, string $kind = self::VEVENT):?VCalendar
  {
    switch ($kind) {
      case self::VEVENT:
        return $this->updateVEventFromRequest($vCalendar, $objectData);
      case 'VTODO':
        return $this->updateVTodoFromRequest($vCalendar, $objectData);
      default:
        return null;
    }
  }

  /**
   * @param VCalendar $vCalendar
   *
   * @param mixed $objectData Calendar object description from HTTP request.
   *
   * @return null|VCalendar
   */
  private function updateVEventFromRequest(VCalendar $vCalendar, mixed $objectData):VCalendar
  {
    $vCalendar = $this->legacyCalendarObject->updateVCalendarFromRequest($objectData, $vCalendar);
    if (empty($vCalendar) || empty($objectData['alarm'])) {
      return $vCalendar;
    }
    $this->addVAlarmsFromRequest($vCalendar, $vCalendar->VEVENT, $objectData);
    $this->addParticipants($vCalendar, $vCalendar->VEVENT, $objectData);
    $this->addRelations($vCalendar, $vCalendar->VEVENT, $objectData);

    return $vCalendar;
  }

  /**
   * @return OC_Calendar_Object The old Owncloud inherited calendar object.
   *
   * @todo Get rid of it.
   */
  public function legacyEventObject():OC_Calendar_Object
  {
    return $this->legacyCalendarObject;
  }

  /**
   * @param mixed $stuff String or array or an VCalendar object.
   *
   * @return VCalendar
   */
  public static function getVCalendar(mixed $stuff):?VCalendar
  {
    $data = null;
    if (is_array($stuff) && isset($stuff['calendardata'])) {
      $data = $stuff['calendardata'];
    } elseif (is_string($stuff)) {
      $data = $stuff;
    } elseif ($stuff instanceof VCalendar) {
      return $stuff;
    }
    if (empty($data)) {
      return null;
    }
    if ($data instanceof VCalendar) {
      return $data;
    }
    return VObject\Reader::read($data);
  }

  /**
   * Get all components of a given type from a VCalendar object and return
   * them as flat array.
   *
   * @param VCalendar $vCalendar VCalendar object.
   *
   * @param string $type Defaults to 'VEVENT'
   *
   * @return array
   */
  public static function getAllVObjects(VCalendar $vCalendar, string $type = self::VEVENT):array
  {
    $vObjects = [];
    foreach ($vCalendar->children() as $child) {
      if (!($child instanceof VComponent)) {
        continue;
      }

      if ($child->name !== $type) {
        continue;
      }

      $vObjects[] = $child;
    }
    return $vObjects;
  }

  /**
   * Return the "master" wrapped object, where the VCalendar object here is
   * rather not an entire calendar, but one item from the database. Still it
   * may contain more than one Component, e.g. for recurring events. In this
   * case the "master" item is returned.
   *
   * @param VCalendar $vCalendar VCalendar object.
   *
   * @return The inner object, or one of the inner objects.
   */
  public static function getVObject(VCalendar $vCalendar):VComponent
  {
    if (isset($vCalendar->VEVENT)) {
      $vObject = $vCalendar->VEVENT;
      foreach ($vObject as $instance) {
        if ($instance->{'RECURRENCE-ID'} === null) {
          break;
        }
        $instance = null;
      }
      if ($instance) {
        $vObject = $instance;
      }
    } elseif (isset($vCalendar->VTODO)) {
      $vObject = $vCalendar->VTODO;
    } elseif (isset($vCalendar->VJOURNAL)) {
      $vObject = $vCalendar->VJOURNAL;
    } elseif (isset($vCalendar->VCARD)) {
      $vObject = $vCalendar->VCARD;
    } else {
      throw new Exception('Called with empty or no VComponent');
    }
    return $vObject;
  }

  /**
   * Return the type of the respective calendar object.
   *
   * @param VCalendar $vCalendar Sabre DAV calendar object.
   *
   * @return string Either VEVENT, VTODO, VJOURNAL or VCARD.
   */
  public static function getVObjectType(VCalendar $vCalendar):string
  {
    if (isset($vCalendar->VEVENT)) {
      return self::VEVENT;
    } elseif (isset($vCalendar->VTODO)) {
      return self::VTODO;
    } elseif (isset($vCalendar->VJOURNAL)) {
      return self::VJOURNAL;
    } elseif (isset($vCalendar->VCARD)) {
      return self::VCARD;
    } else {
      throw new InvalidArgumentException('Called with empty or no VComponent');
    }
    return null;
  }

  /**
   * Determine if the given event is recurring or not.
   *
   * @param VComponent $vComponent Sabre VCalendar or VEvent object.
   *
   * @return bool
   */
  public static function isEventRecurring(VComponent $vComponent):bool
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    return isset($vObject->RRULE) || isset($vObject->RDATE);
  }

  /**
   * Return the category list for the given object
   *
   * @param VComponent $vComponent Sabre VCalendar or VEvent object.
   *
   * @return An array with the categories for the object.
   */
  public static function getCategories(VComponent $vComponent):array
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    return isset($vObject->CATEGORIES) ? $vObject->CATEGORIES->getParts() : [];
  }

  /**
   * @param VComponent $vComponent Sabre VCalendar or VEvent object.
   *
   * @param array $categories Array of strings.
   *
   * @return VComponent Pass through of $vComponent.
   */
  public static function setCategories(VComponent $vComponent, array $categories):VComponent
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    $vObject->CATEGORIES = array_unique($categories);

    return $vComponent;
  }

  /**
   * @param VComponent $vComponent Sabre VCalendar or VEvent object.
   *
   * @return null|string The UID of $vCalendar.
   */
  public static function getUid(VComponent $vComponent):?string
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }

    return isset($vObject->UID) ? $vObject->UID : null;
  }

  /**
   * @param VComponent $vComponent Sabre VCalendar or VEvent object.
   *
   * @param string $uid The uid to set.
   *
   * @return VCalendar Pass through of $vCalendar.
   */
  public static function setUid(VComponent $vComponent, string $uid):VComponent
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    $vObject->UID = $uid;

    return $vComponent;
  }

  /**
   * @param VComponent $vComponent Sabre calendar object.
   *
   * @return null|string The summary comment of $vCalendar.
   */
  public static function getSummary(VComponent $vComponent):?string
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }

    return isset($vObject->SUMMARY) ? $vObject->SUMMARY : null;
  }

  /**
   * @param VComponent $vComponent Object reference.
   *
   * @param null|string $summary The summary to set.
   *
   * @return VComponent Pass through of $vComponent.
   */
  public static function setSummary(VComponent $vComponent, ?string $summary):VComponent
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    $vObject->SUMMARY = $summary;

    return $vComponent;
  }

  /**
   * @param VComponent $vComponent Sabre calendar object.
   *
   * @return null|string The summary comment of $vCalendar.
   */
  public static function getDescription(VComponent $vComponent):?string
  {
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }

    return isset($vObject->DESCRIPTION) ? $vObject->DESCRIPTION : null;
  }

  /**
   * @param VComponent $vComponent Object reference.
   *
   * @param null|string $description The description to set.
   *
   * @return VComponent Pass through of $vComponent.
   */
  public static function setDescription(VComponent $vComponent, ?string $description):VComponent
  {
    // get the inner object
    if ($vComponent instanceof VCalendar) {
      // get the inner object
      $vObject = self::getVObject($vComponent);
    } else {
      $vObject = $vComponent;
    }
    $vObject->DESCRIPTION = $description;

    return $vComponent;
  }

  /**
   * @param VComponent $vObject VEvent or anything else with DTEND or DTSTART
   * + DURATION.
   *
   * @return mixed DTEND property if set, otherwise DTSTART + duration.
   */
  public static function getDTEnd(VComponent $vObject):ICalendar\DateTime
  {
    if ($vObject->DTEND) {
      return $vObject->DTEND;
    }
    $dtEnd = clone $vObject->DTSTART;
    if ($vObject->DURATION) {
      $duration = strval($vObject->DURATION);
      $invert = 0;
      if ($duration[0] == '-') {
        $duration = substr($duration, 1);
        $invert = 1;
      }
      if ($duration[0] == '+') {
        $duration = substr($duration, 1);
      }
      $interval = new DateInterval($duration);
      $interval->invert = $invert;
      $dtEnd->getDateTime()->add($interval);
    }
    return $dtEnd;
  }

  /**
   * Validate the given request-data which is an associative array.
   *
   * @param array $todoData Legacy request event data.
   *
   * @return array|false Array of error messages or false on success
   */
  private function validateVTodoRequest(array $todoData)
  {
    $requiredKeys = [
      'summary',
      'due',
      'start'
    ];
    $errArr = [];
    foreach ($requiredKeys as $key) {
      if (empty($todoData[$key])) {
        $errArr[$key] = true;
      }
    }
    return empty($errArr) ? false : $errArr;
  }

  /**
   * @param array $todoData Calendar object description from HTTP request.
   * ```
   * $taskData = [
   *   'summary' => $title, // required
   *   'related' => other VComponent's UID // optional
   *   'due' => date('d-m-Y', $timeStamp), // required
   *   'status' => 'TASK_NEEDS_ACTION ', // e.g.
   *   'start' => date('d-m-Y'), // optional
   *   'location' => 'Cyber-Space', // optional
   *   'categories' => $categories, // optional
   *   'description' => $description, // optional
   *   'calendar' => $calendarId, // required
   *   'allday' => true, // optional
   *   'alarm' => $alarm,
   * ]; // optional
   * ```.
   *
   * @return VCalendar|null
   */
  private function createVTodoFromRequest(array $todoData):?VCalendar
  {
    if (!empty($this->validateVTodoRequest($todoData))) {
      return null;
    }

    $vCalendar = new VCalendar;
    $vCalendar->PRODID = "Nextloud cafevdb " . $this->appVersion();
    $vCalendar->VERSION = '2.0';

    $vTodo = $vCalendar->createComponent('VTODO');

    $vTodo->CREATED = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $vTodo->UID = VObject\UUIDUtil::getUUID();

    $vCalendar->add($vTodo);

    return $this->updateVTodoFromRequest($vCalendar, $todoData);
  }

  /**
   * Update task from request.
   *
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @param array $request HTTP request data.
   * ```
   * $taskData = [
   *   'summary' => $title, // required
   *   'related' => other VComponent's UID // optional
   *   'due' => date('d-m-Y', $timeStamp), // required
   *   'status' => 'TASK_NEEDS_ACTION ', // e.g.
   *   'start' => date('d-m-Y'), // optional
   *   'location' => 'Cyber-Space', // optional
   *   'categories' => $categories, // optional
   *   'description' => $description, // optional
   *   'calendar' => $calendarId, // required
   *   'allday' => true, // optional
   *   'alarm' => $alarm,
   * ]; // optional
   * ```.
   *
   * @return VCalendar|null
   */
  private function updateVTodoFromRequest(VCalendar $vCalendar, array $request):VCalendar
  {
    if (!empty($this->validateVTodoRequest($request))) {
      return null;
    }

    $vTodo = $vCalendar->VTODO;
    $timezone = $this->getDateTimeZone();

    $vTodo->{'LAST-MODIFIED'} = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $vTodo->DTSTAMP = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $vTodo->SUMMARY = $request['summary'];

    if (!empty($request['due'])) {
      $due = new DateTimeImmutable($request['due'], $timezone);
      if ($request['allday']) {
        $due = $due->setTime(0, 0, 0);
        $vTodo->DUE = $due;
        $vTodo->DUE['VALUE'] = 'DATE';
      } else {
        $vTodo->DUE = $due;
      }
    }
    if (!empty($request['start'])) {
      $dtStart = new DateTimeImmutable($request['start'], $timezone);
      if ($request['allday']) {
        $dtStart = $dtStart->setTime(0, 0, 0);
        $vTodo->DTSTART = $dtStart;
        $vTodo->DTSTART['VALUE'] = 'DATE';
      } else {
        $vTodo->DTSTART = $dtStart;
      }
    }

    $optionalKeys = [
      'priority' => 'PRIORITY',
      'description' => 'DESCRIPTION',
      'location' => 'LOCATION',
      'status' => 'STATUS',
    ];
    foreach ($optionalKeys as $rqKey => $vKey) {
      if (!empty($request[$rqKey])) {
        $vTodo->{$vKey} = $request[$rqKey];
      } else {
        unset($vTodo->{$vKey});
      }
    }

    $categories = [];
    if (!empty($request['categories'])) {
      $categories = explode(',', $request['categories']);
    }
    if (count($categories)) {
      $vTodo->CATEGORIES = $categories;
    } else {
      unset($vTodo->{'CATEGORIES'});
    }

    $this->addVAlarmsFromRequest($vCalendar, $vCalendar->VTODO, $request);
    $this->addParticipants($vCalendar, $vCalendar->VTODO, $request);
    $this->addRelations($vCalendar, $vCalendar->VTODO, $request);

    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar CalDAV root document.
   *
   * @param int $seconds Alarm seconds.
   *
   * @param null|string $related Either START or END, if null negative seconds
   * count from the end, positive from the start.
   *
   * @param null|string $description Alarm description.
   *
   * @param string $action What to do with the alarm.
   *
   * @return null|VAlarm
   */
  private function createVAlarmFromSeconds(
    VCalendar $vCalendar,
    int $seconds,
    ?string $related = null,
    ?string $description = null,
    string $action = self::ALARM_ACTION_DISPLAY,
  ):?VAlarm {
    if (empty($seconds)) {
      return null;
    }
    /*
BEGIN:VALARM
DESCRIPTION:
ACTION:DISPLAY
TRIGGER;VALUE=DURATION:-P1D
X-KDE-KCALCORE-ENABLED:TRUE
END:VALARM
    */
    $inverted = $seconds < 0;
    if ($inverted) {
      $seconds = -$seconds;
      $related = $related ?? self::VALARM_FROM_END;
    } else {
      $related = $related ?? self::VALARM_FROM_START;
    }
    $vAlarm = $vCalendar->createComponent('VALARM');
    $vAlarm->DESCRIPTION = !empty($description) ? $description : $this->l->t('Default Event Notification');
    $vAlarm->ACTION = $action;
    if ($inverted) {
      $dinterval = (new DateTimeImmutable)->add(new DateInterval('PT' . ($seconds + 1) . 'S'));
    } else {
      $dinterval = (new DateTimeImmutable)->sub(new DateInterval('PT' . $seconds . 'S'));
    }
    $interval = $dinterval->diff(new DateTimeImmutable);
    $alarmValue = sprintf(
      '%sP%s%s%s%s',
      $interval->format('%r'),
      $interval->format('%d') > 0 ? $interval->format('%dD') : null,
      ($interval->format('%h') > 0 || $interval->format('%i') > 0) ? 'T' : null,
      $interval->format('%h') > 0 ? $interval->format('%hH') : null,
      $interval->format('%i') > 0 ? $interval->format('%iM') : null);
    $vAlarm->add('TRIGGER', $alarmValue, [ 'VALUE' => 'DURATION', 'RELATED' => $related ]);

    // $this->logInfo('TRY CREATE ALARM ' . $vAlarm->TRIGGER . ' SECONDS ' . ($inverted ? '-' : '') . $seconds);

    return $vAlarm;
  }
}
