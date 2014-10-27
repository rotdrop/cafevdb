<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

OC::$CLASSPATH['CAFEVDB\Config'] = OC_App::getAppPath('cafevdb').'/lib/config.php';
OC::$CLASSPATH['CAFEVDB\Projects'] = OC_App::getAppPath('cafevdb').'/lib/projects.php';
OC::$CLASSPATH['CAFEVDB\Events'] = OC_App::getAppPath('cafevdb').'/lib/events.php';
OC::$CLASSPATH['CAFEVDB\Util'] = OC_App::getAppPath('cafevdb').'/lib/functions.php';

use \CAFEVDB\Config;
use \CAFEVDB\Events;
use \CAFEVDB\Projects;
use \CAFEVDB\Util;

// Internal config link
$this->create('cafevdb_config', 'js/config.js')
  ->actionInclude('cafevdb/js/config.php');

/*Return an array of project-events, given the respective project id. */
\OCP\API::register(
  'get',
  '/apps/'.Config::APP_NAME.'/projects/events/byProjectId/{projectId}/{timezone}/{locale}',
  function($params) {
    //\OCP\Util::writeLog(Config::APP_NAME, "event route: ".print_r($params, true), \OCP\Util::DEBUG);
    
    $projectId = $params['projectId'];
    $timezone = $params['timezone'];
    $locale = $params['locale'];

    if (!$timezone) {
      $timezone = Util::getTimezone();
    }
    if (!$locale) {
      $locale = Util::getLocale();
    } 

    return new \OC_OCS_Result(Events::projectEventData($projectId, null, $timezone, $locale));
  },
  Config::APP_NAME,
  \OC_API::USER_AUTH,
  // defaults
  array('timezone' => false,
        'locale' => false),
  // requirements
  array('projectId')
  );

/*Return an array of project-events, given the respective web-article id. */
\OCP\API::register(
  'get',
  '/apps/'.Config::APP_NAME.'/projects/events/byWebPageId/{articleId}/{timezone}/{locale}',
  function($params) {
    //\OCP\Util::writeLog(Config::APP_NAME, "event route: ".print_r($params, true), \OCP\Util::DEBUG);

    $articleId = $params['articleId'];
    $timezone = $params['timezone'];
    $locale = $params['locale'];

    if (!$timezone) {
      $timezone = Util::getTimezone();
    }
    if (!$locale) {
      $locale = Util::getLocale();
    } 

    $projects = Projects::fetchWebPageProjects($articleId);

    $data = array();
    foreach ($projects as $projectId) {
      $name = Projects::fetchName($projectId);
      if ($name === false) {
        continue;
      }
      $data[$name] = Events::projectEventData($projectId, null, $timezone, $locale);
    }

    return new \OC_OCS_Result($data);
  },
  Config::APP_NAME,
  \OC_API::USER_AUTH,
  // defaults
  array('timezone' => false,
        'locale' => false),
  // requirements
  array('articleId')
  );

?>
