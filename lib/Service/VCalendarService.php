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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;

// Operation/Builder for VCalendar objects
class VCalendarService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  private $legacyCalendarObject;

  const VTODO = 'VTODO';
  const VEVENT = 'VEVENT';
  const ALARM_ACTION_DISPLAY = 'DISPLAY';
  const ALARM_ACTION_AUDIO = 'AUDIO';
  const ALARM_ACTION_EMAIL = 'EMAIL';
  const ALARM_ACTION_PROCEDURE = 'PROCEDURE';

  public function __construct(
    ConfigService $configService,
    OC_Calendar_Object $legacyCalendarObject
  ) {
    $this->configService = $configService;
    $this->legacyCalendarObject = $legacyCalendarObject;
  }

  /**Validate the given request-data which is an associative array.
   *
   * @return array|false Array of error messages or false on success
   */
  public function validateRequest($eventData, $kind = self::VEVENT) {
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
   *
   */
  public function createVCalendarFromRequest($vObjectData, $kind = self::VEVENT) {
    switch ($kind) {
    case self::VEVENT:
      return $this->createVEventFromRequest($vObjectData);
    case 'VTODO':
      return $this->createVTodoFromRequest($vObjectData);
    default:
      return null;
    }
  }

  public function updateVCalendarFromRequest($objectData, $kind = self::VEVENT) {
    switch ($kind) {
    case self::VEVENT:
      return $this->updateVEventFromRequest($vObjectData);
    case 'VTODO':
      return $this->updateVTodoFromRequest($vObjectData);
    default:
      return null;
    }
  }

  private function createVEventFromRequest($objectData)
  {
    $vObject = $this->legacyCalendarObject->createVCalendarFromRequest($objectData);
    if (empty($vObject) || empty($objectData['alarm'])) {
      return $vObject;
    }
    if (!empty($request['alarm'])) {
      $vObjet->VEVENT->VALARM = $this->createVAlarmFromSeconds($vObject, $request['alarm'], $request['summary']);
    }
    return $vObject;
  }

  private function updateVEventFromRequest($objectData)
  {
    $vCalendar = $this->legacyCalendarObject->updateVCalendarFromRequest($objectData);
    if (empty($vCalendar) || empty($objectData['alarm'])) {
      return $vCalendar;
    }
    if (!empty($request['alarm'])) {
      $vObjet->VEVENT->VALARM = $this->createVAlarmFromSeconds($vCalendar, $request['alarm'], $request['summary']);
    } else {
      unset($vOBJECT->VEVENT->{'VALARM'});
    }
    return $vCalendar;
  }

  public function legacyEventObject()
  {
    return $this->legacyCalendarObject;
  }

  private function validateVTodoRequest($todoData)
  {
    $requiredKeys = [
      'summary',
      'due',
      'start'
    ];
    $errArr = [];
    foreach($requiredKeys as $key) {
      if (empty($todoData[$key])) {
        $errArr[$key] = true;
      }
    }
    return empty($errArr) ? false : $errArr;
  }

  /*
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
   *
   * @return  \Sabre\VObject\Component\VCalendar|null
   */
  private function createVTodoFromRequest($todoData)
  {
    if (!empty($this->validateVTodoRequest($todoData))) {
      return null;
    }

    $vCalendar = new \Sabre\VObject\Component\VCalendar();
    $vCalendar->PRODID = "Nextloud cafevdb " . \OCP\App::getAppVersion($this->appName());
    $vCalendar->VERSION = '2.0';

    $vTodo = $vCalendar->createComponent('VTODO');

    $vTodo->CREATED = new \DateTime('now', new \DateTimeZone('UTC'));

    $vTodo->UID = \Sabre\VObject\UUIDUtil::getUUID();

    $vCalendar->add($vTodo);

    return $this->updateVTodoFromRequest($vCalendar, $todoData);
  }

  /**Update task from request.
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
   *
   * @return  \Sabre\VObject\Component\VCalendar|null
   */
  private function updateVTodoFromRequest($vCalendar, $request)
  {
    if (!empty($this->validateVTodoRequest($request))) {
      return null;
    }

    $vTodo = $vCalendar->VTODO;
    $timezone = $this->getDateTimeZone()->getTimeZone();

    $vTodo->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
    $vTodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
    $vTodo->SUMMARY = $request['summary'];

    if (!empty($request['due'])) {
      $vTodo->DUE = new \DateTime($request['due'], $timezone);
    }
    if (!empty($request['start'])) {
      $vTodo->DTSTART = new \DateTime($request['start'], $timezone);
    }

    $optionalKeys = [
      'priority' => 'PRIORITY',
      'related' => 'RELATED-TO',
      'description' => 'DESCRIPTION',
      'location' => 'LOCATION',
    ];
    foreach($optionalKeys as $rqKey => $vKey) {
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
    if(count($categories)){
      $vTodo->CATEGORIES = $categories;
    } else{
      unset($vTodo->{'CATEGORIES'});
    }

    if (!empty($request['alarm'])) {
      $vTodo->VALARM = $this->createVAlarmFromSeconds($vCalendar, -$request['alarm'], $request['summary']);
    } else {
      unset($vTodo->{'VALARM'});
    }

    $vCalendar->VTODO = $vTodo;

    return $vCalendar;
  }

  private function createVAlarmFromSeconds($vCalendar, $seconds, $description = null, $action = self::ALARM_ACTION_DISPLAY)
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
    $dinterval = new \DateTime();
    $dinterval->add(new \DateInterval('PT'.$seconds.'S'));
    $interval = $dinterval->diff(new \DateTime);
    $alarmValue = sprintf('%sP%s%s%s%s',
                          $interval->format('%r'),
                          $interval->format('%d') > 0 ? $interval->format('%dD') : null,
                          ($interval->format('%h') > 0 || $interval->format('%i') > 0) ? 'T' : null,
                          $interval->format('%h') > 0 ? $interval->format('%hH') : null,
                          $interval->format('%i') > 0 ? $interval->format('%iM') : null);
    $vAlarm->add('TRIGGER', $sign.$alarmValue, [ 'VALUE' => 'DURATION', 'RELATED' => $related ]);

    return $vAlarm;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
