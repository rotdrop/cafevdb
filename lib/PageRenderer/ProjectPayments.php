<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Controller\DownloadsController;

/** Table generator for Instruments table. */
class ProjectPayments extends PMETableViewBase
{
  use FieldTraits\ParticipantFileFieldsTrait;
  use FieldTraits\CryptoTrait;

  const TEMPLATE = 'project-payments';
  const TABLE = self::COMPOSITE_PAYMENTS_TABLE;
  const ALL_BALANCE_DOCUMENTS_TABLE = self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE . self::VALUES_TABLE_SEP . 'all';
  const DEBIT_NOTES_TABLE = self::SEPA_BULK_TRANSACTIONS_TABLE;

  const ROW_TAG_PREFIX = '0;';

  /** @var ProjectService */
  protected $projectService;

  /** @var FinanceService */
  private $financeService;

  /** @var UserStorage */
  protected $userStorage;

  /** @var DatabaseStorageUtil */
  protected $databaseStorageUtil;

  /** @var array */
  private $compositePaymentExpanded = [];

  /** @var Entities\Project */
  private $project;

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\CompositePayment::class,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'sql' => 'SELECT
  __t1.*,
  GROUP_CONCAT(
    DISTINCT
    CONCAT_WS("' . self::COMP_KEY_SEP . '", __t2.project_id, __t2.field_id, BIN2UUID(__t2.option_key))
    ORDER BY __t2.project_id ASC, __t2.field_id ASC
  ) AS receivable_keys
FROM ' . self::MUSICIANS_TABLE . ' __t1
LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE . ' __t2
ON __t2.musician_id = __t1.id
GROUP BY __t1.id',
      'identifier' => [
        'id' => 'musician_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::SEPA_BANK_ACCOUNTS_TABLE => [
      'entity' => Entities\SepaBankAccount::class,
      'identifier' => [
        'musician_id' => 'musician_id',
        'sequence' => 'bank_account_sequence',
      ],
      'column' => 'sequence',
      'flags' => self::JOIN_READONLY,
    ],
    self::SEPA_DEBIT_MANDATES_TABLE => [
      'entity' => Entities\SepaDebitMandate::class,
      'identifier' => [
        'musician_id' => 'musician_id',
        'sequence' => 'debit_mandate_sequence',
      ],
      'column' => 'sequence',
      'flags' => self::JOIN_READONLY,
    ],
    self::SEPA_BULK_TRANSACTIONS_TABLE => [
      'entity' => Entities\SepaBulkTransactions::class,
      'identifier' => [
        'id' => 'sepa_transaction_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PAYMENTS_TABLE => [
      // not elegant, but should add an additional row in front of the
      // collection of project-payments.
      //
      // Note that the fancy "composite_key" have to be kept in sync with the
      // composite_key fdd below
      'sql' => "SELECT
  CONCAT('".self::ROW_TAG_PREFIX."', __t1.composite_payment_id) AS row_tag,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::COMP_KEY_SEP."', __t1.project_id, __t1.field_id, BIN2UUID(__t1.receivable_key)) ORDER BY __t1.id) AS receivable_composite_key,
  GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.id) AS id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.amount) ORDER BY __t1.id) AS amount,
  SUM(__t1.amount) AS total_amount,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.subject) ORDER BY __t1.id) AS subject,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.project_id) ORDER BY __t1.id) AS project_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.field_id) ORDER BY __t1.id) AS field_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.receivable_key) ORDER BY __t1.id) AS receivable_key,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.balance_document_sequence) ORDER BY __t1.id) AS balance_document_sequence,
  GROUP_CONCAT(DISTINCT __t1.balance_document_sequence ORDER BY __t1.id) AS balance_document_sequence_values,
  GROUP_CONCAT(DISTINCT __t1.project_id) AS project_ids,
  __t1.musician_id,
  __t1.composite_payment_id
FROM ".self::PROJECT_PAYMENTS_TABLE." __t1
GROUP BY __t1.composite_payment_id
UNION
SELECT
  __t2.id AS row_tag,
  CONCAT_WS('".self::COMP_KEY_SEP."', __t2.project_id, __t2.field_id, BIN2UUID(__t2.receivable_key)) AS receivable_composite_key,
  __t2.id,
  __t2.amount,
  __t2.amount AS total_amount,
  __t2.subject,
  __t2.project_id,
  __t2.field_id,
  __t2.receivable_key,
  __t2.balance_document_sequence,
  __t2.balance_document_sequence AS balance_document_sequence_values,
  __t2.project_id AS project_ids,
  __t2.musician_id,
  __t2.composite_payment_id
FROM ".self::PROJECT_PAYMENTS_TABLE." __t2",
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'id' => false,
      ],
      'filter' => [
        'composite_payment_id' => 'id',
      ],
      'column' => 'row_tag',
      'flags' => self::JOIN_GROUP_BY,
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'project_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'field_id',
        ],
        'project_id' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'project_id',
        ]
      ],
      'column' => 'id',
    ],
    self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDataOption::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'field_id' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'field_id',
        ],
        'key' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'receivable_key',
        ],
      ],
      'column' => 'key',
      'encode' => 'BIN2UUID(%s)',
    ],
    self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE => [
      'entity' => Entities\ProjectBalanceSupportingDocument::class,
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'project_id',
        ],
        'sequence' => [
          'table' => self::PROJECT_PAYMENTS_TABLE,
          'column' => 'balance_document_sequence',
        ],
      ],
      'column' => 'sequence',
    ],
  ];

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ProjectService $projectService
    , ProjectParticipantFieldsService $participantFieldsService
    , FinanceService $financeService
    , UserStorage $userStorage
    , DatabaseStorageUtil $databaseStorageUtil
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->projectService = $projectService;
    $this->participantFieldsService = $participantFieldsService;
    $this->financeService = $financeService;
    $this->userStorage = $userStorage;
    $this->databaseStorageUtil = $databaseStorageUtil;
    $this->compositePaymentExpanded = $this->requestParameters['compositePaymentExpanded'];
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      $this->projectName = $this->project->getName();
    }
    $this->initCrypto();
  }

  public function shortTitle()
  {
    return $this->l->t('Payments for project "%s"', [ $this->projectName ]);
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;

    $projectMode = $this->projectId > 0;
    if (!$projectMode) {
      throw new \InvalidArgumentException('Project-id and/or -name must be given.');
    }

    $opts            = [];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'compositePaymentExpanded' => $this->compositePaymentExpanded,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s). 'id' and self::PROJECT_PAYMENTS_TABLE.id must
    // be there in order to group the fields correctly, as we "blow"
    // up the table by joining self::PROJECT_PAYMENTS_TABLE.
    $opts['sort_field'] = [
      '-date_of_receipt',
      $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'project_id'),
      'musician_id',
      'id',
      $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'row_tag'),
    ];

    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    $opts['css']['postfix'] = [
      self::TEMPLATE,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_DIRECT_CHANGE,
    ];

    // in order to be able to collapse payment details:
    $opts['css']['row'] = function($name, $position, $divider, $row, $pme) {
      static $evenOdd = [ 'even', 'odd' ];
      static $lastCompositeId = -1;
      static $oddProjectPayment = true;
      static $oddCompositePayment = false;

      $compositePaymentId = $row['qf'.$pme->fdn['id']];
      $projectPaymentId = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::PROJECT_PAYMENTS_TABLE)]];

      $cssClasses = ['composite-payment'];
      if ($lastCompositeId != $compositePaymentId) {
        $lastCompositeId = $compositePaymentId;
        $oddProjectPayment = true;
        $cssClasses[] = 'first';
        if (!($this->compositePaymentExpanded[$compositePaymentId]??false)) {
          $cssClasses[] = 'following-hidden';
        }
        // $cssClasses[] = 'disable-row-click';
        $cssClasses[] = $evenOdd[(int)$oddCompositePayment];
        $oddCompositePayment = !$oddCompositePayment;
      } else {
        $cssClasses[] = 'following';
        $cssClasses[] = 'project-payment';
        $cssClasses[] = $evenOdd[(int)$oddProjectPayment];
        $oddProjectPayment = !$oddProjectPayment;
      }

      return $cssClasses;
    };

    // wrap the composite groups into tbody elements, otherwise the
    // groups cannot be hidden individually.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function($pme, $op, $step, $row) {
      // $this->logInfo('DATA TRIGGER '.$op.' '.$step.' '.print_r($row, true));
      static $lastCompositeId = -1;

      $compositePaymentId = $row['qf'.$pme->fdn['id']];

      if ($lastCompositeId != $compositePaymentId) {
        if ($lastCompositeId > 0) {
          echo '</tbody>
<tbody>';
        }
        $lastCompositeId = $compositePaymentId;
      }
      return true;
    };

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'] ?? [], [
        'form'  => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => [
          [
            'id' => 'booking',
            'tooltip' => $this->l->t('General accounting data'),
            'name' => $this->l->t('Booking Entry'),
          ], [
            'id' => 'transaction',
            'tooltip' => $this->l->t('Bulk-transaction data'),
            'name' => $this->l->t('Bank Transaction'),
          ], [
            'id' => 'tab-all',
            'tooltip' => $this->toolTipsService['pme-showall-tab'],
            'name' => $this->l->t('Display all columns'),
          ],
        ],
    ]);
    if ($this->addOperation()){
      $opts['display']['tabs'] = false;
    }

    $opts['fdd']['id'] = [
      'tab' => [ 'id' => 'tab-all', ],
      'name'     => 'id',
      'select'   => 'T',
      'input'    => ($this->expertMode ? 'R' : 'RH'),
      'input|A' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => null,
      'sort'     => true,
      'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
        $html = '';
        if ($this->isCompositeRow($row, $pme)) {
          $html = '<input type="hidden" class="expanded-marker" name="compositePaymentExpanded['.$recordId['id'].']" value="'.(int)($this->compositePaymentExpanded[$recordId['id']]??0).'"/>';
          if ($this->expertMode) {
            $html .= '<span class="cell-wrapper">' . $value . '</span>';
          }
        }
        return $html;
      },
    ];

    $opts['fdd']['musician_id'] = [
      'name'     => $this->l->t('Musician-Id'),
      'input'    => 'RH',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    $opts['fdd']['sepa_transaction_id'] = [
      'name'     => $this->l->t('Bulk-Transaction Id'),
      'input'    => 'RH',
      'options'  => 'LFAVCPD',
    ];

    $joinTables = $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'row_tag', [
        'name' => 'row_tag',
        'input' => 'RH',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'composite_payment_id', [
        'name' => 'composite_payment_id',
        'input' => 'RH',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'name' => $this->l->t('Musician'),
        'css' => [ 'postfix' => [ 'instrumentation-id', 'allow-empty' ], ],
        'select' => 'D',
        'input' => 'M',
        'input|C' => 'R',
        'values' => [
          'description' => [
            'columns' => [ self::musicianPublicNameSql() ],
            'divs' => [],
            'ifnull' => [ false, false ],
            'cast' => [ false ],
          ],
          'data' => 'receivable_keys',
          'filters' => (!$projectMode
                        ? null
                        : parent::musicianInProjectSql($this->projectId)),
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'name' => $this->l->t('User Id'),
        'input' => 'RH',
      ]);

    $opts['fdd']['amount'] = array_merge(
      $this->defaultFDD['money'], [
        'name' => $this->l->t('Total Amount'),
        'input|LFAP' => 'H',
        'input|CDV' => 'M',
        'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
          return $this->moneyValue($value);
        },
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount',
      array_merge(
        $this->defaultFDD['money'], [
          'sql|LF' => 'IF($join_table.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%", $main_table.amount, $join_col_fqn)',
          'sql' => '$join_col_fqn',
          'name' => $this->l->t('Amount'),
          'input' => 'M',
          'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
            return $this->moneyValue($value);
          },
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'imbalance',
      Util::arrayMergeRecursive(
        $this->defaultFDD['money'], [
          'sql' => '$main_table.amount - $join_table.total_amount',
          'css' => [ 'postfix' => [ 'tooltip-auto', ] ],
          'name' => $this->l->t('Imbalance'),
          'input' => 'VR',
          'options' => 'LFCDV',
          'php|CDV' => function($value, $action, $k, $row, $recordId, $pme) {
            if ($pme->hidden($k)) {
              return '';
            }
            $name = $pme->fds[$k];
            $html = $pme->htmlHiddenData($name, $value);
            $html .= '<span class="cell-wrapper">' . $this->moneyValue($value) . '</span>';
            return $html;
          },
          'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return $this->moneyValue($value);
            }
            return '';
          },
          'display|ACP' => [
            'postfix' => null,
            'popup' => 'tooltip',
          ],
          'tooltip' => $this->toolTipsService['project-payments:imbalance'],
        ]
    ));

    $opts['fdd']['date_of_receipt'] = array_merge(
      $this->defaultFDD['date'], [
        'tab' => [ 'id' => 'booking' ],
        'name' => $this->l->t('Date of Receipt'),
        'input' => 'M',
      ]);

    $opts['fdd']['subject'] = [
      'name' => $this->l->t('Subject'),
      'css'  => [ 'postfix' => [ 'subject', 'squeeze-subsequent-lines', 'clip-long-text', ], ],
      'sql|LFVD' => 'REPLACE($main_table.subject, \'; \', \'<br/>\')',
      'input|LFVD' => 'HRM',
      'select' => 'T',
      'display|LF' => [ 'popup' => 'data' ],

      'escape' => true,
      'sort' => true,
      'maxlen' => FinanceService::SEPA_PURPOSE_LENGTH,
      'textarea|ACP' => [
        'css' => 'constrained',
        'rows' => 4,
        'cols' => 35,
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'subject',
      [
        'tab' => [ 'id' => 'booking' ],
        'name' => $this->l->t('Subject'),
        'input'  => 'M',
        'css'  => [ 'postfix' => [ 'subject', 'squeeze-subsequent-lines', 'clip-long-text', ], ],
        'sql|LF' => 'IF($join_table.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%", REPLACE($main_table.subject, \'; \', \'<br/>\'), $join_col_fqn)',
        'sql' => '$join_col_fqn',
        'display|LF' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
        ],
        'maxlen' => FinanceService::SEPA_PURPOSE_LENGTH,
        'textarea|ACP' => [
          'css' => 'constrained',
          'rows' => 4,
          'cols' => 35,
        ],
      ]);

    /**
     * The following is there in order to remove split-transactions. There will
     * also be a dedicated "add a new split".
     */
    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'id', [
        'tab' => [ 'id' => [ 'booking', 'transaction' ] ],
        'css' => [ 'postfix' => [ 'payment-id', 'chosen-dropup', ], ],
        'name' => $this->l->t('Receivables'),
        'select' => 'M',
        'input|LF' => 'H',
        'options' => 'PCDV',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn)',
        'values' => [
          'table' => 'SELECT
  pp.id,
  pp.composite_payment_id,
  pp.musician_id,
  pp.amount,
  m.first_name,
  m.sur_name,
  m.nick_name,
  m.display_name,
  ppf.project_id,
  ppf.id AS field_id,
  ppfo.key AS receivable_key,
  IF(ppf.multiplicity IN ("'.
          FieldMultiplicity::SIMPLE.'","'.
          FieldMultiplicity::SINGLE.'","'.
          FieldMultiplicity::GROUPOFPEOPLE.'"),
     COALESCE(ppftr.content, ppf.name),
     CONCAT_WS(" - ", COALESCE(ppftr.content, ppf.name), COALESCE(ppfotr.content, ppfo.label))
  ) AS receivable_display_label,
  ppf.multiplicity,
  COALESCE(ppftr.content, ppf.name) AS field_name
  FROM ' . self::PROJECT_PAYMENTS_TABLE . ' pp
  LEFT JOIN ' . self::MUSICIANS_TABLE . ' m
    ON pp.musician_id = m.id
  LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE . ' ppfo
    ON ppfo.field_id = pp.field_id
      AND ppfo.key = pp.receivable_key
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppfotr
    ON ppfotr.locale = "'.($this->l10n()->getLanguageCode()).'"
      AND ppfotr.object_class = "'.addslashes(Entities\ProjectParticipantFieldDataOption::class).'"
      AND ppfotr.field = "label"
      AND ppfotr.foreign_key = CONCAT_WS(" ", ppfo.field_id, BIN2UUID(ppfo.key))
  LEFT JOIN '.self::PROJECT_PARTICIPANT_FIELDS_TABLE.' ppf
    ON ppf.id = pp.field_id
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppftr
    ON ppftr.locale = "'.($this->l10n()->getLanguageCode()).'"
      AND ppftr.object_class = "'.addslashes(Entities\ProjectParticipantField::class).'"
      AND ppftr.field = "name"
      AND ppftr.foreign_key = ppf.id',
          'column' => 'id',
          'description' => [
            'columns' => [
              'CONCAT_WS(" ", "' . $this->currencySymbol() . '", FORMAT($table.amount, 2, "' . ($this->l10n()->getLocaleCode()) . '"))',
              '$table.receivable_display_label',
            ],
            'divs' => [ ' - ' ],
            'ifnull' => [ false, false ],
            'cast' => [ false, false ],
          ],
          'join' => '$main_table.id = $join_table.composite_payment_id',
          'filters' => '$table.composite_payment_id = $record_id[id]',
          'data' => 'JSON_OBJECT(
  "musicianId", $table.musician_id
  , "projectId", $table.project_id
  , "projectPaymentId", $table.id
  , "compositePaymentId", $table.composite_payment_id
  , "fieldId", $table.field_id
  , "receivableKey", BIN2UUID($table.receivable_key)
  , "amount", $table.amount
)',
          'groups' => 'IF($table.multiplicity IN ("'.
          FieldMultiplicity::SIMPLE.'","'.
          FieldMultiplicity::SINGLE.'","'.
          FieldMultiplicity::GROUPOFPEOPLE.'"),
  "'.$this->l->t('Single Options').'",
  $table.field_name)',
        ],
        'valueGroups|CP' => [ -1 => $this->l->t('Operations'), ],
        'values2|CP' => [ -1 => $this->l->t('Add a new Receivable'), ],
        'values2glue' => '<br/>',
        'php|VD' => function($value, $action, $k, $row, $recordId, $pme) {
          $compositeKeyIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key')];
          return $this->createSupportingDocumentsDownload($value, $action, $compositeKeyIndex, $row, $recordId, $pme);
        },
      ]);

    list(, $compositeKeyKey) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key',
      [
        'tab' => [ 'id' => [ 'booking', 'transaction' ] ],
        'name' => $this->l->t('Receivables'),
        'select' => 'M',
        'select|ACP' => 'D',
        'input' => 'M',
        'css'  => [ 'postfix' => [ 'receivable', 'allow-empty', 'squeeze-subsequent-lines', 'chosen-dropup', ], ],
        // Pre-computed key for composite-payment row
        'sql' => $this->joinTables[self::PROJECT_PAYMENTS_TABLE].'.receivable_composite_key',
        'values' => [
          'table' => 'SELECT
  IF(ppfo.label IS NOT NULL, ppfo.field_id, -1) AS sort_field,
  CONCAT_WS("'.self::COMP_KEY_SEP.'", ppf.project_id, ppf.id, BIN2UUID(ppfo.key)) AS composite_key,
  ppfo.*,
  IF(ppf.multiplicity IN ("'.
          FieldMultiplicity::SIMPLE.'","'.
          FieldMultiplicity::SINGLE.'","'.
          FieldMultiplicity::GROUPOFPEOPLE.'"),
     COALESCE(ppftr.content, ppf.name),
     CONCAT_WS(" - ", COALESCE(ppftr.content, ppf.name), COALESCE(ppfotr.content, ppfo.label))
  ) AS display_label,
  COALESCE(ppftr.content, ppf.name) AS field_name,
  ppf.project_id AS project_id,
  ppf.data_type AS data_type,
  ppf.multiplicity AS multiplicity
  FROM '.self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE.' ppfo
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppfotr
    ON ppfotr.locale = "'.($this->l10n()->getLanguageCode()).'"
      AND ppfotr.object_class = "'.addslashes(Entities\ProjectParticipantFieldDataOption::class).'"
      AND ppfotr.field = "label"
      AND ppfotr.foreign_key = CONCAT_WS(" ", ppfo.field_id, BIN2UUID(ppfo.key))
  LEFT JOIN '.self::PROJECT_PARTICIPANT_FIELDS_TABLE.' ppf
    ON ppfo.field_id = ppf.id
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppftr
    ON ppftr.locale = "'.($this->l10n()->getLanguageCode()).'"
      AND ppftr.object_class = "'.addslashes(Entities\ProjectParticipantField::class).'"
      AND ppftr.field = "name"
      AND ppftr.foreign_key = ppf.id',
          // 'encode' => 'BIN2UUID(%s)',
          'description' => '$table.display_label',
          'join' => ('$join_table.field_id = '
                     . $this->joinTables[self::PROJECT_PAYMENTS_TABLE].'.field_id'
                     . ' AND $join_table.key = '
                     . $this->joinTables[self::PROJECT_PAYMENTS_TABLE].'.receivable_key'
                     . ' AND $join_table.project_id = '
                     . $this->joinTables[self::PROJECT_PAYMENTS_TABLE].'.project_id'),
          'groups' => 'IF($table.multiplicity IN ("'.
          FieldMultiplicity::SIMPLE.'","'.
          FieldMultiplicity::SINGLE.'","'.
          FieldMultiplicity::GROUPOFPEOPLE.'"),
  "'.$this->l->t('Single Options').'",
  $table.field_name)',
          'orderby' => '$table.sort_field ASC, $table.display_label ASC',
          'filters' => ('$table.deleted IS NULL'
                        . ' AND $table.data_type = \''.FieldType::SERVICE_FEE."'"
                        . ' AND NOT $table.key = CAST(\'\0\' AS BINARY(16))'
                        . ($projectMode ? ' AND $table.project_id = '.$projectId : '')),
        ],
        'values2glue' => '<br/>',
        'display' => [
          'prefixBlah' => function($op, $where, $k, $row, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">';
            }
          },
          'postfixBlah' => function($op, $where, $k, $row, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return '</div></div>';
            }
          },
          'popup' => function($cellData, $k, $row, $pme) {
            return $this->isCompositeRow($row, $pme) ? strip_tags($cellData, '<br>') : '';
          },
        ],
        'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
          return $this->createSupportingDocumentsDownload($value, $action, $k, $row, $recordId, $pme);
        },
      ]);

    // Restrict the choices to the receivables of the actual musician.
    $opts['fdd'][$compositeKeyKey]['values|C'] = $opts['fdd'][$compositeKeyKey]['values'];
    $musicianReceivableFilter = $opts['fdd'][$compositeKeyKey]['values|C']['filters'] .=
      ' AND $table.composite_key
         IN (SELECT DISTINCT CONCAT_WS("'.self::COMP_KEY_SEP.'", ppfd.project_id, ppfd.field_id, BIN2UUID(ppfd.option_key))
  FROM ' . self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE . ' ppfd
  WHERE ppfd.musician_id = (SELECT cp.musician_id FROM ' . self::COMPOSITE_PAYMENTS_TABLE . ' cp WHERE cp.id = $record_id[id])'
      . ($projectMode ? ' AND ppfd.project_id = '.$projectId : '')
      . ')';

    $opts['fdd']['supporting_document_id'] = [
      'tab' => [ 'id' => [ 'booking', ] ],
      'css' => [ 'postfix' => [ 'supporting-document', ], ],
      'name' => $this->l->t('Supporting Document'),
      'input|ALF' => 'HR',
      'options' => 'LFACDPV',
      'php|CP' => function($value, $action, $k, $row, $recordId, $pme) {

        if ($pme->hidden($k)) {
          return '';
        }

        $musicianId = $row['qf'.$pme->fdn['musician_id']];
        /** @var Entities\Musician $musician */
        $musician = $this->findEntity(Entities\Musician::class, $musicianId);
        $fileName = $this->getLegacyPaymentRecordFileName($recordId['id'], $musician->getUserIdSlug());

        return '<div class="file-upload-wrapper">
  <table class="file-upload">'
          . $this->dbFileUploadRowHtml(
            $value,
            fieldId: $musicianId,
            optionKey: $recordId['id'],
            subDir: $this->getSupportingDocumentsFolderName() . UserStorage::PATH_SEP . $this->getBankTransactionsFolderName(),
            fileBase: $fileName,
            overrideFileName: true,
            musician: $musician,
            project: null,
          )
          . '
  </table>
</div>';
      },
      'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {

        $musicianId = $row['qf'.$pme->fdn['musician_id']];
        /** @var Entities\Musician $musician */
        $musician = $this->findEntity(Entities\Musician::class, $musicianId);
        $fileName = $this->getLegacyPaymentRecordFileName($recordId['id'], $musician->getUserIdSlug());

        if (!empty($value)) {

          /** @var Entities\File $file */
          $file = $this->getDatabaseRepository(Entities\File::class)->find($value);
          if (empty($file)) {
            $this->logError('File not found for musician "' . $musician->getPublicName(). ' file-id ' . $value);
            return $value;
          }

          $extension = pathinfo($file->getFileName(), PATHINFO_EXTENSION);

          $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink(
            $value, $fileName);

          $subDirPrefix =
            UserStorage::PATH_SEP . $this->getDocumentsFolderName()
            . UserStorage::PATH_SEP . $this->getSupportingDocumentsFolderName()
            . UserStorage::PATH_SEP . $this->getBankTransactionsFolderName();
          $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician, dry: true);
          try {
            $filesAppTarget = md5($this->userStorage->getFilesAppLink($participantFolder));
            $filesAppLink = $this->userStorage->getFilesAppLink($participantFolder . $subDirPrefix, true);
            $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="'.$this->toolTipsService['project-payments:payment:open-parent'].'"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
          } catch (\OCP\Files\NotFoundException $e) {
            $this->logInfo('No file found for ' . $participantFolder . $subDirPrefix);
            $filesAppLink = '';
          }
          return $filesAppLink
            . '<a class="download-link ajax-download tooltip-auto" title="'.$this->toolTipsService['project-payments:payment:document'].'" href="'.$downloadLink.'">' . $fileName . '.' . $extension . '</a>';
        } else {
          return $value;
        }
      },
    ];

    $opts['fdd']['balance_document_sequence'] = [
      'name' => $this->l->t('Composite Project Balance'),
      'tab' => [ 'id' => 'booking' ],
      'css' => [
        'postfix' => [
          'allow-empty',
          'project-balance-documents',
          'chosen-dropup',
          'squeeze-subsequent-lines',
          'clip-long-text',
        ],
      ],
      'input|LF' => 'HR',
      'select' => 'D',
      'sql' => '$main_table.$field_name',
      'values' => [
        'table' => self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE,
        'column' => 'sequence',
        'join' => [ 'reference' => $this->joinTables[self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE], ],
        'description' => [
          'columns' => [ 'LPAD($table.sequence, 3, "0")', ],
          'divs' => [ -1 => $this->projectName . '-', 0 => '/', ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'data' => 'CONCAT("' . $this->projectName . '", "-", LPAD($table.sequence, 3, "0"), "/")',
        // 'filters' => '$table.project_id = $record_id[project_id]',
      ],
      'tooltip' => $this->toolTipsService['page-renderer:project-payments:project-balance'],
      'display' => [
        'prefix' => function($op, $pos, $k, $row, $pme) {
          if (!$this->isCompositeRow($row, $pme)) {
            return null;
          }
          $value = $row['qf' . $k . '_idx'];
          if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
            return null;
          }

          $documentPathChain = [ $this->getProjectBalancesPath() ];
          if ($this->project->getType() == ProjectType::TEMPORARY) {
            $documentPathChain[] = $this->project->getYear();
          };
          $documentPathChain[] = $this->project->getName();
          $documentPathChain[] = $this->getSupportingDocumentsFolderName();

          $documentParentPath = implode('/', $documentPathChain);
          $filesAppTarget = md5($documentParentPath);
          if (!empty($value)) {
            if (is_numeric($value)) {
              $value = sprintf('%s-%03d', $this->projectName, $value);
            }
          }

          try {
            $filesAppParentLink = $this->userStorage->getFilesAppLink($documentParentPath, subDir: true);
            $filesAppLink = empty($value)
              ? $filesAppParentLink
              : $filesAppParentLink . '/' . $value;
          } catch (\OCP\Files\NotFoundException $e) {
            $this->logInfo('No file found for ' . $documentParentPath);
              $filesAppParentLink = $filesAppLink = '';
          }

          $filesAppAnchor = '
<a href="' . $filesAppLink . '"
   data-parent-link="' . Util::htmlEscape($filesAppParentLink) . '"
   target=" . $filesAppTarget . "
   title="' . $this->toolTipsService['page-renderer:project-payments:project-balance:open'] . '"
   class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
></a>';

          return '<div class="flex-container"><span class="pme-cell-prefix">' . $filesAppAnchor . ' </span><span class="pme-cell-content">'
            . '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">';
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {
          return '</div></div></span></div>';
        },
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'balance_document_sequence', [
        'name' => $this->l->t('Parts Project Balances'),
        'tab' => [ 'id' => 'booking' ],
        'css' => [
          'postfix' => [
            'allow-empty',
            'project-balance-documents',
            'chosen-dropup',
            'squeeze-subsequent-lines',
            'clip-long-text',
          ],
        ],
        'select' => 'D',
        'sql|LF' => 'IF(
  ' . $this->joinTables[self::PROJECT_PAYMENTS_TABLE] . '.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%",
  CONCAT_WS(
    ",",
    $main_table.balance_document_sequence,
    ' . $this->joinTables[self::PROJECT_PAYMENTS_TABLE] . '.balance_document_sequence_values
  ),
  $join_col_fqn)',
        'sql' => 'IF(
  ' . $this->joinTables[self::PROJECT_PAYMENTS_TABLE] . '.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%",
  ' . $this->joinTables[self::PROJECT_PAYMENTS_TABLE] . '.balance_document_sequence_values,
  $join_col_fqn)',
        'values' => [
          'table' => self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE,
          'column' => 'sequence',
          'join' => [ 'reference' => $this->joinTables[self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE], ],
          'description' => [
            'columns' => [ 'LPAD($table.sequence, 3, "0")', ],
            'divs' => [ -1 => $this->projectName . '-', 0 => '/', ],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'data' => 'CONCAT("' . $this->projectName . '", "-", LPAD($table.sequence, 3, "0"), "/")',
          // 'filters' => '$table.project_id = $record_id[project_id]',
        ],
        'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
          if ($this->isCompositeRow($row, $pme)) {
            $value = str_replace(', ', '<br/>', $value);
          }
          return $value;
        },
        'tooltip' => $this->toolTipsService['page-renderer:project-payments:project-balance'],
        'display' => [
          'popup' => function($cellData, $k, $row, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return $cellData;
            } else {
              return $pme->fdd[$k]['tooltip'];
            }
          },
          'prefix' => function($op, $pos, $k, $row, $pme) {

            if ($this->isCompositeRow($row, $pme)) {
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($row['qf' . $k . '_idx'])) {
                return null;
              }
              $value = null;
            } else {
              $value = $row['qf' . $k];
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
                return null;
              }
            }

            $documentPathChain = [ $this->getProjectBalancesPath() ];
            if ($this->project->getType() == ProjectType::TEMPORARY) {
              $documentPathChain[] = $this->project->getYear();
            };
            $documentPathChain[] = $this->project->getName();
            $documentPathChain[] = $this->getSupportingDocumentsFolderName();

            $documentParentPath = implode('/', $documentPathChain);
            $filesAppTarget = md5($documentParentPath);
            if (!empty($value)) {
              if (is_numeric($value)) {
                $value = sprintf('%s-%03d', $this->projectName, $value);
              }
            }

            try {
              $filesAppParentLink = $this->userStorage->getFilesAppLink($documentParentPath, subDir: true);
              $filesAppLink = empty($value)
                ? $filesAppParentLink
                : $filesAppParentLink . '/' . $value;
            } catch (\OCP\Files\NotFoundException $e) {
              $this->logInfo('No file found for ' . $documentParentPath);
              $filesAppParentLink = $filesAppLink = '';
            }

            $filesAppAnchor = '
<a href="' . $filesAppLink . '"
   data-parent-link="' . Util::htmlEscape($filesAppParentLink) . '"
   target=" . $filesAppTarget . "
   title="' . $this->toolTipsService['page-renderer:project-payments:project-balance:open'] . '"
   class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
></a>';

            if ($this->isCompositeRow($row, $pme)) {
              return '<div class="flex-container"><span class="pme-cell-prefix">' . $filesAppAnchor . ' </span><span class="pme-cell-content">'
                . '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">';
            } else {
              return '<div class="flex-container"><span class="pme-cell-prefix">' . $filesAppAnchor . ' </span><span class="pme-cell-content">';
            }
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return '</div></div></span></div>';
            } else {
              return '</span></div>';
            }
          },
        ],
      ],
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BULK_TRANSACTIONS_TABLE, 'created',
      array_merge(
        $this->defaultFDD['date'], [
          'tab' => [ 'id' => [ 'transaction' ] ],
          'name' => $this->l->t('Bulk Transaction'),
          'input' => 'R',
          'options' => 'LFVD',
          'css'  => [ 'postfix' => [ 'bulk-transaction', ], ],
          'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
            $bulkTransactionId = $row['qf'.$pme->fdn['sepa_transaction_id']];
            if (!empty($bulkTransactionId)) {
              $value = sprintf('%04d: %s', $bulkTransactionId, $value);
            }
            return $this->compositeRowOnly($value, $action, $k, $row, $recordId, $pme);
          },
      ]),
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BULK_TRANSACTIONS_TABLE, 'submit_date',
      array_merge(
        $this->defaultFDD['date'], [
          'name' => $this->l->t('Filing Date'),
          'input' => 'R',
          'options' => 'LFVD',
          'css'  => [ 'postfix' => [ 'date-of-submission', ], ],
          'php|LF' => [$this, 'compositeRowOnly'],
        ]),
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BANK_ACCOUNTS_TABLE, 'iban',
      [
        'name' => $this->l->t('IBAN'),
        'input'  => 'R',
        'options' => 'LFVD',
        'css'  => [ 'postfix' => [ 'bank-account-iban', ], ],
        'php|LF' => [$this, 'compositeRowOnly'],
        'encryption' => [
          'encrypt' => function($value) { return $this->ormEncrypt($value); },
          'decrypt' => function($value) { return $this->ormDecrypt($value); },
        ],
        'display' => [
          'popup' => function($data) {
            if (empty($data)) {
              return ''; // can happen
            }
            $info  = $this->financeService->getIbanInfo($data);
            $result = '';
            foreach ($info as $key => $value) {
              $result .= $this->l->t($key).': '.$value.'<br/>';
            }
            return $result;
          },
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference',
      [
        'name' => $this->l->t('Mandate Reference'),
        'input'  => 'R',
        'options' => 'LFVD',
        'css'  => [ 'postfix' => [ 'mandate-reference', ] ],
        'php|LF' => [$this, 'compositeRowOnly'],
      ]);

    $opts['fdd']['notification_message_id'] = [
      'name' => $this->l->t('Message-ID'),
      'input'  => 'R',
      'options' => 'LFVD',
      'css'  => [ 'postfix' => [ 'message-id', 'hide-subsequent-lines', ], ],
      'select' => 'T',
      'escape' => true,
      'sort' => true,
      'tooltip' => $this->toolTipsService['debit-note-email-message-id'],
      'display|LF' => [ 'popup' => 'data' ],
    ];

    $readOnlySafeGuard = function(&$pme, $op, $step, &$row) use ($opts) {

      $bulkTransactionId = $row['qf'.$pme->fdn['sepa_transaction_id']];
      if (false && !empty($bulkTransactionId)) {
        $pme->options = 'LVF';
        if ($op !== 'select') {
          throw new \BadFunctionCallException(
            $this->l->t('Payments resulting from direct debit transfers cannot be changed.')
          );
        }
      } else {
        $pme->options = $opts['options'];
      }

      return true;
    };
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] = $readOnlySafeGuard;

    // we mis-use the fields of the ProjectPayment entities for the
    // CompositePayment entity. We have also to remap other things, like
    // multiplicity of selects and so on.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($musicianReceivableFilter) {


        $rowTagIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'row_tag')];
        $rowTag = $row['qf'.$rowTagIndex];
        $balanceDocumentSequenceIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'balance_document_sequence')];

        $pme->fdd[$balanceDocumentSequenceIndex]['select'] = $this->isCompositeRowTag($rowTag) ? 'M' : 'D';

        if ($this->listOperation()) {
          return true;
        }

        $receivableKeyKey = $this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key');
        $receivableKeyIndex = $pme->fdn[$receivableKeyKey];
        $amountIndex = $pme->fdn['amount'];

        $paymentsAmountIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'amount')];
        $subjectIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject')];
        $musicianIdIndex = $pme->fdn[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'id')];
        $paymentsIdIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'id')];
        $subjectIndex = $pme->fdn['subject'];
        $paymentsSubjectIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject')];
        $imbalanceIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'imbalance')];
        $supportingDocumentIndex = $pme->fdn['supporting_document_id'];
        $compositeBalanceDocumentSequenceIndex = $pme->fdn['balance_document_sequence'];

        if ($this->isCompositeRowTag($rowTag)) {
          $this->logInfo('COMPOSITE ROW');
          $pme->fdd[$receivableKeyIndex]['input'] = 'HR';
          $pme->fdd[$amountIndex]['input'] = 'M';
          $pme->fdd[$paymentsAmountIndex]['input'] = 'HR';
          $pme->fdd[$paymentsIdIndex]['input'] = 'M';
          $pme->fdd[$paymentsIdIndex]['select'] = 'M';
          $pme->fdd[$paymentsIdIndex]['valueData'] = [
            -1 => json_encode([
              'recordId' => $pme->rec,
              'groupbyRec' => $pme->groupby_rec,
            ], true),
          ];
          $pme->fdd[$subjectIndex]['input'] = 'M';
          $pme->fdd[$paymentsSubjectIndex]['input'] = 'HR';
          $pme->fdd[$balanceDocumentSequenceIndex]['input'] = 'R';
          $pme->fdd[$compositeBalanceDocumentSequenceIndex]['input'] = '';

          if ($this->copyOperation()) {
            $pme->fdd[$supportingDocumentIndex]['input'] = 'HR';
            $pme->fdd[$receivableKeyIndex]['select'] = 'D';
            $pme->fdd[$receivableKeyIndex]['input'] = 'M';
            $pme->fdd[$paymentsIdIndex]['input'] = 'RH';

            // Only copy the first receivable
            foreach ([$receivableKeyIndex, $paymentsIdIndex] as $index) {
              $rowIndex = 'qf' . $index;
              list($row[$rowIndex],) = explode(self::VALUES_SEP, $row[$rowIndex]);
            }
            foreach ([$paymentsAmountIndex, $paymentsSubjectIndex] as $index) {
              $rowIndex = 'qf' . $index;
              $row[$rowIndex] = null;
            }
          }
        } else {
          $this->logDebug('COMPONENT ROW');
          $pme->fdd[$receivableKeyIndex]['select'] = 'D';
          $pme->fdd[$receivableKeyIndex]['values']['filters'] = $musicianReceivableFilter;
          $pme->fdd[$paymentsIdIndex]['input'] = 'RH';
          $pme->fdd[$subjectIndex]['input'] = 'HR';
          $pme->fdd[$paymentsSubjectIndex]['input'] = 'M';
          $pme->fdd[$amountIndex]['input'] = 'HR';
          $pme->fdd[$paymentsAmountIndex]['input'] = 'M';
          $pme->fdd[$imbalanceIndex]['input'] = 'HR';
          $pme->fdd[$musicianIdIndex]['input'] = 'R';
          $pme->fdd[$supportingDocumentIndex]['input'] = 'HR';
          $pme->fdd[$compositeBalanceDocumentSequenceIndex]['input'] = 'HR';

          $pme->fdd[$balanceDocumentSequenceIndex]['name'] = $this->l->t('Project Balance');
        }

        // if this payment originated from a scheduled bulk-transaction, then
        // disallow any changes safe the date_of_receipt and adding/changing
        // supporting documents.
        $bulkTransactionId = $row['qf'.$pme->fdn['sepa_transaction_id']];
        if (!empty($bulkTransactionId)) {
          // make all rows read-only with the exception of some
          foreach ($pme->fdn as $fieldName => $fieldIndex) {
            if ($fieldName == 'date_of_receipt') {
              $pme->fdd[$fieldIndex]['input'] = str_replace('M', '', $pme->fdd[$fieldIndex]['input']);
              continue;
            } elseif ($fieldName == 'balance_document_sequence') {
              continue;
            } elseif ($fieldName == $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'balance_document_sequence')) {
              continue;
            } elseif ($fieldName == $this->joinTableFieldName(self::PROJECT_BALANCE_SUPPORTING_DOCUMENTS_TABLE, 'sequence')) {
              continue;
            }
            // $this->logInfo('NAME: ' . $fieldName . ' => ' . $fieldIndex);
            $pme->fdd[$fieldIndex]['input'] .= 'R';
          }
        }

        return true;
      };

    // Real insert (not copy) has no data-triger. We use the pre-trigger to
    // tweak the set of selectable fields.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_PRE][] = function(&$pme, $op) {
      // $this->logInfo('PRE-TRIGGER OPERATION ' . $op);
      $subjectIndex = $pme->fdn['subject'];
      $pme->fdd[$subjectIndex]['input'] = 'HR';
      // $paymentsSubjectIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject')];
      // $pme->fdd[$paymentsSubjectIndex]['input'] = 'HR';
      return true;
    };

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteDoDeleteSubPayments' ];

    if ($projectMode) {
      $opts['filters'] = 'FIND_IN_SET('.$projectId.', '.$joinTables[self::PROJECT_PAYMENTS_TABLE].'.project_ids)';
    }

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    // $this->logInfo('GROUPS '.Functions\dump($opts['groupby_fields']));

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Sub-payment aware delete.
   *
   */
  public function beforeDeleteDoDeleteSubPayments(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $paymentIdKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'id');
    $rowTagKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'row_tag');

    if (!$this->isCompositeRowTag($oldValues[$rowTagKey])) {
      $paymentId = $oldValues[$rowTagKey] ?? $oldValues[$paymentIdKey];
      $this->setDatabaseRepository(Entities\ProjectPayment::class);
      $this->remove($paymentId, true);
    } else {
      $this->setDatabaseRepository(Entities\CompositePayment::class);
      return $this->beforeDeleteSimplyDoDelete($pme, $op, $step, $oldValues, $changed, $newValues);
    }

    $changed = [];
    return true;
  }

  /**
   * Remap the input values in order to satisfy the data-model:
   *
   * - one receivable per ProjectPayment
   * - CompositePayment amount must be sum of all partial payments
   * - CompositePayment subject is constructed from individual payments
   *
   * However, on insert we only add a single "split" transaction. Further
   * parts have to be added afterwards.

   // update for composite

     BEFORE OLDVALS Array (
     [id] => 514
     [musician_id] => 407
     [sepa_transaction_id] =>
     [ProjectPayments:row_tag] => 0;514
     [Musicians:id] => 407
     [amount] => 15.00
     [ProjectPayments:amount] => 55.17,45.00
     [date_of_receipt] => 2022-01-13
     [subject] => asfasfasfa
     [ProjectPayments:subject] => sadfgdsafasd,sdafdasfas
     [ProjectPayments:id] => 976,978
     [ProjectParticipantFieldsDataOptions:composite_key] => 18-224-82fad011-04a6-11ec-9e3f-04e261401ed5,18-224-82fb239c-04a6-11ec-9e3f-04e261401ed5 )

     BEFORE NEWVALS Array (
     [id] => 514
     [musician_id] => 407
     [sepa_transaction_id] =>
     [ProjectPayments:row_tag] => 0;514
     [Musicians:id] => 407
     [amount] => 15.00
     [ProjectPayments:amount] => 55.17,45.00
     [date_of_receipt] => 2022-01-13 00:00:00
     [subject] => asfasfasfa
     [ProjectPayments:subject] => sadfgdsafasd,sdafdasfas
     [ProjectPayments:id] => 976,978
     [ProjectParticipantFieldsDataOptions:composite_key] => 18-224-82fad011-04a6-11ec-9e3f-04e261401ed5,18-224-82fb239c-04a6-11ec-9e3f-04e261401ed5 )
   */
  public function beforeUpdateSanitizeFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    if (empty($changed)) {
      // don't start manipulations if nothing has changed.
      return true;
    }

    $compositeKey = $newValues[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key')]??null;
    $rowTagKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'row_tag');
    $paymentIdKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'id');

    $musicianId = $newValues[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'id')];
    $oldValues['musician_id'] =
      $newValues['musician_id'] = $musicianId;

    if (!$this->isCompositeRowTag($newValues[$rowTagKey])) {
      // current update dialog refers to a split payment

      $this->joinStructure[self::PROJECT_PAYMENTS_TABLE]['flags'] |= self::JOIN_SINGLE_VALUED;

      // determine our payments id
      $paymentId = $newValues[$rowTagKey] ?? $newValues[$paymentIdKey];
      if (empty($paymentId)) {
        $paymentId = 0; // flag key generation
        $newValues[$paymentIdKey] = $newValues[$rowTagKey] = $paymentId;
      } else {
        $newValues[$paymentIdKey] =
          $newValues[$rowTagKey] =
          $oldValues[$paymentIdKey] =
          $oldValues[$rowTagKey] = $paymentId;
      }

      $this->logInfo('COMPOSITE ' . $compositeKey);

      // extract project-id, field-id, receivable_key from the composite-option-key select
      list($projectId, $fieldId, $receivableKey) = explode(
        self::COMP_KEY_SEP,
        $compositeKey,
        3
      );

      $dataSets = $paymentId === 0 ? [ 'new' ] : [ 'old', 'new' ];
      foreach ($dataSets as $dataSet) {
        ${$dataSet . 'Values'} = array_merge(
          ${$dataSet . 'Values'}, [
            $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'musician_id') => $musicianId,
            $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'project_id') => $projectId,
            $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'field_id') => $fieldId,
            $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'receivable_key') => $receivableKey,
            $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'composite_payment_id') => $pme->rec['id'],
          ]);

        // index all values by the key in order to please the
        // PMETableViewBase::beforeUpdateDoUpdateAll() machine
        foreach (${$dataSet . 'Values'} as $key => &$value) {
          if ($key == $paymentIdKey || $key == $rowTagKey) {
            continue;
          }
          if (empty($value)) {
            continue;
          }
          if (str_starts_with($key, self::PROJECT_PAYMENTS_TABLE . self::JOIN_KEY_SEP)) {
            $value = $paymentId . self::JOIN_KEY_SEP . $value;
          }
        }
      }
      unset($value); // break reference

      $unsetTags = [];
      // handled on the composite-level
      $unsetTags[] = 'supporting_document_id';

      // handled on the composite-level
      $unsetTags[] = 'balance_document_sequence';

      foreach ($unsetTags as $tag) {
        unset($newValues[$tag]);
        unset($oldValues[$tag]);
        Util::unsetValue($changed, $tag);
      }
    } else {
      // Composite payment

      // "row_tag" is used as "column" in $this->joinStructure, so transfer
      // the ProjectPayments ids to that field.
      foreach (['newValues', 'oldValues'] as $dataSet) {
        ${$dataSet}[$rowTagKey] = ${$dataSet}[$paymentIdKey];
      }

      $unsetTags = [];
      // remove supporting_document_id as it is handled separately by direct
      // db manipulation.
      $unsetTags[] = 'supporting_document_id';

      // handled on the split-level
      $unsetTags[] = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'balance_document_sequence');

      foreach ($unsetTags as $tag) {
        unset($newValues[$tag]);
        unset($oldValues[$tag]);
        Util::unsetValue($changed, $tag);
      }
    }

    $nullables = [
      'sepa_transaction_id',
      'balance_document_sequence',
      $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'balance_document_sequence'),
    ];
    foreach ($nullables as $key) {
      foreach (['old', 'new'] as $dataSet) {
        if (array_key_exists($key, ${$dataSet . 'Values'}) && empty(${$dataSet . 'Values'}[$key])) {
          ${$dataSet . 'Values'}[$key] = null;
        }
      }
    }

    $changed = [];
    foreach (array_unique(array_merge(array_keys($oldValues), array_keys($newValues))) as $key) {
      if (array_key_exists($key, $oldValues) !== array_key_exists($key, $newValues)
          || ($oldValues[$key]??null) !== ($newValues[$key]??null)) {
        $changed[] = $key;
      }
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    return true;
  }

  /**
   * Remap the input values in order to satisfy the data-model:
   *
   * - one receivable per ProjectPayment
   * - CompositePaymnt amount must be sum of all partial payments
   * - CompositePayment subject is constructed from individual payments
   *
   * However, on insert we only add a single "split" transaction. Further
   * parts have to be added afterwards.
   *
   * Copying sub-transactions is supported.
   */
  public function beforeInsertSanitizeFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $paymentIdKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'id');
    $rowTagKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'row_tag');

    $amountKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'amount');
    $subjectKey = $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject');

    if (!empty($newValues[$rowTagKey]) && !$this->isCompositeRowTag($newValues[$rowTagKey])) {
      // Sub-payment, redirect to change mode

      $compositeKeyKey = $this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key') ;

      // redirect to change operation ...
      $oldValues = $newValues;

      // flag key generation
      $oldValues[$paymentIdKey] = $oldValues[$rowTagKey] = '';
      $newValues[$paymentIdKey] = $newValues[$rowTagKey] = 0;

      $changed = [];
      $changed[] = $amountKey;
      $changed[] = $subjectKey;
      $changed[] = $compositeKeyKey;
      foreach ($changed as $key) {
        $oldValues[$key] = '';
      }

      if ($this->beforeUpdateSanitizeFields($pme, $op, $step, $oldValues, $changed, $newValues)) {
        return $this->beforeUpdateDoUpdateAll($pme, $op, $step, $oldValues, $changed, $newValues);
      }
      return false;
    }

    // clean left-over from expert-mode while copying
    unset($newValues['id']);
    Util::unsetValue($changed, 'id');

    // Clone
    // ProjectPayments:subject -> subject
    // ProjectPayments:amount -> amount
    // Musicians:id -> musician_id
    // ProjectParticipantFieldsDataOptions:key -> ProjectPayments:receivable_key

    // extract project-id, field-id, receivable_key from the composite-option-key select
    list($projectId, $fieldId, $receivableKey) = explode(
      self::COMP_KEY_SEP,
      $newValues[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key')],
      3
    );
    $musicianId = $newValues[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'id')];

    $newValues['musician_id'] = $musicianId;
    if (($newValues[$amountKey]??null) === null) {
      $newValues[$amountKey] = $newValues['amount'];
    } else {
      $newValues['amount'] = $newValues[$amountKey];
    }
    if (($newValues[$subjectKey]??null) === null) {
      $newValues[$subjectKey] = $newValues['subject'];
    } else {
      $newValues['subject'] = $newValues[$subjectKey];
    }
    unset($newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'composite_payment_id')]);

    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'musician_id')] = $musicianId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'project_id')] = $projectId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'field_id')] = $fieldId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'receivable_key')] = $receivableKey;

    $newValues['project_id'] = $projectId;

    // "row_tag" is used as "column" in $this->joinStructure, so transfer
    // the ProjectPayments ids to that field.
    $paymentId =
      $newValues[$paymentIdKey] =
      $newValues[$rowTagKey] = 0;

    // index all values by the key in order to please the
    // PMETableViewBase::beforeUpdateDoUpdateAll() machine
    foreach ($newValues as $key => &$value) {
      if ($key == $paymentIdKey || $key == $rowTagKey) {
        continue;
      }
      if (empty($value)) {
          continue;
      }
      if (strpos($key, self::PROJECT_PAYMENTS_TABLE . self::JOIN_KEY_SEP) === 0) {
        $value = $paymentId . self::JOIN_KEY_SEP . $value;
      }
    }
    unset($value); // break reference

    $changed = array_keys($newValues);

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');
    return true;
  }

  /**
   * @param string $rowTag The tag to examine.
   *
   * @return boolean
   */
  public function isCompositeRowTag(?string $rowTag):bool
  {
    return str_starts_with($rowTag ?? '', self::ROW_TAG_PREFIX);
  }

  /**
   * Print only the values for the composite row.
   *
   * @todo: if the search results (e.g. for the amount) do not contain
   * the composite row, then the missing data should also be printed.
   */
  public function compositeRowOnly($value, $action, $k, $row, $recordId, $pme)
  {
    return $this->selectiveRowDisplay('composite', $value, $action, $k, $row, $recordId, $pme);
  }

  /**
   * Print only the values for the component row.
   *
   * @todo: if the search results (e.g. for the amount) do not contain
   * the composite row, then the missing data should also be printed.
   */
  public function componentRowOnly($value, $action, $k, $row, $recordId, $pme)
  {
    return $this->selectiveRowDisplay('component', $value, $action, $k, $row, $recordId, $pme);
  }

  /** Decide whether the current row refers to the composite payment or to a "split" project-payment */
  private function isCompositeRow($row, $pme)
  {
    $rowTag = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::PROJECT_PAYMENTS_TABLE)]];
    return $this->isCompositeRowTag($rowTag);
  }

  private function selectiveRowDisplay($where, $value, $action, $k, $row, $recordId, $pme)
  {
    $compositeRow = $this->isCompositeRow($row, $pme);
    $composite = $where === 'composite';
    $component = $where === 'component';
    if (($compositeRow && $composite) || (!$compositeRow && $component)) {
      return $value;
    } else {
    }
    return '';
  }

  /**
   * Callback-hook for download-link display (view, delete, list)
   */
  private function createSupportingDocumentsDownload($value, $action, $k, $row, $recordId, $pme)
  {
    $musicianId = $row['qf'.$pme->fdn['musician_id']];
    if ($this->isCompositeRow($row, $pme)) {
      $receivables = Util::explode(self::VALUES_SEP, $row['qf'.$k.'_idx']);
      // $receivables must contain at least one element.
      $supportingDocument = $row['qf'.$pme->fdn['supporting_document_id']];
      $supportingDocuments = [];
      if (!empty($supportingDocument) || count($receivables) > 1) {
        $userIdSlug = $row['qf'.$pme->fdn[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'user_id_slug')]];
        if (!empty($supportingDocument)) {
          $supportingDocuments = [ $supportingDocument ];
        }
        foreach ($receivables as $receivable) {
          list($projectId, $fieldId, $optionKey) = explode(self::COMP_KEY_SEP, $receivable, 3);
          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          $fieldDatum = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)->find([
            'field' => $fieldId,
            'project' => $projectId,
            'musician' => $musicianId,
            'optionKey' => $optionKey,
          ]);
          if (empty($fieldDatum)) {
            $this->logError('Cannot find field-datum for musician ' . $musicianId . ' and option-key ' . $optionKey);
            continue;
          }
          if (!empty($document = $fieldDatum->getSupportingDocument())) {
            $supportingDocuments[] = $document;
          }
          $project = $project??$fieldDatum->getProject();
        }
        $dateOfReceipt = $row['qf'.$pme->fdn['date_of_receipt']];
        $subject = Util::dashesToCamelCase($row['qf'.$pme->fdn['subject']], capitalizeFirstCharacter: true, dashes: ' _-');

        $fileName = $this->getLegacyPaymentRecordFileName($recordId['id'], $userIdSlug);

        if (!empty($supportingDocuments)) {
          $value = $fileName . '<br/>' . $value;
        }

        // there should be at least one project ...
        $subFolder = empty($supportingDocuments)
          ? null
          : $this->getDocumentsFolderName() . UserStorage::PATH_SEP . $this->getSupportingDocumentsFolderName();
        $filesAppAnchor = $this->getFilesAppAnchor(null, $fieldDatum->getMusician(), project: $project, subFolder: $subFolder);
        $downloadLink = $this->databaseStorageUtil->getDownloadLink($supportingDocuments, $fileName);
        return '<div class="flex-container"><span class="pme-cell-prefix">' . $filesAppAnchor . '</span><span class="pme-cell-content">' . '<a class="download-link ajax-download tooltip-auto" title="'.$this->toolTipsService['project-payments:receivable:document'].'" href="'.$downloadLink.'">' . '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">' . $value . '</div></div>' . '</a></span></div>';
      }
    }

    // fall-through, single or no supporting document
    $receivable = $row['qf'.$k.'_idx'];
    list($projectId, $fieldId, $optionKey) = explode(self::COMP_KEY_SEP, $receivable, 3);

    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    $fieldDatum = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)->find([
      'field' => $fieldId,
      'project' => $projectId,
      'musician' => $musicianId,
      'optionKey' => $optionKey,
    ]);
    if (empty($fieldDatum)) {
      $this->logError('Cannot find field-datum for musician ' . $musicianId . ' and option-key ' . $optionKey);
      return $value;
    }
    $filesAppAnchor = $this->getFilesAppAnchor($fieldDatum->getField(), $fieldDatum->getMusician());
    $fileInfo = $this->projectService->participantFileInfo($fieldDatum);
    $valueHtml = '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer one-liner">' . $value . '</div></div>';

    if (!empty($fileInfo)) {
      $downloadLink = $this->databaseStorageUtil->getDownloadLink($fileInfo['file'], $fileInfo['baseName']);
      $downloadAnchor = '<a class="download-link ajax-download tooltip-auto" title="'.$this->toolTipsService['project-payments:receivable:document'].'" href="'.$downloadLink.'">' . $valueHtml . '</a>';
    } else {
      $downloadAnchor = $valueHtml;
    }

    return '<div class="flex-container"><span class="pme-cell-prefix">'
      . $filesAppAnchor
      . '</span><span class="pme-cell-content">'
      . $downloadAnchor
      . '</span>';
  }
}
