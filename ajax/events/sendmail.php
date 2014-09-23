<?php

if(!OCP\User::isLoggedIn()) {
  die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;

try {

  Error::exceptions(true);

  $debugmode = Config::getUserValue('debugmode','') == 'on';
  $debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';

  $locale = Util::getLocale();

  $projectId   = $_POST['ProjectId'];
  $projectName = $_POST['ProjectName'];

  if ($projectId < 0 ||
      ($projectName == '' &&
       ($projectName = CAFEVDB\Projects::fetchName($projectId)) == '')) {
    OCP\JSON::error(
      array(
        'data' => array('error' => 'arguments',
                        'message' => L::t('Project-id and/or name not set'),
                        'debug' => $debugtext)));
    return false;
  }

  $events = Events::events($projectId);

  $dfltIds     = Events::defaultCalendars();
  $eventMatrix = Events::eventMatrix($events, $dfltIds);

  $emailEvents = CAFEVDB\Util::cgiValue('EventSelect', array());
  $selected = array(); // array marking selected events
  foreach ($emailEvents as $event) {
    $selected[$event] = true;
  }

// Now generate the html-fragment

  $tmpl = new OCP\Template('cafevdb', 'eventslisting');

  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);
  $tmpl->assign('Events', $events);
  $tmpl->assign('EventMatrix', $eventMatrix);
  $tmpl->assign('locale', $locale);
  $tmpl->assign('timezone', Util::getTimezone());
  $tmpl->assign('CSSClass', 'projectevents');
  $tmpl->assign('Selected', $selected);

  $html = $tmpl->fetchPage();

  OCP\JSON::success(array('data' => array('contents' => $html,
                                          'debug' => $debugtext)));

  return true;

} catch (\Exception $e) {
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

?>

