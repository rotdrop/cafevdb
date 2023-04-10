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
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use \Exception;
use \DateTimeImmutable;
use \DateTimeZone;

use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Recur\MaxInstancesExceededException;
use Sabre\VObject\Recur\NoInstancesException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

use OCA\DAV\Events\CalendarUpdatedEvent;
use OCA\DAV\Events\CalendarDeletedEvent;
use OCA\DAV\Events\CalendarMovedToTrashEvent;

use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectRestoredEvent;
use OCA\DAV\Events\CalendarObjectDeletedEvent;
use OCA\DAV\Events\CalendarObjectMovedToTrashEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;

use OCA\CAFEVDB\Events\BeforeProjectDeletedEvent;
use OCA\CAFEVDB\Events\PreProjectUpdatedEvent;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType as VCalendarType;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

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

  /**
   * @var array Cache the siblings of recurring events by calendar-id,
   * event-uid, sequence, recurrence-id. This cache contains calendar VEvent
   * instances.
   */
  private $eventSiblings;

  /**
   * @var array Cache the siblings of recurring events by project-id,
   * calendar-uri, event-uid, recurrence-id. This cache contains ProjectEvent
   * entities.
   */
  private $projectEventSiblings;

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
   * @param CalendarObjectCreatedEvent|CalendarObjectRestoredEvent $event
   * $event->getObjectData() wiell yield something like
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
  public function onCalendarObjectCreated(
    CalendarObjectCreatedEvent|CalendarObjectRestoredEvent $event,
  ):void {
    $objectData = $event->getObjectData();
    $calendarData = $event->getCalendarData();
    $calendarIds = $this->defaultCalendars();
    $calendarId = $objectData['calendarid'];
    if (!in_array($calendarId, $calendarIds)) {
      // not for us
      return;
    }
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
      $this->logInfo('OBJECT ' . $objectData['uri'] . ' IS NOT FOR US');
      // not for us
      return;
    }
    $calendarData = $event->getCalendarData();
    $objectData['calendaruri'] = $calendarData['uri'];

    $this->syncCalendarObject($objectData);
  }

  /**
   * @param CalendarObjectDeletedEvent|CalendarObjectMovedToTrashEvent $event Calendar event.
   *
   * @return void
   */
  public function onCalendarObjectDeleted(
    CalendarObjectDeletedEvent|CalendarObjectMovedToTrashEvent $event,
  ):void {
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
   * @param CalendarDeletedEvent|CalendarMovedToTrashEvent $event Event object.
   *
   * @return void
   */
  public function onCalendarDeleted(
    CalendarDeletedEvent|CalendarMovedToTrashEvent $event
  ):void {
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
    $this->flush();
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

      foreach (VCalendarService::getAllVObjects($vCalendar) as $vEvent) {
        $categories = VCalendarService::getCategories($vEvent);
        $key = array_search($oldName, $categories);
        $categories[$key] = $newName;
        VCalendarService::setCategories($vEvent, $categories);

        $summary = VCalendarService::getSummary($vEvent);
        if (!empty($summary)) {
          $summary = str_replace($oldName, $newName, $summary);
          VCalendarService::setSummary($vEvent, $summary);
        }
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
    return $this->findBy(['project' => $projectOrId, 'type' => VCalendarType::VEVENT]);
  }

  /**
   * Use the EventIterator to generate all siblings of a recurring event. For
   * non-recurring events return a single element array containing just this
   * VEvent instance.
   *
   * @param int $calendarId
   *
   * @param VCalendar $vCalendar
   *
   * @return array<int, VEvent>
   */
  private function getVEventSiblings(int $calendarId, VCalendar $vCalendar):array
  {
    $vObject = VCalendarService::getVObject($vCalendar);
    $uid = (string)$vObject->UID;
    $sequence = (int)(string)($vObject->SEQUENCE ?? 0);
    $siblings = $this->eventSiblings[$calendarId][$uid][$sequence] ?? null;
    if ($siblings !== null) {
      return $siblings;
    }
    if (!VCalendarService::isEventRecurring($vObject)) {
      $siblings = [ '' => $vObject ];
    } else {
      $vEvents = VCalendarService::getAllVObjects($vCalendar);
      try {
        // there is also the expand() method on the VCalendar ...
        $siblings = [];
        $eventIterator = new EventIterator($vEvents);
        while ($eventIterator->valid()) {
          $sibling = $eventIterator->getEventObject();
          $siblings[(string)$sibling->{'RECURRENCE-ID'}] = $sibling;
          $eventIterator->next();
        }
      } catch (NoInstancesException $e) {
        // This event is recurring, but it doesn't have a single
        // instance. We are skipping this event from the output
        // entirely.
        $siblings = [];
      } catch (MaxInstancesExceededException $e) {
        // hopefully happens never, but ... just live with the sequence we
        // have.
      }
    }
    $this->eventSiblings[$calendarId][$uid][$sequence] = $siblings;
    return $siblings;
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
   *   'uid' => EVENT_UID,
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
    $event['calendarId'] = $event['calendarid'];
    $event['sequence'] = $projectEvent->getSequence();
    $event['recurrenceId'] = $projectEvent->getRecurrenceId();
    $event['seriesUid'] = (string)$projectEvent->getSeriesUid();
    $calendarObject = $this->calDavService->getCalendarObject($event['calendarid'], $event['uri']);
    if (empty($calendarObject)) {
      $this->logDebug('Orphan project event found: ' . print_r($event, true) . (new Exception())->getTraceAsString());
      if (false) {
        // clean up orphaned events
        try {
          $this->unregister($event['projectid'], $event['uri'], flush: true);
        } catch (\Throwable $t) {
          $this->logException($t);
        }
      }
      return null;
    }
    $vCalendar = VCalendarService::getVCalendar($calendarObject);
    // /** @var VEvent $sibling */
    $siblings = $this->getVEventSiblings($event['calendarid'], $vCalendar);
    $vEvent = $siblings[$event['recurrenceId']] ?? null;
    if ($vEvent === null) {
      $this->logError('Unable to find event-sibling for uri ' . $event['uri'] . ' and recurrence-id ' . $event['recurrenceId']);
      return null;
    }
    $this->fillEventDataFromVObject($vEvent, $event);

    return $event;
  }

  /**
   * Convert the given VEvent object to a simpler flat array structure.
   *
   * @param VEvent $vObject
   *
   * @param array $event Output array to fill.
   *
   * @return array Just return $event, with the following data filled in:
   * ```
   * [
   *   ...
   *   'start' => \DateTime,
   *   'end' => \DateTime,
   *   'allday' => BOOL
   *   'summary' => SUMMARY,
   *   'description' => DESCRTION,
   *   'location' => LOCATION,
   *   ...
   * ]
   * ```
   */
  private function fillEventDataFromVObject(VEvent $vObject, array &$event = []):array
  {
    $dtStart = $vObject->DTSTART;
    $dtEnd   = VCalendarService::getDTEnd($vObject);

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
    $recurrenceId = $vObject->{'RECURRENCE-ID'};
    if ($recurrenceId !== null) {
      $event['recurrenceId'] = (string)$recurrenceId;
    }
    $sequence = $vObject->SEQUENCE;
    if ($sequence) {
      $event['sequence'] = (string)$sequence;
    }

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
    $projectEvent = $this->findOneBy(['project' => $projectId, 'eventUri' => $eventURI]);
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
   * @param array $event Array with at least the keys "calendarId", "uri" and
   * "recurrenceId", e.g. an item in the array returned by self::events().
   *
   * @return string
   */
  public static function makeFlatIdentifier(array $eventIdentifier):string
  {
    return implode(':', [
      $eventIdentifier['calendarId'],
      $eventIdentifier['uri'],
      $eventIdentifier['recurrenceId'] ?? '',
    ]);
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
   * @param array $events An array of event identifiers in the form
   * ```
   * [
   *   [ 'calendarId' => ID, 'uri' => EVENT_URI, 'recurrenceID' => RECUR_ID ],
   *   ...
   * ]
   * ```
   * where the recurrence-id may be missing or empty in which case the entire
   * event is exported. If recurrence-ids are specified, then the function
   * checks if $events contains all siblings and in this case simply exports
   * the entire event. If siblings are missing, then only a collection of the
   * requested events is exported.
   *
   * @param string $projectName Short project tag, will form part of the
   * name of the calendar.
   *
   * @param bool $hideParticipants Switch in order to hide participants. For
   * example email attachment sendc @all most likely should not contain the
   * list of attendees.
   *
   * @return A string with the ICAL data.
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

    $selection = [];
    foreach ($events as $eventIdentifier) {
      $calendarId = $eventIdentifier['calendarId'];
      $eventUri = $eventIdentifier['uri'];
      $recurrenceId = $eventIdentifier['recurrenceId'] ?? null;
      if (empty($selection[$calendarId][$eventUri])) {
        $selection[$calendarId] = $selection[$calendarId] ?? [];
        $selection[$calendarId][$eventUri] = [];
      }
      if (!empty($recurrenceId)) {
        $selection[$calendarId][$eventUri][] = $recurrenceId;
      }
    }
    foreach ($selection as $calendarId => $eventUris) {
      foreach ($eventUris as $eventUri => $recurrenceIds) {
        $event = $this->calDavService->getCalendarObject($calendarId, $eventUri);
        $vCalendar = VCalendarService::getVCalendar($event);
        if (!empty($recurrenceIds)) {
          $siblings = $this->getVEventSiblings($event['calendarid'], $vCalendar);
          $allRecurrenceIds = array_keys($siblings);
          if (count($recurrenceIds) == count($allRecurrenceIds)
              && array_diff($allRecurrenceIds, $recurrenceIds) == []
              && array_diff($recurrenceIds, $allRecurrenceIds) == []) {
            $recurrenceIds = []; // all events requested
          }
        }
        if (empty($recurrenceIds)) {
          $this->logInfo('ALL SIBLINGS');
          $vObjects = VCalendarService::getAllVObjects($vCalendar);
          foreach ($vObjects as $vObject) {
            if ($hideParticipants) {
              $vObject = clone $vObject;
              $vObject->remove('ATTENDEE');
              $vObject->remove('ORGANIZER');
            }
            $result .= $vObject->serialize();
          }
        } else {
          $this->logInfo('ONLY SELECTED SIBLINGS' . print_r($recurrenceIds, true) . ' ' . print_r($allRecurrenceIds, true));
          foreach ($recurrenceIds as $recurrenceId) {
            $vObject = $siblings[$recurrenceId] ?? null;
            if (empty($vObject)) {
              $this->logError('Requested export of sibling ' . $recurrenceId . ' of event ' . $eventUri . ', but it does not exist.');
              continue;
            }
            if ($hideParticipants) {
              $vObject = clone $vObject;
              $vObject->remove('ATTENDEE');
              $vObject->remove('ORGANIZER');
            }
            $result .= $vObject->serialize();
          }
        }
      }
    }
    $result .= "END:VCALENDAR" . $eol;

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
   * @param array $objectData Calendar object data provided by event. The
   * calendar data may define a repeating event. Each recurrence instance will
   * get its own slot in the ProjectEvents table in order to decouple the rest
   * of the code from the complicated recurrence rules of iCalendar events.
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
    $this->logInfo('HELLO');

    $eventURI = $objectData['uri'];
    $calId = $objectData['calendarid'];
    $vCalendar = VCalendarService::getVCalendar($objectData);
    $type = VCalendarService::getVObjectType($vCalendar);

    if ($type == VCalendarType::VEVENT) {
      // As a temporary hack enforce all events to be public as there is
      // currently no means to share calendars with really full-access. This is
      // a missing delegation feature in NC.

      /** @var VEvent $vEvent */
      foreach (VCalendarService::getAllVObjects($vCalendar) as $vEvent) {
        if (!empty($vEvent->CLASS) && ($vEvent->CLASS == 'CONFIDENTIAL' || $vEvent->CLASS == 'PRIVATE')) {

          // We first have to fetch the original event, as the data supplied by
          // the change event carries already the disclosed form of the event.
          $originalEvent = $this->calDavService->getCalendarObject($calId, $eventURI);
          $originalVCalendar = VCalendarService::getVCalendar($originalEvent);
          foreach (VCalendarService::getAllVObjects($originalVCalendar) as $vEvent) {
            if (!empty($vEvent->CLASS) && ($vEvent->CLASS == 'CONFIDENTIAL' || $vEvent->CLASS == 'PRIVATE')) {
              $this->logInfo('FORCE EVENT ' . $eventURI . ' TO BE PUBLIC ' . $vEvent->CLASS);
              $vEvent->CLASS = 'PUBLIC';
              $vEvent->{'LAST-MODIFIED'} = new DateTimeImmutable('now', new DateTimeZone('UTC'));
              $vEvent->DTSTAMP = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            }
          }
          $this->calDavService->updateCalendarObject($calId, $eventURI, $originalVCalendar);
          return []; // there will be another event which then is used to update the project links.
        }
      }

      // Perhaps add another hack and turn any full-day multi-day event into
      // its equivalent recurring event (i.e. same number of days, but with
      // recurrence rules.
    }

    $calURI     = $objectData['calendaruri'];
    $vEvent     = VCalendarService::getVObject($vCalendar);

    // Do the sync. The categories stored in the event are
    // the criterion for this.

    $registered = [];
    $unregistered = [];

    $categories = VCalendarService::getCategories($vEvent);
    $projects = $this->projectService->fetchAll(); // @todo: limit by project year + threshold

    $eventUID   = (string)$vEvent->UID;

    $relatedEvents = [ $eventUID ];
    $relatedTo = $vEvent->{'RELATED-TO'} ?? null;
    if ($relatedTo !== null) {
      foreach ($relatedTo as $relatedUid) {
        $relatedEvents[] = (string)$relatedUid;
      }
    }
    $siblings = $this->getVEventSiblings($calId, $vCalendar);
    $isRecurring = (VCalendarService::isEventRecurring($vEvent) && count($siblings) > 0) || count($relatedEvents) > 1;

    $projectRecurrenceIds = [];

    // register current events and record which recurrence-ids are there

    $this->logInfo(' SIBLINGS' . count($siblings) . ' ' . print_r(array_keys($siblings), true));

    /** @var VEvent $sibling */
    foreach ($siblings as $recurrenceId => $sibling) {
      // recurring events can have exceptions, so we need to fetch the
      // categories for all siblings separately.
      $categories = VCalendarService::getCategories($sibling);

      /** @var Entities\Project $project */
      foreach ($projects as $project) {

        if (in_array($project->getName(), $categories)) {

          $projectId = $project->getId();
          $projectRecurrenceIds[$projectId][] = $recurrenceId;

          // register or update the event in the ProjectEvents table.
          $status = $this->register(
            $project,
            calendarURI: $calURI,
            eventUID: $eventUID,
            sequence: (int)(string)($sibling->SEQUENCE ?? 0),
            recurrenceId: $recurrenceId,
            eventURI: $eventURI,
            calendarId: $calId,
            type: $type,
            relatedEvents: $relatedEvents,
            isRecurring: $isRecurring,
            flush: false,
          );
          if ($status) {
            $registered[] = $projectId;
          }
        }
      }
    }

    // now select all stale project events for all projects.

    $criteria = [
      [ 'eventUid' => $eventUID ],
      [ '(&' =>  true ],
      [ '(|' => true ],
      [ '!project' => array_keys($projectRecurrenceIds) ], // untagged projects
    ];

    // tagged projects with potentially excluded event siblings
    foreach ($projectRecurrenceIds as $projectId => $recurrenceIds) {
      $criteria[] = [ '(&project' => $projectId ];
      $criteria[] = [ '!recurrenceId' => $recurrenceIds ];
      $criteria[] = [ ')' => true ];
    }

    // parentheses should be closed automatically, otherwise:
    $criteria[] = [ ')' => true ]; // inner or
    $criteria[] = [ ')' => true ]; // outer and

    $staleProjectEvents = $this->findBy($criteria);

    /** @var Entities\ProjectEvent $projectEvent */
    foreach ($staleProjectEvents as $projectEvent) {
      $this->remove($projectEvent, flush: false, soft: true, hard: false);
      $unregistered[] = $projectEvent->getProject()->getId();
    }

    $this->flush();

    $registered = array_unique($registered);
    $unregistered = array_unique($unregistered);

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
    /** @var Entities\ProjectEvent $projectEvent */
    foreach ($this->eventProjects($eventURI) as $projectEvent) {
      $this->unregister($projectEvent->getProject()->getId(), $eventURI);
    }
  }

  /**
   * Fetch the related events through a cache from the data-base.
   *
   * @param int $projectId
   *
   * @param int $calendarId
   *
   * @param array<int, string> $relatedEvents
   *
   * @return array<string, Entities\ProjectEvent>
   */
  private function findRelatedEvents(
    int $projectId,
    int $calendarId,
    array $relatedEvents,
  ):array {
    $siblings = [];
    foreach (($this->projectEventSiblings[$projectId][$calendarId] ?? []) as $uid => $instances) {
      if (in_array($uid, $relatedEvents)) {
        $siblings = array_replace($siblings, $instances);
      }
    }
    if (empty($siblings)) {
      $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      $siblings = $this->findBy(
        criteria: [
          'project' => $projectId,
          'calendarId' => $calendarId,
          'eventUid' => $relatedEvents,
        ],
        orderBy: [
          'recurrenceId' => 'INDEX',
        ],
      );
      /** @var Entities\ProjectEvent $sibling */
      foreach ($siblings as $recurrenceId => $sibling) {
        $this->projectEventSiblings[$projectId][$calendarId][$sibling->getEventUid()][$recurrenceId] = $sibling;
      }
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
    }


    return $siblings;
  }

  /**
   * Unconditionally register the given event with the given project. Each
   * sibling of a recurring event gets its own slot here.
   *
   * @param int|Entities\Project $projectOrId The project or its id.
   *
   * @param string $calendarURI The URI of the calender the event belongs to.
   *
   * @param string $eventUID The event UID.
   *
   * @param int $sequence The event sequence.
   *
   * @param string $recurrenceId The recurrence id for recurring events.
   *
   * @param string $eventURI The event key (external key).
   *
   * @param int $calendarId The id of the calender the event belongs to.
   *
   * @param string|VCalendarType $type The event type (VEVENT, VTODO, VJOURNAL, VCARD).
   *
   * @param array $relatedEvents Array of UIDs of related events. Registration
   * will "steel" recurrence-ids from related events. The Nextcloud calendar
   * app generates a linked mesh of related events when the user applies
   * changes to "this and future events".
   *
   * @param bool $isRecurring Whether this is one instance in a series of
   * recurring events.
   *
   * @param bool $flush Whether or not to flush the changes to the database default true.
   *
   * @return bool true if the event has been newly registered.
   */
  private function register(
    mixed $projectOrId,
    string $calendarURI,
    string $eventUID,
    int $sequence,
    string $recurrenceId,
    string $eventURI,
    int $calendarId,
    mixed $type,
    array $relatedEvents,
    bool $isRecurring,
    bool $flush,
  ) {
    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    if (empty($relatedEvents)) {
      $relatedEvents = $eventUID;
    }

    $projectId = $projectOrId instanceof Entities\Project ? $projectOrId->getId() : $projectOrId;

    $siblings = $this->findRelatedEvents($projectId, $calendarId, $relatedEvents);

    /** @var Entities\ProjectEvent $entity */
    $entity = $siblings[$recurrenceId] ?? null;

    if (empty($entity)) {
      $entity = new Entities\ProjectEvent();
      $seriesUid = empty($siblings) ? null : array_values($siblings)[0]->getSeriesUid();
      $entity->setProject($projectOrId)
        ->setCalendarUri($calendarURI)
        ->setCalendarId($calendarId)
        ->setRecurrenceId($recurrenceId)
        ->setType($type)
        ->setSeriesUid($seriesUid);
      $added = true;
      $this->persist($entity);
      $this->projectEventSiblings[$projectId][$calendarId][$eventUID][$recurrenceId] = $entity;
    } else {
      $seriesUid = $entity->getSeriesUid();
      $added = false;
    }

    if ($isRecurring && $seriesUid == null) {
      $entity->setSeriesUid(Uuid::create());
    }

    $entity->setEventUid($eventUID)
      ->setEventUri($eventURI)
      ->setSequence($sequence)
      ->setDeleted(null);

    if ($flush) {
      $this->flush();
    }

    $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);

    return $added;
  }

  /**
   * Unconditionally unregister the given event with the given project.
   *
   * @param int $projectId The project key.
   *
   * @param string $eventURI The event key (external key).
   *
   * @param null|string|array $recurrenceId If not null only unregister this
   * particular instance or instances
   *
   * @param bool $flush
   *
   * @return bool true if the event has been removed, false if it was
   * not registered.
   */
  public function unregister(int $projectId, string $eventURI, mixed $recurrenceId = null, bool $flush = true):bool
  {
    $criteria = ['project' => $projectId, 'eventUri' => $eventURI];
    if (!empty($recurrenceId)) {
      $criteria['recurrenceId'] = $recurrenceId;
    }
    $projectEvents = $this->findBy($criteria);
    $needFlush = false;
    foreach ($projectEvents as $projectEvent) {
      $this->remove($projectEvent, flush: false, hard: false, soft: true);
      $needFlush = true;
    }
    if ($flush && $needFlush) {
      $this->flush();
    }
    return true;
  }

  /**
   * Unconditionally unregister the given event with the given project, and
   * remove the project-name from the event's categories list. For recurring
   * events we define exceptions, i.e. additional VEVENT objects without the
   * project-name as category.
   *
   * @param int $projectId The project key.
   *
   * @param int $calendarId The calendar id.
   *
   * @param string $eventURI The event uri.
   *
   * @param null|string $recurrenceId Optional recurrence id in order to
   * define exceptions for recurring events.
   *
   * @return void
   *
   * @todo Combine a sence with deleteCalendarEntry() in order only to delete
   * event instances attached to a project.
   */
  public function unchain(
    int $projectId,
    int $calendarId,
    string $eventUri,
    ?string $recurrenceId = null,
  ):void {
    $criteria = [
      'project' => $projectId,
      'calendarId' => $calendarId,
      'eventUri' => $eventUri,
      'recurrenceId' => $recurrenceId ?? '',
    ];
    if (!empty($recurrenceId)) {
      $criteria['recurrenceId'] = $recurrenceId;
    }

    $projectEvent = $this->findOneBy($criteria);

    if (empty($projectEvent)) {
      $this->logError('Unable to find ' . $eventUri . ' for project ' . $projectId . ' in calendar ' . $calendarId . ' with recurrence id ' . $recurrenceId);
      return;
    }

    $event = $this->calDavService->getCalendarObject($calendarId, $eventUri);
    $vCalendar  = VCalendarService::getVCalendar($event);
    $masterEvent = VCalendarService::getVObject($vCalendar);
    $projectName = $this->projectService->fetchName($projectId);

    if (!empty($recurrenceId)) {
      if (!VCalendarService::isEventRecurring($vCalendar)) {
        $recurrenceId = null; // not recurring: just update
      } else {
        /** @var VEvent $sibling */
        $siblings = $this->getVEventSiblings($calendarId, $vCalendar);
        $siblings = array_filter($siblings, fn(VEvent $vEvent) => in_array($projectName, VCalendarService::getCategories($vEvent)));
        $sibling = $siblings[$recurrenceId] ?? null;
        if (empty($sibling)) {
          $this->logError('Recurring event ' . $eventUri . ' carries no project event instance with recurrence id ' . $recurrenceId);
          $this->unregister($projectId, $eventUri, $recurrenceId, flush: true);
          return;
        }
        if (count($siblings) <= 1) {
          $recurrenceId = null; // recurring with one instance
        }
      }
    }

    $timeStamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if (empty($recurrenceId)) {
      // $masterData = null;
      // $ignoreKeys = ['SEQUENCE', 'EXDATE', 'RRULE', 'CREATED', 'DTSTAMP', 'LAST-MODIFIED', 'RECURRENCE-ID'];
      /** @var VEvent $vEvent */
      foreach (VCalendarService::getAllVObjects($vCalendar) as $vEvent) {
        $categories = VCalendarService::getCategories($vEvent);
        $key = array_search($projectName, $categories);
        if ($key !== false) {
          unset($categories[$key]);
          VCalendarService::setCategories($vEvent, $categories);
          $vEvent->DTSTAMP =
            $vEvent->{'LAST-MODIFIED'} = $timeStamp;
        }
      }
    } else {
      // add another recurrence instance
      $categories = VCalendarService::getCategories($sibling);
      $key = array_search($projectName, $categories);
      if ($key !== false) {
        unset($categories[$key]);
        VCalendarService::setCategories($sibling, $categories);
        $sibling->DTSTAMP =
          $sibling->{'LAST-MODIFIED'} = $timeStamp;
        $vCalendar->add($sibling);
      }
    }

    // tear done the siblings cache
    $eventUid = (string)$masterEvent->UID;
    $sequence = isset($masterEvent->SEQUENCE) ? $masterEvent->SEQUENCE->getValue() : 0;
    unset($this->eventSiblings[$calendarId][$eventUid][$sequence]);

    $this->unregister($projectId, $eventUri, $recurrenceId, flush: true);
    $this->calDavService->updateCalendarObject($calendarId, $eventUri, $vCalendar);

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
   * Delete a calendar object given by its URI or UID and recurrence-id. For
   * recurring events this function defines date or date-time
   * exceptions. Recurring events with only one remaining sibling will be
   * deleted.
   *
   * @param int $calId Numeric calendar id.
   *
   * @param string $objectIdentifier Either the URI or the UID of the
   * object. If the identifier ends with '.ics' it is assumed to be an URI,
   * other a UID.
   *
   * @param null|string $recurrenceId Optional recurrence id in order to
   * define exceptions for recurring events.
   *
   * @return void
   *
   * @todo This is currently not suitable to only delete parts of a recurrence
   * sequence attached to a project. Maybe add a filter callback which selects
   * the siblings which should be deleted.
   */
  public function deleteCalendarEntry(
    int $calId,
    string $objectIdentifier,
    ?string $recurrenceId = null,
  ):void {
    if (!empty($recurrenceId)) {
      $event = $this->calDavService->getCalendarObject($calId, $objectIdentifier);
      $vCalendar  = VCalendarService::getVCalendar($event);
      if (!VCalendarService::isEventRecurring($vCalendar)) {
        $recurrenceId = null; // not recurring: just delete
      } else {
        /** @var VEvent $sibling */
        $siblings = $this->getVEventSiblings($calId, $vCalendar);
        $sibling = $siblings[$recurrenceId] ?? null;
        if (empty($sibling)) {
          return; // not there, ignore -- perhaps report error
        }
        if (count($siblings) <= 1) {
          $recurrenceId = null; // recurring with one instance: just delete
        }
      }
    }

    if (empty($recurrenceId)) {
      $this->calDavService->deleteCalendarObject($calId, $objectIdentifier);
    }

    // more than one sibling left: define an exception
    $vObject = VCalendarService::getVObject($vCalendar);
    if ((string)($sibling->DTSTART['VALUE'] ?? '') == 'DATE') {
      $vObject->add(
        'EXDATE',
        $sibling->DTSTART, [
          'VALUE' => 'DATE',
        ]
      );
    } else {
      $vObject->add(
        'EXDATE',
        $sibling->DTSTART,
      );
    }

    $currentSequence = isset($vObject->SEQUENCE) ? $vObject->SEQUENCE->getValue() : 0;
    $vObject->SEQUENCE = $currentSequence + 1;

    // this instance could already be an explicit exception, if so remove it
    foreach (VCalendarService::getAllVObjects($vCalendar) as $vEvent) {
      if (isset($vEvent->{'RECURRENCE-ID'}) && $vEvent->{'RECURRENCE-ID'} == $recurrenceId) {
        $vCalendar->remove($vEvent);
      }
      $instanceSequence = isset($vEvent->SEQUENCE) ? $vEvent->SEQUENCE->getValue() : 0;
      if ($instanceSequence === $currentSequence) {
        $vEvent->SEQUENCE = $currentSequence + 1;
      }
    }

    // tear done the siblings cache
    unset($this->eventSiblings[$calId][(string)$vObject->UID][$currentSequence]);

    $this->calDavService->updateCalendarObject($calId, $event['uri'], $vCalendar);
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
    if (array_key_exists('classification', $changeSet)) {
      switch ($changeSet['classification']) {
        case 'PUBLIC':
        case 'PRIVATE':
        case 'CONFIDENTIAL':
          $vEvent->CLASS = $changeSet['classification'];
          break;
        default:
          break;
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
      'priority' => 9,
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
      'priority' => 9,
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
