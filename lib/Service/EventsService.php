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

  /** @var ProjectsService */
  private $projectsService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    ProjectsService $projectsService,
    CalDavService $calDavService,
    VCalendarService $vCalendarService
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->projectsService = $projectsService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
    $this->setDatabaseRepository(ProjectEvents::class);
  }

  /**
   * event->getObjectData() returns
   *
   * @code
   * [
   *   'id'            => $row['id'],
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
        VCalendarService->setSummary($vCalendar, $summary);
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
    return $this->findBy(['ProjectId' => $projectid]);
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
    $projects = $this->projectsService->fetchAll();

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
    return $this->persist(new ProjectEvents()
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
