<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use \BadFunctionCallException;

use OCP\IRequest;

use OCA\CAFEVDB\Common\NumberFormatter;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType as TaxType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\SentEmailsService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/** Table generator for Instruments table. */
class Invoices extends PMETableViewBase
{
  use FieldTraits\ActionMenuToggleTrait;
  use FieldTraits\CryptoTrait;
  use FieldTraits\FinanceModeNavigationItemTrait;
  use FieldTraits\MusicianInProjectTrait;
  use FieldTraits\MusicianPublicNameTrait;
  use FieldTraits\ProjectEntityTrait;
  use FieldTraits\ParticipantFileFieldsTrait;
  use FieldTraits\QueryFieldTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EnsureEntityTrait;

  const TEMPLATE = 'invoices';
  const TABLE = self::INVOICES_TABLE;
  const DEBIT_NOTES_TABLE = self::SEPA_BULK_TRANSACTIONS_TABLE;
  const SPLIT_DATABASE_STORAGE_ENTRIES_TABLE =
    self::DATABASE_STORAGE_DIR_ENTRIES_TABLE . self::VALUES_TABLE_SEP . 'split';
  const COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE =
    self::DATABASE_STORAGE_DIR_ENTRIES_TABLE . self::VALUES_TABLE_SEP . 'composite';

  const ROW_TAG_PREFIX = '0;';

  /** @var array */
  private $invoiceExpanded = [];

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\Invoice::class,
    ],
    self::MUSICIANS_TABLE . self::VALUES_TABLE_SEP . 'originator' => [
      'entity' => Entities\Musician::class,
      'identifier' => [
        'id' => 'originator_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'sql' => 'SELECT
  __t1.*,'
      . '
  JSON_ARRAYAGG(
    DISTINCT
    IF(__t4.id IS NULL OR COALESCE(__t2.option_value, __t3.data) IS NULL, NULL, CONCAT_WS("' . self::COMP_KEY_SEP . '", __t2.project_id, __t4.id, BIN2UUID(__t3.key)))
    ORDER BY __t2.project_id ASC, __t4.id ASC
  ) AS receivable_keys,'
      .'
  JSON_OBJECTAGG(
    IF(__t4.id IS NULL, NULL, CONCAT_WS("' . self::COMP_KEY_SEP . '", __t2.project_id, __t4.id, BIN2UUID(__t3.key))),
    IF(__t4.data_type = "' . FieldType::LIABILITIES . '", COALESCE(-1 * __t2.option_value, -1 * __t3.data), COALESCE(__t2.option_value, __t3.data))
  ) AS receivable_values,'
      . '
  JSON_OBJECTAGG(
    IF(__t4.id IS NULL, NULL, CONCAT_WS("' . self::COMP_KEY_SEP . '", __t2.project_id, __t4.id, BIN2UUID(__t3.key))),
    __t2.deposit
  ) AS receivable_deposits,'
      . '
  JSON_OBJECTAGG(
    IF(__t4.id IS NULL, NULL, CONCAT_WS("' . self::COMP_KEY_SEP . '", __t2.project_id, __t4.id, BIN2UUID(__t3.key))),
    __t4.data_type
  ) AS receivable_data_types'
      . '
FROM ' . self::MUSICIANS_TABLE . ' __t1
LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE . ' __t2
ON __t2.musician_id = __t1.id
LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE . ' __t3
ON __t2.field_id = __t3.field_id AND __t2.option_key = __t3.key
LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_TABLE . ' __t4
ON __t2.field_id = __t4.id AND __t4.data_type  IN ('
        . "   '" . FieldType::RECEIVABLES . "',"
        . "   '" . FieldType::LIABILITIES . "'"
        . ' )
GROUP BY __t1.id',
      'identifier' => [
        'id' => 'debitor_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::SEPA_BANK_ACCOUNTS_TABLE => [
      'entity' => Entities\SepaBankAccount::class,
      'identifier' => [
        'musician_id' => 'debitor_id',
        'sequence' => 'bank_account_sequence',
      ],
      'column' => 'sequence',
      'flags' => self::JOIN_READONLY,
    ],
    self::SEPA_DEBIT_MANDATES_TABLE => [
      'entity' => Entities\SepaDebitMandate::class,
      'identifier' => [
        'musician_id' => 'debitor_id',
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
    self::INVOICE_ITEMS_TABLE => [
      // not elegant, but should add an additional row in front of the
      // collection of invoice-items.
      //
      // Note that the fancy "composite_key" have to be kept in sync with the
      // composite_key fdd below
      'sql' => "SELECT
  CONCAT('".self::ROW_TAG_PREFIX."', __t1.invoice_id) AS row_tag,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::COMP_KEY_SEP."', __t1.project_id, __t1.field_id, BIN2UUID(__t1.receivable_key)) ORDER BY __t1.id) AS receivable_composite_key,
  GROUP_CONCAT(DISTINCT __t1.id ORDER BY __t1.id) AS id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.amount) ORDER BY __t1.id) AS amount,
  SUM(__t1.amount) AS total_amount,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.subject) ORDER BY __t1.id) AS subject,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.project_id) ORDER BY __t1.id) AS project_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.field_id) ORDER BY __t1.id) AS field_id,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.receivable_key) ORDER BY __t1.id) AS receivable_key,
  GROUP_CONCAT(DISTINCT CONCAT_WS('".self::JOIN_KEY_SEP."', __t1.id, __t1.balance_documents_folder_id) ORDER BY __t1.id) AS balance_documents_folder_id,
  GROUP_CONCAT(DISTINCT __t1.balance_documents_folder_id ORDER BY __t1.id) AS balance_documents_folder_ids,
  GROUP_CONCAT(DISTINCT __t1.project_id) AS project_ids,
  __t1.debitor_id,
  __t1.invoice_id
FROM " . self::INVOICE_ITEMS_TABLE . " __t1@WHERE_PLACEHOLDER_T1@
GROUP BY __t1.invoice_id
UNION
SELECT
  __t2.id AS row_tag,
  CONCAT_WS('" . self::COMP_KEY_SEP . "', __t2.project_id, __t2.field_id, BIN2UUID(__t2.receivable_key)) AS receivable_composite_key,
  __t2.id,
  __t2.amount,
  __t2.amount AS total_amount,
  __t2.subject,
  __t2.project_id,
  __t2.field_id,
  __t2.receivable_key,
  __t2.balance_documents_folder_id,
  __t2.balance_documents_folder_id AS balance_documents_folder_ids,
  __t2.project_id AS project_ids,
  __t2.debitor_id,
  __t2.invoice_id
FROM " . self::INVOICE_ITEMS_TABLE . " __t2@WHERE_PLACEHOLDER_T2@",
      'entity' => Entities\InvoiceItem::class,
      'identifier' => [
        'id' => false,
      ],
      'filter' => [
        'invoice_id' => 'id',
      ],
      'column' => 'row_tag',
      'flags' => self::JOIN_GROUP_BY,
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [
        'id' => 'project_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::INVOICE_ITEMS_TABLE,
          'column' => 'field_id',
        ],
        'project_id' => 'project_id',
      ],
      'column' => 'id',
    ],
    self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDataOption::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'field_id' => [
          'table' => self::INVOICE_ITEMS_TABLE,
          'column' => 'field_id',
        ],
        'key' => [
          'table' => self::INVOICE_ITEMS_TABLE,
          'column' => 'receivable_key',
        ],
      ],
      'column' => 'key',
      'encode' => 'BIN2UUID(%s)',
    ],
    // legal justification for sales tax rate
    self::TAXATION_STATUTORY_SOURCES_TABLE => [
      'entity' => Entities\TaxationStatutorySource::class,
      'identifier' => [
        'id' => 'taxation_statutory_source_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
    ],
    // link the balance directories via their parent_id for the composite invoice item
    self::COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE => [
      'entity' => Entities\DatabaseStorageFolder::class,
      'flags' => self::JOIN_READONLY,
      'sql' => 'SELECT
  dsf.*,
  s.root_id,
  pdsf.name AS parent_name,
  p.id AS project_id
FROM Projects p
INNER JOIN DatabaseStorages s
  ON s.id = p.financial_balance_documents_storage_id
LEFT JOIN DatabaseStorageDirEntries pdsf
  ON pdsf.type = "folder" AND (p.type = "permanent" AND pdsf.parent_id = s.root_id)
LEFT JOIN DatabaseStorageDirEntries dsf
  ON dsf.type = "folder" AND ((p.type = "permanent" AND dsf.parent_id = pdsf.id)
     OR (p.type= "temporary" AND dsf.parent_id = s.root_id))
WHERE dsf.id IS NOT NULL',
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECTS_TABLE,
          'column' => 'id',
        ],
        'id' => 'balance_documents_folder_id',
      ],
      'column' => 'id',
    ],

    // link the balance directories via their parent_id for the split invoice items
    self::SPLIT_DATABASE_STORAGE_ENTRIES_TABLE => [
      'entity' => Entities\DatabaseStorageFolder::class,
      'flags' => self::JOIN_READONLY,
      'sql' => 'SELECT
  dsf.*,
  s.root_id,
  pdsf.name AS parent_name,
  p.id AS project_id
FROM Projects p
INNER JOIN DatabaseStorages s
  ON s.id = p.financial_balance_documents_storage_id
LEFT JOIN DatabaseStorageDirEntries pdsf
  ON pdsf.type = "folder" AND (p.type = "permanent" AND pdsf.parent_id = s.root_id)
LEFT JOIN DatabaseStorageDirEntries dsf
  ON dsf.type = "folder" AND ((p.type = "permanent" AND dsf.parent_id = pdsf.id)
     OR (p.type= "temporary" AND dsf.parent_id = s.root_id))
WHERE dsf.id IS NOT NULL',
      'identifier' => [
        'project_id' => [
          'table' => self::PROJECTS_TABLE,
          'column' => 'id',
        ],
        'id' => [
          'table' => self::INVOICE_ITEMS_TABLE,
          'column' => 'balance_documents_folder_id',
        ],
      ],
      'column' => 'id',
    ],
  ];

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    IRequest $request,
    PHPMyEdit $phpMyEdit,
    PageNavigation $pageNavigation,
    ToolTipsService $toolTipsService,
    //
    private DatabaseStorageUtil $databaseStorageUtil,
    private FinanceService $financeService,
    protected OrganizationalRolesService $orgaRolesService,
    protected ProjectParticipantFieldsService $participantFieldsService,
    protected ProjectService $projectService,
    protected UserStorage $userStorage,
  ) {
    parent::__construct(
      self::TEMPLATE,
      //
      configService: $configService,
      entityManager: $entityManager,
      request: $request,
      pme: $phpMyEdit,
      pageNavigation: $pageNavigation,
      toolTipsService: $toolTipsService,
    );
    $this->invoiceExpanded = $this->request['invoiceExpanded'];

    $this->findProject(enforce: false);

    $this->initCrypto();

    if ($this->listOperation()) {
      $this->pme->overrideLabel('Add', $this->l->t('New Invoice'));
    }
  }

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return !empty($this->project)
      ? $this->l->t('Invoicing for project "%s"', $this->project->getName())
      : $this->l->t('Invoicing for all projects');
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.LongVariable)
   */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;

    $projectMode = $this->projectId > 0;

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
      'invoiceExpanded' => $this->invoiceExpanded,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s). 'id' and self::INVOICE_ITEMS_TABLE.id must
    // be there in order to group the fields correctly, as we "blow"
    // up the table by joining self::INVOICE_ITEMS_TABLE.
    if ($projectMode) {
      $opts['sort_field'] = [
        '-invoice_date',
        '-due_date',
        'project_id',
        'debitor_id',
        'id',
        $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'row_tag'),
      ];
    }

    $opts['groupby_fields'] = [ 'id' ];
    $opts['groupby_where'] = true;

    $opts['css']['postfix'] = [
      self::TEMPLATE,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_DIRECT_CHANGE,
    ];

    // in order to be able to collapse payment details:
    $opts['css']['row'] = function(string $name, null $position, string $divider, array $row, PHPMyEdit $pme):array {
      static $evenOdd = [ 'even', 'odd' ];
      static $lastCompositeId = -1;
      static $oddInvoiceItem = true;
      static $oddInvoice = false;

      $invoiceId = $row[$this->queryField('id')];
      // $projectPaymentId = $row[$this->queryField($this->joinTableMasterFieldName(self::INVOICE_ITEMS_TABLE)]];

      $cssClasses = ['invoice'];
      if ($lastCompositeId != $invoiceId) {
        $lastCompositeId = $invoiceId;
        $oddInvoiceItem = true;
        $cssClasses[] = 'first';
        if (!($this->invoiceExpanded[$invoiceId]??false)) {
          $cssClasses[] = 'following-hidden';
        }
        // $cssClasses[] = 'disable-row-click';
        $cssClasses[] = $evenOdd[(int)$oddInvoice];
        $oddInvoice = !$oddInvoice;
      } else {
        $cssClasses[] = 'following';
        $cssClasses[] = 'invoice-item';
        $cssClasses[] = $evenOdd[(int)$oddInvoiceItem];
        $oddInvoiceItem = !$oddInvoiceItem;
      }

      return $cssClasses;
    };

    // wrap the composite groups into tbody elements, otherwise the
    // groups cannot be hidden individually.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function($pme, $op, $step, $row) {
      // $this->logInfo('DATA TRIGGER '.$op.' '.$step.' '.print_r($row, true));
      static $lastCompositeId = -1;

      $invoiceId = $row[$this->queryField('id')];

      if ($lastCompositeId != $invoiceId) {
        if ($lastCompositeId > 0) {
          echo '</tbody>
<tbody>';
        }
        $lastCompositeId = $invoiceId;
      }
      return true;
    };

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';
    $opts['options'] .= 'M';

    // controls display of an location of edit/misc buttons
    $opts['navigation'] = self::PME_NAVIGATION_NO_MULTI . 'C';
    // $opts['misc']['css']['major'] = 'misc';
    // $opts['misc']['css']['minor'] = 'payment tooltip-right';
    // $opts['labels']['Misc'] = $this->l->t('Receipt');

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
            'id' => 'invoice',
            'tooltip' => $this->l->t('General invoice data'),
            'name' => $this->l->t('Invoice Data'),
          ], [
            'id' => 'taxes',
            'tooltip' => $this->l->t('Tax rate and constitutory sources'),
            'name' => $this->l->t('Taxes'),
          ], [
            'id' => 'documents',
            'tooltip' => $this->toolTipsService['page-renderer:invoices:documents'],
            'name' => $this->l->t('Documents'),
          ], [
            'id' => 'transaction',
            'tooltip' => $this->l->t('Bulk-transaction data'),
            'name' => $this->l->t('Bank Transaction'),
          ], [
            'id' => 'tab-all',
            'tooltip' => $this->toolTipsService['page-renderer:tab:showall'],
            'name' => $this->l->t('Display all columns'),
          ],
        ],
      ]);
    if ($this->addOperation()) {
      $opts['display']['tabs'] = false;
    }

    $opts['fdd']['id'] = [
      'tab' => [ 'id' => 'tab-all', ],
      'name'     => $this->l->t('Invoice Id'),
      'select'   => 'T',
      'align'    => 'right',
      'input'    => 'RH',
      'input|A' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => null,
      'sort'     => true,
      'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
        $html = '';
        if ($this->isCompositeRow($row, $pme)) {
          $html = '<input type="hidden" class="expanded-marker" name="invoiceExpanded['.$recordId['id'].']" value="'.(int)($this->invoiceExpanded[$recordId['id']]??0).'"/>';
          if ($this->expertMode) {
            $html .= '<span class="cell-wrapper">' . $value . '</span>';
          }
        }
        return $html;
      },
    ];

    $boardMember = $this->orgaRolesService->getBoardMember($this->userId());
    $defaultOriginatorId = $boardMember ? $boardMember->getMusician()->getId() : null;

    $opts['fdd']['debitor_id'] = [
      'name'     => $this->l->t('Debitor-Musician-Id'),
      'input'    => 'RH',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    $opts['fdd']['project_id'] = [
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'RH',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    $invoiceItemsPlaceHolder = [
      '@WHERE_PLACEHOLDER_T1@',
      '@WHERE_PLACEHOLDER_T2@',
    ];
    $invoiceItemsReplacement = $projectMode
      ? [ ' WHERE __t1.project_id = ' . $this->projectId, ' WHERE __t2.project_id = ' . $this->projectId, ]
    : [ '', '' ];
    $this->joinStructure[self::INVOICE_ITEMS_TABLE]['sql'] = str_replace(
      $invoiceItemsPlaceHolder,
      $invoiceItemsReplacement,
      $this->joinStructure[self::INVOICE_ITEMS_TABLE]['sql'],
    );
    $this->defineJoinStructure($opts);

    $opts['fdd']['sepa_transaction_id'] = [
      'name'     => $this->l->t('Bulk-Transaction Id'),
      'input'    => 'RH',
      'options'  => 'LFAVCPD',
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'row_tag', [
        'name' => 'row_tag',
        'input' => 'RH',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'invoice_id', [
        'name' => 'invoice_id',
        'input' => 'RH',
      ]);

    list(, $projectIdKey) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      [
        'name' => $this->l->t('Project'),
        'css' => [ 'postfix' => [ 'project-id', ], ],
        'select|CDV' => 'T',
        'select|AFLP' => 'D',
        'maxlen' => 20,
        'size' => 16,
        'input' => 'M',
        'input|C' => 'R',
        'input|A' => ($projectMode ? 'R' : ''),
        'default' => ($projectMode ? $this->projectId : null),
        'values' => [
          'description' => [
            'columns' => [ '$table.name' ],
            'cast' => [ false ],
            'ifnull' => [ false ],
          ],
          'groups'      => 'year',
          'orderby'     => '$table.year DESC, $table.name ASC',
          'filters' => (!$projectMode
                        ? null
                        : '$table.id = ' . $this->projectId),
        ],
        'php|LF' => [$this, 'compositeRowOnly'],
      ]);
    if (!$projectMode) {
      $opts['fdd'][$projectIdKey]['values|DVFL'] = $opts['fdd'][$projectIdKey]['values'];
      $opts['fdd'][$projectIdKey]['values|DVFL']['filters'] = '$table.id IN (SELECT project_id FROM $main_table)';
    }

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'name',
      [
        'name'  => $this->l->t('Project Name'),
        'input' => 'VHR',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year',
      [
        'name'  => $this->l->t('Project Year'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['originator_id'] = [
      'name' => $this->l->t('Author'),
      'css' => [ 'postfix' => [ 'originator-id', 'default-readonly', 'allow-empty', 'tab-invoice-readwrite', 'tab-transaction-readwrite', 'tab-all-readwrite' ], ],
      'select' => 'D',
      'input' => 'M',
      'sort' => true,
      // 'input|C' => 'R',
      // 'select|C' => null, // 'T',
      // 'sql|C' => static::musicianPublicNameSql(),
      'default' => $defaultOriginatorId,
      'values' => [
        'column' => 'id',
        'description' => [
          'columns' => [ static::musicianPublicNameSql() ],
          'divs' => [],
          'ifnull' => [ false, false ],
          'cast' => [ false ],
        ],
        // Only executive boad memebers can be registered as originators.
        'filters' => static::musicianInProjectSql($this->getExecutiveBoardProjectId()),
        'join' => [ 'reference' => $this->joinTables[self::MUSICIANS_TABLE . self::VALUES_TABLE_SEP . 'originator'], ],
      ],
      'display|ACP' => [
        'attributes' => [ 'readonly' => true ],
        'popup' => 'data',
        'prefix' => '<div class="flex-container">',
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $checked = 'checked="checked" ';
          return '  <input id="pme-invoice-originator-lock"
    ' . $checked . '
    type="checkbox"
    class="pme-input pme-input-lock lock-unlock"/>
  <label class="pme-input pme-input-lock lock-unlock"
         style="margin:auto 0;position:relative!important;"
         title="' . $this->toolTipsService['pme:input:lock:unlock'].'"
         for="pme-invoice-originator-lock"></label>
</div>';
        },
      ],
      'php|LF' => [$this, 'compositeRowOnly'],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'name' => $this->l->t('Debitor'),
        'css' => [ 'postfix' => [ 'debitor-id', 'allow-empty' ], ],
        'select|AFL' => 'D',
        'input' => 'M',
        'input|C' => 'R',
        'select|CVD' => 'T',
        // 'select|C' => null, // 'T',
        'sql' => static::musicianPublicNameSql(),
        // 'default|C' => $this->musicianId, // ???
        'values' => [
          'description' => [
            'columns' => [ static::musicianPublicNameSql() ],
            'divs' => [],
            'ifnull' => [ false, false ],
            'cast' => [ false ],
          ],
          'data' => [
            'keys' => 'receivable_keys',
            'values' => 'receivable_values',
            'deposits' => 'receivable_deposits',
            'data-types' => 'receivable_data_types',
          ],
          'filters' => (!$projectMode
                        ? null
                        : static::musicianInProjectSql($this->projectId)),
        ],
        'php|LF' => [$this, 'compositeRowOnly'],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name_personal', [
        'name'     => $this->l->t('Display-Name (pers.)'),
        'css'      => [ 'postfix' => [ 'musician-personal-public-name' ], ],
        'options'  => 'LFAVCPD',
        'input' => $this->pmeBare ? 'R' : 'HR', // handy for export
        'sql' => static::musicianPublicNameSql(firstNameFirst: true),
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'organization', [
        'name' => $this->l->t('Organization'),
        'input' => 'RH',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'name' => $this->l->t('User Id'),
        'input' => 'RH',
      ]);

    $opts['fdd']['invoice_number'] = [
      'name'     => $this->l->t('Invoice Number'),
      'tab' => [ 'id' => 'tab-all' ],
      'input'    => '', // 'M', automatically determined.
      'select'   => 'T',
      'options'  => 'LACPDV',
      'default'  => null,
      'maxlen'   => 255,
      'sort'     => true,
      'display' => [
        'attributes' => [
          'readonly' => true,
          'placeholder' => $this->l->t('automatically determined'),
        ],
        'popup' => 'data',
      ],
      'display|ACP' => [
        'attributes' => [
          'readonly' => true,
          'placeholder' => $this->l->t('automatically determined'),
        ],
        'popup' => 'data',
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $checked = 'checked="checked" ';
          return '<input id="pme-invoice-number-lock"
  ' . $checked . '
  type="checkbox"
  class="pme-input pme-input-lock lock-unlock"/>
<label class="pme-input pme-input-lock lock-unlock"
       title="' . $this->toolTipsService['pme:input:lock:unlock'].'"
       for="pme-invoice-number-lock"></label>';
        },
      ],
      'php|LF' => [$this, 'compositeRowOnly'],
    ];

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
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'amount',
      Util::arrayMergeRecursive(
        $this->defaultFDD['money'], [
          'sql|LF' => 'IF(
  $join_table.row_tag LIKE "' . self::ROW_TAG_PREFIX . '%",
  IF(
    NOT $main_table.amount = $join_table.total_amount,
    CONCAT_WS("' . self::JOIN_KEY_SEP . '", $main_table.amount, $join_table.total_amount),
    $main_table.amount
  ),
  $join_col_fqn
)',
          'mask' => null,
          'css' => [ 'postfix' => [ 'validate-non-zero' ], ],
          'sql' => '$join_col_fqn',
          'name' => $this->l->t('Amount'),
          'input' => 'M',
          'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
            $values = explode(self::JOIN_KEY_SEP, $value);
            if (count($values) == 2) {
              $sign = $values[0] > $values[1] ? '+' : '';
              return '<span class="invoice total-amount">' . $this->moneyValue($values[0]) . '</span>'
                . '<span class="text-fill"> (</span>'
                . '<span class="invoice imbalance">' . $sign . $this->moneyValue($values[0] - $values[1]) . '</span>'
                . '<span class="text-fill"> )</span>';
            }
            return $this->moneyValue($value);
          },
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::TAXATION_STATUTORY_SOURCES_TABLE, 'id', [
        'name' => $this->l->t('Sales Tax'),
        'tab' => [ 'id' => 'taxes' ],
        'css' => [ 'postfix' => [ 'sales-tax' ], ],
        'php|LF' => [$this, 'compositeRowOnly'],
        'sql|LF' => '$join_table.rate * 100',
        'select|LFVD' => 'N',
        'select' => 'D',
        'align' => 'right',
        'mask|LFVD' => '%d%%',
        'input' => 'M',
        'values|ACP' => [
          'description' => [
            'columns' => [ '$table.rate * 100', '$table.law', '$table.hint' ],
            'ifnull' => false,
            'cast' => false,
            'divs' => [ '%, ', ' (', ')' ],
          ],
          PHPMyEdit::OPT_FILTERS => [ '$table.tax_type = "' . TaxType::SALES . '"' ],
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::TAXATION_STATUTORY_SOURCES_TABLE, 'law', [
        'name' => $this->l->t('Legal Basis'),
        'tab' => [ 'id' => 'taxes' ],
        'css' => [ 'postfix' => [ 'statutory-source' ], ],
        'php|LF' => [$this, 'compositeRowOnly'],
        'sql|LF' => 'CONCAT($join_col_fqn, " (", $table.hint, ")")',
        'select' => 'T',
        'input' => 'HR',
        'input|LFVD' => 'R',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'imbalance',
      Util::arrayMergeRecursive(
        $this->defaultFDD['money'], [
          'sql' => '$main_table.amount - $join_table.total_amount',
          'tab' => [ 'id' => 'invoice' ],
          'css' => [ 'postfix' => [ 'tooltip-auto', ] ],
          'name' => $this->l->t('Inconsistency'),
          'input|LF' => 'VHR',
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
          'tooltip' => $this->toolTipsService['invoices:invoice-items:imbalance'],
        ]
    ));

    // This in principle should be set to the date of the actual sending-out
    // of the invoice.
    $opts['fdd']['invoice_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['date'], [
        'tab' => [ 'id' => 'invoice' ],
        'name' => $this->l->t('Invoice Date'),
        'css'  => [ 'postfix' => [ 'invoice-date', ], ],
        'input' => 'M',
        // 'input|LF' => 'H',
        'tooltip' => $this->toolTipsService['invoices:invoice-date'],
      ]);

    // The default should be initialized in JS from either the minimum of the
    // due dates of the registered items.
    $opts['fdd']['due_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['date'], [
        'tab' => [ 'id' => 'invoice' ],
        'name' => $this->l->t('Due Date'),
        'css'  => [ 'postfix' => [ 'due-date', ], ],
        'input' => 'M',
        'input|LF' => 'H',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_TABLE, 'due_date',
      Util::arrayMergeRecursive(
        $this->defaultFDD['date'], [
          'tab' => [ 'id' => 'invoice' ],
          'name|LF' => $this->l->t('Due Date'),
          'name' => $this->l->t('Item Due Date'),
          'sql|LF' => 'IF(' . $this->joinTables[self::INVOICE_ITEMS_TABLE] . '.row_tag LIKE "' . self::ROW_TAG_PREFIX . '%", $main_table.due_date, $join_col_fqn)',
          'sql' => '$join_col_fqn',
          'input|LFDV' => '',
          'input|APC' => 'RH',
        ],
      ),
    );

    $opts['fdd']['balanced_date'] = Util::arrayMergeRecursive($this->defaultFDD['date'], [
        'tab' => [ 'id' => 'invoice' ],
        'name' => $this->l->t('Balanced Date'),
        'css'  => [ 'postfix' => [ 'balanced-date', ], ],
        'input' => '',
        'php|LF' => [$this, 'compositeRowOnly'],
      ]);

    $opts['fdd']['purpose'] = [
      'name' => $this->l->t('Cover Text'),
      'css'  => [ 'postfix' => [ 'purpose', 'squeeze-subsequent-lines', 'clip-long-text', ], ],
      'sql|LFVD' => 'REPLACE($main_table.subject, \'; \', \'<br/>\')',
      'input|LFVD' => 'HRM',
      'select' => 'T',
      'display|LF' => [ 'popup' => 'data' ],
      'escape' => true,
      'sort' => true,
      'textarea|ACP' => [
        'css' => 'wysiwyg-editor',
        'rows' => 4,
        'cols' => 35,
      ],
      'tooltip' => $this->toolTipsService['page-renderer:invoices:purpose'],
    ];

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
      'tooltip' => $this->toolTipsService['page-renderer:invoices:subject'],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'subject',
      [
        'tab' => [ 'id' => 'invoice' ],
        'name' => $this->l->t('Subject'),
        'input'  => 'M',
        'input|LF' => 'HR',
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
        'tooltip' => $this->toolTipsService['page-renderer:invoices:subject'],
      ]);

    /**
     * The following is there in order to remove split-transactions. There will
     * also be a dedicated "add a new split".
     */
    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'id', [
        'tab' => [ 'id' => [ 'invoice', 'transaction' ] ],
        'css' => [ 'postfix' => [ 'invoice-item-id', 'chosen-dropup', ], ],
        'name' => $this->l->t('Receivables'),
        'select' => 'M',
        'input|LF' => 'H',
        'options' => 'PCDV',
        'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn)',
        'values' => [
          'table' => 'SELECT
  ii.id,
  ii.invoice_id,
  ii.debitor_id,
  ii.amount,
  m.first_name,
  m.sur_name,
  m.nick_name,
  m.display_name,
  ppf.project_id,
  ppf.id AS field_id,
  ppf.due_date,
  ppf.deposit_due_date,
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
  FROM ' . self::INVOICE_ITEMS_TABLE . ' ii
  LEFT JOIN ' . self::MUSICIANS_TABLE . ' m
    ON ii.debitor_id = m.id
  LEFT JOIN ' . self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE . ' ppfo
    ON ppfo.field_id = ii.field_id
      AND ppfo.key = ii.receivable_key
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppfotr
    ON ppfotr.locale = "'.($this->getTranslationLanguage()).'"
      AND ppfotr.object_class = "'.addslashes(Entities\ProjectParticipantFieldDataOption::class).'"
      AND ppfotr.field = "label"
      AND ppfotr.foreign_key = CONCAT_WS(" ", ppfo.field_id, BIN2UUID(ppfo.key))
  LEFT JOIN '.self::PROJECT_PARTICIPANT_FIELDS_TABLE.' ppf
    ON ppf.id = ii.field_id
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppftr
    ON ppftr.locale = "'.($this->getTranslationLanguage()).'"
      AND ppftr.object_class = "'.addslashes(Entities\ProjectParticipantField::class).'"
      AND ppftr.field = "name"
      AND ppftr.foreign_key = ppf.id',
          'column' => 'id',
          'description' => [
            'columns' => [
              'CONCAT_WS(" ", "' . $this->currencySymbol() . '", FORMAT($table.amount, 2, "' . ($this->getTranslationLanguage()) . '"))',
              '$table.receivable_display_label',
            ],
            'divs' => [ ' - ' ],
            'ifnull' => [ false, false ],
            'cast' => [ false, false ],
          ],
          'join' => '$main_table.id = $join_table.invoice_id',
          'filters' => '$table.invoice_id = $record_id[id]',
          'data' => 'JSON_OBJECT(
  "debitorId", $table.debitor_id
  , "projectId", $table.project_id
  , "invoiceItemId", $table.id
  , "invoiceId", $table.invoice_id
  , "fieldId", $table.field_id
  , "receivableKey", BIN2UUID($table.receivable_key)
  , "amount", $table.amount
  , "dueDate<", $table.due_date
  , "depositDueDate", $table.deposit_due_date
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
          $compositeKeyIndex = $this->joinQueryFieldIndex(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key');
          return $this->createSupportingDocumentsDownload($value, $action, $compositeKeyIndex, $row, $recordId, $pme);
        },
      ]);

    list(, $compositeKeyKey) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key',
      [
        'tab' => [ 'id' => [ 'invoice', 'transaction' ] ],
        'name' => $this->l->t('Receivables'),
        'select' => 'M',
        'select|ACP' => 'D',
        'input' => 'M',
        'css'  => [ 'postfix' => [ 'receivable', 'allow-empty', 'squeeze-subsequent-lines', 'chosen-dropup', ], ],
        // Pre-computed key for invoice row
        'sql' => $this->joinTables[self::INVOICE_ITEMS_TABLE].'.receivable_composite_key',
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
  ppf.multiplicity AS multiplicity,
  ppf.due_date AS due_date,
  ppf.deposit_due_date AS deposit_due_date
  FROM ' . self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE . ' ppfo
  LEFT JOIN ' . self::FIELD_TRANSLATIONS_TABLE . ' ppfotr
    ON ppfotr.locale = "' . ($this->getTranslationLanguage()) . '"
      AND ppfotr.object_class = "' . addslashes(Entities\ProjectParticipantFieldDataOption::class) . '"
      AND ppfotr.field = "label"
      AND ppfotr.foreign_key = CONCAT_WS(" ", ppfo.field_id, BIN2UUID(ppfo.key))
  INNER JOIN '.self::PROJECT_PARTICIPANT_FIELDS_TABLE.' ppf
    ON ppfo.field_id = ppf.id
      AND ppf.data_type IN ("' . FieldType::RECEIVABLES . '","' . FieldType::LIABILITIES . '")'
          . ($projectMode
             ? '
      AND ppf.project_id = ' . $this->projectId
             : '')
          . '
  LEFT JOIN '.self::FIELD_TRANSLATIONS_TABLE.' ppftr
    ON ppftr.locale = "'.($this->getTranslationLanguage()).'"
      AND ppftr.object_class = "'.addslashes(Entities\ProjectParticipantField::class).'"
      AND ppftr.field = "name"
      AND ppftr.foreign_key = ppf.id
  LEFT JOIN '.self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.' ppfd
    ON ppfd.option_key = ppfo.key
  WHERE ppfo.deleted IS NULL
    AND NOT ppfo.key = CAST("\0" AS BINARY(16))
    AND (ppfd.option_value IS NOT NULL OR ppfo.data IS NOT NULL)
  GROUP BY ppfo.key',
          // 'encode' => 'BIN2UUID(%s)',
          'description' => '$table.display_label',
          'join' => ('$join_table.field_id = '
                     . $this->joinTables[self::INVOICE_ITEMS_TABLE].'.field_id'
                     . ' AND $join_table.key = '
                     . $this->joinTables[self::INVOICE_ITEMS_TABLE].'.receivable_key'
                     . ' AND $join_table.project_id = '
                     . $this->joinTables[self::INVOICE_ITEMS_TABLE].'.project_id'),
          'groups' => 'IF($table.multiplicity IN ("'.
          FieldMultiplicity::SIMPLE.'","'.
          FieldMultiplicity::SINGLE.'","'.
          FieldMultiplicity::GROUPOFPEOPLE.'"),
  "'.$this->l->t('Single Options').'",
  $table.field_name)',
          'orderby' => '$table.sort_field ASC, $table.display_label ASC',
          'filters' => '1',
          'data' => 'JSON_OBJECT(
  "receivableKey", BIN2UUID($table.key)
  , "dueDate", $table.due_date
  , "depositDueDate", $table.deposit_due_date
)',
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
  WHERE ppfd.musician_id = (SELECT cp.debitor_id FROM ' . self::INVOICES_TABLE . ' cp WHERE cp.id = $record_id[id])'
      . ($projectMode ? ' AND ppfd.project_id = '.$this->projectId : '')
      . ')';

    $opts['fdd']['written_invoice_id'] = [
      'name' => $this->l->t('Local Copy'),
      'tab' => [ 'id' => 'documents' ],
      'input|A' => 'HR',
      'css'      => [ 'postfix' => [ 'local-copy', 'written-invoice', ], ],
      'options' => 'LFACDPV',
      'php|CP' => function($value, $action, $k, $row, $recordId, $pme) {

        if ($pme->hidden($k) || empty($row)) {
          return '';
        }

        $invoiceNumber = $row[$this->queryField('invoice_number')];
        $organization = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'organization')];
        // $musicianName = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'id')];
        $musicianNamePersonal = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'display_name_personal')];
        $projectName = $row[$this->joinQueryField(self::PROJECTS_TABLE, 'name')];

        $fileName = $this->getLegacyInvoiceFileName(
          $invoiceNumber,
          $organization,
          $musicianNamePersonal,
          $projectName,
        );

        $dir = $this->getInvoicesPath();
        $invoiceDate = $row[$this->queryField('invoice_date')];
        $year = substr($invoiceDate, 0, 4);
        $dir .= UserStorage::PATH_SEP . $year;

        return '<div class="file-upload-wrapper">
  <table class="file-upload">'
          . $this->dbFileUploadRowHtml(
            $value,
            fieldId: $recordId['id'],
            optionKey: $recordId['id'],
            subDir: null,
            fileBase: $dir . UserStorage::PATH_SEP . $fileName,
            overrideFileName: true,
            musician: null,
            project: null,
            inputValueName: 'written_invoice_id',
          )
          . '
  </table>
</div>';
      },
      'php|LFVD' => function($value, $action, $k, $row, $recordId, $pme) {
        if (empty($value)) {
          return $value;
        }

        /** @var Entities\DatabaseStorageFile $file */
        $file = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($value);

        $invoiceNumber = $row[$this->queryField('invoice_number')];
        $organization = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'organization')];
        // $musicianName = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'id')];
        $musicianNamePersonal = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'display_name_personal')];
        $projectName = $row[$this->joinQueryField(self::PROJECTS_TABLE, 'name')];

        $invoiceFolder = $this->getLegacyInvoiceFolderName(
          $invoiceNumber,
          $organization,
          $musicianNamePersonal,
          $projectName,
        );

        $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink($file);
        $invoiceDate = $row[$this->queryField('invoice_date')];
        $year = substr($invoiceDate, 0, 4);
        $dir = $this->getInvoicesPath()
          . UserStorage::PATH_SEP . $year
          . UserStorage::PATH_SEP . $invoiceFolder;

        try {
          $filesAppLink = $this->userStorage->getFilesAppLink($dir, true);
          $filesAppTarget = md5($filesAppLink);
          $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="'.$this->toolTipsService['page-renderer:upload:open-parent'].'"
       class="button operation open-parent tooltip-auto' . (empty($filesAppLink) ? ' disabled' : '') . '"
       ></a>';
        } catch (\OCP\Files\NotFoundException $e) {
          $this->logInfo('No file found for ' . $dir);
          $filesAppLink = '';
        }
        return '<div class="flex-container">
'
          . $filesAppLink
          . '<a class="download-link ajax-download tooltip-auto inline-block clip-long-text"
   title="' . $this->toolTipsService['page-renderer:invoices:supporting-document'] . '"
   href="' . $downloadLink . '">' . $file->getName() . '</a>
</div>';
      },
    ];

    $opts['fdd']['notification_message_id'] = [
      'name' => $this->l->t('Message-ID'),
      'input'  => '',
      'options'  => 'ACDFLPV',
      'css'  => [ 'postfix' => [ 'message-id', 'hide-subsequent-lines', ], ],
      'select' => 'T',
      'escape' => true,
      'sort' => true,
      'tooltip' => $this->toolTipsService['page-renderer:invoices:notification-message-id'],
      'display|LF' => [ 'popup' => 'data' ],
      'php|LF' => [$this, 'compositeRowOnly'],
      'display' => [
        'popup' => 'data',
      ],
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $attributes = [
            'placeholder' => '<abcdefghijk@domain.tld>',
          ];
          if (!empty($row[PHPMyEdit::QUERY_FIELD . $k])) {
            $attributes['readonly'] = true;
          }
          return $attributes;
        },
        'popup' => 'data',
        'postfix' => function($op, $pos, $k, $row, $pme) {
          if (empty($row[PHPMyEdit::QUERY_FIELD . $k])) {
            // default unlocked if value is empty.
            $checked = '';
          } else {
            $checked = 'checked="checked" ';
          }
          return '<input id="pme-message-id-lock"
  ' . $checked . '
  type="checkbox"
  class="pme-input pme-input-lock lock-unlock"/>
<label class="pme-input pme-input-lock lock-unlock"
       title="' . $this->toolTipsService['pme:input:lock:unlock'].'"
       for="pme-message-id-lock"></label>';
        },
      ],
    ];

    $opts['fdd']['balance_documents_folder_id'] = [
      'name' => $this->l->t('Financial Project Balance'),
      'tab' => [ 'id' => 'documents' ],
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
      'sql' => '$join_col_fqn', // '$main_table.$field_name',
      'values' => [
        'table' => self::DATABASE_STORAGE_DIR_ENTRIES_TABLE,
        'column' => 'id',
        'join' => [ 'reference' => $this->joinTables[self::COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE], ],
        'description' => [
          'columns' => [ '$table.name', ],
          'divs' => [ 0 => '/', ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'groups' => 'CONCAT($table.parent_name, "/")',
        'orderby' => '$table.parent_name ASC, $table.name ASC',
        'data' => 'CONCAT($table.name, "/")',
        'filters' => (!$projectMode
                      ? null
                      : '$table.project_id = ' . $this->projectId),
      ],
      'tooltip' => $this->toolTipsService['page-renderer:invoice-items:project-balance'],
      'display' => [
        'prefix' => function($op, $pos, $k, $row, $pme) {

          if ($op === PHPMyEdit::OPERATION_ADD && empty($this->project)) {
            return null;
          }

          if ($op != PHPMyEdit::OPERATION_ADD && !$this->isCompositeRow($row, $pme)) {
            return null;
          }

          $value = $row[$this->joinQueryField(self::COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE, 'name')];
          if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
            return null;
          }

          $project = $this->project ?? $this->ensureProject($row[$this->queryField('project_id')]);
          $documentPathChain = [ $this->getProjectBalancesPath() ];
          if ($project->getType() == ProjectType::TEMPORARY) {
            $documentPathChain[] = $project->getYear();
          };
          $documentPathChain[] = $project->getName();
          $documentPathChain[] = $this->getSupportingDocumentsFolderName();

          $documentParentPath = implode('/', $documentPathChain);
          $filesAppTarget = md5($documentParentPath);
          if (!empty($value)) {
            if (is_numeric($value)) {
              $value = sprintf('%s-%03d', $project->getName(), $value);
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
   target="' . $filesAppTarget . '"
   title="' . $this->toolTipsService['page-renderer:invoice-items:project-balance:open'] . '"
   class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
></a>';

          return '<div class="flex-container"><span class="pme-cell-prefix">' . $filesAppAnchor . ' </span><span class="pme-cell-content">'
            . '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">';
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {

          if ($op === PHPMyEdit::OPERATION_ADD && empty($this->project)) {
            return null;
          }

          if ($op != PHPMyEdit::OPERATION_ADD && !$this->isCompositeRow($row, $pme)) {
            return null;
          }

          $value = $row[$this->joinQueryField(self::COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE, 'name')];
          if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
            return null;
          }

          return '</div></div></span></div>';
        },
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::COMPOSITE_DATABASE_STORAGE_ENTRIES_TABLE, 'name', [
        'input' => 'HR',
      ],
    );

    $this->makeJoinTableField(
      $opts['fdd'], self::INVOICE_ITEMS_TABLE, 'balance_documents_folder_id', [
        'name' => $this->l->t('Parts Project Balances'),
        'tab' => [ 'id' => 'documents' ],
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
  ' . $this->joinTables[self::INVOICE_ITEMS_TABLE] . '.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%",
  CONCAT_WS(
    ",",
    $main_table.balance_documents_folder_id,
    ' . $this->joinTables[self::INVOICE_ITEMS_TABLE] . '.balance_documents_folder_ids
  ),
  $join_col_fqn)',
        'sql' => 'IF(
  ' . $this->joinTables[self::INVOICE_ITEMS_TABLE] . '.row_tag LIKE "'.self::ROW_TAG_PREFIX.'%",
  ' . $this->joinTables[self::INVOICE_ITEMS_TABLE] . '.balance_documents_folder_ids,
  $join_col_fqn)',
        'values' => [
          'table' => self::DATABASE_STORAGE_DIR_ENTRIES_TABLE,
          'column' => 'id',
          'join' => [ 'reference' => $this->joinTables[self::SPLIT_DATABASE_STORAGE_ENTRIES_TABLE], ],
          'description' => [
            'columns' => [ '$table.name', ],
            'divs' => [ 0 => '/', ],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'groups' => 'CONCAT($table.parent_name, "/")',
          'orderby' => '$table.parent_name ASC, $table.name ASC',
          'data' => 'CONCAT($table.name, "/")',
          'filters' => (!$projectMode
                        ? null
                        : '$table.project_id = ' . $this->projectId),
        ],
        'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
          if ($this->isCompositeRow($row, $pme)) {
            $value = str_replace(', ', '<br/>', $value);
          }
          return $value;
        },
        'tooltip' => $this->toolTipsService['page-renderer:invoice-items:project-balance'],
        'display' => [
          'popup' => function($cellData, $k, $row, $pme) {
            if ($this->isCompositeRow($row, $pme)) {
              return $cellData;
            } else {
              return $pme->fdd[$k]['tooltip'];
            }
          },
          'prefix' => function($op, $pos, $k, $row, $pme) {

            if ($op === PHPMyEdit::OPERATION_ADD && empty($this->project)) {
              return null;
            }

            if ($this->isCompositeRow($row, $pme)) {
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($row[PHPMyEdit::QUERY_FIELD . $k . '_idx'])) {
                return null;
              }
              $value = null;
            } else {
              $value = $row[$this->joinQueryField(self::SPLIT_DATABASE_STORAGE_ENTRIES_TABLE, 'name')];
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
                return null;
              }
            }

            $project = $this->project ?? $this->ensureProject($row[$this->queryField('project_id')]);
            $documentPathChain = [ $this->getProjectBalancesPath() ];
            if ($project->getType() == ProjectType::TEMPORARY) {
              $documentPathChain[] = $project->getYear();
            };
            $documentPathChain[] = $project->getName();
            $documentPathChain[] = $this->getSupportingDocumentsFolderName();

            $documentParentPath = implode('/', $documentPathChain);
            $filesAppTarget = md5($documentParentPath);

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
   title="' . $this->toolTipsService['page-renderer:invoice-items:project-balance:open'] . '"
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

            if ($op === PHPMyEdit::OPERATION_ADD && empty($this->project)) {
              return null;
            }

            if ($this->isCompositeRow($row, $pme)) {
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($row[PHPMyEdit::QUERY_FIELD . $k . '_idx'])) {
                return null;
              }
              $value = null;
            } else {
              $value = $row[$this->joinQueryField(self::SPLIT_DATABASE_STORAGE_ENTRIES_TABLE, 'name')];
              if ($op === PHPMyEdit::OPERATION_DISPLAY && empty($value)) {
                return null;
              }
            }

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
      $opts['fdd'], self::SPLIT_DATABASE_STORAGE_ENTRIES_TABLE, 'name', [
        'input' => 'HR',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BULK_TRANSACTIONS_TABLE, 'created',
      array_merge(
        $this->defaultFDD['date'], [
          'tab' => [ 'id' => [ 'transaction' ] ],
          'name' => $this->l->t('Bank Transaction'),
          'input' => 'R',
          'options' => 'LFVD',
          'css'  => [ 'postfix' => [ 'bulk-transaction', ], ],
          'php|LF' => function($value, $action, $k, $row, $recordId, $pme) {
            $bulkTransactionId = $row[$this->queryField('sepa_transaction_id')];
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
          'name' => $this->l->t('Date of Bank Transaction'),
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
        'options' => 'LFVDCP',
        'select|LF' => 'D',
        'css'  => [ 'postfix' => [ 'bank-account-iban', 'meta-data-popup', ], ],
        'php|LF' => [$this, 'compositeRowOnly'],
        'encryption' => [
          'encrypt' => fn($value) => $this->ormEncrypt($value),
          'decrypt' => fn($value) => $this->ormDecrypt($value ?? ''),
        ],
        'css|LF'  => [ 'postfix' => [ 'bank-account-iban', 'lazy-decryption', 'meta-data-popup', ], ],
        'encryption|LF' => [
          'encrypt' => fn($value) => $this->ormEncrypt($value),
          'decrypt' => function($value) {
            if (empty($value)) {
              return '';
            }
            $value = '<span class="iban encryption-placeholder"
      data-crypto-hash="' . md5($value) . '"
      title="' . Util::htmlEscape($this->l->t('Fetching decrypted values in the background.')) . '"
>'
              . $this->l->t('please wait')
              . '</span>';
            return $value;
          },
        ],
        'values' => [
          'data' => [
            'crypto-hash' => 'MD5($table.$column)',
            'meta-data' => '"iban"', // SQL STRING
          ],
        ],
        'display' => [
          'popup' => function($data) {
            if (empty($data)) {
              return ''; // can happen
            }
            $info  = $this->financeService->getIbanInfo($data);
            if (empty($info)) {
              $this->logInfo('NO INFO FOR IBAN ' . $data);
            }
            $result = '';
            foreach ($info as $key => $value) {
              $result .= $this->l->t($key).': '.$value.'<br/>';
            }
            return $result;
          },
          'attributes' => [
            'data-meta-data' => 'iban',
          ],
        ],
        'display|LF' => [
          'popup' => 'data',
          'attributes' => [
            'data-meta-data' => 'iban',
          ],
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

    $readOnlySafeGuard = function(&$pme, $op, $step, &$row) use ($opts) {

      $bulkTransactionId = $row[$this->queryField('sepa_transaction_id')];
      if (false && !empty($bulkTransactionId)) {
        $pme->options = 'LVF';
        if ($op !== 'select') {
          throw new BadFunctionCallException(
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

    // we mis-use the fields of the InvoiceItem entities for the
    // Invoice entity. We have also to remap other things, like
    // multiplicity of selects and so on.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($musicianReceivableFilter) {

        $rowTag = $row[$this->joinQueryField(self::INVOICE_ITEMS_TABLE, 'row_tag')];

        $balanceDocumentsFolderIdIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'balance_documents_folder_id');
        $pme->fdd[$balanceDocumentsFolderIdIndex]['select'] = $this->isCompositeRowTag($rowTag) ? 'M' : 'D';

        if ($this->listOperation()) {
          // $this->logInfo('LIST OPERATION');
          return true;
        }

        $receivableKeyIndex = $this->joinQueryFieldIndex(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key');

        $amountIndex = $this->queryFieldIndex('amount');
        $invoiceItemsAmountIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'amount');

        $invoiceDateIndex = $this->queryFieldIndex('invoice_date');
        $dueDateIndex = $this->queryFieldIndex('due_date');
        $invoiceItemsDueDateIndex = $this->joinQueryFieldIndex(self::PROJECT_PARTICIPANT_FIELDS_TABLE, 'due_date');

        $balancedDateIndex = $this->queryFieldIndex('balanced_date');

        $subjectIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'subject');
        $debitorIdIndex = $this->joinQueryFieldIndex(self::MUSICIANS_TABLE, 'id');
        $paymentsIdIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'id');
        $subjectIndex = $this->queryFieldIndex('subject');
        $paymentsSubjectIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'subject');
        $imbalanceIndex = $this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'imbalance');
        $writtenInvoiceIndex = $this->queryFieldIndex('written_invoice_id');
        $compositeBalanceDocumentsFolderIdIndex = $this->queryFieldIndex('balance_documents_folder_id');

        $taxationStatutorySourcesIndex = $this->joinQueryFieldIndex(self::TAXATION_STATUTORY_SOURCES_TABLE, 'id');
        $taxationStatutorySourcesLawIndex = $this->joinQueryFieldIndex(self::TAXATION_STATUTORY_SOURCES_TABLE, 'law');

        if ($this->isCompositeRowTag($rowTag)) {
          $this->debug('COMPOSITE ROW');
          $pme->fdd[$receivableKeyIndex]['input'] = 'HR';
          $pme->fdd[$amountIndex]['input'] = 'M';
          $pme->fdd[$invoiceItemsAmountIndex]['input'] = 'HR';
          $pme->fdd[$invoiceDateIndex]['input'] = 'M';
          $pme->fdd[$invoiceDateIndex]['sql'] = '';
          $pme->fdd[$dueDateIndex]['input'] = 'M';
          $pme->fdd[$dueDateIndex]['sql'] = '';
          $pme->fdd[$invoiceItemsDueDateIndex]['input'] = 'HR';
          $pme->fdd[$balancedDateIndex]['input'] = '';
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
          $pme->fdd[$balanceDocumentsFolderIdIndex]['input'] = 'R';
          $pme->fdd[$compositeBalanceDocumentsFolderIdIndex]['input'] = '';
          $pme->fdd[$taxationStatutorySourcesIndex]['input'] = 'M';
          $pme->fdd[$taxationStatutorySourcesLawIndex]['input|LFVD'] = 'R';

          if ($this->copyOperation()) {
            $pme->fdd[$writtenInvoiceIndex]['input'] = 'HR';
            $pme->fdd[$receivableKeyIndex]['select'] = 'D';
            $pme->fdd[$receivableKeyIndex]['input'] = 'M';
            $pme->fdd[$paymentsIdIndex]['input'] = 'RH';

            // Only copy the first receivable
            foreach ([$receivableKeyIndex, $paymentsIdIndex] as $index) {
              $rowIndex = PHPMyEdit::QUERY_FIELD . $index;
              list($row[$rowIndex],) = explode(self::VALUES_SEP, $row[$rowIndex]);
            }
            foreach ([$invoiceItemsAmountIndex, $paymentsSubjectIndex] as $index) {
              $rowIndex = PHPMyEdit::QUERY_FIELD . $index;
              $row[$rowIndex] = null;
            }
          }
        } else {
          $this->debug('COMPONENT ROW');
          $pme->fdd[$receivableKeyIndex]['select'] = 'D';
          $pme->fdd[$receivableKeyIndex]['values']['filters'] = $musicianReceivableFilter;
          $pme->fdd[$paymentsIdIndex]['input'] = 'RH';
          $pme->fdd[$subjectIndex]['input'] = 'HR';
          $pme->fdd[$paymentsSubjectIndex]['input'] = 'M';
          $pme->fdd[$amountIndex]['input'] = 'HR';
          $pme->fdd[$invoiceItemsAmountIndex]['input'] = 'M';
          $pme->fdd[$balancedDateIndex]['input'] = 'HR';
          $pme->fdd[$invoiceDateIndex]['input'] = 'VR';
          $pme->fdd[$invoiceDateIndex]['sql'] = '$column';
          $pme->fdd[$dueDateIndex]['input'] = 'VR';
          $pme->fdd[$dueDateIndex]['sql'] = '$column';
          $pme->fdd[$invoiceItemsDueDateIndex]['input'] = 'VR';
          $pme->fdd[$imbalanceIndex]['input'] = 'HR';
          $pme->fdd[$debitorIdIndex]['input'] = 'R';
          $pme->fdd[$writtenInvoiceIndex]['input'] = 'HR';
          $pme->fdd[$compositeBalanceDocumentsFolderIdIndex]['input'] = 'HR';
          $pme->fdd[$taxationStatutorySourcesIndex]['input'] = 'HR';
          $pme->fdd[$taxationStatutorySourcesLawIndex]['input|LFVD'] = 'HR';

          $pme->fdd[$balanceDocumentsFolderIdIndex]['name'] = $this->l->t('Project Balance');
        }

        // if this payment originated from a scheduled bulk-transaction, then
        // disallow any changes safe the date_of_receipt and adding/changing
        // supporting documents.
        $bulkTransactionId = $row[$this->queryField('sepa_transaction_id')];
        if (!empty($bulkTransactionId)) {
          // make all rows read-only with the exception of some
          foreach ($pme->fdn as $fieldName => $fieldIndex) {
            switch ($fieldName) {
              case 'date_of_receipt':
                $pme->fdd[$fieldIndex]['input'] = str_replace('M', '', $pme->fdd[$fieldIndex]['input']);
                continue 2;
              case 'balance_documents_folder_id':
              case $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'balance_documents_folder_id'):
              case $this->joinTableFieldName(self::DATABASE_STORAGE_DIR_ENTRIES_TABLE, 'id'):
                continue 2;
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
      $pme->fdd[$this->queryFieldIndex('subject')]['input'] = 'HR';
      // $pme->fdd[$this->joinQueryFieldIndex(self::INVOICE_ITEMS_TABLE, 'subject')['input'] = 'HR';
      return true;
    };

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertSanitizeFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteDoDeleteSubPayments' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] =
      function($pme, $op, $step, &$row) {
        if (!$this->listOperation() && !$this->addOperation()) {
          $pme->buttons = $this->pageNavigation->prependTableButtons(buttons: []);
          $menuData = $this->generateActionMenuData($pme->rec['id'], $row);
          foreach (['C', 'P', 'D', 'V'] as $operationMode) {
            foreach (['up', 'down'] as $position) {
              $actionMenu = $this->generateActionMenuToggle($menuData);
              $button = [
                'code' => $actionMenu,
                'name' => 'actions',
              ];
              array_unshift($pme->buttons[$operationMode][$position], $button);
            }
          }
        }
        return true;
      };

    if ($projectMode) {
      // $opts['filters'] = 'FIND_IN_SET('.$this->projectId.', '.$joinTables[self::INVOICE_ITEMS_TABLE].'.project_ids)';
      $opts[PHPMyEdit::OPT_FILTERS] = '$table.project_id = ' . $this->projectId;
    }

    $this->installActionMenuToggle(
      $opts,
      function(array $recordId, array $groupByRecordId, array $row, PHPMyEdit $pme) {
        $rowTag = $row[$this->joinQueryField(self::INVOICE_ITEMS_TABLE, 'row_tag')];
        if (!$this->isCompositeRowTag($rowTag)) {
          return null;
        }
        return $this->generateActionMenuData($recordId['id'], $row);
      },
    );

    $opts = Util::arrayMergeRecursive($this->generateBasePMEOptions(), $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * @param int $entityId
   *
   * @param array $row Legacy DB data provided by PME.
   *
   * @return array
   */
  protected function generateActionMenuData(int $entityId, array $row):array
  {
    $invoiceNumber = $row[$this->queryField('invoice_number')];
    $amount = $row[$this->queryField('amount')];
    $numberFormatter = new NumberFormatter($this->appLocale());
    $l10nAmount = $numberFormatter->formatCurrency($amount);
    $debitorName = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'id')];
    return [
      'amount' => (float)$amount,
      'currencyCode' => $this->currencyCode(),
      'debitorId' => (int)$row[$this->queryField('debitor_id')],
      'debitorName' => $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'id')],
      'entityId' => (int)$entityId,
      'invoiceNumber' => $invoiceNumber,
      'menuCaption' => $invoiceNumber . ' - ' . $debitorName . ' - ' . $l10nAmount,
      'originatorId' => (int)$row[$this->queryIndexField('originator_id')],
      'originatorName' => $row[$this->queryField('originator_id')],
      'projectId' => (int)$row[$this->queryField('project_id')],
      'projectName' => $row[$this->joinQueryField(self::PROJECTS_TABLE, 'name')],
    ];
  }

  /**
   * Sub-payment aware delete.
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
  public function beforeDeleteDoDeleteSubPayments(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, ?array &$changed, ?array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $paymentIdKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'id');
    $rowTagKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'row_tag');

    if (!$this->isCompositeRowTag($oldValues[$rowTagKey])) {
      $paymentId = $oldValues[$rowTagKey] ?? $oldValues[$paymentIdKey];
      $this->setDatabaseRepository(Entities\InvoiceItem::class);
      $this->remove($paymentId, true);
    } else {
      $this->setDatabaseRepository(Entities\Invoice::class);
      return $this->beforeDeleteSimplyDoDelete($pme, $op, $step, $oldValues, $changed, $newValues);
    }

    $changed = [];
    return true;
  }

  /**
   * Remap the input values in order to satisfy the data-model:
   *
   * - one receivable per InvoiceItem
   * - Invoice amount must be sum of all partial payments
   * - Invoice subject is constructed from individual payments
   *
   * However, on insert we only add a single "split" transaction. Further
   * parts have to be added afterwards.

   // update for composite

     BEFORE OLDVALS Array (
     [id] => 514
     [debitor_id] => 407
     [sepa_transaction_id] =>
     [InvoiceItems:row_tag] => 0;514
     [Musicians:id] => 407
     [amount] => 15.00
     [InvoiceItems:amount] => 55.17,45.00
     [date_of_receipt] => 2022-01-13
     [subject] => asfasfasfa
     [InvoiceItems:subject] => sadfgdsafasd,sdafdasfas
     [InvoiceItems:id] => 976,978
     [ProjectParticipantFieldsDataOptions:composite_key] => 18-224-82fad011-04a6-11ec-9e3f-04e261401ed5,18-224-82fb239c-04a6-11ec-9e3f-04e261401ed5 )

     BEFORE NEWVALS Array (
     [id] => 514
     [debitor_id] => 407
     [sepa_transaction_id] =>
     [InvoiceItems:row_tag] => 0;514
     [Musicians:id] => 407
     [amount] => 15.00
     [InvoiceItems:amount] => 55.17,45.00
     [date_of_receipt] => 2022-01-13 00:00:00
     [subject] => asfasfasfa
     [InvoiceItems:subject] => sadfgdsafasd,sdafdasfas
     [InvoiceItems:id] => 976,978
     [ProjectParticipantFieldsDataOptions:composite_key] => 18-224-82fad011-04a6-11ec-9e3f-04e261401ed5,18-224-82fb239c-04a6-11ec-9e3f-04e261401ed5 )
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

    if (empty($changed)) {
      // don't start manipulations if nothing has changed.
      return true;
    }

    $compositeKey = $newValues[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key')]??null;
    $rowTagKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'row_tag');
    $invoiceItemIdKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'id');

    $debitorId = $newValues[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'id')];
    $oldValues['debitor_id'] =
      $newValues['debitor_id'] = $debitorId;

    if (!$this->isCompositeRowTag($newValues[$rowTagKey])) {
      // current update dialog refers to a split payment

      $this->joinStructure[self::INVOICE_ITEMS_TABLE]['flags'] |= self::JOIN_SINGLE_VALUED;

      // determine our payments id
      $invoiceItemId = $newValues[$rowTagKey] ?? $newValues[$invoiceItemIdKey];
      if (empty($invoiceItemId)) {
        $invoiceItemId = 0; // flag key generation
        $newValues[$invoiceItemIdKey] = $newValues[$rowTagKey] = $invoiceItemId;
      } else {
        $newValues[$invoiceItemIdKey] =
          $newValues[$rowTagKey] =
          $oldValues[$invoiceItemIdKey] =
          $oldValues[$rowTagKey] = $invoiceItemId;
      }

      // extract project-id, field-id, receivable_key from the composite-option-key select
      list($projectId, $fieldId, $receivableKey) = explode(
        self::COMP_KEY_SEP,
        $compositeKey,
        3
      );

      $dataSets = $invoiceItemId === 0 ? [ 'new' ] : [ 'old', 'new' ];
      foreach ($dataSets as $dataSet) {
        ${$dataSet . 'Values'} = array_merge(
          ${$dataSet . 'Values'}, [
            $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'debitor_id') => $debitorId,
            $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'project_id') => $projectId,
            $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'field_id') => $fieldId,
            $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'receivable_key') => $receivableKey,
            $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'invoice_id') => $pme->rec['id'],
          ]);

        // index all values by the key in order to please the
        // PMETableViewBase::beforeUpdateDoUpdateAll() machine
        foreach (${$dataSet . 'Values'} as $key => &$value) {
          if ($key == $invoiceItemIdKey || $key == $rowTagKey) {
            continue;
          }
          if (empty($value)) {
            continue;
          }
          if (str_starts_with($key, self::INVOICE_ITEMS_TABLE . self::JOIN_KEY_SEP)) {
            $value = $invoiceItemId . self::JOIN_KEY_SEP . $value;
          }
        }
      }
      unset($value); // break reference

      $unsetTags = [];
      // handled on the composite-level
      $unsetTags[] = 'written_invoice_id';

      // handled on the composite-level
      $unsetTags[] = 'balance_document_folder_id';

      foreach ($unsetTags as $tag) {
        unset($newValues[$tag]);
        unset($oldValues[$tag]);
        Util::unsetValue($changed, $tag);
      }
    } else {
      // Composite payment

      $newValues['taxation_statutory_source_id'] =
        $newValues[$this->joinTableFieldName(self::TAXATION_STATUTORY_SOURCES_TABLE, 'id')];

      // "row_tag" is used as "column" in $this->joinStructure, so transfer
      // the InvoiceItems ids to that field.
      foreach (['newValues', 'oldValues'] as $dataSet) {
        ${$dataSet}[$rowTagKey] = ${$dataSet}[$invoiceItemIdKey];
      }

      $unsetTags = [];
      // remove written_invoice_id as it is handled separately by direct
      // db manipulation.
      $unsetTags[] = 'written_invoice_id';

      // handled on the split-level
      $unsetTags[] = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'balance_documents_folder_id');

      foreach ($unsetTags as $tag) {
        unset($newValues[$tag]);
        unset($oldValues[$tag]);
        Util::unsetValue($changed, $tag);
      }

      $tag = 'notification_message_id';
      if (in_array($tag, $changed) && !empty($newValues[$tag])) {
        $sentEmailsService = $this->di(SentEmailsService::class);
        /** @var Entities\SentEmail $sentEmail */
        $sentEmail = $sentEmailsService->sentEmailFromMessageId(
          $newValues[$tag],
          persist: true,
          flush: false,
        );
        if ($sentEmail === null) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Unable to find an email message on the imap-server for the given message id "%1$s".',
              $newValues[$tag],
            ),
            context: [
              'message-id' => $newValues[$tag],
            ],
          );
        }
        $sentEmail->setProject($this->project);
      }
    }

    $nullables = [
      'sepa_transaction_id',
      'balance_documents_folder_id',
      'notification_message_id',
      $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'balance_documents_folder_id'),
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

    $field = 'invoice_number';
    if (empty($newValues[$field])) {
      $newValues[$field] = null;
      if (!in_array($field, $changed)) {
        $changed[] = $field;
      }
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    return true;
  }

  /**
   * Remap the input values in order to satisfy the data-model:
   *
   * - one receivable per InvoiceItem
   * - Invoice amount must be sum of all partial invoice item
   * - Invoice subject is constructed from individual payments
   *
   * However, on insert we only add a single "split" transaction. Further
   * parts have to be added afterwards.
   *
   * Copying sub-transactions is supported.
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
  public function beforeInsertSanitizeFields(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $invoiceItemIdKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'id');
    $rowTagKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'row_tag');

    $amountKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'amount');
    $subjectKey = $this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'subject');

    if (!empty($newValues[$rowTagKey]) && !$this->isCompositeRowTag($newValues[$rowTagKey])) {
      // Sub-payment, redirect to change mode

      $compositeKeyKey = $this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key') ;

      // redirect to change operation ...
      $oldValues = $newValues;

      // flag key generation
      $oldValues[$invoiceItemIdKey] = $oldValues[$rowTagKey] = '';
      $newValues[$invoiceItemIdKey] = $newValues[$rowTagKey] = 0;

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
    // InvoiceItems:subject -> subject
    // InvoiceItems:amount -> amount
    // Musicians:id -> musician_id
    // ProjectParticipantFieldsDataOptions:key -> InvoiceItems:receivable_key

    // extract project-id, field-id, receivable_key from the composite-option-key select
    list($projectId, $fieldId, $receivableKey) = explode(
      self::COMP_KEY_SEP,
      $newValues[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE, 'composite_key')],
      3
    );

    $debitorId = $newValues[$this->joinTableFieldName(self::MUSICIANS_TABLE, 'id')];
    $newValues['debitor_id'] = $debitorId;

    $newValues['taxation_statutory_source_id'] =
      $newValues[$this->joinTableFieldName(self::TAXATION_STATUTORY_SOURCES_TABLE, 'id')];

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
    unset($newValues[$this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'invoice_id')]);

    $newValues[$this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'debitor_id')] = $debitorId;
    $newValues[$this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'project_id')] = $projectId;
    $newValues[$this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'field_id')] = $fieldId;
    $newValues[$this->joinTableFieldName(self::INVOICE_ITEMS_TABLE, 'receivable_key')] = $receivableKey;

    $newValues['project_id'] = $projectId;

    // "row_tag" is used as "column" in $this->joinStructure, so transfer
    // the InvoiceItems ids to that field.
    $invoiceItemId =
      $newValues[$invoiceItemIdKey] =
      $newValues[$rowTagKey] = 0;

    // index all values by the key in order to please the
    // PMETableViewBase::beforeUpdateDoUpdateAll() machine
    foreach ($newValues as $key => &$value) {
      if ($key == $invoiceItemIdKey || $key == $rowTagKey) {
        continue;
      }
      if (empty($value)) {
          continue;
      }
      if (strpos($key, self::INVOICE_ITEMS_TABLE . self::JOIN_KEY_SEP) === 0) {
        $value = $invoiceItemId . self::JOIN_KEY_SEP . $value;
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
  public function compositeRowOnly(
    mixed $value,
    string $action,
    int $k,
    array $row,
    array $recordId,
    PHPMyEdit $pme,
  ) {
    return $this->selectiveRowDisplay('composite', $value, $action, $k, $row, $recordId, $pme);
  }

  /**
   * Print only the values for the component row.
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
  public function componentRowOnly(
    mixed $value,
    string $action,
    int $k,
    array $row,
    array $recordId,
    PHPMyEdit $pme,
  ) {
    return $this->selectiveRowDisplay('component', $value, $action, $k, $row, $recordId, $pme);
  }

  /**
   * Decide whether the current row refers to the composite payment or to a "split" invoice-item
   *
   * @param array $row
   *
   * @param PHPMyEdit $pme
   *
   * @return bool
   */
  private function isCompositeRow(array $row, PHPMyEdit $pme):bool
  {
    $rowTag = $row[$this->queryField($this->joinTableMasterFieldName(self::INVOICE_ITEMS_TABLE))];
    if (empty($rowTag)) {
      $this->logException(new \Exception('blah'));
    }
    return $this->isCompositeRowTag($rowTag);
  }

  /**
   * @param string $where
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
   * @return null|string HTML fragment or null.
   */
  private function selectiveRowDisplay(
    string $where,
    mixed $value,
    string $action,
    int $k,
    array $row,
    array $recordId,
    PHPMyEdit $pme,
  ):?string {
    $compositeRow = $this->isCompositeRow($row, $pme);
    $composite = $where === 'composite';
    $component = $where === 'component';
    if (($compositeRow && $composite) || (!$compositeRow && $component)) {
      return (string)$value;
    }
    return '';
  }

  /**
   * Callback-hook for download-link display (view, delete, list)
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
   */
  private function createSupportingDocumentsDownload(
    mixed $value,
    string $action,
    int $k,
    array $row,
    array $recordId,
    PHPMyEdit $pme,
  ):string {
    $debitorId = $row[$this->queryField('debitor_id')];
    if ($this->isCompositeRow($row, $pme)) {
      $receivables = Util::explode(self::VALUES_SEP, $row[PHPMyEdit::QUERY_FIELD . $k.'_idx']);
      // $receivables must contain at least one element.
      $supportingDocument = $row[$this->queryField('written_invoice_id')];
      $supportingDocuments = [];
      if (!empty($supportingDocument) || count($receivables) > 1) {
        $userIdSlug = $row[$this->joinQueryField(self::MUSICIANS_TABLE, 'user_id_slug')];
        if (!empty($supportingDocument)) {
          $supportingDocuments = [ $supportingDocument ];
        }
        foreach ($receivables as $receivable) {
          list($projectId, $fieldId, $optionKey) = explode(self::COMP_KEY_SEP, $receivable, 3);
          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          $fieldDatum = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)->find([
            'field' => $fieldId,
            'project' => $projectId,
            'musician' => $debitorId,
            'optionKey' => $optionKey,
          ]);
          if (empty($fieldDatum)) {
            $this->logError('Cannot find field-datum for musician ' . $debitorId . ' and option-key ' . $optionKey);
            continue;
          }
          $document = $fieldDatum->getSupportingDocument();
          if (!empty($document)) {
            $supportingDocuments[] = $document;
          }
          $project = $project??$fieldDatum->getProject();
        }
        // $dateOfReceipt = $row[$this->queryField('date_of_receipt')];
        // $subject = Util::dashesToCamelCase($row[$this->queryField('subject')], capitalizeFirstCharacter: true, dashes: ' _-');

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
        return '<div class="flex-container"><span class="pme-cell-prefix">'
          . $filesAppAnchor
          . '</span><span class="pme-cell-content">'
          . '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService['invoice-items:receivable:document'].'"
   href="'.$downloadLink.'">'
          . '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">' . $value . '</div></div>'
          . '</a></span></div>';
      }
    }

    // fall-through, single or no supporting document
    $receivable = $row[PHPMyEdit::QUERY_FIELD . $k.'_idx'];
    list($projectId, $fieldId, $optionKey) = explode(self::COMP_KEY_SEP, $receivable, 3);

    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    $fieldDatum = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)->find([
      'field' => $fieldId,
      'project' => $projectId,
      'musician' => $debitorId,
      'optionKey' => $optionKey,
    ]);
    if (empty($fieldDatum)) {
      $this->logError('Cannot find field-datum for musician ' . $debitorId . ' and option-key ' . $optionKey);
      return $value;
    }
    $filesAppAnchor = $this->getFilesAppAnchor($fieldDatum->getField(), $fieldDatum->getMusician());
    $fileInfo = $this->projectService->participantFileInfo($fieldDatum);
    $valueHtml = '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer one-liner">' . $value . '</div></div>';

    if (!empty($fileInfo)) {
      $downloadLink = $this->databaseStorageUtil->getDownloadLink($fileInfo['dirEntry']);
      $downloadAnchor = '<a class="download-link ajax-download tooltip-auto"
   title="'.$this->toolTipsService['invoice-items:receivable:document'].'"
   href="'.$downloadLink.'">' . $valueHtml . '</a>';
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
