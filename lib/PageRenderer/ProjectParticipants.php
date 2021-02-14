<?php
/**
 * Orchestra member, musician and project management application.
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
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\FinanceService;
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
  const PROJECT_PAYMENTS_TABLE = 'ProjectPayments';
  const EXTRA_FIELDS_TABLE = 'ProjectExtraFields';
  const EXTRA_FIELDS_DATA_TABLE = 'ProjectExtraFieldsData';
  const SEPA_DEBIT_MANDATES_TABLE = 'SepaDebitMandates';
  const FIXED_COLUMN_SEP = '@';

  /** @var int */
  private $memberProjectId;

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
    [
      'table' => self::PROJECT_PAYMENTS_TABLE,
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
      ],
      'column' => 'id',
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
    // Defined dynamically in render():
    // SepaDebitMandates
    // [
    //   'table' => self::SEPA_DEBIT_MANDATES_TABLE,
    //   'entity' => Entities\SepaDebitMandate::class,
    //   'identifier' => [
    //     'musician_id' => 'musician_id',
    //     'project_id' => [
    //       'condition' => 'IN ($main_table.project_id, )',
    //     ],
    //     'deleted_at' => [ 'value' => null ],
    //   ],
    //   'column' => 'sequence',
    // ],
  ];

  /** @var \OCA\CAFEVDB\Service\GeoCodingService */
  private $geoCodingService;

  /** @var \OCA\CAFEVDB\Service\PhoneNumberService */
  private $phoneNumberService;

  /** @var \OCA\CAFEVDB\Service\FinanceService */
  private $financeService;

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
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , GeoCodingService $geoCodingService
    , ContactsService $contactsService
    , PhoneNumberService $phoneNumberService
    , FinanceService $financeService
    , ProjectExtraFieldsService $extraFieldsService
    , Musicians $musiciansRenderer
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->financeService = $financeService;
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

  /**
   * Show the underlying table.
   *
   * @todo Much of this is really CTOR stuff.
   */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;
    $memberProjectId = $this->getConfigValue('memberProjectId', -1);

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
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'sur_name'),
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

    /* Tweak the join-structure with dynamic data
     */
    $this->joinStructure[] = [
      // SepaDebitMandates
      'table' => self::SEPA_DEBIT_MANDATES_TABLE,
      'entity' => Entities\SepaDebitMandate::class,
      'identifier' => [
        'musician_id' => 'musician_id',
        'project_id' => [
          'condition' => 'IN ($main_table.project_id, '.$memberProjectId.')',
        ],
        'deleted_at' => [ 'value' => null ],
        'sequence' => false,
      ],
      'column' => 'sequence',
    ];

    /* For each extra field add one dedicated join table entry
     * which is pinned to the respective field-id.
     */
    $extraFieldJoinIndex = [];
    foreach ($extraFields as $field) {
      $fieldId = $field['id'];
      $tableName = self::EXTRA_FIELDS_DATA_TABLE.self::FIXED_COLUMN_SEP.$fieldId;
      $extraFieldJoinTable = [
        'table' => $tableName,
        'entity' => Entities\ProjectExtraFieldDatum::class,
        'nullable' => true,
        'identifier' => [
          'project_id' => 'project_id',
          'musician_id' => 'musician_id',
          'field_id' => [ 'value' => $field['id'], ],
        ],
        'column' => 'field_id',
      ];
      $extraFieldJoinIndex[$tableName] = count($this->joinStructure);
      $this->joinStructure[] = $extraFieldJoinTable;
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
      $opts['fdd'], self::MUSICIANS_TABLE, 'sur_name',
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
        'sql'      => 'IFNULL($main_table.$field_name, 0)',
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
     * member-status from the musicians table
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

    $monetary = $this->extraFieldsService->monetaryFields($this->project);

    if (!empty($monetary) || ($projectId == $this->memberProjectId)) {

      $this->makeJoinTableField(
        $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount',
        [
          'tab'      => array('id' => $financeTab),
          'name'     => $this->l->t('Total Charges'),
          'css'      => [ 'postfix' => ' total-project-fees money' ],
          'sort'    => false,
          'options' => 'VDLF', // wrong in change mode
          'input' => 'VR',
          'sql' => 'IFNULL(SUM($join_col_fqn), 0.0)',
          'php' => function($amountPaid, $op, $k, $row, $recordId, $pme) use ($monetary) {
            $project_id = $recordId['project_id'];
            $musicianId = $recordId['musician_id'];

            $amountInvoiced = 0.0;
            foreach ($monetary as $fieldId => $extraField) {

              $table = self::EXTRA_FIELDS_DATA_TABLE.self::FIXED_COLUMN_SEP.$fieldId;
              $label = $this->joinTableFieldName($table, 'field_value');

              if (!isset($pme->fdn[$label])) {
                throw new \Exception($this->l->t('Data for monetary field "%s" not found', $label));
              }

              $rowIndex = $pme->fdn[$label];
              $qf = 'qf'.$rowIndex;
              $qfIdx = $qf.'_idx';
              if (isset($row[$qfidx])) {
                $value = $row[$qfIdx];
              } else {
                $value = $row[$qf];
              }
              if (empty($value)) {
                continue;
              }
              $allowed = $extraField['allowedValues'];
              $multiplicity = $extraField['multiplicity'];
              $amountInvoiced += $this->extraFieldsService->extraFieldSurcharge($value, $allowed, $multiplicity);
            }

            if ($projectId == $this->memberProjectId) {
              // $amountInvoiced += InstrumentInsurance::annualFee($row['qf'.$musIdIdx]);
            }

            // display as TOTAL/PAID/REMAINDER
            $rest = $amountInvoiced - $amountPaid;

            $amountInvoiced = $this->moneyValue($amountInvoiced);
            $amountPaid = $this->moneyValue($amountPaid);
            $rest = $this->moneyValue($rest);
            return ('<span class="totals finance-state">'.$amountInvoiced.'</span>'
                    .'<span class="received finance-state">'.$amountPaid.'</span>'
                    .'<span class="outstanding finance-state">'.$rest.'</span>');
          },
        'tooltip'  => $this->toolTipsService['project-total-fee-summary'],
        'display|LFVD' => [ 'popup' => 'tooltip' ],
        ]);

    } // have monetary fields

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
      $multiplicity = $field['multiplicity'];
      $dataType = $field['data_type'];

      if (!$this->extraFieldsService->isSupportedType($multiplicity, $dataType)) {
        throw new \Exception(
          $this->l->t('Unsupported multiplicity / data-type combination: %s / %s',
                      [ $multiplicity, $dataType ]));
      }

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

      $tableName = self::EXTRA_FIELDS_DATA_TABLE.self::FIXED_COLUMN_SEP.$fieldId;

      $css = [ 'extra-field', 'field-id-'.$fieldId, ];
      list($curColIdx, $fddName) = $this->makeJoinTableField(
        $opts['fdd'], $tableName, 'field_value',
        [
          'name' => $this->l->t($fieldName),
          'tab' => $tab,
          'css'      => [ 'postfix' => ' '.implode(' ', $css), ],
          'default'  => $field['default_value'],
          'values' => [
            'column' => 'field_value',
            'filters' => ('$table.field_id = '.$fieldId
                          .' AND $table.project_id = '.$projectId
                          .' AND $table.musician_id = $record_id[musician_id]'),
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

    switch ($dataType) {
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
      case 'boolean':
        // handled below
        $fdd['align'] = 'right';
        break;
      case 'integer':
        $fdd['select'] = 'N';
        $fdd['mask'] = '%d';
        $fdd['align'] = 'right';
        break;
      case 'float':
        $fdd['select'] = 'N';
        $fdd['mask'] = '%g';
        $fdd['align'] = 'right';
        break;
      case 'date':
      case 'datetime':
      case 'money':
      case 'service-fee':
      case 'deposit':
        if ($dataType == 'service-fee' || $dataType == 'deposit') {
          $dataType = 'money';
        }
        $style = $this->defaultFDD[$dataType];
        if (empty($style)) {
          throw \Exception($this->l->t('Not default style for "%s" available.', $dataType));
        }
        unset($style['name']);
        $fdd = array_merge($fdd, $style);
        $fdd['css']['postfix'] .= ' '.implode(' ', $css);
        break;
      }

      switch ($multiplicity) {
      case 'simple':
        break;
      case 'single':
        reset($values2); $key = key($values2);
        $fdd['values2|CAP'] = [ $key => '' ]; // empty label for simple checkbox
        $fdd['values2|LVDF'] = [
          0 => $this->l->t('false'),
          $key => $this->l->t('true'),
        ];
        $fdd['select'] = 'C';
        $fdd['default'] = (string)!!(int)$field['default_value'];
        $fdd['css']['postfix'] .= ' boolean single-valued '.$dataType;
        switch ($dataType) {
        case 'boolean':
          break;
        case 'money':
        case 'service-fee':
          $money = $this->moneyValue(reset($valueData));
          $noMoney = $this->moneyValue(0);
          // just use the amount to pay as label
          $fdd['values2|LVDF'] = [
            '' => '-,--',
            0 => $noMoney, //'-,--',
            $key => $money
          ];
          $fdd['values2|CAP'] = [ $key => $money, ];
          break;
        default:
          $fdd['values2|CAP'] = [ $key => reset($valueData) ];
          break;
        } // data-type switch
        break;
      case 'parallel':
      case 'multiple':
        switch ($dataType) {
        case 'service-fee':
          foreach ($allowed as $option) {
            $key = $option['key'];
            $label = $option['label'];
            $data  = $option['data'];
            $values2[$key] = $this->allowedOptionLabel($label, $data, $dataType, 'money');
          }
          $fdd['values2glue'] = "<br/>";
          $fdd['escape'] = false;
          // fall through
        default:
          $fdd['values2'] = $values2;
          $fdd['valueTitles'] = $valueTitles;
          $fdd['valueData'] = $valueData;
          if ($multiplicity == 'parallel') {
            $fdd['css']['postfix'] .= ' set hide-subsequent-lines';
            $fdd['select'] = 'M';
          } else {
            $fdd['css']['postfix'] .= ' enumeration allow-empty';
            $fdd['select'] = 'D';
          }
          $fdd['css']['postfix'] .= ' '.$dataType;
          $fdd['display|LF'] = [ 'popup' => 'data' ];
          break;
        }
        break;
      case 'groupofpeople':
        // old field, group selection
        $fdd = array_merge(
          $fdd,
          [
            'mask' => null,
          ]);

        // generate a new group-definition field as yet another column
        list($curColIdx, $fddName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'musician_id', $fdd);

    // hide value field and tweak for view displays.
        $css[] = 'groupofpeople';
        $css[] = 'single-valued';
        $fdd = Util::arrayMergeRecursive(
          $fdd,
          [
            'css' => [ 'postfix' => ' '.implode(' ', $css).' groupofpeople-id', ],
            'input' => 'VSRH',
// not needed anymore
//             'sql|LVFD' => "GROUP_CONCAT(DISTINCT \$join_col_fqn ORDER BY \$order_by SEPARATOR ', ')",
//             'values|LFDV' => [
//               'table' => "SELECT
//   m.id AS musician_id,
//   CONCAT_WS(' ', m.first_name, m.sur_name) AS name,
//   m.sur_name AS sur_name,
//   m.first_name AS first_name,
//   fd.field_value AS group_id
// FROM ProjectParticipants pp
// LEFT JOIN Musicians AS m
//   ON m.id = pp.musician_id
// LEFT JOIN ProjectExtraFieldsData fd
//   ON fd.musician_id = pp.musician_id AND fd.project_id = pp.project_id
// WHERE pp.project_id = $projectId AND fd.field_id = $fieldId",
//               'column' => 'name',
//               'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
//               'join' => '$join_table.group_id = '.$joinTables[$tableName].'.field_value',
//             ],
          ]);

        // new field, member selection
        $fdd = &$opts['fdd'][$fddName];

        // tweak the join-structure entry for the group field
        $joinInfo = &$this->joinStructure[$extraFieldJoinIndex[$tableName]];
        $joinInfo = array_merge(
          $joinInfo,
          [
            'identifier' => [
              'project_id' => 'project_id',
              'musician_id' => false,
              'field_id' => [ 'value' => $fieldId, ],
            ],
            'column' => 'musician_id',
          ]);

        // define the group stuff
        $max = $allowed[0]['limit'];
        $fdd = array_merge(
          $fdd, [
            'select' => 'M',
            'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn)',
            'display' => [ 'popup' => 'data' ],
            'colattrs' => [ 'data-groups' => json_encode([ 'limit' => $max ]), ],
            'filter' => 'having',
            'values' => [
              'table' => "SELECT
   m1.id AS musician_id,
   CONCAT_WS(' ', m1.first_name, m1.sur_name) AS name,
   m1.sur_name AS sur_name,
   m1.first_name AS first_name,
   fd.field_value AS group_id,
   fdg.group_number AS group_Number
FROM ProjectParticipants pp
LEFT JOIN Musicians m1
  ON m1.id = pp.musician_id
LEFT JOIN ProjectExtraFieldsData fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = $projectId AND fd.field_id = $fieldId
LEFT JOIN (SELECT
    fd2.field_value AS group_id,
    ROW_NUMBER() OVER (ORDER BY fd2.field_id) AS group_number
    FROM ProjectExtraFieldsData fd2
    WHERE fd2.project_id = $projectId AND fd2.field_id = $fieldId
    GROUP BY fd2.field_value
  ) fdg
  ON fdg.group_id = fd.field_value
WHERE pp.project_id = $projectId",
              'column' => 'musician_id',
              'description' => 'name',
              //'groups' => "CONCAT_WS(' ', '".$fieldName."',\$table.group_number,\$table.group_id)",
              'groups' => "CONCAT_WS(' ', '".$fieldName."',\$table.group_number)",
              'data' => "CONCAT('{\"limit\":".$max.",\"groupId\":\"',IFNULL(\$table.group_id,-1),'\"}')",
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.field_value',
            ],
            'valueGroups' => [ -1 => $this->l->t('without group') ],
          ]);

        $fdd['css']['postfix'] .= ' '.implode(' ', $css);

        if ($dataType == 'service-fee') {
          $fdd['css']['postfix'] .= ' service-fee';
          $money = $this->moneyValue(reset($valueData));
          $fdd['name|LFVD'] = $fdd['name'];
          $fdd['name'] = $this->allowedOptionLabel($fdd['name'], reset($valueData), $dataType, 'money');
          $fdd['display|LFVD'] = array_merge(
            $fdd['display'],
            [
              'prefix' => '<span class="allowed-option-name money clip-long-text group">',
              'postfix' => ('</span><span class="allowed-option-separator money">&nbsp;</span>'
                            .'<span class="allowed-option-value money">'.$money.'</span>'),
            ]);
        }

        // in filter mode mask out all non-group-members
        $fdd['values|LF'] = array_merge(
          $fdd['values'],
          [ 'filters' => '$table.group_id IS NOT NULL' ]);

        break;
      case 'groupsofpeople':
        // tweak the join-structure entry for the group field
        $joinInfo = &$this->joinStructure[$extraFieldJoinIndex[$tableName]];
        $joinInfo = array_merge(
          $joinInfo,
          [
            'identifier' => [
              'project_id' => 'project_id',
              'musician_id' => false,
              'field_id' => [ 'value' => $fieldId, ],
            ],
            'column' => 'musician_id',
          ]);

        // define the group stuff
        $groupValues2   = $values2;
        $groupValueData = $valueData;
        $values2 = [];
        $valueGroups = [ -1 => $this->l->t('without group') ];
        $idx = -1;
        foreach($allowed as $key => $value) {
          $valueGroups[--$idx] = $value['label'];
          $data = $value['data'];
          if ($dataType == 'service-fee') {
            $data = $this->moneyValue($data);
          }
          if (!empty($data)) {
            $valueGroups[$idx] .= ':&nbsp;' . $data;
          }
          $values2[$idx] = $this->l->t('add to this group');
          $valueData[$idx] = json_encode([ 'groupId' => $value['key'] ]);
        }

        // make the field a select box for the predefined groups, like
        // for the "multiple" stuff.

        $css[] = 'groupofpeople';
        $css[] = 'predefined';
        if ($dataType === 'service-fee') {
          $css[] = 'service-fee';
          foreach($groupValues2 as $key => $value) {
            $groupValues2[$key] = $this->allowedOptionLabel(
              $value, $groupValueData[$key], $dataType, 'money group clip-long-text');
          }
        }

        // old field, group selection
        $fdd = array_merge(
          $fdd,
          [
            //'name' => $this->l->t('%s Group', $fieldName),
            'css'         => [ 'postfix' => ' '.implode(' ', $css) ],
            'select'      => 'D',
            'values2'     => $groupValues2,
            'display'     => [ 'popup' => 'data' ],
            'sort'        => true,
            'escape'      => false,
            'mask' => null,
          ]);

        // generate a new group-definition field as yet another column
        list($curColIdx, $fddName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'musician_id', $fdd);

        // hide value field and tweak for view displays.
        $fdd = Util::arrayMergeRecursive(
          $fdd,
          [
            'input' => 'VSRH',
            'css'   => [ 'postfix' => ' '.implode(' ', $css).' groupofpeople-id' ],
            'sql|LVFD' => "GROUP_CONCAT(DISTINCT \$join_col_fqn ORDER BY \$order_by SEPARATOR ', ')",
            'values|LFDV' => [
              'table' => "SELECT
  m2.id AS musician_id,
  CONCAT_WS(' ', m2.first_name, m2.sur_name) AS name,
  m2.sur_name AS sur_name,
  m2.first_name AS first_name,
  fd.field_value AS group_id
FROM ProjectParticipants pp
LEFT JOIN Musicians m2
  ON m2.id = pp.musician_id
LEFT JOIN ProjectExtraFieldsData fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = pp.project_id
WHERE pp.project_id = $projectId AND fd.field_id = $fieldId",
              'column' => 'name',
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.field_value',
            ],
          ]);

        // new field, member selection
        $fdd = &$opts['fdd'][$fddName];

        $fdd = Util::arrayMergeRecursive(
          $fdd, [
            'select' => 'M',
            'sql|ACP' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id)',
            //'sql' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id)',
            //'display' => [ 'popup' => 'data' ], // @todo
            'colattrs' => [ 'data-groups' => json_encode($allowed), ],
            'values|ACP' => [
              'table' => "SELECT
  m3.id AS musician_id,
  CONCAT_WS(' ', m3.first_name, m3.sur_name) AS name,
  m3.sur_name AS sur_name,
  m3.first_name AS first_name,
  fd.field_value AS group_id,
  JSON_VALUE(ef.allowed_values, REPLACE(JSON_UNQUOTE(JSON_SEARCH(ef.allowed_values, 'one', fd.field_value)), 'key', 'label')) AS group_label,
  JSON_VALUE(ef.allowed_values, REPLACE(JSON_UNQUOTE(JSON_SEARCH(ef.allowed_values, 'one', fd.field_value)), 'key', 'data')) AS group_data
FROM ProjectParticipants pp
LEFT JOIN Musicians m3
  ON m3.id = pp.musician_id
LEFT JOIN ProjectExtraFieldsData fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = $projectId AND fd.field_id = $fieldId
LEFT JOIN ProjectExtraFields ef
  ON ef.project_id = $projectId AND ef.id = fd.field_id
WHERE pp.project_id = $projectId",
              'column' => 'musician_id',
              'description' => 'name',
              'groups' => "CONCAT(\$table.group_label, ': ', \$table.group_data)",
              'data' => "CONCAT('{\"groupId\":\"',IFNULL(\$table.group_id, -1),'\"}')",
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.field_value',
              //'titles' => '$table.name',
            ],
            'valueGroups|ACP' => $valueGroups,
            'valueData|ACP' => $valueData,
            'values2|ACP' => $values2,
            'mask' => null,
            'display|LDV' => [
              'popup' => 'data:previous',
            ],
            'display|ACP' => [
              'prefix' => function($op, $pos, $row, $k, $pme) use ($css) {
                return '<label class="'.implode(' ', $css).'">';
              },
              'postfix' => function($op, $pos, $row, $k, $pme) use ($allowed, $dataType) {
                $html = '';
                foreach ($allowed as $idx => $option) {
                  $key = $option['key'];
                  $active = $row['qf'.($k-1)] == $key ? 'selected' : null;
                  $html .= $this->allowedOptionLabel(
                    $option['label'], $option['data'], $dataType, $active, [ 'key' => $option['key'], ]);
                }
                $html .= '</label>';
                return $html;
              },
            ],
          ]);

        $fdd['css']['postfix'] .= ' clip-long-text';
        $fdd['css|LFVD']['postfix'] = $fdd['css']['postfix'].' view';

        break;
      }

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
      // @todo check whether this is still needed or 'groups' => 'year' is just fine.
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
      'sql' => '`PMEtable0`.`musician_id`', // @todo: needed?
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        $stampIdx = array_search('Updated', $pme->fds);
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
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        switch($action) {
        case 'change':
        case 'display':
          $data = [];
          foreach($pme->fds as $idx => $label) {
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

    ///////////////////////////////////////////////////////////////////////////

    // One virtual field in order to be able to manage SEPA debit
    // mandates. Note that in rare circumstances there may be two
    // debit mandates: one for general and one for the project. We
    // fetch both with the same sort-order and leave it to the calling
    // code to do THE RIGHT THING (tm).

    //       $mandateIdx = count($opts['fdd']);
    //       $mandateAlias = "`PMEjoin".$mandateIdx."`";
    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference',
      array_merge([
        'name' => $this->l->t('SEPA Debit Mandate'),
        'input' => 'VR',
        'tab' => array('id' => $financeTab),
        'select' => 'M',
        'options' => 'LFACPDV',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_table.project_id DESC)',
        'nowrap' => true,
        'sort' => true,
        'php' => function($mandates, $action, $k, $row, $recordId, $pme) {
           if ($this->pme_bare) {
             return $mandates;
           }
           $projectId = $this->projectId;
           $projectName = $this->projectName;

           if ($projectId != $recordId['project_id']) {
             throw new \Exception($this->l->t('Inconsistent project-ids: %s / %s', [ $projectId, $recordId['project_id'] ]));
           }

           // can be multi-valued (i.e.: 2 for member table and project table)
           $mandateProjects = $row['qf'.($k+1)];
           $mandates = Util::explode(',', $mandates);
           $mandateProjects = Util::explode(',', $mandateProjects);
           if (count($mandates) !== count($mandateProjects)) {
             throw new \RuntimeException(
               $this->l->t('Data inconsistency, mandates: "%s", projects: "%s"',
                           [ implode(',', $mandates),
                             implode(',', $mandateProjects) ])
             );
           }

           // Careful: this changes when rearranging the sort-order of the display
           $musicianId        = $row[$this->queryField('musician_id', $pme->fdd)];
           $musicianFirstName = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'first_name', $pme->fdd)];
           $musicianSurName  = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'sur_name', $pme->fdd)];
           $musician = $musicianSurName.', '.$musicianFirstName;

           $html = [];
           foreach($mandates as $key => $mandate) {
             if (empty($mandate)) {
               continue;
             }
             $expired = $this->financeService->mandateIsExpired($mandate);
             $mandateProject = $mandateProjects[$key];
             if ($mandateProject === $projectId) {
               $html[] = $this->sepaDebitMandateButton(
                $mandate, $expired,
                $musicianId, $musician,
                $projectId, $projectName);
            } else {
              $mandateProjectName = Projects::fetchName($mandateProject);
              $html[] = $this->sepaDebitMandateButton(
                $mandate, $expired,
                $musicianId, $musician,
                $projectId, $projectName,
                $mandateProject, $mandateProjectName);
            }
          }
          if (empty($html)) {
            // Empty default knob
            $html = [
              $this->sepaDebitMandateButton(
                $this->l->t("SEPA Debit Mandate"), false,
                $musicianId, $musician,
                $projectId, $projectName),
            ];
          }
          return implode("\n", $html);
        },
      ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'project_id',
      array_merge([
        'input' => 'VHR',
        'name' => 'internal data',
        'options' => 'H',
        'select' => 'T',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_col_fqn DESC)',
      ]));

    //////// END Field definitions

    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateSanitizeExtraFields' ];
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
//         'php' => function($amount, $op, $field, $row, $recordId, $pme)
//         use ($monetary, $amountPaidIdx, $paidCurrentYearIdx, $projectId, $memberTableId, $musIdIdx)
//         {
//           foreach($pme->fds as $key => $label) {
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

//           $amount = $this->moneyValue($amount);
//           $paid = $this->moneyValue($paid);
//           $rest = $this->moneyValue($rest);
//           return ('<span class="totals finance-state">'.$amount.'</span>'
//                   .'<span class="received finance-state">'.$paid.'</span>'
//                   .'<span class="outstanding finance-state">'.$rest.'</span>');
//         },
//         'tooltip'  => Config::toolTips('project-total-fee-summary'),
//         'display|LFVD' => array('popup' => 'tooltip'),
//         );

//     }

//     $opts['triggers']['update']['before'] = [];
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

    ///@@@@@@@@@@@@@@@@@@@@@

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * phpMyEdit calls the trigger (callback) with
   * the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldValues Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newValues Set of new values, which may also be modified.
   *
   * @return boolean  If returning @c false the operation will be terminated
   *
   * @bug Too long, just split into multiple "triggers" or call subroutines.
   */
  public function beforeUpdateSanitizeExtraFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->logDebug('OLDVALUES '.print_r($oldValues, true));
    $this->logDebug('NEWVALUES '.print_r($newValues, true));
    $this->logDebug('CHANGED '.print_r($changed, true));

    foreach ($this->project['extra_fields'] as $extraField) {
      $fieldId = $extraField['id'];
      $multiplicity = $extraField['multiplicity'];
      $dataType = $extraField['dataType'];

      $tableName = self::EXTRA_FIELDS_DATA_TABLE.self::FIXED_COLUMN_SEP.$fieldId;

      $fieldName = $this->joinTableFieldName($tableName, 'field_value');
      $groupFieldName = $this->joinTableFieldName($tableName, 'musician_id');

      $this->logDebug('FIELDNAMES '.$fieldName." / ".$groupFieldName);

      $this->logDebug("MULT ".$multiplicity);
      switch ($multiplicity) {
      case 'groupofpeople':
        if (array_search($groupFieldName, $changed) === false) {
          continue 2;
        }

        $allowed = $this->extraFieldsService->explodeAllowedValues($extraField['allowed_values'], false, true);
        $max = $allowed[0]['limit'];

        // add the group id as data field in order to satisfy
        // PMETableViewBase::beforeUpdateDoUpdateAll().
        $groupId = $oldValues[$fieldName]?:Uuid::uuid1();

        $members = explode(',', $newValues[$groupFieldName]);

        if (count($members) > $max) {
          throw new \Exception(
            $this->l->t('Number %d of requested participants for group %s is larger than the number %d of allowed participants.',
                        [ $count($members), $extraField['name'], $max ]));
        }

        foreach ($members as &$member) {
          $member .= ':'.$groupId;
        }
        $newValues[$fieldName] = implode(',', $members);

        $changed[] = $fieldName;
        break;
      case 'groupsofpeople':
        if (array_search($groupFieldName, $changed) === false
            && array_search($fieldName, $changed) === false) {
          continue 2;
        }
        $oldGroupId = $oldValues[$fieldName];
        $newGroupId = $newValues[$fieldName];

        $allowed = $this->extraFieldsService->explodeAllowedValues($extraField['allowed_values'], false, true);
        $max = PHP_INT_MAX;
        $label = $this->l->t('unknown');
        foreach ($allowed as $option) {
          if ($option['key'] == $newGroupId) {
            $max = $option['limit'];
            $label = $option['label'];
            break;
          }
        }

        $newMembers = explode(',', $newValues[$groupFieldName]);

        if (count($newMembers) > $max) {
          throw new \Exception(
            $this->l->t('Number %d of requested participants for group %s is larger than the number %d of allowed participants.',
                        [ $count($newMembers), $label, $max ]));
        }

        foreach ($newMembers as &$member) {
          $member .= ':'.$newGroupId;
        }
        $newValues[$fieldName] = implode(',', $newMembers);

        $oldMembers = explode(',', $oldValues[$groupFieldName]);
        foreach ($newMembers as &$member) {
          $member .= ':'.$oldGroupId;
        }
        $oldValues[$fieldName] = implode(',', $oldMembers);

        $changed[] = $fieldName;
      default:
        break;
      }
    }
    return true;
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

  private function allowedOptionLabel($label, $value, $dataType, $css = null, $data = null)
  {
    $label = Util::htmlEscape($label);
    $css = empty($css) ? $dataType : $css.' '.$dataType;
    $innerCss = $dataType;
    $htmlData = [];
    if (is_array($data)) {
      foreach ($data as $key => $_value) {
        $htmlData[] = "data-".$key."='".$_value."'";
      }
    }
    $htmlData = implode(' ', $htmlData);
    if (!empty($htmlData)) {
      $htmlData = ' '.$htmlData;
    }
    switch ($dataType) {
    case 'service-fee':
      $value = $this->moneyValue($value);
      $innerCss .= ' money';
      break;
    default:
      $value = Util::htmlEscape($value);
      break;
    }
    $label = '<span class="allowed-option-name '.$innerCss.'">'.$label.'</span>';
    $sep   = '<span class="allowed-option-separator '.$innerCss.'">&nbsp;</span>';
    $value = '<span class="allowed-option-value '.$innerCss.'">'.$value.'</span>';
    return '<span class="allowed-option '.$css.'"'.$htmlData.'>'.$label.$sep.$value.'</span>';
  }

  /**
   * Generate a clickable form element which finally will display the
   * debit-mandate dialog, i.e. load some template stuff by means of
   * some java-script and ajax blah.
   */
  public function sepaDebitMandateButton(
    $reference
    , $expired
    , $musicianId
    , $musician
    , $projectId
    , $projectName
    , $mandateProjectId = null
    , $mandateProjectName = null)
  {
    empty($mandateProjectId) && $mandateProjectId = $projectId;
    empty($mandateProjectName) && $mandateProjectName = $projectName;
    $data = [
      'mandateReference' => $reference,
      'mandateExpired' => $expired,
      'musicianId' => $musicianId,
      'musicianName' => $musician,
      'projectId' => $projectId,
      'projectName' => $projectName,
      'mandateProjectId' => $mandateProjectId,
      'mandateProjectName' => $mandateProjectName,
    ];

    $data = htmlspecialchars(json_encode($data, JSON_NUMERIC_CHECK));
    //$data = json_encode($data, JSON_NUMERIC_CHECK);

    $css= ($reference == ($this->l->t("SEPA Debit Mandate")) ? "missing-data " : "")."sepa-debit-mandate";
    $button = '<div class="sepa-debit-mandate tooltip-left">'
            .'<input type="button" '
            .'       id="sepa-debit-mandate-'.$musicianId.'-'.$projectId.'"'
            .'       class="'.$css.' tooltip-left" '
            .'       value="'.$reference.'" '
            .'       title="'.$this->l->t("Click to enter details of a potential SEPA debit mandate").' " '
            .'       name="SepaDebitMandate" '
            .'       data-debit-mandate=\''.$data.'\' '
            .'/>'
            .'</div>';
    return $button;
  }

}
