<?php
/**
 * Copyright (c) 2011 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 *
 *
 * Modified 2012 to allow for custom new-event pop-ups for the Camerata DB app by
 * Claus-Justus Heine <himself at claus-justus-heine.de>
 *
 */

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('calendar');
OCP\JSON::checkAppEnabled('cafevdb');

$debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

use CAFEVDB\L;

$projectId   = CAFEVDB\Util::cgiValue('ProjectId', -1);
$projectName = CAFEVDB\Util::cgiValue('ProjectName', -1);
$eventKind   = CAFEVDB\Util::cgiValue('EventKind', -1);

// standard calendar values
$start  = CAFEVDB\Util::cgiValue('start', false);
$end    = CAFEVDB\Util::cgiValue('end', false);
$allday = CAFEVDB\Util::cgiValue('allday', false);

// choose defaults which make sense
$categories   = $projectName.','.L::t($eventKind);
$calendarname = CAFEVDB\Config::getSetting($eventKind.'calendar', L::t($eventKind));
$title        = L::t($eventKind).', '.$projectName;

// make sure that the calendar exists and is writable
$sharinguser  = CAFEVDB\Config::getSetting('sharinguser', CAFEVDB\Config::getValue('dbuser'));
$calendargroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$calendars     = OC_Calendar_Calendar::allCalendars($sharinguser);
$defaultcal    = false;
foreach ($calendars as $calendar) {
    if ($calendar['displayname'] == $calendarname) {
        $defaultcal = $calendar;
        $defaultid  = $defaultcal['id'];
        break;
    }
}

// Create the calendar if necessary
if (!$defaultcal) {
    $calid = OC_Calendar_Calendar::addCalendar($sharinguser, $calendarname);
    $defaultcal = OC_Calendar_Calendar::find($calid);
    $defaultid  = $calid;
}

// Check that we can edit, simply set the item as shared
//
// Total cheating ...
if ($defaultcal &&
    !OCP\Share::getItemSharedWithBySource('calendar', $defaultid)) {
    $olduser = $_SESSION['user_id'];
    $_SESSION['user_id'] = $sharinguser;
    try {
        $token = OCP\Share::shareItem('calendar', $defaultid,
                                      OCP\Share::SHARE_TYPE_GROUP,
                                      $calendargroup,
                                      OCP\Share::PERMISSION_CREATE|
                                      OCP\Share::PERMISSION_READ|
                                      OCP\Share::PERMISSION_UPDATE|
                                      OCP\Share::PERMISSION_DELETE);
    } catch (Exception $exception) {
        $_SESSION['user_id'] = $olduser;
        OC_JSON::error(array('data' => array('message' => $exception->getMessage())));
        return;
    }
    $_SESSION['user_id'] = $olduser;
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
$timezone = OC_Calendar_App::getTimezone();
$start->setTimezone(new DateTimeZone($timezone));
$end->setTimezone(new DateTimeZone($timezone));

// make sure the default calendar is the first in the list
$calendar_options = array($defaultcal);

$calendars = OC_Calendar_Calendar::allCalendars(OCP\USER::getUser());
foreach($calendars as $calendar) {
    if ($calendar['id'] == $defaultid) {
        continue; // skip, already in list.
    }
	if ($calendar['userid'] != OCP\User::getUser()) {
		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $calendar['id']);
		if ($sharedCalendar && ($sharedCalendar['permissions'] & OCP\Share::PERMISSION_UPDATE)) {
			array_push($calendar_options, $calendar);
		}
	} else {
		array_push($calendar_options, $calendar);
	}
}

$repeat_options = OC_Calendar_App::getRepeatOptions();
$repeat_end_options = OC_Calendar_App::getEndOptions();
$repeat_month_options = OC_Calendar_App::getMonthOptions();
$repeat_year_options = OC_Calendar_App::getYearOptions();
$repeat_weekly_options = OC_Calendar_App::getWeeklyOptions();
$repeat_weekofmonth_options = OC_Calendar_App::getWeekofMonth();
$repeat_byyearday_options = OC_Calendar_App::getByYearDayOptions();
$repeat_bymonth_options = OC_Calendar_App::getByMonthOptions();
$repeat_byweekno_options = OC_Calendar_App::getByWeekNoOptions();
$repeat_bymonthday_options = OC_Calendar_App::getByMonthDayOptions();

$tmpl = new OCP\Template('calendar', 'part.newevent');
$tmpl->assign('access', 'owner');
$tmpl->assign('calendar_options', $calendar_options);
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
  OCP\JSON::error(array('data' => array('contents' => '',
                                        'debug' => $debugtext)));

  return true;
}

$tmpl->printpage();

?>