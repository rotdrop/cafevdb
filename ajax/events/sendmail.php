<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Send events as email attachments via cafev email form.
 */

// This is NOT a replacement for calendar/ajax/event/sendmail.php

namespace CAFEVDB {

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

    $projectId   = $_POST['ProjectId'];
    $projectName = $_POST['ProjectName'];

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

    $events = Events::events($projectId);

    $dfltIds     = Events::defaultCalendars();
    $eventMatrix = Events::eventMatrix($events, $dfltIds);

    $emailEvents = CAFEVDB\Util::cgiValue('EventSelect', array());
    $selected = array(); // array marking selected events
    foreach ($emailEvents as $event) {
      $selected[$event] = true;
    }

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

} // namespace

?>