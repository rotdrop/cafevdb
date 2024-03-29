<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator;
use OCA\CAFEVDB\Controller\DownloadsController;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Exceptions;

/** TBD. */
class SepaBankAccounts extends PMETableViewBase
{
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use FieldTraits\CryptoTrait;
  use FieldTraits\MusicianInProjectTrait;
  use FieldTraits\MusicianPublicNameTrait;
  use FieldTraits\ParticipantFieldsTrait;
  use FieldTraits\ParticipantTotalFeesTrait;
  use FieldTraits\QueryFieldTrait;

  const AMOUNT_TAB_ID = 'amount';

  const TEMPLATE = 'sepa-bank-accounts';
  const TABLE = self::SEPA_BANK_ACCOUNTS_TABLE;
  const FIXED_COLUMN_SEP = self::VALUES_TABLE_SEP;

  protected $cssClass = 'sepa-bank-accounts';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\SepaBankAccount::class,
      'identifier' => [
        'musician_id' => 'musician_id',
        'sequence' => false,
      ],
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
    ],
    self::SEPA_DEBIT_MANDATES_TABLE => [
      'entity' => Entities\SepaDebitMandate::class,
      'flags' => self::JOIN_GROUP_BY,
      'identifier' => [
        'musician_id' => 'musician_id',
        'bank_account_sequence' => 'sequence',
        'sequence' => false,
      ],
      'column' => 'sequence',
    ],
    self::PROJECT_PARTICIPANTS_TABLE => [
      'entity' => Entities\ProjectParticipants::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'musician_id' => 'musician_id',
        'project_id' => false,
      ],
      'column' => 'project_id',
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::SEPA_DEBIT_MANDATES_TABLE,
          'column' => 'project_id',
        ],
      ],
      'column' => 'id',
    ],
    self::COMPOSITE_PAYMENTS_TABLE => [
      'entity' => Entities\CompositePayment::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'musician_id' => 'musician_id',
        'bank_account_sequence' => 'sequence',
      ],
      'column' => 'id',
    ],
    // extra input fields depending on the type of the project,
    // e.g. service fees etc.
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECT_PARTICIPANTS_TABLE,
          'column' => 'project_id',
        ],
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_REMOVE_EMPTY,
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECT_PARTICIPANTS_TABLE,
          'column' => 'project_id',
        ],
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
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECT_PARTICIPANTS_TABLE,
          'column' => 'project_id',
        ],
        'musician_id' => 'musician_id',
      ],
      'column' => 'id',
    ],
  ];

  /** @var ProjectParticipantFieldsService */
  protected $participantFieldsService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  protected $projectService;

  /** @var UserStorage */
  protected $userStorage;

  /** @var Entities\Project */
  private $project = null;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    ProjectParticipantFieldsService $participantFieldsService,
    FinanceService $financeService,
    ProjectService $projectService,
    UserStorage $userStorage,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->participantFieldsService = $participantFieldsService;
    $this->financeService = $financeService;
    $this->projectService = $projectService;
    $this->userStorage = $userStorage;
    $this->initCrypto();
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      $this->projectName = $this->project->getName();
    }
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove this Bank-Account?');
    } elseif ($this->viewOperation()) {
      if ($this->projectId > 0 && $this->projectName != '') {
        return $this->l->t('Bank-Account for %s', array($this->projectName));
      } else {
        return $this->l->t('Bank-Account');
      }
    } elseif ($this->changeOperation()) {
      return $this->l->t('Change this Bank-Account');
    }
    if ($this->projectId > 0 && $this->projectName != '') {
      return $this->l->t('Overview over all SEPA Bank Accounts for %s', [ $this->projectName ]);
    } else {
      return $this->l->t('Overview over all SEPA Bank Accounts');
    }
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;

    $projectMode = $this->projectId > 0;
    if ($projectMode) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    }

    $opts = [];

    $opts['css']['postfix'] = [
      self::CSS_TAG_DIRECT_CHANGE,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'musician_id' => 'int', 'sequence' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::MUSICIANS_TABLE, 'id'),
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'id'),
      'sequence',
      $this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'sequence'),
    ];

    // Group by for to-many joins
    $opts['groupby_fields'] = $opts['sort_field'];
    $opts['groupby_where'] = true;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    if ($projectMode) {
      $opts['options'] .= 'M';

      // controls display of an location of edit/misc buttons
      $opts['navigation'] = self::PME_NAVIGATION_MULTI;
      $opts['misc']['css']['major'] = 'misc';
      $opts['misc']['css']['minor'] = 'debit-note tooltip-right';
      $opts['labels']['Misc'] = $this->l->t('Debit');
    }

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    /*
     * End general options.
     *
     **************************************************************************
     *
     * Extra-buttons for initiating bank transfers
     *
     */

    $buttons = [];
    if ($projectMode) {
      // check whether we have fields with generated receivables
      $monetaryFields = $this->participantFieldsService->monetaryFields($this->project);
      /** @var Entities\ProjectParticipantField $field */
      $haveGenerators = $monetaryFields->exists(function($key, $field) {
        return $field->getMultiplicity() == FieldMultiplicity::RECURRING();
      });

      // Control to select what we want to debit
      $cgiBulkTransactions = $this->requestParameters->getParam('sepaBulkTransactions');

      $selectAllLabel = Util::htmlEscape($this->l->t('=== (DE-)SELECT ALL ==='));

      $sepaBulkTransactions = '
<span id="sepa-bulk-transactions" class="sepa-bulk-transactions pme-menu-block">
  <select multiple data-placeholder="'.$this->l->t('SEPA Bulk Transactions').'"
          class="sepa-bulk-transactions"
          title="'.$this->toolTipsService['sepa-bulk-transactions-choice'].'"
          name="sepaBulkTransactions[]">
    <option value=""></option>
    <option value="-1">' . $selectAllLabel . '</option>
';

      $jobOptions = $this->participantFieldsService->monetarySelectOptions($this->project);

      $sepaBulkTransactions .= $this->pageNavigation->selectOptions($jobOptions, $cgiBulkTransactions);
      $sepaBulkTransactions .= '
  </select>
</span>';
      $buttons[] = [ 'code' =>  $sepaBulkTransactions, 'name' => 'bulk-transactions' ];

      // Control to select when we want to have the money (or to have spent the money)
      // $cgiDueDeadline = $this->requestParameters->getParam('sepaDueDeadline');
      $sepaDueDeadline = '
<span id="sepa-due-deadline" class="sepa-due-deadline">
  <input type="text"
         size="10"
         maxlength="10"
         name="sepaDueDeadline"
         placeholder="'.$this->l->t('SEPA due date').'"
         title="'.$this->toolTipsService['sepa-due-deadline'].'"
         class="date sepa-due-deadline"/>
</span>';
      $buttons[] = [ 'code' => $sepaDueDeadline, 'name' => 'due-deadline' ];



      $regenerateReceivables = '
<span id="regenerate-receivables" class="'.(!$haveGenerators ? 'hidden ' : '').'regenerate-receivables dropdown-container">
  <input type="button"
         value="'.$this->l->t('Recompute').'"
         title="'.$this->toolTipsService['bulk-transactions-regenerate-receivables'].'"
         class="regenerate-receivables"
         data-project-id="'.$this->projectId.'"
  />
  <nav class="dropdown-content dropdown-align-left">
    <ul>
      <li><span class="caption">'.$this->l->t('In case of Conflict').'</span></li>';
      foreach (IRecurringReceivablesGenerator::UPDATE_STRATEGIES as $tag) {
        $cssId = 'recurring-receivables-update-strategy-' . $tag;
        $checked = $tag === 'exception' ? 'checked' : '';
        $regenerateReceivables .= '
      <li data-id="'.$tag.'">
        <a href="#">
          <input id="'.$cssId.'"
                 type="radio"
                 class="checkbox recurring-receivables-update-strategy"
                 name="recurringReceivablesUpdateStrategy-{POSITION}"
                 value="'.$tag.'"
                 '.$checked.'
          />
          <label for="'.$cssId.'">
          '.$this->l->t($tag).'
          </label>
        </a>
      </li>';
      }
      $regenerateReceivables .= '
    </ul>
  </nav>
</span>';
      $buttons[] = [ 'code' => $regenerateReceivables, 'name' => 'regenerate-receivables' ];

      $buttonPositions = [
        'up' => [
          'left' => [
            'bulk-transactions',
            'due-deadline',
            'misc',
            'regenerate-receivables',
            'export',
          ],
        ],
        'down' => [
          'left' => [
            'due-deadline',
            'misc',
            'regenerate-receivables',
            'export',
          ],
          'right' => [
            'bulk-transactions',
          ],
        ]
      ];
    } else {
      $monetaryFields = [];
      $buttonPositions = [
        'up' => [ 'left' => [ /* 'misc', */ 'export' ], ],
        'down' => [ 'left' => [ /* 'misc', */ 'export' ], ]
      ];
    }

    $button = $this->pageNavigation->tableExportButton();
    $button['name'] = 'export';
    $buttons[] = $button;

    $opts['buttons'] = $this->pageNavigation->prependTableButtons(
      $buttons, $buttonPositions);

    /*
     *
     *
     *
     **************************************************************************
     *
     * ... more general options
     *
     */

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => $this->tableTabs($monetaryFields),
      'navigation' => 'VCD', // 'VCPD',
    ];

    if ($this->addOperation()) {
      $opts['display']['tabs'] = false;
    }

    /*
     * End options.
     *
     **************************************************************************
     *
     * Field definition data.
     *
     */

    $opts['fdd']['musician_id'] = [
      'name'     => $this->l->t('Musician-Id'),
      'css'      => [ 'postfix' => [ 'musician-id', ], ],
      'tab'      => $this->expertMode ? [ 'id' => 'miscinfo' ] : null,
      'input'    => $this->expertMode ? 'R' : 'RH',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    $opts['fdd']['sequence'] = [
      'name'     => $this->l->t('Bank-Account-Sequence'),
      'css'      => [ 'postfix' => [ 'bank-account-sequence', ], ],
      'tab'      => $this->expertMode ? [ 'id' => 'miscinfo' ] : null,
      'input'    => $this->expertMode ? 'R' : 'RH',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    // Define some further join-restrictions
    array_walk($this->joinStructure, function(&$joinInfo, $table) use ($projectMode) {
      $joinInfo['table'] = $table;
      switch ($table) {
        case self::PROJECT_PARTICIPANTS_TABLE:
          if ($projectMode) {
            $joinInfo['identifier']['project_id'] = [
              'value' => $this->project->getId(),
            ];
          }
          break;
        case self::SEPA_DEBIT_MANDATES_TABLE:
          if ($projectMode) {
            $joinInfo['filter']['project_id'] = [
              'value' => [ $this->project->getId(), $this->membersProjectId ],
            ];
          }
          if (!$this->showDisabled) {
            $joinInfo['filter']['deleted'] = [
              'value' => null,
            ];
          }
          break;
        case self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'label');
          break;
        default:
          break;
      }
    });


    // add participant fields-data, if in project-mode
    if ($projectMode) {
      list($participantFieldsJoin, $participantFieldsGenerator) =
        $this->renderParticipantFields(
          $monetaryFields, [
            'table' => self::PROJECT_PARTICIPANTS_TABLE,
            'column' => 'project_id',
          ],
          self::AMOUNT_TAB_ID);
      $this->joinStructure = array_merge($this->joinStructure, $participantFieldsJoin);
    }

    $this->defineJoinStructure($opts);

    // field definitions

    ///////////////////////////////////////////////////////////////////////////

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'tab'      => [ 'id' => 'tab-all' ],
        'name'     => $this->l->t('Musician'),
        'css'      => [ 'postfix' => [ 'allow-empty', 'project-participant', ], ],
        'input'    => 'R',
        'input|A'  => null,
        'select'   => 'D',
        'maxlen'   => 11,
        'sort'     => true,
        'values' => [
          'description' => [
            'columns' => [ static::musicianPublicNameSql() ],
            'divs' => [],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'filters' => (!$projectMode
                        ? null
                        : self::musicianInProjectSql($this->projectId)),
        ],
      ]);


    ///////////////

    // couple of "easy" fields

    $opts['fdd']['bank_account_owner'] = [
      'tab' => [ 'id' => [ 'account', 'mandate', ], ],
      'name' => $this->l->t('Bank Account Owner'),
      'css' => [ 'postfix' => [ 'allow-empty', 'bank-account-owner', 'lazy-decryption', ], ],
      'input' => 'M',
      'options' => 'LFACPDV',
      'select' => 'T',
      'select|LF' => 'D',
      'maxlen' => 80,
      'encryption' => [
        'encrypt' => fn($value) => $this->ormEncrypt($value),
        'decrypt' => fn($value) => md5($value), // $this->ormDecrypt($value),
      ],
      'values|LFVD'  => [
        'table' => self::TABLE,
        'column' => 'bank_account_owner',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'filters' => (!$projectMode
                      ? null
                      : self::musicianInProjectSql($this->projectId, 'musician_id')),
        'data' => [
          'data' => 'GROUP_CONCAT(DISTINCT CONCAT_WS("'.self::COMP_KEY_SEP.'", $table.musician_id, $table.sequence))',
          'crypto-hash' => 'MD5($table.$column)',
          'meta-data' => '"iban"', // SQL STRING
        ],
      ],
      'values|ACP' => [
        'table' => self::TABLE,
        'column' => 'bank_account_owner',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        // in order to support auto-completion and/or filling in particular
        // while adding new mandates we provide the bank account identifier
        'data' => [
          'data' => 'GROUP_CONCAT(DISTINCT CONCAT_WS("'.self::COMP_KEY_SEP.'", $table.musician_id, $table.sequence))',
          'crypto-hash' => 'MD5($table.$column)',
          'meta-data' => '"bankAccountOwner"', // SQL string
        ],
        'filters' => '$table.deleted IS NULL',
      ],
      'display' => [
        'attributes' => [
          'data-meta-data' => 'bankAccountrOwner',
        ],
      ],
    ];
    // See also FieldTraits/SepaAccountsTrait. Decrypt values in the
    // background to improve the responsiveness of the UI.
    $opts['fdd']['bank_account_owner']['encryption|LF']['decrypt'] = function($value) {
      // $this->ormDecrypt($value);
      $value = '<span class="iban encryption-placeholder"
      data-crypto-hash="' . md5($value) . '"
      title="' . $this->l->t('Fetching decrypted values in the background.') . '"
>'
        . $this->l->t('please wait')
        . '</span>';
      return $value;
    };

    // soft-deletion
    $opts['fdd']['deleted'] = Util::arrayMergeRecursive(
      $this->defaultFDD['deleted'], [
        'tab' =>  [ 'id' => 'account' ],
        'options' => 'LFCPDV',
      ]);

    $opts['fdd']['iban'] = [
      'tab' => [ 'id' => [ 'account', 'mandate' ] ],
      'name' => 'IBAN',
      'css' => [ 'postfix' => [ 'bank-account-iban', 'meta-data-popup', 'lazy-decryption' ], ],
      'input' => 'M',
      'options' => 'LFACPDV',
      'select' => 'T',
      'select|LF' => 'D',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => fn($value) => $this->ormEncrypt($value),
        'decrypt' => fn($value) => md5($value), // $this->ormDecrypt($value),
      ],
      'values|LFVD' => [
        'table' => self::TABLE,
        'column' => 'iban',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'filters' => (!$projectMode
                      ? null
                      : self::musicianInProjectSql($this->projectId, 'musician_id')),
        'data' => [
          'data' => 'GROUP_CONCAT(DISTINCT CONCAT_WS("'.self::COMP_KEY_SEP.'", $table.musician_id, $table.sequence))',
          'crypto-hash' => 'MD5($table.$column)',
          'meta-data' => '"iban"', // SQL STRING
        ],
      ],
      'values|ACP' => [ // use full table contents for auto-completion
        'table' => self::TABLE,
        'column' => 'iban',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        // in order to support auto-completion and/or filling in particular
        // while adding new mandates we provide the bank account identifier
        'data' => [
          'data' => 'GROUP_CONCAT(DISTINCT CONCAT_WS("'.self::COMP_KEY_SEP.'", $table.musician_id, $table.sequence))',
          'crypto-hash' => 'MD5($table.$column)',
          'meta-data' => '"iban"', // SQL STRING
        ],
        'filters' => '$table.deleted IS NULL',
      ],
      'display' => [
        'popup' => function($data) {
          $info  = $this->financeService->getIbanInfo($data);
          $result = '';
          foreach ($info??[] as $key => $value) {
            $result .= $this->l->t($key).': '.$value.'<br/>';
          }
          return $result;
        },
        'attributes' => [
          'data-meta-data' => 'iban',
        ],
      ],
      'display|LF' => [
        'attributes' => [
          'data-meta-data' => 'iban',
        ],
      ],
    ];
    // See also FieldTraits/SepaAccountsTrait. Decrypt values in the
    // background to improve the responsiveness of the UI.
    $opts['fdd']['iban']['encryption|LF']['decrypt'] = function($value) {
      // $this->ormDecrypt($value);
      $value = '<span class="iban encryption-placeholder"
      data-crypto-hash="' . md5($value) . '"
      title="' . $this->l->t('Fetching decrypted values in the background.') . '"
>'
        . $this->l->t('please wait')
        . '</span>';
      return $value;
    };

    $opts['fdd']['blz'] = [
      'tab' => [ 'id' => 'account' ],
      'name'   => $this->l->t('Bank Code'),
      'select' => 'T',
      'input|LF' => 'H',
      'maxlen' => 12,
      'encryption' => [
        'encrypt' => fn($value) => $this->ormEncrypt($value),
        'decrypt' => fn($value) => $this->ormDecrypt($value),
      ],
    ];

    $opts['fdd']['bic'] = [
      'name'   => 'BIC',
      'input' => 'R',
      'input|LF' => 'H',
      'select' => 'T',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => fn($value) => $this->ormEncrypt($value),
        'decrypt' => fn($value) => $this->ormDecrypt($value),
      ],
    ];

    ///////////////////////////////////////////////////////////////////////////

    // This is the project from the mandate
    list($projectIndex, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id', [
        'tab' => $this->expertMode ? [ 'id' => [ 'mandate', 'miscinfo', ], ] : [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Mandate Project'),
        'input'    => ($projectMode && !$this->expertMode) ? 'H' : 'R',
        'input|A'  => null, // $projectMode ? 'R' : null,
        'select'   => 'D',
        'maxlen'   => 11,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'mandate-project', 'allow-empty', 'chosen-width-auto', ], ],
        'values' => [
          'description' => [
            'columns' => [ 'year' => "CONCAT(\$table.year, IF(\$table.year IS NULL, '', ': '))", 'name' => 'name' ],
            //'divs' => [ 'year' => ': ' ]
          ],
        ],
        'display' => [
          'attributes' => [
            'data-placeholder' => $this->l->t('Choose a Project to define a Mandate'),
          ],
        ],
      ]
    );

    if ($projectMode) {
      $opts['fdd'][$fieldName]['values']['filters'] =
        '$table.id IN ('.$projectId.','.$this->membersProjectId.')';
      $opts['fdd'][$fieldName]['values|CP'] = $opts['fdd'][$fieldName]['values'];
      $opts['fdd'][$fieldName]['values|CP']['filters'] =
        ($opts['fdd'][$fieldName]['values']['filters']
         . '
   AND $table.id in (SELECT pp.project_id FROM '.self::PROJECT_PARTICIPANTS_TABLE.' pp WHERE pp.musician_id = $record_id[musician_id])');
      if ($projectId === $this->membersProjectId) {
        $opts['fdd'][$fieldName] = array_merge(
          $opts['fdd'][$fieldName], [
            'select' => 'T',
            'sort' => false,
            'maxlen' => 40,
            'options' => 'VPCDL',
          ]);
      }
    }

    ///////////////////////////////////////////////////////////////////////////

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'sequence',
      [
        'tab'    => [ 'id' => 'mandate' ],
        'name'   => $this->l->t('Mandate Sequence'),
        'css'      => [ 'postfix' => [ 'mandate-sequence', 'empty-mandate-project-hidden', ], ],
        'input'  => 'R',
        'select' => 'N',
        'maxlen' => 3,
        'sort'   => true,
        'php|ACP' => function($value, $op, $k, $row, $recordId, $pme) {
          $mandateReference = $row[PHPMyEdit::QUERY_FIELD . ($k+1)] ?? null;
          if (empty($mandateReference)) {
            return $this->l->t('generated on save');
          }
          // In order not to trigger erroneous update of unchanged value we
          // emit the sequence value as hidden input. It is further protected
          // by an udpate "trigger" which just forces it to remain unchanged.
          $html = $pme->htmlHiddenData($pme->fds[$k], $value);
          $html .= '<span class="cell-wrapper">' . (string)$value . '</span>';
          return $html;
        },
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference',
      [
        'tab'    => [ 'id' => 'mandate' ],
        'name'   => $this->l->t('Mandate Reference'),
        'css'    => [ 'postfix' => [ 'mandate-reference', 'empty-mandate-project-hidden', ], ],
        'input'  => 'R',
        'select' => 'T',
        'maxlen' => 35,
        'sort'   => true,
        'php|LFDV' => function($value, $op, $k, $row, $recordId, $pme) {
          $writtenMandateId = $row[PHPMyEdit::QUERY_FIELD . ($k + 5)];
          if (empty($writtenMandateId)) {
            return $value;
          }
          $mandateReference = $value;

          /** @var Entities\File $file */
          $file = $this->getDatabaseRepository(Entities\File::class)->find($writtenMandateId);
          if (empty($file)) {
            return $value;
          }

          $fileName = $mandateReference;
          $extension = Util::fileExtensionFromMimeType($file->getMimeType());
          if (!empty($extension)) {
            $fileName .= '.' . $extension;
          }

          $downloadLink = $this->urlGenerator()
            ->linkToRoute($this->appName().'.downloads.get', [
              'section' => DownloadsController::SECTION_DATABASE,
              'object' => $writtenMandateId,
            ])
            . '?'
            . http_build_query([
              'requesttoken'  => \OCP\Util::callRegister(),
              'fileName' => $fileName,
            ]);
          return '<a class="download-link ajax-download tooltip-auto" title="'.$this->toolTipsService['participant-attachment-download'].'" href="'.$downloadLink.'">' . $fileName . '</a>';
        },
      ]);

    list($mandateRecurringIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'non_recurring',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name' => $this->l->t('Non-Recurring'),
        'css'    => [ 'postfix' => [ 'mandate-non-recurring', 'empty-mandate-project-hidden', ], ],
        'input|A' => 'R',
        'select' => 'O',
        'maxlen' => '1',
        'default' => 0,
        'sort' => true,
        'escape' => false,
        'sqlw' => 'IF($val_qas = "", 0, 1)',
        'values2' => [ 0 => '', 1 => '&#10004;' ],
        'values2|CAP' => [ 0 => '', 1 => '' ],
      ]);

    list($mandateDateIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_date',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Date Issued'),
        'css'      => [ 'postfix' => [ 'mandate-date', 'sepadate', 'empty-mandate-project-hidden', ], ],
        'input' => 'M',
        'input|A' => 'RM',
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'dateformat' => 'medium',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'pre_notification_business_days',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Notification Target Days'),
        'css'      => [ 'postfix' => [ 'pre-notification-days', 'empty-mandate-project-hidden', ], ],
        'select'   => 'N',
        'maxlen'   => 10,
        'align'    => 'right',
        'sort'     => true,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'pre_notification_calendar_days',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Notification Calendar Days'),
        'css'      => [ 'postfix' => [ 'pre-notification-days', 'empty-mandate-project-hidden', ], ],
        'select'   => 'N',
        'maxlen'   => 10,
        'align'    => 'right',
        'sort'     => true,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'written_mandate_id',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Written Mandate'),
        'select'   => 'T',
        'input|LFDV' => 'H',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'written-mandate', 'empty-mandate-project-hidden', ], ],
        'php|ACP' => function(
          $writtenMandateId,
          $op,
          $k,
          $row,
          $recordId,
          $pme,
        ) {
          $mandateReference = $row[$this->joinQueryField(self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference')] ?? null;
          if (empty($mandateReference)) {
            return $this->l->t('please upload written mandate after saving');
          }
          $musician = $this->findEntity(Entities\Musician::class, $recordId['musician_id']);
          $projectId = $row[$this->joinQueryField(self::PROJECTS_TABLE, 'id')];
          $project = $this->findEntity(Entities\Project::class, $projectId);

          return '<div class="file-upload-wrapper">
  <table class="file-upload">'
            . $this->dbFileUploadRowHtml(
              $writtenMandateId,
              fieldId: $recordId['musician_id'],
              optionKey: $row[$this->joinQueryField(self::SEPA_DEBIT_MANDATES_TABLE, 'sequence')],
              subDir: $this->getDebitMandatesFolderName(),
              fileBase: $this->getLegacyDebitMandateFileName($mandateReference),
              overrideFileName: true,
              musician: $musician,
              project: $project)
            . '
  </table>
</div>';
        },
      ]);

    list($mandateDeletedIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'deleted',
      array_merge($this->defaultFDD['deleted'], [
        'tab'    => [ 'id' => 'mandate' ],
        'name'   => $this->l->t('Mandate Revoked'),
        'options' => 'LFCPDV',
      ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'date_of_receipt',
      [
        'tab' => [ 'id' => [ 'account', 'mandate' ] ],
        'name'     => $this->l->t('Last-Used Date'),
        'input'    => 'VR',
        'input|A'  => 'VRH',
        'sql'      => "GREATEST(
  COALESCE(MAX(\$join_col_fqn), ''),
  COALESCE(last_used_date, '')
)",
        'values'    => [
          'description' => 'date_of_receipt',
        ],
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => [ 'last-used-date', ], ],
        'dateformat' => 'medium',
      ]);

    ///////////////

    if (!$this->addOperation() && $projectMode) {
      if ($monetaryFields->count() > 0) {
        $this->makeTotalFeesField($opts['fdd'], $monetaryFields, self::AMOUNT_TAB_ID);
      }

      $totalFeesTargetIndex = count($opts['fdd']);

      $subTotals = [];
      $participantFieldsGenerator($opts['fdd'], $subTotals);

      if (!empty($subTotals)) {
        $totalFeesIndex = count($opts['fdd']);
        $this->makeTotalFeesFields($opts['fdd'], $subTotals, self::AMOUNT_TAB_ID);

        $baseFdd = array_slice($opts['fdd'], 0, $totalFeesTargetIndex);
        $participantFieldsFdd = array_slice($opts['fdd'], $totalFeesTargetIndex, $totalFeesIndex - $totalFeesTargetIndex);
        $totalFeesFdd = array_slice($opts['fdd'], $totalFeesIndex);

        $opts['fdd'] = $baseFdd + $totalFeesFdd + $participantFieldsFdd;
      }
    }

    ///////////////

    if ($projectMode) {
      $opts['filters']['AND'][] =
        $this->joinTables[self::PROJECT_PARTICIPANTS_TABLE].'.project_id = '.$projectId;
    }
    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateSanitizeParticipantFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertGenerateKeys' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_DATA][] =
      function(&$pme, $op, $step, &$row) use ($projectIndex, $mandateDateIndex, $mandateRecurringIndex, $mandateDeletedIndex) {
        switch ($op) {
          case PHPMyEdit::SQL_QUERY_UPDATE:
            if (empty($row[$this->joinQueryField(self::SEPA_DEBIT_MANDATES_TABLE, 'sequence')])) {
              // enable input for unset debit mandate
              $pme->fdd[$projectIndex]['input'] = '';
              $pme->fdd[$mandateDateIndex]['input'] = 'R';
              $pme->fdd[$mandateDeletedIndex]['input'] = 'R';
              $pme->fdd[$mandateRecurringIndex]['input'] = 'R';
            }
            break;
        }
        return true;
      };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {

      if (!empty($row[$this->queryField('deleted')])
          || !empty($row[$this->joinQueryField(self::SEPA_DEBIT_MANDATES_TABLE, 'deleted')])) {
        // disable the "misc" checkboxes essentially disabling the possibility
        // to draw debit-mandates from deleted/revoked bank accounts and debit
        // mandates. There is also a corresponding check in the backend which
        // protects the "API" calls.
        $pme->options = str_replace('M', '', $opts['options']);
      } else {
        $pme->options = $opts['options'];
      }
      return true;
    };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::OPERATION_LIST][PHPMyEdit::TRIGGER_PRE][] = [ $this, 'totalFeesPreFilterTrigger' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * @param iterable $participantFields
   *
   * @return array
   */
  private function tableTabs(iterable $participantFields = []):array
  {
    $tabs = [
      [
        'id' => 'account',
        'tooltip' => $this->l->t('Bank account associated to this debit mandate.'),
        'name' => $this->l->t('Bank Account'),
      ],
      [
        'id' => 'mandate',
        'tooltip' => $this->l->t('Debit mandate, mandate-id, last used date, recurrence'),
        'name' => $this->l->t('Mandate'),
      ],
    ];
    if ($this->projectId > 0) {
      $tabs[] = [
        'id' => self::AMOUNT_TAB_ID,
        'tooltip' => $this->l->t('Show the amounts to draw by debit transfer, including sum of payments
received so far'),
        'name' => $this->l->t('Amount')
      ];
    }

    foreach ($participantFields as $field) {

      $extraTab = $field['tab'] ?: ProjectParticipantFieldsService::defaultTabId($field->getMultiplicity(), $field->getDataType());
      if (empty($extraTab)) {
        continue;
      }

      if ($extraTab == 'finance') {
        $extraTab = self::AMOUNT_TAB_ID;
      }

      foreach ($tabs as $tab) {
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
        $tabs[] = $newTab;
      }
    }

    if ($this->expertMode) {
      $tabs[] = [
        'id' => 'miscinfo',
        'tooltip' => $this->toolTipsService['page-renderer:tab:miscinfo'],
        'name' => $this->l->t('Miscinfo'),
      ];
    }

    $tabs[] = [
      'id' => 'tab-all',
      'tooltip' => $this->toolTipsService['page-renderer:tab:showall'],
      'name' => $this->l->t('Display all columns'),
    ];

    return $tabs;
  }

  /**
   * Safe-guard against unwanted changes
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
  public function beforeUpdateSanitizeFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $accountSequence = 'sequence';
    $debitMandateSequenceKey = $this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'sequence');
    $readOnlyKeys = [ $accountSequence, $debitMandateSequenceKey ];
    $unsafeChanged = array_intersect($changed, $readOnlyKeys);
    if (!empty($unsafeChanged)) {
      throw new Exceptions\DatabaseInconsistentValueException(
        $this->l->t(
          'The change-set contains read-only keys: %s.',
          implode(', ', $unsafeChanged)
        ));
    }
    $debitMandateSequence = $newValues[$debitMandateSequenceKey];

    $mandateNonRecurring = $this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'non_recurring');
    $newValues[$mandateNonRecurring] = (int)$newValues[$mandateNonRecurring];
    $oldValues[$mandateNonRecurring] = (int)$oldValues[$mandateNonRecurring];
    if ($oldValues[$mandateNonRecurring] == $newValues[$mandateNonRecurring]) {
      Util::unsetValue($changed, $mandateNonRecurring);
    } else {
      $changed[] = $mandateNonRecurring;
      $changed = array_unique($changed);
    }

    // Remove "written-mandate-id" because it is handled separately
    $writtenMandateIdKey = $this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'written_mandate_id');
    unset($newValues[$writtenMandateIdKey]);
    unset($oldValues[$writtenMandateIdKey]);
    Util::unsetValue($changed, $writtenMandateIdKey);

    // convert to the KEY:VALUE format understood by beforeUpdateDoUpdateAll()
    foreach (['newValues', 'oldValues'] as $valueSet) {
      foreach (${$valueSet} as $key => $value) {
        if ($key == $debitMandateSequenceKey) {
          continue;
        }
        if (strpos($key, self::SEPA_DEBIT_MANDATES_TABLE . self::JOIN_FIELD_NAME_SEPARATOR) === 0) {
          ${$valueSet}[$key] = $debitMandateSequence . self::JOIN_KEY_SEP . $value;
        }
      }
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');
    return true;
  }

  /**
   * Before insert handler.
   * - check whether it is is a new IBAN for the musician, otherwise redirect to the change dialog
   * - prepare data to be usable by beforeUpdateDoUpdateAll() and beforeInsertDoInsertAll()
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
  public function beforeInsertGenerateKeys(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    /** @var Entities\Musician $musician */
    $musician = $this->getReference(Entities\Musician::class, $newValues['musician_id']);
    $sequence = $newValues['sequence']??null;

    $bankAccountsRepository = $this->getDatabaseRepository(Entities\SepaBankAccount::class);

    if (!empty($newValues['sequence'])) {
      // if we have a sequence, fetch the bank account
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $bankAccountsRepository->find([
        'musician' => $musician,
        'sequence' => $sequence,
      ]);
      if (empty($bankAccount)) {
        throw new Exceptions\DatabaseEntityNotFoundException(
          $this->l->t(
            'Unable to find the bank-account with sequence "%1$s" for the musician "%2$s".',
            [ $sequence, $musician->getPublicName(true) ]
          )
        );
      }
      $this->logInfo('FOUND EXISTING WITH IBAN ' . $bankAccount->getIban());
    } else {
      // Still make sure that an existing bank-account with the same IBAN is
      // re-used. Unfortunately findBy() does not work with encrypted fields,
      // so fetch all and filter in PHP.
      $iban = $newValues['iban'];
      $bankAccountCandidates = $bankAccountsRepository->findBy([
        'musician' => $musician,
        //'iban' => $iban,
        'deleted' => null,
      ]);
      $bankAccountCandidates = array_values(
        array_filter(
          $bankAccountCandidates, fn(Entities\SepaBankAccount $account) => $account->getIban() == $iban
        ));
      /** @var Entities\SepaBankAccount $account */
      foreach ($bankAccountCandidates as $account) {
        $this->logInfo('IBAN ' . $account->getIban());
      }
      $this->logInfo('NUMBER OF EXISTING ' . count($bankAccountCandidates));
      if (count($bankAccountCandidates) > 1) {
        throw new Exceptions\DatabaseEntityNotUniqueException(
          $this->l->t(
            'More than one (%1$d) active bank account found for musician "%2$s" and IBAN "%3$s".',
            [ count($bankAccountCandidates), $musician->getPublicName(true), $newValues['iban'] ]
          )
        );
      }
      $bankAccount = $bankAccountCandidates[0];
    }

    $maxSequence = $this->getDatabaseRepository(Entities\SepaDebitMandate::class)->sequenceMax($musician);
    $debitMandateSequence = $maxSequence + 1;
    $debitMandateSequenceKey = $this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'sequence');
    $newValues[$debitMandateSequenceKey] = $debitMandateSequence;

    // add some missing values
    $newValues[$this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'bank_account_sequence')] =
      $newValues['sequence'];
    $newValues[$this->joinTableFieldName(self::SEPA_DEBIT_MANDATES_TABLE, 'project_id')] =
      $newValues[$this->joinTableFieldName(self::PROJECTS_TABLE, 'id')];

    // convert to the KEY:VALUE format understood by beforeInsert...
    foreach ($newValues as $key => $value) {
      if ($key == $debitMandateSequenceKey) {
        continue;
      }
      if (strpos($key, self::SEPA_DEBIT_MANDATES_TABLE . self::JOIN_FIELD_NAME_SEPARATOR) === 0) {
        $newValues[$key] = $debitMandateSequence . self::JOIN_KEY_SEP . $value;
      }
    }

    $changed = array_keys($newValues);

    if (empty($bankAccount)) {
      $maxSequence = $bankAccountsRepository->sequenceMax($musician);
      $newValues['sequence'] = $maxSequence + 1;
    } else {
      // redirect to the updater

      if (!$bankAccount->unused()) {
        $newValues['bank_account_owner'] = $bankAccount->getBankAccountOwner();
      }

      $oldValues['musician_id'] = $newValues['musician_id'];
      $oldValues['sequence'] = $newValues['sequence'];
      $oldValues['deleted'] = $newValues['deleted'];
      $oldValues['iban'] = $newValues['iban'];
      $oldValues['iban'] = $newValues['iban'];
      $oldValues['blz'] = $newValues['blz'];
      $oldValues['bic'] = $newValues['bic'];

      $changed = [];
      foreach (array_merge(array_keys($oldValues), array_keys($newValues)) as $key) {
        if ($newValues[$key] !== $oldValues[$key]) {
          $changed[] = $key;
        }
      }

      return $this->beforeUpdateDoUpdateAll($pme, $op, $step, $oldValues, $changed, $newValues);
    }

    return true;
  }

  /**
   * Translate the tab-name to an id if the name is set in the tab
   * definitions of the table. This is needed by the
   * ParticipantFieldsTrait in order to move extra-fields to the
   * correct tab.
   *
   * @param string $idOrName Tab id or translated tab name.
   *
   * @return string
   */
  protected function tableTabId(string $idOrName):string
  {
    if ($idOrName === 'finance') {
      $idOrName = self::AMOUNT_TAB_ID;
    }
    $dflt = $this->tableTabs();
    foreach ($dflt as $tab) {
      if ($idOrName === $tab['name'] || $idOrName === $this->l->t($tab['id'])) {
        return $tab['id'];
      }
    }

    return $idOrName;
  }
}
