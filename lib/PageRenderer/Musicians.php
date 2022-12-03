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

use chillerlan\QRCode\QRCode;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Controller\MailingListsController;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Controller\ImagesController;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

/**Table generator for Musicians table. */
class Musicians extends PMETableViewBase
{
  use FieldTraits\SepaAccountsTrait;
  use FieldTraits\MusicianPhotoTrait;
  use FieldTraits\MailingListsTrait;
  use FieldTraits\MusicianEmailsTrait;
  use FieldTraits\AllProjectsTrait;

  const ALL_TEMPLATE = 'all-musicians';
  const ADD_TEMPLATE = 'add-musicians';
  const CSS_CLASS = 'musicians';
  const TABLE = self::MUSICIANS_TABLE;
  const ALL_EMAILS_TABLE = self::MUSICIAN_EMAILS_TABLE . self::VALUES_TABLE_SEP . 'all';

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var OCA\CAFEVDB\Service\PhoneNumberService */
  private $phoneNumberService;

  /** @var OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService */
  private $insuranceService;

  /** @var MusicianService */
  private $musicianService;

  /** @var MailingListsService */
  private $listsService;

  /**
   * @var bool Called with project-id in order to add musicians to an
   * existing project
   */
  private $projectMode;

  /**
   * Join table structure. All update are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\Musician::class,
    ],
    self::MUSICIAN_INSTRUMENTS_TABLE => [
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'id',
      ],
      'column' => 'instrument_id',
    ],
    self::INSTRUMENTS_TABLE => [
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
    self::INSTRUMENT_INSURANCES_TABLE => [
      'entity' => Entities\InstrumentInsurance::class,
      'identifier' => [
        'id' => false,
        'instrument_holder_id' => 'id',
      ],
      'column' => 'bill_to_party_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::MUSICIAN_PHOTO_JOIN_TABLE => [
      'entity' => Entities\MusicianPhoto::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'owner_id' => 'id',
        'image_id' => false,
      ],
      'column' => 'image_id',
    ],
    self::PROJECT_PARTICIPANTS_TABLE => [
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'project_id' => false,
        'musician_id' => 'id',
      ],
      'column' => 'project_id',
      'flags' => self::JOIN_READONLY,
    ],
  ];

  /** @var Entities\Project */
  private $project;

  /** {@inheritdoc} */
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
    InstrumentInsuranceService $insuranceService,
    MusicianService $musicianService,
    MailingListsService $listsService,
  ) {
    parent::__construct(self::ALL_TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->insuranceService = $insuranceService;
    $this->musicianService = $musicianService;
    $this->listsService = $listsService;
    $this->projectMode = false;

    if (empty($this->musicianId)) {
      $this->musicianId = $this->pmeRecordId['id']??null;
    }

    if ($this->listOperation()) {
      $this->pme->overrideLabel('Add', $this->l->t('New Musician'));
    }
  }

  /** @return string */
  public function cssClass():string
  {
    return self::CSS_CLASS;
  }

  /**
   * Enable the project-mode which is used to add new participants to a
   * project. In effect, all persons are shown except those already
   * participating in the project and some additional controls are geneated in
   * order to select new participants.
   *
   * @return void
   */
  public function enableProjectMode():void
  {
    $this->projectMode = true;
    $this->setTemplate(self::ADD_TEMPLATE);
    if (empty($this->projectId)) {
      $this->logInfo('NO PROJECT ID ?????');
    }
    if (empty($this->project)) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      if (empty($this->projectName)) {
        $this->projectName = $this->project->getName();
      }
    }
  }

  /**
   * Disable project mode.
   *
   * @see enableProjectMode()
   *
   * @return void
   */
  public function disableProjectMode():void
  {
    $this->projectMode = false;
    $this->setTemplate(self::ALL_TEMPLATE);
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove all data of the displayed musician?');
    } elseif ($this->copyOperation()) {
      return $this->l->t('Copy the displayed musician?');
    } elseif ($this->viewOperation()) {
      return $this->l->t('Display of all stored personal data for the shown musician.');
    } elseif ($this->changeOperation()) {
      return $this->l->t('Edit the personal data of the displayed musician.');
    } elseif ($this->addOperation()) {
      return $this->l->t('Add a new musician to the data-base.');
    } elseif (!$this->projectMode) {
      return $this->l->t('Overview over all registered musicians');
    } else {
      return $this->l->t("Add musicians to the project `%s'", [ $this->projectName ]);
    }
  }

  /** {@inheritdoc} */
  public function headerText()
  {
    $header = $this->shortTitle();
    if ($this->projectMode) {
      $title = $this->l->t("This page is the only way to add musicians to projects in order to
make sure that the musicians are also automatically added to the
`global' musicians data-base (and not only to the project).");
    } else {
      $title = '';
    }

    return '<div class="'.$this->cssPrefix().'-header-text" title="'.$title.'">'.$header.'</div>';
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = [
      'show-hide-disabled',
    ];

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
      // overwrite to catch copy/insert
      'musicianId' => $this->musicianId,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      'display_name',
      'sur_name',
      'first_name',
      'nick_name',
      'id'
    ];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

    // controls display an location of edit/misc buttons
    $opts['navigation'] = self::PME_NAVIGATION_MULTI;

    // needed early as otherwise the add_operation() etc. does not work.
    $this->pme->setOptions($opts);

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '5';

    if (!$this->projectMode) {
      $export = $this->pageNavigation->tableExportButton();
      $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);
    }

    // Display special page elements
    $opts['display'] =  [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [ 'id' => 'orchestra',
          'default' => true,
          'tooltip' => $this->toolTipsService['musician-orchestra-tab'],
          'name' => $this->l->t('Instruments and Status') ],
        [ 'id' => 'contact',
          'tooltip' => $this->toolTipsService['musican-contact-tab'],
          'name' => $this->l->t('Contact Information') ],
        [ 'id' => 'finance',
          'tooltip' => $this->toolTipsService['musician-finance-tab'],
          'name' => $this->l->t('Financial Topics') ],
        [ 'id' => 'miscinfo',
          'tooltip' => $this->toolTipsService['musician-miscinfo-tab'],
          'name' => $this->l->t('Miscellaneous Data') ],
        [ 'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns')
        ],
      ],
    ];

    /*
     *
     **************************************************************************
     *
     * field definitions
     *
     */

    $opts['fdd']['id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => 'id',
      'select'   => 'T',
      'input'    => ($expertMode ? 'R' : 'RH'),
      'input|AP' => 'RH', // new id, no sense to display
      'options'  => 'AVCPD',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',  // auto increment
      'sort'     => true,
    ];

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    // Tweak the join-structure with dynamic data.
    list($allProjectsJoin, $allProjectsFieldGenerator) = $this->renderAllProjectsField(
      musicianIdField: 'id',
      tableTab: 'orchestra',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $allProjectsJoin);

    list($emailJoin, $emailFieldGenerator) = $this->renderMusicianEmailFields(css: [ $addCSS ]);
    $this->joinStructure = array_merge($this->joinStructure, $emailJoin);

    list($sepaJoin, $sepaFieldGenerator) = $this->renderSepaAccounts();
    $this->joinStructure = array_merge($this->joinStructure, $sepaJoin);

    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      $joinInfo['table'] = $table;
      switch ($table) {
        case self::INSTRUMENTS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
          break;
        default:
          break;
      }
    });

    $joinTables = $this->defineJoinStructure($opts);

    $bval = strval($this->l->t('Add to %s', [ $projectName ]));
    $tip  = strval($this->toolTipsService['register-musician']);
    if ($this->projectMode) {
      $opts['fdd']['add_musicians'] = [
        'tab' => [ 'id' => 'tab-all' ],
        'name' => $this->l->t('Add Musicians'),
        'css' => [ 'postfix' => [ 'register-musician', ], ],
        'select' => 'T',
        'options' => 'VCLR',
        'input' => 'V',
        'sql' => '$main_table.id',
        'php' => function($musicianId, $action, $k, $row, $recordId, $pme) use ($bval, $tip) {
            return '<div class="register-musician">'
              .'  <input type="button"'
              .'         value="'.$bval.'"'
              .'         data-musician-id="'.$musicianId.'"'
              .'         title="'.$tip.'"'
              .'         name="registerMusician"'
              .'         class="register-musician" />'
              .'</div>';
        },
        'escape' => false,
        'nowrap' => true,
        'sort' =>false,
      ];
    }

    $opts['fdd']['sur_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Surname'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, 'duplicates-indicator', ], ],
      'input|LF' => $this->pmeBare ? '' : 'H',
      'input|ACP' => 'M',
      // 'options'  => 'AVCPD',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['first_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Forename'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, 'duplicates-indicator', ], ],
      'input|LF' => $this->pmeBare ? '' : 'H',
      'input|ACP' => 'M',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['nick_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Nickname'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, 'duplicates-indicator', ], ],
      'input|LF' => $this->pmeBare ? '' : 'H',
      'sql|LFVD' => 'IF($column IS NULL OR $column = \'\', $table.first_name, $column)',
      'select'   => 'T',
      'maxlen'   => 380,
      'sort'     => true,
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $nickNamePlaceholder = $this->l->t('e.g. Cathy');
          $firstName = $row['qf'.($k-1)] ?? '';
          $lockedPlaceholder = $firstName ?: $nickNamePlaceholder;
          $unlockedPlaceholder = $nickNamePlaceholder;
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
<label
   class="pme-input pme-input-lock lock-empty"
   title="'.$this->toolTipsService['pme:input:lock-empty'].'"
   for="pme-musician-nickname"></label>';
        },
      ],
    ];

    $opts['fdd']['display_name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Display-Name'),
      'css'      => [
        'postfix' => [
          'musician-name',
          'default-readonly',
          'tab-contact-readwrite',
          'tab-all-readwrite',
          'tooltip-auto',
          $addCSS,
        ],
      ],
      'sql|LFVD' => parent::musicianPublicNameSql(),
      'maxlen'   => 384,
      'sort'     => true,
      'select'   => 'T',
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          // $this->logInfo('OP '.$op);
          $displayNamePlaceholder = $this->l->t('e.g. Doe, Cathy');
          $surName = $row['qf'.($k-3)] ?? '';
          $firstName = $row['qf'.($k-2)] ?? '';
          $nickName = $row['qf'.($k-1)] ?? '';
          $lockedPlaceholder = $op == 'add' ? $displayNamePlaceholder : $surName.', '.($nickName?:$firstName);
          $unlockedPlaceholder = $displayNamePlaceholder;
          if (empty($row['qf'.$k])) {
            return [
              'placeholder' => $lockedPlaceholder,
              'readonly' => true,
              'data-unlocked-placeholder' => $unlockedPlaceholder,
              'data-locked-placeholder' => $lockedPlaceholder,
            ];
          } else {
            return [
              'placeholder' => $unlockedPlaceholder,
              'readonly' => false,
              'data-unlocked-placeholder' => $unlockedPlaceholder,
              'data-locked-placeholder' => $lockedPlaceholder,
            ];
          }
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
          return '<input id="pme-musician-displayname"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-empty"
/><label
    class="pme-input pme-input-lock lock-empty"
    title="'.$this->toolTipsService['pme:input:lock-empty'].'"
    for="pme-musician-displayname"></label>';
        },
      ],
    ];

    $opts['fdd']['user_id_slug'] = [
      'tab'      => [ 'id' => [ 'orchestra', 'contact', ], ],
      'name'     => $this->l->t('User Id'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, ], ],
      'input'    => ($this->expertMode ? 'R' : 'HR'),
      // 'options'  => 'AVCPD',
      'select'   => 'T',
      'maxlen'   => 256,
      'sort'     => true,
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $surName = $row['qf'.($k-4)] ?? '';
          $firstName = $row['qf'.($k-3)] ?? '';
          $nickName = $row['qf'.($k-2)] ?? '';
          $placeHolder = $this->defaultUserIdSlug($surName, $firstName, $nickName);
          return [
            'placeholder' => $op == 'add' ? '' : $placeHolder,
            'readonly' => true,
          ];
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $checked = 'checked="checked" ';
          return '<input id="pme-musician-user-id-slug"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-unlock"
/><label
    class="pme-input pme-input-lock lock-unlock"
    title="'.$this->toolTipsService['pme:input:lock-unlock'].'"
    for="pme-musician-user-id-slug"></label>';
        },
      ],
    ];

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
      'name'        => $this->l->t('Instruments'),
      'tab'         => ['id' => 'orchestra'],
      'css'         => [
        'postfix' => [
          'musician-instruments',
          'tooltip-top',
          'no-chosen',
          'selectize',
        ],
      ],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => 'GROUP_CONCAT(DISTINCT
  IF('.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.deleted IS NULL, $join_col_fqn, NULL)
  ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)',
      'select'      => 'M',
      'values' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ 'l10n_name', ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby'     => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      'valueGroups' => $this->instrumentInfo['idGroups'],
      'filter' => [
        'having' => true,
      ],
      'display' => [
        'attributes' => [
          'data-selectize-options' => [
            'create' => false,
            'plugins' => [ 'drag_drop', ],
          ],
        ],
      ],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    list($instrumentsFddIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $opts['fdd'][$this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order')] = [
      'tab'         => [ 'id' => [ 'orchestra' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'HRV',
      'select'      => 'M',
      'sort'     => true,
      'values' => [
        'column' => 'sort_order',
        'orderby' => '$table.sort_order ASC',
        'join' => [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'deleted', [
        'name'    => $this->l->t('Disabled Instruments'),
        'tab'     => [ 'id' => [ 'orchestra' ] ],
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
        'display' => [
          'attributes' => [
            'data-selectize-options' => [
              'create' => false,
            ],
          ],
        ],
      ]);

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $memberStatusFddIndex = count($opts['fdd']);
    $opts['fdd']['member_status'] = [
      'name'    => strval($this->l->t('Member Status')),
      'tab'     => [ 'id' => [ 'orchestra' ] ],
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => [ 'memberstatus', 'tooltip-wide', ], ],
      'values2' => $this->memberStatusNames,
      'tooltip' => $this->toolTipsService['member-status'],
    ];

    $opts['fdd']['cloud_account_deactivated'] = [
      'name' => $this->l->t('Cloud Account Deactivated'),
      'tab' => [ 'id' => [ 'orchestra' ] ],
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
    ];

    $opts['fdd']['cloud_account_disabled'] = [
      'name' => $this->l->t('Hidden from Cloud'),
      'tab' => [ 'id' => [ 'orchestra' ] ],
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
    ];

    $allProjectsFieldGenerator($opts['fdd']);

    $opts['fdd']['mobile_phone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Mobile Phone'),
      'css'      => ['postfix' => [ 'phone-number', 'duplicates-indicator', ], ],
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      ];

    $opts['fdd']['fixed_line_phone'] = [
      'tab'      => ['id' => [ 'contact', ], ],
      'name'     => $this->l->t('Fixed Line Phone'),
      'css'      => ['postfix' => [ 'phone-number', 'duplicates-indicator', ], ],
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
    ];

    $emailFieldGenerator($opts['fdd']);

    $opts['fdd']['mailing_list'] = $this->announcementsSubscriptionControls(emailSql: '$table.email', columnTabs: [ 'orchestra', 'contact', ]);

    $opts['fdd']['address_supplement'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Address Supplement'),
      'css'      => ['postfix' => [ 'musician-address', 'street', 'duplicates-indicator', $addCSS, ], ],
      'input|LF' => $expertMode ? '' : 'H',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
      'tooltip'  => $this->toolTipsService['page-renderer:musicians:address-supplement'],
    ];

    $opts['fdd']['street'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Street'),
      'css'      => ['postfix' => [ 'musician-address', 'street', 'duplicates-indicator', $addCSS, ], ],
      'sql|FL'   => 'CONCAT(
  IF(COALESCE($table.address_supplement, "") <> "",
    CONCAT($table.address_supplement, ", "),
    ""),
  $table.street, COALESCE(CONCAT(" ", $table.street_number), ""))',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
      'tooltip' => $this->toolTipsService['autocomplete:require-three'],
    ];

    $opts['fdd']['street_number'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Street Number'),
      'css'      => ['postfix' => [ 'musician-address', 'street-number', 'duplicates-indicator', $addCSS, ], ],
      'input|LF' => $expertMode ? '' : 'H',
      'select'   => 'T',
      'size'     => 11,
      'maxlen'   => 32,
      'sort'     => true,
    ];

    $opts['fdd']['postal_code'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Postal Code'),
      'css'      => ['postfix' => [ 'musician-address', 'postal-code', 'duplicates-indicator', $addCSS, ], ],
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true,
    ];

    $opts['fdd']['city'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('City'),
      'css'      => ['postfix' => [ 'musician-address', 'city', 'duplicates-indicator', $addCSS, ], ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $opts['fdd']['country'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Country'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => $this->getConfigValue('streetAddressCountry'),
      'css'      => [ 'postfix' => [ 'musician-address', 'country', 'chosen-dropup', 'allow-empty', ], ],
      'values2'     => $countries,
      'valueGroups' => $countryGroups,
      'sort'     => true,
    ];

    $opts['fdd']['birthday'] = $this->defaultFDD['birthday'];
    $opts['fdd']['birthday']['tab'] = ['id' => 'miscinfo'];

    $opts['fdd']['remarks'] = [
      'tab'      => ['id' => 'orchestra'],
      'name'     => strval($this->l->t('Remarks')),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => [ 'postfix' => [ 'remarks', 'tooltip-top', 'squeeze-subsequent-lines', ], ],
      'textarea' => ['css' => 'wysiwyg-editor',
                     'rows' => 5,
                     'cols' => 50],
      'display|LF' => [
        'popup' => 'data',
        'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
        'postfix' => '</div></div>',
      ],
      'escape' => false,
      'sort'     => true,
    ];

    $opts['fdd']['language'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => $this->l->t('Language'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values2'  => $this->localeLanguageNames(),
    ];

    /*
     *
     **************************************************************************
     *
     * SEPA information
     *
     */

    $sepaFieldGenerator($opts['fdd']);

    /*
     * End SEPA information.
     *
     **************************************************************************
     *
     *
     */

    $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENT_INSURANCES_TABLE, 'insurance_amount', [
       'tab' => ['id' => 'finance'],
       'input' => 'V',
       'name' => $this->l->t('Instrument Insurance'),
       'select' => 'T',
       'options' => 'LFCDV',
       'sql' => 'SUM($join_col_fqn)',
       'escape' => false,
       'nowrap' => true,
       'sort' => false,
       'css' => [ 'postfix' => [ 'restrict-height', ], ],
       'php' => function($totalAmount, $action, $k, $row, $recordId, $pme) {
         $musicianId = $recordId['id'];
         $annualFee = $this->insuranceService->insuranceFee($musicianId, null);
         $bval = $this->l->t(
           'Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;', [ $totalAmount, $annualFee ]);
         $tip = $this->toolTipsService['musician-instrument-insurance'];
         $button = "<div class=\"pme-cell-wrapper restrict-height musician-instrument-insurance\">"
                 ."<input type=\"button\" "
                 ."value=\"$bval\" "
                 ."title=\"$tip\" "
                 ."name=\""
                 ."Template=instrument-insurance&amp;"
                 ."MusicianId=".$musicianId."\" "
                 ."class=\"musician-instrument-insurance\" />"
                 ."</div>";
         return $button;
       }
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_PHOTO_JOIN_TABLE, 'image_id', [
        'tab'      => ['id' => 'miscinfo'],
        'input' => 'VRS',
        'name' => $this->l->t('Photo'),
        'select' => 'T',
        'options' => 'VCD',
        'php' => function($imageId, $action, $k, $row, $recordId, $pme) {
          $musicianId = $recordId['id'] ?? 0;
          return $this->photoImageLink($musicianId, $action, $imageId);
        },
        'css' => ['postfix' => [ 'photo', ], ],
        'default' => '',
        'sort' => false
      ]);

//     ///////////////////// Test

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '$main_table.id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        // $this->logInfo('ROW: '.print_r($row, true));
        switch ($action) {
          case 'change':
          case 'display':
            list('musician' => $musician, 'categories' => $categories) = $this->musicianFromRow($row, $pme);
            $vcard = $this->contactsService->export($musician);
            unset($vcard->PHOTO); // too much information
            $categories = array_merge($categories, $vcard->CATEGORIES->getParts());
            sort($categories);
            $vcard->CATEGORIES->setParts($categories);
            // $this->logInfo($vcard->serialize());
            return '<img height="231" width="231" src="'.(new QRCode)->render($vcard->serialize()).'"></img>';
          default:
            return '';
        }
      },
      'default' => '',
      'sort' => false
    ];

    //////////////////////////

    $opts['fdd']['uuid'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => 'UUID',
      'options'  => 'LFVCDR',
      'input'    => 'R',
      'css'      => ['postfix' => [ 'musician-uuid', $addCSS, ], ],
      'sql'      => 'BIN2UUID($main_table.uuid)',
      'sqlw'     => 'UUID2BIN($val_qas)',
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => true,
    ];

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          'name' => $this->l->t('Last Updated'),
          'nowrap' => true,
          'options' => 'LFVCD', // Set by update trigger.
          'input' => 'R',
          'timeformat' => 'medium',
        ]
      );

    $opts['fdd']['created'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          'name' => $this->l->t('Created'),
          'nowrap' => true,
          'options' => 'LFVCD', // Set by update trigger
          'input' => 'R',
          'timeformat' => 'medium',
        ]
      );

    if ($this->projectMode) {
      //$key = 'qf'.$projectsIdx;
      $projectsJoin = $joinTables[self::PROJECT_PARTICIPANTS_TABLE];
      $projectIds = "GROUP_CONCAT(DISTINCT {$projectsJoin}.project_id)";
      $opts[PHPMyEdit::OPT_HAVING]['AND'] = "($projectIds IS NULL OR NOT FIND_IN_SET('$projectId', $projectIds))";
      $opts['misc']['css']['minor'] = [ 'bulkcommit', 'tooltip-right' ];
      $opts['labels']['Misc'] = strval($this->l->t('Add all to %s', [$projectName]));
    }

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'ensureUserIdSlug' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_AFTER][] = function(PHPMyEdit &$pme, $op, $step, $oldVals, &$changed, &$newVals) {

      /** @var Entities\Musician $musician */
      $musician = $this->legacyRecordToEntity($pme->rec);

      // add the new musician id to the persistent CGI array
      $pme->addPersistentCgi([
        'musicianId' => $musician->getId(),
      ]);

      // invite, subscribe or do nothing. If the person is already subscribed
      // then it can unsubscribe by itself, so nothing more is needed here.
      $list = $this->getConfigValue('announcementsMailingList');
      if (!empty($list)) {
        try {
          switch ($newVals['mailing_list'] ?? '') {
            case 'invite':
              $this->logInfo('SHOULD INVITE TO MAILING LIST');
              $this->listsService->invite($list, $musician->getEmail(), $musician->getPublicName(firstNameFirst: true));
              break;
            case 'subscribe':
              $this->logInfo('SHOULD SUBSCRIBE TO MAILING LIST');
              $this->listsService->subscribe($list, $musician->getEmail(), $musician->getPublicName(firstNameFirst: true));
              break;
            default:
              $this->logInfo('LEAVING MAILING LIST SUBSCRIPTION ALONE');
              break;
          }
        } catch (\Throwable $t) {
          // for now we ignore any mailing list related errors in order not to
          // annoy the persons who are desperately trying to add persons to
          // the data-base. We should, however, find a notification channel
          // for end-user messages.
          $this->logException(
            $t,
            'Failed to add or invite ' . $musician->getPublicName(firstNameFirst: true)
            . ' to the mailing-list ' . $list);
        }
      }
      return true;
    };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeDeleteTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {
      if (!empty($row[$this->queryField('deleted', $pme->fdd)])) {
        // disable misc-checkboxes for soft-deleted musicians in order to
        // avoid sending them bulk-email.
        $pme->options = str_replace('M', '', $opts['options']);
      } else {
        $pme->options = $opts['options'];
      }
      return true;
    };
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) {
      if ($this->copyOperation()) {
        $this->logInfo('ROW ' . print_r($row, true));
      }

      unset($row['qf' . $pme->fdn['uuid']]);
      unset($row['qf' . $pme->fdn['user_id_slug']]);

      return true;
    };

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
   * This is a phpMyEdit before-SOMETHING trigger.
   *
   * The phpMyEdit class calls the trigger (callback) with the following
   * arguments:
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
  public function beforeDeleteTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, ?array &$changed, ?array &$newValues):bool
  {
    $entity = $this->legacyRecordToEntity($pme->rec);

    $this->musicianService->deleteMusician($entity);

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
