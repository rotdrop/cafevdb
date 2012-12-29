<?php
/**
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled(CAFEVDB\Config::APP_NAME);

$projectId   = $_POST['ProjectId'];
$projectName = $_POST['ProjectName'];

header("Content-Type: text/calendar");
header("Content-Disposition: inline; filename=".$projectName.".ics");

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\Util;

$debugmode = Config::getUserValue('debugmode','') == 'on';
$debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';

$emailEvents = Util::cgiValue('email-check', array());

$events = array();
if (count($emailEvents) > 0) {
  foreach ($emailEvents as $event) {
    $events[] = $event;
  }
} else {
  $allEvents = Events::events($projectId);
  foreach ($allEvents as $event) {
    $events[] = $event['EventId'];
  }
}

$eol = "\r\n";

echo ""
."BEGIN:VCALENDAR".$eol
."VERSION:2.0".$eol
."PRODID:ownCloud Calendar " . OCP\App::getAppVersion('calendar') .$eol
."X-WR-CALNAME:" . $projectName . ' (' . Config::ORCHESTRA . ')' . $eol;

foreach ($events as $id) {
  $text = OC_Calendar_Export::export($id, OC_Calendar_Export::EVENT);
  // Well, not elegant, but for me the easiest way to strip the
  // BEGIN/END VCALENDAR tags
  $data = explode($eol, $text);
  $silent = true;
  foreach ($data as $line) {
    if (strncmp($line, "BEGIN:VEVENT", strlen("BEGIN:VEVENT")) == 0) {
      $silent = false;
    }
    if (!$silent) {
      echo $line.$eol;
    }
    if (strncmp($line, "END:VEVENT", strlen("END:VEVENT")) == 0) {
      $silent = true;
    }
  }
}

echo "END:VCALENDAR".$eol;

?>
