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
