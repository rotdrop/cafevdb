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
  protected static function syncEvent($eventId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }
    
    // fetch the respective event
    $vobject = \OC_Calendar_App::getVCalendar($eventId, false, false);
    if (!$vobject) {
      return;
    }
        
    // Convert to an array
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
          self::register($prKey, $eventId, $handle);
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
    // fetch the respective event
    $vobject = \OC_Calendar_App::getVCalendar($eventId, false, false);
    if (!$vobject) {
      return;
    }
        
    // Convert to an array
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
        self::register($prKey,$eventId,$handle);
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
  protected static function register($projectId, $eventId,
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
    ."  (`ProjectId`,`EventId`)"
    ."  VALUES ('$projectId','$eventId')";
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

  protected static function projectEvents($projectId, $handle = false)
  {    
    $events = array();

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }
      
    $query =<<<__EOT__
SELECT `EventId`
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
