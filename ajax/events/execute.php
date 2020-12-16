<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**@file
 */

namespace CAFEVDB {

 /**@addtogroup AJAX
  * AJAX related scripts.
  * @{
  */

  if(!\OCP\User::isLoggedIn()) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
  }

  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::checkAppEnabled('calendar');

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
      \OCP\JSON::error(
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
      \OCP\JSON::error(array('data' => array('contents' => '',
                                            'debug' => L::t('Invalid operation: ').$action)));
      return false;
    }

    $dfltIds     = Events::defaultCalendars();
    $eventMatrix = Events::eventMatrix($events, $dfltIds);

    // Now generate the html-fragment

    $tmpl = new \OCP\Template('cafevdb', 'eventslisting');

    $tmpl->assign('ProjectName', $projectName);
    $tmpl->assign('ProjectId', $projectId);
    $tmpl->assign('Events', $events);
    $tmpl->assign('EventMatrix', $eventMatrix);
    $tmpl->assign('locale', $locale);
    $tmpl->assign('timezone', Util::getTimezone());
    $tmpl->assign('CSSClass', 'projectevents');
    $tmpl->assign('Selected', $selected);

    $html = $tmpl->fetchPage();

    \OCP\JSON::success(array('data' => array('contents' => $html,
                                             'debug' => $debugtext)));

    return true;

  } catch (\Exception $e) {
    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'message' => L::t('Error, caught an exception'))));
    return false;
  }

  /**@} AJAX group */

} // namespace

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
