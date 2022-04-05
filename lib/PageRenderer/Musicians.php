<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  const ALL_TEMPLATE = 'all-musicians';
  const ADD_TEMPLATE = 'add-musicians';
  const CSS_CLASS = 'musicians';
  const TABLE = self::MUSICIANS_TABLE;

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
    , InstrumentInsuranceService $insuranceService
    , MusicianService $musicianService
    , MailingListsService $listsService
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

  public function cssClass():string { return self::CSS_CLASS; }

  public function enableProjectMode()
  {
    $this->projectMode = true;
    $this->setTemplate(self::ADD_TEMPLATE);
  }

  public function disableProjectMode()
  {
    $this->projectMode = false;
    $this->setTemplate(self::ALL_TEMPLATE);
  }

  /** Short title for heading. */
  public function shortTitle() {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove all data of the displayed musician?');
    } else if ($this->copyOperation()) {
      return $this->l->t('Copy the displayed musician?');
    } else if ($this->viewOperation()) {
      return $this->l->t('Display of all stored personal data for the shown musician.');
    } else if ($this->changeOperation()) {
      return $this->l->t('Edit the personal data of the displayed musician.');
    } else if ($this->addOperation()) {
      return $this->l->t('Add a new musician to the data-base.');
    } else if (!$this->projectMode) {
      return $this->l->t('Overview over all registered musicians');
    } else {
      return $this->l->t("Add musicians to the project `%s'", [ $this->projectName ]);
    }
  }

  /** Header text informations. */
  public function headerText() {
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

    // @todo still true? must come after the key-def fdd
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
        'php' => function($musicianId, $action, $k, $row, $recordId, $pme)
          use($bval, $tip) {
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

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    $opts['fdd']['sur_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Surname'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, ], ],
      'input|LF' => 'H',
      // 'options'  => 'AVCPD',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['first_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Forename'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, ], ],
      'input|LF' => 'H',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['nick_name'] = [
      'tab'      => [ 'id' => 'contact' ],
      'name'     => $this->l->t('Nickname'),
      'css'      => [ 'postfix' => [ 'musician-name', $addCSS, ], ],
      'input|LF' => 'H',
      'sql|LFVD' => 'IF($column IS NULL OR $column = \'\', $table.first_name, $column)',
      'select'   => 'T',
      'maxlen'   => 380,
      'sort'     => true,
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $firstName = $row['qf'.($k-1)] ?? '';
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
          $surName = $row['qf'.($k-3)] ?? '';
          $firstName = $row['qf'.($k-2)] ?? '';
          $nickName = $row['qf'.($k-1)] ?? '';
          $lockedPlaceholder = $op == 'add' ? $displayNamePlaceholder : $surName.', '.($nickName?:$firstName);
          $unlockedPlaceholder = $this->l->t('e.g. Doe, Cathy');
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
      'input|LF' => 'H',
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
      'css'         => ['postfix' => [ 'musician-instruments', 'tooltip-top', 'no-chosen', 'selectize', 'drag-drop', ], ],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => 'GROUP_CONCAT(DISTINCT IF('.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.deleted IS NULL, $join_col_fqn, NULL) ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)',
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
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    $this->makeJoinTableField(
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
      ]);

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
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

    $opts['fdd']['mailing_list'] = [
      'name'    => $this->l->t('Mailing List'),
      'tab'     => [ 'id' => [ 'orchestra' ] ],
      'css'     => [ 'postfix' => [ 'mailing-list', 'tooltip-wide', ], ],
      'sql'     => '$table.email',
      'options' => 'ACPVD',
      'input'   => 'V',
      'php' => function($email, $action, $k, $row, $recordId, $pme) {
        $list = $this->getConfigValue('announcementsMailingList');
        try {
          $status = $this->listsService->getSubscriptionStatus($list, $email);
        } catch (\Throwable $t) {
          $this->logException($t, $this->l->t('Unable to contact mailing lists service'));
          $status = 'unknown';
        }
        $statusText = $this->l->t($status);
        $operations = [
          MailingListsController::OPERATION_INVITE,
          MailingListsController::OPERATION_ACCEPT,
          MailingListsController::OPERATION_SUBSCRIBE,
          MailingListsController::OPERATION_REJECT,
          MailingListsController::OPERATION_UNSUBSCRIBE,
        ];
        $disabled = [
          MailingListsController::OPERATION_INVITE => ($status != MailingListsService::STATUS_UNSUBSCRIBED),
          MailingListsController::OPERATION_ACCEPT => ($status != MailingListsService::STATUS_WAITING),
          MailingListsController::OPERATION_REJECT => ($status != MailingListsService::STATUS_INVITED && $status != MailingListsService::STATUS_WAITING),
          MailingListsController::OPERATION_SUBSCRIBE => (!$this->expertMode || $status != MailingListsService::STATUS_UNSUBSCRIBED),
          MailingListsController::OPERATION_UNSUBSCRIBE => ($status != MailingListsService::STATUS_SUBSCRIBED),
        ];
        $defaultCss = [ 'mailing-list', 'operation' ];
        $cssClasses = [
          MailingListsController::OPERATION_INVITE => [ 'status-unsubscribed-visible', ],
          MailingListsController::OPERATION_ACCEPT => [ 'status-waiting-visible', ],
          MailingListsController::OPERATION_REJECT => [ 'status-invited-visible', 'status-waiting-visible', ],
          MailingListsController::OPERATION_SUBSCRIBE => [ 'status-unsubscribed-visible', ],
          MailingListsController::OPERATION_UNSUBSCRIBE => [ 'status-subscribed-visible', ],
        ];
        $html = '
<span class="mailing-list status action-' . $action . ' status-' . $status . '" data-status="' . $status. '">' . $statusText . '</span>
<span class="mailing-list operations action-' . $action . ' status-' . $status . '" data-status="' . $status. '">
';
        foreach ($operations as $operation) {
          $css = implode(' ', array_merge($defaultCss, $cssClasses[$operation], [ $operation ]));
          $html .= '
  <input type="button"
         name="' . $operation . '"
         class="' . $css . '"
         value="' . $this->l->t($operation) . '"
         title="' . $this->toolTipsService['page-renderer:musicians:mailing-list:actions:' . $operation] . '"
         ' .  ($disabled[$operation] ? 'disabled' : '') . '/>';
        }
        $html .= '
</span>
';
        return $html;
      },
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

    $opts['fdd']['projects'] = [
      'tab' => ['id' => 'orchestra'],
      'input' => 'VR',
      'options' => 'LFVCD',
      'select' => 'M',
      'name' => $this->l->t('Projects'),
      'sort' => true,
      'css'      => ['postfix' => [ 'projects', 'tooltip-top', ], ],
      'display|LVDF' => ['popup' => 'data'],
      'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by SEPARATOR \',\')',
      'filter' => [
        'having' => false,
        'flags' => PHPMyEdit::OMIT_DESC|PHPMyEdit::OMIT_SQL,
      ],
      'values' => [
        'table' => self::PROJECTS_TABLE,
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'column' => 'name',
        'orderby' => '$table.year ASC, $table.name ASC',
        'groups' => 'year',
        'join' => '$join_table.id = '.$joinTables[self::PROJECT_PARTICIPANTS_TABLE].'.project_id'
      ],
    ];

    $opts['fdd']['mobile_phone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Mobile Phone'),
      'css'      => ['postfix' => [ 'phone-number', ], ],
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
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Fixed Line Phone'),
      'css'      => ['postfix' => [ 'phone-number', ], ],
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
    ];

    $opts['fdd']['email'] = $this->defaultFDD['email'];
    $opts['fdd']['email']['tab'] = ['id' => 'contact'];
    $opts['fdd']['email']['input'] = ($opts['fdd']['email']['input'] ?? '') . 'M';

    $opts['fdd']['street'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Street'),
      'css'      => ['postfix' => [ 'musician-address', 'street', ], ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
      'tooltip' => $this->toolTipsService['autocomplete:require-three'],
    ];

    $opts['fdd']['postal_code'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Postal Code'),
      'css'      => ['postfix' => [ 'musician-address', 'postal-code', ], ],
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true,
    ];

    $opts['fdd']['city'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('City'),
      'css'      => ['postfix' => [ 'musician-address', 'city', ], ],
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
       'sort' =>false,
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
      'options' => 'APVCD',
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
        switch($action) {
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
      //'options'  => 'AVCPDR',
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
          "name" => $this->l->t("Last Updated"),
          "nowrap" => true,
          "options" => 'LFAVCPDR', // Set by update trigger.
          'timeformat' => 'medium',
        ]
      );

    $opts['fdd']['created'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Created"),
          "nowrap" => true,
          "options" => 'LFAVCPDR', // Set by update trigger
          'timeformat' => 'medium',
        ]
      );

    if ($this->projectMode) {
      //$key = 'qf'.$projectsIdx;
      $projectsJoin = $joinTables[self::PROJECT_PARTICIPANTS_TABLE];
      $projectIds = "GROUP_CONCAT(DISTINCT {$projectsJoin}.project_id)";
      $opts['having']['AND'] = "($projectIds IS NULL OR NOT FIND_IN_SET('$projectId', $projectIds))";
      $opts['misc']['css']['minor'] = [ 'bulkcommit', 'tooltip-right' ];
      $opts['labels']['Misc'] = strval($this->l->t('Add all to %s', [$projectName]));
    }

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'ensureUserIdSlug' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_AFTER][] = function(&$pme, $op, $step, $oldVals, &$changed, &$newVals) {
      // add the new musician id to the persistent CGI array
      $pme->addPersistentCgi([
        'musicianId' => $newVals['id'],
      ]);
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
   * @return boolean If returning @c false the operation will be terminated
   */
  public function beforeDeleteTrigger(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
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
