<?php

// Init owncloud

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\mySQL;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

// Loop over all calendars the calendar user shares with the orchestra
// group.
$shareowner = Config::getSetting(
  'shareowner', Config::getValue('dbuser').'shareowner');
$sharegroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');

// Possibly fix sharing issues with the default calendars.
foreach (explode(',',Config::DFLT_CALS) as $key) {
  $calKey = $key.'calendar';
  $id     = Config::getValue($calKey.'id');
  $name   = Config::getSetting($calKey, L::t($key));
  $newId = Events::checkSharedCalendar($name, $id);
  if ($newId !== false && $newId != $id) {
    Config::setValue($calKey.'id', $newId);
  }
}


// Determine all calendars shared with the group
$sharedCals = Events::sudo($shareowner, function() use ($shareowner) {
    $result1 = \OCP\Share::getItemsShared('calendar');
    $result2 = \OCP\Share::getItemsSharedWith('calendar');
    $result = array_merge($result1, $result2);
    return $result;
  });

Config::init();
$handle = mySQL::connect(Config::$pmeopts);

$txt = '';
foreach ($sharedCals as $share) {
  if ($share['share_with'] != $sharegroup) {
    continue;
  }
  $calId = $share['item_source'];
  
  $txt .= $calId.' ';
  // Do the sync
  $events = \OC_Calendar_Object::all($calId);
  foreach ($events as $event) {
    Events::syncEvent($event['id'], $handle);
  }
}

mySQL::close($handle);

OC_JSON::success(array("data" => array("message" => L::t("Done.").'<PRE>'.$txt.'</PRE>')));
return;

return;

?>
