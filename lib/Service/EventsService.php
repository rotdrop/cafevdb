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
use OCA\DAV\Events\CalendarDeletedEvent;

use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectDeletedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;

use OCA\CAFEVDB\Events\ProjectDeletedEvent;
use OCA\CAFEVDB\Events\ProjectUpdatedEvent;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvents;

/**Events and tasks handling. */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DBTABLE = 'ProjectEvents';

  /** @var EntityManager */
  private $entityManager;

  /** @var ProjectService */
  private $projectService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    ProjectService $projectService,
    CalDavService $calDavService,
    VCalendarService $vCalendarService
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
    $this->setDatabaseRepository(ProjectEvents::class);
  }

  /**
   * event->getObjectData() returns
   *
   * @code
   * [
   *   'uri'           => $row['uri'],
   *   'lastmodified'  => $row['lastmodified'],
   *   'etag'          => '"' . $row['etag'] . '"',
   *   'calendarid'    => $row['calendarid'],
   *   'size'          => (int)$row['size'],
   *   'calendardata'  => $this->readBlob($row['calendardata']),
   *   'component'     => strtolower($row['componenttype']),
   *   'classification'=> (int)$row['classification']
   * ];
   */
  public function onCalendarObjectCreated(CalendarObjectCreatedEvent $event)
  {
    $objectData = $event->getObjectData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
    $this->logError(__METHOD__);
    $this->syncCalendarObject($objectData, false);
  }

  public function onCalendarObjectUpdated(CalendarObjectUpdatedEvent $event)
  {
    $objectData = $event->getObjectData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
    $this->logError(__METHOD__);
    $this->syncCalendarObject($objectData);
  }

  public function onCalendarObjectDeleted(CalendarObjectDeletedEvent $event)
  {
    $objectData = $event->getObjectData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
    $this->logError(__METHOD__);
    $this->deleteCalendarObject($objectData);
  }

  public function onCalendarDeleted(CalendarDeletedEvent $event)
  {
    if (!$this->inGroup()) {
      return;
    }

    $this->queryBuilder()
         ->delete(self::DBTABLE, 'e')
         ->where('e.CalendarId = :calendarId')
         ->setParameter('calendarId', $event->getCalendarId())
         ->getQuery()
         ->execute();

    // remove from config-space if found
    foreach(ConfigService::CALENDARS as $cal) {
      $uri = $cal['uri'];
      $calendarId = $this->getCalendarId($uri);
      if ($event->getCalendarId() == $calendarId) {
        $this->deleteCalendarId($uri);
        break;
      }
    }
  }

  public function onCalendarUpdated(CalendarUpdatedEvent $event)
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

  public function onProjectDeleted(ProjectDeletedEvent $event)
  {
    $events = $this->projectEvents($event->getProjectId());
    foreach ($events as $event) {
      $this->remove($event);
      $uri = $event->getEventURI();
      $calId = $event->getCalendarId();
      $this->calDavService->deleteCalendarObject($calId, $uri);
    }
  }

  public function onProjectUpdated(ProjectUpdatedEvent $event)
  {
    $events = $this->projectEvents($event->getProjectId());
    $oldName = $event->getOldData()['name'];
    $newName = $event->getNewData()['name'];
    foreach ($events as $projectEvent) {
      $calendarId = $projectEvent->getCalendarId();
      $eventURI = $projectEvent->getEventURI();
      $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
      $vCalendar  = CalendarService::getVCalendar($event);
      $categories = calendarService::getVCategories($vCalendar);

      $key = array_search($oldName, $categories);
      $categories[$key] = $newName;
      VCalendarService::setVCategories($vCalendar, $categories);

      $summary = VCalendarService::getSummary($vCalendar);
      if (!empty($summary)) {
        $summary = str_replace($oldName, $newName, $summary);
        VCalendarService::setSummary($vCalendar, $summary);
      }
      $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
    }
  }

  /** @return ProjectEvents[] */
  private function eventProjects($eventURI)
  {
    return $this->findBy(['EventURI' => $eventURI]);
  }

  /** @return ProjectEvents[] */
  private function projectEvents($projectId)
  {
    return $this->findBy(['ProjectId' => $projectid, 'Type' => 'VEVENT']);
  }

  /**Augment database entity by calendar data. */
  private function makeEvent($projectEvent)
  {
    $event = [];
    $event['projectid'] = $projectId;
    $event['uri'] = $projectEvent->getEventURI();
    $event['calendarid'] = $projectEvent->getCalendarId();
    $calendarObject = $this->calDavService->getCalendarObject($event['CalendarId'], $event['URI']);
    $vCalendar = VCalendarService::getVCalendar($calendarObject);
    $vObject = VCalendarService::getVObject($vCalendar);
    $dtStart = $vObject->DTSTART;
    $dtEnd   = VCalendarService::getDTEnd($vCalendar);

    $start = $dtStart->getDateTime();
    $end = $dtEnd->getDateTime();
    $allDay = !$dtStart->hasTime();

    if (!$allDay) {
      if ($dtStart->isFloating()) {
        $timeZone = $this->getDateTimezone();
        $start->setTimezone($timezone);
      }
      if ($dtEnd->isFloating()) {
          $timeZone = $this->getDateTimezone();
          $end->setTimezone($timezone);
      }
    } else {
      $start->setTimezone($timezone);
      $end->setTimezone($timezone);
    }

    $event['start'] = $start;
    $event['end'] = $end;
    $event['allday'] = $allDay;

    // description + summary?
    $event['summary'] = (string)$vObject->SUMMARY;
    $event['description'] = (string)$vObject->DESCRIPTION;
    $event['location'] = (string)$vObject->LOCATION;

    return $event;
  }

  /**Fetch one specific event and convert start and end to DateTime,
   * also determine allDay.
   */
  public function fetchEvent($projectId, $eventURI)
  {
    $projectEvent = $this->find(['ProjectId' => $projectId, 'EventURI' => $eventURI]);
    if (empty($projectEvent)) {
      return null;
    }
    return $this->makeEvent($projectEvent);
  }

  /**Fetch the list of events associated with $projectId. This
   * functions fetches all the data, not only the pivot-table. Time
   * stamps from the data-base are converted to PHP DateTime()-objects
   * with UTC time-zone.
   *
   * @param[in] $projectId The numeric id of the project.
   *
   * @return Full event data for this project.
   */
  public function events($projectId)
  {
    // fetch the relevant data from the pivot-table
    $events = $this->projectEvents($projectId);

    $utc = new \DateTimeZone("UTC");

    $events = [];
    foreach ($events as $projectEvent) {
      $events[] = $this->makeEvent($projectEvent);
    }

    usort($events, function($a, $b) {
      return (($a['start'] == $b['start'])
              ? 0
              : (($a['start'] < $b['start']) ? -1 : 1));
    });

    return $events;
  }

  /**Form start and end date and time in given timezone and locale,
   * return is an array
   *
   * array('start' => array('date' => ..., 'time' => ..., 'allday' => ...), 'end' => ...)
   *
   * @param $eventObject The corresponding event object from fetchEvent() or events().
   *
   * @param $timezone Explicit time zone to use, otherwise fetched
   * from user-settings.
   *
   * @param $locale Explicit language setting to use, otherwise
   * fetched from user-settings.
   *
   */
  private function eventTimes($eventObject, $timezone = null, $locale = null)
  {
    if ($timezone === null) {
      $timezone = $this->getTimezone();
    }
    if ($locale === null) {
      $locale = $this->getLocale();
    }

    $start = $eventObject['start'];
    $end   = $eventObject['end'];
    $allDay = $eventObject['allday'];

    $startStamp = $start->getTimestamp();

    /* Event end is inclusive the last second of "end" to generate
     * non-confusing dates and times for whole-day events.
     */
    $endStamp = $end->getTimestamp() - 1;

    $startdate = Util::strftime("%x", $startStamp, $timezone, $locale);
    $starttime = Util::strftime("%H:%M", $startStamp, $timezone, $locale);
    $enddate = Util::strftime("%x", $endStamp, $timezone, $locale);
    $endtime = Util::strftime("%H:%M", $endStamp, $timezone, $locale);

    return [
      'timezone' => $timezone,
      'locale' => $locale,
      'allday' => $allDay,
      'start' => array('stamp' => $startStamp,
                       'date' => $startdate,
                       'time' => $starttime),
      'end' => array('stamp' => $endStamp,
                     'date' => $enddate,
                     'time' => $endtime)
    ];
  }

  /**Form a brief event date in the given locale. */
  public function briefEventDate($eventObject, $timezone = null, $locale = null)
  {
    $times = $this->eventTimes($eventObject, $timezone, $locale);

    if ($times['start']['date'] == $times['end']['date']) {
      $datestring = $times['start']['date'].($times['start']['allday'] ? '' : ', '.$times['start']['time']);
      $datestring = $times['start']['date'].' - '.$times['end']['date'];
    }
    return $datestring;
  }

  /**Form a "brief long" event date in the given locale. */
  public function longEventDate($eventObject, $timezone = null, $locale = null)
  {
    $times = $this->eventTimes($eventObject, $timezone, $locale);

    if ($times['start']['date'] == $times['end']['date']) {
      $datestring = $times['start']['date'];
      if (!$times['start']['allday']) {
        $datestring .= ', '.$times['start']['time'].' - '.$times['end']['time'];
      }
    } else {
      $datestring = $times['start']['date'];
      if (!$times['start']['allday']) {
        $datestring .= ', '.$times['start']['time'];
      }
      $datestring .= '  -  '.$times['end']['date'];
      if (!$times['start']['allday']) {
        $datestring .= ', '.$times['end']['time'];
      }
    }
    return $datestring;
  }

  /**Convert the given flat event-list (as returned by self::events())
   * into a matrix grouped as specified by the array $calendarIds in
   * the given order. The result is an associative array where the
   * keys are the displaynames of the calenders; the last row will
   * contain events which do not belong to any id mentioned in
   * $calendarIds and be tagged by the key '__other__'.
   *
   * @param[in] $projectEvents List returned by self::events().
   *
   * @param[in] $calendarIds Array with calendar sorting order, giving
   * the ids of the wanted calendars in the wanted order.
   *
   * @return Associative array with calendarnames as keys.
   */
  public static function eventMatrix($projectEvents, $calendarIds)
  {
    $calendarNames = [];

    $result = [];

    foreach ($calendarIds as $calendarId) {
      $cal = $this->calDavService->calendarById($calendarId);
      $displayName = !empty($cal)
                   ? $cal->getDisplayName()
                   : strval($this->l-t('Unknown Calendar').' '.$calendarId);

      $result[$calendarId] = [
        'name' => $displayName,
        'events' => [],
      ];
    }
    $result[-1] = [
      'name' => strval(L::t('Miscellaneous Calendars')),
      'events' => []
    ];

    foreach ($projectEvents as $event) {
      $calId = array_search($event['calendarid'], $calendarIds);
      if ($calId === false) {
        $result[-1]['events'][] = $event;
      } else {
        $calId = $calendarIds[$calId];
        $result[$calId]['events'][] = $event;
      }
    }

    return $result;
  }

  /**Form an array with the most relevant event data. */
  private function eventData($eventObject, $timezone = null, $locale = null)
  {
    $vcalendar = self::getVCalendar($eventObject);
    $vobject = self::getVObject($vcalendar);

    $times = $this->eventTimes($eventObject, $timezone, $locale);

    $quoted = array('\,' => ',', '\;' => ';');
    $summary = strtr($eventObject['summary'], $quoted);
    $location = strtr($eventObject['location'], $quoted);
    $description = strtr($eventObject['description'], $quoted);

    return [
      'times' => $times,
      'summary' => $summary,
      'location' => $location,
      'description' => $description
    ];
  }

  /**Return event data for given project id and calendar id. Used in
   * an API call from Redaxo.
   */
  public function projectEventData($projectId, $calendarIds = null, $timezone = null, $locale = null)
  {
    $events = $this->events($projectId);

    if ($calendarIds === null || $calendarIds === false) {
      $calendarIds = $this->defaultCalendars(true);
    } else if (!is_array($calendarIds)) {
      $calendarIds = [ $calendarIds ];
    }

    $result = [];

    foreach ($calendarIds as $calendarId) {
      $cal = $this->calDavService->calendarById($calendarId);
      $displayName = !empty($cal)
                   ? $cal->getDisplayName()
                   : strval($this->l->t('Unknown Calendar').' '.$calendarId);

      $result[$calendarId] = [
        'name' => $displayName,
        'events' => [],
      ];
    }
    foreach ($events as $event) {
      $calId = array_search($event['calendarid'], $calendarIds);
      if ($calId !== false) {
        $calId = $calendarIds[$calId];
        $result[$calId]['events'][] = $this->eventData($event, $timezone, $locale);
      }
    }

    return array_values($result);
  }

  /**Export the given events in ICAL format. The events need not
   * belong to the same calendar.
   *
   * @param[in] $events An array with 'calendarid' => 'eventuri'
   *
   * @param[in] $projectName Short project tag, will form part of the
   * name of the calendar.
   *
   * @return A string with the ICAL data.
   *
   * @todo Include local timezone.
   */
  static public function exportEvents($events, $projectName)
  {
    $result = '';

    $eol = "\r\n";

    $result .= ""
            ."BEGIN:VCALENDAR".$eol
            ."VERSION:2.0".$eol
            ."PRODID:Nextloud cafevdb " . \OCP\App::getAppVersion($this->appName()) . $eol
            ."X-WR-CALNAME:" . $projectName . ' (' . $this->getConfigValue('orchestra') . ')' . $eol;

    foreach ($events as $calendarId => $eventURI) {
      $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
      $vObject = VCalendarService::getVObject(CalendarObject::getVCalendar($event));
      if (empty($vObject)) {
        continue;
      }
      $result .= $vObject->serialize();
    }
    $result .= "END:VCALENDAR".$eol;

    return $result;
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

  /**Delete the configured calendar id. */
  private function deleteCalendarId($uri)
  {
    return $this->deleteConfigValue($uri.'calendar'.'id');
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

  /**Parse the respective event and make sure the ProjectEvents
   * table is uptodate.
   *
   * @param[in] $eventId The OwnCloud-Id of the event.
   *
   * @param[in] $handle Optional. MySQL handle.
   **
   * @return bool, @c true if the event has been added.
   */
  private function syncCalendarObject($objectData, $unregister = true)
  {
    $eventURI   = $objectData['uri'];
    $calId      = $objectData['calendarid'];
    $vCalendar  = CalendarService::getVCalendar($objectData);
    $categories = calendarService::getVCategories($vCalendar);

    // Now fetch all projects and their names ...
    $projects = $this->projectService->fetchAll();

    $result = false;
    // Do the sync. The categories stored in the event are
    // the criterion for this.
    foreach ($projects as $project) {
      $prKey = $project->getId();
      $registered = $this->isRegistered($prKey, $eventURI);
      if (in_array($project->getName(), $categories)) {
        // register or update the event
        $type = VCalendarService::getVObjectType($vCalendar);
        $this->register($prKey, $calId, $eventURI, $type);
        $result = !$registered;
      } else if ($registered) {
        // unregister the event
        $this->unregister($prKey, $eventURI);
      }
    }
    return $result;
  }

  private function deleteCalendarObject($objectData)
  {
    $eventURI   = $objectData['uri'];

    foreach ($this->eventProjects($uri) as $project) {
      $this->unregister($project->getId(), $eventURI);
    }
  }

  /**Unconditionally register the given event with the given project.
   *
   * @param[in] $projectId The project key.
   * @param[in] $eventURI The event key (external key).
   * @param[in] $calendarId The id of the calender the vent belongs to.
   * @param[in] $type The event type (VEVENT, VTODO, VJOURNAL, VCARD).
   *
   * @return Undefined.
   */
  private function register($projectId,
                            $eventURI,
                            $calendarId,
                            $type)
  {
    return $this->persist((new ProjectEvents())
                          ->setProjectId($projectId)
                          ->setEventURI($eventURI)
                          ->setCalendarId($calendarId)
                          ->setType($type));
  }

  /**Unconditionally unregister the given event with the given project.
   *
   * @param[in] $projectId The project key.
   * @param[in] $eventURI The event key (external key).
   *
   * @return Undefined.
   */
  private function unregister($projectId, $eventURI)
  {
    return $this->remove(['ProjectId' => $projectId, 'EventURI' => $eventURI]);
  }

  /**Unconditionally unregister the given event with the given
   * project, and remove the project-name from the event's categories
   * list.
   *
   * @param[in] $projectId The project key.
   * @param[in] $eventURI The event uri.
   *
   * @return Undefined.
   */
  public function unchain($projectId, $eventURI)
  {
    $this->unregister($projectId, $eventURI);

    $projectName = $this->projectService->fetchName($projectId);
    $projectEvent = $this->find(['ProjectId' => $projectId, 'EventURI' => $eventURI]);
    $calendarId = $projectEvent->getCalendaId();
    $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
    $vCalendar  = VCalendarService::getVCalendar($event);
    $categories = VCalendarService::getVCategories($vCalendar);

    $key = array_search($projectName, $categories);
    unset($categories[$key]);
    VCalendarService::setVCategories($vCalendar, $categories);

    return $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
  }

  /**Test if the given event is linked to the given project.
   *
   * @param[in] $projectId The project key.
   * @param[in] $eventURI The event key (external key).
   *
   * @return @c true if the event is registered, otherwise false.
   */
  private function isRegistered($projectId, $eventURI)
  {
    //return !empty($this->find(['ProjectId' => $projectId, 'EventURI' => $eventURI]));
    return $this->count(['ProjectId' => $projectId, 'EventURI' => $eventURI]) > 0;
  }

  /**Inject a new task into the given calendar. This function calls
   * $request is a post-array. One example, in order to create a
   * simple task:
   *
   * @code
   * [
   *   'description' => $title, // required
   *   'related' => other VObject's UID // optional
   *   'due' => date('d-m-Y', $timeStamp), // required
   *   'start' => date('d-m-Y'), // optional
   *   'location' => 'Cyber-Space', // optional
   *   'categories' => $categories, // optional
   *   'description' => $description, // optional
   *   'calendar' => $calendarId, // required
   *   'starred' => true, // optional
   *   'alarm' => $alarm, // optional
   * ]
   * @end code
   *
   * We also support adding a reminder: 'alarm' => unset or interval
   * in seconds (i.e. time-stamp diff). The function may throw
   * errors.
   *
   * @return task-uri on success, null on error.
   */
  public function newTask($taskData)
  {
    if (empty($taskData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($taskData, VCalendarService::VTODO);
    if (empty($vCalendar)) {
      return null;
    }
    return $this->calDavService->createCalendarObject($taskData['calendar'], $vCalendar);
  }

  /**Inject a new event into the given calendar.
   *
   * One example, in order to create a simple non-repeating event:
   *
   * @code
   * [
   *   'summary' => TITLE,
   *   'from' => dd-mm-yyyy,
   *   'to' => dd-mm-yyyy,
   *   'allday' => on (or unset),
   *   'location' => WHERE (may be empty),
   *   'categories' => <list, comma separated>,
   *   'description' => TEXT,
   *   'repeat' => 'doesnotrepeat',
   *   'calendar' => CALID
   * ]
   * @end code
   *
   * We also support adding a reminder: 'alarm' => unset or interval
   * in seconds (i.e. time-stamp diff). The function may throw
   * errors.
   *
   * @return string|null event-uri or null on error.
   */
  public function newEvent($eventData)
  {
    if (empty($eventData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($eventData, VCalendarService::VEVENT);
    if (empty($vCalendar)) {
      return null;
    }
    return $this->calDavService->createCalendarObject($eventData['calendar'], $vCalendar);
  }

  public function playground() {

    $eventData = [
      'summary' => 'Title',
      'description' => 'Text',
      'location' => 'Where',
      'categories' => 'Cat1,Cat2',
      'priority' => 10,
      'from' => '01-11-2020',
      'fromtime' => '10:20:22',
      'to' => '30-11-2020',
      'totime' => '00:00:00',
      'calendar' => 'calendarId',
      'repeat' => 'doesnotrepeat',
    ];

    $errors = $this->vCalendarService->validateRequest($eventData, VCalendarService::VEVENT);
    $this->logError('EventError' . print_r($errors, true));
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($eventData, VCalendarService::VEVENT);
    $this->logError('VEVENT VCalendar entry' . print_r($vCalendar, true));

    $taskData = [
      'summary' => 'Title',
      'description' => 'Text',
      'location' => 'Where',
      'categories' => 'Cat1,Cat2',
      'priority' => 10,
      'due' => '01-11-2020',
      'start' => '01-11-2020',
      'calendar' => 'calendarId',
      'alarm' => 10
    ];

    $errors = $this->vCalendarService->validateRequest($taskData, VCalendarService::VTODO);
    $this->logError('TodoError' . print_r($errors, true));
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($taskData, VCalendarService::VTODO);
    $this->logError('VTODO VCalendar entry' . print_r($vCalendar, true));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
