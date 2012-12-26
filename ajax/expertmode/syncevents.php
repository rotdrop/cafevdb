<?php

// Init owncloud

use CAFEVDB\L;
use CAFEVDB\Events;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

// Loop over all calendars the calendar user shares with the orchestra
// group.
$shareowner    = CAFEVDB\Config::getSetting('shareowner',
                                            CAFEVDB\Config::getValue('dbuser').'shareowner');
$calendargroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$calendars     = \OC_Calendar_Calendar::allCalendars($shareowner);

$dfltnames = array('concerts', 'rehearsals', 'other', 'management');
$dfltcals = array();
foreach ($dfltnames as $name) {
  $dfltcals[] = CAFEVDB\Config::getSetting($name.'calendar', L::t($name));
}

CAFEVDB\Config::init();
$handle = CAFEVDB\mySQL::connect(CAFEVDB\Config::$pmeopts);

foreach ($calendars as $calendar) {
  $calId = $calendar['id'];
  if (!OCP\Share::getItemSharedWithBySource('calendar', $calId)) {
    $calDpyName = $calendar['displayname'];
    if (in_array($calDpyName, $dfltcals)) {
      // Force permission on default calendars
      $newId = $calId;
      try {
        $newId = Events::checkSharedCalendar($calDpyName, $calId);
      } catch (\Exception $exception) {
        OC_JSON::error(
          array(
            "data" => array(
              "message" => L::t("Exception:").$exception->getMessage())));
        return false;
      }
    } else {
      continue;
    }
  }

  // Ok, now we should have access. Do the sync.
  $events = \OC_Calendar_Object::all($calId);
  foreach ($events as $event) {
    Events::syncEvent($event['id'], $handle);
  }
}

CAFEVDB\mySQL::close($handle);

OC_JSON::success(array("data" => array("message" => L::t("Done."))));
return;

?>
