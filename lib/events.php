<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    /**Make sure the data-base provides the necessary table(s).
     * This function may through an exception.
     */
    public static function configureDatabase($_handle = false) 
    {
      try {
        if ($_handle === false) {
          Config::init();
          $handle = mySQL::connect(Config::$dbopts);
        } else {
          $handle = $_handle;
        }

        $query = 'CREATE TABLE IF NOT EXISTS `ProjectEvents` (
 `Id` int(11) NOT NULL AUTO_INCREMENT,
 `ProjectId` int(11) DEFAULT NULL,
 `CalendarId` int(11) NOT NULL,
 `EventId` int(11) NOT NULL,
 PRIMARY KEY (`Id`),
 UNIQUE KEY `ProjectId` (`ProjectId`,`EventId`)
) DEFAULT CHARSET=utf8';

        $result = mySQL::query($query, $handle);

        if ($_handle === false) {
          mySQL::close($handle);
        }
      } catch (\Exception $e) {

        if ($_handle === false) {
          mySQL::close($handle);
        }

        throw $e;
      }

      return $result;
    }

    /**Link the event (or whatever) into the ProjectEvents table. This
     * function is invoked on every event-creation. The link between
     * projects and events is triggered by the Projekte::Name field
     * through the categories tag of the event. Any event with a
     * category equal to the project's name is considered as belonging
     * to the project, it it addtionally belongs to one of the three
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

    public static function killCalendarListener($calendarId)
    {
      if (!Config::inGroup()) {
        return;
      }

      Config::init();
      $handle = mySQL::connect(Config::$dbopts);

      // Execute the show-stopper

      $query = "DELETE FROM `ProjectEvents` WHERE `CalendarId` = $calendarId";

      mySQL::query($query, $handle);

      mySQL::close($handle);

      // And remove the calendar from our config space.
      foreach (explode(',',Config::DFLT_CALS) as $key) {
        $id = Config::getValue($key.'calendars'.'id');
        if ($id === $calendarId) {
          $id = Config::setValue($key.'calendars'.'id','');
          // ... but we keep the name nevertheless.-
        }
      }
    }

    public static function editCalendarListener($calendarId)
    {
      if (!Config::inGroup()) {
        return;
      }

      // We simply should update our idea of the name of the calender if
      // it is one of our four calendars, rename the calendar back to
      // what we want it to be
      foreach (explode(',',Config::DFLT_CALS) as $key) {
        $id = Config::getValue($key.'calendar'.'id');
        if ($id === $calendarId) {
          $name    = Config::getValue($key.'calendar');
          $cal     = \OC_Calendar_Calendar::find($calendarId);
          $dpyName = $cal['displayname'];
          if (!$name || $name == '') {
            Config::setValue($key.'calendar', $dpyName);
          } else if ($cal['displayname'] != $name) {
            // revert it
            \OC_Calendar_Calendar::editCalendar($calendarId, $name);
          }
        }
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
        if (in_array($prName, $categories)) {
          if (!self::isRegistered($prKey, $eventId, $handle)) {
            // register the event
            self::register($prKey, $eventId, $calId, $handle);
            $result = true;
          }
        } else if (self::isRegistered($prKey, $eventId, $handle)) {
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
     * @return An array, the row from the OC-database.
     */
    protected static function getEvent($eventId)
    {
      return \OC_Calendar_App::getEventObject($eventId, false, false);
    }

    /**Inject a new event into the given calendar. This function calls
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
     * @param[in] $event Mixed, either an ID are the corresponding row
     * from the OC database.
     *
     * @return The VCALENDAR object or @c false if an error occurs.
     */
    protected static function getVCalendar($event)
    {
      if (is_array($event)) {
        if (isset($event['calendardata'])) {
          $vcalendar = \Sabre\VObject\Reader::read($event['calendardata']);
        } else if (isset($event['object'])) {
          $vcalendar = self::getVCalendar($event['object']);
        }
      } else {
        $vcalendar = \OC_Calendar_App::getVCalendar($event, false, false);
      }

      return $vcalendar;
    }

    /**Return a reference to the object contained in a Sabre VCALENDAR
     * object. This is a reference to allow for modification of the
     * $vCalendar object.
     *
     * @param[in] $vcalendar \OC_VObject
     *
     * @return A reference to the inner object.
     */
    protected static function &getVObject(&$vcalendar)
    {
      if (isset($vcalendar->VEVENT)) {
        $vobject = &$vcalendar->VEVENT;
      } else if (isset($vcalendar->VTODO)) {
        $vobject = &$vcalendar->VTODO;
      } else if (isset($vcalendar->VJOURNAL)) {
        $vobject = &$vcalendar->VJOURNAL;
      } else if (isset($vcalendar->VCARD)) {
        $vobject = &$vcalendar->VCARD;
      } else {
        throw new \Exception('Called with empty of no VObjecvt');
      }

      return $vobject;
    }
  
    /**Return the category list for the given event.
     *
     * @param[in] $stuff Either a VCALENDAR object, or the inner VEVENT,
     * VTODO etc. or an OC-event (array, row from the data-base) or just
     * an event Id (in which case the row from the data-base will be
     * fetched).
     *
     * @return An array with the categories for the event.
     */
    protected static function getCategories($stuff)
    {
      if ($stuff instanceof \Sabre\VObject\Component\VCalendar) {
        $vcalendar = $stuff;
      } else {
        $vcalendar = self::getVCalendar($stuff);
      }    
      $vobject = self::getVObject($vcalendar);

      if (isset($vobject->CATEGORIES)) {
        $categories = $vobject->CATEGORIES->getParts();
      } else {
        $categories = array();
      }

      return $categories;
    }

    /**Set the category list for the given event.
     *
     * @param[in] $stuff Either a VCALENDAR object or an OC-event
     * (array, row from the data-base) or just an event Id (in which
     * case the row from the data-base will be fetched).
     *
     * @param[in] $categories A string-array with the new categories.
     *
     * @return The VCALENDAR object with the new categories installed.
     */
    protected static function setCategories($stuff, $categories)
    {
      if ($stuff instanceof \Sabre\VObject\Component\VCalendar) {
        $vcalendar = $stuff;
      } else {
        $vcalendar = self::getVCalendar($stuff);
      }
      $vobject = self::getVObject($stuff);

      $vobject->CATEGORIES = $categories;

      return $vcalendar;
    }

    /**Return the summary for the given event.
     *
     * @param[in] $stuff Either a VCALENDAR object, or the inner VEVENT,
     * VTODO etc. or an OC-event (array, row from the data-base) or just
     * an event Id (in which case the row from the data-base will be
     * fetched).
     *
     * @return A string with the event's brief title
     */
    public static function getSummary($stuff)
    {
      if ($stuff instanceof \Sabre\VObject\Component\VCalendar) {
        $vcalendar = $stuff;
      } else {
        $vcalendar = self::getVCalendar($stuff);
      }    
      $vobject = self::getVObject($vcalendar);

      $summary = $vobject->SUMMARY;

      return $summary;
    }

    /**Set the summary (brief title) for the given event.
     *
     * @param[in] $stuff Either a VCALENDAR object or an OC-event
     * (array, row from the data-base) or just an event Id (in which
     * case the row from the data-base will be fetched).
     *
     * @param[in] $summary A string with the new summary.
     *
     * @return The VCALENDAR object with the new summary installed.
     */
    protected static function setSummary($stuff, $summary)
    {
      if ($stuff instanceof \Sabre\VObject\Component\VCalendar) {
        $vcalendar = $stuff;
      } else {
        $vcalendar = self::getVCalendar($stuff);
      }
      $vobject = self::getVObject($stuff);

      $vobject->SUMMARY = $summary;

      return $vcalendar;
    }

    /**Return the description for the given event.
     *
     * @param[in] $stuff Either a VCALENDAR object, or the inner VEVENT,
     * VTODO etc. or an OC-event (array, row from the data-base) or just
     * an event Id (in which case the row from the data-base will be
     * fetched).
     *
     * @return A string with the event's brief title
     */
    public static function getDescription($stuff)
    {
      if ($stuff instanceof \Sabre\VObject\Component\VCalendar) {
        $vcalendar = $stuff;
      } else {
        $vcalendar = self::getVCalendar($stuff);
      }    
      $vobject = self::getVObject($vcalendar);

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
          self::register($prKey, $eventId, $calId, $handle);
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
     * @param[in] $eventId The event key (external key).
     * @param[in] $calendarId The id of the calender the vent belongs to.
     * @param[in] $handle mySQL handle or false.
     *
     * @return Undefined.
     */
    protected static function register($projectId, $eventId, $calendarId,
                                       $handle = false)
    {
      $values = array('ProjectId' => $projectId,
                      'EventId' => $eventId,
                      'CalendarID' => $calendarId);
      $result = mySQL::insert('ProjectEvents', $values, $handle);
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

    /**Find a matching calendar by its displayname.
     *
     * @param[in] $dpyName The display name.
     *
     * @param[in] $owner The owner of the calendar object.
     *
     * @param[in] $includeShared Include also shared calendars.
     *
     * @return The OC-calendar object (row of the database, i.e. an array).
     */
    public static function calendarByName($dpyName, $owner = false, $includeShared = false)
    {
      if ($owner === false) {
        $owner = \OC_User::getUser();
      }

      $result = false;
      $calendars = \OC_Calendar_Calendar::allCalendars($owner);

      foreach ($calendars as $calendar) {
        if (!$includeShared &&
            ($share = \OCP\Share::getItemSharedWithBySource('calendar', $calendar['id'])) != false &&
            ($share['uid_owner'] != $owner)) {
          // Exclude shared items.
          continue;
        }      
        if ($calendar['displayname'] == $dpyName) {
          $result = $calendar;
          break;
        }
      }
      return $result;
    }
  
    /**Make sure there is a suitable shared calendar with the given
     * name and/or id. Create one if necesary.
     *
     * @param[in] $dpyName The display name. Mandatory.
     *
     * @param[in] $calId The calendar id. If set, the corresponding
     * calender will be renamed to $dpyName. If unset, search for a
     * calendar with $dpyName as display name or create one.
     *
     * @return The (real) calendar id or false in case of error.
     */
    public static function checkSharedCalendar($dpyName, $calId = false)
    {
      $sharegroup = Config::getAppValue('usergroup');
      $shareowner = Config::getValue('shareowner');

      // Make sure the dummy user owning all shared stuff is "alive"
      if (!ConfigCheck::checkShareOwner($shareowner)) {
        return false;
      }

      if ($calId === false) {
        // try to find one ...
        $shareCal = self::calendarByName($dpyName, $shareowner);
      } else {
        // otherwise there should be one, in principle ...
        $shareCal = \OC_Calendar_Calendar::find($calId);
        // the user interface primarily exhibits the name, so maybe we
        // have an orphan id, recheck with the display name
        if (!$shareCal) {
          $shareCal = self::calendarByName($dpyName, $shareowner);
        }
      }
    
      if (!$shareCal) {
        // Well, then we create one ...
        $calId = \OC_Calendar_Calendar::addCalendar($shareowner, $dpyName);
        $shareCal = \OC_Calendar_Calendar::find($calId);
      } else {
        $calId = $shareCal['id'];
      }

      // Now there should be a calendar, if not, bail out.
      if (!$shareCal) {
        return false;
      }

      // Check that we can edit, simply set the item as shared    
      ConfigCheck::sudo($shareowner, function() use ($calId, $sharegroup) {
          $result = ConfigCheck::groupShareObject($calId, $sharegroup);
          return $result;
        });

      // Finally check, that the display-name matches. Otherwise rename
      // the calendar.
      if ($shareCal['displayname'] != $dpyName) {
        ConfigCheck::sudo($shareowner, function() use ($calId, $dpyName) {
            $result = \OC_Calendar_Calendar::editCalendar($calId, $dpyName);
            return $result;
          });
      }

      return $calId;
    }

    /**Return the IDs of the default calendars.
     */
    public static function defaultCalendars($public = false)
    {
      $cals = explode(',',Config::DFLT_CALS);
      $result = array();
      foreach ($cals as $cal) {
        if ($public && ($cal == 'management' || $cal == 'finance')) {
          continue;
        }
        $result[] = Config::getValue($cal.'calendar'.'id');
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

      if (!$calendarIds) {
        $calendarIds = self::defaultCalendars(true);
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
  FROM `ProjectEvents` WHERE `ProjectId` = $projectId
  ORDER BY `Id` ASC
__EOT__;

      $result = mySQL::query($query, $handle);
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
    
      if ($ownConnection) {
        mySQL::close($handle);
      }

      usort($events, "\CAFEVDB\Events::eventCompare");

      return $events;
    }

    /**Fetch the related rows from the pivot-table (without calendar
     * data).
     */
    protected static function projectEvents($projectId, $handle = false)
    {    
      $events = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }
      
      $query =<<<__EOT__
SELECT `CalendarId`,`EventId`
  FROM `ProjectEvents` WHERE `ProjectId` = $projectId
  ORDER BY `Id` ASC
__EOT__;

      $result = mySQL::query($query, $handle);
      while ($line = mySQL::fetch($result)) {
        $events[] = $line['EventId'];
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
      while ($line = mySQL::fetch($result)) {
        $projects[] = $line['ProjectId'];
      }
    
      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $projects;
    }

    /**Export the given events in ICAL format. The events need not
     * belong to the same calendar.
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
        $data = explode($eol, $text);
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
