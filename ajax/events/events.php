<?php

if(!OCP\User::isLoggedIn()) {
  die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

CAFEVDB\Config::init();
use CAFEVDB\L;

$debugtext = '<PRE>'.print_r($_POST, true).'</PRE>';

$projectId   = CAFEVDB\Util::cgiValue('ProjectId', -1);
$projectName = CAFEVDB\Util::cgiValue('ProjectName', '');

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
                      'debug' => $debugtext)));
  return true;
}

$events = CAFEVDB\Projects::events($projectId);

$lang = OC_L10N::findLanguage('cafevdb');
$locale = $lang.'_'.strtoupper($lang).'.UTF-8';

$tmpl = new OCP\Template('cafevdb', 'events');

$tmpl->assign('ProjectName', $projectName);
$tmpl->assign('ProjectId', $projectId);
$tmpl->assign('Events', $events);
$tmpl->assign('Locale', $locale);
$tmpl->assign('CSSClass', 'projectevents');
$tmpl->assign('Selected', array());

$html = $tmpl->fetchPage();

OCP\JSON::success(
  array('data' => array('contents' => $html,
                        'projectId' => $projectId,
                        'projectName' => $projectName,
                        'debug' => $debugtext)));

return true;

?>
