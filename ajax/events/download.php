<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Based on the download-script from the calendar app, which is
 *
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
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

namespace CAFEVDB {

  if(!\OCP\User::isLoggedIn()) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
  }
  \OCP\App::checkAppEnabled(CAFEVDB\Config::APP_NAME);

  $projectId   = $_POST['ProjectId'];
  $projectName = $_POST['ProjectName'];

  header("Content-Type: text/calendar");
  header("Content-Disposition: inline; filename=".$projectName.".ics");

  $debugmode = Config::getUserValue('debugmode','') == 'on';
  $debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';

  $emailEvents = Util::cgiValue('EventSelect', array());

  $events = array();
  if (count($emailEvents) > 0) {
    foreach ($emailEvents as $event) {
      $events[] = $event;
    }
  } else {
    $allEvents = Events::events($projectId);
    foreach ($allEvents as $event) {
      $events[] = $event['EventId'];
    }
  }

  echo Events::exportEvents($events, $projectName);

} // namespace
  
?>
