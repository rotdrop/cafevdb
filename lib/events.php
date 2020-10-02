<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /** Helper class for event management
   */
  class Events
  {
    /**Link the event (or whatever) into the ProjectEvents table. This
     * function is invoked on every event-creation. The link between
     * projects and events is triggered by the Projekte::Name field
     * through the categories tag of the event. Any event with a
     * category equal to the project's name is considered as belonging
     * to the project, if it addtionally belongs to one of the three
     * shared calendars (conerts, rehearsals, other, management, finance).
     *
     * @param[in] $eventId The OwnCloud-Id of the event.
     *
     * @return Undefined.
     */
    public static function newEventListener($eventId)
    {
      if (Config::inGroup()) {
        self::maybeAddEvent($eventId);
      }
    }

    /**An event has been changed. The link between projects and events
     * goes through their category. Here we have to sync the event's
     * category-list with the ProjectEvents table.
     *
     * @param[in] $eventId The OwnCloud-Id of the event.
     *
     * @return Undefined.
     */
    public static function changeEventListener($eventId)
    {
      if (Config::inGroup()) {
        self::syncEvent($eventId);
      }
    }

    public static function killEventListener($eventId)
    {
      if (Config::inGroup()) {
        self::maybeKillEvent($eventId);
      }
    }

    public static function moveEventListener($eventId)
    {
      // Maybe check whether all members of the orchester group can
      // still access the event. But do nothing for now.
      if (Config::inGroup()) {
      }
    }

    /**Parse the respective event and make sure the ProjectEvents
     * table is uptodate.
     *
     * @param[in] $eventId The OwnCloud-Id of the event.
     *
     * @param[in] $handle Optional. MySQL handle.
     *
     * @return bool, @c true if the event has been added.
     */
    public static function syncEvent($eventId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $event      = self::getEvent($eventId);
      $calId      = self::getCalendarId($event);
      $categories = self::getCategories($event);

      // Now fetch all projects and their names ...
      $projects = Projects::fetchProjects($handle);

      $result = false;
      // Do the sync. The categories stored in the event are
      // the criterion for this.
      foreach ($projects as $prKey => $prName) {
        $registered = self::isRegistered($prKey, $eventId, $handle);
        if (in_array($prName, $categories)) {
          // register or update the event
          $type = self::getEventType($event);
          self::register($prKey, $calId, $eventId, $type, $handle);
          $result = !$registered;
        } else if ($registered) {
          // unregister the event
          self::unregister($prKey, $eventId, $handle);
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    protected static function maybeKillEvent($eventId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $projects = self::eventProjects($eventId, $handle);

      foreach ($projects as $prKey) {
        self::unregister($prKey, $eventId, $handle);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }
    }

    /**Return the OC-event corresponding to the given event-id.
     *
     * @param[in] $eventId OC event-ID.
     *
     * @return An array, the row from the OC-database. BIG FAT NOTE:
     * this is NOT the VObject.
     */
    protected static function getEvent($eventId)
    {
      return \OC_Calendar_App::getEventObject($eventId, false, false);
    }

    /**Inject a new task into the given calendar. This function calls
     * OC_Calendar_Object::createVCalendarFromRequest($request). $request
     * is a post-array. One example, in order to create a simple
     * non-repeating event:
     *
     * array('title' => TITLE,
     *       'description' => TEXT,
     *       'location' => WHERE (may be empty),
     *       'categories' => <list, comma separated>,
     *       'location' => WHERE (may be empty),
     *       'priority' => true/false, (translates to "starred")
     *       'start' => dd-mm-yyyy,
     *       'due' => dd-mm-yyyy,
     *       'calendar' => CALID
     *
     * We also support adding a reminder: 'alarm' => unset or interval
     * in seconds (i.e. time-stamp diff). The function may throw
     * errors.
     */
    public static function newTask($taskData)
    {
      $response = Util::postToRoute('tasks.tasks.addTask',
                                    array(),
                                    array('calendarID' => $taskData['calendar'],
                                          'name' => $taskData['title'],
                                          'starred' => $taskData['priority'],
                                          'due' => $taskData['due'],
                                          'start' => $taskData['start']),
                                    'urlencoded');

      if (!is_array($response) || !isset($response['id'])) {
        throw new \RunTimeException(L::t('Unexpected response while creating new task: %s',
                                         array(print_r($response, true))));
      }
      $id = $response['id'];

      if (isset($taskData['description'])) {
        $response = Util::postToRoute('tasks.tasks.setTaskNote',
                                      array('taskID' => $id),
                                      array('note' => $taskData['description']),
                                      'urlencoded');
        if ($response !== true) {
          throw new \RunTimeException(L::t('Unexpected response while creating new task: %s',
                                           array(print_r($response, true))));
        }
      }
      if (isset($taskData['location']) && $taskData['location'] != '') {
        $response = Util::postToRoute('tasks.tasks.setLocation',
                                      array('taskID' => $id),
                                      array('location' => $taskData['location']),
                                      'urlencoded');
        if ($response !== true) {
          throw new \RunTimeException(L::t('Unexpected response while creating new task: %s',
                                           array(print_r($response, true))));
        }
      }
      if (isset($taskData['categories']) && $taskData['categories'] != '') {
        $response = Util::postToRoute('tasks.tasks.setCategories',
                                      array('taskID' => $id),
                                      array('categories' => $taskData['categories']),
                                      'urlencoded');
        if ($response !== true) {
          throw new \RunTimeException(L::t('Unexpected response while creating new task: %s',
                                           array(print_r($response, true))));
        }
      }
      if (isset($taskData['alarm']) && $taskData['alarm']) {
        $dinterval = new \DateTime();
        $dinterval->add(new \DateInterval('PT'.$taskData['alarm'].'S'));
        $interval = $dinterval->diff(new \DateTime);

        // We always remind a period before the due date.
        $response = Util::postToRoute('tasks.tasks.setReminderDate',
                                      array('taskID' => $id),
                                      array('action' => "DISPLAY",
                                            'invert' => true,
                                            'description' => $taskData['title'],
                                            'related' => "END",
                                            'type' => "DURATION",
                                            'week' => 0,
                                            'day' => $interval->format('%d'),
                                            'hour' => $interval->format('%h'),
                                            'minute' => $interval->format('%i'),
                                            'second' => 0),
                                      'urlencoded');
        if ($response !== true) {
          throw new \RunTimeException(L::t('Unexpected response while creating new task: %s',
                                           array(print_r($response, true))));
        }
      }

      return $id;
    }

    /**Inject a new event into the given calendar. This function calls
     * OC_Calendar_Object::createVCalendarFromRequest($request). $request
     * is a post-array. One example, in order to create a simple
     * non-repeating event:
     *
     * array('title' => TITLE,
     *       'from' => dd-mm-yyyy,
     *       'to' => dd-mm-yyyy,
     *       'allday' => on (or unset),
     *       'location' => WHERE (may be empty),
     *       'categories' => <list, comma separated>,
     *       'description' => TEXT,
     *       'repeat' => 'doesnotrepeat',
     *       'calendar' => CALID
     *
     * We also support adding a reminder: 'alarm' => unset or interval
     * in seconds (i.e. time-stamp diff). The function may throw
     * errors.
     */
    public static function newEvent($eventData)
    {
      \OC::$CLASSPATH['OC_Calendar_Object'] = 'calendar/lib/object.php';

      $errarr = \OC_Calendar_Object::validateRequest($eventData);
      if ($errarr) {
        throw new \InvalidArgumentException(
          "\n".
          L::t("Unable to create event from given data:").
          "\n".
          print_r($eventData, true));
      }

      $calendarId = $eventData['calendar'];
      $vcalendar = \OC_Calendar_Object::createVCalendarFromRequest($eventData);
      $alarm = isset($eventData['alarm']) ? $eventData['alarm'] : false;
      if ($alarm !== false) {
      /*
BEGIN:VALARM
DESCRIPTION:
ACTION:DISPLAY
TRIGGER;VALUE=DURATION:-P1D
X-KDE-KCALCORE-ENABLED:TRUE
END:VALARM
      */
        $valarm =$vcalendar->createComponent('VALARM');
        $valarm->DESCRIPTION = $eventData['title'];
        $valarm->ACTION = 'DISPLAY';
        $dinterval = new \DateTime();
        $dinterval->add(new \DateInterval('PT'.$alarm.'S'));
        $interval = $dinterval->diff(new \DateTime);
        $alarmValue = sprintf('%sP%s%s%s%s',
                              $interval->format('%r'),
                              $interval->format('%d') > 0 ? $interval->format('%dD') : null,
                              ($interval->format('%h') > 0 || $interval->format('%i') > 0) ? 'T' : null,
                              $interval->format('%h') > 0 ? $interval->format('%hH') : null,
                              $interval->format('%i') > 0 ? $interval->format('%iM') : null);
        $valarm->add('TRIGGER', $alarmValue, array('VALUE' => 'DURATION'));
        $vcalendar->VEVENT->add($valarm);
      }

      $eventId = \OC_Calendar_Object::add($calendarId, $vcalendar->serialize());

      return $eventId;
    }

    /**Really delete all traces of the event from CAFEVDB and the
     * calendars. Events attached to more than one project are not
     * remove, however.
     *
     * @return @c true iff the event could be handled successfully,
     * i.e. only SQL-errors result in a return value @c false.
     *
     */
    static public function deleteEvent($eventId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $result = true;
      if (count(self::eventProjects($eventId, $handle)) === 1) {
        // delete it :)
        $query = "DELETE FROM ProjectEvents WHERE EventId = $eventId";
        $result = mySQL::query($query);

        if ($result === true) {
          // remove it also from the calendar
          try {
            \OC_Calendar_Object::delete($eventId);
          } catch (\Exception $e) {
            $result = false;
          }
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Return the OC calendar-id
     *
     * @param[in] $event Either an id or the event data from the
     * data-base. The event-data will be fetched if $event is an id.
     *
     * @return The OC calendar id the event belongs to.
     */
    protected static function getCalendarId($event)
    {
      if (!is_array($event)) {
        $event = self::getEvent($event);
      }
      return $event['calendarid'];
    }

    /**Return the OC-id corresponding to $event, which may be an
     * OC-event, an event-id, or our representation of events.
     */
    protected static function eventId($event)
    {
      $result = false;
      if (is_array($event)) {
        if (isset($event['EventId'])) {
          $result = $event['EventId'];
        } else if (isset($event['id'])) {
          $result = $event['id'];
        }
      } else {
        $result = $event;
      }
      return $result;
    }

    /**Return the VCALENDAR object corresponding to $event.
     *
     * @param[in] $event Mixed, either an ID, or the corresponding row
     * from the OC database, or an array where the 'object' field
     * contains the row from the OC database, or even already a
     * \\Sabre\\VObject\\Component\\VCalendar object, which then simply is returned.
     *
     * @return The VCALENDAR object or @c false if an error occurs.
     */
    protected static function &getVCalendar(&$event)
    {
      if ($event instanceof \Sabre\VObject\Component\VCalendar) {
        return $event;
      }

      if (is_array($event)) {
        if (!isset($event['calendardata']) && isset($event['object'])) {
          $event = $event['object'];
        }
        if (!isset($event['calendardata'])) {
          throw new \InvalidArgumentException('Passed argument does not contain calendar-data');
        }
        $vcalendar = \Sabre\VObject\Reader::read($event['calendardata']);
      } else {
        $vcalendar = \OC_Calendar_App::getVCalendar($event, false, false);
      }

      return $vcalendar;
    }

    /**Return a reference to the object contained in a Sabre VCALENDAR
     * object. This is a reference to allow for modification of the
     * $vCalendar object.
     *
     * @param[in] $stuff Something understood by getVCalendar().
     *
     * @return A reference to the inner object.
     */
    protected static function &getVObject(&$stuff)
    {
      $vcalendar = self::getVCalendar($stuff);

      if (isset($vcalendar->VEVENT)) {
        $vobject = &$vcalendar->VEVENT;
      } else if (isset($vcalendar->VTODO)) {
        $vobject = &$vcalendar->VTODO;
      } else if (isset($vcalendar->VJOURNAL)) {
        $vobject = &$vcalendar->VJOURNAL;
      } else if (isset($vcalendar->VCARD)) {
        $vobject = &$vcalendar->VCARD;
      } else {
        throw new \Exception('Called with empty of no VObject');
      }

      return $vobject;
    }

    /**Return the type of the respective calendar object.
     *
     * @param[in] $stuff Something understood by getVCalendar().
     *
     * @return string Either VEVENT, VTODO, VJOURNAL or VCARD.
     */
    protected static function getEventType($stuff)
    {
      $vcalendar = self::getVCalendar($stuff);

      if (isset($vcalendar->VEVENT)) {
        return 'VEVENT';
      } else if (isset($vcalendar->VTODO)) {
        return 'VTODO';
      } else if (isset($vcalendar->VJOURNAL)) {
        return 'JVOURNAL';
      } else if (isset($vcalendar->VCARD)) {
        return 'VCARD';
      } else {
        throw new \InvalidArgumentException('Called with empty of no VObject');
      }
      return '';
    }

    /**Return the category list for the given event.
     *
     * @param[in] $stuff Something understoood by getVCalendar()
     *
     * @return An array with the categories for the event.
     */
    protected static function getCategories($stuff)
    {
      // get the inner object
      $vobject = self::getVObject($stuff);

      if (isset($vobject->CATEGORIES)) {
        $categories = $vobject->CATEGORIES->getParts();
      } else {
        $categories = array();
      }

      return $categories;
    }

    /**Set the category list for the given event.
     *
     * @param[in] $stuff Something understood by getVCalendar().
     *
     * @param[in] $categories A string-array with the new categories.
     *
     * @return The VCALENDAR object with the new categories installed.
     */
    protected static function setCategories($stuff, $categories)
    {
      $vobject = self::getVObject($stuff);

      $vobject->CATEGORIES = $categories;

      return $vcalendar;
    }

    /**Return the summary for the given event.
     *
     * @param[in] $stuff Something understood by getVCalendar().
     *
     * @return A string with the event's brief title
     */
    public static function getSummary($stuff)
    {
      $vobject = self::getVObject($stuff);

      $summary = $vobject->SUMMARY;

      return $summary;
    }

    /**Set the summary (brief title) for the given event.
     *
     * @param[in] $stuff Something understodd by getVCalendar().
     *
     * @param[in] $summary A string with the new summary.
     *
     * @return The VCALENDAR object with the new summary installed.
     */
    protected static function setSummary($stuff, $summary)
    {
      $vobject = self::getVObject($stuff);

      $vobject->SUMMARY = $summary;

      return $vcalendar;
    }

    /**Return the description for the given event.
     *
     * @param[in] $stuff Something understood by getVCalendar().
     *
     * @return A string with the event's brief title
     */
    public static function getDescription($stuff)
    {
      $vobject = self::getVObject($stuff);

      $description = $vobject->DESCRIPTION;

      return $description;
    }

    /**Add the given event to any of the known projects if its category
     * list contains the project-tag.
     *
     * @param[in] $eventId OC event-id.
     *
     * @param[in] $handle Optional, mySQL data-base handle.
     *
     * @return true on success or false on error.
     *
     * @bug Error checking is not implemented.
     */
    protected static function maybeAddEvent($eventId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $event      = self::getEvent($eventId);
      $categories = self::getCategories($event);
      $calId      = self::getCalendarId($event);

      // Now fetch all projects and their names ...
      $projects = Projects::fetchProjects($handle);

      // Search for matching project-tags. We assume that this is
      // not really timing critical, i.e. that there are only few
      // projects. Events may belong to more than one projet.
      foreach ($projects as $prKey => $prName) {
        if (in_array($prName, $categories)) {
          // Ok. Link it in.
          $type = self::getEventType($event);
          self::register($prKey, $calId, $eventId, $type, $handle);
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    /**Unconditionally register the given event with the given project.
     *
     * @param[in] $projectId The project key.
     * @param[in] $calendarId The id of the calender the vent belongs to.
     * @param[in] $eventId The event key (external key).
     * @param[in] $type The event type (VEVENT, VTODO, VJOURNAL, VCARD).
     * @param[in] $handle mySQL handle or false.
     *
     * @return Undefined.
     */
    protected static function register($projectId,
                                       $calendarId,
                                       $eventId,
                                       $type,
                                       $handle = false)
    {
      $values = array('ProjectId' => $projectId,
                      'CalendarID' => $calendarId,
                      'EventId' => $eventId,
                      'Type' => $type);
      $result = mySQL::insert('ProjectEvents', $values, $handle, mySQL::UPDATE);
    }

    /**Unconditionally unregister the given event with the given project.
     *
     * @param[in] $projectId The project key.
     * @param[in] $eventId The event key (external key).
     * @param[in] $handle mySQL handle or false.
     *
     * @return Undefined.
     */
    public static function unregister($projectId, $eventId,
                                      $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Link it in.
      $query =<<<__EOT__
DELETE FROM `ProjectEvents`
  WHERE
    `ProjectId` = $projectId
    AND
    `EventId` = $eventId
__EOT__;
      mySQL::query($query, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }
    }

    /**Unconditionally unregister the given event with the given
     * project, and remove the project-name from the event's categories
     * list.
     *
     * @param[in] $projectId The project key.
     * @param[in] $eventId The event key (external key).
     * @param[in] $handle mySQL handle or false.
     *
     * @return Undefined.
     */
    public static function unchain($projectId, $eventId,
                                   $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      self::unregister($projectId, $eventId, $handle);

      $projectName = Projects::fetchName($projectId, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      $vcalendar  = self::getVCalendar($eventId);
      $categories = self::getCategories($vcalendar);

      $key = array_search($projectName, $categories);
      unset($categories[$key]);

      $vcalendar = self::setCategories($vcalendar, $categories);

      \OC_Calendar_Object::edit($eventId, $vcalendar->serialize());
    }

    /**Replace an old category with a new one. As the code is taylored
     * such that the category is part of the short-description of the
     * event, we also do a string replace in the title field to keep
     * things more or less consistent.
     *
     * @param[in] $event Event ID, or OC-Event, or CAFEVDB-Event.
     *
     * @param[in] $old Old category.
     *
     * @param[in] $new New category.
     *
     * @param[in] $tweakSummary Also do a string-replace on the summary
     * field, defaults to @c false.
     *
     * @return Undefined.
     */
    public static function replaceCategory($event, $old, $new, $tweakSummary = false)
    {
      $vcalendar  = self::getVCalendar($event);
      $categories = self::getCategories($vcalendar);

      $key = array_search($old, $categories);
      if ($new === false) {
        unset($categories[$key]);
      } else {
        $categories[$key] = $new;
      }

      $vcalendar = self::setCategories($vcalendar, $categories);

      // optionally also tweak the summary field.
      if ($tweakSummary) {
        $summary = self::getSummary($vcalendar);
        if ($summary && $summary != '') {
          $summary = str_replace($old, $new, $summary);
          $vcalendar = self::setSummary($vcalendar, $summary);
        }
      }
      \OC_Calendar_Object::edit(self::eventId($event), $vcalendar->serialize());
    }

    /**Test if the given event is linked to the given project.
     *
     * @param[in] $projectId The project key.
     * @param[in] $eventId The event key (external key).
     * @param[in] $handle mySQL handle or false.
     *
     * @return @c true if the event is registered, otherwise false.
     */
    protected static function isRegistered($projectId, $eventId,
                                           $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query =<<<__EOT__
FROM `ProjectEvents`
WHERE `EventId` = $eventId
      AND
     `ProjectId` = $projectId
__EOT__;
      $result = mySQL::queryNumRows($query, $handle) > 0;

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
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
      $calendarNames = array();

      $result = array();

      foreach ($calendarIds as $id) {
        $result[$id] = array(
          'name' => strval(L::t('Unknown Calendar').' '.$id),
          'events' => array());
        $cal = \OC_Calendar_Calendar::find($id);
        if ($cal != false && $cal['displayname']) {
          $result[$id]['name'] = $cal['displayname'];
        }
      }
      $result[-1] = array('name' => strval(L::t('Miscellaneous Calendars')),
                          'events' => array());

      foreach ($projectEvents as $event) {
        $object = $event['object'];
        $calId = array_search($object['calendarid'], $calendarIds);
        if ($calId === false) {
          $result[-1]['events'][] = $event;
        } else {
          $calId = $calendarIds[$calId];
          $result[$calId]['events'][] = $event;
        }
      }

      return $result;
    }

    public static function eventCompare($a, $b)
    {
      if ($a['object']['startdate'] == $b['object']['startdate']) {
        return 0;
      }
      if ($a['object']['startdate'] < $b['object']['startdate']) {
        return -1;
      } else {
        return 1;
      }
    }

    /**Fetch the event and convert the time-stamps to a PHP
     * DateTime-object with time-zone UTC.
     */
    public static function fetchEvent($id)
    {
      $event = \OC_Calendar_App::getEventObject($id, false, false);
      if ($event === false) {
        return false;
      }

      $utc = new \DateTimeZone("UTC");
      $event['startdate'] = new \DateTime($event['startdate'], $utc);
      $event['enddate']   = new \DateTime($event['enddate'], $utc);

      return $event;
    }

    /**Form start and end date and time in given timezone and locale,
     * return is an array
     *
     * array('start' => array('date' => ..., 'time' => ..., 'allday' => ...), 'end' => ...)
     *
     * @param $eventObject The corresponding event object, i.e. the
     * data stored in the data-base.
     *
     * @param $timezone Explicit time zone to use, otherwise fetched
     * from user-settings.
     *
     * @param $locale Explicit language setting to use, otherwise
     * fetched from user-settings.
     *
     */
    public static function eventTimes($eventObject, $timezone = null, $locale = null)
    {
      if ($timezone === null) {
        $timezone = Util::getTimezone();
      }
      if ($locale === null) {
        $locale = Util::getLocale();
      }

      $start = $eventObject['startdate'];
      $end   = $eventObject['enddate'];

      $startStamp = $start->getTimestamp();
      $endStamp = $end->getTimestamp();

      // Attention: a full-time event is flagged by start- and end-time
      // being 0:00 in __UTC__, in principle this is a bug, isn't it???
      // There is just one solution: check the raw calendar data for
      //
      // DTSTART;VALUE=DATE:
      // DTEND;VALUE=DATE:
      //
      // If so, the event is meant for the whole day. In this case we
      // just fetch the date in UTC and forget about the time.
      $startEntireDay = strstr($eventObject['calendardata'], 'DTSTART;VALUE=DATE:') !== false;
      $endEntireDay = strstr($eventObject['calendardata'], 'DTEND;VALUE=DATE:') !== false;

      if ($startEntireDay) {
        $startdate = Util::strftime("%x", $startStamp, 'UTC', $locale);
        $tz = new \DateTimeZone($timezone);
        $start = new \DateTime($startdate.' 00:00:00', $tz);
        $startStamp = $start->getTimestamp();
      }
      if ($endEntireDay) {
        $enddate = Util::strftime("%x", $endStamp, 'UTC', $locale);
        $tz = new \DateTimeZone($timezone);
        $end = new \DateTime($enddate.' 00:00:00', $tz);
        $endStamp = $end->getTimestamp();
        $endStamp -= 1;
      }

      $startdate = Util::strftime("%x", $startStamp, $timezone, $locale);
      $starttime = Util::strftime("%H:%M", $startStamp, $timezone, $locale);
      $enddate = Util::strftime("%x", $endStamp, $timezone, $locale);
      $endtime = Util::strftime("%H:%M", $endStamp, $timezone, $locale);

      return array('timezone' => $timezone,
                   'locale' => $locale,
                   'start' => array('stamp' => $startStamp,
                                    'date' => $startdate,
                                    'time' => $starttime,
                                    'allday' => $startEntireDay),
                   'end' => array('stamp' => $endStamp,
                                  'date' => $enddate,
                                  'time' => $endtime,
                                  'allday' => $endEntireDay));
    }

    /**Form a brief event date in the given locale. */
    public static function briefEventDate($eventObject, $timezone = null, $locale = null)
    {
      $times = self::eventTimes($eventObject, $timezone, $locale);

      if ($times['start']['date'] == $times['end']['date']) {
        $datestring = $times['start']['date'].($times['start']['allday'] ? '' : ', '.$times['start']['time']);
      } else {
        $datestring = $times['start']['date'].' - '.$times['end']['date'];
      }
      return $datestring;
    }

    /**Form a brief long event date in the given locale. */
    public static function longEventDate($eventObject, $timezone = null, $locale = null)
    {
      $times = self::eventTimes($eventObject, $timezone, $locale);

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

    /**Form an array with the most relevant event data. */
    public static function eventData($eventObject, $timezone = null, $locale = null)
    {
      $vcalendar = self::getVCalendar($eventObject);
      $vobject = self::getVObject($vcalendar);

      $times = self::eventTimes($eventObject, $timezone, $locale);

      $quoted = array('\,' => ',', '\;' => ';');
      $summary = strtr($eventObject['summary'], $quoted);
      $location = strtr($vobject->LOCATION, $quoted);
      $description = strtr($vobject->DESCRIPTION, $quoted);

      return array('times' => $times,
                   'summary' => $summary,
                   'location' => $location,
                   'description' => $description);
    }

    /**Return event data for given project id and calendar id*/
    public static function projectEventData($projectId, $calendarIds = null, $timezone = null, $locale = null)
    {
      $events = self::events($projectId);

      if ($calendarIds === null || $calendarIds === false) {
        $calendarIds = self::defaultCalendars(true);
      } else if (!is_array($calendarIds)) {
        $calendarIds = array($calendarIds);
      }

      $result = array();

      foreach ($calendarIds as $id) {
        $result[$id] = array(
          'name' => strval(L::t('Unknown Calendar').' '.$id),
          'events' => array());
        $cal = \OC_Calendar_Calendar::find($id);
        if ($cal != false && $cal['displayname']) {
          $result[$id]['name'] = $cal['displayname'];
        }
      }
      foreach ($events as $event) {
        $object = $event['object'];
        $calId = array_search($object['calendarid'], $calendarIds);
        if ($calId !== false) {
          $calId = $calendarIds[$calId];
          $result[$calId]['events'][] = self::eventData($object, $timezone, $locale);
        }
      }

      return array_values($result);
    }

    /**Fetch the list of events associated with $projectId. This
     * functions fetches all the data, not only the pivot-table. Time
     * stamps from the data-base are converted to PHP DateTime()-objects
     * with UTC time-zone.
     *
     * @param[in] $projectId The numeric id of the project.
     *
     * @param[in] $handle Data-base handle or false.
     *
     * @return Full event data for this project.
     */
    public static function events($projectId, $handle = false)
    {
      $events = array();

      $ownConnection = ($handle === false);
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $utc = new \DateTimeZone("UTC");

      $query =<<<__EOT__
SELECT `Id`,`EventId`,`CalendarId`
  FROM `ProjectEvents`
  WHERE `ProjectId` = $projectId AND `Type` = 'VEVENT'
  ORDER BY `Id` ASC
__EOT__;

      $result = mySQL::query($query, $handle);
      if ($result !== false) {
        while ($line = mySQL::fetch($result)) {
          $event = array('Id' => $line['Id'],
                         'EventId' => $line['EventId'],
                         'CalendarId' => $line['CalendarId'],
                         'object'     => \OC_Calendar_App::getEventObject($line['EventId'], false, false));

          if ($event['object'] !== false) {
            $event['object']['startdate'] = new \DateTime($event['object']['startdate'], $utc);
            $event['object']['enddate'] = new \DateTime($event['object']['enddate'], $utc);
            $events[] = $event;
          }
        }
        mySQL::freeResult($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      usort($events, "\CAFEVDB\Events::eventCompare");

      return $events;
    }

    /**Fetch the related rows from the pivot-table (without calendar
     * data).
     *
     * @return A flat array with the associated event-ids. Note that
     * even in case of an error an (empty) array is returned.
     */
    public static function projectEvents($projectId, $handle = false)
    {
      $events = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query =<<<__EOT__
SELECT `CalendarId`,`EventId`
  FROM `ProjectEvents`
  WHERE `ProjectId` = $projectId AND `Type` = 'VEVENT'
  ORDER BY `Id` ASC
__EOT__;

      $result = mySQL::query($query, $handle);
      if ($result !== false) {
        while ($line = mySQL::fetch($result)) {
          $events[] = $line['EventId'];
        }
        mySQL::freeResult($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $events;
    }

    protected static function eventProjects($eventId, $handle = false)
    {
      $projects = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query =<<<__EOT__
SELECT `ProjectId`
  FROM `ProjectEvents` WHERE `EventId` = $eventId
  ORDER BY `Id` ASC
__EOT__;

      $result = mySQL::query($query, $handle);
      if ($result !== false) {
        while ($line = mySQL::fetch($result)) {
          $projects[] = $line['ProjectId'];
        }
        mySQL::freeResult($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $projects;
    }

    /**Export the given events in ICAL format. The events need not
     * belong to the same calendar.
     *
     * @@TODO: this should be Sabre -> serializ?
     *
     * @param[in] $events An array with event Ids.
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
        ."PRODID:ownCloud Calendar " . \OCP\App::getAppVersion('calendar') .$eol
        ."X-WR-CALNAME:" . $projectName . ' (' . Config::$opts['orchestra'] . ')' . $eol;

      foreach ($events as $id) {
        $text = \OC_Calendar_Export::export($id, \OC_Calendar_Export::EVENT);
        // Well, not elegant, but for me the easiest way to strip the
        // BEGIN/END VCALENDAR tags
        $data = Util::explode($eol, $text);
        $silent = true;
        foreach ($data as $line) {
          if (strncmp($line, "BEGIN:VEVENT", strlen("BEGIN:VEVENT")) == 0) {
            $silent = false;
          }
          if (!$silent) {
            $result .= $line.$eol;
          }
          if (strncmp($line, "END:VEVENT", strlen("END:VEVENT")) == 0) {
            $silent = true;
          }
        }
      }
      $result .= "END:VCALENDAR".$eol;

      return $result;
    }

  }; // class Events

}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
