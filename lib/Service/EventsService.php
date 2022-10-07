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

use \Exception;
use \DateTimeImmutable;
use \DateTimeZone;

use OCA\DAV\Events\CalendarUpdatedEvent;
use OCA\DAV\Events\CalendarDeletedEvent;

use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectDeletedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;

use OCA\CAFEVDB\Events\BeforeProjectDeletedEvent;
use OCA\CAFEVDB\Events\PreProjectUpdatedEvent;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/**
 * Events and tasks handling.
 *
 * @todo
 * - cleanup-jobs for orphan events
 */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const VALARM_FROM_START = VCalendarService::VALARM_FROM_START;
  const VALARM_FROM_END = VCalendarService::VALARM_FROM_END;

  const TASK_IN_PROCESS = VCalendarService::VTODO_STATUS_IN_PROCESS;
  const TASK_COMPLETED = VCalendarService::VTODO_STATUS_COMPLETED;
  const TASK_NEEDS_ACTION = VCalendarService::VTODO_STATUS_NEEDS_ACTION;


  /** @var EntityManager */
  protected $entityManager;

  /** @var ProjectService */
  private $projectService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    ProjectService $projectService,
    CalDavService $calDavService,
    VCalendarService $vCalendarService,
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
    $this->setDatabaseRepository(Entities\ProjectEvent::class);
    $this->l = $this->l10n();
  }

  /**
   * @param CalendarObjectCreatedEvent $event $event->getObjectData() returns
   * ```
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
   * ```.
   *
   * @return void
   */
  public function onCalendarObjectCreated(CalendarObjectCreatedEvent $event):void
  {
    $objectData = $event->getObjectData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
    $calendarData = $event->getCalendarData();
    $objectData['calendaruri'] = $calendarData['uri'];

    $this->syncCalendarObject($objectData, false);
  }

  /**
   * @param CalendarObjectUpdatedEvent $event Event object.
   *
   * @return void
   */
  public function onCalendarObjectUpdated(CalendarObjectUpdatedEvent $event):void
  {
    $objectData = $event->getObjectData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
    $calendarData = $event->getCalendarData();
    $objectData['calendaruri'] = $calendarData['uri'];

    $this->syncCalendarObject($objectData);
  }

  /**
   * @param CalendarObjectDeletedEvent $event Event object.
   *
   * @return void
   */
  public function onCalendarObjectDeleted(CalendarObjectDeletedEvent $event):void
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

  /**
   * @param CalendarDeletedEvent $event Event object.
   *
   * @return void
   */
  public function onCalendarDeleted(CalendarDeletedEvent $event):void
  {
    if (!$this->inGroup()) {
      return;
    }

    $this->queryBuilder()
         ->delete(Entities\ProjectEvent::class, 'e')
         ->where('e.calendarId = :calendarId')
         ->setParameter('calendarId', $event->getCalendarId())
         ->getQuery()
         ->execute();

    // remove from config-space if found
    foreach (ConfigService::CALENDARS as $cal) {
      $uri = $cal['uri'];
      $calendarId = $this->getCalendarId($uri);
      if ($event->getCalendarId() == $calendarId) {
        $this->deleteCalendarId($uri);
        break;
      }
    }
  }

  /**
   * @param CalendarUpdatedEvent $event Event object.
   *
   * @return void
   */
  public function onCalendarUpdated(CalendarUpdatedEvent $event):void
  {
    if (!$this->inGroup()) {
      return;
    }
    foreach (ConfigService::CALENDARS as $cal) {
      $uri = $cal['uri'];
      $calendarId = $this->getCalendarId($uri);
      if ($event->getCalendarId() == $calendarId) {
        $displayName = $this->getCalendarDisplayName($uri);
        $calendarData = $event->getCalendarData();
        if (empty($displayName)) {
          $this->setCalendarDisplayName($uri, $calendarData['displayname']);
        } elseif ($displayName != $calendarData['displayname']) {
          // revert the change
          $this->calDavService->displayName($calendarId, $displayName);
        }
        break;
      }
    }
  }

  /**
   * @param BeforeProjectDeletedEvent $event Event object.
   *
   * @return void
   */
  public function onProjectDeleted(BeforeProjectDeletedEvent $event):void
  {
    $projectId = $event->getProjectId();
    $projectEvents = $this->projectEvents($projectId);
    foreach ($projectEvents as $projectEvent) {
      $eventUri = $projectEvent->getEventUri();

      // remove project link from join table
      $this->remove($event);

      // still used?
      if (count($this->eventProjects($eventUri)) === 0) {
        $calId = $event->getCalendarId();
        $this->calDavService->deleteCalendarObject($calId, $eventUri);
      } else {
        // update categories
        $this->unchain($projectId, $eventUri);
      }
    }
  }

  /**
   * @param PreProjectUpdatedEvent $event Event object.
   *
   * @return void
   */
  public function onProjectUpdated(PreProjectUpdatedEvent $event):void
  {
    $events = $this->projectEvents($event->getProjectId());
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
      VCalendarService::setCategories($vCalendar, $categories);

      $summary = VCalendarService::getSummary($vCalendar);
      if (!empty($summary)) {
        $summary = str_replace($oldName, $newName, $summary);
        VCalendarService::setSummary($vCalendar, $summary);
      }
      $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
    }
  }

  /**
   * @param string $eventURI CalDAV URI.
   *
   * @return Entities\ProjectEvent[]
   */
  private function eventProjects(string $eventURI):array
  {
    return $this->findBy(['eventUri' => $eventURI]);
  }

  /**
   * @param int|Entities\Project $projectOrId Database entity or its id.
   *
   * @return Entities\ProjectEvent[]
   */
  private function projectEvents($projectOrId):array
  {
    return $this->findBy(['project' => $projectOrId, 'type' => 'VEVENT']);
  }

  /**
   * Augment the database entity by calendar data.
   *
   * @param Entities\ProjectEvent $projectEvent Database entity.
   *
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
  private function makeEvent(Entities\ProjectEvent $projectEvent):?array
  {
    $event = [];
    $event['projectid'] = $projectEvent->getProject()->getId();
    $event['uri'] = $projectEvent->getEventUri();
    $event['uid'] = $projectEvent->getEventUid();
    $event['calendarid'] = $projectEvent->getCalendarId();
    $calendarObject = $this->calDavService->getCalendarObject($event['calendarid'], $event['uri']);
    if (empty($calendarObject)) {
      $this->logDebug('Orphan project event found: ' . print_r($event, true) . (new Exception())->getTraceAsString());
      if (false) {
        // clean up orphaned events
        try {
          $this->unregister($event['projectid'], $event['uri']);
        } catch (\Throwable $t) {
          $this->logException($t);
        }
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

    $timeZone = $this->getDateTimezone();
    if (!$allDay) {
      if ($dtStart->isFloating()) {
        $start = $start->setTimezone($timeZone);
      }
      if ($dtEnd->isFloating()) {
        $end = $end->setTimezone($timeZone);
      }
    } else {
      // the following is not overly correct, but make a prettier
      // display:
      $start = new DateTimeImmutable($start->format('Y-m-d H:i:s'), $timeZone);
      $end = new DateTimeImmutable($end->format('Y-m-d H:i:s'), $timeZone);
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
   *
   * @param int $projectId
   *
   * @param string $eventURI CalDAV URI.
   *
   * @return null|array
   *
   * @see makeEvent()
   */
  public function fetchEvent(int $projectId, string $eventURI):?array
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
   * @param int $projectId The numeric id of the project.
   *
   * @return array Event-data as generated by self::makeEvent().
   */
  public function events(int $projectId):array
  {
    // fetch the relevant data from the pivot-table
    $projectEvents = $this->projectEvents($projectId);

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
   * Form start and end date and time in given timezone and locale.
   *
   * @param array $eventObject The corresponding event object from fetchEvent() or events().
   *
   * @param null|string $timezone Explicit time zone to use, otherwise fetched
   * from user-settings.
   *
   * @param null|string $locale Explicit language setting to use, otherwise
   * fetched from user-settings.
   *
   * @return array
   * ```
   * [ 'start' => array('date' => ..., 'time' => ..., 'allday' => ...), 'end' => ... ]
   * ```
   */
  private function eventTimes(array$eventObject, ?string $timezone = null, ?string $locale = null):array
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
    $endStamp = $end->getTimestamp() + ($allDay ? -1 : 0);

    $startdate = Util::strftime("%x", $startStamp, $timezone, $locale);
    $starttime = Util::strftime("%H:%M", $startStamp, $timezone, $locale);
    $enddate = Util::strftime("%x", $endStamp, $timezone, $locale);
    $endtime = Util::strftime("%H:%M", $endStamp, $timezone, $locale);

    return [
      'timezone' => $timezone,
      'locale' => $locale,
      'allday' => $allDay,
      'start' => [
        'stamp' => $startStamp,
        'date' => $startdate,
        'time' => $starttime,
      ],
      'end' => [
        'stamp' => $endStamp,
        'date' => $enddate,
        'time' => $endtime,
      ],
    ];
  }

  /**
   * Form a brief event date in the given locale.
   *
   * @param array $eventObject The corresponding event object from fetchEvent() or events().
   *
   * @param null|string $timezone Explicit time zone to use, otherwise fetched
   * from user-settings.
   *
   * @param null|string $locale Explicit language setting to use, otherwise
   * fetched from user-settings.
   *
   * @return string
   */
  public function briefEventDate(array $eventObject, ?string $timezone = null, ?string $locale = null):string
  {
    $times = $this->eventTimes($eventObject, $timezone, $locale);

    if ($times['start']['date'] == $times['end']['date']) {
      $datestring = $times['start']['date'].($times['allday'] ? '' : ', '.$times['start']['time']);
    } else {
      $datestring = $times['start']['date'].' - '.$times['end']['date'];
    }
    return $datestring;
  }

  /**
   * Form a "brief long" event date in the given locale.
   *
   * @param array $eventObject The corresponding event object from fetchEvent() or events().
   *
   * @param null|string $timezone Explicit time zone to use, otherwise fetched
   * from user-settings.
   *
   * @param null|string $locale Explicit language setting to use, otherwise
   * fetched from user-settings.
   *
   * @return string
   */
  public function longEventDate(array $eventObject, ?string $timezone = null, ?string $locale = null):string
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
   * @param array $projectEvents List returned by self::events().
   *
   * @param array $calendarIds Array with calendar sorting order, giving
   * the ids of the wanted calendars in the wanted order.
   *
   * @return array Associative array with calendarnames as keys.
   */
  public function eventMatrix(array $projectEvents, array $calendarIds):array
  {
    $result = [];

    $shareOwnerId = $this->shareOwnerId();

    foreach ($calendarIds as $calendarId) {
      $cal = $this->calDavService->calendarById($calendarId);
      $displayName = !empty($cal)
                   ? $cal->getDisplayName()
                   : strval($this->l->t('Unknown Calendar').' '.$calendarId);
      $displayName = str_replace(' (' . $shareOwnerId . ')', '', $displayName);

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

  /**
   * Form an array with the most relevant event data.
   *
   * @param array $eventObject The corresponding event object from fetchEvent() or events().
   *
   * @param null|string $timezone Explicit time zone to use, otherwise fetched
   * from user-settings.
   *
   * @param null|string $locale Explicit language setting to use, otherwise
   * fetched from user-settings.
   *
   * @return array
   * ```
   * [
   *   'times' => $this->eventTimes($eventObject, $timezone, $locale),
   *   'summary' => $eventObject['summary'],
   *   'location' => $eventObject['location'],
   *   'description' => $eventObject['description'],
   * ]
   * ```
   */
  private function eventData(array $eventObject, ?string $timezone = null, ?string $locale = null):array
  {
    // $vcalendar = self::getVCalendar($eventObject);
    // $vobject = self::getVObject($vcalendar);

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
   *
   * @param int $projectId
   *
   * @param null|string|array $calendarIds null to get the events from all
   * calendars or the 'uri' component from OCA\CAFEVDB\Service\ConfigService::CALENDARS.
   *
   * @param null|string $timezone
   *
   * @param null|string $locale
   *
   * @return array
   * ```
   * [
   *   [ 'events' => EVENT_DATA1 ],
   *   [ 'events' => EVENT_DATA2 ],
   *   ...
   * ]
   * ```
   */
  public function projectEventData(int $projectId, mixed $calendarIds = null, ?string $timezone = null, ?string $locale = null):array
  {
    $events = $this->events($projectId);

    if ($calendarIds === null || $calendarIds === false) {
      $calendarIds = $this->defaultCalendars(true);
    } elseif (!is_array($calendarIds)) {
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
   * @param array $events An array with EVENT_URI => CALENDAR_ID.
   *
   * @param string $projectName Short project tag, will form part of the
   * name of the calendar.
   *
   * @param bool $hideParticipants Switch in order to hide participants. For
   * example email attachment sendc @all most likely should not contain the
   * list of attendees.
   *
   * @return A string with the ICAL data.
   *
   * @todo Include local timezone.
   */
  public function exportEvents(array $events, string $projectName, bool $hideParticipants = false)
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
      $vCalendar = VCalendarService::getVCalendar($event);
      $vObject = VCalendarService::getVObject($vCalendar);
      if (empty($vObject)) {
        continue;
      }
      if ($hideParticipants) {
        $vObject->remove('ATTENDEE');
        $vObject->remove('ORGANIZER');
      }
      $result .= $vObject->serialize();
    }
    $result .= "END:VCALENDAR".$eol;

    return $result;
  }

  /**
   * @param bool $public Hide non-public calendars.
   *
   * @return array The IDs of the default calendars.
   *
   * @see ConfigService::CALENDARS
   */
  public function defaultCalendars(bool $public = false):array
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

  /**
   * @param string $uri Calendar tag.
   *
   * @return null|string Return the configured calendar id.
   */
  private function getCalendarId(string $uri):?string
  {
    return $this->getConfigValue($uri.'calendar'.'id');
  }

  /**
   * Delete the configured calendar id.
   *
   * @param string $uri Calendar tag.
   *
   * @return void
   */
  private function deleteCalendarId(string $uri):void
  {
    $this->deleteConfigValue($uri.'calendar'.'id');
  }

  /**
   * Return the configured calendar display name.
   *
   * @param string $uri Calendar tag.
   *
   * @return string
   */
  private function getCalendarDisplayName(string $uri):string
  {
    return $this->getConfigValue($uri.'calendar');
  }

  /**
   * Set the configured calendar display name.
   *
   * @param string $uri Calendar tag.
   *
   * @param string $displayName Value to set.
   *
   * @return bool
   */
  private function setCalendarDisplayName(string $uri, string $displayName):bool
  {
    return $this->setConfigValue($uri.'calendar', $displayName);
  }

  /**
   * Parse the respective event data and make sure the ProjectEvents
   * table is uptodate.
   *
   * @param array $objectData Calendar object data provided by event.
   *
   * @param bool $unregister Whether to unregister the event from projects not
   * mentioned in its category list.
   *
   * @return array
   * ```
   * [
   *   'registered' => [ PROJECT_ID, ... ],
   *   'unregistered' => [ PROJECT_ID, ... ],
   * ]
   * ```
   */
  public function syncCalendarObject(array $objectData, bool $unregister = true):array
  {
    $eventURI   = $objectData['uri'];
    $calId      = $objectData['calendarid'];
    $calURI     = $objectData['calendaruri'];
    // $eventData  = $objectData['calendardata'];
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
        if ($this->register($project, $eventURI, $eventUID, $calId, $calURI, $type)) {
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
   *
   * @param array $objectData Event provided object data.
   *
   * @return void
   */
  private function deleteCalendarObject(array $objectData):void
  {
    $eventURI = $objectData['uri'];
    foreach ($this->eventProjects($eventURI) as $projectEvent) {
      $this->unregister($projectEvent->getProject(), $eventURI);
    }
  }

  /**
   * Unconditionally register the given event with the given project.
   *
   * @param int|Entities\Project $projectOrId The project or its id.
   * @param string $eventURI The event key (external key).
   * @param string $eventUID The event UID.
   * @param int $calendarId The id of the calender the event belongs to.
   * @param string $calendarURI The URI of the calender the event belongs to.
   * @param string $type The event type (VEVENT, VTODO, VJOURNAL, VCARD).
   *
   * @return bool true if the event has been newly registered.
   */
  private function register(
    $projectOrId,
    string $eventURI,
    string $eventUID,
    int $calendarId,
    string $calendarURI,
    string $type,
  ) {
    /** @var Entities\ProjectEvent $entity */
    $entity = $this->findOneBy(['project' => $projectOrId, 'eventUri' => $eventURI]);
    if (empty($entity)) {
      $entity = (new Entities\ProjectEvent())
        ->setProject($projectOrId)
        ->setEventUri($eventURI)
        ->setEventUid($eventUID)
        ->setCalendarId($calendarId)
        ->setCalendarUri($calendarURI)
        ->setType($type);
      $this->persist($entity);
      $added = true;
    } else {
      $entity->setCalendarId($calendarId)
             ->setType($type);
      $this->merge($entity);
      $added = false;
    }
    $this->flush();

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
   * @param int|Entities\Project $projectId The project key.
   *
   * @param string $eventURI The event uri.
   *
   * @return mixed
   */
  public function unchain(int $projectId, string $eventURI)
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
   * @param int $projectId The project key.
   *
   * @param string $eventURI The event key (external key).
   *
   * @return bool \true if the event is registered, otherwise false.
   */
  private function isRegistered(int $projectId, string $eventURI):bool
  {
    //return !empty($this->find(['project' => $projectId, 'eventUri' => $eventURI]));
    return $this->count(['project' => $projectId, 'eventUri' => $eventURI]) > 0;
  }

  /**
   * Inject a new task into the given calendar. This function calls
   * $request is a post-array. One example, in order to create a
   * simple task:
   *
   * @param array $taskData Passed on to VCalendarService::createVCalendarFromRequest()
   * ```
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
   * ```
   *
   * We also support adding a reminder: 'alarm' => ALARMSPEC.
   * ALARMSPEC can be
   * - null, 0 no alarm
   * - int The alarm interval in seconds
   * - [ RELATED => int ] where RELATED is either START or END.
   *   Negative values reach in the past relative to RELATED.
   *
   * @return array task-uri, task-uid on success, null on error.
   * ```
   * [ 'uri' => TASK_URI, 'uid' => TASK_UID, 'task' => TASK_OBJECT ]
   * ```
   */
  public function newTask(array $taskData):?array
  {
    if (empty($taskData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($taskData, VCalendarService::VTODO);
    if (empty($vCalendar)) {
      return null;
    }
    $taskUri = $this->calDavService->createCalendarObject($taskData['calendar'], null, $vCalendar);
    if (!empty($taskUri)) {
      $taskObject = $this->calDavService->getCalendarObject($taskData['calendar'], $taskUri);
      if (!empty($taskObject)) {
        $vCalendar  = VCalendarService::getVCalendar($taskObject);
        $taskObject['calendardata'] = $vCalendar;
        $taskUid   = VCalendarService::getUid($vCalendar);
        return [ 'uri' => $taskUri, 'uid' => $taskUid, 'task' => $taskObject ];
      }
    }
    return null;
  }

  /**
   * Inject a new event into the given calendar.
   *
   * @param array $eventData Passed on to VCalendarService::createVCalendarFromRequest()
   *
   * One example, in order to create a simple non-repeating event:
   *
   * ```
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
   * ```
   *
   * We also support adding a reminder: 'alarm' => unset or interval
   * in seconds (i.e. time-stamp diff). The function may throw
   * errors.
   *
   * @return array|null event-uri, event-uid or null on error.
   * ```
   * [ 'uri' => EVENT_URI, 'uid' => EVENT_UID, 'event' => EVENT_OBJECT ]
   * ```
   */
  public function newEvent(array $eventData):?array
  {
    if (empty($eventData['calendar'])) {
      return null;
    }
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($eventData, VCalendarService::VEVENT);
    if (empty($vCalendar)) {
      return null;
    }
    $eventUri = $this->calDavService->createCalendarObject($eventData['calendar'], null, $vCalendar);
    if (!empty($eventUri)) {
      $eventObject = $this->calDavService->getCalendarObject($eventData['calendar'], $eventUri);
      if (!empty($eventObject)) {
        $vCalendar  = VCalendarService::getVCalendar($eventObject);
        $eventUid   = VCalendarService::getUid($vCalendar);
        $eventObject['calendardata'] = $vCalendar;
        return [ 'uri' => $eventUri, 'uid' => $eventUid, 'event' => $eventObject ];
      }
    }
    return null;
  }

  /**
   * Delete a calendar object given by its URI or UID.
   *
   * @param mixed $calId Numeric calendar id.
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If the identifier ends with '.ics' it is assumed to be an URI,
   * other a UID.
   *
   * @return void
   */
  public function deleteCalendarEntry(mixed $calId, string $objectIdentifier):void
  {
    $this->calDavService->deleteCalendarObject($calId, $objectIdentifier);
  }

  /**
   * Update the calendar entry contained in $object, i.e. write it back to the server.
   *
   * @param array $object Modified event object, previously obtained by findCalendarEntry().
   *
   * @param array $changeSet Array of changes to apply.
   *
   * @return void
   *
   * @see CalDavService::updateCalendarObject()
   */
  public function updateCalendarEntry(array $object, array $changeSet = []):void
  {
    $vCalendar  = VCalendarService::getVCalendar($object);
    if (!empty($changeSet)) {
      if (!empty($vCalendar->VEVENT)) {
        $this->logInfo('EVENT ' . print_r(array_keys($object), true));
        $this->updateCalendarEvent($object, $changeSet);
      } elseif (!empty($vCalendar->VTODO)) {
        $this->updateCalendarTask($object, $changeSet);
      }
    } else {
      $objectURI = $object['uri'];
      $calendarId = $object['calendarid'];
      $this->calDavService->updateCalendarObject($calendarId, $objectURI, $vCalendar);
    }
  }

  /**
   * @param array $event Event object to  modify, previously obtained by findCalendarEntry().
   *
   * @param null|string $status Status to set.
   *
   * @param null|int $percentComplete Percent completed to set.
   *
   * @param null|DateTimeImmutable $dateCompleted The date of completion, if
   * non-null percent completed is set to 100 and the status also to
   * COMPLETED.
   *
   * @return void
   */
  public function setCalendarTaskStatus(
    array $event,
    ?string $status = null,
    ?int $percentComplete = null,
    ?DateTimeImmutable $dateCompleted = null,
  ):void {

    $eventURI = $event['uri'];
    $calendarId = $event['calendarid'];
    $vCalendar  = VCalendarService::getVCalendar($event);

    if ($dateCompleted !== null) {
      $status = self::TASK_COMPLETED;
    }
    if ($status == self::TASK_COMPLETED) {
      $percentComplete = 100;
    }
    if ($percentComplete == 100) {
      $status = self::TASK_COMPLETED;
      if (empty($dateCompleted)) {
        $dateCompleted = new DateTimeImmutable;
      }
    } elseif ($percentComplete == 0) {
      $status = self::TASK_NEEDS_ACTION;
      $dateCompleted = false;
    } else {
      $status = self::TASK_IN_PROCESS;
      $dateCompleted = false;
    }

    if ($status == self::TASK_COMPLETED) {
      if (empty($dateCompleted)) {
        $dateCompleted = new DateTimeImmutable;
      }
    } elseif ($status == self::TASK_NEEDS_ACTION || $status == self::TASK_IN_PROCESS) {
      $dateCompleted = false;
    }

    $vTodo = $vCalendar->VTODO;

    if ($status !== null) {
      $vTodo->STATUS = $status;
      if ($status == self::TASK_COMPLETED) {
        // remove alarms
        unset($vTodo->VALARM);
      }
    }
    if ($percentComplete !== null) {
      $vTodo->{'PERCENT-COMPLETE'} = $percentComplete;
    }
    if ($dateCompleted !== null) {
      if ($dateCompleted === false) {
        unset($vTodo->COMPLETED);
      } else {
        $vTodo->COMPLETED = $dateCompleted;
      }
    }

    $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
  }

  /**
   * Update the event entry contained in $event, modify it and write it back
   * to the server.
   *
   * @param array $event To be modified event object according to $changeSet.
   *
   * @param array $changeSet Array of changes to apply.
   *
   * @return void
   *
   * @see CalDavService::updateCalendarObject()
   */
  public function updateCalendarEvent(array $event, array $changeSet = []):void
  {
    $eventURI = $event['uri'];
    $calendarId = $event['calendarid'];
    $vCalendar  = VCalendarService::getVCalendar($event);

    $vEvent = $vCalendar->VEVENT;

    if (!empty($changeSet['start']) || !empty($changeSet['end'])) {
      $timezone = $this->getDateTimeZone();
      if (array_key_exists('allday', $changeSet)) {
        $allDay = !empty($changeSet['allday']);
      } else {
        $dateValue = $vEvent->DTSTART['VALUE'];
        $dtStart = DateTimeImmutable::createFromInterface($vEvent->DTSTART);
        $allDay = ($dtStart == $dtStart->setTime(0, 0, 0) && $dateValue == 'DATE');
      }
      foreach (['start', 'end'] as $key) {
        if (!empty($changeSet[$key])) {
          $eventProperty = 'DT' . strtoupper($key);
          if ($allDay) {
            $date = $changeSet[$key]->setTime(0, 0, 0);
            $vEvent->{$eventProperty} = $date;
            $vEvent->{$eventProperty}['VALUE'] = 'DATE';
          } else {
            $date = $changeSet[$key]->setTimezone($timezone);
          }
        }
      }
    }
    if (array_key_exists('alarm', $changeSet)) {
      $this->vCalendarService->addVAlarmsFromRequest($vCalendar, $vEvent, $changeSet);
    }
    if (!empty($changeSet['summary'])) {
      $this->vCalendarService->setSummary($vCalendar, $changeSet['summary']);
    }
    if (array_key_exists('description', $changeSet)) {
      $this->vCalendarService->setDescription($vCalendar, $changeSet['description']);
    }
    if (array_key_exists('related', $changeSet)) {
      $this->vCalendarService->addRelations($vCalendar, $vEvent, $changeSet);
    }

    $vEvent->{'LAST-MODIFIED'} = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $vEvent->DTSTAMP = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $this->calDavService->updateCalendarObject($calendarId, $eventURI, $vCalendar);
  }

  /**
   * Update the task entry contained in $event, modify it and write it back
   * to the server.
   *
   * @param array $task To be modified event object according to $changeSet.
   *
   * @param array $changeSet Array of changes to apply.
   *
   * @return void
   *
   * @see CalDavService::updateCalendarObject()
   */
  public function updateCalendarTask(array $task, array $changeSet = []):void
  {
    $taskURI = $task['uri'];
    $calendarId = $task['calendarid'];
    $vCalendar  = VCalendarService::getVCalendar($task);

    $vTodo = $vCalendar->VTODO;

    if (array_key_exists('alarm', $changeSet)) {
      $this->vCalendarService->addVAlarmsFromRequest($vCalendar, $vTodo, $changeSet);
    }
    if (!empty($changeSet['summary'])) {
      $this->vCalendarService->setSummary($vCalendar, $changeSet['summary']);
    }
    if (array_key_exists('description', $changeSet)) {
      $this->vCalendarService->setDescription($vCalendar, $changeSet['description']);
    }
    if (array_key_exists('related', $changeSet)) {
      $this->vCalendarService->addRelations($vCalendar, $vTodo, $changeSet);
    }

    $vTodo->{'LAST-MODIFIED'} = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $vTodo->DTSTAMP = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $this->calDavService->updateCalendarObject($calendarId, $taskURI, $vCalendar);
  }

  /**
   * Find a calendar object by its URI or UID.
   *
   * @param mixed $calId
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If the identifier ends with '.ics' it is assumed to be an URI,
   * other a UID.
   *
   * @return array|null
   *
   * @see CalDavService::getCalendarObject()
   */
  public function findCalendarEntry(mixed $calId, string $objectIdentifier)
  {
    $event = $this->calDavService->getCalendarObject($calId, $objectIdentifier);

    $vCalendar = VCalendarService::getVCalendar($event);
    $event['calendardata'] = $vCalendar;

    return $event;
  }

  /**
   * @param array $event To be cloned event array obtained form
   * findCalendarEntry().
   *
   * @return array The cloned event.
   *
   * @bug The existance of this function is caused by $event not being a class
   * instance.
   */
  public static function cloneCalendarEntry(array $event):array
  {
    foreach ($event as $key => $value) {
      $event[$key] = is_object($value) ? clone($value) : $value;
    }
    return $event;
  }

  /**
   * @return void
   */
  public function playground():void
  {

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
