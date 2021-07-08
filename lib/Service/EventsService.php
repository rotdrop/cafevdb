<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent;

use OCA\CAFEVDB\Common\Util;

/**
 * Events and tasks handling.
 * @todo
 * - cleanup-jobs for orphan events
 */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var EntityManager */
  protected $entityManager;

  /** @var ProjectService */
  private $projectService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , ProjectService $projectService
    , CalDavService $calDavService
    , VCalendarService $vCalendarService
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
    $this->setDatabaseRepository(ProjectEvent::class);
    $this->l = $this->l10n();
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
   * @endcode
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
    $this->deleteCalendarObject($objectData);
  }

  public function onCalendarDeleted(CalendarDeletedEvent $event)
  {
    if (!$this->inGroup()) {
      return;
    }

    $this->queryBuilder()
         ->delete(ProjectEvent::class, 'e')
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
    $projectId = $event->getProject();
    $projectEvents = $this->projectEvents($projectId);
    foreach ($calendarEvents as $projectEvent) {
      $eventUri = $projectEvent->getEventUri();

      // remove project link from join table
      $this->remove($event);

      // still used?
      if (count($this->eventProjects($eventUri)) === 0) {
        $calId = $event->getCalendarId();
        $this->calDavService->deleteCalendarObject($calId, $uri);
      } else {
        // update categories
        $this->unchain($projectId, $eventUri);
      }
    }
  }

  public function onProjectUpdated(ProjectUpdatedEvent $event)
  {
    $events = $this->projectEvents($event->getProject());
    $oldName = $event->getOldData()['name'];
    $newName = $event->getNewData()['name'];
    foreach ($events as $projectEvent) {
      $calendarId = $projectEvent->getCalendarId();
      $eventURI = $projectEvent->getEventURI();
      $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
      $vCalendar  = VCalendarService::getVCalendar($event);
      $categories = VCalendarService::getCategories($vCalendar);

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

  /** @return ProjectEvent[] */
  private function eventProjects($eventURI)
  {
    return $this->findBy(['eventUri' => $eventURI]);
  }

  /** @return ProjectEvent[] */
  private function projectEvents($projectOrId)
  {
    return $this->findBy(['project' => $projectOrId, 'type' => 'VEVENT']);
  }

  /**
   * Augment database entity by calendar data.
   * @return array|null Returns null if the calendar object cannot be
   * found, otherwise an array
   * ```
   * [
   *   'projectid' => PROJECT_ID,
   *   'uri' => EVENT_URI,
   *   'calendarid' => CALENDAR_ID,
   *   'start' => \DateTime,
   *   'end' => \DateTime,
   *   'allday' => BOOL
   *   'summary' => SUMMARY,
   *   'description' => DESCRTION,
   *   'location' => LOCATION,
   * ]
   * ```
   */
  private function makeEvent($projectEvent)
  {
    $event = [];
    $event['projectid'] = $projectEvent->getProject()->getId();
    $event['uri'] = $projectEvent->getEventUri();
    $event['uid'] = $projectEvent->getEventUid();
    $event['calendarid'] = $projectEvent->getCalendarId();
    $calendarObject = $this->calDavService->getCalendarObject($event['calendarid'], $event['uri']);
    if (empty($calendarObject)) {
      $this->logDebug('Orphan project event found: ' . print_r($event, true) . (new \Exception())->getTraceAsString());
      // clean up orphaned events
      try {
        $this->unregister($event['projectid'], $event['uri']);
      } catch  (\Throwable $t) {
        $this->logException($t);
      }
      return null;
    }
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

  /**
   * Fetch one specific event and convert start and end to DateTime,
   * also determine allDay.
   */
  public function fetchEvent($projectId, $eventURI)
  {
    $projectEvent = $this->find(['project' => $projectId, 'eventUri' => $eventURI]);
    if (empty($projectEvent)) {
      return null;
    }
    return $this->makeEvent($projectEvent);
  }

  /**
   * Fetch the list of events associated with $projectId. This
   * functions fetches all the data, not only the pivot-table. Time
   * stamps from the data-base are converted to PHP DateTime()-objects
   * with UTC time-zone.
   *
   * @param $projectId The numeric id of the project.
   *
   * @return array Event-data as generated by self::makeEvent().
   */
  public function events($projectId)
  {
    // fetch the relevant data from the pivot-table
    $projectEvents = $this->projectEvents($projectId);

    $utc = new \DateTimeZone("UTC");

    $events = [];
    foreach ($projectEvents as $projectEvent) {
      $eventData = $this->makeEvent($projectEvent);
      if (empty($eventData)) {
        continue;
      }
      $events[] = $eventData;
    }

    usort($events, function($a, $b) {
      return (($a['start'] == $b['start'])
              ? 0
              : (($a['start'] < $b['start']) ? -1 : 1));
    });

    //$this->logInfo("Events: ".print_r($events, true));

    return $events;
  }

  /**
   * Form start and end date and time in given timezone and locale,
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
      $datestring = $times['start']['date'].($times['allday'] ? '' : ', '.$times['start']['time']);
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
      if (!$times['allday']) {
        $datestring .= ', '.$times['start']['time'].' - '.$times['end']['time'];
      }
    } else {
      $datestring = $times['start']['date'];
      if (!$times['allday']) {
        $datestring .= ', '.$times['start']['time'];
      }
      $datestring .= '  -  '.$times['end']['date'];
      if (!$times['allday']) {
        $datestring .= ', '.$times['end']['time'];
      }
    }
    return $datestring;
  }

  /**
   * Convert the given flat event-list (as returned by self::events())
   * into a matrix grouped as specified by the array $calendarIds in
   * the given order. The result is an associative array where the
   * keys are the displaynames of the calenders; the last row will
   * contain events which do not belong to any id mentioned in
   * $calendarIds and be tagged by the key '__other__'.
   *
   * @param $projectEvents List returned by self::events().
   *
   * @param $calendarIds Array with calendar sorting order, giving
   * the ids of the wanted calendars in the wanted order.
   *
   * @return Associative array with calendarnames as keys.
   */
  public function eventMatrix($projectEvents, $calendarIds)
  {
    $calendarNames = [];

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
    $result[-1] = [
      'name' => strval($this->l->t('Miscellaneous Calendars')),
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

  /**
   * Return event data for given project id and calendar id. Used in
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

  /**
   * Export the given events in ICAL format. The events need not
   * belong to the same calendar.
   *
   * @param $events An array with EVENT_URI => CALENDAR_ID.
   *
   * @param $projectName Short project tag, will form part of the
   * name of the calendar.
   *
   * @return A string with the ICAL data.
   *
   * @todo Include local timezone.
   */
  public function exportEvents($events, $projectName)
  {
    $result = '';

    $eol = "\r\n";

    $result .= ""
            ."BEGIN:VCALENDAR".$eol
            ."VERSION:2.0".$eol
            ."PRODID:Nextloud cafevdb " . $this->appVersion() . $eol
            ."X-WR-CALNAME:" . $projectName . ' (' . $this->getConfigValue('orchestra') . ')' . $eol;

    foreach ($events as $eventURI => $calendarId) {
      $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
      $vObject = VCalendarService::getVObject(VCalendarService::getVCalendar($event));
      if (empty($vObject)) {
        continue;
      }
      $result .= $vObject->serialize();
    }
    $result .= "END:VCALENDAR".$eol;

    return $result;
  }

  /** Return the IDs of the default calendars. */
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

  /** Return the configured calendar id. */
  private function getCalendarId($uri)
  {
    return $this->getConfigValue($uri.'calendar'.'id');
  }

  /** Delete the configured calendar id. */
  private function deleteCalendarId($uri)
  {
    return $this->deleteConfigValue($uri.'calendar'.'id');
  }

  /** Return the configured calendar display name. */
  private function getCalendarDisplayName($uri)
  {
    return $this->getConfigValue($uri.'calendar');
  }

  /** Return the configured calendar display name. */
  private function setCalendarDisplayName($uri, $displayName)
  {
    return $this->setConfigValue($uri.'calendar', $displayName);
  }

  /**
   * Parse the respective event and make sure the ProjectEvent
   * table is uptodate.
   *
   * @return array
   * ```
   * [
   *   'registered' => [ PROJECT_ID, ... ],
   *   'unregistered' => [ PROJECT_ID, ... ],
   * ]
   * ```
   */
  public function syncCalendarObject($objectData, $unregister = true)
  {
    $eventURI   = $objectData['uri'];
    $calId      = $objectData['calendarid'];
    $eventData  = $objectData['calendardata'];
    $vCalendar  = VCalendarService::getVCalendar($objectData);
    $categories = VCalendarService::getCategories($vCalendar);
    $eventUID   = VCalendarService::getUid($vCalendar);

    // Now fetch all projects and their names ...
    $projects = $this->projectService->fetchAll();

    $registered = [];
    $unregistered = [];
    // Do the sync. The categories stored in the event are
    // the criterion for this.
    foreach ($projects as $project) {
      $prKey = $project->getId();
      if (in_array($project->getName(), $categories)) {
        // register or update the event
        $type = VCalendarService::getVObjectType($vCalendar);
        if ($this->register($project, $eventURI, $eventUID, $calId, $type)) {
          $registered[] = $prKey;
        }
      } else {
        // unregister the event
        if ($this->unregister($prKey, $eventURI)) {
          $unregistered[] = $prKey;
        }
      }
    }
    return [ 'registered' => $registered, 'unregistered' => $unregistered ];
  }

  /**
   * Remove the calendar object from the join table as the calendar
   * object is no more.
   */
  private function deleteCalendarObject($objectData)
  {
    $eventURI   = $objectData['uri'];
    foreach ($this->eventProjects($eventURI) as $projectEvent) {
      $this->unregister($projectEvent->getProject(), $eventURI);
    }
  }

  /**
   * Unconditionally register the given event with the given project.
   *
   * @param int|Project $projectId The project or its id.
   * @param string $eventURI The event key (external key).
   * @param string $eventUID The event UID.
   * @param int $calendarId The id of the calender the vent belongs to.
   * @param string $type The event type (VEVENT, VTODO, VJOURNAL, VCARD).
   *
   * @return bool true if the event has been newly registered.
   */
  private function register($projectOrId,
                            string $eventURI,
                            string $eventUID,
                            int $calendarId,
                            string $type)
  {
    $entity = $this->findOneBy(['project' => $projectOrId, 'eventUri' => $eventURI]);
    if (empty($entity)) {
      $entity = new ProjectEvent();
      $entity->setProject($projectOrId)
             ->setEventUri($eventURI)
             ->setEventUid($eventUID)
             ->setCalendarId($calendarId)
             ->setType($type);
      $this->persist($entity);
      $added = true;
    } else {
      $entity->setCalendarId($calendarId)
             ->setType($type);
      $this->merge($entity);
      $added = false;
    }
    $this->flush($entity);

    return $added;
  }

  /**
   * Unconditionally unregister the given event with the given project.
   *
   * @param int $projectId The project key.
   * @param string $eventURI The event key (external key).
   *
   * @return bool true if the event has been removed, false if it was
   * not registered.
   */
  public function unregister(int $projectId, string $eventURI)
  {
    if (!$this->isRegistered($projectId, $eventURI)) {
      return false;
    }
    $this->remove(['project' => $projectId, 'eventUri' => $eventURI], true);
    return true;
  }

  /**
   * Unconditionally unregister the given event with the given
   * project, and remove the project-name from the event's categories
   * list.
   *
   * @param $projectId The project key.
   * @param $eventURI The event uri.
   *
   * @return undefined
   */
  public function unchain($projectId, $eventURI)
  {
    $projectEvent = $this->find(['project' => $projectId, 'eventUri' => $eventURI]);
    $calendarId = $projectEvent->getCalendarId();

    $this->unregister($projectId, $eventURI);

    $projectName = $this->projectService->fetchName($projectId);
    $event = $this->calDavService->getCalendarObject($calendarId, $eventURI);
    $vCalendar  = VCalendarService::getVCalendar($event);
    $categories = VCalendarService::getCategories($vCalendar);

    $key = array_search($projectName, $categories);
    unset($categories[$key]);
    VCalendarService::setCategories($vCalendar, $categories);

    return $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
  }

  /**
   * Test if the given event is linked to the given project.
   *
   * @param $projectId The project key.
   * @param $eventURI The event key (external key).
   *
   * @return bool @c true if the event is registered, otherwise false.
   */
  private function isRegistered($projectId, $eventURI)
  {
    //return !empty($this->find(['project' => $projectId, 'eventUri' => $eventURI]));
    return $this->count(['project' => $projectId, 'eventUri' => $eventURI]) > 0;
  }

  /**
   * Inject a new task into the given calendar. This function calls
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
   * @endcode
   *
   * We also support adding a reminder: 'alarm' => unset or interval
   * in seconds (i.e. time-stamp diff). The function may throw
   * errors.
   *
   * @return string task-uri on success, null on error.
   */
  public function newTask(array $taskData): ?string
  {
    if (empty($taskData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($taskData, VCalendarService::VTODO);
    if (empty($vCalendar)) {
      return null;
    }
    return $this->calDavService->createCalendarObject($taskData['calendar'], null, $vCalendar);
  }

  /**
   * Inject a new event into the given calendar.
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
   * @endcode
   *
   * We also support adding a reminder: 'alarm' => unset or interval
   * in seconds (i.e. time-stamp diff). The function may throw
   * errors.
   *
   * @return string|null event-uri or null on error.
   */
  public function newEvent(array $eventData): ?string
  {
    if (empty($eventData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($eventData, VCalendarService::VEVENT);
    if (empty($vCalendar)) {
      return null;
    }
    return $this->calDavService->createCalendarObject($eventData['calendar'], null, $vCalendar);
  }

  public function deleteCalendarEntry($calId, $objectUri)
  {
    return $this->calDavService->deleteCalendarObject($calId, $objectUri);
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
