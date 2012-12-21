<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');

$projects = array_keys(CAFEVDB\Util::cgiValue('Project', array()));
if (count($projects) != 1 || !($projects[0] >= 0)) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
$projectId = $projects[0];
$events = CAFEVDB\Projects::events($projectId);

$l = OC_L10N::get('cafevdb');
trim($l->t('blah')); /* necessary, but why? */

$tmpl = new OCP\Template('cafevdb', 'events');

$tmpl->assign('ProjectName', CAFEVDB\Projects::fetchName($projectId));
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);

return $tmpl->printPage();

?>
