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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/** TBD. */
class SepaBulkTransactions extends PMETableViewBase
{
  const TEMPLATE = 'sepa-bulk-transactions';
  const TABLE = self::SEPA_BULK_TRANSACTIONS_TABLE;
  const DATA_TABLE = self::SEPA_BULK_TRANSACTION_DATA_TABLE;

  protected $cssClass = self::TEMPLATE;

  /** @var FinanceService */
  private $financeService;

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\SepaBulkTransaction::class,
    ],
    self::DATA_TABLE => [
      'identifier' => [
        'sepa_bulk_transaction_id' => 'id',
        'encrypted_file_id' => false,
      ],
      'column' => 'encrypted_file_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::FILES_TABLE => [
      'entity' => Entities\EncryptedFile::class,
      'identifier' => [
        'id' => [
          'table' => self::DATA_TABLE,
          'column' => 'encrypted_file_id',
        ],
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::COMPOSITE_PAYMENTS_TABLE => [
      'sql' => "SELECT
  CONCAT_WS(';', __t1.sepa_transaction_id, GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.id)) AS row_tag,
  0 AS sort_key,
  __t1.sepa_transaction_id AS sepa_transaction_id,
  GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.id) AS id,
  GROUP_CONCAT(DISTINCT __t1.musician_id ORDER BY __t1.id) AS musician_id,
  GROUP_CONCAT(DISTINCT __t1.subject ORDER BY __t1.id) AS subject,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.musician_id, __t1.bank_account_sequence) ORDER BY __t1.id) AS bank_account_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.musician_id, __t1.debit_mandate_sequence) ORDER BY __t1.id) AS debit_mandate_id,
  SUM(__t1.amount) AS amount
FROM ".self::COMPOSITE_PAYMENTS_TABLE." __t1
GROUP BY __t1.sepa_transaction_id
UNION
SELECT
  __t2.id AS row_tag,
  __t2.id AS sort_key,
  __t2.sepa_transaction_id AS sepa_transaction_id,
  __t2.id AS id,
  __t2.musician_id AS musician_id,
  __t2.subject AS subject,
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
  ];

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project */
  private $project = null;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , FinanceService $financeService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->financeService = $financeService;
  }

  public function shortTitle()
  {
    return $this->l->t('Bulk-transactions for project "%s"', array($this->projectName));
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

    $projectMode = $this->projectId > 0;
    if ($projectMode)  {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    }

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
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'id' => 'int', ];

    // Sorting field(s)
    $opts['sort_field'] = [
      '-submission_dead_line',
      '-created',
      'id',
      $this->joinTableFieldName(self::COMPOSITE_PAYMENTS_TABLE, 'sort_key'),
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
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];
    if ($this->addOperation()){
      $opts['display']['tabs'] = false;
    }

    // in order to be able to collapse composite-payment details:
    $opts['css']['row'] = function($name, $position, $divider, $row, $pme) {
      static $evenOdd = [ 'even', 'odd' ];
      static $lastBulkTransactionId = -1;
      static $oddCompositePayment = true;
      static $oddBulkTransaction = false;

      $bulkTransactionId = $row['qf'.$pme->fdn['id']];
      $compositePaymentId = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];

      $cssClasses = ['bulk-transaction'];
      if ($lastBulkTransactionId != $bulkTransactionId) {
        $lastBulkTransactionId = $bulkTransactionId;
        $oddCompositePayment = true;
        $cssClasses[] = 'first';
        $cssClasses[] = 'following-hidden';
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

      $bulkTransactionId = $row['qf'.$pme->fdn['id']];
      $compositePaymentId = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];

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
      'input'    => 'R',
      'input|AP' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => '0', // auto increment
      'sort'     => true
    ];

    $joinTables = $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'row_tag', [ 'input' => 'SRH' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_PAYMENTS_TABLE, 'sort_key', [ 'input' => 'SRH' ]);

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
        'css'      => [ 'postfix' => ' musician-id squeeze-subsequent-lines' ],
        'select' => 'M',
        'input' => 'R',
        'sql' => $this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.musician_id',
        'values' => [
          'table' => self::MUSICIANS_TABLE,
          'column' => 'id',
          'join' => '$join_col_fqn = '.$this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.musician_id',
          'description' => 'CONCAT($table.id, \': \', '.parent::musicianPublicNameSql().')',
          'filters' => (!$projectMode
                        ? null
                        : parent::musicianInProjectSql($this->projectId)),
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
        'css'      => [ 'postfix' => ' bank-account-iban squeeze-subsequent-lines' ],
        'select' => 'M',
        'sql' => $this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.bank_account_id',
        'values' => [
          'table' => "SELECT
  CONCAT_WS('".self::JOIN_KEY_SEP."', __t.musician_id, __t.sequence) AS bank_account_id,
  __t.iban AS iban
  FROM ".self::SEPA_BANK_ACCOUNTS_TABLE." __t",
          'column' => 'bank_account_id',
          'join' => '$join_col_fqn = '.$this->joinTables[self::COMPOSITE_PAYMENTS_TABLE].'.bank_account_id',
          'description' => 'iban',
        ],
        'php' => function($value, $op, $field, $row, $recordId, $pme) {
          $values = Util::explode(',', $value, Util::TRIM);
          foreach ($values as &$value) {
            $value = $this->decrypt($value);
          }
          return implode('<br/>', $values);
        },
        'display' => [
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
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
          'description' => 'mandate_reference',
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
        'css' => [ 'postfix' => ' subject squeeze-subsequent-lines', ],
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
      ]);

    $opts['fdd']['due_date'] = array_merge(
      $this->defaultFDD['due_date'],
      [
        'tab' => [ 'id' => 'transaction' ],
        'input' => 'R',
        'tooltip' => $this->toolTipsService['bulk-transaction-due-date'],
        'php|LF' => [$this, 'bulkTransactionRowOnly'],
      ]);

    $opts['fdd']['actions'] = [
      'tab' => [ 'id' => 'transaction' ],
      //'php|LF' => [$this, 'bulkTransactionRowOnly'],
      'name'  => $this->l->t('Actions'),
      'css'   => [ 'postfix' => ' bulk-transaction-actions' ],
      'input' => 'VR',
      'sql' => '$main_table.id',
      'sort'  => false,
      'php' => function($value, $op, $field, $row, $recordId, $pme) {
          $rowTag = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];
          if (strstr($rowTag, ';') === false) {
            return '';
          }
          $post = json_encode([
            'bulkTransactionId' => $recordId['id'],
            'requesttoken' => \OCP\Util::callRegister(),
            'projectId' => $row['qf'.$pme->fdn[$this->joinTableFieldName(self::PROJECTS_TABLE, 'id')].'_idx'],
            'projectName' => $row['qf'.$pme->fdn[$this->joinTableFieldName(self::PROJECTS_TABLE, 'id')]],
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
          foreach($actions as $key => $action) {
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

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($submitIdx, $opts)  {
      if (empty($row['qf'.$submitIdx])) {
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
   * Print only the values for the bulk-transaction row
   *
   * @todo: if the search results (e.g. for the amount) do not contain
   * the composite row, then the missing data should also be printed.
   */
  public function bulkTransactionRowOnly($value, $action, $k, $row, $recordId, $pme) {
    $rowTag = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::COMPOSITE_PAYMENTS_TABLE)]];
    if (strstr($rowTag, ';') !== false) {
      return $value;
    } else {
      return '';
    }
  }
}
