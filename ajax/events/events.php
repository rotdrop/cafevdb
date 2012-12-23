<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

$projects = array_keys(CAFEVDB\Util::cgiValue('Project', array()));
if (count($projects) != 1 || !($projects[0] >= 0)) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

CAFEVDB\Config::init();
use CAFEVDB\L;

$projectId = $projects[0];
$events = CAFEVDB\Projects::events($projectId);

$lang = OC_L10N::findLanguage('cafevdb');
$locale = $lang.'_'.strtoupper($lang).'.UTF-8';

$tmpl = new OCP\Template('cafevdb', 'events');

$tmpl->assign('ProjectName', CAFEVDB\Projects::fetchName($projectId));
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);
$tmpl->assign('Locale', $locale);
$tmpl->assign('CSSClass', 'projectevents');
$tmpl->assign('Selected', array());

return $tmpl->printPage();

?>
