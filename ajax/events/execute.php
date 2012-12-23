<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

$debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

use CAFEVDB\L;

$lang = \OC_L10N::findLanguage('cafevdb');
$locale = $lang.'_'.strtoupper($lang).'.UTF-8';

$projectId   = CAFEVDB\Util::cgiValue('ProjectId', -1);
$projectName = CAFEVDB\Util::cgiValue('ProjectName', '');

if ($projectId < 0 ||
    ($projectName == '' &&
     ($projectName = CAFEVDB\Projects::fetchName($projectId)) == '')) {
  die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

$eventId = CAFEVDB\Util::cgiValue('EventId', -1);
$action  = CAFEVDB\Util::cgiValue('Action', '');

$emailEvents = CAFEVDB\Util::cgiValue('email-check', array());
$selected = array(); // array marking selected events

switch ($action) {
case 'delete':
  // Delete it. The kill-hook will clean up after us.
  \OC_Calendar_Object::delete($eventId);
  foreach ($emailEvents as $event) {
    if ($event != $eventId) {
      $selected[$event] = true;
    }
  }
  // Re-fetch the events
  $events = CAFEVDB\Projects::events($projectId);
  break;
case 'detach':
  // Keep the event in the calendar, but detach it
  CAFEVDB\Events::unchain($projectId, $eventId);
  foreach ($emailEvents as $event) {
    if ($event != $eventId) {
      $selected[$event] = true;
    }
  }
  // Re-fetch the events
  $events = CAFEVDB\Projects::events($projectId);
  break;
case 'redisplay':
  // Re-fetch the events
  $events = CAFEVDB\Projects::events($projectId);
  // Just remember the events selected for email transmission
  foreach ($emailEvents as $event) {
    $selected[$event] = true;
  }
  break;
case 'select':
  // Re-fetch the events
  $events = CAFEVDB\Projects::events($projectId);
  // Mark all events as selected.
  foreach ($events as $event) {
    $selected[$event['calEventId']] = true;
  }
  break;
case 'deselect':
  // Re-fetch the events
  $events = CAFEVDB\Projects::events($projectId);
  // Just do nothing, leave all events unmarked.
  break;
default:
  OCP\JSON::error(array('data' => array('contents' => '',
                                        'debug' => L::t('Invalid operation: ').$action)));
  return false;
}

// Now generate the html-fragment

$tmpl = new OCP\Template('cafevdb', 'eventslisting');

$tmpl->assign('ProjectName', $projectName);
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);
$tmpl->assign('Locale', $locale);
$tmpl->assign('CSSClass', 'projectevents');
$tmpl->assign('Selected', $selected);

$html = $tmpl->fetchPage();

OCP\JSON::success(array('data' => array('contents' => $html,
                                        'debug' => $debugtext)));

return true;

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>

