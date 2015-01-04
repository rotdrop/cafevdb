<?php
/**Based on the download-script from the calendar app, which is
 *
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
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

$emailEvents = Util::cgiValue('EventSelect', array());

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

echo Events::exportEvents($events, $projectName);

?>
