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

namespace OCA\CAFEVDB;

/**
 * @file
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Bav\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 *
 * @todo How to docme?
 */

/**
 * @global array $routes
 *
 * Cloud-routes registered with the app.
 *
 * @todo docme.
 */
$routes = [
  'routes' => [
    [
      'name' => 'page#not_found',
      'url' => '/ajax/{a}/{b}/{c}/{d}/{e}',
      'verb' => 'POST',
      'defaults' => [ 'a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '', ],
    ],
    [
      'name' => 'page#not_found',
      'url' => '/ajax/{a}/{b}/{c}/{d}/{e}',
      'verb' => 'GET',
      'defaults' => [ 'a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '', ],
    ],
    [
      'name' => 'page#index',
      'url' => '/',
      'verb' => 'GET',
    ],
    // [
    //   'name' => 'page#index',
    //   'url' => '/',
    //   'verb' => 'POST',
    // ],
    [
      'name' => 'page#loader',
      'url' => '/page/loader/{renderAs}',
      'verb' => 'POST',
      'defaults' => [ 'renderAs' => 'user' ],
    ],
    [
      'name' => 'page#remember',
      'url' => '/page/remember/{renderAs}',
      'verb' => 'POST',
      'defaults' => [ 'renderAs' => 'user' ],
    ],
    [
      'name' => 'page#history',
      'url' => '/page/recall/{level}',
      'verb' => 'POST',
      'defaults' => [ 'level' => 0 ]
    ],
    [
      'name' => 'pme_table#load',
      'url' => '/page/pme',
      'verb' => 'POST',
    ],
    [
      'name' => 'page#debug',
      'url' => '/page/debug',
    ],
    // internal "ajax" routes
    [
      'name' => 'admin_settings#set',
      'url' => '/settings/admin/set/{parameter}',
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
    // blog
    [ // generate template for editor popup
      'name' =>  'blog#edit_entry',
      'url' => '/blog/editentry',
      'verb' => 'POST',
    ],
    [ // create, modify, markread, delete
      'name' =>  'blog#action',
      'url' => '/blog/action/{operation}',
      'verb' => 'POST',
    ],
    // while-logged-in background job
    [
      'name' => 'background_job#trigger',
      'url' => '/backgroundjob/trigger',
      'verb' => 'GET',
    ],
    // progress status for ongoing long-runners
    [
      'name' => 'progress_status#get',
      'url' => '/foregroundjob/progress/{id}',
      'verb' => 'GET',
    ],
    [
      'name' => 'progress_status#action',
      'url' => '/foregroundjob/progress/{action}',
      'verb' => 'POST',
    ],
    // various download stuff
    [
      'name' => 'downloads#fetch',
      'url' => '/download/{path}',
      'verb' => 'GET',
    ],
    /**
     * Image service out of database or OC file-space
     *
     * FROM OLD VERSION
     *
     * GET: stored photo from data base
     * POST: upload / select cloud / save crop
     * POST: delete image by id
     *
     * Commands:
     * - section as OBJECT_IMAGE
     *     - musician_photo
     *     - project_poster
     *     - project_flyer
     *     - cloud
     * - object:
     */
    [
      'name' => 'images#get',
      'url' => '/image/{joinTable}/{ownerId}',
      'verb' => 'GET',
    ],
    /**
     * actions:
     *   - upload, upload from client machine
     *     Respond with temporary image path
     *   - cloud, select from cloud storage
     *     Respond with temporary image path
     *   - dragndrop
     *   - save, save image data, possibly from crop-editor
     *   - edit, crop existing edit
     *   - delete, delete given image
     */
    [
      'name' => 'images#post',
      'url' => '/image/{action}',
      'verb' => 'POST',
    ],
    /**
     * Project data validation etc.
     */
    [
      'name' => 'projects#validate',
      'url' => '/projects/validate/{topic}',
      'verb' => 'POST',
    ],
    /**
     * Project events
     */
    [
      'name' => 'project_events#service_switch',
      'url' => '/projects/events/{topic}',
      'verb' => 'POST',
      'defaults' => [ 'topic' => 'dialog' ],
    ],
    /**
     * Project web-pages
     */
    [
      'name' => 'project_web_pages#service_switch',
      'url' => '/projects/webpages',
      'verb' => 'POST',
    ],
  ],
];

return $routes;

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
