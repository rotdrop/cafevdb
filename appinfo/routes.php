<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * @var array
 *
 * Cloud-routes registered with the app.
 *
 * @todo docme.
 */
$routes = [
  'routes' => [
    [
      'name' => 'page#index',
      'url' => '/',
      'verb' => 'GET',
    ],
    [
      'name' => 'page#index',
      'postfix' => 'post',
      'url' => '/',
      'verb' => 'POST',
    ],
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
      'name' => 'pme_table#service_switch',
      'url' => '/page/pme/{topic}', // load or export
      'verb' => 'POST',
      'defaults' => [ 'topic' => 'load' ],
    ],
    [
      'name' => 'page#debug',
      'url' => '/page/debug',
    ],
    // admin settings
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
    [
      'name' => 'personal_settings#get_app',
      'url' => '/settings/app/get/{parameter}',
      'verb' => 'POST',
    ],
    // expert mode operations
    // [
    //   'name' => 'expert_mode#form',
    //   'url' => '/expertmode/form',
    //   'verb' => 'GET',
    // ],
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
      'url' => '/foregroundjob/progress/{operation}',
      'verb' => 'POST',
    ],
    // CSP violation logging
    [
      'name' => 'csp_violation#post',
      'url' => '/csp-violation/{operation}',
      'verb' => 'POST',
      'defaults' => [ 'operation' => 'report' ],
    ],
    // generic upload end-point supporting multiple files and also to
    // old cloud-filepicker.
    //
    // Currently /upload/stash to move uploaded files to a app-storage
    // directory and /upload/move to move a stashed file to a cloud
    // directory.
    [
      'name' => 'uploads#stash',
      'url' => '/upload/stash',
      'verb' => 'POST',
    ],
    [
      'name' => 'uploads#move',
      'url' => '/upload/move',
      'verb' => 'POST',
    ],
    // various download stuff
    [
      'name' => 'downloads#fetch',
      'url' => '/download/{section}/{object}',
      'verb' => 'POST',
    ],
    [
      'name' => 'downloads#get',
      'url' => '/download/{section}/{object}',
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
     * operations:
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
      'url' => '/image/{operation}',
      'verb' => 'POST',
    ],
    /**
     * Project data validation etc.
     */
    [
      'name' => 'projects#validate',
      'url' => '/validate/projects/{topic}',
      'verb' => 'POST',
    ],
    /**
     *  options/define
     *  options/regenerate
     *  generator/define
     *  generator/run
     */
    [
      'name' => 'project_participant_fields#service_switch',
      'url' => '/projects/participant-fields/{topic}/{subTopic}',
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
      'url' => '/projects/webpages/{topic}',
      'verb' => 'POST',
    ],
    /**
     * Project Participants
     */
    [
      'name' => 'project_participants#change_instruments',
      'url' => '/projects/participants/change-instruments/{context}',
      'verb' => 'POST',
    ],
    [
      'name' => 'project_participants#add_musicians',
      'url' => '/projects/participants/add-musicians',
      'verb' => 'POST',
    ],
    [
      'name' => 'project_participants#files',
      'url' => '/projects/participants/files/{operation}',
      'verb' => 'POST',
    ],
    /**
     * Musicians
     */
    [
      'name' => 'musicians#validate',
      'url' => '/validate/musicians/{topic}',
      'verb' => 'POST',
    ],
    /**
     * Finance and stuff
     */
    [
      'name' => 'sepa_debit_mandates#mandate_validate',
      'url' => '/finance/sepa/debit-mandates/validate',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#mandate_form',
      'url' => '/finance/sepa/debit-mandates/dialog',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#mandate_store',
      'url' => '/finance/sepa/debit-mandates/store',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#mandate_delete',
      'url' => '/finance/sepa/debit-mandates/delete',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#mandate_disable',
      'url' => '/finance/sepa/debit-mandates/disable',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#mandate_reactivate',
      'url' => '/finance/sepa/debit-mandates/reactivate',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#account_delete',
      'url' => '/finance/sepa/bank-accounts/delete',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#account_disable',
      'url' => '/finance/sepa/bank-accounts/disable',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#account_reactivate',
      'url' => '/finance/sepa/bank-accounts/reactivate',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_debit_mandates#pre_filled_mandate_form',
      'url' => '/finance/sepa/debit-mandates/pre-filled',
      'verb' => 'POST',
    ],
    [
      'name' => 'sepa_bulk_transactions#service_switch',
      'url' => '/finance/sepa/bulk-transactions/{topic}',
      'verb' => 'POST',
    ],
    /**
     * Insurances
     */
    [
      'name' => 'instrument_insurance#validate',
      'url' => '/insurance/validate/{control}',
      'verb' => 'POST',
    ],
    [
      'name' => 'instrument_insurance#download',
      'url' => '/insurance/download',
      'verb' => 'POST',
    ],
    /**
     * Email form, mass email sending
     */
    [
      'name' => 'email_form#web_form',
      'url' => '/communication/email/outgoing/form',
      'verb' => 'POST',
    ],
    [
      'name' => 'email_form#recipients_filter',
      'url' => '/communication/email/outgoing/recipients-filter',
      'verb' => 'POST',
    ],
    /**
     * Operations:
     *
     * - update
     * - send
     * - cancel
     * - preview
     * - save
     * - delete
     * - load
     */
    [
      'name' => 'email_form#composer',
      'url' => '/communication/email/outgoing/composer/{operation}/{topic}',
      'verb' => 'POST',
      'default' => [
        'operation' => 'update',
        'topic' => 'undefined',
      ],
    ],
    /**
     * Attach a file by uploading it to the server (source = upload)
     * or choosing one from the cloud file-space (source = cloud).
     */
    [
      'name' => 'email_form#attachment',
      'url' => '/communication/email/outgoing/attachment/{source}',
      'verb' => 'POST',
      'defaults' => [ 'source' => 'upload' ],
    ],
    [
      'name' => 'email_form#contacts',
      'url' => '/communication/email/outgoing/contacts/{operation}',
      'verb' => 'POST',
    ],
    /**
     * General validations ...
     */
    [
      'name' => 'validation#service_switch',
      'url' => '/validate/general/{topic}',
      'verb' => 'POST',
    ],
    /**
     * Attempt a catch all ...
     */
    [
      'name' => 'page#post',
      'postfix' => 'post',
      'url' => '/{a}/{b}/{c}/{d}/{e}/{f}/{g}',
      'verb' => 'POST',
      'defaults' => [
        'a' => '',
        'b' => '',
        'c' => '',
        'd' => '',
        'e' => '',
        'f' => '',
        'g' => '',
      ],
    ],
  ],
];

return $routes;
