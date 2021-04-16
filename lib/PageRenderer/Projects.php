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
  const TEMPLATE = 'projects';
  const TABLE = self::PROJECTS_TABLE;
  const ENTITY = Entities\Project::class;
  const NAME_LENGTH_MAX = 20;
  const POSTER_JOIN_TABLE = 'ProjectPoster';
  const FLYER_JOIN_TABLE = 'ProjectFlyer';

  /** @var OCA\CAFEVDB\Service\ProjectService */
  private $projectService;

  /** @var EventsService */
  private $eventsService;

  /** @var \OCP\EventDispatcher\IEventDispatcher */
  private $eventDispatcher;

  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'flags' => self::JOIN_MASTER,
      'entity' => self::ENTITY,
    ],
    [
      'table' => self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE,
      'entity' => Entities\ProjectInstrumentationNumber::class,
      'identifier' => [
        'project_id' => 'id',
        'instrument_id' => false,
        'voice' => false,
      ],
      'column' => 'instrument_id',
    ],
    [
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'readonly' => true,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    [
      'table' => self::PROJECT_PARTICIPANT_FIELDS_TABLE,
      'entity' => Entities\ProjectParticipantField::class,
      'readonly' => true,
      'identifier' => [
        'project_id' => 'id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    [
      'table' => self::FLYER_JOIN_TABLE,
      'entity' => Entities\ProjectFlyer::class,
      'readonly' => true,
      'identifier' => [
        'owner_id' => 'id',
        'image_id' => false,
      ],
      'column' => 'image_id',
    ],
  ];

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
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
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

    $joinTables = $this->defineJoinStructure($opts);

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
      'php|LF'  => function($value, $op, $field, $row, $recordId, $pme) {
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
        //'table' => self::TABLE,
        //'column' => 'name',
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
        'tooltip'  => $this->toolTipsService['participant-fields-disabled']
      ];
    }

    $opts['fdd']['type'] = [
      'name'     => $this->l->t('Kind'),
      'select'   => 'D',
      'options'  => 'LFAVCPD', // auto increment
      'maxlen'   => 11,
      'css'      => ['postfix' => ' tooltip-right'],
      'values2'  => $this->projectTypeNames,
      'default'  => 'temporary',
      'sort'     => true,
      'tooltip' => $this->toolTipsService['project-kind'],
    ];

    $opts['fdd']['actions'] = [
      'name'     => $this->l->t('Actions'),
      'input'    => 'RV',
      'sql'      => '$main_table.name',
      'php|VCLDF'    => function($value, $op, $field, $row, $recordId, $pme) {
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

    $opts['fdd']['tools'] = [
      'name'     => $this->l->t('Toolbox'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projecttoolbox'],
      'sql'      => '$main_table.name',
      'php|CV'   =>  function($value, $op, $field, $row, $recordId, $pme) {
        $projectName = $value;
        $projectId = $recordId;
        return $this->projectToolbox($projectId, $projectName);
      },
      'sort'     => true,
      'escape'   => false
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'instrument_id',
      [
        'name' => $this->l->t('Instrumentation'),
        'select' => 'M',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'display|LF'  => ["popup" => 'data',
                          "prefix" => '<div class="projectinstrumentation">',
                          "postfix" => '</div>'],
        'css'         => ['postfix' => ' projectinstrumentation tooltip-top'],
        'values' => [
          'column' => 'id',
          'description' => 'name',
          'orderby' => '$table.sort_order ASC',
          'join' => [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
        ],
        'values2' => $this->instrumentInfo['byId'],
        'valueGroups' => $this->instrumentInfo['idGroups'],
        'php|VD' => function($value, $op, $field, $row, $recordId, $pme) {
          $post = [
            'projectInstruments' => $value,
            'template' => 'project-instrumentation-numbers',
            'projectName' => $row[$this->queryField('name', $pme->fdd)],
            'project_id' => $recordId,
            'projectId' => $recordId,
          ];
          $json = json_encode($post);
          $post = http_build_query($post, '', '&');
          $title = $this->toolTipsService['project-action-project-instrumentation-numbers'];
          $link =<<<__EOT__
<li class="nav tooltip-top" title="$title">
  <a class="nav" href="#" data-post="$post" data-json='$json'>
$value
  </a>
</li>
__EOT__;
          return $link;
        },
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_TABLE, 'name',
      [
        'name' => $this->l->t('Extra Member Data'),
        'options'  => 'FLCVD',
        'input'    => 'VR',
        'sql'      => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_col_fqn ASC SEPARATOR \', \')',
        'php|VDCP'  => function($value, $op, $field, $row, $recordId, $pme) {
          $post = [
            'ProjectParticipantFields' => $value,
            'template' => 'project-participant-fields',
            'projectName' => $row[$this->queryField('name', $pme->fdd)],
            'project_id' => $recordId,
            'projectId' => $recordId,
          ];
          $json = json_encode($post);
          $post = http_build_query($post, '', '&');
          $title = $this->toolTipsService['project-action-participant-fields'];
          $link =<<<__EOT__
<li class="nav tooltip-top" title="$title">
  <a class="nav" href="#" data-post="$post" data-json='$json'>
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
      ]);

    $opts['fdd']['program'] = [
      'name'     => $this->l->t('Program'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projectprogram'],
      'sql'      => '$main_table.id',
      'php|CV'    => function($value, $action, $field, $row, $recordId, $pme) {
        $projectId = $recordId; // and also $value
        return $this->projectProgram($projectId, $action);
      },
      'sort'     => true,
      'escape' => false
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::FLYER_JOIN_TABLE, 'image_id', [
        'input' => 'V',
        'name' => $this->l->t('Flyer'),
        'select' => 'T',
        'options' => 'VCD',
        'php' => function($value, $action, $field, $row, $recordId, $pme) {
          $projectId = $recordId;
          $stamp = $value;
          if (empty($value)) {
            return $this->flyerImageLink($projectId, $action, null);
          } else {
            return implode('', array_map(function($imageId) use ($projectId, $action) {
              return $this->flyerImageLink($projectId, $action, $imageId);
            }, Util::explode(',', $value)));
          }
        },
        'values' => [ 'grouped' => true, ],
        'css' => ['postfix' => ' projectflyer'],
        'default' => '',
        'sort' => false,
      ]);

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

    $opts['filters'] = [ 'OR' => [], 'AND' => [] ];
    if (!empty($this->parameterService[$this->pme->cgiSysName('qf'.$nameIdx.'_id')])) {
      // unset the year filter, as it does not make sense
      unset($_POST[$this->pme->cgiSysName('qf'.$yearIdx)]);
      unset($_GET[$this->pme->cgiSysName('qf'.$yearIdx)]);
    } else {
      $opts['filters']['OR'][] = "\$table.type = 'permanent'";
    }
    $opts['filters']['AND'][] = '$table.disabled <= '.intval($this->showDisabled);

    // We could try to use 'before' triggers in order to verify the
    // data. However, at the moment the stuff does not work without JS
    // anyway, and we use Ajax calls to verify the form data.

    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateTrigger' ];
    $opts['triggers']['update']['after'][]   = [ $this, 'afterUpdateTrigger' ];

    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertTrigger' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertDoInsertAll' ];
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

  public function flyerImageLink($projectId, $action = 'display', $imageId = -1)
  {
    switch ($action) {
      case 'add':
        return $this->l->t("Flyers can only be added to existing projects, please add the new
project without a flyer first.");
      case 'display':
        $url = $this->urlGenerator()->linkToRoute(
          'cafevdb.images.get',
          [ 'joinTable' => self::FLYER_JOIN_TABLE,
            'ownerId' => $projectId, ]);
        $url .= '?imageSize=1200&timeStamp='.time();
        if (!empty($imageId) && $imageId > 0) {
          $url .= '&imageId='.$imageId;
        }
        $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
        $div = ''
             .'<div class="photo"><img class="cafevdb_inline_image flyer zoomable" src="'.$url.'" '
             .'title="Flyer, if available" /></div>';
        return $div;
      case 'change':
        $imageInfo = json_encode([
          'ownerId' => $projectId,
          'imageId' => $imageId,
          'joinTable' => self::FLYER_JOIN_TABLE,
          'imageSize' => 400,
        ]);
        $imagearea = ''
          .'<div class="project_flyer_upload">
  <div data-image-info=\''.$imageInfo.'\' class="tip project_flyer propertycontainer cafevdb_inline_image_wrapper" title="'
        .$this->l->t("Drop image to upload (max %s)", [\OCP\Util::humanFileSize(Util::maxUploadSize())]).'"'
        .' data-element="PHOTO">
    <ul class="phototools transparent hidden contacts_property">
      <li><a class="svg delete" title="'.$this->l->t("Delete current flyer").'"></a></li>
      <li><a class="svg edit" title="'.$this->l->t("Edit current flyer").'"></a></li>
      <li><a class="svg upload" title="'.$this->l->t("Upload new flyer").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select image from Cloud").'"></a></li>
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
                 'title' => $this->toolTipsService['project-action-participant-fields'],
                 'value' => 'project-participant-fields',
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
                'disabled' => false, // @todo !Config::isTreasurer(),
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
                  'data-project-name="'.htmlentities($projectName).'">'), // @todo: standard way to do this
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
    $urlTemplate = $this->projectService->webPageCMSURL('%articleId%', $action == 'change');
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
      $newvals['name'] = $this->projectService->sanitizeName($newvals['name']);
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
    $this->projectService->createProjectInfraStructure($newvals);
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
    // // category, we also need to updated all linke events in case the
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
    $this->projectService->nameProjectWebPages($pme->rec, $newvals['name']);

    return true;
  }

  /**
   * @copydoc Projects::afterInsertTrigger()
   *
   * This trigger, in particular, tries to take care to remove all
   * "side-effects" the existance of the project had. However, there
   * is some data which must not be removed automatically
   */
  public function deleteTrigger(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $this->projectService->deleteProject($pme->rec);

    $changed = []; // signal nothing more to delete

    return true;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
