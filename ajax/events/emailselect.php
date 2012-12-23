<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

$debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

use CAFEVDB\L;

$lang = OC_L10N::findLanguage('cafevdb');
$locale = $lang.'_'.strtoupper($lang).'.UTF-8';

$projectId   = $_POST['ProjectId'];
$projectName = $_POST['ProjectName'];
$action      = $_POST['Action'];

$events = CAFEVDB\Projects::events($projectId);

$selected = array();
if ($action == 'select') {
  foreach ($events as $event) {
    $selected[$event['calEventId']] = true;
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

