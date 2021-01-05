<?php
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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

/**Table generator for Instruments table. */
class ProjectInstrumentationNumbers extends PMETableViewBase
{
  const TEMPLATE = 'project-instrumentation-numbers';
  const CSS_CLASS = self::TEMPLATE;
  const TABLE = 'ProjectInstrumentationNumbers';
  const PROJECTS_TABLE = 'Projects';
  const INSTRUMENTS_TABLE = 'Instruments';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const PROJECT_PARTICIPANTS_TABLE = 'ProjectParticipants';

  // Projects Instruments ProjectInstruments
  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'master' => true,
      'entity' => Entities\ProjectInstrumentationNumber::class,
    ],
    [
      'table' => self::PROJECTS_TABLE,
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    [
      'table' => self::PROJECT_INSTRUMENTS_TABLE,
      'entity' => Entities\ProjectInstrument::class,
      'identifier' => [
        'project_id' => 'project_id',
        'instrument_id' => 'instrument_id',
        'musician_id' => false,
      ],
      'column' => 'musician_id',
    ],
    [
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'identifier' => [
        'id' => 'instrument_id',
      ],
      'column' => 'id',
    ],
    // [
    //   'table' => self::PROJECT_PARTICIPANTS_TABLE,
    //   'entity' => Entities\ProjectParticipant::class,
    //   'identifier' => [
    //     'project_id' => 'project_id',
    //     'musician_id' => [
    //       'table' => self::PROJECT_INSTRUMENTS_TABLE,
    //       'column' => 'musician_id',
    //     ],
    //   ],
    //   'column' => 'registration',
    // ],
  ];

  public function __construct(
    ConfigService $configService
  , RequestParameterService $requestParameters
  , EntityManager $entityManager
  , PHPMyEdit $phpMyEdit
  , ChangeLogService $changeLogService
  , ToolTipsService $toolTipsService
  , PageNavigation $pageNavigation
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
  }

  public function cssClass() {
    return self::CSS_CLASS;
  }

  public function shortTitle()
  {
    if ($this->projectName) {
      return $this->l->t("Instrumentation Numbers for `%s'", array($this->projectName));
    } else {
      return $this->l->t("Instrumentation Numbers");
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    $header .= "<p>".$this->l->t("The target instrumentation numbers can be filled into this table. ".
                          "The `have'-numbers are the numbers of the musicians ".
                          "already registered for the project.".
                          "In order to transfer the instruments of the already registerd musicions ".
                          "into this table click the `Adjust Instrument' option from the `Actions' menu.");

    return '<div class="'.self::CSS_CLASS.'-header-text">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template       = $this->template;
    $projectName    = $this->projectName;
    $projectId      = $this->projectId;
    $projectMode    = $projectId > 0;
    $instruments    = $this->instruments;
    $recordsPerPage = $this->recordsPerPage;

    $opts            = [];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    $template = self::TEMPLATE;
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'instrument_id' => 'int', 'voice' => 'int' ];
    $opts['groupby_fields'] = array_keys($opts['key']);
    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'year'),
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'name'),
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      'voice',
    ];

    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    if ($projectMode) {
      $opts['options'] = 'ACDFVP';
      $sort = false;
    } else {
      $opts['options'] = 'ACDFVP';
      $sort = true;
    }

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '16';

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => $sort,
        'time'  => true,
        'tabs'  => false,
        'navigation' => 'CDVP',
    ]);

    if ($projectMode) {
      $adjustButton = array(
        'name' => 'transfer_instruments',
        'value' => $this->l->t('Transfer Instruments'),
        'css' => 'transfer-registered-instruments'
        );
      $opts['buttons'] = $this->pageNavigation->prependTableButton($adjustButton, false, false);
    }

    // field definitions

    $joinTables = $this->defineJoinStructure($opts);

    $opts['fdd']['project_id'] = [
      'name'      => $this->l->t('Project'),
      'input'     => ($projectMode ? 'R' : ''),
      'css' => [ 'postfix' => ' project-instrument-project-name' ],
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : -1),
      'sort'     => $sort,
      'values|ACP' => [
        'column'      => 'id',
        'description' => 'name',
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        //        'join'        => '$main_col_fqn = $join_col_fqn',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
      ],
      'values|DVFL' => [
        'column'      => 'id',
        'description' => 'name',
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
        'filters'     => '$table.id IN (SELECT project_id FROM $main_table)',
      ],
    ];
    $this->addSlug('project', $opts['fdd']['project_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'name',
      [
        'name'  => $this->l->t('Project Name'),
        'input' => ($projectMode ? 'HR' : ''),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year',
      [
        'name'  => $this->l->t('Project Year'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['instrument_id'] = [
      'name'     => $this->l->t('Instrument'),
      'select'   => 'D',
      'options'  => 'LACPDVF',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => $sort,
      'values'   => [
        'column' => 'id',
        'description' => 'instrument',
        'orderby' => '$table.sort_order',
        'join' => [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      //'values2|AVCPDLF' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $this->addSlug('instrument', $opts['fdd']['instrument_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENTS_TABLE, 'sort_order',
      [
        'name'  => $this->l->t('Orchestral Sorting'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['voice'] = [
      'name'     => $this->l->t('Voice'),
      //'input'    => 'R',
      'select'   => 'D',
      'options'  => 'LACPDVF',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '-1',
      'sort'     => $sort,
      'values2' => [ '-1' => $this->l->t('n/a') ] + array_combine(range(1, 8), range(1, 8)),
    ];
    $this->addSlug('voice', $opts['fdd']['voice']);

    // required quantity
    $opts['fdd']['quantity'] = [
      'name' => $this->l->t('Required'),
      'select' => 'N',
      'sort' => $sort,
      'align' => 'right',
    ];
    $this->addSlug('required', $opts['fdd']['quantity']);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'musician_id',
      [
        'name'   => $this->l->t('Registered'),
        'input'  => 'VR',
        'sort'   => $sort,
        'select' => 'N',
        'align' => 'right',
        'sql'    => 'COUNT($join_col_fqn)',
      ]);

    $opts['fdd'][$this->joinTableFieldName(self::PROJECT_PARTICIPANTS_TABLE, 'musician_id')] = [
      'name'   => $this->l->t('Confirmed'),
      'input'  => 'VR',
      'sort'   => $sort,
      'select' => 'N',
      'align' => 'right',
      'sql'    => 'COUNT($join_col_fqn)',
      'values' => [
        'table' => self::PROJECT_PARTICIPANTS_TABLE,
        'column' => 'musician_id',
        'join' => '$join_table.project_id = $main_table.project_id
  AND $join_table.musician_id = '.$joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.musician_id
  AND $join_table.registration = 1',
      ],
    ];

    // trigger

    $opts['triggers']['*']['pre'][] = [ $this, 'preTrigger' ];

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['copy']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];

    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

    // go

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  private function addSlug(string $slug, array &$fdd)
  {
    $slug = self::CSS_CLASS.'-'.$slug;
    if (!isset($fdd['css']['postfix'])) {
      $fdd['css'] = [ 'postfix' => '' ];
    }
    $fdd['css']['postfix'] .= ' '.$slug;
    $fdd['tooltip'] = $this->toolTipsService[$slug];
  }
}
