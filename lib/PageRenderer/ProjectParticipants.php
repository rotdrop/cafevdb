<?php
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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use chillerlan\QRCode\QRCode;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ProjectExtraFieldsService;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Instruments table. */
class ProjectParticipants extends PMETableViewBase
{
  const TEMPLATE = 'project-participants';
  const CSS_CLASS = self::TEMPLATE;
  const TABLE = 'ProjectParticipants';
  const MUSICIANS_TABLE = 'Musicians';
  const PROJECTS_TABLE = 'Projects';
  const INSTRUMENTS_TABLE = 'Instruments';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const MUSICIAN_INSTRUMENT_TABLE = 'MusicianInstrument';
  const PROJECT_INSTRUMENTATION_NUMBERS_TABLE = 'ProjectInstrumentationNumbers';
  const EXTRA_FIELDS_TABLE = 'ProjectExtraFields';
  const EXTRA_FIELDS_DATA_TABLE = 'ProjectExtraFieldsData';

  /**
   * Join table structure. All update are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'master' => true,
      'entity' => Entities\ProjectParticipant::class,
    ],
    [
      'table' => self::MUSICIANS_TABLE,
      'entity' => Entities\Musician::class,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
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
        'musician_id' => 'musician_id',
        'instrument_id' => false,
      ],
      'column' => 'instrument_id',
      'group_by' => true,
    ],
    [
      'table' => self::MUSICIAN_INSTRUMENT_TABLE,
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'instrument_id',
    ],
    // in order to get the participation in all projects
    [
      'table' => self::TABLE,
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'project_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'project_id',
      'read_only' => true,
    ],
    // extra input fields depending on the type of the project,
    // e.g. service fees etc.
    [
      'table' => self::EXTRA_FIELDS_TABLE,
      'entity' => Entities\ProjectExtraField::class,
      'identifier' => [
        'project_id' => 'project_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    [
      'table' => self::EXTRA_FIELDS_DATA_TABLE,
      'entity' => Entities\ProjectExtraFieldDatum::class,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'field_id' => [
          'table' => self::EXTRA_FIELDS_TABLE,
          'column' => 'id',
        ],
      ],
      'column' => 'field_id',
    ],
  ];

  /** @var \OCA\CAFEVDB\Service\GeoCodingService */
  private $geoCodingService;

  /** @var \OCA\CAFEVDB\Service\PhoneNumberService */
  private $phoneNumberService;

  /** @var \OCA\CAFEVDB\Service\ProjectExtraFieldsService */
  private $extraFieldsService;

  /** @var \OCA\CAFEVDB\PageRenderer\Musicians */
  private $musiciansRenderer;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project */
  private $project;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , GeoCodingService $geoCodingService
    , ContactsService $contactsService
    , PhoneNumberService $phoneNumberService
    , ProjectExtraFieldsService $extraFieldsService
    , Musicians $musiciansRenderer
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->musiciansRenderer = $musiciansRenderer;
    $this->extraFieldsService = $extraFieldsService;
    $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
  }

  public function cssClass() {
    return self::CSS_CLASS;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove the musician from %s?', [ $this->projectName ]);
    } else if ($this->viewOperation()) {
      return $this->l->t('Display of all stored data for the shown musician.');
    } else if ($this->changeOperation()) {
      return $this->l->t('Edit the data of the displayed musician.');
    }
    return $this->l->t("Instrumentation for Project `%s'", [ $this->projectName ]);
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    if (empty($projectName) || empty($projectId)) {
      throw new \InvalidArgumentException('Project-id and/or -name must be given ('.$projectName.' / '.$projectId.').');
    }

    $opts['filters']['AND'] = [
      "PMEtable0.project_id = $projectId",
      "PMEtable0.disabled <= ".intval($this->showDisabled),
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => self::TEMPLATE,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.self::TEMPLATE,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'musician_id' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      'voice',
      '-section_leader',
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'first_name'),
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    $extraFields = $this->project['extraFields'];

    $this->logInfo(count($extraFields));

    // count number of finance fields
    $extraFinancial = 0;
    foreach ($extraFields as $field) {
      $extraFinancial += $field['dataType'] == 'service-fee';
    }
    if ($extraFinancial > 0 || $project['prePayment'] > 0) {
      $useFinanceTab = true;
      $financeTab = 'finance';
    } else {
      $useFinanceTab = false;
      $financeTab = 'project';
    }

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs' => $this->tableTabs($userExtraFields, $useFinanceTab),
        'navigation' => 'VCD',
    ]);

    $opts['fdd']['project_id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      ];

    $opts['fdd']['musician_id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Musician-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    ];

    $joinTables = $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'first_name',
      [
        'name'     => $this->l->t('First Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'name',
      [
        'name'     => $this->l->t('Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'maxlen'   => 384,
      ]);

    if ($this->showDisabled) {
      $opts['fdd']['disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'tab'      => [ 'id' => 'tab-all' ], // display on all tabs, or just give -1
        'options' => $expertMode ? 'LAVCPDF' : 'LVCPDF',
        'input'    => $expertMode ? '' : 'R',
        'select'   => 'C',
        'maxlen'   => 1,
        'sort'     => true,
        'escape'   => false,
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /* '&#10004;' */ ],
        'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
        'tooltip'  => $this->toolTipsService['musician-disabled'],
        'css'      => [ 'postfix' => ' musician-disabled' ],
      ];
    }

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id',
      [
        'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
        'name'        => $this->l->t('Project Instrument'),
        'css'         => ['postfix' => ' project-instruments tooltip-top'],
        'display|LVF' => ['popup' => 'data'],
        'sql|VDCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'select'      => 'M',
        //'filter'      => 'having', // need "HAVING" for group by stuff
        'values|VDPC' => [
          'table'       => self::INSTRUMENTS_TABLE,
          'column'      => 'id',
          'description' => 'name',
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.instrument_id',
          'filters'     => "FIND_IN_SET(id, (SELECT GROUP_CONCAT(DISTINCT instrument_id) FROM ".self::MUSICIAN_INSTRUMENT_TABLE." mi WHERE \$record_id[project_id] = ".$projectId." AND \$record_id[musician_id] = mi.musician_id GROUP BY mi.musician_id))",
        ],
        'values|LFV' => [
          'table'       => self::INSTRUMENTS_TABLE,
          'column'      => 'id',
          'description' => 'name',
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.instrument_id',
          'filters'     => "FIND_IN_SET(id, (SELECT GROUP_CONCAT(DISTINCT instrument_id) FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi WHERE ".$projectId." = pi.project_id GROUP BY pi.project_id))",
        ],
        //'values2' => $this->instrumentInfo['byId'],
        'valueGroups' => $this->instrumentInfo['idGroups'],
      ]);
    $joinTables[self::INSTRUMENTS_TABLE] = 'PMEjoin'.(count($opts['fdd'])-1);

    $opts['fdd'][$this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order')] = [
      'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'HRS',
      'sort'        => true,
      'values' => [
        'column' =>  'sort_order',
        'orderby' => '$table.sort_order ASC',
        'join' => [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'voice',
      [
        'tab'      => [ 'id' => 'instrumentation' ],
        'name'     => $this->l->t('Voice'),
        'default'  => -1, // keep in sync with ProjectInstrumentationNumbers
        'select'   => 'M',
        'css'      => [ 'postfix' => ' allow-empty no-search instrument-voice' ],
        'sql|VD' => "GROUP_CONCAT(DISTINCT
  IF(\$join_col_fqn != -1,
     CONCAT(".$joinTables[self::INSTRUMENTS_TABLE].".name,
            ' ',
            \$join_col_fqn),
     NULL)
  ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        'sql|CP' => "GROUP_CONCAT(DISTINCT CONCAT(".$joinTables[self::INSTRUMENTS_TABLE].".id,':',".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice) ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        'values|CP' => [
          'table' => "SELECT
  pi.project_id,
  pi.musician_id,
  i.id AS instrument_id,
  i.name,
  i.sort_order,
  pin.quantity,
  n.n,
  CONCAT(pi.instrument_id,':', n.n) AS value
  FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pi.instrument_id
  LEFT JOIN ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin
    ON pin.instrument_id = pi.instrument_id
  JOIN numbers n
    ON n.n <= pin.voice AND n.n >= 1
  WHERE
    pi.project_id = $projectId
  GROUP BY
    project_id, musician_id, instrument_id, n
  ORDER BY
    i.sort_order ASC, n.n ASC",
          'column' => 'value',
          'description' => [
            'columns' => [ 'name', 'n' ],
            'divs' => ' ',
          ],
          'orderby' => '$table.sort_order ASC, $table.n ASC',
          'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
          //'join' => '$join_table.musician_id = $main_table.musician_id AND $join_table.project_id = $main_table.project_id',
          'join' => false,
        ],
        'values2|LF' => [ '-1' => $this->l->t('n/a') ] + array_combine(range(1, 8), range(1, 8)),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'section_leader',
      [
       'name|LF' => ' &alpha;',
       'name|CAPVD' => $this->l->t("Section Leader"),
       'tab' => [ 'id' => 'instrumentation' ],
       'css'      => [ 'postfix' => ' section-leader tooltip-top' ],
       'default' => false,
       'options'  => 'LAVCPDF',
       'select' => 'C',
       'maxlen' => '1',
       'sort' => true,
       'escape' => false,
       'sql|CAPDV' => "GROUP_CONCAT(
  IF(".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader = 1, ".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".instrument_id, 0) ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
       'display|LF' => [ 'popup' => function($data) {
         return $this->toolTipsService['section-leader-mark'];
       }],
       'values|CAPDV' => [
         'table' => "SELECT
  pi.project_id,
  pi.musician_id,
  pi.instrument_id,
  i.name,
  i.sort_order
  FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pi.instrument_id
  WHERE
    pi.project_id = $projectId",
         'column' => "instrument_id",
         'description' => "name",
         'orderby' => '$table.sort_order',
         'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
         'join' => '$join_table.project_id = $main_table.project_id AND $join_table.musician_id = $main_table.musician_id',
       ],
       'values2|LF' => [ '0' => '&nbsp;', '1' => '&alpha;' ],
       'tooltip' => $this->l->t("Set to `%s' in order to mark the section leader",
                                [ "&alpha;" ]),
      ]);

    $opts['fdd']['registration'] = [
      'name|LF' => ' &#10004;',
      'name|CAPDV' => $this->l->t("Registration"),
      'tab' => [ 'id' => [ 'project', 'instrumentation' ] ],
      'options'  => 'LAVCPDF',
      'select' => 'C',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /* '&#10004;' */ ],
      'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
      'tooltip' => $this->l->t("Set to `%s' in order to mark participants who passed a personally signed registration form to us.",
                               [ "&#10004;" ]),
      'display|LF' => [
        'popup' => function($data) {
          return $this->toolTipsService['registration-mark'];
        },
      ],
      'css'      => [ 'postfix' => ' registration tooltip-top' ],
    ];

    $fdd = [
      'name'        => $this->l->t('All Instruments'),
      'tab'         => [ 'id' => [ 'musician', 'instrumentation' ] ],
      'css'         => ['postfix' => ' musician-instruments tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => 'GROUP_CONCAT(DISTINCT IF('.$joinTables[self::MUSICIAN_INSTRUMENT_TABLE].'.disabled, NULL, $join_col_fqn) ORDER BY $order_by)',
      'select'      => 'M',
      //'filter'      => 'having', // ?????? need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'name',
        'orderby'     => '$table.sort_order ASC',
        'join'        => '$join_col_fqn = '.$joinTables[self::MUSICIAN_INSTRUMENT_TABLE].'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.disabled = 0' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENT_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENT_TABLE, 'disabled', [
        'name'    => $this->l->t('Disabled Instruments'),
        'tab'     => [ 'id' => [ 'musician', 'instrumentation' ] ],
        'sql'     => "GROUP_CONCAT(DISTINCT IF(\$join_col_fqn, \$join_table.instrument_id, NULL))",
        'default' => false,
        'select'  => 'T',
        'input'   => ($expertMode ? 'R' : 'RH'),
        'tooltip' => $this->toolTipsService['musician-instruments-disabled'],
      ]);


    /*
     *
     **************************************************************************
     *
     * several fields from Musicians table
     *
     */

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'member_status',
      [
        'name'    => $this->l->t('Member Status'),
        'select'  => 'D',
        'maxlen'  => 128,
        'css'     => ['postfix' => ' memberstatus tooltip-wide'],
        'values2' => $this->memberStatusNames,
        'tooltip' => $this->toolTipsService['member-status'],
      ]);

    /*
     *
     **************************************************************************
     *
     * project fee and debit mandates information
     *
     */

    /*
     *
     **************************************************************************
     *
     * extra columns like project fee, deposit etc.
     *
     */


    // Generate input fields for the extra columns
    foreach ($extraFields as $field) {
      $fieldName = $field['name'];
      $fieldId   = $field['id'];

      // set tab unless overridden by field definition
      if ($field['data_type'] == 'service-fee') {
        $tab = [ 'id' => $financeTab ];
      } else {
        $tab = [ 'id' => 'project' ];
      }
      if (!empty($field['tab'])) {
        $tabId = $this->tableTabId($field['tab']);
        $tab = [ 'id' => $tabId ];
      }

      list($curColIdx, $fddName) = $this->makeJoinTableField(
        $opts['fdd'], self::EXTRA_FIELDS_DATA_TABLE, $fieldName.':'.$fieldId,
        [
          'name' => $this->l->t($fieldName),
          'tab' => $tab,
          'css'      => [ 'postfix' => ' extra-field' ],
          'sql' => 'GROUP_CONCAT(DISTINCT
  IF($join_table.field_id = '.$fieldId.', $join_table.field_value, NULL))',
          'default'  => $field['default_value'],
          'values' => [
            'column' => 'field_id',
          ],
          'tooltip' => $field['tool_tip']?:null,
        ]);
      $fdd = &$opts['fdd'][$fddName];

      $allowed = $this->extraFieldsService->explodeAllowedValues($field['allowed_values'], false, true);
      $values2     = [];
      $valueTitles = [];
      $valueData   = [];
      foreach ($allowed as $idx => $value) {
        $key = $value['key'];
        if (empty($key)) {
          continue;
        }
        if ($value['flags'] === 'deleted') {
          continue;
        }
        $values2[$key] = $value['label'];
        $valueTitles[$key] = $value['tooltip'];
        $valueData[$key] = $value['data'];
      }

      switch ($field['data_type']) {
      case 'text':
        // default config
        break;
      case 'html':
        $fdd['textarea'] = [
          'css' => 'wysiwyg-editor',
          'rows' => 5,
          'cols' => 50,
        ];
        $fdd['css']['postfix'] .= ' hide-subsequent-lines';
        $fdd['display|LF'] = [ 'popup' => 'data' ];
        $fdd['escape'] = false;
        break;
      case 'integer':
        $fdd['select'] = 'N';
        $fdd['mask'] = '%d';
        breal;
      case 'float':
        $fdd['select'] = 'N';
        $fdd['mask'] = '%g';
        break;
      case 'date':
      case 'datetime':
      case 'money':
      case 'service-fee':
        $style = $this->defaultFDD[$field['data_type']];
        unset($style['name']);
        $fdd = array_merge($fdd, $style);
        $fdd['css']['postfix'] .= ' extra-field';
        break;
      default:
        throw new \Exception($this->l->t('Unsupported data type: "%s"', $field['data_type']));
      }

      switch ($field['multiplicity']) {
      case 'simple':
        // handled above
        break;
      case 'single':
        // @TODO number options?
        reset($values2); $key = key($values2);
        $fdd['values2|CAP'] = [ $key => '' ]; // empty label for simple checkbox
        $fdd['values2|LVDF'] = [
          0 => $this->l->t('false'),
          $key => $this->l->t('true'),
        ];
        $fdd['select'] = 'C';
        $fdd['default'] = (string)!!(int)$field['default_value'];
        $fdd['css']['postfix'] .= ' boolean single-valued '.$field['data_type'];
        switch ($field['data_type']) {
        case 'money':
        case 'service-fee':
          $money = $this->moneyValue(reset($valueData));
          // just use the amount to pay as label
          $fdd['values2|LVDF'] = [
            0 => '-,--',
            $key => $money
          ];
          $fdd['name|LFVD'] = $fdd['name'];
          $fdd['name'] = '<span class="allowed-option-name money">'.Util::htmlEscape($fdd['name']).'</span><span class="allowed-option-value money">'.$money.'</span>';
          break;
        }
        break;
      case 'multiple':
      case 'parallel':
        if ($field['data_type'] == 'service-fee') {
          foreach($values2 as $key => $value) {
            $money = $this->moneyValue($valueData[$key]);
            $value = Util::htmlEscape($value);
            $value = '<span class="allowed-option-name money multiple-choice">'.$value.'</span>';
            $money = '<span class="allowed-option-value money">'.'&nbsp;'.$money.'</span>';
            $values2[$key] = $value.$money;
          }
          $fdd['values2glue'] = "<br/>";
          $fdd['escape'] = false;
        }
        $fdd['values2'] = $values2;
        $fdd['valueTitles'] = $valueTitles;
        $fdd['valueData'] = $valueData;
        if ($field['multiplicity'] == 'parallel') {
          $fdd['css']['postfix'] .= ' set hide-subsequent-lines';
          $fdd['select'] = 'M';
        } else {
          $fdd['css']['postfix'] .= ' enumeration allow-empty';
          $fdd['select'] = 'D';
        }
        $fdd['css']['postfix'] .= ' '.$field['data_type'];
        $fdd['display|LF'] = [ 'popup' => 'data' ];
        break;
      case 'groupofpeople':
      case 'groupsofpeople':
        break;
      default:
        throw new \Exception($this->l->t('Unsupported field multiplicity type: "%s"', $field['multiplicity']));
      }

      // @TODO Groups: "simple group" e.g. "twin rooms". Collect a
      // number of participants into groups, e.g. to record
      // preferences of the participants for multi-bed accomodation
      //
      // "PredefinedGroups": E.g. to collect concrete groups into a
      // restricted number of named groups with restricted number of
      // participants per group. E.g. to distribute the participants
      // to cars or hotel-rooms and the like.

//       case 'SimpleGroup':
//       case 'SurchargeGroup':
//         // keep the original value as hidden input field and generate
//         // a new group-definition field as yet another column
//         $opts['fdd'][$fieldName.'Group'] = $fdd;
//         $fdd['input'] = 'H';
//         $fdd = &$opts['fdd'][$fieldName.'Group'];
//         $curColIdx++;

//         // define the group stuff
//         $max = $allowed[0]['limit']; // ATM, may change
//         $fdd = array_merge(
//           $fdd, [
//             'select' => 'M',
//             'sql' => "GROUP_CONCAT(DISTINCT PMEjoin{$curColIdx}.InstrumentationId)",
//             'display' => [ 'popup' => 'data' ],
//             'colattrs' => [ 'data-groups' => json_encode([ 'Limit' => $max ]), ],
//             'filter' => 'having',
//             'values' => [
//               'table' => "SELECT
//   b.Id AS InstrumentationId,
//   CONCAT_WS(' ', m.Vorname, m.Name) AS Name,
//   m.Name AS LastName, m.Vorname AS FirstName,
//   fd.FieldValue AS GroupId
// FROM Besetzungen b
// LEFT JOIN Musiker AS m
//   ON b.MusikerId = m.Id
// LEFT JOIN ProjectExtraFieldsData fd
//   ON b.Id = fd.BesetzungenId AND fd.FieldId = $fieldId
// WHERE b.ProjektId = $projectId",
//               'column' => 'InstrumentationId',
//               'description' => 'Name',
//               'groups' => "CONCAT('".$fieldName." ',\$table.GroupId)",
//               'data' => "CONCAT('{\"Limit\":".$max.",\"GroupId\":\"',IFNULL(\$table.GroupId,-1),'\"}')",
//               'orderby' => '$table.GroupId ASC, $table.LastName ASC, $table.FirstName ASC',
//               'join' => '$main_table.`'.$fieldName.'` = $join_table.GroupId',
//               ],
//             'valueGroups' => [ -1 => $this->l->t('without group') ],
//             ]);
//         $fdd['css']['postfix'] .= ' groupofpeople single-valued';

//         if ($type['Name'] === 'SurchargeGroup') {
//           $fdd['css']['postfix'] .= ' surcharge';
//           $money = Util::moneyValue(reset($valueData));
//           $fdd['name|LFVD'] = $fdd['name'];
//           $fdd['name'] = '<span class="allowed-option-name money">'.Util::htmlEscape($fdd['name']).'</span><span class="allowed-option-value money">'.$money.'</span>';
//           $fdd['display|LFVD'] = array_merge(
//             $fdd['display'],
//             [
//               'prefix' => '<span class="allowed-option-name clip-long-text group">',
//               'postfix' => ('</span><span class="allowed-option-value money">'.
//                             $money.
//                             '</span>'),
//               ]);
//         }

//         // in filter mode mask out all non-group-members
//         $fdd['values|LF'] = array_merge(
//           $fdd['values'],
//           [ 'filters' => '$table.GroupId IS NOT NULL' ]);

//         // after all this tweaking, we still need the real group id
//         $opts['fdd'][$fieldName.'GroupId'] = [
//           'name'     => $this->l->t('%s Group Id', array($name)),
//           'css'      => [ 'postfix' => ' groupofpeople-id' ],
//           'input|LFVD' => 'VRH',
//           'input'      => 'SRH',
//           'select'   => 'T',
//           'sql'      => 'PMEtable0.`'.$fieldName.'`',
//           ];
//         break;
//       case 'PredefinedGroups':
//       case 'SurchargeGroups':
//         // keep the original value as hidden input field and generate
//         // a new group-definition field as yet another column
//         $opts['fdd'][$fieldName.'Group'] = $fdd;
//         $fdd['input'] = 'H';
//         $fdd = &$opts['fdd'][$fieldName.'Group'];
//         $curColIdx++;

//         // define the group stuff
//         $groupValues2   = $values2;
//         $groupValueData = $valueData;
//         $values2 = [];
//         $valueGroups = [ -1 => $this->l->t('without group') ];
//         $idx = -1;
//         foreach($allowed as $value) {
//           $valueGroups[--$idx] = $value['key'];
//           $values2[$idx] = $this->l->t('add to this group');
//           $valueData[$idx] = json_encode([ 'GroupId' => $value['key'] ]);
//         }
//         $fdd = array_merge(
//           $fdd, [
//             'select' => 'M',
//             'sql' => "GROUP_CONCAT(DISTINCT PMEjoin{$curColIdx}.InstrumentationId)",
//             'display' => [ 'popup' => 'data' ],
//             'colattrs' => [ 'data-groups' => json_encode($allowed), ],
//             'filter' => 'having',
//             'values' => [
//               'table' => "SELECT
//   b.Id AS InstrumentationId,
//   CONCAT_WS(' ', m.Vorname, m.Name) AS Name,
//   m.Name AS LastName, m.Vorname AS FirstName,
//   fd.FieldValue AS GroupId
// FROM Besetzungen b
// LEFT JOIN Musiker AS m
//   ON b.MusikerId = m.Id
// LEFT JOIN ProjectExtraFieldsData fd
//   ON b.Id = fd.BesetzungenId AND fd.FieldId = $fieldId
// WHERE b.ProjektId = $projectId",
//               'column' => 'InstrumentationId',
//               'description' => 'Name',
//               'groups' => "\$table.GroupId",
//               'data' => "CONCAT('{\"GroupId\":\"',IFNULL(\$table.GroupId, -1),'\"}')",
//               'orderby' => '$table.GroupId ASC, $table.LastName ASC, $table.FirstName ASC',
//               'join' => '$main_table.`'.$fieldName.'` = $join_table.GroupId',
//               ],
//             'valueGroups' => $valueGroups,
//             'valueData' => $valueData,
//             'values2' => $values2,
//             ]);
//         $fdd['css']['postfix'] .= ' groupofpeople predefined clip-long-text';
//         $fdd['css|LFVD']['postfix'] = $fdd['css']['postfix'].' view';

//         // in filter mode mask out all non-group-members
//         $fdd['values|LF'] = array_merge(
//           $fdd['values'],
//           [ 'filters' => '$table.GroupId IS NOT NULL' ]);

//         $css = ' groupofpeople-id predefined';
//         if ($type['Name'] === 'SurchargeGroups') {
//           $css .= ' surcharge';
//           foreach($groupValues2 as $key => $value) {
//             $money = Util::moneyValue($groupValueData[$key], Config::$locale);
//             $groupValues2ACP[$key] = $value.':&nbsp;'.$money;
//             $value = Util::htmlEscape($value);
//             $value = '<span class="allowed-option-name group clip-long-text">'.$value.'</span>';
//             $money = '<span class="allowed-option-value money">'.'&nbsp;'.$money.'</span>';
//             $groupValues2[$key] = $value.$money;
//           }
//         }

//         // after all this tweaking, we still need the real group id
//         $opts['fdd'][$fieldName.'GroupId'] = [
//           'name'        => $this->l->t('%s Group', array($name)),
//           'css'         => [ 'postfix' => $css ],
//           'input|LFVD'  => 'VR',
//           'input'       => 'SR',
//           'select'      => 'D',
//           'sql'         => $fieldName,
//           'values2'     => $groupValues2,
// //          'values2|ACP' => $groupValues2ACP,
//           'display'     => [ 'popup' => 'data' ],
//           'sort'        => true,
//           'escape'      => false,
//           ];
//         if (!empty($groupValues2ACP)) {
//           $opts['fdd'][$fieldName.'GroupId']['values2|ACP'] = $groupValues2ACP;
//         }
//         break;
//       default:
//         break;
//       }
    }

    /*
     *
     **************************************************************************
     *
     * several further fields from Musicians table
     *
     */

    $opts['fdd']['remarks'] = [
      'name' => $this->l->t("Remarks")."\n(".$projectName.")",
      'tooltip' => $this->toolTipsService['project-remarks'],
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => [ 'postfix' => ' remarks tooltip-left' ],
      'textarea' => [
        'css' => 'wysiwyg-editor',
        'rows' => 5,
        'cols' => 50,
      ],
      'display|LF' => [ 'popup' => 'data' ],
      'escape' => false,
      'sort'   => true,
      'tab'    => [ 'id' => 'project' ]
    ];

    $opts['fdd']['all_projects'] = [
      'tab' => ['id' => 'musician'],
      'input' => 'VR',
      'options' => 'LFVC',
      'select' => 'M',
      'name' => $this->l->t('Projects'),
      'sort' => true,
      'css'      => ['postfix' => ' projects tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by SEPARATOR \',\')',
      //'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table' => 'Projects',
        'column' => 'id',
        'description' => 'name',
        'orderby' => '$table.year ASC, $table.name ASC',
        'groups' => 'year',
        'join' => '$join_col_fqn = '.$joinTables[self::TABLE].'.project_id'
      ],
      // @TODO check whether this is still needed or 'groups' => 'year' is just fine.
      //'values2' => $projects,
      //'valueGroups' => $groupedProjects
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'email',
      array_merge($this->defaultFDD['email'], [ 'tab' => ['id' => 'musician'], ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'mobile_phone',
      [
        'name'     => $this->l->t('Mobile Phone'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => [ 'postfix' => ' phone-number' ],
        'display'  => [
          'popup' => function($data) {
            return $this->phoneNumberService->metaData($data, null, '<br/>');
          }
        ],
        'nowrap'   => true,
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'fixed_line_phone',
      [
        'name'     => $this->l->t('Fixed Line Phone'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => [ 'postfix' => ' phone-number' ],
        'display'  => [
          'popup' => function($data) {
            return $this->phoneNumberService->metaData($data, null, '<br/>');
          }
        ],
        'nowrap'   => true,
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'street',
      [
        'name'     => $this->l->t('Street'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => ['postfix' => ' musician-address street'],
        'maxlen'   => 128,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'postal_code',
      [
        'name'     => $this->l->t('Postal Code'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => ['postfix' => ' musician-address postal-code'],
        'maxlen'   => 11,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'city',
      [
        'name'     => $this->l->t('City'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => ['postfix' => ' musician-address city'],
        'maxlen'   => 128,
      ]);

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'country',
      [
        'name'     => $this->l->t('Country'),
        'tab'      => [ 'id' => 'musician' ],
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => $this->getConfigValue('streetAddressCountry'),
        'css'      => ['postfix' => ' musician-address country chosen-dropup'],
        'values2'     => $countries,
        'valueGroups' => $countryGroups,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'birthday',
      array_merge($this->defaultFDD['birthday'], [ 'tab' => [ 'id' => 'musician' ], ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'remarks',
      [
        'tab'      => ['id' => 'musician'],
        'name'     => $this->l->t('Remarks'),
        'maxlen'   => 65535,
        'css'      => ['postfix' => ' remarks tooltip-top'],
        'textarea' => [
          'css' => 'wysiwyg-editor',
          'rows' => 5,
          'cols' => 50,
        ],
        'display|LF' => ['popup' => 'data'],
        'escape' => false,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'language',
      [
        'tab'      => ['id' => 'musician'],
        'name'     => $this->l->t('Language'),
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => 'Deutschland',
        'values2'  => $this->findAvailableLanguages(),
      ]);

    $opts['fdd']['photo'] = [
      'tab'      => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => $this->l->t('Photo'),
      'select' => 'T',
      'options' => 'APVCD',
      'sql' => '`PMEtable0`.`musician_id`', // @TODO: needed?
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        $stampIdx = array_search('Updated', $fds);
        $stamp = strtotime($row['qf'.$stampIdx]);
        return $this->musiciansRenderer->photoImageLink($musicianId, $action, $stamp);
      },
      'css' => ['postfix' => ' photo'],
      'default' => '',
      'sort' => false
    ];

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '`PMEtable0`.`musician_id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        switch($action) {
        case 'change':
        case 'display':
          $data = [];
          foreach($fds as $idx => $label) {
            $data[$label] = $row['qf'.$idx];
          }
          $musician = new Entities\Musician();
          foreach ($data as $key => $value) {
            $fieldInfo = $this->joinTableField($key);
            if ($fieldInfo['table'] != self::MUSICIANS_TABLE) {
              continue;
            }
            $column = $fieldInfo['column'];
            try {
              $musician[$column] = $value;
            } catch (\Throwable $t) {
              // Don't care, we know virtual stuff is not there
              // $this->logException($t);
            }
          }
          $vcard = $this->contactsService->export($musician);
          unset($vcard->PHOTO); // too much information
          //$this->logDebug(print_r($vcard->serialize(), true));
          return '<img height="231" width="231" src="'.(new QRCode)->render($vcard->serialize()).'"></img>';
        default:
          return '';
        }
      },
      'default' => '',
      'sort' => false
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'uuid',
      [
        'tab'      => [ 'id' => 'miscinfo' ],
        'name'     => 'UUID',
        'options'  => 'LAVCPDR',
        'css'      => ['postfix' => ' musician-uuid clip-long-text tiny-width'],
        'sql'      => 'BIN2UUID($join_col_fqn)',
        'display|LVF' => ['popup' => 'data'],
        'sqlw'     => 'UUID2BIN($val_qas)',
        'maxlen'   => 32,
        'sort'     => true,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'updated',
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          "default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'created',
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Created"),
          "default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
        ]));

    //////// END Field definitions

    $opts['triggers']['*']['pre'][] = [ $this, 'preTrigger' ];

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

//     $opts['triggers']['update']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeUpdateTrigger';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    ///@@@@@@@@@@@@@@@@@@@@@

//     $feeIdx = count($opts['fdd']);
//     $opts['fdd']['Unkostenbeitrag'] = Config::$opts['money'];
//     $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";
//     $opts['fdd']['Unkostenbeitrag']['default'] = $project['Unkostenbeitrag'];
//     $opts['fdd']['Unkostenbeitrag']['css']['postfix'] .= ' fee';
//     $opts['fdd']['Unkostenbeitrag']['tab'] = array('id' => $financeTab);

//     if ($project['Anzahlung'] > 0) {
//       // only include if configured in project
//       $opts['fdd']['Anzahlung'] = Config::$opts['money'];
//       $opts['fdd']['Anzahlung']['name'] = "Anzahlung";
//       $opts['fdd']['Anzahlung']['default'] = $project['Anzahlung'];
//       $opts['fdd']['Anzahlung']['css']['postfix'] .= ' deposit';
//       $opts['fdd']['Anzahlung']['tab'] = array('id' => $financeTab);
//     }

//     $needDebitMandates = Projects::needDebitMandates($projectId);
//     $paymentStatusValues2 = array(
//       'outstanding' => '&empty;',
//       'awaitingdepositdebit' => '&#9972;',
//       'deposited' => '&#9684;',
//       'awaitingdebit' => '&#9951;',
//       'payed' => '&#10004;'
//       );

//     if (Projects::needDebitMandates($projectId)) {

//       $memberTableId = Config::getValue('memberTableId');
//       $monetary = ProjectExtra::monetaryFields($userExtraFields, $fieldTypes);

//       $amountPaidIdx = count($opts['fdd']);
//       $opts['fdd']['AmountPaid'] = array(
//         'input' => 'HR',
//         );

//       $paidCurrentYearIdx = count($opts['fdd']);
//       $opts['fdd']['PaidCurrentYear'] = array(
//         'input' => 'HR',
//         );

//       $opts['fdd']['TotalProjectFees'] = array(
//         'tab'      => array('id' => $financeTab),
//         'name'     => $this->l->t('Total Charges'),
//         'css'      => array('postfix' => ' total-project-fees money'),
//         'sort'    => false,
//         'options' => 'VDLF', // wrong in change mode
//         'input' => 'VR',
//         'sql' => '`PMEtable0`.`Unkostenbeitrag`',
//         'php' => function($amount, $op, $field, $fds, $fdd, $row, $recordId)
//         use ($monetary, $amountPaidIdx, $paidCurrentYearIdx, $projectId, $memberTableId, $musIdIdx)
//         {
//           foreach($fds as $key => $label) {
//             if (!isset($monetary[$label])) {
//               continue;
//             }
//             $qf    = "qf{$key}";
//             $qfidx = $qf.'_idx';
//             if (isset($row[$qfidx])) {
//               $value = $row[$qfidx];
//             } else {
//               $value = $row[$qf];
//             }
//             if (empty($value)) {
//               continue;
//             }
//             $field   = $monetary[$label];
//             $allowed = $field['AllowedValues'];
//             $type    = $field['Type'];
//             $amount += self::extraFieldSurcharge($value, $allowed, $type['Multiplicity']);
//           }

//           if ($projectId === $memberTableId) {
//             $amount += InstrumentInsurance::annualFee($row['qf'.$musIdIdx]);
//             $paid = $row['qf'.$paidCurrentYearIdx];
//           } else {
//             $paid = $row['qf'.$amountPaidIdx];
//           }

//           // display as TOTAL/PAID/REMAINDER
//           $rest = $amount - $paid;

//           $amount = Util::moneyValue($amount);
//           $paid = Util::moneyValue($paid);
//           $rest = Util::moneyValue($rest);
//           return ('<span class="totals finance-state">'.$amount.'</span>'
//                   .'<span class="received finance-state">'.$paid.'</span>'
//                   .'<span class="outstanding finance-state">'.$rest.'</span>');
//         },
//         'tooltip'  => Config::toolTips('project-total-fee-summary'),
//         'display|LFVD' => array('popup' => 'tooltip'),
//         );

//       $opts['fdd']['Lastschrift'] = array(
//         'tab'      => array('id' => $financeTab),
//         'name'     => $this->l->t('Direct Debit'),
//         'css'      => array('postfix' => ' direct-debit-allowed'),
//         'values2|CAP' => array('1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /*'&#10004;'*/),
//         'values2|LVDF' => array('0' => '&nbsp;',
//                                 '1' => '&#10004;'),
//         'escape' => false,
//         //'values2|CAP' => array(1 => ''),
//         //'values2|LVFD' => array(1 => $this->l->t('true'), 0 => $this->l->t('false')),
//         'default'  => '',
//         'select'   => 'O',
//         'sort'     => true,
//         'tooltip'  => Config::toolTips('project-direct-debit-allowed'),
//         'display|LF' => array('popup' => 'tooltip'),
//         );

//       $debitJoinCondition =
//         '('.
//         '$join_table.projectId = '.$projectId.
//         ' OR '.
//         '$join_table.projectId = '.$memberTableId.
//         ')'.
//         ' AND $join_table.musicianId = $main_table.MusikerId'.
//         ' AND $join_table.active = 1';

//       // One virtual field in order to be able to manage SEPA debit
//       // mandates. Note that in rare circumstances there may be two
//       // debit mandates: one for general and one for the project. We
//       // fetch both with the same sort-order and leave it to the calling
//       // code to do THE RIGHT THING (tm).
//       $mandateIdx = count($opts['fdd']);
//       $mandateAlias = "`PMEjoin".$mandateIdx."`";
//       $opts['fdd']['SepaDebitMandate'] = array(
//         'name' => $this->l->t('SEPA Debit Mandate'),
//         'input' => 'VR',
//         'tab' => array('id' => $financeTab),
//         'select' => 'M',
//         'options' => 'LFACPDV',
//         'sql' => "GROUP_CONCAT(DISTINCT ".$mandateAlias.".`mandateReference`
//   ORDER BY ".$mandateAlias.".`projectId` DESC)",
//         'values' => array(
//           'table' => 'SepaDebitMandates',
//           'column' => 'mandateReference',
//           'join' => $debitJoinCondition,
//           'description' => 'mandateReference'
//           ),
//         'nowrap' => true,
//         'sort' => true,
//         'php' => function($mandates, $action, $k, $fds, $fdd, $row, $recordId)
//         use ($musIdIdx, $musFirstNameIdx, $musLastNameIdx)
//         {
//           if ($this->pme_bare) {
//             return $mandates;
//           }
//           $projectId = $this->projectId;
//           $projectName = $this->projectName;
//           // can be multi-valued (i.e.: 2 for member table and project table)
//           $mandateProjects = $row['qf'.($k+1)];
//           $mandates = Util::explode(',', $mandates);
//           $mandateProjects = Util::explode(',', $mandateProjects);
//           if (count($mandates) !== count($mandateProjects)) {
//             throw new \RuntimeException(
//               $this->l->t('Data inconsistency, mandates: "%s", projects: "%s"',
//                    array(implode(',', $mandates),
//                          implode(',', $mandateProjects)))
//               );
//           }

//           // Careful: this changes when rearranging the sort-order of the display
//           $musicianId        = $row['qf'.$musIdIdx];
//           $musicianFirstName = $row['qf'.$musFirstNameIdx];
//           $musicianLastName  = $row['qf'.$musLastNameIdx];
//           $musician = $musicianLastName.', '.$musicianFirstName;

//           $html = [];
//           foreach($mandates as $key => $mandate) {
//             if (empty($mandate)) {
//               continue;
//             }
//             $expired = Finance::mandateIsExpired($mandate);
//             $mandateProject = $mandateProjects[$key];
//             if ($mandateProject === $projectId) {
//               $html[] = self::sepaDebitMandateButton(
//                 $mandate, $expired,
//                 $musicianId, $musician,
//                 $projectId, $projectName);
//             } else {
//               $mandateProjectName = Projects::fetchName($mandateProject);
//               $html[] = self::sepaDebitMandateButton(
//                 $mandate, $expired,
//                 $musicianId, $musician,
//                 $projectId, $projectName,
//                 $mandateProject, $mandateProjectName);
//             }
//           }
//           if (empty($html)) {
//             // Empty default knob
//             $html = array(self::sepaDebitMandateButton(
//                             $this->l->t("SEPA Debit Mandate"), false,
//                             $musicianId, $musician,
//                             $projectId, $projectName));
//           }
//           return implode("\n", $html);
//         },
//         );

//       $mandateProjectIdx = count($opts['fdd']);
//       $opts['fdd']['DebitMandateProject'] = array(
//         'input' => 'VHR',
//         'name' => 'internal data',
//         'options' => 'H',
//         'select' => 'T',
//         'sql' => "GROUP_CONCAT(DISTINCT ".$mandateAlias.".`projectId`
//   ORDER BY ".$mandateAlias.".`projectId` DESC)",
//         );
//     }

//     $opts['triggers']['update']['before'] = [];
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeAnythingTrimAnything';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeUpdateTrigger';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

//     // that one has to be adjusted further ...
//     $opts['triggers']['delete']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeDeleteTrigger';

//     // fill the numbers table
//     $opts['triggers']['filter']['pre'][]  =
//       $opts['triggers']['update']['pre'][]  =
//       $opts['triggers']['insert']['pre'][]  = 'CAFEVDB\ProjectExtra::preTrigger';

//     //$opts['triggers']['select']['data'][] =
//     $opts['triggers']['update']['data'][] =
//       function(&$pme, $op, $step, &$row) {
//       $prInstIdx        = $pme->fdn['ProjectInstrumentId'];
//       $voiceIdx         = $pme->fdn['Voice'];
//       $sectionLeaderIdx = $pme->fdn['SectionLeader'];
//       $instruments = Util::explode(',', $row["qf{$prInstIdx}_idx"]);
//       //error_log('data '.print_r($row, true));
//       switch (count($instruments)) {
//       case 0:
//         $pme->fdd[$voiceIdx]['input'] = 'R';
//         $pme->fdd[$sectionLeaderIdx]['input'] = 'R';
//         break;
//       case 1:
//         unset($pme->fdd[$voiceIdx]['values']['groups']);
//         //error_log('data '.print_r($pme->fdd[$voiceIdx], true));
//         $pme->fdd[$voiceIdx]['select'] = 'D';
//         break;
//       default:
//         break;
//       }
//       return true;
//     };

//     if ($this->pme_bare) {
//       // disable all navigation buttons, probably for html export
//       $opts['navigation'] = 'N'; // no navigation
//       $opts['options'] = '';
//       // Don't display special page elements
//       $opts['display'] =  array_merge($opts['display'],
//                                       array(
//                                         'form'  => false,
//                                         'query' => false,
//                                         'sort'  => false,
//                                         'time'  => false,
//                                         'tabs'  => false,
//                                         ));
//       // Disable sorting buttons
//       foreach ($opts['fdd'] as $key => $value) {
//         $opts['fdd'][$key]['sort'] = false;
//       }
//     }

//     // Inject the underlying table name as 'querygroup' parameter
//     // s.t. update queries can be split into several queries which
//     // only target one of the underlying tables.
//     $viewStructure = Projects::viewStructure($projectId, $userExtraFields);
//     //print_r($viewStructure);
//     foreach($opts['fdd'] as $name => &$data) {
//       if (isset($viewStructure[$name])) {
//         $joinField = $viewStructure[$name];
//         $table = $joinField['table'];
//         $tablename = $joinField['tablename'];
//         $key = isset($joinField['key']) ? $joinField['key'] : false;
//         if (isset($joinField['update'])) {
//           $column = $joinField['update'];
//         } else if ($joinField['column'] === true) {
//           $column = $name;
//         } else {
//           $column = $joinField['column'];
//         }
//         $data['querygroup'] = array(
//           'table' => $table,
//           'tablename' => $tablename,
//           'column' => $column,
//           'key' => $key
//           );
//       }
//     }

    ///@@@@@@@@@@@@@@@@@@@@@

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  private function tableTabId($idOrName)
  {
    $dflt = $this->defaultTableTabs();
    foreach($dflt as $tab) {
      if ($idOrName === $tab['name']) {
        return $idOrName;
      }
    }
    return $idOrName;
  }

  /**
   * Export the default tabs family.
   */
  private function defaultTableTabs($useFinanceTab = false)
  {
    $pre = [
      [
        'id' => 'instrumentation',
        'default' => true,
        'tooltip' => $this->toolTipsService['project-instrumentation-tab'],
        'name' => $this->l->t('Instrumentation related data'),
      ],
      [
        'id' => 'project',
        'tooltip' => $this->toolTipsService['project-metadata-tab'],
        'name' => $this->l->t('Project related data'),
      ],
    ];
    $finance = [
      [
        'id' => 'finance',
        'tooltip' => $this->toolTipsService['project-finance-tab'],
        'name' => $this->l->t('Finance related data'),
      ],
    ];
    $post = [
      [
        'id' => 'musician',
        'tooltip' => $this->toolTipsService['project-personaldata-tab'],
        'name' => $this->l->t('Personal data'),
      ],
      [
        'id' => 'miscinfo',
        'tooltip' => $this->toolTipsService['project-personalmisc-tab'],
        'name' => $this->l->t('Miscinfo'),
      ],
      [
        'id' => 'tab-all',
        'tooltip' => $this->toolTipsService['pme-showall-tab'],
        'name' => $this->l->t('Display all columns'),
      ],
    ];
    if ($useFinanceTab) {
      return array_merge($pre, $finance, $post);
    } else {
      return array_merge($pre, $post);
    }
  }

  /**
   * Export the description for the table tabs.
   */
  private function tableTabs($extraFields = false, $useFinanceTab = false)
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_array($extraFields)) {
      return $dfltTabs;
    }

    $extraTabs = [];
    foreach($extraFields as $field) {
      if (empty($field['Tab'])) {
        continue;
      }

      $extraTab = $field['Tab'];
      foreach($dfltTabs as $tab) {
        if ($extraTab === $tab['id'] ||
            $extraTab === (string)$tab['name']) {
          $extraTab = false;
          break;
        }
      }
      if ($extraTab !== false) {
        $extraTabs[] = [
          'id' => $extraTab,
          'name' => $this->l->t($extraTab),
          'tooltip' => $this->toolTipsService['extra-fields-extra-tab'],
        ];
      }
    }

    return array_merge($dfltTabs, $extraTabs);
  }

}
