<?php

namespace CAFEVDB;

/** Helper class for event management
 */
class Events
{
  /**Link the event (or whatever) into the ProjectEvents table. This
   * function is invoked on every event-creation. The link between
   * projects and events is triggered by the Projekte::Name field
   * through the categories tag of the event. Any event with a
   * category equal to the project's name is considered as belonging
   * to the project, it it addtionally belongs to one of the three
   * shared calendars (conerts, rehearsals, other).
   *
   * @param[in] $eventId The OwnCloud-Id of the event.
   *
   * @return Undefined.
   */
  public static function newEventListener($eventId)
  {
    self::maybeAddEvent($eventId);
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
    self::syncEvent($eventId);
  }

  public static function killEventListener($eventId)
  {
    self::maybeKillEvent($eventId);
  }

  public static function moveEventListener($eventId)
  {
    // Maybe check whether all members of the orchester group can
    // still access the event. But do nothing for now.
  }

  public static function killCalendarListener($calendarId)
  {
    Config::init();
    $handle = mySQL::connect(Config::$pmeopts);

    // Execute the show-stopper

    $query = "DELETE FROM `ProjectEvents` WHERE `CalendarId` = $calendarId";

    mySQL::query($query, $handle);

    mySQL::close($handle);
  }

  public static function editCalendarListener($calendarId)
  {
    // We simply should update our idea of the name of the calender if
    // it is one of our four calendars
  }

  public static function strftime($format, $timestamp = NULL, $locale = NULL)
  {
    $oldlocale = setlocale(LC_TIME, 0);
    if ($locale) {
      setlocale(LC_TIME, $locale);
    }
    $result = strftime($format, $timestamp);

    setlocale(LC_TIME, $oldlocale);

    return $result;
  }
    
  /**Parse the respective event and make sure the ProjectEvents
   * table is uptodate.
   *
   * @param[in] $eventId The OwnCloud-Id of the event.
   *
   * @return Undefined.
   */
  public static function syncEvent($eventId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }
    
    // fetch the respective event
    $event = \OC_Calendar_Object::find($eventId);
    if($event === false) {
      return false;
    }

    $vobject = \OC_VObject::parse($event['calendardata']);
    if (!$vobject) {
      return;
    }
        
    // Extract the categories
    if ($vobject->__isset('VEVENT')) {
      $vobject = $vobject->VEVENT;
    } else if ($vobject->__isset('VTODO')) {
      $vobject = $vobject->VTODO;
    } else if ($vobject->__isset('VJOURNAL')) {
      $vobject = $vobject->VJOURNAL;
    } else if ($vobject->__isset('VCARD')) {
      $vobject = $vobject->VCARD;
    }

    $categories = $vobject->getAsArray('CATEGORIES');

    // Now fetch all projects and their names ...
    $projects = Projects::fetchProjects($handle);

    // Do the sync. The categories stored in the event are 
    // the criterion for this.
    foreach ($projects as $prKey => $prName) {
      if (in_array($prName, $categories)) {
        if (!self::isRegistered($prKey, $eventId, $handle)) {
          // register the event
          self::register($prKey, $eventId, $event['calendarid'], $handle);
        }
      } else {
        if (self::isRegistered($prKey, $eventId, $handle)) {
          // unregister the event
          self::unregister($prKey, $eventId, $handle);
        }        
      }
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }    
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

  protected static function maybeAddEvent($eventId, $handle = false)
  {
    $event = \OC_Calendar_Object::find($eventId);
    if($event === false) {
      return false;
    }

    $vobject = \OC_VObject::parse($event['calendardata']);
    if (!$vobject) {
      return;
    }
        
    // Extract the categories as array
    if ($vobject->__isset('VEVENT')) {
      $vobject = $vobject->VEVENT;
    } else if ($vobject->__isset('VTODO')) {
      $vobject = $vobject->VTODO;
    } else if ($vobject->__isset('VJOURNAL')) {
      $vobject = $vobject->VJOURNAL;
    } else if ($vobject->__isset('VCARD')) {
      $vobject = $vobject->VCARD;
    }

    $categories = $vobject->getAsArray('CATEGORIES');

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }
        
    // Now fetch all projects and their names ...
    $projects = Projects::fetchProjects($handle);
        
    // Search for matching project-tags. We assume that this is
    // not really timing critical, i.e. that there are only few
    // projects.
    foreach ($projects as $prKey => $prName) {
      if (in_array($prName, $categories)) {
        // Ok. Link it in.
        self::register($prKey,$eventId,$event['calendarid'],$handle);
      }
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }
  }

  /**Unconditionally register the given event with the given project.
   *
   * @param[in] $projectId The project key.
   * @param[in] $eventId The event key (external key).
   * @param[in] $handle mySQL handle or false.
   *
   * @return Undefined.
   */
  protected static function register($projectId, $eventId, $calendarId,
                                     $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }
        
    // Link it in.
    $query = ''
    ."INSERT INTO `ProjectEvents`"
    ."  (`ProjectId`,`EventId`,`CalendarID`)"
    ."  VALUES ('$projectId','$eventId','$calendarId')";
    mySQL::query($query, $handle);


    if ($ownConnection) {
      mySQL::close($handle);
    }
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

    // fetch the respective event
    $vcalendar = \OC_Calendar_App::getVCalendar($eventId, false, false);
    if (!$vcalendar) {
      return;
    }
        
    // Convert to an array
    if ($vcalendar->__isset('VEVENT')) {
      $vobject = &$vcalendar->VEVENT;
    } else if ($calendar->__isset('VTODO')) {
      $vobject = &$vcalendar->VTODO;
    } else if ($vcalendar->__isset('VJOURNAL')) {
      $vobject = &$vcalendar->VJOURNAL;
    } else if ($vcalendar->__isset('VCARD')) {
      $vobject = &$vcalendar->VCARD;
    }

    $categories = $vobject->getAsArray('CATEGORIES');

    $key = array_search($projectName, $categories);
    unset($categories[$key]);
    $categories = implode(',', $categories);

    $vobject->setString('CATEGORIES', $categories);
    
    \OC_Calendar_Object::edit($eventId, $vcalendar->serialize());    
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

  
  /**Make sure the "sharing" user exists, create it when necessary.
   * May throw an exception.
   */
  public static function checkShareOwner($shareowner)
  {
    if (!$sharegroup = Config::getAppValue('usergroup', false)) {
      return false; // need at least this group!
    }

    if (!\OC_User::userExists($shareowner) &&
        !\OC_User::createUser($shareowner,
                              \OC_User::generatePassword())) {
      return false;
    }
    if (!\OC_Group::inGroup($shareowner, $sharegroup) &&
        !\OC_Group::addToGroup($shareowner, $sharegroup)) {
      return false;
    }
    return true;
  }
  

  /**Return the IDs of the default calendars.
   */
  public static function defaultCalendars()
  {
    

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
      $cal = \OC_Calendar_Calendar::find($id);
      $name = isset($cal['displayname'])
        ? $cal['displayname'] : L::K('Unknown Calendar').' '.$id;
      $calendarNames[$id] = $name;
      $result[$name] = array();
    }
    $calendarNames['unknown'] = L::K('Unknown Calendars');
    $result[$calendarNames['unknown']] = array();

    foreach ($projectEvents as $event) {
      $object = $event['object'];
      $calId = array_search($objects['calendarid'], $calendarIds);
      if ($calId === false) {
        $result[$calendarNames['unknown']][] = $event;
      } else {
        $result[$calendarNames[$calId]][] = $event;
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

    $ownConnection = $handle === false;
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

      $event['object']['startdate'] = new \DateTime($event['object']['startdate'], $utc);
      $event['object']['enddate'] = new \DateTime($event['object']['enddate'], $utc);
      $events[] = $event;
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

}; // class Events

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
