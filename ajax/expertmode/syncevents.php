<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

// Loop over all calendars the calendar user shares with the orchestra
// group.
$calendaruser  = CAFEVDB\Config::getSetting('calendaruser', CAFEVDB\Config::getValue('dbuser'));
$calendargroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$calendars     = OC_Calendar_Calendar::allCalendars($calendaruser);

$dfltnames = array('concerts', 'rehearsals', 'other');
$dfltcals = array();
foreach ($dfltnames as $name) {
  $dfltcals[] = CAFEVDB\Config::getSetting($name.'calendar', L::t($name));
}

CAFEVDB\Config::init();
$handle = CAFEVDB\mySQL::connect(CAFEVDB\Config::$pmeopts);

foreach ($calendars as $calendar) {
  $calId = $calendar['id'];
  if (!OCP\Share::getItemSharedWithBySource('calendar', $calId)) {
    if (in_array($calendar['displayname'], $dfltcals)) {
      // force sharing
      $olduser = $_SESSION['user_id'];
      $_SESSION['user_id'] = $calendaruser;
      try {
        $token = OCP\Share::shareItem('calendar', $calId,
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
    } else {
      continue;
    }
  }

  // Ok, now we should have access. Do the sync.
  $events = \OC_Calendar_Object::all($calId);
  foreach ($events as $event) {
    CAFEVDB\Events::syncEvent($event['id'], $handle);
  }
}

CAFEVDB\mySQL::close($handle);

echo '<span class="bold">'.L::t('Done.').'</span>';

?>
