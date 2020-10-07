<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Bav\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */

return [
    'routes' => [
        [
            'name' => 'page#not_found',
            'url' => '/ajax/{a}/{b}/{c}/{d}/{e}',
            'verb' => 'POST',
            'defaults' => [ 'a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '', ],
        ],
        [
            'name' => 'page#index',
            'url' => '/',
            'verb' => 'GET',
        ],
        [
            'name' => 'page#loader',
            'url' => '/page/loader/{renderAs}',
            'verb' => 'POST',
            'defaults' => [ 'renderAs' => 'user' ],
        ],
        [
            'name' => 'page#history',
            'url' => '/page/history/{level}',
            'verb' => 'POST',
            'defaults' => [ 'level' => 0 ]
        ],
        [
            'name' => 'page#debug',
            'url' => '/page/debug',
            'verb' => 'GET',
        ],
        // internal "ajax" routes
        [
            'name' => 'admin_settings#set',
            'url' => '/settings/admin/set',
            'verb' => 'POST',
        ],
        // personal settings
        [
            'name' => 'personal_settings#set',
            'url' => '/settings/personal/set/{parameter}',
            'verb' => 'POST',
        ],
        [
            'name' => 'personal_settings#form',
            'url' => '/settings/personal/form',
            'verb' => 'GET',
        ],
        [
            'name' => 'personal_settings#set_app',
            'url' => '/settings/app/set/{parameter}',
            'verb' => 'POST',
        ],
        [
            'name' => 'personal_settings#get',
            'url' => '/settings/get/{parameter}',
            'verb' => 'POST',
        ],
        // expert mode operations
        [
            'name' => 'expert_mode#form',
            'url' => '/expertmode/form',
            'verb' => 'GET',
        ],
        [
            'name' => 'expert_mode#action',
            'url' => '/expertmode/action/{operation}',
            'verb' => 'POST',
        ],
        // legacy calendar events
        [
            'name' => 'legacy_events#service_switch',
            'url' => '/legacy/events/{topic}/{subTopic}', // topic = forms|actions
            'verb' => 'POST',
        ],
        [
            'name' => 'legacy_events#export_event',
            'url' => '/legacy/events/actions/export',
            'verb' => 'GET',
        ],
   ]
];

return;

OC::$CLASSPATH['CAFEVDB\Config'] = OC_App::getAppPath('cafevdb').'/lib/config.php';
OC::$CLASSPATH['CAFEVDB\Projects'] = OC_App::getAppPath('cafevdb').'/lib/projects.php';
OC::$CLASSPATH['CAFEVDB\Events'] = OC_App::getAppPath('cafevdb').'/lib/events.php';
OC::$CLASSPATH['CAFEVDB\Util'] = OC_App::getAppPath('cafevdb').'/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Cron'] = OC_App::getAppPath("cafevdb") . '/lib/cron.php';

use \CAFEVDB\Config;
use \CAFEVDB\Events;
use \CAFEVDB\Projects;
use \CAFEVDB\Util;

// Internal config link
$this->create('cafevdb_config', 'js/config.js')
  ->actionInclude('cafevdb/js/config.php');

$this->create('cafevdb_root', '/')
  ->actionInclude('cafevdb/index.php');

$this->create('cafevdb_index', 'index.php')
  ->actionInclude('cafevdb/index.php');

// Regular tasks
$this->create('cafevdb_backgroundjobs', '/backgroundjobs')
  ->post()->action('CAFEVDB\Cron', 'run');

// include automatically generated routes
include 'autoroutes.php';

/*Return an array of project-events, given the respective project id. */
\OCP\API::register(
  'get',
  '/apps/'.Config::APP_NAME.'/projects/events/byProjectId/{projectId}/{calendar}/{timezone}/{locale}',
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
  array('calendar' => 'all',
        'timezone' => false,
        'locale' => false),
  // requirements
  array('projectId')
  );

/*Return an array of project-events, given the respective web-article
 * id. 'calendar' can be any of 'all', 'concerts', 'rehearsals',
 * 'other'. 'management' and 'finance' calendars are not exported by
 * the API.
 */
\OCP\API::register(
  'get',
  '/apps/'.Config::APP_NAME.'/projects/events/byWebPageId/{articleId}/{calendar}/{timezone}/{locale}',
  function($params) {
    //\OCP\Util::writeLog(Config::APP_NAME, "event route: ".print_r($params, true), \OCP\Util::DEBUG);

    $articleId = $params['articleId'];
    $calendar = $params['calendar'];
    $timezone = $params['timezone'];
    $locale = $params['locale'];

    switch ($calendar) {
    case 'all':
      $calendar = null;
      break;
    case 'concerts':
    case 'rehearsals':
    case 'other':
      $calendar = Config::getValue($calendar.'calendar'.'id');
      break;
    default:
      return new \OC_OCS_Result(null,
                                \OCP\API::RESPOND_NOT_FOUND,
                                "Invalid calendar type: ".$calendar);
    }

    // OC uses symphony which rawurldecodes the request URL. This
    // implies that in order to pass a slash / we caller must
    // urlencode that thingy twice, and Symphony consequently will
    // only deliver encoded data in this case.

    if (!$timezone) {
      $timezone = Util::getTimezone();
    }
    if (!$locale) {
      $locale = Util::getLocale();
    }
    $timezone = rawurldecode($timezone);
    $locale = rawurldecode($locale);

    $projects = Projects::fetchWebPageProjects($articleId);



    $data = array();
    foreach ($projects as $projectId) {
      $name = Projects::fetchName($projectId);
      if ($name === false) {
        continue;
      }
      $data[$name] = Events::projectEventData($projectId, $calendar, $timezone, $locale);
    }

    return new \OC_OCS_Result($data);
  },
  Config::APP_NAME,
  \OC_API::USER_AUTH,
  // defaults
  array('calendar' => 'all',
        'timezone' => false,
        'locale' => false),
  // requirements
  array('articleId')
  );

?>
