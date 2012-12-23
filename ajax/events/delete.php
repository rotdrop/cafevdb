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

$projectId   = $_POST['ProjectId'];
$projectName = $_POST['ProjectName'];
$eventId     = $_POST['EventId'];
$action      = $_POST['Action'];


switch ($action) {
 case 'delete':
   // Delete it. The kill-hook will clean up after us.
   \OC_Calendar_Object::delete($eventId);
   break;
 case 'detach':
   // Keep the event in the calendar, but detach it
   CAFEVDB\Events::unchain($projectId, $eventId);
   break;
 default:
   OCP\JSON::error(array('data' => array('message' => L::t('Invalid operation: ').$action)));
   return false;
}

$events = CAFEVDB\Projects::events($projectId);

// Remember the events selected for email transmission
$emailEvents = CAFEVDB\Util::cgiValue('email-check', array());

$selected = array();
foreach ($emailEvents as $event) {
  if ($event != $eventId) {
    $selected[$event] = true;
  }
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

OCP\JSON::success(array('data' => array('message' => $html,
                                        'debug' => $debugtext)));

return true;

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>

