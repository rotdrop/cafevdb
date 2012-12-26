<?php

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
    
    $event      = self::getEvent($eventId);
    $calId      = self::getCalendarId($event);
    $categories = self::getCategories($event);

    // Now fetch all projects and their names ...
    $projects = Projects::fetchProjects($handle);

    // Do the sync. The categories stored in the event are 
    // the criterion for this.
    foreach ($projects as $prKey => $prName) {
      if (in_array($prName, $categories)) {
        if (!self::isRegistered($prKey, $eventId, $handle)) {
          // register the event
          self::register($prKey, $eventId, $calId, $handle);
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
      $vcalendar = \OC_VObject::parse($event['calendardata']);
    } else {
      $vcalendar = \OC_Calendar_App::getVCalendar($event, false, false);
    }

    return $vcalendar;
  }

  /**Return a reference to the object contained in a Sabre VCALENDAR
   * object. This is a reference to allow for modification of the
   * $vCalendar object.
   *
   * @param[in] $vCalendar Sabre VCALENDAR object.
   *
   * @return A reference to the inner object.
   */
  protected static function &getVObject($vcalendar)
  {
    // Extract the categories as array
    if ($vcalendar->__isset('VEVENT')) {
      $vobject = &$vcalendar->VEVENT;
    } else if ($vcalendar->__isset('VTODO')) {
      $vobject = &$vcalendar->VTODO;
    } else if ($vcalendar->__isset('VJOURNAL')) {
      $vobject = &$vcalendar->VJOURNAL;
    } else if ($vcalendar->__isset('VCARD')) {
      $vobject = &$vcalendar->VCARD;
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
    if (is_object($stuff)) {
      // VCALENDAR or contained V-object
      if ($stuff->__isset('VCALENDAR')) {
        $vobject = self::getVObject($stuff);
      } else {
        $vobject = $stuff;
      }
    } else {
      // Event or ID
      $vcalendar = self::getVCalendar($stuff);
      $vobject = self::getVObject($vcalendar);
    }

    $categories = $vobject->getAsArray('CATEGORIES');

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
    if (is_object($stuff)) {
      // VCALENDAR or contained V-object
      if ($stuff instanceof \OC_VObject) {
        $vcalendar = $stuff;
      } else {
        return false;
      }
    } else {
      // Event or ID
      $vcalendar = self::getVCalendar($stuff);
    }

    $vobject = self::getVObject($vcalendar);

    $categories = implode(',', $categories);

    $vobject->setString('CATEGORIES', $categories);

    return $vcalendar;
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
   * @bugs Error checking is not implemented.
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

    $vcalendar  = self::getVCalendar($eventId);
    $categories = self::getCategories($vcalendar);

    $key = array_search($projectName, $categories);
    unset($categories[$key]);

    $vcalendar = self::setCategories($vcalendar, $categories);
    
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

  /**Find a matching calendar by its displayname.
   */
  public static function calendarByName($dpyName, $owner = false)
  {
    if ($owner === false) {
      $owner = \OC_User::getUser();
    }

    $result = false;
    $calendars = \OC_Calendar_Calendar::allCalendars($owner);
    foreach ($calendars as $calendar) {
      if ($calendar['displayname'] == $dpyName) {
        $result = $calendar;
        break;
      }
    }
    return $result;
  }
  
  /**Share an object between the members of the specified group.
   */
  public static function groupShareObject($id, $group, $type = 'calendar')
  {
    return \OCP\Share::shareItem($type, $id,
                                 \OCP\Share::SHARE_TYPE_GROUP,
                                 $group,
                                 \OCP\Share::PERMISSION_CREATE|
                                 \OCP\Share::PERMISSION_READ|
                                 \OCP\Share::PERMISSION_UPDATE|
                                 \OCP\Share::PERMISSION_DELETE);
  }

  /**Fake execution with other user-id.
   */
  public static function sudo($uid, $callback)
  {
    $olduser = \OC_User::getUser();
    \OC_User::setUserId($uid);
    try {
      $result = call_user_funct($callback);
    } catch (Exception $exception) {
      \OC_User::setUserId($olduser);
      throw new Exception($exception->getMessage);
      return false;
    }
    \OC_User::setUserId($olduser);

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
   */
  public static function checkSharedCalendar($dpyName, $calId = false)
  {
    $sharegroup = Config::getAppValue('usergroup');
    $shareowner = Config::getValue('shareowner');

    // Make sure the dummy user owning all shared stuff is "alive"
    if (!self::checkShareOwner($shareowner)) {
      return false;
    }

    if ($calId === false) {
      // try to find one ...
      $shareCal = self::findCalendarByName($calName, $shareowner);
      
    } else {
      // otherwise there should be one, in principle ...
      $shareCal = \OC_Calendar_Calendar::find($shareid);
    }
    
    if (!$shareCal) {
      // Well, then we create one ...
      $calId = \OC_Calendar_Calendar::addCalendar($shareowner, $calName);
      $shareCal = \OC_Calendar_Calendar::find($calid);
    } else {
      $calId = $shareCal['id'];
    }

    // Now there should be a calendar, if not, bail out.
    if (!$shareCal) {
      return false;
    }

    // Check that we can edit, simply set the item as shared    
    sudo($shareowner, function() use ($calId, $sharegroup) {
        $result = groupShareObject($calId, $sharegroup);
        return $result;
      });

    // Finally check, that the display-name matches. Otherwise rename
    // the calendar.
    if ($shareCal['displayname'] != $calName) {
      sudo($shareowner, function() use ($calId, $calName) {
          $result = \OC_Calendar_Calendar::editCalendar($calId, $calName);
          return $result;
        });
    }

    return $calId;
  }

  /**Make sure the "sharing" user exists, create it when necessary.
   * May throw an exception.
   *
   * @param[in] $shareowner The account holding the shared resources.
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

}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
