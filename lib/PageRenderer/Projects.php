<?php // Hey, Emacs, we are -*- php -*- mode!
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Events;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Projects table. */
class Projects extends PMETableViewBase
{
  const CSS_CLASS = 'projects';
  const TABLE = 'Projects';
  const ENTITY = Entities\Project::class;
  const INSTRUMENTATION_NUMBERS_TABLE = 'ProjectInstrumentationNumbers';
  const NAME_LENGTH_MAX = 20;
  const POSTER_JOIN = 'ProjectPoster';
  const FLYER_JOIN = 'ProjectFlyer';

  /** @var EventsService */
  private $eventsService;

  /** @var \OCP\EventDispatcher\IEventDispatcher */
  private $eventDispatcher;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , ProjectService $projectService
    , EventsService $eventsService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , IEventDispatcher $eventDispatcher
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->projectService = $projectService;
    $this->eventsService = $eventsService;
    $this->eventDispatcher = $eventDispatcher;

    if (empty($this->projectId) || $this->projectId < 0 || empty($this->projectName)) {
      $this->projectId = $this->pmeRecordId;
      if ($this->projectId > 0) {
        $this->projectName = $this->projectService->fetchName($this->projectId);
      }
    }
  }

  public function cssClass() { return self::CSS_CLASS; }

  /** Short title for heading. */
  public function shortTitle() {
    if (!empty($this->projectName)) {
      return $this->l->t("%s Project %s",
                         [ ucfirst($this->getConfigValue('orchestra')),
                           $this->projectName]);
    } else {
      return $this->l->t("%s Projects", [ ucfirst($this->getConfigValue('orchestra')) ]);
    }
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = ' show-hide-disabled';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Install values for after form-submit, e.g. $this->template ATM
    // is just the request parameter, while Template below will define
    // the value of $this->template after form submit.
    $template = 'projects';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = ['-year', 'name'];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => false
    ];

    $idIdx = 0;
    $opts['fdd']['id'] = [
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH', // always auto-increment
      'options'  => 'AVCPD',
      'maxlen'   => 11,
      'default'  => '0', // auto increment
      'sort'     => true,
    ];

    $currentYear = date('Y');
    $yearRange = $this->getDatabaseRepository(self::ENTITY)->findYearRange();
    $yearValues = [' '];
    for ($year = $yearRange["min"] - 1; $year < $currentYear + 5; $year++) {
      $yearValues[] = $year;
    }

    $yearIdx = count($opts['fdd']);
    $opts['fdd']['year'] = [
      'name'     => 'year',
      'select'   => 'N',
      //'options'  => 'LAVCPDF'
      'maxlen'   => 5,
      'default'  => $currentYear,
      'sort'     => true,
      'values'   => $yearValues,
    ];

    $nameIdx = count($opts['fdd']);
    $opts['fdd']['name'] = [
      'name'     => $this->l->t('Projekt-Name'),
      'php|LF'  => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        //error_log('project-id: '.$recordId);
        $projectId = $recordId;
        $projectName = $value;
        $placeHolder = false;
        $overview = true;
        return $this->projectActions($projectId, $projectName, $placeHolder, $overview);
      },
      'select'   => 'T',
      'select|LF' => 'D',
      'maxlen'   => self::NAME_LENGTH_MAX + 6,
      'css'      => ['postfix' => ' projectname control'],
      'sort'     => true,
      'values|LF'   => [
        'table' => self::TABLE,
        'column' => 'name',
        'description' => 'name',
        'groups' => 'year',
        'orderby' => '$table.`year` DESC',
      ],
    ];


    if ($this->showDisabled) {
      $opts['fdd']['disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'css'      => ['postfix' => ' project-disabled'],
        'values2|CAP' => [1 => '' ],
        'values2|LVFD' => [1 => $this->l->t('true'),
                           0 => $this->l->t('false')],
        'default'  => '0',
        'select'   => 'C',
        'sort'     => true,
        'tooltip'  => $this->toolTipsService['extra-fields-disabled']
      ];
    }

    $opts['fdd']['temporal_type'] = [
      'name'     => $this->l->t('Kind'),
      'select'   => 'D',
      'options'  => 'LFAVCPD', // auto increment
      'maxlen'   => 11,
      'css'      => ['postfix' => ' tooltip-right'],
      'values2'  => ['temporary' => $this->l->t('temporary'),
                     'permanent' => $this->l->t('permanent')],
      'default'  => 'temporary',
      'sort'     => true,
      'tooltip' => $this->toolTipsService['project-kind'],
    ];

    $opts['fdd']['actions'] = [
      'name'     => $this->l->t('Actions'),
      'input'    => 'RV',
      'sql'      => '`PMEtable0`.`name`',
      'php|VCLDF'    => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        $projectId = $recordId;
        $projectName = $value;
        $overview = false;
        $placeHolder = $this->l->t("Actions");
        return $this->projectActions($projectId, $projectName, $placeHolder, $overview);
      },
      'select'   => 'T',
      'options'  => 'VD',
      'maxlen'   => 11,
      'default'  => '0',
      'css'      => ['postfix' => ' control'],
      'sort'     => false
    ];

    $projInstIdx = count($opts['fdd']);
    $opts['fdd']['project_instrumentation_join'] = [
      'name'   => $this->l->t('Instrumentation Join Pseudo Field'),
      'sql'    => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.instrument_id
  ORDER BY PMEjoin'.$projInstIdx.'.instrument_id ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTATION_NUMBERS_TABLE,
        'column'      => 'instrument_id',
        'description' => [ 'columns' => [ 'instrument_id', ], ],
        'join'        => '$join_table.project_id = $main_table.id',
      ]
    ];

    $opts['fdd']['instrumentation_key'] = [
      'name'  => $this->l->t('Instrumentation Key'),
      'sql'   => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.instrument_id
  ORDER BY PMEjoin'.$projInstIdx.'.instrument_id ASC)',
      'input' => 'SRH',
      'filter' => 'having', // need "HAVING" for group by stuff
    ];

    $instIdx = count($opts['fdd']);
    $opts['fdd']['instrumentation'] = [
      'name'        => $this->l->t('Instrumentation'),
      'input'       => 'S', // skip
      'sort'        => true,
      'display|LF'  => ["popup" => 'data',
                        "prefix" => '<div class="projectinstrumentation">',
                        "postfix" => '</div>'],
      'css'         => ['postfix' => ' projectinstrumentation tooltip-top'],
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.id ORDER BY PMEjoin'.$instIdx.'.id ASC)',
      //'input' => 'V', not virtual, tweaked by triggers
      'filter'      => 'having',
      'select'      => 'M',
      'maxlen'      => 11,
      'values' => [
        'table'       => 'Instruments',
        'column'      => 'id',
        'description' => 'id',
        'orderby'     => 'sort_order',
        'join'        => '$join_table.id = PMEjoin'.$projInstIdx.'.instrument_id'
      ],
      'values2'     => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];

    $opts['fdd']['tools'] = [
      'name'     => $this->l->t('Toolbox'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projecttoolbox'],
      'sql'      => '`PMEtable0`.`Name`',
      'php|CV'   =>  function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        $projectName = $value;
        $projectId = $recordId;
        return $this->projectToolbox($projectId, $projectName);
      },
      'sort'     => true,
      'escape'   => false
    ];

    $opts['fdd']['service_charge'] = $this->defaultFDD['money'];
    $opts['fdd']['service_charge']['name'] = $this->l->t("Project Fee");
    $opts['fdd']['service_charge']['maxlen'] = 8;
    $opts['fdd']['service_charge']['tooltip'] = $this->l->t('Default project fee for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
    $opts['fdd']['service_charge']['display|LF'] = ['popup' => 'tooltip'];
    $opts['fdd']['service_charge']['css']['postfix'] .= ' tooltip-top';

    $opts['fdd']['pre_payment'] = $this->defaultFDD['money'];
    $opts['fdd']['pre_payment']['name'] = $this->l->t("Deposit");
    $opts['fdd']['pre_payment']['maxlen'] = 8;
    $opts['fdd']['pre_payment']['tooltip'] = $this->l->t('Default project deposit for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
    $opts['fdd']['pre_payment']['display|LF'] = ['popup' => 'tooltip'];
    $opts['fdd']['pre_payment']['css']['postfix'] .= ' tooltip-top';

    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['extra_fields_join'] = [
      'options'  => 'FLCVD',
      'input'    => 'VRH',
      'sql'      => '`PMEtable0`.`id`',
      'filter'   => 'having',
      'values'   => [
        'table'  => 'ProjectExtraFields',
        'column' => 'name',
        'description' => 'name',
        'join'   => '$main_table.`id` = $join_table.`project_id`'
      ],
    ];

    $opts['fdd']['extra_fields'] = [
      'name' => $this->l->t('Extra Member Data'),
      'options'  => 'FLCVD',
      'input'    => 'VR',
      'sql'      => ("GROUP_CONCAT(DISTINCT NULLIF(`".$join_table."`.`Name`,'') ".
                     "ORDER BY `".$join_table."`.`Name` ASC SEPARATOR ', ')"),
      'php|VCP'  => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx) {
        $post = ['ProjectExtraFields' => $value,
                 'template' => 'project-extra-fields',
                 'projectName' => $row['qf'.$nameIdx],
                 'project_id' => $recordId];
        $post = http_build_query($post, '', '&');
        $title = $this->toolTipsService['project-action-extra-fields'];
        $link =<<<__EOT__
<li class="nav tooltip-top" title="$title">
  <a class="nav" href="#" data-post="$post">
$value
  </a>
</li>
__EOT__;
        return $link;
      },
      'select'   => 'T',
      'maxlen'   => 30,
      'css'      => ['postfix' => ' projectextra'],
      'sort'     => false,
      'escape'   => false,
      'display|LF' => ['popup' => 'data'],
    ];

    $opts['fdd']['program'] = [
      'name'     => $this->l->t('Program'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projectprogram'],
      'sql'      => '`PMEtable0`.`id`',
      'php|CV'    => function($value, $action, $field, $fds, $fdd, $row, $recordId) {
        $projectId = $recordId; // and also $value
        return $this->projectProgram($projectId, $action);
      },
      'sort'     => true,
      'escape' => false
    ];

    $opts['fdd']['flyer'] = [
      'input' => 'V',
      'name' => $this->l->t('Flyer'),
      'select' => 'T',
      'options' => 'VCD',
      'sql'      => '`PMEtable0`.`Updated`',
      'php' => function($value, $action, $field, $fds, $fdd, $row, $recordId) {
        $projectId = $recordId;
        $stamp = $value;
        return $this->flyerImageLink($projectId, $action, $stamp);
      },
      'css' => ['postfix' => ' projectflyer'],
      'default' => '',
      'sort' => false,
    ];

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          "default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger.
        ]
      );

    /* Table-level filter capability. If set, it is included in the WHERE clause
       of any generated SELECT statement in SQL query. This gives you ability to
       work only with subset of data from table.

       $opts['filters'] = "column1 like '%11%' AND column2<17";
       $opts['filters'] = "section_id = 9";
       $opts['filters'] = "PMEtable0.sessions_count > 200";

       $opts['filters']['OR'] = expression or array;
       $opts['filters']['AND'] = expression or array;
       $opts['filters'] = andexpression or [andexpression1, andexpression2);
    */
    $opts['filters'] = [ 'OR' => [], 'AND' => [] ];
    if (!empty($this->parameterService[$this->pme->cgiSysName('qf'.$nameIdx.'_id')])) {
      // unset the year filter, as it does not make sense
      unset($_POST[$this->pme->cgiSysName('qf'.$yearIdx)]);
      unset($_GET[$this->pme->cgiSysName('qf'.$yearIdx)]);
    } else {
      $opts['filters']['OR'][] = "`PMEtable0`.`temporal_type` = 'permanent'";
    }
    $opts['filters']['AND'][] = '`PMEtable0`.`disabled` <= '.intval($this->showDisabled);

    // We could try to use 'before' triggers in order to verify the
    // data. However, at the moment the stuff does not work without JS
    // anyway, and we use Ajax calls to verify the form data.

    $opts['triggers']['update']['before'][]  = [ $this, 'addOrChangeInstrumentation' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateTrigger' ];
    $opts['triggers']['update']['after'][]   = [ $this, 'afterUpdateTrigger' ];

    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertTrigger' ];
    $opts['triggers']['insert']['after'][]   = [ $this, 'addOrChangeInstrumentation' ];
    $opts['triggers']['insert']['after'][]   = [ $this, 'afterInsertTrigger' ];

    $opts['triggers']['delete']['before'][] = [ $this , 'deleteTrigger' ];
    $opts['triggers']['delete']['after'][] = [ $this, 'deleteTrigger' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }

  }

  public function flyerImageLink($projectId, $action = 'display', $timeStamp = '')
  {
    switch ($action) {
      case 'add':
        return $this->l->t("Flyers can only be added to existing projects, please add the new
project without a flyer first.");
      case 'display':
        $url = $this->urlGenerator()->linkToRoute(
          'cafevdb.images.get',
          [ 'joinTable' => self::FLYER_JOIN,
            'ownerId' => $projectId ]);
        $url .= '?imageSize=1200&timeStamp='.$timeStamp;
        $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
        $div = ''
             .'<div class="photo"><img class="cafevdb_inline_image flyer zoomable" src="'.$url.'" '
             .'title="Flyer, if available" /></div>';
        return $div;
      case 'change':
        $imagearea = ''
          .'<div id="project_flyer_upload">
  <div class="tip project_flyer propertycontainer" id="cafevdb_inline_image_wrapper" title="'
        .$this->l->t("Drop image to upload (max %s)", [\OCP\Util::humanFileSize(Util::maxUploadSize())]).'"'
        .' data-element="PHOTO">
    <ul id="phototools" class="transparent hidden contacts_property">
      <li><a class="svg delete" title="'.$this->l->t("Delete current flyer").'"></a></li>
      <li><a class="svg edit" title="'.$this->l->t("Edit current flyer").'"></a></li>
      <li><a class="svg upload" title="'.$this->l->t("Upload new flyer").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select image from ownCloud").'"></a></li>
    </ul>
  </div>
</div> <!-- project_flyer -->
';
        return $imagearea;
      default:
        return $this->l->t("Internal error, don't know what to do concerning project-flyers in the given context.");
    }
  }

  public function projectActions($projectId, $projectName, $placeHolder = false, $overview = false)
  {
    $projectPaths = $this->projectService->ensureProjectFolders($projectId, $projectName);

    if ($placeHolder === false) {
      // Strip the 4-digit year from the end, if present
      // $placeHolder = preg_replace("/^(.*\D)(\d{4})$/", "$1", $projectName);
      $placeHolder = $projectName; // or maybe don't strip.
    }

    $control = ''
            .'
<span class="project-actions-block">
  <select data-placeholder="'.$placeHolder.'"
          class="project-actions"
          title="'.$this->toolTipsService['project-actions'].'"
          data-project-id="'.$projectId.'"
          data-project-name="'.htmlentities($projectName).'">
    <option value=""></option>
'
             .($overview
               ? $this->pageNavigation->htmlTagsFromArray([
                 'pre' => '<optgroup>', 'post' => '</optgroup>',
                 ['type' => 'option',
                  'title' => $this->toolTipsService['project-infopage'],
                  'value' => 'project-infopage',
                'name' => $this->l->t('Project Overview')
                 ]])
               : '')
             .$this->pageNavigation->htmlTagsFromArray([
               'pre' => '<optgroup>',
               'post' => '</optgroup>',
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-project-participants'],
                 'value' => 'project-participants',
                 'name' => $this->l->t('Participants') ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-project-instrumentation-numbers'],
                 'value' => 'project-instrumentation-numbers',
                 'name' => $this->l->t('Instrumentation Numbers') ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-extra-fields'],
                 'value' => 'project-extra-fields',
                 'name' => $this->l->t('Extra Member Data') ], ])
             .$this->pageNavigation->htmlTagsFromArray([
               'pre' => '<optgroup>',
               'post' => '</optgroup>',
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-files'],
                 'value' => 'project-files',
                 'data' => [ 'projectFiles' => $projectPaths['project'] ],
                 'name' => $this->l->t('Project Files')
               ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-wiki'],
                 'value' => 'project-wiki',
                 'data' => [
                   'wikiPage' => $this->projectService->projectWikiLink($projectName),
                   'wikiTitle' => $this->l->t('Project Wiki for %s', [ $projectName ])
                 ],
                 'name' => $this->l->t('Project Notes')
               ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-events'],
                 'value' => 'events',
                 'name' => $this->l->t('Events')
               ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-email'],
                 'value' => 'project-email',
                 'name' => $this->l->t('Em@il')
               ], ])
            .$this->pageNavigation->htmlTagsFromArray([
              'pre' => '<optgroup>',
              'post' => '</optgroup>',
              [ 'type' => 'option',
                'title' => $this->toolTipsService['project-action-debit-mandates'],
                'value' => 'sepa-debit-mandates',
                'disabled' => false, // @TODO !Config::isTreasurer(),
                'name' => $this->l->t('Debit Mandates')
              ],
              [ 'type' => 'option',
                'title' => $this->toolTipsService['project-action-financial-balance'],
                'value' => 'profit-and-loss',
                'data' => [ 'projectFiles' => $projectPaths['balance'] ],
                'name' => $this->l->t('Profit and Loss Account')
              ] ])
            .'
  </select>
</span>
';
      return $control;
  }

  /**Gather events, instrumentation numbers and the wiki-page in a
   * form-set for inclusion into some popups etc.
   */
  public function projectToolbox($projectId, $projectName, $value = false, $eventSelect = [])
  {
    $toolbox = $this->pageNavigation->htmlTagsFromArray(
      [
        'pre' => ('<fieldset class="projectToolbox" '.
                  'data-project-id="'.$projectId.'" '.
                  'data-project-name="'.htmlentities($projectName).'">'), // @TODO: standard way to do this
        'post' => '</fieldset>',
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action-wiki'],
          'data' => [
            'wikiPage' => $this->projectService->projectWikiLink($projectName),
            'wikiTitle' => $this->l->t('Project Wiki for %s', [ $projectName ])
          ],
          'class' => 'project-wiki tooltip-top',
          'value' => 'project-wiki',
          'name' => $this->l->t('Project Notes')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action-events'],
          'class' => 'events tooltip-top',
          'value' => 'events',
          'name' => $this->l->t('Events')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action-email'],
          'class' => 'project-email tooltip-top',
          'value' => 'project-email',
          'name' => $this->l->t('Em@il')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action-instrumentation-numbers'],
          'class' => 'project-instrumentation-numbers tooltip-top',
          'value' => 'project-instrumentation-numbers',
          'name' => $this->l->t('Instrumentation Numbers')
        ],
      ]);
    return '<div class="projectToolbox">
'.$toolbox.'
</div>
';
  }

  /**
   * Genereate the input data for the link to the CMS in order to edit
   * the project's public web articles inline.
   *
   * @todo Do something more useful in the case of an error (database
   * or CMS unavailable)
   */
  public function projectProgram($projectId, $action)
  {
    $projectPages = $this->projectService->projectWebPages($projectId);
    $urlTemplate = $this->projectService->webPageCMSURL('%ArticleId%', $action == 'change');
    if ($action != 'change') {
      $urlTemplate .= '&rex_version=1';
    }
    $templateParameters = array_merge(
      $projectPages,
      [
        'appName' => $this->appName(),
        'urlGenerator' => $this->urlGenerator(),
        'pageNavigation' => $this->pageNavigation,
        'projectId' => $projectId,
        'action' => $action,
        'cmsURLTemplate' => $urlTemplate,
      ]
    );

    $template = new TemplateResponse(
      $this->appName(),
      'project-web-articles',
      $templateParameters,
      'blank'
    );

    // in case the login at start did not work
    $this->projectService->refreshCMSCookies();

    return $template->render();
  }

  /**
   * Validate the name, no spaces, camel case, last four characters
   * are either digits of the form 20XX.
   *
   * @param string $projectName The name to validate.
   *
   * @param boolean $requireYear Year in last four characters is
   * mandatory.
   */
  private function sanitizeName($projectName, $requireYear = false)
  {
    $projectYear = substr($projectName, -4);
    if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
      $projectYear = null;
    } else {
      $projectName = substr($projectName, 0, -4);
    }
    if ($requireYear && !$projectYear) {
      return false;
    }

    if ($projectName ==  strtoupper($projectName)) {
      $projectName = strtolower($projectName);
    }
    $projectName = ucwords($projectName);
    $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);

    if ($projectYear) {
      $projectName .= $projectYear;
    }
    return $projectName;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (isset($newvals['name']) && $newvals['name']) {
      $newvals['name'] = $this->sanitizeName($newvals['name']);
      if ($newvals['name'] === false) {
        return false;
      }
    }
    return true;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @bug Convert this to a function triggering a "user-friendly" error message.
   */
  public function beforeUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (array_search('name', $changed) === false) {
      return true;
    }
    if (isset($newvals['name']) && $newvals['name']) {
      $newvals['name'] = $this->sanitizeName($newvals['name']);
      if ($newvals['name'] === false) {
        return false;
      }
    }
    return true;
  }

  /**
   * Instruments are stored in a separate pivot-table, hence we have
   * to take care of them from outside PME or use a view.
   *
   * @copydoc beforeTriggerSetTimestamp
   *
   * @todo Find out about transactions to be able to do a roll-back on
   * error.
   */
  public function addOrChangeInstrumentation($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $field = 'Instrumentation';
    $keyField = 'InstrumentationKey';
    $key = array_search($field, $changed);
    if ($key !== false) {
      //error_log('key: '.$key.' value: '.$changed[$key]);
      $table      = self::INSTRUMENTATION_NUMBERS_TABLE;
      $projectId  = $pme->rec;
      $oldIds     = Util::explode(',', $oldValues[$field]);
      $newIds     = Util::explode(',', $newValues[$field]);
      $oldKeys    = Util::explode(',', $oldValues[$keyField]);
      $oldRecords = array_combine($oldIds, $oldKeys);

      // we have to delete any removed instruments and to add any new instruments

      $repository = $this->getDatabaseRepository(Entities\ProjectInstrumentationNumber::class);
      try {
        foreach(array_diff($oldIds, $newIds) as $id) {
          $this->remove([ 'id' => $oldRecords[$id] ]);
        }
        $this->flush();
        // need references instead of id in order to "satisfy" associations
        $project = $this->entityManager->getReference(Entities\Project::class, [ 'id' => $projectId ]);
        foreach(array_diff($newIds, $oldIds) as $instrumentId) {
          $instrument = $this->entityManager->getReference(Entities\Instrument::class, [ 'id' => $instrumentId ]);
          $projectInstrument = Entities\ProjectInstrumentationNumber::create()
                             ->setProject($project)
                             ->setInstrument($instrument);
          $this->persist($projectInstrument);
          $this->flush($projectInstrument);
          $rec = $projectInstrument->getId();
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        // @todo Do we want to bailout here?
        // return false;
        throw new \Exception($this->l->t("Unable to update instrumentation"), $t->getCode(), $t);
      }
      /**
       * @note Unset in particular the $changed records. Note that
       * phpMyEdit will generate a new change-set after its operations
       * have completed, so the change-log entries for the original
       * table will also be present.
       */
      unset($changed[$key]);
      unset($newValues[$field]);
      unset($newValues[$keyField]);
    }

    return true;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function afterInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    // $newvals contains the new values
    $projectId   = $pme->rec;
    $projectName = $newvals['name'];

    // Also create the project folders.
    $projectPaths = $this->projectService->ensureProjectFolders($projectId, $projectName);

    $this->projectService->generateWikiOverview();
    $this->projectService->generateProjectWikiPage($projectId, $projectName);

    // Generate an empty offline page template in the public web-space
    $this->projectService->createProjectWebPage($projectId, 'concert');
    $this->projectService->createProjectWebPage($projectId, 'rehearsals');

    return true;
  }

  /**
   * @copydoc Projects::afterInsertTrigger()
   */
  public function afterUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (array_search('name', $changed) === false) {
      // Nothing more has to be done if the name stays the same
      return true;
    }

    // // Now that we link events to projects using their short name as
    // // category, we also need to update all linke events in case the
    // // short-name has changed.
    // $events = Events::events($pme->rec, $pme->dbh);

    // foreach ($events as $event) {
    //   // Last parameter "true" means to also perform string substitution
    //   // in the summary field of the event.
    //   Events::replaceCategory($event, $oldvals['name'], $newvals['name'], true);
    // }

    // Now, we should also rename the project folder. We simply can
    // pass $newvals and $oldvals
    $this->projectService->renameProjectFolder($newvals, $oldvals);

    // Same for the Wiki
    $this->projectService->renameProjectWikiPage($newvals, $oldvals);

    // Rename titles of all public project web pages
    $this->projectService-nameProjectWebPages($pme->rec, $newvals['name']);

    return true;
  }

  /**
   * @Copydoc Projects::afterInsertTrigger()
   *
   * This trigger, in particular, tries to take care to remove all
   * "side-effects" the existance of the project had. However, there
   * is some data which must not be removed automatically
   */
  public function deleteTrigger(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $projectId   = $pme->rec;
    $projectName = $oldvals['name'];
    if (empty($projectName)) {
      $projectName = $this->projectService->fetchName($projectId); // @TODO: really needed?
      $oldvals['name'] = $projectName;
    }

    $safeMode = false;
    if ($step === 'before') {
      $payments = $this->getDatabaseRepository(Entities\ProjectPayment::class)->findBy([ 'projectId' => $projectId ]);
      $safeMode = !empty($payments); // don't really remove if we have finance data
    }

    if ($step === 'after' || $safeMode) {
      // with $safeMode there is no 'after'. This should be cleaned up.

      // And now remove the project folder ... OC has undelete
      // functionality and we have a long-ranging backup.
      $this->projectService->removeProjectFolders($oldvals);

      // Regenerate the TOC page in the wiki.
      $this->projectService->generateWikiOverview();

      // Delete the page template from the public web-space. However,
      // here we only move it to the trashbin.
      $webPages = $this->getDatabaseRepository(Entities\ProjectWebPage::class)->findBy([ 'projectId' => $projectid ]);
      foreach ($webPages as $page) {
        // ignore errors
        $this->logDebug("Attempt to delete for ".$projectId.": ".$page['ArticleId']." all ".print_r($page, true));

        $this->projectService->deleteProjectWebPage($projectId, $page['ArticleId']);
      }

      // // Remove all attached events. This really deletes stuff.
      // $projectEvents = $this->eventsService->projectEvents($projectId);
      // foreach($projectEvents as $event) {
      //   $this->eventsService->deleteEvent($event);
      // }

      if ($step === 'after') {
        $this->eventDispatcher->dispatchTyped(new Events\ProjectDeletedEvent($projectId, $safeMode));
      }
    }

    if ($safeMode) {
      $this->projectService->disable($projectId);
      return false; // clean-up has to be done manually later
    }

    // remaining part cannot be reached if project-payments need to be
    // maintained, as in this case the 'before' trigger already has
    // aborted the deletion. Only events, web-pages and wiki are
    // deleted, and in the case of the wiki and the web-pages the
    // respective underlying "external" services make a backup-copy of
    // their own (respectively CAFEVDB just moves web-pages to the
    // Redaxo "trash" category).

    // // delete all extra fields and associated data.
    $repository = $this->getDatabaseRepository(Entities\ProjectExtraField::class);
    // @TODO use cascading to delete
    // $projectExtra = ProjectExtra::projectExtraFields($projectId, false, $pme->dbh);
    // foreach($projectExtra as $fieldInfo) {
    //   $fieldId = $fieldInfo['id'];
    //   ProjectExtra::deleteExtraField($fieldId, $projectId, true, $pme->dbh);
    // }

    $deleteTables = [
      Entities\ProjectParticipant::class,
      Entities\ProjectInstrument::class,
      Entities\ProjectWebPage::class,
      Entities\ProjectExtraField::class, // needs cascading
      // [ 'table' => 'ProjectEvents', 'column' => 'project_id' ], handled above
    ];

    $triggerResult = true;
    foreach($deleteTables as $table) {
      $this->entityManager
        ->createQueryBuilder()
        ->delete($table, 't')
        ->where('t.projectId = :projectId')
        ->setParameter('projectId', $projectId)
        ->getQuery()
        ->execute();
    }

    return $triggerResult;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
