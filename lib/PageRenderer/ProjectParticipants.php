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

use chillerlan\QRCode\QRCode;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\InsuranceService;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

/**Table generator for Instruments table. */
class ProjectParticipants extends PMETableViewBase
{
  const TEMPLATE = 'project-participants';
  const TABLE = 'ProjectParticipants';
  const MUSICIANS_TABLE = 'Musicians';
  const PROJECTS_TABLE = 'Projects';
  const INSTRUMENTS_TABLE = 'Instruments';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const MUSICIAN_INSTRUMENTS_TABLE = 'MusicianInstrument';
  const PROJECT_INSTRUMENTATION_NUMBERS_TABLE = 'ProjectInstrumentationNumbers';
  const PROJECT_PAYMENTS_TABLE = 'ProjectPayments';
  const PARTICIPANT_FIELDS_TABLE = 'ProjectParticipantFields';
  const PARTICIPANT_FIELDS_DATA_TABLE = 'ProjectParticipantFieldsData';
  const PARTICIPANT_FIELDS_OPTIONS_TABLE = 'ProjectParticipantFieldsDataOptions';
  const SEPA_DEBIT_MANDATES_TABLE = 'SepaDebitMandates';

  /** @var int */
  private $memberProjectId;

  /**
   * Join table structure. All update are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'flags' => self::JOIN_MASTER,
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
      'flags' => self::JOIN_GROUP_BY,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'instrument_id' => false,
        'voice' => [ 'self' => true ],
      ],
      'column' => 'instrument_id',
    ],
    [
      'table' => self::MUSICIAN_INSTRUMENTS_TABLE,
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
      'flags' => self::JOIN_READONLY,
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
      'table' => self::PARTICIPANT_FIELDS_TABLE,
      'entity' => Entities\ProjectParticipantField::class,
      'identifier' => [
        'project_id' => 'project_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    [
      'table' => self::PARTICIPANT_FIELDS_DATA_TABLE,
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'field_id' => [
          'table' => self::PARTICIPANT_FIELDS_TABLE,
          'column' => 'id',
        ],
        'option_key' => false,
      ],
      'column' => 'option_key',
      'encode' => 'BIN2UUID(%s)',
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
    //     'deleted' => [ 'value' => null ],
    //   ],
    //   'column' => 'sequence',
    // ],
  ];

  /** @var \OCA\CAFEVDB\Service\GeoCodingService */
  private $geoCodingService;

  /** @var \OCA\CAFEVDB\Service\PhoneNumberService */
  private $phoneNumberService;

  /** @var \OCA\CAFEVDB\Service\Finance\FinanceService */
  private $financeService;

  /** @var \OCA\CAFEVDB\Service\Finance\InsuranceService */
  private $insuranceService;

  /** @var \OCA\CAFEVDB\Service\ProjectParticipantFieldsService */
  private $participantFieldsService;

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
    , InsuranceService $insuranceService
    , ProjectParticipantFieldsService $participantFieldsService
    , Musicians $musiciansRenderer
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->financeService = $financeService;
    $this->insuranceService = $insuranceService;
    $this->musiciansRenderer = $musiciansRenderer;
    $this->participantFieldsService = $participantFieldsService;
    $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
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
      '$table.project_id = '.$projectId,
    ];
    if ($this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => $template,
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
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'display_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'sur_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'first_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'nick_name'),
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    $participantFields = $this->project['participantFields'];

    // count number of finance fields
    $extraFinancial = 0;
    foreach ($participantFields as $field) {
      $extraFinancial += ($field['dataType'] == FieldType::SERVICE_FEE || $field['dataType'] == FieldType::DEPOSIT);
    }
    if ($extraFinancial > 0) {
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
        'deleted' => [ 'value' => null ],
        'sequence' => false,
      ],
      'column' => 'sequence',
    ];

    /**
     * For each extra field add one dedicated join table entry
     * which is pinned to the respective field-id.
     *
     * @todo Joining many tables with multiple rows per join key is a
     * performance hit. Maybe all those joins should be replaced by
     * only a single one by using IF-clauses inside the GROUP_CONCAT().
     */
    $participantFieldJoinIndex = [];
    foreach ($participantFields as $field) {
      $fieldId = $field['id'];

      // Bad idea and really increases query time
      //
      // $tableName = self::PARTICIPANT_FIELDS_OPTIONS_TABLE.self::VALUES_TABLE_SEP.$fieldId;
      // $participantFieldOptionJoinTable = [
      //   'table' => $tableName,
      //   'entity' => Entities\ProjectParticipantFieldDataOption::class,
      //   'flags' => self::JOIN_FLAGS_NONE,
      //   'identifier' => [
      //     'field_id' => [ 'value' => $fieldId, ],
      //     'key' => false,
      //   ],
      //   'column' => 'key',
      //   'encode' => 'BIN2UUID(%s)',
      // ];

      // $participantFieldJoinIndex[$tableName] = count($this->joinStructure);
      // $this->joinStructure[] = $participantFieldOptionJoinTable;

      $tableName = self::PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
      $participantFieldJoinTable = [
        'table' => $tableName,
        'entity' => Entities\ProjectParticipantFieldDatum::class,
        'flags' => self::JOIN_REMOVE_EMPTY,
        'identifier' => [
          'project_id' => 'project_id',
          'musician_id' => 'musician_id',
          'field_id' => [ 'value' => $fieldId, ],
          'option_key' => false,
        ],
        'column' => 'option_key',
        'encode' => 'BIN2UUID(%s)',
      ];
      $participantFieldJoinIndex[$tableName] = count($this->joinStructure);
      $this->joinStructure[] = $participantFieldJoinTable;
    }

    /*
     *
     **************************************************************************
     *
     * General display options
     *
     */

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs' => $this->tableTabs($participantFields, $useFinanceTab),
        'navigation' => 'VCD',
    ]);

    /*
     *
     **************************************************************************
     *
     * Field descriptions
     *
     */

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
      $opts['fdd'], self::MUSICIANS_TABLE, 'sur_name',
      [
        'name'     => $this->l->t('Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'input|LF' => 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'first_name',
      [
        'name'     => $this->l->t('First Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'input|LF' => 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'nick_name',
      [
        'name'     => $this->l->t('Nickname'),
        'tab'      => [ 'id' => 'tab-all' ],
        'input|LF' => 'H',
        'sql|LFVD' => 'IF($column IS NULL OR $column = \'\', $table.first_name, $column)',
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $row, $k, $pme) {
            $firstName = $row['qf'.($k-1)];
            return [
              'placeholder' => $firstName,
                'readonly' => empty($row['qf'.$k]),
            ];
          },
          'postfix' => function($op, $pos, $row, $k, $pme) {
            $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-nickname"
  '.$checked.'
  type="checkbox"
  class="pme-input pme-input-lock-empty"/>
<label class="pme-input pme-input-lock-empty" for="pme-musician-nickname"></label>';
          },
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name',
      [
        'name'     => $this->l->t('Display-Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'sql|LFVD' => 'IF($column IS NULL OR $column = \'\',
  CONCAT(
    $table.sur_name,
    \', \',
    IF($table.nick_name IS NULL OR $table.nick_name = \'\',
      $table.first_name,
      $table.nick_name
    )
  ),
  $column)',
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $row, $k, $pme) {
            $surName = $row['qf'.($k-3)];
            $firstName = $row['qf'.($k-2)];
            $nickName = $row['qf'.($k-1)];
            return [
              'placeholder' => $surName.', '.($nickName?:$firstName),
              'readonly' => empty($row['qf'.$k]),
            ];
          },
          'postfix' => function($op, $pos, $row, $k, $pme) {
            $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-displayname"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock-empty"
/><label class="pme-input pme-input-lock-empty" for="pme-musician-displayname"></label>';
          },
        ],
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

    $l10nInstrumentsTable = $this->makeFieldTranslationsJoin([
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'identifier' => [ 'id' => true ], // just need the key
    ], 'name');

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id',
      [
        'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
        'name'        => $this->l->t('Project Instrument'),
        'css'         => ['postfix' => ' project-instruments tooltip-top'],
        'display|LVF' => ['popup' => 'data'],
        'sql|VDCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'select'      => 'M',
        'values|VDPC' => [
          'table'       => $l10nInstrumentsTable, // self::INSTRUMENTS_TABLE,
          'column'      => 'id',
          'description' => 'l10n_name',
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.instrument_id',
          'filters'     => "FIND_IN_SET(id, (SELECT GROUP_CONCAT(DISTINCT instrument_id) FROM ".self::MUSICIAN_INSTRUMENTS_TABLE." mi WHERE \$record_id[project_id] = ".$projectId." AND \$record_id[musician_id] = mi.musician_id GROUP BY mi.musician_id))",
        ],
        'values|LFV' => [
          'table'       => $l10nInstrumentsTable, // self::INSTRUMENTS_TABLE,
          'column'      => 'id',
          'description' => 'l10n_name',
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
        'default'  => 0, // keep in sync with ProjectInstrumentationNumbers
        'select'   => 'M',
        'css'      => [ 'postfix' => ' allow-empty no-search instrument-voice' ],
        'sql|VD' => "GROUP_CONCAT(DISTINCT
  IF(\$join_col_fqn > 0,
     CONCAT(".$joinTables[self::INSTRUMENTS_TABLE].".name,
            ' ',
            \$join_col_fqn),
     NULL)
  ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        // copy/change only include non-zero voice
        'sql|CP' => "GROUP_CONCAT(
  DISTINCT
  IF(".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice > 0,
    CONCAT_WS(
      '".self::JOIN_KEY_SEP."',
      ".$joinTables[self::INSTRUMENTS_TABLE].".id,
      ".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice),
    NULL
  )
  ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        'values|CP' => [
          'table' => "SELECT
  CONCAT(pi.instrument_id,'".self::JOIN_KEY_SEP."', n.n) AS value,
  pi.project_id,
  pi.musician_id,
  i.id AS instrument_id,
  i.name,
  COALESCE(ft.content, i.name) AS l10n_name,
  i.sort_order,
  pin.quantity,
  n.n
  FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pi.instrument_id
  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." ft
    ON ft.locale = '".($this->l10n()->getLanguageCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
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
            'columns' => [ 'l10n_name', 'n' ],
            'divs' => ' ',
          ],
          'orderby' => '$table.sort_order ASC, $table.n ASC',
          'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
          //'join' => '$join_table.musician_id = $main_table.musician_id AND $join_table.project_id = $main_table.project_id',
          'join' => false,
        ],
        'values2|LF' => [ '0' => $this->l->t('n/a') ] + array_combine(range(1, 8), range(1, 8)),
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
  DISTINCT
  CONCAT_WS(
    '".self::JOIN_KEY_SEP."',
    ".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".instrument_id,
    ".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader)
  ORDER BY ".$joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
       'display|LF' => [ 'popup' => function($data) {
         return $this->toolTipsService['section-leader-mark'];
       }],
       'values|CAPDV' => [
         'table' => "SELECT
  CONCAT_WS('".self::JOIN_KEY_SEP."', pi.instrument_id, 1) AS value,
  pi.project_id,
  pi.musician_id,
  pi.instrument_id,
  pi.voice,
  MAX(pin.voice) AS voices,
  i.name,
  COALESCE(ft.content, i.name) AS l10n_name,
  i.sort_order
  FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pi.instrument_id
  LEFT JOIN ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin
    ON pin.project_id = pi.project_id AND pin.instrument_id = pi.instrument_id
  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." ft
    ON ft.locale = '".($this->l10n()->getLanguageCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
  WHERE
    pi.project_id = $projectId
  GROUP BY pi.instrument_id
  HAVING (MAX(pin.voice) = 0 OR pi.voice > 0)",
         'column' => 'value',
         'description' => [ 'l10n_name', 'IF($table.voice = 0, \'\', CONCAT(\' \', $table.voice))' ],
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
      'css'         => ['postfix' => ' musician-instruments tooltip-top no-chosen selectize drag-drop'],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => ($expertMode
                        ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'
                        : 'GROUP_CONCAT(DISTINCT IF('.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.disabled, NULL, $join_col_fqn) ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'),
      'select'      => 'M',
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'name',
        'orderby'     => '$table.sort_order ASC',
        'join'        => '$join_col_fqn = '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.disabled = 0' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'disabled', [
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

    $monetary = $this->participantFieldsService->monetaryFields($this->project);

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

            /** @var Entities\ProjectParticipantField $participantField */
            $amountInvoiced = 0.0;
            foreach ($monetary as $fieldId => $participantField) {

              $table = self::PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
              $fieldValues = [ 'key' => null, 'value' => null ];
              foreach ($fieldValues as $fieldName => &$fieldValue) {
                $label = $this->joinTableFieldName($table, 'option_'.$fieldName);
                if (!isset($pme->fdn[$label])) {
                  throw new \Exception($this->l->t('Data for monetary field "%s" not found', $label));
                }
                $rowIndex = $pme->fdn[$label];
                $qf = 'qf'.$rowIndex;
                $qfIdx = $qf.'_idx';
                if (isset($row[$qfIdx])) {
                  $fieldValue = $row[$qfIdx];
                } else {
                  $fieldValue = $row[$qf];
                }
              }

              if (empty($fieldValues['key']) && empty($fieldValues['value'])) {
                continue;
              }

              $amountInvoiced += $this->participantFieldsService->participantFieldSurcharge(
                $fieldValues['key'], $fieldValues['value'], $participantField);
            }

            if ($projectId == $this->memberProjectId) {
              $amountInvoiced += $this->insuranceService->insuranceFee($musicianId, new \DateTime(), true);
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
    /** @var Entities\ProjectParticipantField $field */
    foreach ($participantFields as $field) {
      $fieldName = $field['name'];
      $fieldId   = $field['id'];
      $multiplicity = $field['multiplicity'];
      $dataType = (string)$field['data_type'];

      if (!$this->participantFieldsService->isSupportedType($multiplicity, $dataType)) {
        throw new \Exception(
          $this->l->t('Unsupported multiplicity / data-type combination: %s / %s',
                      [ $multiplicity, $dataType ]));
      }

      // set tab unless overridden by field definition
      if ($field['data_type'] == FieldType::SERVICE_FEE || $field['data_type'] == FieldType::DEPOSIT) {
        $tab = [ 'id' => $financeTab ];
      } else {
        $tab = [ 'id' => 'project' ];
      }
      if (!empty($field['tab'])) {
        $tabId = $this->tableTabId($field['tab']);
        $tab = [ 'id' => $tabId ];
      }

      $tableName = self::PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;

      $css = [ 'participant-field', 'field-id-'.$fieldId, ];
      $extraFddBase = [
        'name' => $this->l->t($fieldName),
        'tab' => $tab,
        'css'      => [ 'postfix' => ' '.implode(' ', $css), ],
        'default|A'  => $field['default_value'],
        'filter' => 'having',
        'values' => [
          'grouped' => true,
          'filters' => ('$table.field_id = '.$fieldId
                        .' AND $table.project_id = '.$projectId
                        .' AND $table.musician_id = $record_id[musician_id]'),
        ],
        'tooltip' => $field['tooltip']?:null,
      ];

      list($keyFddIndex, $keyFddName) = $this->makeJoinTableField(
        $opts['fdd'], $tableName, 'option_key',
        Util::arrayMergeRecursive($extraFddBase, [ 'values' => ['encode' => 'BIN2UUID(%s)',], ])
      );
      $keyFdd = &$opts['fdd'][$keyFddName];

      list($valueFddIndex, $valueFddName) = $this->makeJoinTableField(
        $opts['fdd'], $tableName, 'option_value',
        Util::arrayMergeRecursive($extraFddBase, [ 'input' => 'VSRH', ])
      );
      $valueFdd = &$opts['fdd'][$valueFddName];

      /** @var Doctrine\Common\Collections\Collection */
      $dataOptions = $field['dataOptions']->filter(function(Entities\ProjectParticipantFieldDataOption $option) {
        // Filter out the generator option and soft-deleted options
        return ((string)$option->getKey() != Uuid::NIL && empty($option->getDeleted()));
      });
      $values2     = [];
      $valueTitles = [];
      $valueData   = [];
      /** @var Entities\ProjectParticipantFieldDataOption $dataOption */
      foreach ($dataOptions as $dataOption) {
        $key = (string)$dataOption['key'];
        if (empty($key)) {
          continue;
        }
        if ($dataOption->isDeleted()) {
          continue;
        }
        $values2[$key] = $dataOption['label'];
        $valueTitles[$key] = $dataOption['tooltip'];
        $valueData[$key] = $dataOption['data'];
      }

      foreach ([ &$keyFdd, &$valueFdd ] as &$fdd) {
        switch ($dataType) {
          case FieldType::TEXT:
            // default config
            break;
          case FieldType::HTML:
            $fdd['textarea'] = [
              'css' => 'wysiwyg-editor',
              'rows' => 5,
              'cols' => 50,
            ];
            $fdd['css']['postfix'] .= ' hide-subsequent-lines';
            $fdd['display|LF'] = [ 'popup' => 'data' ];
            $fdd['escape'] = false;
            break;
          case FieldType::BOOLEAN:
            // handled below
            $fdd['align'] = 'right';
            break;
          case FieldType::INTEGER:
            $fdd['select'] = 'N';
            $fdd['mask'] = '%d';
            $fdd['align'] = 'right';
            break;
          case FieldType::FLOAT:
            $fdd['select'] = 'N';
            $fdd['mask'] = '%g';
            $fdd['align'] = 'right';
            break;
          case FieldType::DATE:
          case FieldType::DATETIME:
          case FieldType::SERVICE_FEE:
          case FieldType::DEPOSIT:
            $style = $this->defaultFDD[$dataType];
            if (empty($style)) {
              throw new \Exception($this->l->t('Not default style for "%s" available.', $dataType));
            }
            unset($style['name']);
            $fdd = array_merge($fdd, $style);
            $fdd['css']['postfix'] .= ' '.implode(' ', $css);
            break;
        }
      }

      switch ($multiplicity) {
      case FieldMultiplicity::SIMPLE:
        /**********************************************************************
         *
         * Simple input field.
         *
         */
        $valueFdd['input'] = $keyFdd['input'];
        $keyFdd['input'] = 'VSRH';
        $valueFdd['css']['postfix'] .= ' simple-valued '.$dataType;
        switch ($dataType) {
        case FieldType::SERVICE_FEE:
        case FieldType::DEPOSIT:
          unset($valueFdd['mask']);
          $valueFdd['php|VDLF'] = function($value) {
            return $this->moneyValue($value);
          };
          break;
        case FieldType::FILE_DATA:
          $valueFdd['php|CAP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field) {
            $key = $field->getDataOptions()->first()->getKey();
            return '<div class="file-upload-wrapper" data-option-key="'.$key.'">
  <a href="#">Blah</a>
</div>';
          };
          break;
        default:
          break;
        }
        break;
      case FieldMultiplicity::SINGLE:
        /**********************************************************************
         *
         * Single choice field, yes/no
         *
         */
        reset($values2); $key = key($values2);
        $keyFdd['values2|CAP'] = [ $key => '' ]; // empty label for simple checkbox
        $keyFdd['values2|LVDF'] = [
          0 => $this->l->t('false'),
          $key => $this->l->t('true'),
        ];
        $keyFdd['select'] = 'C';
        $keyFdd['default'] = (string)!!(int)$field['default_value'];
        $keyFdd['css']['postfix'] .= ' boolean single-valued '.$dataType;
        switch ($dataType) {
        case FieldType::BOOLEAN:
          break;
        case 'money':
        case FieldType::SERVICE_FEE:
        case FieldType::DEPOSIT:
          $money = $this->moneyValue(reset($valueData));
          $noMoney = $this->moneyValue(0);
          // just use the amount to pay as label
          $keyFdd['values2|LVDF'] = [
            '' => '-,--',
            0 => $noMoney, //'-,--',
            $key => $money,
          ];
          $keyFdd['values2|CAP'] = [ $key => $money, ];
          unset($keyFdd['mask']);
          $keyFdd['php|VDLF'] = function($value) {
            return $this->moneyValue($value);
          };
          break;
        default:
          $keyFdd['values2|CAP'] = [ $key => reset($valueData) ];
          break;
        } // data-type switch
        break;
      case FieldMultiplicity::PARALLEL:
      case FieldMultiplicity::MULTIPLE:
        /**********************************************************************
         *
         * Multiple or single choices from a set of predefined choices.
         *
         */
        switch ($dataType) {
        case FieldType::SERVICE_FEE:
        case FieldType::DEPOSIT:
          foreach ($dataOptions as $dataOption) {
            $key = (string)$dataOption['key'];
            $label = $dataOption['label'];
            $data  = $dataOption['data'];
            $values2[$key] = $this->allowedOptionLabel($label, $data, $dataType, 'money');
          }
          unset($keyFdd['mask']);
          $keyFdd['values2glue'] = "<br/>";
          $keyFdd['escape'] = false;
          // fall through
        default:
          $keyFdd['values2'] = $values2;
          $keyFdd['valueTitles'] = $valueTitles;
          $keyFdd['valueData'] = $valueData;
          $keyFdd['display|LF'] = [
            'popup' => 'data',
            'prefix' => '<div class="allowed-option-wrapper">',
            'postfix' => '</div>',
          ];
          if ($multiplicity == FieldMultiplicity::PARALLEL) {
            $keyFdd['css']['postfix'] .= ' set hide-subsequent-lines';
            $keyFdd['select'] = 'M';
          } else {
            $keyFdd['css']['postfix'] .= ' enum allow-empty';
            $keyFdd['select'] = 'D';
          }
          $keyFdd['css']['postfix'] .= ' '.$dataType;
          break;
        }
        break;
      case FieldMultiplicity::RECURRING:

        /**********************************************************************
         *
         * Recurring auto-generated fields
         *
         */

        foreach ([&$keyFdd, &$valueFdd] as &$fdd) {
          $fdd['css']['postfix'] .= ' recurring generated '.$dataType;
          unset($fdd['mask']);
          $fdd['select'] = 'M';
          $fdd['values'] = array_merge(
            $fdd['values'], [
              'column' => 'option_key',
              'description' => [
                'columns' => [ 'BIN2UUID($table.option_key)', '$table.option_value', ],
                'divs' => ':',
              ],
              'orderby' => '$table.created DESC',
              'encode' => 'BIN2UUID(%s)',
            ]);
        }

        foreach ($dataOptions as $dataOption) {
          $values2[(string)$dataOption['key']] = $dataOption['label'];
        }
        $keyFdd['values2|LFVD'] = $values2;

        $keyFdd['values|FL'] = array_merge(
          $keyFdd['values'], [
            'filters' => ('$table.field_id = '.$fieldId
                          .' AND $table.project_id = '.$projectId),
          ]);
        $keyFdd['display|LF'] = [ 'popup' => 'data' ];
        $keyFdd['php|LFVD'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataType) {
          // LF are actually both the same. $value will always just
          // come from the filter's $value2 array. The actual values
          // we need are in the description fields which are passed
          // through the 'qf'.$k field in $row.
          $values = Util::explodeIndexed($row['qf'.$k]);
          $html = [];
          foreach ($values as $key => $value) {
            $option =  $field->getDataOption($key);
            $label = $option ? $option->getLabel() : '';
            $html[] = $this->allowedOptionLabel($label, $value, $dataType);
          }
          return '<div class="allowed-option-wrapper">'.implode('<br/>', $html).'</div>';
        };

        // For a useful add/change/copy view we should use the value fdd.
        $valueFdd['input|ACP'] = $keyFdd['input'];
        $keyFdd['input|ACP'] = 'VSRH';

        $valueFdd['sql|ACP'] = 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    \''.self::JOIN_KEY_SEP.'\',
    BIN2UUID($join_table.option_key),
    $join_table.option_value
  )
  ORDER BY $order_by)';

        $valueFdd['php|ACP'] = function($value, $op, $k, $row, $recordId, $pme) use ($field, $dataType, $keyFddName, $valueFddName) {
          $this->logInfo('VALUE '.$k.': '.$value);
          $this->logInfo('ROW '.$k.': '.$row['qf'.$k]);
          $this->logInfo('ROW IDX '.$k.': '.$row['qf'.$k.'_idx']);

          $value = $row['qf'.$k];
          $values = Util::explodeIndexed($value);
          $valueName = $this->pme->cgiDataName($valueFddName);
          $keyName = $this->pme->cgiDataName($keyFddName);
          $html = '<table class="row-count-'.count($values).'">
  <thead>
    <tr><th>'.$this->l->t('Actions').'</th><th>'.$this->l->t('Subject').'</th><th>'.$this->l->t('Value [%s]', $this->currencySymbol()).'</th></tr>
  </thead>
  <tbody>';
          $idx = 0;
          foreach ($values as $key => $value) {
            $option =  $field->getDataOption($key);
            $label = $option ? $option->getLabel() : '';
            $html .= '
<tr data-option-key="'.$key.'" data-field-id="'.$field['id'].'">
  <td class="operations">
    <input
      class="operation delete-undelete"
      title="'.$this->toolTipsService['participant-fields-recurring-data:delete-undelete'].'"
      type="button"/>
    <input
      class="operation regenerate"
      title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate'].'"
      type="button"/>
  </td>
  <td class="label">
    '.$label.'
  </td>
  <td class="input">
    <input id="receivable-input-'.$key.'" type=checkbox checked="checked" class="pme-input pme-input-lock-unlock left-lock"/>
    <label class="pme-input pme-input-lock-unlock left-lock" title="'.$this->toolTipsService['pme-lock-unlock'].'" for="receivable-input-'.$key.'"></label>
    <input class="pme-input '.$dataType.'" type="number" readonly="readonly" name="'.$valueName.'['.$idx.']" value="'.$value.'"/>
     <input class="pme-input '.$dataType.'" type="hidden" name="'.$keyName.'['.$idx.']" value="'.$key.'"/>
  </td>
</tr>';
            $idx++;
          }
          $html .= '
    <tr data-field-id="'.$field['id'].'">
      <td class="operations" colspan="3">
        <input
          class="operation regenerate-all"
          title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate-all'].'"
          type="button"
          value="'.$this->l->t('Recompute all Receivables').'"
          title="'.$this->toolTipsService['participant-fields-recurring-data:regenerate-all'].'"
        />
      </td>
    </tr>
  </tbody>
</table>';
          return $html;
        };
        break;
        /*
         * end of FieldMultiplicity::RECURRING
         *
         *********************************************************************/
      case FieldMultiplicity::GROUPOFPEOPLE:
        /**********************************************************************
         *
         * Grouping with variable number of groups, e.g. "room-mates".
         *
         */

        // special option with Uuid::NIL holds the management information
        $generatorOption = $field->getManagementOption();
        $valueGroups = [ -1 => $this->l->t('without group'), ];

        // old field, group selection
        $keyFdd = array_merge($keyFdd, [ 'mask' => null, ]);

        // generate a new group-definition field as yet another column
        list(, $fddGroupMemberName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'musician_id', $keyFdd);

        // hide value field and tweak for view displays.
        $css[] = FieldMultiplicity::GROUPOFPEOPLE;
        $css[] = 'single-valued';
        $keyFdd = Util::arrayMergeRecursive(
          $keyFdd, [
            'css' => [ 'postfix' => ' '.implode(' ', $css).' groupofpeople-id', ],
            'input' => 'VSRH',
          ]);


        // tweak the join-structure entry for the group field
        $joinInfo = &$this->joinStructure[$participantFieldJoinIndex[$tableName]];
        $joinInfo = array_merge(
          $joinInfo,
          [
            'identifier' => [
              'project_id' => 'project_id',
              'musician_id' => false,
              'field_id' => [ 'value' => $fieldId, ],
              'option_key' => [ 'self' => true, ],
            ],
            'column' => 'musician_id',
          ]);

        // store the necessary group data compatible to the predefined groups stuff
        $max = $generatorOption['limit'];
        $dataOptionsData = $dataOptions->map(function($value) use ($max) {
          return [
            'key' => (string)$value['key'],
            'data' => [ 'limit' => $max, ],
          ];
        })->getValues();
        array_unshift(
          $dataOptionsData,
          [ 'key' => $generatorOption['key'], 'data' => [ 'limit' => $max, ], ]
        );
        $dataOptionsData = json_encode(array_column($dataOptionsData, 'data', 'key'));

        // new field, member selection
        $groupMemberFdd = &$opts['fdd'][$fddGroupMemberName];
        $groupMemberFdd = array_merge(
          $groupMemberFdd, [
            'select' => 'M',
            'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn)',
            'display' => [ 'popup' => 'data' ],
            'colattrs' => [ 'data-groups' => $dataOptionsData, ],
            'filter' => 'having',
            'values' => [
              'table' => "SELECT
   m1.id AS musician_id,
   CONCAT_WS(' ', m1.first_name, m1.sur_name) AS name,
   m1.sur_name AS sur_name,
   m1.first_name AS first_name,
   fd.option_key AS group_id,
   fdg.group_number AS group_number
FROM ".self::TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m1
  ON m1.id = pp.musician_id
LEFT JOIN ".self::PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = $projectId AND fd.field_id = $fieldId
LEFT JOIN (SELECT
    fd2.option_key AS group_id,
    ROW_NUMBER() OVER (ORDER BY fd2.field_id) AS group_number
    FROM ".self::PARTICIPANT_FIELDS_DATA_TABLE." fd2
    WHERE fd2.project_id = $projectId AND fd2.field_id = $fieldId
    GROUP BY fd2.option_key
  ) fdg
  ON fdg.group_id = fd.option_key
WHERE pp.project_id = $projectId",
              'column' => 'musician_id',
              'description' => 'name',
              'groups' => "IF(
  \$table.group_number IS NULL,
  '".$this->l->t('without group')."',
  CONCAT_WS(' ', '".$fieldName."', \$table.group_number))",
              'data' => 'JSON_OBJECT(
  "groupId", IFNULL(BIN2UUID($table.group_id), -1),
  "limit", '.$max.'
)',
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.option_key',
            ],
            'valueGroups|ACP' => $valueGroups,
          ]);

        $groupMemberFdd['css']['postfix'] .= ' '.implode(' ', $css);

        if ($dataType == FieldType::SERVICE_FEE || $dataType == FieldType::DEPOSIT) {
          $groupMemberFdd['css']['postfix'] .= ' money '.$dataType;
          $fieldData = $generatorOption['data'];
          $money = $this->moneyValue($fieldData);
          $groupMemberFdd['name|LFVD'] = $groupMemberFdd['name'];
          $groupMemberFdd['name'] = $this->allowedOptionLabel($groupMemberFdd['name'], $fieldData, $dataType, 'money');
          $groupMemberFdd['display|LFVD'] = array_merge(
            $groupMemberFdd['display'],
            [
              'prefix' => '<span class="allowed-option money group service-fee"><span class="allowed-option-name money clip-long-text group">',
              'postfix' => ('</span><span class="allowed-option-separator money">&nbsp;</span>'
                            .'<span class="allowed-option-value money">'.$money.'</span></span>'),
            ]);
        }

        // in filter mode mask out all non-group-members
        $groupMemberFdd['values|LF'] = array_merge(
          $groupMemberFdd['values'],
          [ 'filters' => '$table.group_id IS NOT NULL' ]);

        break;
      case FieldMultiplicity::GROUPSOFPEOPLE:
        /**********************************************************************
         *
         * Grouping with predefined group names, e.g. for car-sharing
         * or excursions.
         *
         */
        // tweak the join-structure entry for the group field
        $joinInfo = &$this->joinStructure[$participantFieldJoinIndex[$tableName]];
        $joinInfo = array_merge(
          $joinInfo, [
            'identifier' => [
              'project_id' => 'project_id',
              'musician_id' => false,
              'field_id' => [ 'value' => $fieldId, ],
              'option_key' => [ 'self' => true, ],
            ],
            'column' => 'musician_id',
          ]);

        // define the group stuff
        $groupValues2   = $values2;
        $groupValueData = $valueData;
        $values2 = [];
        $valueGroups = [ -1 => $this->l->t('without group'), ];
        $idx = -1;
        foreach($dataOptions as $dataOption) {
          $valueGroups[--$idx] = $dataOption['label'];
          $data = $dataOption['data'];
          if ($dataType == FieldType::SERVICE_FEE || $dataType == FieldType::DEPOSIT) {
            $data = $this->moneyValue($data);
          }
          if (!empty($data)) {
            $valueGroups[$idx] .= ':&nbsp;' . $data;
          }
          $values2[$idx] = $this->l->t('add to this group');
          $valueData[$idx] = json_encode([ 'groupId' => $dataOption['key'], ]);
        }

        // make the field a select box for the predefined groups, like
        // for the "multiple" stuff.

        $css[] = FieldMultiplicity::GROUPOFPEOPLE;
        $css[] = 'predefined';
        if ($dataType === FieldType::SERVICE_FEE || $dataType === FieldType::DEPOSIT) {
          $css[] = ' money '.$dataType;
          foreach ($groupValues2 as $key => $value) {
            $groupValues2[$key] = $this->allowedOptionLabel(
              $value, $groupValueData[$key], $dataType, 'money group');
          }
        }

        // old field, group selection
        $keyFdd = array_merge(
          $keyFdd, [
            //'name' => $this->l->t('%s Group', $fieldName),
            'css'         => [ 'postfix' => ' '.implode(' ', $css) ],
            'select'      => 'D',
            'values2'     => $groupValues2,
            'display'     => [ 'popup' => 'data' ],
            'sort'        => true,
            'escape'      => false,
            'mask' => null,
          ]);

        $fddBase = Util::arrayMergeRecursive([], $keyFdd);

        // hide value field
        $keyFdd = Util::arrayMergeRecursive(
          $keyFdd, [
            'css' => [ 'postfix' => ' '.implode(' ', $css).' groupofpeople-id', ],
            'input' => 'VSRH',
          ]);

        // generate a new group-definition field as yet another column
        list(, $fddGroupMemberName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'musician_id', $fddBase);

        // compute group limits per group
        $dataOptionsData = $dataOptions->map(function($value) {
          return [
            'key' => (string)$value['key'],
            'data' =>  [ 'limit' => $value['limit'], ],
          ];
        })->getValues();
        $dataOptionsData = json_encode(array_column($dataOptionsData, 'data', 'key'));

        // new field, member selection
        $groupMemberFdd = &$opts['fdd'][$fddGroupMemberName];
        $groupMemberFdd = Util::arrayMergeRecursive(
          $groupMemberFdd, [
            'select' => 'M',
            'sql|ACP' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id)',
            //'sql' => 'GROUP_CONCAT(DISTINCT $join_table.musician_id)',
            //'display' => [ 'popup' => 'data' ],
            'colattrs' => [ 'data-groups' => $dataOptionsData, ],
            'values|ACP' => [
              'table' => "SELECT
  m3.id AS musician_id,
  CONCAT_WS(' ', m3.first_name, m3.sur_name) AS name,
  m3.sur_name AS sur_name,
  m3.first_name AS first_name,
  fd.option_key AS group_id,
  do.label AS group_label,
  do.data AS group_data,
  do.limit AS group_limit
FROM ".self::TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m3
  ON m3.id = pp.musician_id
LEFT JOIN ".self::PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = $projectId AND fd.field_id = $fieldId
LEFT JOIN ".self::PARTICIPANT_FIELDS_OPTIONS_TABLE." do
  ON do.field_id = fd.field_id AND do.key = fd.option_key
WHERE pp.project_id = $projectId",
              'column' => 'musician_id',
              'description' => 'name',
              'groups' => "CONCAT(\$table.group_label, ': ', \$table.group_data)",
              'data' => 'JSON_OBJECT(
  "groupId", IFNULL(BIN2UUID($table.group_id), -1),
  "limit", $table.group_limit
)',
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.option_key',
              //'titles' => '$table.name',
            ],
            'valueGroups|ACP' => $valueGroups,
            'valueData|ACP' => $valueData,
            'values2|ACP' => $values2,
            'mask' => null,
            'display|LDV' => [
              'popup' => 'data:next',
            ],
            'display|ACP' => [
              'prefix' => function($op, $pos, $row, $k, $pme) use ($css) {
                return '<label class="'.implode(' ', $css).'">';
              },
              'postfix' => function($op, $pos, $row, $k, $pme) use ($dataOptions, $dataType, $keyFddIndex) {
                $selectedKey = $row['qf'.$keyFddIndex];
                $html = '';
                foreach ($dataOptions  as $dataOption) {
                  $key = $dataOption['key'];
                  $active = $selectedKey == $key ? 'selected' : null;
                  $html .= $this->allowedOptionLabel(
                    $dataOption['label'], $dataOption['data'], $dataType, $active, [ 'key' => $dataOption['key'], ]);
                }
                $html .= '</label>';
                return $html;
              },
            ],
          ]);

        $groupMemberFdd['css']['postfix'] .= ' clip-long-text';
        $groupMemberFdd['css|LFVD']['postfix'] = $groupMemberFdd['css']['postfix'].' view';

        // generate yet another field to define popup-data
        list(, $fddMemberNameName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'musician_name', $fddBase);

        // new field, data-popup
        $popupFdd = &$opts['fdd'][$fddMemberNameName];

        // data-popup field
        $popupFdd = Util::arrayMergeRecursive(
           $popupFdd, [
             'input' => 'VSRH',
             'css'   => [ 'postfix' => ' '.implode(' ', $css).' groupofpeople-popup' ],
             'sql|LVFD' => "GROUP_CONCAT(DISTINCT \$join_col_fqn ORDER BY \$order_by SEPARATOR ', ')",
            'values|LFDV' => [
              'table' => "SELECT
  m2.id AS musician_id,
  CONCAT_WS(' ', m2.first_name, m2.sur_name) AS name,
  m2.sur_name AS sur_name,
  m2.first_name AS first_name,
  fd.option_key AS group_id
FROM ".self::TABLE." pp
LEFT JOIN ".self::MUSICIANS_TABLE." m2
  ON m2.id = pp.musician_id
LEFT JOIN ".self::PARTICIPANT_FIELDS_DATA_TABLE." fd
  ON fd.musician_id = pp.musician_id AND fd.project_id = pp.project_id
WHERE pp.project_id = $projectId AND fd.field_id = $fieldId",
              'column' => 'name',
              'orderby' => '$table.group_id ASC, $table.sur_name ASC, $table.first_name ASC',
              'join' => '$join_table.group_id = '.$joinTables[$tableName].'.option_key',
            ],
           ]);

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
        'table' => self::PROJECTS_TABLE,
        'column' => 'name',
        //'description' => 'name',
        'orderby' => '$table.year ASC, $table.name ASC',
        'groups' => 'year',
        'join' => '$join_table.id = '.$joinTables[self::TABLE].'.project_id'
      ],
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
      'sql' => '$main_table.musician_id', // @todo: needed?
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
      'sql' => '$main_table.musician_id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        switch($action) {
        case 'change':
        case 'display':
          $data = [];
          foreach($pme->fds as $idx => $label) {
            $data[$label] = $row['qf'.$idx];
          }
          $categories = [];
          $musician = new Entities\Musician();
          foreach ($data as $key => $value) {
            switch ($key) {
            case 'all_projects':
              $categories = array_merge($categories, explode(',', Util::removeSpaces($value)));
              break;
            case 'MusicianInstrument:instrument_id':
              foreach (explode(',', Util::removeSpaces($value)) as $instrumentId) {
                $categories[] = $this->instrumentInfo['byId'][$instrumentId];
              }
              break;
            default:
              $fieldInfo = $this->joinTableField($key);
              if ($fieldInfo['table'] != self::MUSICIANS_TABLE) {
                continue 2;
              }
              $column = $fieldInfo['column'];
              // In order to support "categories" the same way as the
              // AddressBook-integration we need to feed the
              // Musician-entity with more data:
              try {
                $musician[$column] = $value;
              } catch (\Throwable $t) {
                // Don't care, we know virtual stuff is not there
                // $this->logException($t);
              }
              break;
            }
          }
          $vcard = $this->contactsService->export($musician);
          unset($vcard->PHOTO); // too much information
          $categories = array_merge($categories, $vcard->CATEGORIES->getParts());
          sort($categories);
          $vcard->CATEGORIES->setParts($categories);
          //$this->logInfo($vcard->serialize());
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
           if ($this->pmeBare) {
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
        'select' => 'T',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_col_fqn DESC)',
      ]));

    //////// END Field definitions

    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateSanitizeParticipantFields' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateEnsureInstrumentationNumbers' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'extractInstrumentRanking' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'cleanupParticipantFields' ];

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
//       $monetary = ProjectParticipant::monetaryFields($userParticipantFields, $fieldTypes);

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
//             $allowed = $field['DataOptions'];
//             $type    = $field['Type'];
//             $amount += self::participantFieldSurcharge($value, $allowed, $type['Multiplicity']);
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
//       $opts['triggers']['insert']['pre'][]  = 'CAFEVDB\ProjectParticipant::preTrigger';

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

    ///@@@@@@@@@@@@@@@@@@@@@

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Make sure at least a dummy instrumentation number exists when
   * adding people to project instruments.
   */
  public function beforeUpdateEnsureInstrumentationNumbers($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $voiceField = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice');
    $instrumentField = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id');

    if (array_search($voiceField, $changes) === false && array_search($instrumentField, $changed) == false) {
      // nothing to do
      $this->debug('UNCHANGED INSTRUMENTS');
      return true;
    }

    // only the new values should matter ...
    $instrumentValues = Util::explodeIndexed($newValues[$instrumentField], 0);
    $voiceValues = Util::explodeIndexed($newValues[$voiceField]);

    $instrumentationNumbers = $this->project->getInstrumentationNumbers();

    $voices = array_replace($voiceValues, $instrumentValues);

    $this->debug('INSTRUMENTS '.print_r($instrumentValues, true));
    $this->debug('VOICE VALUES '.print_r($voiceValues, true));
    $this->debug('VOICES '.print_r($voices, true));

    foreach ($voices as $instrumentId => $voice) {
      if (!$instrumentationNumbers->exists(function($dummy, Entities\ProjectInstrumentationNumber $instrumentationNumber) use ($instrumentId, $voice) {
        return ($instrumentationNumber->getInstrument()->getId() == $instrumentId
                &&
                $instrumentationNumber->getVoice() == $voice);
      })) {
        $instrumentationNumber = (new Entities\ProjectInstrumentationNumber)
                               ->setProject($this->project)
                               ->setInstrument($instrumentId)
                               ->setVoice($voice)
                               ->setQuantity(0);
        $this->persist($instrumentationNumber);
      }
    }

    return true;
  }

  /**
   * Tweak the submitted data for the somewhat complicate "participant
   * fields" -- i.e. the personal data collected for the project
   * participants -- into a form understood by
   * beforeUpdataDoUpdateAll() and beforeInsertDoInsertAll().
   */
  public function beforeUpdateSanitizeParticipantFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->debug('OLDVALUES '.print_r($oldValues, true));
    $this->debug('NEWVALUES '.print_r($newValues, true));
    $this->debug('CHANGED '.print_r($changed, true));

    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project['participantFields'] as $participantField) {
      $fieldId = $participantField['id'];
      $multiplicity = $participantField['multiplicity'];
      $dataType = $participantField['dataType'];

      $tableName = self::PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;

      $keyName = $this->joinTableFieldName($tableName, 'option_key');
      $valueName = $this->joinTableFieldName($tableName, 'option_value');
      $groupFieldName = $this->joinTableFieldName($tableName, 'musician_id');

      $this->debug('FIELDNAMES '.$keyName." / ".$groupFieldName);

      $this->debug("MULTIPLICITY ".$multiplicity);
      switch ($multiplicity) {
      case FieldMultiplicity::SIMPLE:
        // We fake a multi-selection field and set the user input as
        // additional field value.
        if (array_search($valueName, $changed) === false) {
          continue 2;
        }
        $dataOption = $participantField['dataOptions']->first(); // the only one
        $key = $dataOption['key'];
        $oldKey = $oldValues[$keyName]?:$key;
        if ($oldKey !== $key) {
          throw new \RuntimeException(
            $this->l->t('Inconsistent field keys, old: "%s", new: "%s"',
                        [ $key, $oldKey ]));
        }
        // tweak the option_key value
        $newValues[$keyName] = $key;
        $changed[] = $keyName;
        // tweak the option value to have the desired form
        $newValues[$valueName] = $key.self::JOIN_KEY_SEP.$newValues[$valueName];
        break;
      case FieldMultiplicity::RECURRING:
        if (array_search($valueName, $changed) === false
            && array_search($keyName, $changed) === false) {
          continue 2;
        }

        // just convert to KEY:VALUE notation for the following trigger functions
        // $oldValues ATM already has this format
        foreach ([&$newValues] as &$dataSet) {
          $keys = Util::explode(',', $dataSet[$keyName]);
          $amounts = Util::explode(',', $dataSet[$valueName]);
          $values = [];
          foreach (array_combine($keys, $amounts) as $key => $amount) {
            $values[] = $key.self::JOIN_KEY_SEP.$amount;
          }
          $dataSet[$valueName] = implode(',', $values);
        }

        // mark both as changed
        foreach ([$keyName, $valueName] as $fieldName) {
          Util::unsetValue($changed, $fieldName);
          if ($oldValues[$fieldName] != $newValues[$fieldName]) {
            $changed[] = $fieldName;
          }
        }
        break;
      case FieldMultiplicity::GROUPOFPEOPLE:
      case FieldMultiplicity::GROUPSOFPEOPLE:
        // Multiple predefined groups with a variable number of
        // members. Think of distributing members to cars or rooms

        if (array_search($groupFieldName, $changed) === false
            && array_search($keyName, $changed) === false) {
          continue 2;
        }

        $oldGroupId = $oldValues[$keyName];
        $newGroupId = $newValues[$keyName];

        $max = PHP_INT_MAX;
        $label = $this->l->t('unknown');
        if ($multiplicity == FieldMultiplicity::GROUPOFPEOPLE) {
          /** @var Entities\ProjectParticipantFieldDataOption $generatorOption */
          $generatorOption = $participantField->getManagementOption();
          $max = $generatorOption['limit'];
          $label = $participantField['name'];
        } else {
          $newDataOption = $participantField->getDataOption($newGroupId);
          $max = $newDataOption['limit'];
          $label = $newDataOption['label'];
        }

        $oldMembers = Util::explode(',', $oldValues[$groupFieldName]);
        $newMembers = Util::explode(',', $newValues[$groupFieldName]);

        if (count($newMembers) > $max) {
          throw new \Exception(
            $this->l->t('Number %d of requested participants for group %s is larger than the number %d of allowed participants.',
                        [ count($newMembers), $label, $max ]));
        }

        if ($multiplicity == FieldMultiplicity::GROUPOFPEOPLE && !empty($newMembers)) {
          // make sure that a group-option exists, clean up afterwards
          if (empty($newGroupId) || $newGroupId == Uuid::NIL) {
            $newGroupId = Uuid::create();
            $dataOption = (new Entities\ProjectParticipantFieldDataOption)
                        ->setField($participantField)
                        ->setKey($newGroupId);
            $this->persist($dataOption);
          }
        }

        // In order to compute the changeset in
        // PMETableViewBase::beforeUpdateDoUpdateAll() we need to
        // include all musicians referencing the new group into the
        // set of old members as well as all newMembers who already
        // reference a group. This will result in deletion of all old
        // members as well as deletion of references to other groups
        // (group membership is single select).
        // The current musician must always remain

        $oldMemberships = []; // musician_id => option_key
        foreach ($participantField->getFieldData() as $fieldDatum) {
          $musicianId = $fieldDatum->getMusician()->getId();
          $optionKey = $fieldDatum->getOptionKey();
          if (array_search($musicianId, $newMembers) !== false
              || $optionKey == $newGroupId
              || $musicianId == $pme->rec['musician_id']
          ) {
            $oldMemberships[$musicianId] = $musicianId.self::JOIN_KEY_SEP.$fieldDatum->getOptionKey();
          }
        }

        // recompute the old set of relevant musicians
        $oldValues[$groupFieldName] = implode(',', array_keys($oldMemberships));
        $oldValues[$keyName] = implode(',', array_values($oldMemberships));

        // recompute the new set of relevant musicians
        foreach ($newMembers as &$member) {
          $member .= self::JOIN_KEY_SEP.$newGroupId;
        }
        $newValues[$keyName] = implode(',', $newMembers);

        $changed[] = $groupFieldName;
        $changed[] = $keyName;
      default:
        break;
      }
    }
    $changed = array_values(array_unique($changed));
    return true;
  }

  /**
   * In particular remove no longer needed groupofpeople options
   */
  public function cleanupParticipantFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    /** @var Entities\ProjectParticipantField $participantField */
    foreach ($this->project['participantFields'] as $participantField) {
      if ($participantField->getMultiplicity() != Types\EnumParticipantFieldMultiplicity::GROUPOFPEOPLE()) {
        continue;
      }
      /** @var Entities\ProjEctParticipantFieldDataOption $dataOption */
      foreach ($participantField->getDataOptions() as $dataOption) {
        if ((string)$dataOption->getKey() != Uuid::NIL
            && count($dataOption->getFieldData()) == 0) {
          $participantField->getDataOptions()->removeElement($dataOption);
          $this->remove($dataOption);
          $this->flush();
        }
      }
    }
    return true;
  }

  // ??????
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
   * Export the default tabs family. Extra-tabs are inserted after the
   * personal data and before the misc-tab. The finance tab comes
   * before the personal data.
   */
  private function defaultTableTabs($useFinanceTab = false, $extraTabs = [])
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
    $personal = [
      [
        'id' => 'musician',
        'tooltip' => $this->toolTipsService['project-personaldata-tab'],
        'name' => $this->l->t('Personal data'),
      ],
    ];
    $post = [
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
      return array_merge($pre, $finance, $personal, $extraTabs, $post);
    } else {
      return array_merge($pre, $personal, $extraTabs, $post);
    }
  }

  /**
   * Export the description for the table tabs.
   */
  private function tableTabs($participantFields = false, $useFinanceTab = false)
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_iterable($participantFields)) {
      return $dfltTabs;
    }

    $extraTabs = [];
    foreach ($participantFields as $field) {
      if (empty($field['tab'])) {
        continue;
      }

      $extraTab = $field['tab'];
      foreach ($dfltTabs as $tab) {
        if ($extraTab === $tab['id'] || $extraTab === (string)$tab['name']) {
          $extraTab = null;
          break;
        }
      }
      if (!empty($extraTab)) {
        $newTab = [
          'id' => $extraTab,
          'name' => $this->l->t($extraTab),
          'tooltip' => $this->toolTipsService['participant-fields-extra-tab'],
        ];
        $dfltTabs[] = $newTab;
        $extraTabs[] = $newTab;
      }
    }

    return $this->defaultTableTabs($useFinanceTab, $extraTabs);
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
    case 'money':
    case FieldType::SERVICE_FEE:
    case FieldType::DEPOSIT:
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
