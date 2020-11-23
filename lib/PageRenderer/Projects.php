<?php // Hey, Emacs, we are -*- php -*- mode!
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Projects table. */
class Projects extends PMETableViewBase
{
  const CSS_CLASS = 'projects';
  const TABLE = 'Projects';
  const ENTITY = Entities\Project::class;
  const INSTRUMENTATION= 'ProjectInstrumentation';
  const NAME_LENGTH_MAX = 20;
  const POSTER_JOIN = 'ProjectPoster';
  const FLYER_JOIN = 'ProjectFlyer';

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , ProjectService $projectService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->projectService = $projectService;
  }

  public function cssClass() { return self::CSS_CLASS; }

  /** Short title for heading. */
  public function shortTitle() {
  }

  /** Header text informations. */
  public function headerText() {
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->pmeOptions;

    $expertMode = $this->getUserValue('expertmode');

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
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = ['-Jahr', 'Name'];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'Id';

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
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => false
      ],
    );

    $idIdx = 0;
    $opts['fdd']['Id'] = [
      'name'     => 'Id',
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
    $opts['fdd']['Jahr'] = [
      'name'     => 'Jahr',
      'select'   => 'N',
      //'options'  => 'LAVCPDF'
      'maxlen'   => 5,
      'default'  => $currentYear,
      'sort'     => true,
      'values'   => $yearValues,
    ];

    $nameIdx = count($opts['fdd']);
    $opts['fdd']['Name'] = [
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
        'column' => 'Name',
        'description' => 'Name',
        'groups' => 'Jahr',
        'orderby' => '$table.`Jahr` DESC',
      ],
    ];


    if ($this->showDisabled) {
      $opts['fdd']['Disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'css'      => ['postfix' => ' project-disabled'],
        'values2|CAP' => [1 => ''],
        'values2|LVFD' => [1 => $this->l->t('true'),
                           0 => $this->l->t('false')],
        'default'  => '',
        'select'   => 'O',
        'sort'     => true,
        'tooltip'  => $this->toolTipsService['extra-fields-disabled']
      ];
    }

    $opts['fdd']['Art'] = [
      'name'     => $this->l->t('Kind'),
      'select'   => 'D',
      'options'  => 'AVCPD', // auto increment
      'maxlen'   => 11,
      'css'      => ['postfix' => ' tooltip-right'],
      'values2'  => ['temporary' => $this->l->t('temporary'),
                     'permanent' => $this->l->t('permanent')],
      'default'  => 'temporary',
      'sort'     => false,
      'tooltip' => $this->toolTipsService['project-kind'],
    ];

    $opts['fdd']['Actions'] = [
      'name'     => $this->l->t('Actions'),
      'input'    => 'RV',
      'sql'      => '`PMEtable0`.`Name`',
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

    if (false) {
      $groupedInstruments = $this->instrumentInfo['nameGroups'];
      $instruments        = $this->instrumentInfo['byName'];

      $opts['fdd']['Besetzung'] = [
        'name'     => 'Besetzung',
        'options'  => 'LAVCPD',
        'select'   => 'M',
        'maxlen'   => 11,
        'sort'     => true,
        'display|LF' => ["popup" => 'data',
                         "prefix" => '<div class="projectinstrumentation">',
                         "postfix" => '</div>'],
        'css'      => ['postfix' => ' projectinstrumentation tooltip-top'],
        'values'   => $instruments,
        'valueGroups' => $groupedInstruments,
      ];
    }

    $projInstIdx = count($opts['fdd']);
    $opts['fdd']['ProjectInstrumentationJoin'] = [
      'name'   => $this->l->t('Instrumentation Join Pseudo Field'),
      'sql'    => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.Id
  ORDER BY PMEjoin'.$projInstIdx.'.InstrumentId ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTATION,
        'column'      => 'Id',
        'description' => ['columns' => 'Id'],
        'join'        => '$join_table.ProjectId = $main_table.Id',
      ]
    ];

    $opts['fdd']['InstrumentationKey'] = [
      'name'  => $this->l->t('Instrumentation Key'),
      'sql'   => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.Id
  ORDER BY PMEjoin'.$projInstIdx.'.InstrumentId ASC)',
      'input' => 'SRH',
      'filter' => 'having', // need "HAVING" for group by stuff
    ];

    $instIdx = count($opts['fdd']);
    $opts['fdd']['Instrumentation'] = [
      'name'        => $this->l->t('Instrumentation'),
      'input'       => 'S', // skip
      'sort'        => true,
      'display|LF'  => ["popup" => 'data',
                        "prefix" => '<div class="projectinstrumentation">',
                        "postfix" => '</div>'],
      'css'         => ['postfix' => ' projectinstrumentation tooltip-top'],
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.Id ORDER BY PMEjoin'.$instIdx.'.Id ASC)',
      //'input' => 'V', not virtual, tweaked by triggers
      'filter'      => 'having',
      'select'      => 'M',
      'maxlen'      => 11,
      'values' => [
        'table'       => 'Instruments',
        'column'      => 'Id',
        'description' => 'Id',
        'orderby'     => 'Sortierung',
        'join'        => '$join_table.Id = PMEjoin'.$projInstIdx.'.InstrumentId'
      ],
      'values2'     => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];

    $opts['fdd']['Tools'] = [
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

    $opts['fdd']['Unkostenbeitrag'] = $this->defaultFDD['money'];
    $opts['fdd']['Unkostenbeitrag']['name'] = $this->l->t("Project Fee");
    $opts['fdd']['Unkostenbeitrag']['maxlen'] = 8;
    $opts['fdd']['Unkostenbeitrag']['tooltip'] = $this->l->t('Default project fee for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
    $opts['fdd']['Unkostenbeitrag']['display|LF'] = ['popup' => 'tooltip'];
    $opts['fdd']['Unkostenbeitrag']['css']['postfix'] .= ' tooltip-top';

    $opts['fdd']['Anzahlung'] = $this->defaultFDD['money'];
    $opts['fdd']['Anzahlung']['name'] = $this->l->t("Deposit");
    $opts['fdd']['Anzahlung']['maxlen'] = 8;
    $opts['fdd']['Anzahlung']['tooltip'] = $this->l->t('Default project deposit for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
    $opts['fdd']['Anzahlung']['display|LF'] = ['popup' => 'tooltip'];
    $opts['fdd']['Anzahlung']['css']['postfix'] .= ' tooltip-top';

    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['ExtraFelderJoin'] = [
      'options'  => 'FLCVD',
      'input'    => 'VRH',
      'sql'      => '`PMEtable0`.`Id`',
      'filter'   => 'having',
      'values'   => [
        'table'  => 'ProjectExtraFields',
        'column' => 'Name',
        'description' => 'Name',
        'join'   => '$main_table.`Id` = $join_table.`ProjectId`'
      ],
    ];

    $opts['fdd']['ExtraFelder'] = [
      'name' => $this->l->t('Extra Member Data'),
      'options'  => 'FLCVD',
      'input'    => 'VR',
      'sql'      => ("GROUP_CONCAT(DISTINCT NULLIF(`".$join_table."`.`Name`,'') ".
                     "ORDER BY `".$join_table."`.`Name` ASC SEPARATOR ', ')"),
      'php|VCP'  => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx) {
        $post = ['ProjectExtraFields' => $value,
                 'Template' => 'project-extra',
                 'ProjectName' => $row['qf'.$nameIdx],
                 'ProjectId' => $recordId];
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

    $opts['fdd']['Programm'] = [
      'name'     => $this->l->t('Program'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projectprogram'],
      'sql'      => '`PMEtable0`.`Id`',
      'php|CV'    => function($value, $action, $field, $fds, $fdd, $row, $recordId) {
        $projectId = $recordId; // and also $value
        return $this->projectProgram($projectId, $action);
      },
      'sort'     => true,
      'escape' => false
    ];

    $opts['fdd']['Flyer'] = [
      'input' => 'V',
      'name' => $this->l->t('Flyer'),
      'select' => 'T',
      'options' => 'VCD',
      'sql'      => '`PMEtable0`.`Aktualisiert`',
      'php' => function($value, $action, $field, $fds, $fdd, $row, $recordId) {
        $projectId = $recordId;
        $stamp = $value;
        return $this->flyerImageLink($projectId, $action, $stamp);
      },
      'css' => ['postfix' => ' projectflyer'],
      'default' => '',
      'sort' => false,
    ];

    $opts['fdd']['Aktualisiert'] =
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
      $opts['filters']['OR'][] = "`PMEtable0`.`Art` = 'permanent'";
    }
    $opts['filters']['AND'][] = '`PMEtable0`.`Disabled` <= '.intval($this->showDisabled);

    // We could try to use 'before' triggers in order to verify the
    // data. However, at the moment the stuff does not work without JS
    // anyway, and we use Ajax calls to verify the form data.

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeUpdateRemoveUnchanged' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'addOrChangeInstrumentation' ];
    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeUpdateTrigger' ];
    // $opts['triggers']['update']['after'][]   = 'CAFEVDB\Projects::afterUpdateTrigger';

    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeInsertTrigger' ];
    $opts['triggers']['insert']['after'][]   = [ $this, 'addOrChangeInstrumentation' ];
    // $opts['triggers']['insert']['after'][]   = 'CAFEVDB\Projects::afterInsertTrigger';

    // $opts['triggers']['delete']['before'][] = 'CAFEVDB\Projects::deleteTrigger';
    // $opts['triggers']['delete']['after'][] = 'CAFEVDB\Projects::deleteTrigger';

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
        $div = ''
             .'<div class="photo"><img class="cafevdb_inline_image flyer zoomable" src="'
             .$this->urlGenerator()->linkToRoute('cafevdb.image.get.'.self::FLYER_JOIN.'.'.$projectId).'&imageSize=1200&timeStamp='.$timeStamp
             .'" '
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
    return "<H2>Project-Actions Placeholder</H2>";
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
          data-project-name="'.Util::htmlEncode($projectName).'">
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
                 'title' => $this->toolTipsService['project-action-detailed-instrumentation'],
                 'value' => 'detailed-instrumentation',
                 'name' => $this->l->t('Instrumentation') ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-instrumentation-numbers'],
                 'value' => 'project-instruments',
                 'name' => $this->l->t('Instrumentation Numbers') ],
               [ 'type' => 'option',
                 'title' => $this->toolTipsService['project-action-extra-fields'],
                 'value' => 'project-extra',
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
                  'data-project-name="'.html_entities($projectName).'">'), // @TODO: standard way to do this
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
          'class' => 'project-instruments tooltip-top',
          'value' => 'project-instruments',
          'name' => $this->l->t('Instrumentation Numbers')
        ],
      ]);
    return '<div class="projectToolbox">
'.$toolbox.'
</div>
';
  }

  /**Genereate the input data for the link to the CMS in order to edit
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
  private static function sanitizeName($projectName, $requireYear = false)
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
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (isset($newvals['Name']) && $newvals['Name']) {
      $newvals['Name'] = self::sanitizeName($newvals['Name']);
      if ($newvals['Name'] === false) {
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
   * @return boolean. If returning @c false the operation will be terminated
   *
   * @bug Convert this to a function triggering a "user-friendly" error message.
   */
  public static function beforeUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (array_search('Name', $changed) === false) {
      return true;
    }
    if (isset($newvals['Name']) && $newvals['Name']) {
      $newvals['Name'] = self::sanitizeName($newvals['Name']);
      if ($newvals['Name'] === false) {
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
      $table      = self::INSTRUMENTATION;
      $projectId  = $pme->rec;
      $oldIds     = Util::explode(',', $oldValues[$field]);
      $newIds     = Util::explode(',', $newValues[$field]);
      $oldKeys    = Util::explode(',', $oldValues[$keyField]);
      $oldRecords = array_combine($oldIds, $oldKeys);

      // we have to delete any removed instruments and to add any new instruments

      $repository = $this->getDatabaseRepository(Entities\ProjectInstrumentation::class);
      try {
        foreach(array_diff($oldIds, $newIds) as $id) {
          $this->remove([ 'Id' => $oldRecords[$id] ]);
          $this->changeLogService->logDelete($table, 'Id', [
            'Id' => $oldRecords[$id],
            'ProjectId' => $musicianId,
            'InstrumentId' => $id,
          ]);
        }
        $this->flush();
        // need references instead of id in order to "satisfy" associations
        $project = $this->entityManager->getReference(Entities\Project::class, [ 'Id' => $projectId ]);
        foreach(array_diff($newIds, $oldIds) as $instrumentId) {
          $instrument = $this->entityManager->getReference(Entities\Instrument::class, [ 'id' => $instrumentId ]);
          $projectInstrument = Entities\ProjectInstrumentation::create()
                             ->setProject($project)
                             ->setInstrument($instrument);
          $this->persist($projectInstrument);
          $this->flush($projectInstrument);
          $rec = $projectInstrument->getId();
          if (!empty($rec)) {
            $this->changeLogService->logInsert($table, $rec, [
              'ProjectId' => $projectId,
              'InstrumentId' => $id
            ]);
          }
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        // @todo Do we want to bailout here?
        // return false;
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
