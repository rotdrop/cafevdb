<?php

if(!OCP\User::isLoggedIn()) {
  die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;

Config::init();

$debugmode = Config::getUserValue('debugmode', '') == 'on';
$debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';
$msg = '';

$projectId   = Util::cgiValue('ProjectId', -1);
$projectName = Util::cgiValue('ProjectName', '');

if ($projectId < 0 ||
    ($projectName == '' &&
     ($projectName = CAFEVDB\Projects::fetchName($projectId)) == '')) {
  die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

if (false) {
  OCP\JSON::error(
    array(
      'data' => array('contents' => '',
                      'projectId' => $projectId,
                      'projectName' => $projectName,
                      'debug' => $debugtext.$msg)));
  return true;
}

$events      = Events::events($projectId);
$dfltIds     = Events::defaultCalendars();
$eventMatrix = Events::eventMatrix($events, $dfltIds);

$emailEvents = Util::cgiValue('EventSelect', array());
$selected = array(); // array marking selected events

foreach ($emailEvents as $event) {
  $selected[$event] = true;
}

$locale = Util::getLocale();

$tmpl = new OCP\Template('cafevdb', 'events');

$tmpl->assign('ProjectName', $projectName);
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);
$tmpl->assign('EventMatrix', $eventMatrix);
$tmpl->assign('Locale', $locale);
$tmpl->assign('CSSClass', 'projectevents');
$tmpl->assign('Selected', $selected);

$html = $tmpl->fetchPage();

OCP\JSON::success(
  array('data' => array('contents' => $html,
                        'projectId' => $projectId,
                        'projectName' => $projectName,
                        'debug' => $debugtext)));

return true;

?>
