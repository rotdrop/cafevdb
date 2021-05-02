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
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;

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
  use FieldTraits\SepaAccountsTrait;
  use FieldTraits\ParticipantFieldsTrait;
  use FieldTraits\MusicianPhotoTrait;

  const TEMPLATE = 'project-participants';
  const TABLE = self::PROJECT_PARTICIPANTS_TABLE;

  /**
   * Join table structure. All update are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\ProjectParticipant::class,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    self::PROJECT_INSTRUMENTS_TABLE => [
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
    self::MUSICIAN_INSTRUMENTS_TABLE => [
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'instrument_id',
    ],
    // in order to get the participation in all projects
    self::TABLE . self::VALUES_TABLE_SEP . 'allProjects' => [
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'project_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'project_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
      ],
      'column' => 'id',
    ],
    // extra input fields depending on the type of the project,
    // e.g. service fees etc.
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'identifier' => [
        'project_id' => 'project_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'field_id' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_TABLE,
          'column' => 'id',
        ],
        'option_key' => false,
      ],
      'column' => 'option_key',
      'encode' => 'BIN2UUID(%s)',
    ],
  ];

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var PhoneNumberService */
  private $phoneNumberService;

  /** @var FinanceService */
  private $financeService;

  /** @var InsuranceService */
  private $insuranceService;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var ProjectService */
  private $projectService;

  /** @var Entities\Project */
  private $project;

  /** @var UserStorage */
  private $userStorage;

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
    , ProjectService $projectService
    , UserStorage $userStorage
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->financeService = $financeService;
    $this->insuranceService = $insuranceService;
    $this->participantFieldsService = $participantFieldsService;
    $this->projectService = $projectService;
    $this->userStorage = $userStorage;

    $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);

    $this->pme->overrideLabel('Add', 'Add Musician');
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

    $opts            = [];

    if (empty($projectName) || empty($this->projectId)) {
      throw new \InvalidArgumentException('Project-id and/or -name must be given ('.$projectName.' / '.$this->projectId.').');
    }

    $opts['filters']['AND'] = [
      '$table.project_id = '.$this->projectId,
    ];
    if (!$this->showDisabled) {
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

    // Tweak the join-structure with dynamic data.

    list($sepaJoin, $sepaFieldGenerator) = $this->renderSepaAccounts(
      'musician_id', [ $this->projectId, $this->membersProjectId ], $financeTab);
    $this->joinStructure = array_merge($this->joinStructure, $sepaJoin);

    list($participantFieldsJoin, $participantFieldsGenerator) =
      $this->renderParticipantFields($participantFields, 'project_id', $financeTab);
    $this->joinStructure = array_merge($this->joinStructure, $participantFieldsJoin);

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
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name', [
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

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'tab'      => [ 'id' => 'tab-all' ],
        'name'     => $this->l->t('User Id'),
        'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
        'input|LF' => 'H',
        // 'options'  => 'AVCPD',
        'select'   => 'T',
        'maxlen'   => 256,
        'sort'     => true,
        'display|ACP' => [
          'attributes' => function($op, $row, $k, $pme) {
            $surName = $row['qf'.($k-4)];
            $firstName = $row['qf'.($k-3)];
            $nickName = $row['qf'.($k-2)];
            $placeHolder = $this->projectService->defaultUserIdSlug($surName, $firstName, $nickName);
            return [
              'placeholder' => $placeHolder,
              'readonly' => true,
            ];
          },
          'postfix' => function($op, $pos, $row, $k, $pme) {
            $checked = 'checked="checked" ';
            return '<input id="pme-musician-user-id-slug"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock-unlock"
/><label class="pme-input pme-input-lock-unlock" for="pme-musician-user-id-slug"></label>';
          },
        ],
      ]);

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
          //'datemask' => 'd.m.Y H:i:s',
        ]
      );
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
          'join'        => '$join_col_fqn = '.$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.instrument_id',
          'filters'     => "FIND_IN_SET(id, (SELECT GROUP_CONCAT(DISTINCT instrument_id) FROM ".self::MUSICIAN_INSTRUMENTS_TABLE." mi WHERE \$record_id[project_id] = ".$this->projectId." AND \$record_id[musician_id] = mi.musician_id GROUP BY mi.musician_id))",
        ],
        'values|LFV' => [
          'table'       => $l10nInstrumentsTable, // self::INSTRUMENTS_TABLE,
          'column'      => 'id',
          'description' => 'l10n_name',
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.instrument_id',
          'filters'     => "FIND_IN_SET(id, (SELECT GROUP_CONCAT(DISTINCT instrument_id) FROM ".self::PROJECT_INSTRUMENTS_TABLE." pi WHERE ".$this->projectId." = pi.project_id GROUP BY pi.project_id))",
        ],
        //'values2' => $this->instrumentInfo['byId'],
        'valueGroups' => $this->instrumentInfo['idGroups'],
      ]);
    $this->joinTables[self::INSTRUMENTS_TABLE] = 'PMEjoin'.(count($opts['fdd'])-1);

    $opts['fdd'][$this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order')] = [
      'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'HRS',
      'sort'        => true,
      'values' => [
        'column' =>  'sort_order',
        'orderby' => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE], ],
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
     CONCAT(".$this->joinTables[self::INSTRUMENTS_TABLE].".name,
            ' ',
            \$join_col_fqn),
     NULL)
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        // copy/change only include non-zero voice
        'sql|CP' => "GROUP_CONCAT(
  DISTINCT
  IF(".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice > 0,
    CONCAT_WS(
      '".self::JOIN_KEY_SEP."',
      ".$this->joinTables[self::INSTRUMENTS_TABLE].".id,
      ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice),
    NULL
  )
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
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
    pi.project_id = $this->projectId
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
        'align|LF' => 'center',
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
    ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".instrument_id,
    ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader)
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
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
    pi.project_id = $this->projectId
  GROUP BY pi.instrument_id
  HAVING (MAX(pin.voice) = 0 OR pi.voice > 0)",
         'column' => 'value',
         'description' => [ 'l10n_name', 'IF($table.voice = 0, \'\', CONCAT(\' \', $table.voice))' ],
         'orderby' => '$table.sort_order',
         'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
         'join' => '$join_table.project_id = $main_table.project_id AND $join_table.musician_id = $main_table.musician_id',
       ],
       'values2|LF' => [ 0 => '', 1 => '&alpha;' ],
       'align|LF' => 'center',
       'tooltip' => $this->l->t("Set to `%s' in order to mark the section leader",
                                [ "&alpha;" ]),
      ]);

    $opts['fdd']['registration'] = [
      'name|LF' => ' &#10004;',
      'name|CAPDV' => $this->l->t("Registration"),
      'tab' => [ 'id' => [ 'project', 'instrumentation' ] ],
      'options'  => 'LAVCPDF',
      'select' => 'O',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2' => [ 0 => '', 1 => '&#10004;' ],
      'tooltip' => $this->l->t("Set to `%s' in order to mark participants who passed a personally signed registration form to us.",
                               [ "&#10004;" ]),
      'display|LF' => [
        'popup' => function($data) {
          return $this->toolTipsService['registration-mark'];
        },
      ],
      'css'      => [ 'postfix' => ' registration tooltip-top align-center' ],
    ];

    $fdd = [
      'name'        => $this->l->t('All Instruments'),
      'tab'         => [ 'id' => [ 'musician', 'instrumentation' ] ],
      'css'         => ['postfix' => ' musician-instruments tooltip-top no-chosen selectize drag-drop'],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => ($expertMode
                        ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY '.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'
                        : 'GROUP_CONCAT(DISTINCT IF('.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.deleted IS NULL, $join_col_fqn, NULL) ORDER BY '.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'),
      'select'      => 'M',
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'name',
        'orderby'     => '$table.sort_order ASC',
        'join'        => '$join_col_fqn = '.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'deleted', [
        'name'    => $this->l->t('Disabled Instruments'),
        'tab'     => [ 'id' => [ 'musician', 'instrumentation' ] ],
        'sql'     => "GROUP_CONCAT(DISTINCT IF(\$join_col_fqn IS NULL, NULL, \$join_table.instrument_id))",
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

    if (!empty($monetary) || ($this->projectId == $this->membersProjectId)) {

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

              $table = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
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

            if ($this->projectId == $this->membersProjectId) {
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
    $participantFieldsGenerator($opts['fdd']);

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
        'join' => '$join_table.id = '.$this->joinTables[self::TABLE].'.project_id'
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
        return $this->photoImageLink($musicianId, $action, $stamp);
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
          list('musician' => $musician, 'categories' => $categories) = $this->musicianFromRow($row, $pme);
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

    /*
     *
     **************************************************************************
     *
     * SEPA information
     *
     */

    $sepaFieldGenerator($opts['fdd']);

    /*
     *
     *
     **************************************************************************
     *
     * End field definitions.
     *
     */

    $opts['triggers']['update']['before'][] = [ $this, 'ensureUserIdSlug' ];
    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateSanitizeParticipantFields' ];
    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateEnsureInstrumentationNumbers' ];
    $opts['triggers']['update']['before'][] = [ $this, 'extractInstrumentRanking' ];
    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['update']['before'][] = [ $this, 'cleanupParticipantFields' ];
    $opts['triggers']['update']['before'][] = [ $this, 'renameProjectParticipantFolders' ];

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


}
