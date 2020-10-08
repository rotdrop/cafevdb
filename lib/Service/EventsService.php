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

/**Events and tasks handling. */
class EventsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const DBTABLE = 'ProjectEvents';

  /** @var DatabaseService */
  private $databaseService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

  public function __construct(
    ConfigService $configService,
    DatabaseService $databaseService,
    CalDavService $calDavService,
    VCalendarService $vCalendarService
  ) {
    $this->configService = $configService;
    $this->databaseService = $databaseService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
  }

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
    $this->syncCalendarObject($objectData);
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

    // unconditionally remove the project-links
    $query = "DELETE FROM ".self::DBTABLE." WHERE CalendarId = ?";
    $this->databaseService->executeQuery($query, [$event->getCalendarId()]);

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

  private function eventProjects($eventId)
  {
    $query = "SELECT ProjectId
  FROM ProjectEvents WHERE EventId = ?
  ORDER BY Id ASC";

    return $this->databaseService->fetchArray($query, [$eventId]);
  }

  /**Fetch the related rows from the pivot-table (without calendar
   * data).
   *
   * @return A flat array with the associated event-ids. Note that
   * even in case of an error an (empty) array is returned.
   */
  private function projectEvents($projectId)
  {
    $query = "SELECT EventId
  FROM ProjectEvents
  WHERE ProjectId = ? AND Type = 'VEVENT'
  ORDER BY Id ASC";

    return $this->databaseService->fetchArray($query, [$projectId]);
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

  private function syncCalendarObject($objectData)
  {}

  private function deleteCalendarObject($objectData)
  {}

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
