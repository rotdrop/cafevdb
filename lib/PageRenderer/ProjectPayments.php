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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

/**Table generator for Instruments table. */
class ProjectPayments extends PMETableViewBase
{
  const TEMPLATE = 'project-payments';
  const TABLE = self::COMPOSITE_PAYMENTS_TABLE;
  const DEBIT_NOTES_TABLE = self::SEPA_BULK_TRANSACTIONS_TABLE;

  /** @var FinanceService */
  private $financeService;

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\CompositePayment::class,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
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
      'sql' => "SELECT
  CONCAT_WS(';', __t1.composite_payment_id, GROUP_CONCAT(__t1.id ORDER BY __t1.id)) AS row_tag,
  0 AS sort_key,
  GROUP_CONCAT(CONCAT_WS('".self::COMP_KEY_SEP."', __t1.project_id, __t1.field_id, BIN2UUID(__t1.receivable_key)) ORDER BY __t1.id) AS receivable_key_string,
  __t1.*
FROM ".self::PROJECT_PAYMENTS_TABLE." __t1
GROUP BY __t1.composite_payment_id
UNION
SELECT
  __t2.id AS row_tag,
  __t2.id AS sort_key,
  CONCAT_WS('".self::COMP_KEY_SEP."', __t2.project_id, __t2.field_id, BIN2UUID(__t2.receivable_key)) AS receivable_key_string,
  __t2.*
FROM ".self::PROJECT_PAYMENTS_TABLE." __t2",
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'id' => null, // [ 'self' => true ],
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
  ];

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
    if ($projectMode)  {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    }
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
      $this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'sort_key'),
    ];

    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    $opts['css']['postfix'] = [
      'project-payments',
      'direct-change',
      'show-hide-disabled',
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
        $cssClasses[] = 'following-hidden';
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
      $projectPaymentId = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::PROJECT_PAYMENTS_TABLE)]];

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
      'input|AP' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => null,
      'sort'     => true,
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
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'row_tag', [ 'name' => 'row_tag', 'input' => 'SRH' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'sort_key', [ 'name' => 'input', 'input' => 'SRH' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'name'     => $this->l->t('Musician'),
        'css'      => [ 'postfix' => [ 'instrumentation-id', ], ],
        'select' => 'D',
        'input' => 'M',
        'values' => [
	  'description' => [
	    'columns' => [ '$table.id', self::musicianPublicNameSql() ],
	    'divs' => [ ': ' ],
	    'ifnull' => [ false, false ],
	    'cast' => [ 'CHAR', false ],
	  ],
          'filters' => (!$projectMode
                        ? null
                        : parent::musicianInProjectSql($this->projectId)),
        ],
        'php|LF' => [$this, 'compositeRowOnly'],
      ]);

    $opts['fdd']['amount'] = array_merge(
      $this->defaultFDD['money'], [
        'name' => $this->l->t('Amount'),
        'input' => 'H',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'amount',
      array_merge(
        $this->defaultFDD['money'], [
          'sql' => 'IF($join_table.sort_key = 0, $main_table.amount, $join_col_fqn)',
          'name' => $this->l->t('Amount'),
          'input' => 'M',
        ]));

    $opts['fdd']['date_of_receipt'] = array_merge(
      $this->defaultFDD['date'], [
        'tab' => [ 'id' => 'booking' ],
        'name' => $this->l->t('Date of Receipt'),
        'input' => 'M',
      ]);

    $opts['fdd']['subject'] = array(
      'name' => $this->l->t('Subject'),
      'css'  => [ 'postfix' => [ 'subject', 'squeeze-subsequent-lines', ], ],
      'input' => 'H',
      'select' => 'T',
      'display|LF' => [ 'popup' => 'data' ],
      'escape' => true,
      'sort' => true
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'subject',
      [
        'tab' => [ 'id' => 'booking' ],
        'name' => $this->l->t('Subject'),
        'input'  => 'MR',
        'css'  => [ 'postfix' => [ 'subject', 'squeeze-subsequent-lines', ], ],
        'sql|LFVD' => 'IF($join_table.sort_key = 0, REPLACE($main_table.subject, \'; \', \'<br/>\'), $join_col_fqn)',
        'sql|ACP' => 'IF($join_table.sort_key = 0, $main_table.subject, $join_col_fqn)',
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

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key',
      [
        'tab' => [ 'id' => [ 'booking', 'transaction' ] ],
        'name' => $this->l->t('Receivable'),
        'select' => 'M',
        'select|ACP' => 'D',
        'input' => 'M',
        'css'  => [ 'postfix' => [ 'receivable', 'squeeze-subsequent-lines', 'chosen-dropup', ], ],
        // Pre-computed key for composite-payment row
        'sql' => $this->joinTables[self::PROJECT_PAYMENTS_TABLE].'.receivable_key_string',
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
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppftr
    ON ppftr.locale = "'.($this->l10n()->getLanguageCode()).'"
      AND ppftr.object_class = "'.addslashes(Entities\ProjectParticipantField::class).'"
      AND ppftr.field = "name"
      AND ppftr.foreign_key = ppf.id
  ON ppfo.field_id = ppf.id',
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
          'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
          'popup' => 'data',
        ],
      ]);

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
          'encrypt' => function($value) { return $this->encrypt($value); },
          'decrypt' => function($value) { return $this->decrypt($value); },
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
      'input' => 'R',
      'select' => 'T',
      'escape' => true,
      'sort' => true,
      'tooltip' => $this->toolTipsService['debit-note-email-message-id'],
      'display|LF' => [ 'popup' => 'data' ],
    ];

    $readOnlySafeGuard = function(&$pme, $op, $step, &$row) use ($opts) {

        $bulkTransactionId = $row['qf'.$pme->fdn['sepa_transaction_id']];
        if (!empty($bulkTransactionId)) {
          $pme->options = 'LVF';
          if ($op !== 'select' ) {
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
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) {

        if ($this->listOperation()) {
          return true;
        }

        $this->logInfo('OP ' . $op);

        $sortKeyIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'sort_key')];
        $receivableKeyIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'key')];
        $amountIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'amount')];
        $subjectIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject')];

        if ($row['qf'.$sortKeyIndex] == 0) {
          $this->logInfo('COMPOSITE ROW');
          $pme->fdd[$receivableKeyIndex]['select'] = 'M';
          $pme->fdd[$receivableKeyIndex]['name'] = $this->l->t('Receivables');
          $pme->fdd[$amountIndex]['input'] = 'R';
          $pme->fdd[$amountIndex]['name'] = $this->l->t('Total Amount');
          $pme->fdd[$subjectIndex]['name'] = $this->l->t('Summary');
        } else {
          $this->logInfo('COMPONENT ROW');
          $pme->fdd[$receivableKeyIndex]['select'] = 'D';
        }
        return true;
      };

    if ($projectMode) {
      $opts['filters'] = $joinTables[self::PROJECT_PAYMENTS_TABLE].'.project_id = '.$projectId;
    }

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    // $this->logInfo('GROUPS '.Functions\dump($opts['groupby_fields']));

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
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
   * @todo The UI should rather expose multiple edit lines and provide a
   * "feeling" like e.g. in GnuCash for split-bookings.
   */
  public function beforeInsertSanitizeFields(&$pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

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
    $newValues['amount'] = $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'amount')];
    $newValues['subject'] = $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'subject')];

    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'musician_id')] = $musicianId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'project_id')] = $projectId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'field_id')] = $fieldId;
    $newValues[$this->joinTableFieldName(self::PROJECT_PAYMENTS_TABLE, 'receivable_key')] = $receivableKey;

    $changed = array_keys($newValues);

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');
    return true;
  }

  /**
   * Print only the values for the composite row.
   *
   * @todo: if the search results (e.g. for the amount) do not contain
   * the composite row, then the missing data should also be printed.
   */
  public function compositeRowOnly($value, $action, $k, $row, $recordId, $pme) {
    $rowTag = $row['qf'.$pme->fdn[$this->joinTableMasterFieldName(self::PROJECT_PAYMENTS_TABLE)]];
    if (strstr($rowTag, ';') !== false) {
      return $value;
    } else {
      return '';
    }
  }
}
