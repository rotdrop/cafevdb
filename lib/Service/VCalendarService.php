<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use \DateTimeInterface;
use \DateTimeZone;
use \DateInterval;
use \DateTimeImmutable;
use \Exception;
use \InvalidArgumentException;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Component as VObject;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;

/** Operation/Builder for VCalendar objects */
class VCalendarService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var OC_Calendar_Object */
  private $legacyCalendarObject;

  const VTODO = 'VTODO';
  const VEVENT = 'VEVENT';
  const ALARM_ACTION_DISPLAY = 'DISPLAY';
  const ALARM_ACTION_AUDIO = 'AUDIO';
  const ALARM_ACTION_EMAIL = 'EMAIL';
  const ALARM_ACTION_PROCEDURE = 'PROCEDURE';

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
   * @param array $eventData Legacy request event data.
   *
   * @param string $kind What kind of event to create.
   *
   * @return array|false Array of error messages or false on success
   */
  public function validateRequest(array $eventData, string $kind = self::VEVENT)
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
   * @param array $vObjectData HTTP request submitted event data.
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
   * ];
   *
   * $taskData = array('summary' => $title, // required
   *                   'related' => other VObject's UID // optional
   *                   'due' => date('d-m-Y', $timeStamp), // required
   *                   'start' => date('d-m-Y'), // optional
   *                   'location' => 'Cyber-Space', // optional
   *                   'categories' => $categories, // optional
   *                   'description' => $description, // optional
   *                   'calendar' => $calendarId, // required
   *                   'starred' => true, // optional
   *                   'alarm' => $alarm); // optional
   * ```.
   *
   * @return null|VCalendar
   */
  public function createVCalendarFromRequest(array $vObjectData, string $kind = self::VEVENT):?VCalendar
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
   * @param array $objectData Calendar object description from HTTP request.
   *
   * @return null|VCalendar
   */
  private function createVEventFromRequest(array $objectData):?VCalendar
  {
    $vObject = $this->legacyCalendarObject->createVCalendarFromRequest($objectData);
    if (empty($vObject) || empty($objectData['alarm'])) {
      return $vObject;
    }
    if (!empty($objectData['alarm'])) {
      $vObject->VEVENT->add('VALARM', $this->createVAlarmFromSeconds($vObject, $objectData['alarm'], $objectData['summary']));
    }
    return $vObject;
  }

  /**
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @param array $objectData Calendar object description from HTTP request.
   *
   * @param string $kind What kind of object to create.
   *
   * @return null|VCalendar
   */
  public function updateVCalendarFromRequest(VCalendar $vCalendar, array $objectData, string $kind = self::VEVENT):?VCalendar
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
   * @param array $objectData Calendar object description from HTTP request.
   *
   * @return null|VCalendar
   */
  private function updateVEventFromRequest(VCalendar $vCalendar, array $objectData):VCalendar
  {
    $vCalendar = $this->legacyCalendarObject->updateVCalendarFromRequest($objectData, $vCalendar);
    if (empty($vCalendar) || empty($objectData['alarm'])) {
      return $vCalendar;
    }
    unset($vCalendar->VEVENT->{'VALARM'});
    if (!empty($objectData['alarm'])) {
      $vCalendar->VEVENT->add('VALARM', $this->createVAlarmFromSeconds($vCalendar, $objectData['alarm'], $objectData['summary']));
    }
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
  public static function getVCalendar(mixed $stuff):VCalendar
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
    return VObject\Reader::read($stuff['calendardata']);
  }

  /**
   * Return a reference to the object contained in a Sabre VCALENDAR
   * object. This is a reference to allow for modification of the
   * $vCalendar object.
   *
   * @param VCalendar $vCalendar VCalendar object.
   *
   * @return A reference to the inner object.
   */
  public static function &getVObject(VCalendar &$vCalendar):VObject
  {
    if (isset($vCalendar->VEVENT)) {
      $vobject = &$vCalendar->VEVENT;
    } elseif (isset($vCalendar->VTODO)) {
      $vobject = &$vCalendar->VTODO;
    } elseif (isset($vCalendar->VJOURNAL)) {
      $vobject = &$vCalendar->VJOURNAL;
    } elseif (isset($vCalendar->VCARD)) {
      $vobject = &$vCalendar->VCARD;
    } else {
      throw new Exception('Called with empty or no VObject');
    }
    return $vobject;
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
      return 'VEVENT';
    } elseif (isset($vCalendar->VTODO)) {
      return 'VTODO';
    } elseif (isset($vCalendar->VJOURNAL)) {
      return 'JVOURNAL';
    } elseif (isset($vCalendar->VCARD)) {
      return 'VCARD';
    } else {
      throw new InvalidArgumentException('Called with empty of no VObject');
    }
    return null;
  }

  /**
   * Return the category list for the given object
   *
   * @param VCalendar $vCalendar Sabe vCalendar object.
   *
   * @return An array with the categories for the object.
   */
  public static function getCategories(VCalendar $vCalendar):array
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);

    return isset($vObject->CATEGORIES) ? $vObject->CATEGORIES->getParts() : [];
  }

  /**
   * @param VCalendar $vCalendar Object reference.
   *
   * @param array $categories Array of strings.
   *
   * @return VCalendar Pass through of $vCalendar.
   *
   * @todo Check whether we really need a reference here.xy
   */
  public static function setCategories(VCalendar &$vCalendar, array $categories):VCalendar
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);
    $vObject->CATEGORIES = $categories;
    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @return null|string The UID of $vCalendar.
   */
  public static function getUid(VCalendar $vCalendar):?string
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);

    return isset($vObject->UID) ? $vObject->UID : null;
  }

  /**
   * @param VCalendar $vCalendar Object reference.
   *
   * @param string $uid The uid to set.
   *
   * @return VCalendar Pass through of $vCalendar.
   *
   * @todo Check whether we really need a reference here.xy
   */
  public static function setUid(VCalendar &$vCalendar, string $uid):VCalendar
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);
    $vObject->UID = $uid;

    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @return null|string The summary comment of $vCalendar.
   */
  public static function getSummary(VCalendar $vCalendar):?string
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);

    return isset($vObject->SUMMARY) ? $vObject->SUMMARY : null;
  }

  /**
   * @param VCalendar $vCalendar Object reference.
   *
   * @param null|string $summary The summary to set.
   *
   * @return VCalendar Pass through of $vCalendar.
   *
   * @todo Check whether we really need a reference here.xy
   */
  public static function setSummary(VCalendar &$vCalendar, ?string $summary):VCalendar
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);
    $vObject->SUMMARY = $summary;

    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @return null|string The summary comment of $vCalendar.
   */
  public static function getDescription(VCalendar $vCalendar):?string
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);

    return isset($vObject->DESCRIPTION) ? $vObject->DESCRIPTION : null;
  }

  /**
   * @param VCalendar $vCalendar Object reference.
   *
   * @param null|string $description The description to set.
   *
   * @return VCalendar Pass through of $vCalendar.
   *
   * @todo Check whether we really need a reference here.xy
   */
  public static function setDescription(VCalendar &$vCalendar, ?string $description):VCalendar
  {
    // get the inner object
    $vObject = self::getVObject($vCalendar);
    $vObject->DESCRIPTION = $description;
    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar Object reference.
   *
   * @return DateTimeInterface DTEND property if set, otherwise DTSTART + duration.
   */
  public static function getDTEnd(VCalendar $vCalendar):DateTimeInterface
  {
    $vObject = self::getVObject($vCalendar);
    if ($vObject->DTEND) {
      return $vObject->DTEND;
    }
    $dtEnd = clone $vObject->DTSTART;
    // clone creates a shallow copy, also clone DateTime
    $dtEnd->setDateTime(clone $dtEnd->getDateTime());
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
   * $taskData = array('summary' => $title, // required
   *                   'related' => other VObject's UID // optional
   *                   'due' => date('d-m-Y', $timeStamp), // required
   *                   'start' => date('d-m-Y'), // optional
   *                   'location' => 'Cyber-Space', // optional
   *                   'categories' => $categories, // optional
   *                   'description' => $description, // optional
   *                   'calendar' => $calendarId, // required
   *                   'starred' => true, // optional
   *                   'alarm' => $alarm); // optional
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

    $vTodo->UID = \Sabre\VObject\UUIDUtil::getUUID();

    $vCalendar->add($vTodo);

    return $this->updateVTodoFromRequest($vCalendar, $todoData);
  }

  /**
   * Update task from request.
   *
   * @param VCalendar $vCalendar Sabre calendar object.
   *
   * @param array $request HTTOP request data.
   * ```
   * $taskData = array('summary' => $title, // required
   *                   'related' => other VObject's UID // optional
   *                   'due' => date('d-m-Y', $timeStamp), // required
   *                   'start' => date('d-m-Y'), // optional
   *                   'location' => 'Cyber-Space', // optional
   *                   'categories' => $categories, // optional
   *                   'description' => $description, // optional
   *                   'calendar' => $calendarId, // required
   *                   'starred' => true, // optional
   *                   'alarm' => $alarm); // optional
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
      $vTodo->DUE = new DateTimeImmutable($request['due'], $timezone);
    }
    if (!empty($request['start'])) {
      $vTodo->DTSTART = new DateTimeImmutable($request['start'], $timezone);
    }

    $optionalKeys = [
      'priority' => 'PRIORITY',
      'related' => 'RELATED-TO',
      'description' => 'DESCRIPTION',
      'location' => 'LOCATION',
    ];
    foreach ($optionalKeys as $rqKey => $vKey) {
      if (!empty($request[$rqKey]) && $request[$rqKey]) {
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

    unset($vTodo->{'VALARM'});
    if (!empty($request['alarm'])) {
      $vTodo->add('VALARM', $this->createVAlarmFromSeconds($vCalendar, -$request['alarm'], $request['summary']));
    }

    $vCalendar->VTODO = $vTodo;

    return $vCalendar;
  }

  /**
   * @param VCalendar $vCalendar
   *
   * @param int $seconds Alarm seconds.
   *
   * @param null|string $description Alarm description.
   *
   * @param string $action What to do with the alarm.
   *
   * @return null|VAlarm
   */
  private function createVAlarmFromSeconds(VCalendar $vCalendar, int $seconds, ?string $description = null, string $action = self::ALARM_ACTION_DISPLAY):?VAlarm
  {
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
      $sign = '-';
      $related = 'END';
    } else {
      $sign = '';
      $related = 'START';
    }
    $vAlarm = $vCalendar->createComponent('VALARM');
    $vAlarm->DESCRIPTION = !empty($description) ? $description : $this->l->t('Default Event Notification');
    $vAlarm->ACTION = $action;
    $dinterval = new DateTimeImmutable();
    $dinterval->add(new DateInterval('PT'.$seconds.'S'));
    $interval = $dinterval->diff(new DateTimeImmutable);
    $alarmValue = sprintf(
      '%sP%s%s%s%s',
      $interval->format('%r'),
      $interval->format('%d') > 0 ? $interval->format('%dD') : null,
      ($interval->format('%h') > 0 || $interval->format('%i') > 0) ? 'T' : null,
      $interval->format('%h') > 0 ? $interval->format('%hH') : null,
      $interval->format('%i') > 0 ? $interval->format('%iM') : null);
    $vAlarm->add('TRIGGER', $sign.$alarmValue, [ 'VALUE' => 'DURATION', 'RELATED' => $related ]);

    return $vAlarm;
  }
}
