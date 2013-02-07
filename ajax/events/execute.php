<?php
/**@file
 */

/**@addtogroup AJAX
 * AJAX related scripts.
 * @{
 */
if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;
use CAFEVDB\Error;

try {
  
  Error::exceptions(true);
  
  $debugmode = Config::getUserValue('debugmode','') == 'on';
  $debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';

  $locale = Util::getLocale();

  $projectId   = Util::cgiValue('ProjectId', -1);
  $projectName = Util::cgiValue('ProjectName', '');

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

  $eventId = Util::cgiValue('EventId', -1);
  $action  = Util::cgiValue('Action', '');

  $emailEvents = Util::cgiValue('EventSelect', array());
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
    $events = Events::events($projectId);
    break;
  case 'detach':
    // Keep the event in the calendar, but detach it
    Events::unchain($projectId, $eventId);
    foreach ($emailEvents as $event) {
      if ($event != $eventId) {
        $selected[$event] = true;
      }
    }
    // Re-fetch the events
    $events = Events::events($projectId);
    break;
  case 'redisplay':
    // Re-fetch the events
    $events = Events::events($projectId);
    // Just remember the events selected for email transmission
    foreach ($emailEvents as $event) {
      $selected[$event] = true;
    }
    break;
  case 'select':
    // Re-fetch the events
    $events = Events::events($projectId);
    // Mark all events as selected.
    foreach ($events as $event) {
      $selected[$event['EventId']] = true;
    }
    break;
  case 'deselect':
    // Re-fetch the events
    $events = Events::events($projectId);
    // Just do nothing, leave all events unmarked.
    break;
  default:
    OCP\JSON::error(array('data' => array('contents' => '',
                                          'debug' => L::t('Invalid operation: ').$action)));
    return false;
  }

  $dfltIds     = Events::defaultCalendars();
  $eventMatrix = Events::eventMatrix($events, $dfltIds);

// Now generate the html-fragment

  $tmpl = new OCP\Template('cafevdb', 'eventslisting');

  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);
  $tmpl->assign('Events', $events);
  $tmpl->assign('EventMatrix', $eventMatrix);
  $tmpl->assign('locale', $locale);
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
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

/**@} AJAX group */

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>

