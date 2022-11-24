<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\PageRenderer;

use InvalidArgumentException;

use chillerlan\QRCode\QRCode;

use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
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
use OCA\CAFEVDB\Common\Functions;

/**Table generator for Instruments table. */
class ProjectParticipants extends PMETableViewBase
{
  use FieldTraits\SepaAccountsTrait;
  use FieldTraits\ParticipantFieldsTrait;
  use FieldTraits\MusicianPhotoTrait;
  use FieldTraits\ParticipantTotalFeesTrait;
  use FieldTraits\MailingListsTrait;
  use FieldTraits\MusicianEmailsTrait;
  use FieldTraits\AllProjectsTrait;

  const TEMPLATE = 'project-participants';
  const TABLE = self::PROJECT_PARTICIPANTS_TABLE;

  private const EXTRA_VOICES = 2;
  private const INSERT_VOICES = 8;

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
    self::INSTRUMENTS_TABLE => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_INSTRUMENTS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    self::MUSICIAN_INSTRUMENTS_TABLE => [
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'instrument_id',
    ],
    self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians' => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::MUSICIAN_INSTRUMENTS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    self::MUSICIAN_PHOTO_JOIN_TABLE => [
      'entity' => Entities\MusicianPhoto::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'owner_id' => 'musician_id',
        'image_id' => false,
      ],
      'column' => 'image_id',
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
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => 'project_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_REMOVE_EMPTY,
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
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDataOption::class,
      'flags' => 0,
      'identifier' => [
        'field_id' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_TABLE,
          'column' => 'id',
        ],
        'key' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE,
          'column' => 'option_key',
        ],
      ],
      'column' => 'key',
      'encode' => 'BIN2UUID(%s)',
    ],
  ];

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var PhoneNumberService */
  private $phoneNumberService;

  /** @var FinanceService */
  private $financeService;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var ProjectParticipantFieldsService */
  protected $participantFieldsService;

  /** @var ProjectService */
  protected $projectService;

  /** @var Entities\Project */
  private $project;

  /** @var UserStorage */
  protected $userStorage;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    GeoCodingService $geoCodingService,
    ContactsService $contactsService,
    PhoneNumberService $phoneNumberService,
    FinanceService $financeService,
    InstrumentInsuranceService $insuranceService,
    ProjectParticipantFieldsService $participantFieldsService,
    ProjectService $projectService,
    UserStorage $userStorage,
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
    $this->projectName = $this->project->getName();

    $this->pme->overrideLabel('Add', $this->l->t('Add Musician'));
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove the musician from %s?', [ $this->projectName ]);
    } elseif ($this->viewOperation()) {
      return $this->l->t('Display of all stored data for the shown musician.');
    } elseif ($this->changeOperation()) {
      return $this->l->t('Edit the data of the displayed musician.');
    }
    return $this->l->t('Instrumentation for Project "%s"', [ $this->projectName ]);
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $instruments     = $this->instruments;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['css']['postfix'] = [
      self::CSS_TAG_DIRECT_CHANGE,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
    ];

    if (empty($projectName) || empty($this->projectId)) {
      throw new InvalidArgumentException($this->l->t('Project-id and/or -name must be given (%1$s / %2$s)', [ $projectName, $this->projectId ]));
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
      'dataPrefix' => [
        'musicians' => self::MUSICIANS_TABLE . self::JOIN_FIELD_NAME_SEPARATOR,
      ],
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'musician_id' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice'),
      '-' . $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'section_leader'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'display_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'sur_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'first_name'),
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'nick_name'),
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDF';
    $opts['options'] .= 'M'; // misc

    // controls display an location of edit/misc buttons
    $opts['navigation'] = self::PME_NAVIGATION_MULTI;

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    $participantFields = $this->project['participantFields'];

    // count number of finance fields
    $extraFinancial = 0;
    foreach ($participantFields as $field) {
      $extraFinancial += (int)($field['dataType'] == FieldType::SERVICE_FEE);
    }
    if ($extraFinancial > 0) {
      $useFinanceTab = true;
      $financeTab = 'finance';
    } else {
      $useFinanceTab = false;
      $financeTab = 'project';
    }

    // Tweak the join-structure with dynamic data.
    list($allProjectsJoin, $allProjectsFieldGenerator) = $this->renderAllProjectsField(
      musicianIdField: 'musician_id',
      tableTab: 'musician',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $allProjectsJoin);

    list($emailJoin, $emailFieldGenerator) = $this->renderMusicianEmailFields(
      musicianIdField: 'musician_id',
      tableTab: 'contactdata',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $emailJoin);

    list($sepaJoin, $sepaFieldGenerator) = $this->renderSepaAccounts(
      'musician_id', $this->projectId, $this->membersProjectId, $financeTab);
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
      $opts['display'] ?? [], [
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
      'input'    => ($expertMode ? 'R' : 'RH'),
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
      'input'    => ($expertMode ? 'R' : 'RH'),
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    ];

    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      $joinInfo['table'] = $table;
      switch ($table) {
        case self::INSTRUMENTS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
          break;
        case self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians':
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
          break;
        case self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'label');
          break;
        default:
          break;
      }
    });

    $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'sur_name',
      [
        'name'     => $this->l->t('Name'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'first_name',
      [
        'name'     => $this->l->t('First Name'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'nick_name',
      [
        'name'     => $this->l->t('Nickname'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'sql|LFVD' => 'IF($join_col_fqn IS NULL OR $join_col_fqn = \'\', $table.first_name, $join_col_fqn)',
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $nickNamePlaceholder = $this->l->t('e.g. Cathy');
            $firstName = $row['qf'.($k-1)];
            $lockedPlaceholder = $firstName ?: $nickNamePlaceholder;
            $unlockedPlaceholder = $this->l->t('e.g. Cathy');
            if (empty($row['qf'.$k])) {
              return [
                'placeholder' => $lockedPlaceholder,
                'readonly' => true,
                'data-placeholder' => $unlockedPlaceholder,
              ];
            } else {
              return [
                'placeholder' => $unlockedPlaceholder,
                'readonly' => false,
                'data-placeholder' => $lockedPlaceholder,
              ];
            }
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-nickname"
  '.$checked.'
  type="checkbox"
  class="pme-input pme-input-lock lock-empty"/>
<label class="pme-input pme-input-lock lock-empty"
       title="'.$this->toolTipsService['pme:input:lock-empty'].'"
       for="pme-musician-nickname"></label>';
          },
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name', [
        'name'     => $this->l->t('Display-Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'css'      => [ 'postfix' => [ 'default-readonly', 'tab-musician-readwrite', 'tab-all-readwrite', ], ],
        'sql|LFVD' => parent::musicianPublicNameSql(),
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $displayNamePlaceholder = $this->l->t('e.g. Doe, Cathy');
            $surName = $row['qf'.($k-3)];
            $firstName = $row['qf'.($k-2)];
            $nickName = $row['qf'.($k-1)];
            $lockedPlaceholder = $op == 'add' ? $displayNamePlaceholder : $surName.', '.($nickName?:$firstName);
            $unlockedPlaceholder = $this->l->t('e.g. Doe, Cathy');
            if (empty($row['qf'.$k])) {
              return [
                'placeholder' => $lockedPlaceholder,
                'readonly' => true,
                'data-locked-placeholder' => $lockedPlaceholder,
                'data-unlocked-placeholder' => $unlockedPlaceholder,
              ];
            } else {
              return [
                'placeholder' => $unlockedPlaceholder,
                'readonly' => false,
                'data-locked-placeholder' => $lockedPlaceholder,
                'data-unlocked-placeholder' => $unlockedPlaceholder,
              ];
            }
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-displayname"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-empty"
/><label class="pme-input pme-input-lock lock-empty"
         title="'.$this->toolTipsService['pme:input:lock-empty'].'"
         for="pme-musician-displayname"></label>';
          },
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'tab'      => [ 'id' => 'musician' ],
        'name'     => $this->l->t('User Id'),
        'css'      => [ 'postfix' => [ 'musician-name', ], ],
        'input|LF' => 'H',
        // 'options'  => 'AVCPD',
        'select'   => 'T',
        'maxlen'   => 256,
        'sort'     => true,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $surName = $row['qf'.($k-4)];
            $firstName = $row['qf'.($k-3)];
            $nickName = $row['qf'.($k-2)];
            $placeHolder = $this->projectService->defaultUserIdSlug($surName, $firstName, $nickName);
            return [
              'placeholder' => $placeHolder,
              'readonly' => true,
            ];
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = 'checked="checked" ';
            return '<input id="pme-musician-user-id-slug"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-unlock"
/><label class="pme-input pme-input-lock lock-unlock"
         title="'.$this->toolTipsService['pme:input:lock-unlock'].'"
         for="pme-musician-user-id-slug"></label>';
          },
        ],
      ]);

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
          'dateformat' => 'medium',
          'timeformat' => 'short',
          'maxlen' => 19,
        ]
      );
      Util::unsetValue($opts['fdd']['deleted']['css']['postfix'], 'date');
      $opts['fdd']['deleted']['css']['postfix'][] = 'datetime';
    }

    $fdd = [
      'tab'         => [ 'id' => [ 'instrumentation' ] ],
      'name'        => $this->l->t('Project Instrument'),
      'css'         => [
        'postfix' => [
          'project-instruments',
          'tooltip-top',
          'select-wide',
        ],
      ],
      'display|LVF' => ['popup' => 'data'],
      'sql|VDCP'    => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'select'      => 'M',
      'values' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ '$table.l10n_name' ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'orderby'     => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $fdd['values|VDPC'] = array_merge($fdd['values'], [
      'filters' => '$table.id IN (SELECT DISTINCT instrument_id
  FROM '.self::MUSICIAN_INSTRUMENTS_TABLE.' mi
  WHERE $record_id[project_id] = '.$this->projectId.' AND $record_id[musician_id] = mi.musician_id)',
    ]);
    $fdd['values|LFV'] = array_merge($fdd['values'], [
      'filters' => '$table.id IN (SELECT DISTINCT instrument_id
  FROM '.self::PROJECT_INSTRUMENTS_TABLE.' pi
  WHERE '.$this->projectId.' = pi.project_id)',
    ]);

    // Use $fdd defined above after tweaking its values
    list($instrumentsFddIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    // kind of a hack, in principle this should go to the global join structure
    // $this->joinTables[self::INSTRUMENTS_TABLE] = 'PMEjoin'.(count($opts['fdd'])-1);

    $opts['fdd'][$this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order')] = [
      'tab'         => [ 'id' => [ 'instrumentation' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'HRS',
      'select'      => 'M',
      'sort'     => true,
      'values' => [
        'column' => 'sort_order',
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
        'css'      => [
          'postfix' => [
            'allow-empty',
            'no-search',
            'instrument-voice',
            'select-wide',
          ],
        ],
        'display|CAP' => [
          'prefix' => function($op, $when, $k, $row, $pme) {
            return '<div class="cell-wrapper">
  <div class="dropdown-menu">';
          },
          'postfix' => function($op, $when, $k, $row, $pme) {
            $html = '</div>
'; // close dropdown-menu

            $instrumentsIndex = $k - 2;
            $instruments = Util::explode(',', $row['qf'.$instrumentsIndex]);
            $instrumentNames = $pme->set_values($instrumentsIndex)['values'];

            $templateParameters = [
              'instruments' => $instruments,
              'dataName' => $pme->cgiDataName($this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice').'[]'),
              'inputLabel' => function($instrument) use ($instrumentNames) {
                return $instrumentNames[$instrument];
              },
              'toolTips' => $this->toolTipsService,
              'toolTipSlug' => $this->toolTipSlug('instrument-voice-request'),
            ];

            $template = new TemplateResponse($this->appName(), 'fragments/instrument-voices', $templateParameters, 'blank');
            $html .= $template->render();

            return $html;
          },
        ],
        'sql|VD' => "GROUP_CONCAT(DISTINCT
  IF(\$join_col_fqn > 0,
     CONCAT(".$this->joinTables[self::INSTRUMENTS_TABLE].".l10n_name,
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
  CONCAT(pi.instrument_id,'".self::JOIN_KEY_SEP."', IF(n.seq <= GREATEST(" . self::EXTRA_VOICES . ", MAX(pin.voice)), n.seq, '?')) AS value,
  pi.project_id,
  pi.musician_id,
  i.id AS instrument_id,
  i.name,
  COALESCE(ft.content, i.name) AS l10n_name,
  i.sort_order,
  GROUP_CONCAT(IF(pin.voice = n.seq, pin.quantity, NULL)) AS quantity,
  MAX(pin.voice) AS number_of_voices,
  n.seq
  FROM " . self::PROJECT_INSTRUMENTS_TABLE . " pi
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pi.instrument_id
  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." ft
    ON ft.locale = '".($this->l10n()->getLocaleCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
  LEFT JOIN ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin
    ON pin.instrument_id = pi.instrument_id AND pin.project_id = pi.project_id
  JOIN " . self::SEQUENCE_TABLE . " n
    ON n.seq <= (" . self::EXTRA_VOICES . " + 1 + pin.voice)
    AND n.seq >= 1
    AND n.seq <= (1+(SELECT GREATEST(" . self::EXTRA_VOICES . ", MAX(pin2.voice)) FROM ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin2))
  WHERE
    pi.project_id = \$record_id[project_id]
  GROUP BY
    pi.project_id, pi.musician_id, pi.instrument_id, n.seq
  ORDER BY
    i.sort_order ASC, n.seq ASC",
          'column' => 'value',
          'description' => [
            'columns' => [ '$table.l10n_name', 'IF($table.seq <= GREATEST(' . self::EXTRA_VOICES . ', $table.number_of_voices), $table.seq, \'?\')' ],
            'divs' => ' ',
          ],
          'titles' => 'IF($table.seq > GREATEST(' . self::EXTRA_VOICES . ', $table.number_of_voices),
  "' . $this->toolTipsService['page-renderer:participants:voice:define-new'] . '",
  NULL
)',
          'orderby' => '$table.sort_order ASC, $table.seq ASC',
          'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
          //'join' => '$join_table.musician_id = $main_table.musician_id AND $join_table.project_id = $main_table.project_id',
          'join' => false,
        ],
        'values2|LF' => [ '0' => $this->l->t('n/a') ] + array_combine(range(1, self::INSERT_VOICES), range(1, self::INSERT_VOICES)),
        'align|LF' => 'center',
        'tooltip|CAP' => $this->toolTipsService['page-renderer:participants:voice'],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'section_leader',
      [
       'name|LF' => ' &alpha;',
       'name|CAPVD' => $this->l->t("Section Leader"),
       'tab' => [ 'id' => 'instrumentation' ],
       'css'      => [ 'postfix' => [ 'section-leader', 'tooltip-top', ], ],
       'default' => false,
       'options'  => 'LAVCPDF',
       'select' => 'C',
       'maxlen' => '1',
       'sort' => true,
       'escape' => false,
       'sql|CAPDV' => "GROUP_CONCAT(
  DISTINCT
  IF(".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader IS NULL
     OR ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader = 0,
    NULL,
    CONCAT_WS(
      '".self::JOIN_KEY_SEP."',
      CONCAT_WS('".self::COMP_KEY_SEP."',
        ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".instrument_id,
        ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice
      ),
      ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".section_leader)
  )
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
       'display|LF' => [ 'popup' => function($data) {
         return $this->toolTipsService['section-leader-mark'];
       }],
       'values|CAPDV' => [
         'table' => "SELECT
  CONCAT_WS('".self::JOIN_KEY_SEP."', CONCAT_WS('".self::COMP_KEY_SEP."', pi.instrument_id, pi.voice), 1) AS value,
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
    ON ft.locale = '".($this->l10n()->getLocaleCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
  WHERE
    pi.project_id = $this->projectId
  GROUP BY pi.instrument_id, pi.musician_id
  HAVING (MAX(pin.voice) = 0 OR pi.voice > 0)",
         'column' => 'value',
         'description' => [ 'l10n_name', 'IF($table.voice = 0, \'\', CONCAT(\' \', $table.voice))' ],
         'orderby' => '$table.sort_order',
         'filters' => '$record_id[project_id] = project_id AND $record_id[musician_id] = musician_id',
         'join' => false, //'$join_table.project_id = $main_table.project_id AND $join_table.musician_id = $main_table.musician_id',
       ],
       'values2|LF' => [ 0 => '', 1 => '&alpha;' ],
       'align|LF' => 'center',
       'tooltip|LFVD' => $this->toolTipsService['page-renderer:participants:section-leader:view'],
       'tooltip|CAP' => $this->toolTipsService['page-renderer:participants:section-leader'],
      ]);

    // @todo
    // Replace by registration status
    // - preliminary
    //   just entered in the instrumentation table with or without communication
    // - application
    //   written and potentially signed application
    // - accepted
    //   participant has been accepted by the organizers and receives an email
    $opts['fdd']['registration'] = [
      // 'name|LF' => ' &#10004;',
      // 'name|CAPDV' => $this->l->t("Participation"),
      'name' => $this->l->t("Participation"),
      'tab' => [ 'id' => [ 'project', 'instrumentation' ] ],
      'options'  => 'LAVCPDF',
      'select' => 'O',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'sql'  => 'IFNULL($join_col_fqn, 0)',
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2' => [ 0 => $this->l->t('tentatively'), 1 => $this->l->t('confirmed'), ],
      // 'values2|ACP' => [ 0 => $this->l->t('tentatively'), 1 => $this->l->t('confirmed'), ],
      // 'values2|DV' => [ 0 => $this->l->t('tentatively'), 1 => $this->l->t('confirmed'), ],
      // 'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
      // 'values2|LF' => [
      // 0 => '',
      // 1 => '&#10004;'
      // ],
      'tooltip|LFDV' => $this->l->t(
        "Set to `%s' in order to mark participants who passed a personally signed registration form to us.", [ "&#10004;" ]),
      'tooltip|CAP' => $this->l->t("Check in order to mark participants who passed a personally signed registration form to us."),
      'display|LF' => [
        'popup' => function($data) {
          return $this->toolTipsService['registration-mark'];
        },
      ],
      'css'      => [ 'postfix' => [ 'registration', 'tooltip-top', 'align-center', ], ],
    ];

    $fdd = [
      'name' => $this->l->t('All Instruments'),
      'tab'  => [ 'id' => [ 'musician', 'instrumentation' ] ],
      'css'  => [
        'postfix' => [
          'musician-instruments',
          'tooltip-top',
          'no-chosen',
          'selectize',
          'drag-drop',
          'select-wide',
        ],
      ],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => 'GROUP_CONCAT(DISTINCT
  IF('.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.deleted IS NULL, $join_col_fqn, NULL)
  ORDER BY '.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)',
      'select'      => 'M',
      'values' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ 'l10n_name', ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby'     => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians'], ],
      ],
      'valueGroups' => $this->instrumentInfo['idGroups'],
      'filter' => [
        'having' => true,
      ],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    // Use $fdd defined above after tweaking its values
    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'deleted', [
        'name'    => $this->l->t('Disabled Instruments'),
        'tab'     => [ 'id' => [ 'musician', 'instrumentation' ] ],
        'css'     => [ 'postfix' => [ 'selectize', 'no-chosen', ], ],
        'sql'     => 'GROUP_CONCAT(DISTINCT IF($join_col_fqn IS NULL, NULL, $join_table.instrument_id))',
        'default' => null,
        'select'  => 'M',
        'input'   => 'SR',
        'tooltip' => $this->toolTipsService['musician-instruments-disabled'],
        'values2' => $this->instrumentInfo['byId'],
        'valueGroups' => $this->instrumentInfo['idGroups'],
        'filter' => [
          'having' => true,
          // 'flags' => PHPMyEdit::OMIT_SQL|PHPMyEdit::OMIT_DESC,
        ],
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
    list($memberStatusFddIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'member_status',
      [
        'name'    => $this->l->t('Member Status'),
        'select'  => 'D',
        'maxlen'  => 128,
        'css'     => ['postfix' => [ 'memberstatus', 'tooltip-wide', ], ],
        'values2' => $this->memberStatusNames,
        'tooltip' => $this->toolTipsService['member-status'],
      ]);

    // soft-deleted musician kept to keep the instrumentation for the old project
    list(, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'deleted', array_merge(
        $this->defaultFDD['deleted'], [
          'input' => ($this->showDisabled && $this->expertMode ? '' : 'HR'),
          'name' => $this->l->t('Musician Deleted'),
          'dateformat' => 'medium',
          'timeformat' => 'short',
          'maxlen' => 19,
        ]
      )
    );
    Util::unsetValue($opts['fdd'][$fieldName]['css']['postfix'], 'date');
    $opts['fdd'][$fieldName]['css']['postfix'][] = 'datetime';

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'cloud_account_deactivated', [
        'name' => $this->l->t('Cloud Account Deactivated'),
        'input' => ($this->expertMode ? null : 'HR'),
        'select' => 'C',
        'css' => [ 'postfix' => [ 'cloud-account-deactivated', ], ],
        'sort' => true,
        'default' => null,
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVDF' => [
          0 => '',
          1 => '&#10004;',
        ],
        'align|LF' => 'center',
        'sql|LVDF' => 'COALESCE($join_col_fqn, 0)',
        'tooltip' => $this->toolTipsService['page-renderer:musicians:cloud-account-deactivated'],
        'display' => [ 'popup' => 'tooltip' ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'cloud_account_disabled', [
        'name' => $this->l->t('Hidden from Cloud'),
        'input' => ($this->expertMode ? null : 'HR'),
        'select' => 'C',
        'css' => [ 'postfix' => [ 'cloud-account-disabled', ], ],
        'sort' => true,
        'default' => null,
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVDF' => [
          0 => '',
          1 => '&#10004;',
        ],
        'align|LF' => 'center',
        'sql|LVDF' => 'COALESCE($join_col_fqn, 0)',
        'tooltip' => $this->toolTipsService['page-renderer:musicians:cloud-account-disabled'],
        'display' => [ 'popup' => 'tooltip' ],
      ]);

    /*
     *
     **************************************************************************
     *
     * project fee and debit mandates information
     *
     */

    $monetary = $this->participantFieldsService->monetaryFields($this->project);
    if ($monetary->count() > 0 || ($this->projectId == $this->membersProjectId)) {
      $this->makeTotalFeesField($opts['fdd'], $monetary, $financeTab);
    }

    $totalFeesTargetIndex = count($opts['fdd']);

    /*
     *
     **************************************************************************
     *
     * extra columns like project fee, deposit etc.
     *
     */

    // Generate input fields for the extra columns
    $subTotals = [];
    $participantFieldsGenerator($opts['fdd'], $subTotals);

    if (!empty($subTotals)) {
      $totalFeesIndex = count($opts['fdd']);
      $this->makeTotalFeesFields($opts['fdd'], $subTotals, $financeTab);

      $baseFdd = array_slice($opts['fdd'], 0, $totalFeesTargetIndex);
      $participantFieldsFdd = array_slice($opts['fdd'], $totalFeesTargetIndex, $totalFeesIndex - $totalFeesTargetIndex);
      $totalFeesFdd = array_slice($opts['fdd'], $totalFeesIndex);

      $opts['fdd'] = $baseFdd + $totalFeesFdd + $participantFieldsFdd;
    }

    /*
     *
     **************************************************************************
     *
     * several further fields from Musicians table
     *
     */

    $allProjectsFieldGenerator($opts['fdd']);

    $emailFieldGenerator($opts['fdd']);

    $opts['fdd']['project_mailing_list'] = $this->projectListSubscriptionControls(override: [
      'sql' => $this->joinTables[self::MUSICIANS_TABLE] . '.email',
      'tab' => [ 'id' => [ 'musician', 'contactdata', ], ],
      'css' => [ 'postfix' => [ 'project-mailing-list', ], ],
      'name' => $this->l->t('Project Mailing List'),
    ]);

    $opts['fdd']['announcements_mailing_list'] = $this->announcementsSubscriptionControls(override: [
      'sql' => $this->joinTables[self::MUSICIANS_TABLE] . '.email',
      'tab' => [ 'id' => [ 'musician', 'contactdata', ], ],
      'css' => [ 'postfix' => [ 'announcements-mailing-list', ], ],
      'name' => $this->l->t('Announcements List'),
    ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'mobile_phone',
      [
        'name'     => $this->l->t('Mobile Phone'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'phone-number', ], ],
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
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'phone-number', ], ],
        'display'  => [
          'popup' => function($data) {
            return $this->phoneNumberService->metaData($data, null, '<br/>');
          }
        ],
        'nowrap'   => true,
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'address_supplement',
      [
        'name'     => $this->l->t('Address Supplement'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'address-supplement', ], ],
        'maxlen'   => 128,
        'input|LF' => $expertMode ? '' : 'H',
        'tooltip'  => $this->toolTipsService['page-renderer:musicians:address-supplement'],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'street',
      [
        'name'     => $this->l->t('Street'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'street', ], ],
        'maxlen'   => 128,
        'sql|FL'   => 'CONCAT(
  IF(COALESCE($join_table.address_supplement, "") <> "",
    CONCAT($join_table.address_supplement, ", "),
    ""),
  $join_table.street, COALESCE(CONCAT(" ", $join_table.street_number), ""))',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'street_number',
      [
        'name'     => $this->l->t('Street Number'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'street-number', ], ],
        'maxlen'   => 32,
        'size'     => 11,
        'input|LF' => $expertMode ? '' : 'H',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'postal_code',
      [
        'name'     => $this->l->t('Postal Code'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'postal-code', ], ],
        'maxlen'   => 11,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'city',
      [
        'name'     => $this->l->t('City'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'city', ], ],
        'maxlen'   => 128,
      ]);

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'country',
      [
        'name'     => $this->l->t('Country'),
        'tab'      => [ 'id' => [ 'musician', 'contactdata', ], ],
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => $this->getConfigValue('streetAddressCountry'),
        'css'      => [ 'postfix' => [ 'musician-address', 'country', 'chosen-dropup', 'allow-empty', ], ],
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
        'css'      => ['postfix' => [ 'remarks', 'tooltip-top', 'squeeze-subsequent-lines', ], ],
        'textarea' => [
          'css' => 'wysiwyg-editor',
          'rows' => 5,
          'cols' => 50,
        ],
        'display|LF' => [
          'popup' => 'data',
          'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
        ],
        'escape' => false,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'language',
      [
        'tab'      => ['id' => 'musician'],
        'name'     => $this->l->t('Language'),
        'css'      => [ 'postfix' => [ 'musician-language', 'chosen-dropup', 'allow-empty', ], ],
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => 'Deutschland',
        'values2'  => $this->localeLanguageNames(),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_PHOTO_JOIN_TABLE, 'image_id', [
        'tab'      => ['id' => 'miscinfo'],
        'input' => 'VRS',
        'name' => $this->l->t('Photo'),
        'select' => 'T',
        'options' => 'APVCD',
        'php' => function($imageId, $action, $k, $row, $recordId, $pme) {
          $musicianId = $recordId['musician_id'] ?? 0;
          return $this->photoImageLink($musicianId, $action, $imageId);
        },
        'css' => ['postfix' => [ 'photo', ], ],
        'default' => '',
        'sort' => false
      ]);

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '$main_table.musician_id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        switch ($action) {
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
        'css'      => [ 'postfix' => [ 'musician-uuid', 'clip-long-text', 'tiny-width', ], ],
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
          "nowrap" => true,
          "options" => 'LFAVCPDR',
          'timeformat' => 'medium',
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'created',
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Created"),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
          'timeformat' => 'medium',
        ]));

    /*
     *
     **************************************************************************
     *
     * SEPA information
     *
     */

    if ($monetary->count() > 0 || $this->membersProjectId == $this->projectId) {
      $sepaFieldGenerator($opts['fdd']);
    }

    /*
     *
     *
     **************************************************************************
     *
     * End field definitions.
     *
     */

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'ensureUserIdSlug' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateSanitizeParticipantFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateEnsureInstrumentationNumbers' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateRemoveDependentVoices' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'cleanupParticipantFields' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeDeleteTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {

      if (!empty($row[$this->queryField('deleted', $pme->fdd)])
          || !empty($row[$this->joinQueryField(self::MUSICIANS_TABLE, 'deleted', $pme->fdd)])) {
        // disable misc-checkboxes for soft-deleted musicians in order to
        // avoid sending them bulk-email.
        $pme->options = str_replace('M', '', $opts['options']);
      } else {
        $pme->options = $opts['options'];
      }
      return true;
    };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::OPERATION_LIST][PHPMyEdit::TRIGGER_PRE][] = [ $this, 'totalFeesPreFilterTrigger' ];

    $opts['cgi']['persist']['memberStatusFddIndex'] = $memberStatusFddIndex;
    $opts['cgi']['persist']['instrummentsFddIndex'] = $instrumentsFddIndex;

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * When removing an instrument any pending voice(s) have to be removed, too.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateRemoveDependentVoices(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    // sanitize instrumentation numbers
    $instrumentsColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id');
    $voicesColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice');

    $debugColumns = [ $instrumentsColumn, $voicesColumn ];
    $this->debugPrintValues($oldValues, $changed, $newValues, $debugColumns, 'before');

    // [ProjectInstruments:instrument_id] => 3 [ProjectInstruments:voice] => 5:1

    foreach (['old', 'new'] as $dataSet) {
      $dataArray = $dataSet . 'Values';
      ${$dataSet . 'Instruments'} = Util::explode(',', ${$dataArray}[$instrumentsColumn]??'');
      ${$dataSet . 'Voices'} =  Util::explodeIndexedMulti(${$dataArray}[$voicesColumn]??'', null, ',', self::JOIN_KEY_SEP);

      // Add the zero "unvoice" to the voices if no other voices are configured
      foreach (${$dataSet . 'Instruments'} as $instrument) {
        if (empty(${$dataSet . 'Voices'}[$instrument])) {
          ${$dataSet . 'Voices'}[$instrument] = [ 0 ];
        }
      }

      // Remove any voice for which no instrument is configured in the
      // respective data set
      foreach (${$dataSet . 'Voices'} as $instrument => $voices) {
        if (array_search($instrument, ${$dataSet . 'Instruments'}) === false) {
          $this->debug('REMOVE VOICES ' . implode(',', $voices) . ' FOR INSTRUMENT ' . $instrument);
          unset(${$dataSet . 'Voices'}[$instrument]);
        }
      }

      // implode things again, instruments remains unchanged
      ${$dataArray}[$voicesColumn] = Util::implodeIndexedMulti(${$dataSet . 'Voices'}, ',', self::JOIN_KEY_SEP);
    }

    // recompute changeset
    Util::unsetValue($changed, $voicesColumn);
    if ($oldValues[$voicesColumn] !== $newValues[$voicesColumn]) {
      $changed[] = $voicesColumn;
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, $debugColumns, 'after');

    return true;
  }

  /**
   * Make sure at least a dummy instrumentation number exists when
   * adding people to project instruments.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateEnsureInstrumentationNumbers(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $voiceField = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice');
    $instrumentField = $this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id');

    // $debugColumns = [ $instrumentField, $voiceField, ];
    // $this->debugPrintValues($oldValues, $changed, $newValues, $debugColumns, 'before');

    if (array_search($voiceField, $changed) === false
        && array_search($instrumentField, $changed) === false) {
      // nothing to do
      $this->debug('UNCHANGED INSTRUMENTS');
      return true;
    }

    // only the new values should matter ...
    $instrumentVoices = [];
    $instruments = Util::explode(',', $newValues[$instrumentField]);
    foreach ($instruments as $instrument) {
      $instrumentVoices[$instrument] = [ 0 ];
    }
    $voiceValues = Util::explodeIndexedMulti($newValues[$voiceField]);

    $this->debug('VOICE VALUES '.print_r($voiceValues, true));

    foreach ($instrumentVoices as $instrument => &$voices) {
      $voices = array_merge($voices, $voiceValues[$instrument]??[]);
    }
    unset($voices); // break reference

    $this->debug('VOICES '.print_r($instrumentVoices, true));

    $instrumentationNumbers = $this->project->getInstrumentationNumbers();
    foreach ($instrumentVoices as $instrumentId => $voices) {
      foreach ($voices as $voice) {
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
          $this->flush();
        }
      }
    }

    // $this->debugPrintValues($oldValues, $changed, $newValues, $debugColumns, 'after');

    return true;
  }

  /**
   * Translate the tab-name to an id if the name is set in the tab
   * definitions of the table. This is needed by the
   * ParticipantFieldsTrait in order to move extra-fields to the
   * correct tab.
   *
   * @param string $idOrName
   *
   * @return string
   */
  protected function tableTabId(string $idOrName):string
  {
    $dflt = $this->defaultTableTabs(true);
    foreach ($dflt as $tab) {
      if ($idOrName === $tab['name'] || $idOrName === $this->l->t($tab['id'])) {
        return $tab['id'];
      }
    }
    return $idOrName;
  }

  /**
   * Export the default tabs family. Extra-tabs are inserted after the
   * personal data and before the misc-tab. The finance tab comes
   * before the personal data.
   *
   * @param bool $useFinanceTab
   *
   * @param array $extraTabs
   *
   * @return array
   */
  private function defaultTableTabs(bool $useFinanceTab = false, array $extraTabs = []):array
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
      // [
      //   'id' => 'file-attachments',
      //   'tooltip' => $this->toolTipsService['project-file-attachments-tab'],
      //   'name' => $this->l->t('Project file attachments'),
      // ],
      [
        'id' => 'contactdata',
        'tooltip' => $this->toolTipsService['project-contactdata-tab'],
        'name' => $this->l->t('Contact Information'),
      ],
      [
        'id' => 'musician',
        'tooltip' => $this->toolTipsService['project-personaldata-tab'],
        'name' => $this->l->t('Personal data'),
      ],
    ];
    $post = [
      [
        'id' => 'miscinfo',
        'tooltip' => $this->toolTipsService['page-renderer:miscinfo-tab'],
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
   *
   * @param mixed $participantFields
   *
   * @param bool $useFinanceTab
   *
   * @return array
   */
  private function tableTabs(mixed $participantFields = false, bool $useFinanceTab = false):array
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_iterable($participantFields)) {
      $this->logInfo('UNITERABLE PART FIELDS');
      return $dfltTabs;
    }

    $extraTabs = [];
    /** @var Entities\ProjectParticipantField $field */
    foreach ($participantFields as $field) {
      $extraTab = $field['tab'] ?: ProjectParticipantFieldsService::defaultTabId($field->getMultiplicity(), $field->getDataType());
      if (empty($extraTab)) {
        continue;
      }

      foreach ($dfltTabs as $tab) {
        if ($extraTab == $tab['id']
            || $extraTab == $this->l->t($tab['id'])
            || $extraTab == $tab['name']
            || $extraTab == $this->l->t($tab['name'])) {
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

  /**
   * This is a phpMyEdit before-SOMETHING trigger.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeDeleteTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $entity = $this->legacyRecordToEntity($pme->rec);

    $this->projectService->deleteProjectParticipant($entity);

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
  }
}
