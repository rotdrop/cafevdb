<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;

/** TBD. */
class SepaBulkTransactions extends PMETableViewBase
{
  use FieldTraits\CryptoTrait;
  use FieldTraits\MusicianInProjectTrait;
  use FieldTraits\MusicianPublicNameTrait;

  const TEMPLATE = 'sepa-bulk-transactions';
  const TABLE = self::SEPA_BULK_TRANSACTIONS_TABLE;
  const DATA_TABLE = self::SEPA_BULK_TRANSACTION_DATA_TABLE;

  const ROW_TAG_PREFIX = '0;';

  protected $cssClass = self::TEMPLATE;

  /** @var FinanceService */
  private $financeService;

  /** @var SepaBulkTransactionService */
  private $bulkTransactionService;

  /** @var array */
  private $bulkTransactionExpanded = [];

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\SepaBulkTransaction::class,
    ],
    self::DATA_TABLE => [
      'identifier' => [
        'sepa_bulk_transaction_id' => 'id',
        'database_storage_file_id' => false,
      ],
      'column' => 'database_storage_file_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::DATABASE_STORAGE_DIR_ENTRIES_TABLE => [
      'entity' => Entities\DatabaseStorageFile::class,
      'identifier' => [
        'id' => [
          'table' => self::DATA_TABLE,
          'column' => 'database_storage_file_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::COMPOSITE_PAYMENTS_TABLE => [
      'sql' => "SELECT
  CONCAT('".self::ROW_TAG_PREFIX."', __t1.sepa_transaction_id) AS row_tag,
  __t1.sepa_transaction_id AS sepa_transaction_id,
  GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.id) AS id,
  GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.project_id) AS project_id,
  GROUP_CONCAT(DISTINCT __t1.musician_id ORDER BY __t1.id) AS musician_id,
  GROUP_CONCAT(DISTINCT __t1.subject ORDER BY __t1.id) AS subject,
  GROUP_CONCAT(DISTINCT __t1.notification_message_id ORDER BY __t1.id) AS notification_message_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.musician_id, __t1.bank_account_sequence) ORDER BY __t1.id) AS bank_account_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.musician_id, __t1.debit_mandate_sequence) ORDER BY __t1.id) AS debit_mandate_id,
  SUM(__t1.amount) AS amount
FROM ".self::COMPOSITE_PAYMENTS_TABLE." __t1
GROUP BY __t1.sepa_transaction_id
UNION
SELECT
  __t2.id AS row_tag,
  __t2.sepa_transaction_id AS sepa_transaction_id,
  __t2.id AS id,
  __t2.project_id AS project_id,
  __t2.musician_id AS musician_id,
  __t2.subject AS subject,
  __t2.notification_message_id AS notification_message_id,
  CONCAT_WS('".self::JOIN_KEY_SEP."', __t2.musician_id, __t2.bank_account_sequence) AS bank_account_id,
  CONCAT_WS('".self::JOIN_KEY_SEP."', __t2.musician_id, __t2.debit_mandate_sequence) AS debit_mandate_id,
  __t2.amount AS amount
FROM ".self::COMPOSITE_PAYMENTS_TABLE." __t2",
      'entity' => Entities\CompositePayment::class,
      'flags' => self::JOIN_READONLY|self::JOIN_GROUP_BY,
      'column' => 'row_tag',
      'identifier' => [
        'id' => false,
      ],
      'filter' => [
        'sepa_transaction_id' => 'id',
      ],
    ],
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => false,
      ],
      'filter' => [
        'composite_payment_id' => [
          'table' => self::COMPOSITE_PAYMENTS_TABLE,
          'column' => 'id',
        ],
      ],
      'column' => 'id',
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
    self::SENT_EMAILS_TABLE => [
      'entity' => Entities\SentEmail::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'message_id' => [
          'table' => self::COMPOSITE_PAYMENTS_TABLE,
          'column' => 'notification_message_id',
        ],
      ],
      'column' => 'message_id',
    ],
  ];

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project */
  private $project = null;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    FinanceService $financeService,
    SepaBulkTransactionService $bulkTransactionService,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->financeService = $financeService;
    $this->bulkTransactionService = $bulkTransactionService;
    $this->bulkTransactionExpanded = $this->requestParameters['bulkTransactionExpanded'];

    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      $this->projectName = $this->project->getName();
    }

    $this->initCrypto();
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return $this->l->t('Bulk-transactions for project "%s"', array($this->projectName));
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;

    $projectMode = $this->projectId > 0;

    $opts            = [];

    $opts['css']['postfix'] = [
      self::TEMPLATE,
      'direct-change',
      'show-hide-disabled',
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
      'bulkTransactionExpanded' => $this->bulkTransactionExpanded,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'id' => 'int', ];

    // Sorting field(s)
    $opts['sort_field'] = [
      '-submission_dead_line',
      '-created',
      'id',
      $this->joinTableFieldName(self::COMPOSITE_PAYMENTS_TABLE, 'row_tag'),
    ];
    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CDFLV';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'UG';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [
          'id' => 'transaction',
          'tooltip' => $this->l->t('Bulk Transaction'),
          'name' => $this->l->t('Transaction'),
        ], [
          'id' => 'bookings',
          'tooltip' => $this->l->t('Booking Entries'),
          'name' => $this->l->t('Bookings'),
        ], [
          'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['page-renderer:tab:showall'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];
    if ($this->addOperation()) {
      $opts['display']['tabs'] = false;
    }

    // in order to be able to collapse composite-payment details:
    $opts['css']['row'] = function($name, $position, $divider, $row, $pme) {
      static $evenOdd = [ 'even', 'odd' ];
      static $lastBulkTransactionId = -1;
      static $oddCompositePayment = true;
      static $oddBulkTransaction = false;

      $bulkTransactionId = $row[PHPMyEdit::QUERY_FIELD . $pme->fdn['id']];
      // $compositePaymentId = $row[PHPMyEdit::QUERY_FIELD . $pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];

      $cssClasses = ['bulk-transaction'];
      if ($lastBulkTransactionId != $bulkTransactionId) {
        $lastBulkTransactionId = $bulkTransactionId;
        $oddCompositePayment = true;
        $cssClasses[] = 'first';
        if (empty($this->bulkTransactionExpanded[$bulkTransactionId])) {
          $cssClasses[] = 'following-hidden';
        }
        $cssClasses[] = $evenOdd[(int)$oddBulkTransaction];
        $oddBulkTransaction = !$oddBulkTransaction;
      } else {
        $cssClasses[] = 'following';
        $cssClasses[] = 'composite-payment';
        $cssClasses[] = $evenOdd[(int)$oddCompositePayment];
        $oddCompositePayment = !$oddCompositePayment;
      }

      return $cssClasses;
    };

    // wrap the composite groups into tbody elements, otherwise the
    // groups cannot be hidden individually.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function($pme, $op, $step, $row) {
      // $this->logInfo('DATA TRIGGER '.$op.' '.$step.' '.print_r($row, true));
      static $lastBulkTransactionId = -1;

      $bulkTransactionId = $row[PHPMyEdit::QUERY_FIELD . $pme->fdn['id']];
      // $compositePaymentId = $row[PHPMyEdit::QUERY_FIELD . $pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];

      if ($lastBulkTransactionId != $bulkTransactionId) {
        if ($lastBulkTransactionId > 0) {
          echo '</tbody>
<tbody>';
        }
        $lastBulkTransactionId = $bulkTransactionId;
      }
      return true;
    };

    ///////////////////////////////////////////////////////////////////////////
    //
    // Add the id-columns of the main-table
    //

    $opts['fdd']['id'] = [
      'tab' => [ 'id' => 'tab-all', ],
      'name'     => $this->l->t('Id'),
      'select'   => 'T',
      'input'    => ($this->expertMode ? 'R' : 'RH'),
      'input|AP' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => '0', // auto increment
      'sort'     => true,
      'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
        $html = '';
        if ($this->isBulkTransactionRow($row, $pme)) {
          $html = '<input type="hidden" class="expanded-marker" name="bulkTransactionExpanded['.$recordId['id'].']" value="'.(int)($this->bulkTransactionExpanded[$recordId['id']]??0).'"/>';
          if ($this->expertMode) {
            $html .= '<span class="cell-wrapper">' . $value . '</span>';
          }
        }
        return $html;
      },
    ];

    $joinTables = $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'row_tag', [ 'input' => 'SRH', ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      Util::arrayMergeRecursive(
        [
          'name'     => $this->l->t('Project'),
          'input'    => $projectMode ? 'H' : null,
          'select'   => 'D',
          'sort'     => true,
          'values' => [
            'description' => $projectMode
            ? 'name'
            : [
              'columns' => [ 'year' => 'year', 'name' => 'name' ],
              'divs' => [ 'year' => ': ' ]
            ],
            'groups' => 'year',
            'orderby' => '$table.year DESC, $table.name ASC',
          ],
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'amount',
      array_merge(
        $this->defaultFDD['money'], [
          'tab' => [ 'id' => 'tab-all', ],
          'name' => $this->l->t('Amount'),
          'input' => 'R',
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'musician_id',
      [
        'tab' => [ 'id' =>[ 'bookings', 'transaction' ], ],
        'name'     => $this->l->t('Musician'),
        'css'      => [ 'postfix' => [ 'musician-id', 'squeeze-subsequent-lines', ], ],
        'select' => 'M',
        'input' => 'R',
        'sql' => $this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.musician_id',
        'values' => [
          'table' => self::MUSICIANS_TABLE,
          'column' => 'id',
          'join' => '$join_col_fqn = '.$this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.musician_id',
          'description' => [
            'columns' => [ '$table.id', static::musicianPublicNameSql() ],
            'divs' => [ ': ' ],
            'ifnull' => [ false, false ],
            'cast' => [ 'CHAR', false ],
          ],
          'filters' => (!$projectMode
                        ? null
                        : static::musicianInProjectSql($this->projectId)),
        ],
        'values2glue' => '<br/>',
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'bank_account_id', [
        'tab' => [ 'id' =>[ 'bookings', ], ],
        'name'     => $this->l->t('IBAN'),
        'css'      => [
          'postfix' => [
            'bank-account-iban',
            'squeeze-subsequent-lines',
            'lazy-decryption',
            'meta-data-popup',
          ],
        ],
        'select' => 'M',
        'sql' => $this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.bank_account_id',
        'values' => [
          'table' => "SELECT
  CONCAT_WS('".self::JOIN_KEY_SEP."', __t.musician_id, __t.sequence) AS bank_account_id,
  __t.iban AS sealed_value,
  MD5(__t.iban) AS crypto_hash
  FROM ".self::SEPA_BANK_ACCOUNTS_TABLE." __t",
          'column' => 'bank_account_id',
          'join' => '$join_col_fqn = '.$this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.bank_account_id',
          'description' => [
            'columns' => 'CONCAT("<span class=\"iban encryption-placeholder\"
      data-crypto-hash=\"", $table.crypto_hash, "\"
      title=\"' . $this->l->t('Fetching decrypted values in the background.') . '\"
>'
            . $this->l->t('please wait')
            . '</span>")',
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'data' => [
            'crypto-hash' => '$table.crypto_hash',
            'sealed-value' => '$table.sealed_value',
            'meta-data' => "'iban'",
          ],
        ],
        'php' => function($value, $op, $field, $row, $recordId, $pme) {
          $values = Util::explode(',', $value, Util::TRIM);
          return implode('<br/>', $values);
          foreach ($values as &$value) {
            // $value = $this->ormDecrypt($value);
            $value = '<span class="iban encryption-placeholder"
      data-crypto-hash="' . $value . '"
      title="' . $this->l->t('Fetching decrypted values in the background.') . '"
>'
            . $this->l->t('please wait')
            . '</span>';
          }
          return implode('<br/>', $values);
        },
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
          'attributes' => [
            'data-meta-data' => 'iban',
          ],
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'debit_mandate_id', [
        'tab' => [ 'id' =>[ 'bookings', ], ],
        'name'     => $this->l->t('Mandate Reference'),
        'css'      => [ 'postfix' => ' mandate-reference squeeze-subsequent-lines' ],
        'select' => 'M',
        'input' => 'R',
        'sql' => $this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.debit_mandate_id',
        'values' => [
          'table' => "SELECT
  CONCAT_WS('".self::JOIN_KEY_SEP."', __t.musician_id, __t.sequence) AS debit_mandate_id,
  __t.mandate_reference AS mandate_reference
  FROM ".self::SEPA_DEBIT_MANDATES_TABLE." __t",
          'column' => 'debit_mandate_id',
          'join' => '$join_col_fqn = '.$this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.debit_mandate_id',
          'description' => self::trivialDescription('mandate_reference'),
        ],
        'values2glue' => '<br/>',
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
          'attributes' => [ 'readonly', ],
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'subject', [
        'tab' => [ 'id' => [ 'bookings', ], ],
        'name' => $this->l->t('Subject'),
        'input' => 'RD',
        'css' => [ 'postfix' => [ 'subject squeeze-subsequent-lines', 'clip-long-text', ], ],
        'sql|LFVD' => 'REPLACE($join_col_fqn, \'; \', \'<br/>\')',
        'sql|ACP' => '$join_col_fqn',
        'display' => [
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

    $opts['fdd']['created'] = array_merge(
      $this->defaultFDD['datetime'],
      [
        'tab' => [ 'id' => 'transaction' ],
        'name' => $this->l->t('Time Created'),
        'input' => 'R',
        'tooltip' => $this->toolTipsService['bulk-transaction-creation-time'],
        'php|LF' => [$this, 'bulkTransactionRowOnly'],
      ]);

    $opts['fdd']['submission_deadline'] = array_merge(
      $this->defaultFDD['date'],
      [
        'tab' => [ 'id' => 'transaction' ],
        'name' => $this->l->t('Submission Deadline'),
        'input' => 'R',
        'tooltip' => $this->toolTipsService['bulk-transaction-submission-deadline'],
        'php|LF' => [$this, 'bulkTransactionRowOnly'],
      ]);

    $submitIdx = count($opts['fdd']);
    $opts['fdd']['submit_date'] = array_merge(
      $this->defaultFDD['date'],
      [
        'tab' => [ 'id' => 'transaction' ],
        'name' => $this->l->t('Date of Submission'),
        'tooltip' => $this->toolTipsService['bulk-transaction-date-of-submission'],
        'php|LF' => [$this, 'bulkTransactionRowOnly'],
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            return empty($row[PHPMyEdit::QUERY_FIELD . $k]) ? [] : [ 'readonly' => true ];
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = empty($row[PHPMyEdit::QUERY_FIELD . $k]) ? '' : 'checked';
            return '<input id="pme-submit-date-lock"
  type="checkbox"
  ' . $checked . '
  class="pme-input pme-input-lock lock-unlock"
/><label
    class="pme-input pme-input-lock lock-unlock"
    title="'.$this->toolTipsService['pme:input:lock:unlock'].'"
    for="pme-submit-date-lock"></label>';
          },
        ],
      ]);

    $opts['fdd']['due_date'] = array_merge(
      $this->defaultFDD['due_date'],
      [
        'tab' => [ 'id' => 'transaction' ],
        'input' => 'R',
        'tooltip' => $this->toolTipsService['bulk-transaction-due-date'],
        'php|LF' => [$this, 'bulkTransactionRowOnly'],
      ]);

    list(, $msgIdField) = $this->makeJoinTableField(
      $opts['fdd'], self::SENT_EMAILS_TABLE, 'message_id', [
        'name' => $this->l->t('Pre-Notification'),
        'tab' => [ 'id' => 'transaction' ],
        'css' => [ 'postfix' => [ 'squeeze-subsequent-lines', 'medium-width', ], ],
        'input' => 'RH',
        // 'options'  => 'LFVCD',
        'select|LF' => 'D',
        'escape' => false,
        'values' => [
          'description' => [
            'columns' => [
              'REPLACE(REPLACE($table.bulk_recipients, "<", "&lt;"), ">", "&gt;")',
              'subject',
              'created',
              'created_by',
              'REPLACE(REPLACE($table.message_id, "<", "&lt;"), ">", "&gt;")',
            ],
            'ifnull' => [ false ],
            'cast' => [ false ],
            'divs' => [
              -1 => $this->l->t('Recipients') . ': ',
              0 => '<br/>' . $this->l->t('Subject') . ': ',
              1 => '<br/>' . $this->l->t('Date') . ': ',
              2 => '<br/>' . $this->l->t('From') . ': ',
              3 => '<br/>' . 'Message-ID: ',
            ],
          ],
        ],
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
        ],
      ]);
    if ($this->projectId > 0) {
      $opts['fdd'][$msgIdField]['value']['filters'] = '$table.project_id = ' . $this->projectId;
    }

    list(, $msgIdField) = $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'notification_message_id', [
        'name' => $this->l->t('Pre-Notification'),
        'tab' => [ 'id' => 'transaction' ],
        'css' => [ 'postfix' => [ 'squeeze-subsequent-lines', 'medium-width', ], ],
        'input' => 'R',
        'options'  => 'LFVCD',
        'select' => 'M',
        'escape' => true,
        'values2glue' => '<br/>',
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data:previous',
        ],
        'php|C' => function($value, $action, $k, $row, $recordId, $pme) {
          if ($this->isBulkTransactionRow($row, $pme)) {
            return str_replace(',', '<br/>', Util::htmlEscape($value));
          }
          $html = $pme->cellDisplay($this->joinQueryFieldIndex(static::SENT_EMAILS_TABLE, 'message_id'), $row);
          return empty($html) ? Util::htmlEscape($value) : $html;
        },
      ]);
    if ($this->projectId > 0) {
      $opts['fdd'][$msgIdField]['value']['filters'] = '$table.project_id = ' . $this->projectId;
    }

    $opts['fdd']['actions'] = [
      'tab' => [ 'id' => 'transaction' ],
      //'php|LF' => [$this, 'bulkTransactionRowOnly'],
      'name'  => $this->l->t('Actions'),
      'css'   => [ 'postfix' => ' bulk-transaction-actions' ],
      'input' => 'VR',
      'sql' => '$main_table.id',
      'sort'  => false,
      'php' => function($value, $op, $field, $row, $recordId, $pme) {
        if (!$this->isBulkTransactionRow($row, $pme)) {
          return '';
        }
        $post = json_encode([
          'bulkTransactionId' => $recordId['id'],
          'requesttoken' => \OCP\Util::callRegister(),
          'projectId' => $row[PHPMyEdit::QUERY_FIELD . $pme->fdn[$this->joinTableFieldName(self::PROJECTS_TABLE, 'id')].'_idx'],
          'projectName' => $row[PHPMyEdit::QUERY_FIELD . $pme->fdn[$this->joinTableFieldName(self::PROJECTS_TABLE, 'id')]],
        ]);
        $actions = [
          'download' => [
            'label' =>  $this->l->t('download'),
            'post' => $post,
            'title' => $this->toolTipsService['bulk-transaction-download'],
          ],
          'announce' => [
            'label' => $this->l->t('announce'),
            'post'  => $post,
            'title' => $this->toolTipsService['bulk-transaction-announce'],
          ],
        ];
        $html = '';
        foreach ($actions as $key => $action) {
          $html .=<<<__EOT__
<li class="nav tooltip-left inline-block tooltip-auto">
  <a class="nav {$key} tooltip-auto"
     href="#"
     data-post='{$action['post']}'
     title="{$action['title']}">
{$action['label']}
  </a>
</li>
__EOT__;
        }
        return $html;
      },
    ];

    //
    ///////////////////////////////////////////////////////////////////////////

    if ($projectMode) {
      $opts['filters'] = $joinTables[self::PROJECT_PAYMENTS_TABLE].'.project_id = '.$projectId;
    }

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($submitIdx, $opts) {
      if ($this->expertMode || empty($row[PHPMyEdit::QUERY_FIELD . $submitIdx])) {
        $pme->options = $opts['options'];
      } else {
        $pme->options = 'LFV';
      }
      return true;
    };

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Use ORM to actually delete stuff.
   *
   * PhpMyEdit calls the trigger (callback) with the following arguments:
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
   * @param array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated.
   */
  public function beforeDeleteTrigger(
    PHPMyEdit $pme,
    string $op,
    string $step,
    array &$oldValues,
    array &$changed,
    array &$newValues,
  ):bool {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    /** @var Entities\SepaBulkTransaction $bulkTransaction */
    $bulkTransaction = $this->legacyRecordToEntity($pme->rec);

    if (!empty($bulkTransaction->submitDate)) {
      throw new Exceptions\DatabaseReadonlyException(
        $this->l->t(
          'The bulk-transaction with id "%1$d" has already been submitted to the bank, it cannot be deleted.', $bulkTransaction->getId())
      );
    }

    $rowTagKey = $this->joinTableFieldName(self::COMPOSITE_PAYMENTS_TABLE, 'row_tag');

    $paymentId = null;

    if (!str_starts_with($oldValues[$rowTagKey], self::ROW_TAG_PREFIX)) {
      $paymentId = $oldValues[$rowTagKey];

      if ($bulkTransaction->getPayments()->count() == 1) {
        // delete the entire transaction if the last single part is removed.
        /** @var Entities\CompositePayment $payment */
        $payment = $bulkTransaction->getPayments()->first();
        if ($payment->getId() != $paymentId) {
          throw new Exceptions\DatabaseInconsistentValueException(
            $this->l->t('Data inconsistency in bulk-transaction payments: %d / %d', [
              $payment->getId(), $paymentId
            ])
          );
        }
        $paymentId = null;
      }

    }

    if ($paymentId === null) {
      $this->bulkTransactionService->removeBulkTransaction($bulkTransaction);
    } else {
      $this->setDatabaseRepository(Entities\CompositePayment::class);
      $payment = $this->find($paymentId);
      $bulkTransaction->getPayments()->removeElement($payment);
      $this->flush();
    }

    $changed = [];
    return true;
  }

  /**
   * @param array $row Row data from PME.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @return bool Whether this table row refers to a bulk-tranaction (\true)
   * or its split transactions (\false).
   */
  private function isBulkTransactionRow(array $row, PHPMyEdit $pme)
  {
    $rowTag = $row[PHPMyEdit::QUERY_FIELD . $pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];
    return str_starts_with($rowTag, self::ROW_TAG_PREFIX);
  }

  /**
   * Print only the values for the bulk-transaction row
   *
   * @param mixed $value Value passed on from PME.
   *
   * @param string $action Curent PME-action.
   *
   * @param int $k Current PME fdd index.
   *
   * @param array $row Row data from PME.
   *
   * @param array $recordId Record-id of current row.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @return string HTML fragment.
   *
   * @todo: if the search results (e.g. for the amount) do not contain
   * the composite row, then the missing data should also be printed.
   */
  public function bulkTransactionRowOnly(
    mixed $value,
    string $action,
    int $k,
    array $row,
    array $recordId,
    PHPMyEdit $pme,
  ):string {
    if ($this->isBulkTransactionRow($row, $pme)) {
      return $value;
    } else {
      return '';
    }
  }
}
