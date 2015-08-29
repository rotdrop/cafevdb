<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally taken from calendar app,
 * copyright 2011 Georg Ehrke <ownclouddev@georgswebsite.de>
 *
 * Modified 2012 to allow for custom new-event pop-ups for the
 * Camerata DB app by Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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

namespace CAFEVDB {

  /**@addtogroup AJAX
   * AJAX related scripts.
   * @{
   */

  if (!\OCP\User::isLoggedIn()) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
  }

  \OCP\JSON::checkAppEnabled('calendar');
  \OCP\JSON::checkAppEnabled('cafevdb');

  try {

    Error::exceptions(true);

    $debugmode = Config::getUserValue('debugmode','') == 'on';
    $debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';

    $projectId   = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', -1);
    $eventKind   = Util::cgiValue('EventKind', -1);
    $debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

    //!@cond
    if ($projectId < 0 ||
        ($projectName == '' &&
         ($projectName = CAFEVDB\Projects::fetchName($projectId)) == '')) {
      \OCP\JSON::error(
        array(
          'data' => array('error' => 'arguments',
                          'message' => L::t('Project-id and/or name not set'),
                          'debug' => $debugtext)));
      return false;
    }
    //!@endcond

    // standard calendar values
    $start  = Util::cgiValue('start', false);
    $end    = Util::cgiValue('end', false);
    $allday = Util::cgiValue('allday', false);

    // Choose the right calendar
    $categories   = $projectName.','.L::t($eventKind);
    $calKey       = $eventKind.'calendar';
    $calendarName = Config::getSetting($calKey, L::t($eventKind));
    $calendarId   = Config::getSetting($calKey.'id', false);
    $shareOwner   = Config::getSetting(
      'shareowner', Config::getValue('dbuser').'shareowner');

    // Default title for the event
    $title        = L::t($eventKind).', '.$projectName;

    // make sure that the calendar exists and is writable
    $newId = Events::checkSharedCalendar($calendarName, $calendarId);

    if ($newId === false) {
      \OCP\JSON::error(
        array(
          'data' => array(
            'error' => 'nodata',
            'message' => L::t('Cannot access calendar: `%s\'', array($calKey)),
            'debug' => $debugtext)));
      return false;
    }

    if ($newId !== $calendarId) {
      Config::setValue($calKey.'id', $calendarId);
      $calendarId = $newId;
    }

    if (!$start) {
      $start = new DateTime('now');
      $start = $start->getTimeStamp();
    }

    if (!$end) {
      $duration = CAFEVDB\Config::getSetting('eventduration', 180);
      $end = $start + ($duration * 60);
    }

    $start = new DateTime('@'.$start);
    $end = new DateTime('@'.$end);
    $timezone = Util::getTimezone();
    $start->setTimezone(new DateTimeZone($timezone));
    $end->setTimezone(new DateTimeZone($timezone));

    $defaultCal    = \OC_Calendar_App::getCalendar($calendarId, true, true);

    // make sure the default calendar is the first in the list
    $calendar_options = array($defaultCal);

    //!@cond
    $calendars = \OC_Calendar_Calendar::allCalendars(\OCP\USER::getUser());
    foreach($calendars as $calendar) {
      if ($calendar['id'] == $calendarId) {
        continue; // skip, already in list.
      }
      if($calendar['userid'] != \OCP\User::getUser()) {
        $sharedCalendar = \OCP\Share::getItemSharedWithBySource('calendar', $calendar['id']);
        if ($sharedCalendar && ($sharedCalendar['permissions'] & \OCP\PERMISSION_UPDATE)) {
          array_push($calendar_options, $calendar);
        }
      } else {
        array_push($calendar_options, $calendar);
      }
    }
    //!@endcond

    $access_class_options = \OC_Calendar_App::getAccessClassOptions();
    $repeat_options = \OC_Calendar_App::getRepeatOptions();
    $repeat_end_options = \OC_Calendar_App::getEndOptions();
    $repeat_month_options = \OC_Calendar_App::getMonthOptions();
    $repeat_year_options = \OC_Calendar_App::getYearOptions();
    $repeat_weekly_options = \OC_Calendar_App::getWeeklyOptions();
    $repeat_weekofmonth_options = \OC_Calendar_App::getWeekofMonth();
    $repeat_byyearday_options = \OC_Calendar_App::getByYearDayOptions();
    $repeat_bymonth_options = \OC_Calendar_App::getByMonthOptions();
    $repeat_byweekno_options = \OC_Calendar_App::getByWeekNoOptions();
    $repeat_bymonthday_options = \OC_Calendar_App::getByMonthDayOptions();

    $tmpl = new \OCP\Template('calendar', 'part.newevent');
    $tmpl->assign('access', 'owner');
    $tmpl->assign('accessclass', 'PUBLIC');
    $tmpl->assign('calendar_options', $calendar_options);
    $tmpl->assign('access_class_options', $access_class_options);
    $tmpl->assign('repeat_options', $repeat_options);
    $tmpl->assign('repeat_month_options', $repeat_month_options);
    $tmpl->assign('repeat_weekly_options', $repeat_weekly_options);
    $tmpl->assign('repeat_end_options', $repeat_end_options);
    $tmpl->assign('repeat_year_options', $repeat_year_options);
    $tmpl->assign('repeat_byyearday_options', $repeat_byyearday_options);
    $tmpl->assign('repeat_bymonth_options', $repeat_bymonth_options);
    $tmpl->assign('repeat_byweekno_options', $repeat_byweekno_options);
    $tmpl->assign('repeat_bymonthday_options', $repeat_bymonthday_options);
    $tmpl->assign('repeat_weekofmonth_options', $repeat_weekofmonth_options);

    $tmpl->assign('eventid', 'new');
    $tmpl->assign('startdate', $start->format('d-m-Y'));
    $tmpl->assign('starttime', $start->format('H:i'));
    $tmpl->assign('enddate', $end->format('d-m-Y'));
    $tmpl->assign('endtime', $end->format('H:i'));
    $tmpl->assign('allday', $allday);
    $tmpl->assign('repeat', 'doesnotrepeat');
    $tmpl->assign('repeat_month', 'monthday');
    $tmpl->assign('repeat_weekdays', array());
    $tmpl->assign('repeat_interval', 1);
    $tmpl->assign('repeat_end', 'never');
    $tmpl->assign('repeat_count', '10');
    $tmpl->assign('repeat_weekofmonth', 'auto');
    $tmpl->assign('repeat_date', '');
    $tmpl->assign('repeat_year', 'bydate');

    // cafevdb defaults
    $tmpl->assign('categories', $categories);
    $tmpl->assign('title', $title);

    if (false) {
      \OCP\JSON::error(array('data' => array('contents' => '',
                                            'debug' => $debugtext)));

      return true;
    }

    $tmpl->printpage();

  } catch (\Exception $e) {
    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'message' => L::t('Error, caught an exception'))));
    return false;
  }

  /**@} AJAX group */

} // namespace

?>
