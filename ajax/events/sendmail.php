<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

use CAFEVDB\L;

$debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

$lang = OC_L10N::findLanguage('cafevdb');
$locale = $lang.'_'.strtoupper($lang).'.UTF-8';

$projectId   = $_POST['ProjectId'];
$projectName = $_POST['ProjectName'];

$events = CAFEVDB\Projects::events($projectId);

// Now generate the html-fragment

$tmpl = new OCP\Template('cafevdb', 'eventslisting');

$tmpl->assign('ProjectName', $projectName);
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);
$tmpl->assign('Locale', $locale);
$tmpl->assign('CSSClass', 'projectevents');
$tmpl->assign('Selected', array());

$html = $tmpl->fetchPage();

OCP\JSON::success(array('data' => array('contents' => $html,
                                        'debug' => $debugtext)));

return true;

?>

