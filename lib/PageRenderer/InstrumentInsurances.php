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

use DateTime;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceReceivablesGenerator;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Common\Uuid;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Common\Util;

/** Render the instrument insurances of the club-members. */
class InstrumentInsurances extends PMETableViewBase
{
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;

  const TEMPLATE = 'instrument-insurance';
  const TABLE = self::INSTRUMENT_INSURANCES_TABLE;
  const BROKER_TABLE = 'InsuranceBrokers';
  const RATES_TABLE = 'InsuranceRates';
  const MEMBERSHIP_TABLE = self::PROJECT_PARTICIPANTS_TABLE . self::VALUES_TABLE_SEP . 'memberShip';
  const BILL_TO_PARTY_TABLE = self::MUSICIANS_TABLE . self::VALUES_TABLE_SEP . 'billToParty';
  const INSTRUMENT_OWNER_TABLE = self::MUSICIANS_TABLE . self::VALUES_TABLE_SEP . 'instrumentOwner';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\InstrumentInsurance::class,
    ],
    self::RATES_TABLE => [
      'entity' => Entities\InsuranceRate::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'broker_id' => 'broker_id',
        'geographical_scope' => 'geographical_scope',
      ],
      'column' => 'rate',
    ],
    // for summing stuff up
    self::TABLE . self::VALUES_TABLE_SEP . 'allItems' => [
      'entity' => Entities\InstrumentInsurance::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'instrument_holder_id' => 'instrument_holder_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // in order to trace missing member-ship
    self::MEMBERSHIP_TABLE => [
      'entity' => Entities\ProjectParticipant::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => [
          'value' => 'FILL_ME',
        ],
        'musician_id' => 'bill_to_party_id',
      ],
      'column' => 'musician_id',
    ],
  ];

  /** @var Entities\Project */
  private $project = null;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var ProjectService */
  private $projectService;

  /** @var UserStorage */
  private $userStorage;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    InstrumentInsuranceService $insuranceService,
    ProjectService $projectService,
    UserStorage $userStorage,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);

    $this->showDisabled = true; // otherwise it is too confusing.

    $this->insuranceService = $insuranceService;
    $this->projectService = $projectService;
    $this->userStorage = $userStorage;

    $this->projectId = $this->getClubMembersProjectId();
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      $this->projectName = $this->project->getName();
    } else {
      $this->projectName = $this->getClubMembersProjectName();
    }

    $scopes = array_values(Types\EnumGeographicalScope::toArray());

    $this->scopeNames = [];
    foreach ($scopes as $tag) {
      $this->scopeNames[$tag] = $this->l->t($tag);
    }
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    return $this->l->t('Overview over the Bulk Instrument Insurances');
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $recordsPerPage  = $this->recordsPerPage;

    $opts            = [];

    $opts['css']['postfix'] = [
      'direct-change',
      'show-hide-disabled',
      $this->cssClass(),
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
      'projectId' => $this->projectId,
      'projectName' => $this->projectName,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'id' => 'int' /* , 'bill_to_party_id' => 'int' */ ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'broker_id', 'geographical_scope', 'instrument_holder_id', 'accessory', ];

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

    // controls display an location of edit/misc buttons
    $opts['navigation'] = self::PME_NAVIGATION_MULTI;

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [
          'id' => 'overview',
          'default' => true,
          'tooltip' => $this->l->t('Insurance brief, item, musician, broker'),
          'name' => $this->l->t('Overview'),
        ],
        [
          'id' => 'item',
          'tooltip' => $this->l->t('Details about the insured item'),
          'name' => $this->l->t('Insured Object'),
        ],
        [
          'id' => 'finance',
          'tooltip' => $this->l->t('Insurance rate and total sum of insurance police'),
          'name' => $this->l->t('Insurance Amount'),
        ],
        [
          'id' => 'miscinfo',
          'tooltip' => $this->toolTipsService['page-renderer:miscinfo-tab'],
          'name' => $this->l->t('Miscellaneous Data'),
        ],
        [
          'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];

    if ($this->musicianId > 0) {
      $opts['filters'] = '$table.instrument_holder_id = ' . $this->musicianId;
    }

    $opts['groupby_fields'] = [ 'id' ];

    ///////////////////////////////////////////////////////////////////////////
    //
    // Field definitions
    //

    $opts['fdd']['id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Insurance Id'),
      'input'    => 'R',
      'input|AP' => 'RH',
      'options'  => 'AVCPD',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true
    ];

    $this->joinStructure[self::MEMBERSHIP_TABLE]['identifier']['project_id']['value'] = $this->projectId;
    $joinTables = $this->defineJoinStructure($opts);

    $joinTables[self::MUSICIANS_TABLE] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['instrument_holder_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Musician'),
      'css'      => [ 'postfix' => [ 'musician-id', 'instrument-holder', ], ],
      'select'   => 'D',
      'maxlen'   => 11,
      'sort'     => true,
      'default' => 0,
      'values' => [
        'table' => self::MUSICIANS_TABLE,
        'column' => 'id',
        'description' => self::trivialDescription(self::musicianPublicNameSql()),
        'join' => ' $join_col_fqn = $main_table.instrument_holder_id',
        // 'filters' => parent::musicianInProjectSql($this->projectId),
      ],
      'display' => [
        'prefix' => function($op, $pos, $k, $row, $pme) {
          $css = [ 'cell-wrapper' ];
          if ($op != 'add') {
            $instrumentHolder = $row[$this->queryIndexField('instrument_holder_id', $pme->fdd)];
            $billToParty = $row[$this->queryIndexField('bill_to_party_id', $pme->fdd)];
            $instrumentOwner = $row[$this->queryIndexField('instrument_owner_id', $pme->fdd)];
            $isClubMember = $row[$this->joinQueryField(self::MEMBERSHIP_TABLE, 'project_id', $pme->fdd)];

            empty($billToParty) && $billToParty = $instrumentHolder;
            empty($instrumentOwner) && $instrumentOwner = $instrumentHolder;

            $css[] = $isClubMember ? 'is-club-member' : 'not-a-club-member';
            $css[] = $instrumentHolder == $billToParty ? 'is-bill-to-party' : 'not-the-bill-to-party';
            $css[] = $instrumentHolder == $instrumentOwner ? 'is-instrument-owner' : 'not-the-instrument-owner';
          }
          return '<div class="' . implode(' ', $css) . '">';
        },
        'postfix' => '</div>',
      ],
      'tooltip' => $this->toolTipsService['page-renderer:instrument-insurances:instrument-holder'],
    ];

    $joinTables[self::BILL_TO_PARTY_TABLE] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['bill_to_party_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Bill-to Party'),
      'css'      => [ 'postfix' => [ 'bill-to-party', 'allow-empty' ], ],
      'select'   => 'D',
      'maxlen'   => 11,
      'sort'     => true,
      'default' => 0,
      'values' => [
        'table' => self::MUSICIANS_TABLE,
        'column' => 'id',
        'description' => self::trivialDescription(self::musicianPublicNameSql()),
        'join' => ' $join_col_fqn = $main_table.bill_to_party_id',
        //'filters' => parent::musicianInProjectSql($this->projectId),
      ],
      'display' => [
        'prefix' => function($op, $pos, $k, $row, $pme) {
          $css = [ 'cell-wrapper', 'tooltip-auto' ];
          $title = '';
          if ($op != 'add') {
            $instrumentHolder = $row[$this->queryIndexField('instrument_holder_id', $pme->fdd)];
            $billToParty = $row[$this->queryIndexField('bill_to_party_id', $pme->fdd)];
            $instrumentOwner = $row[$this->queryIndexField('instrument_owner_id', $pme->fdd)];
            $isClubMember = $row[$this->joinQueryField(self::MEMBERSHIP_TABLE, 'project_id', $pme->fdd)];

            empty($billToParty) && $billToParty = $instrumentHolder;
            empty($instrumentOwner) && $instrumentOwner = $instrumentHolder;

            $css[] = $isClubMember ? 'is-club-member' : 'not-a-club-member';
            $css[] = $billToParty == $instrumentHolder ? 'is-instrument-holder' : 'not-the-instrument-holder';
            $css[] = $billToParty == $instrumentOwner? 'is-instrument-owner' : 'not-the-instrument-owner';
            $title = $isClubMember
              ? ''
              : ' title="' . $this->toolTipsService['instrument-insurance:not-a-club-member'] . '"';
          }
          return '<div class="' . implode(' ', $css) . '"' . $title . '>';
        },
        'postfix' => '</div>',
      ],
      'tooltip' => $this->toolTipsService['page-renderer:instrument-insurances:bill-to-party'],
    ];

    $joinTables[self::INSTRUMENT_OWNER_TABLE] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['instrument_owner_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Owner'),
      'css'      => [ 'postfix' => [ 'instrument-owner', 'allow-empty' ], ],
      'select'   => 'D',
      'maxlen'   => 11,
      'sort'     => true,
      'default' => 0,
      'values' => [
        'table' => self::MUSICIANS_TABLE,
        'column' => 'id',
        'description' => self::trivialDescription(self::musicianPublicNameSql()),
        'join' => ' $join_col_fqn = $main_table.instrument_owner_id',
        //'filters' => parent::musicianInProjectSql($this->projectId),
      ],
      'display' => [
        'prefix' => function($op, $pos, $k, $row, $pme) {
          $css = [ 'cell-wrapper', 'tooltip-auto' ];
          $title = '';
          if ($op != 'add') {
            $instrumentHolder = $row[$this->queryIndexField('instrument_holder_id', $pme->fdd)];
            $billToParty = $row[$this->queryIndexField('bill_to_party_id', $pme->fdd)];
            $instrumentOwner = $row[$this->queryIndexField('instrument_owner_id', $pme->fdd)];
            $isClubMember = $row[$this->joinQueryField(self::MEMBERSHIP_TABLE, 'project_id', $pme->fdd)];

            empty($billToParty) && $billToParty = $instrumentHolder;
            empty($instrumentOwner) && $instrumentOwner = $instrumentHolder;

            $css[] = $instrumentOwner == $billToParty ? 'is-bill-to-party' : 'not-the-bill-to-party';
            $css[] = $instrumentOwner == $instrumentHolder ? 'is-instrument-holder' : 'not-the-instrument-holder';
            $css[] = $isClubMember ? 'is-club-member' : 'not-a-club-member';
          }
          return '<div class="' . implode(' ', $css) . '"' . $title . '>';
        },
        'postfix' => '</div>',
      ],
      'tooltip' => $this->toolTipsService['page-renderer:instrument-insurances:instrument-owner'],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MEMBERSHIP_TABLE, 'project_id',
      [
        'name' => $this->l->t('Club Member'),
        'tab'  => [ 'id' => 'tab-all' ],
        'css' => [ 'postfix' => [ 'align-center', ], ],
        'options' => 'LFACPDV',
        'sql' => 'IF($join_col_fqn IS NULL, 0, 1)',
        'input|LF' => 'RHV',
        'input' => 'RV',
        'select' => 'T',
        'values2' => [
          0 => '',
          1 => '&#10004;',
        ],
        'php|CAP' => function($value) {
          return $value ? '&#10004;' : '';
        },
      ]);

    if ($this->pmeBare) {
      // export mode, export just the short name
      $opts['fdd']['broker_id'] = [
        'name'     => $this->l->t('Insurance Broker'),
        'select'   => 'T',
      ];
    } else {
      $joinTables[self::BROKER_TABLE] = 'PMEjoin'.count($opts['fdd']);
      $opts['fdd']['broker_id'] = [
        'tab'      => [ 'id' => 'overview'],
        'name'     => $this->l->t('Insurance Broker'),
        'css'      => [ 'postfix' => [ 'broker-select', 'squeeze-subsequent-lines', ], ],
        'select'   => 'D',
        'maxlen'   => 384,
        'sort'     => true,
        'default'  => '',
        'values' => [
          'table' => 'SELECT
  b.*,
  GROUP_CONCAT(DISTINCT r.geographical_scope ORDER BY r.geographical_scope ASC) AS geographical_scopes,
  CONCAT("[", GROUP_CONCAT(DISTINCT
    JSON_OBJECT(
      "geographicalScope", r.geographical_scope
      , "rate", r.rate
      , "dueDate", r.due_date
      , "policyNumber", r.policy_number
) ORDER BY r.geographical_scope ASC),"]") AS insurance_rates
FROM '.self::BROKER_TABLE.' b
LEFT JOIN '.self::RATES_TABLE.' r
  ON r.broker_id = b.short_name
GROUP BY b.short_name',
          'column' => 'short_name',
          'description' => [
            'columns' => [ 'long_name', 'REPLACE($table.address, "\n", "<br/>")' ],
            'divs' => '<br/>',
            'cast' => [ false, false ],
          ],
          'join' => '$join_col_fqn = $main_table.broker_id',
          // 'data' => '$table.geographical_scopes',
          'data' => '$table.insurance_rates',
        ],
        'display|LFVD' => [
          'popup' => 'data',
          'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
        ],
      ];
      $opts['fdd']['broker_id']['values|ACP'] = $opts['fdd']['broker_id']['values'];
      $opts['fdd']['broker_id']['values|ACP']['description'] = [
        'columns' => [ 'long_name' ],
        'cast' => [ false ],
      ];
    }

    $opts['fdd']['geographical_scope'] = [
      'tab'      => [ 'id' => 'overview' ],
      'name'     => $this->l->t('Geographical Scope'),
      'css'      => [ 'postfix' => [ 'scope-select', ], ],
      'select'   => 'D',
      'maxlen'   => 384,
      'sort'     => true,
      'default'  => '',
      'values2'  => $this->scopeNames,
    ];

    $instrumentValues = array_values($this->instruments);
    $opts['fdd']['object'] = [
      'tab'      => [ 'id' => [ 'item', 'overview' ]],
      'name'     => $this->l->t('Insured Object'),
      'css'      => [ 'postfix' => [ 'insured-item', ], ],
      'input'    => 'M', // required
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'display' => [
        'attributes' => [
          'data-autocomplete' => array_merge(
            $instrumentValues,
            // array_map(fn($x) => $x . '-' . $this->l->t('bow'), $instrumentValues),
            // array_map(fn($x) => $x . '-' . $this->l->t('case'), $instrumentValues),
          ),
        ],
      ],
    ];

    $opts['fdd']['accessory'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Accessory'),
      'css'      => [ 'postfix' => [ 'accessory', 'align-center', ], ],
      'select|CAP'   => 'O',
      'select|LVDF' => 'T',
      'sort'     => true,
      'default'  => false,
      'escape'   => false,
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
      'values2|LVDF' => [
        0 => '',
        1 => '&#10004;',
      ],
    ];

    $opts['fdd']['manufacturer'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Manufacturer'),
      'tooltip'  => $this->toolTipsService['instrument-insurance:manufacturer'],
      'css'      => [ 'postfix' => [ 'manufacturer', ], ],
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('e.g. Bilbo Beutlin, Hobbiton, The Shire'),
        ],
      ],
      'input'    => 'M', // required
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
    ];

    $yearAutocomplete = range(1900, (new DateTime)->format('Y')+1, 10);
    $yearAutocomplete = array_merge(
      array_map(fn($year) => $this->l->t('around %1$04d', $year), $yearAutocomplete),
      range(2001, (new DateTime)->format('Y')+1)
    );
    for ($century = 15; $century <= (new DateTime)->format('Y') / 100; ++$century) {
      $yearAutocomplete[] = $this->l->t('end of %1$02dth century', $century);
    }
    sort($yearAutocomplete);
    $yearAutocomplete = array_values(array_unique($yearAutocomplete));

    $opts['fdd']['year_of_construction'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Year of Construction'),
      'css'      => [ 'postfix' => [ 'construction-year', ], ],
      'input'    => 'M', // required
      'select'   => 'T',
      'tooltip'  => $this->toolTipsService['instrument-insurance:year-of-construction'],
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('e.g. "1921" or "around 1900"'),
          'data-autocomplete' => $yearAutocomplete,
        ],
      ],
      'maxlen'   => 20,
      'sort'     => true,
    ];

    $opts['fdd']['insurance_amount'] = array_merge(
      $this->defaultFDD['money'],
      [
        'tab'  => [ 'id' => 'finance' ],
        'name' => $this->l->t('Insurance Amount'),
        'css'  => [ 'postfix' => [ 'insurance-amount', 'amount', 'align-right', ], ],
        'input' => 'M', // required
        'display' => [
          'attributes' => [
            'min' => 100,
            'step' => 1,
          ],
        ],
        'php|LFDV' => fn($value) => $this->moneyValue($value),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::RATES_TABLE, 'rate',
      [
        'tab'  => [ 'id' => 'finance' ],
        'css' => [ 'postfix' => [ 'insurance-rate', 'align-right', ], ],
        'name' => $this->l->t('Insurance Rate'),
        'options' => 'LFACPDV',
        'sql' => '$join_col_fqn',
        'php' => function($rate, $op, $k, $row, $rec, $pme) {
          $cssPostfix   = $pme->fdd[$k]['css']['postfix']??[];
          $cssClassName = $pme->getCSSclass('input', null, false, $cssPostfix);
          return
            $pme->htmlHiddenData('insurance_rate', $rate, $cssClassName)
            . '<span class="insurance-rate-display" data-value="'.$rate.'">'
            . $this->floatValue((float)$rate*100.0).' %'
            . '</span';
        },
      ]);

    $opts['fdd']['insurance_fee'] = [
      'tab'  => [ 'id' => 'finance' ],
      'input' => 'V',
      'select' => 'T',
      'css' => [ 'postfix' => [ 'insurance-fee', 'align-right', ], ],
      'name' => $this->l->t('Insurance Fee w/ taxes'),
      'options' => 'LFACPDV',
      'sort' => true,
      'sql' => 'ROUND($table.insurance_amount
 * ' . $joinTables[self::RATES_TABLE] . '.rate
 * (1 + ' . floatval(InstrumentInsuranceService::TAXES) . '), 2)',
      'php' => function($value) {
        return '<span class="insurance-fee-display"
  data-currency-code="' . $this->currencyCode() . '"
  data-tax-rate="' . InstrumentInsuranceService::TAXES . '"
>'
          . $this->moneyValue($value)
          . '</span>';
      },
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::RATES_TABLE, 'due_date', array_merge(
        $this->defaultFDD['date'], [
          'name' => $this->l->t('Due Date'),
          'tab'  => [ 'id' => 'finance' ],
          'css' => [ 'postfix' => [ 'insurance-rate -due-date', ], ],
          // 'sql' => 'DATE_ADD($join_col_fqn, INTERVAL (YEAR(NOW()) - YEAR($join_col_fqn) + 1) YEAR)',
          'sql' => 'DATE_ADD($join_col_fqn, INTERVAL (YEAR(NOW()) - YEAR($join_col_fqn) + 0) YEAR)',
          'input' => 'VR',
        ])
    );

    $allItemsTable = self::TABLE . self::VALUES_TABLE_SEP . 'allItems';
    $this->makeJoinTableField(
      $opts['fdd'], $allItemsTable, 'insurance_amount', [
      'tab'  => [ 'id' => 'finance' ],
      'input' => 'V',
      'name' => $this->l->t('Total Insurance'),
      'select' => 'T',
      'options' => 'CDV',
      'sql' => 'SUM($join_col_fqn)',
      'escape' => false,
      'nowrap' => true,
      'sort' => true,
      'php' => function($totalAmount, $action, $k, $row, $recordId, $pme) {
        $musicianId = $row[$this->queryField('instrument_holder_id', $pme->fdd)];
        $annualFee = $this->insuranceService->insuranceFee($musicianId, null);
        $bval = $this->l->t(
          'Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;', [ $totalAmount, $annualFee ]);
        $tip = $this->toolTipsService['musician-instrument-insurance'];
        $button = "<div class=\"musician-instrument-insurance\">"
                ."<input type=\"button\" "
                ."value=\"$bval\" "
                ."title=\"$tip\" "
                ."name=\""
                ."template=instrument-insurance&amp;"
                ."musicianId=".$musicianId."\" "
                ."class=\"musician-instrument-insurance\" />"
                ."</div>";
        return $button;
      }
      ]);

    $opts['fdd']['start_of_insurance'] = array_merge(
      $this->defaultFDD['date'],
      [
        'tab'  => [ 'id' => 'overview' ],
        'name' => $this->l->t('Start of Insurance'),
        'input' => 'M', // required
      ]);

    if ($this->showDisabled) {
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'tab'  => [ 'id' => 'overview' ],
          'name' => $this->l->t('End of Insurance'),
          'select' => 'T',
          'dateformat' => 'medium',
          'timeformat' => null,
          'css' => [ 'postfix' => [ 'revocation-date', 'date', ], ],
          'tooltip' => $this->toolTipsService['page-renderer:instrument-insurances:deleted'],
        ],
      );
    }

    $insuranceBillSubDir = $this->getSupportingDocumentsPathName();

    $opts['fdd']['bill'] = [
      'tab'   => [ 'id' => 'tab-all' ],
      'name'  => $this->l->t('Bill'),
      'css'   => [ 'postfix' => [ 'instrument-insurance-bill', ], ],
      'input' => 'VR',
      'options' => 'ACVDFM',
      'sql'   => '$main_table.bill_to_party_id',
      'sort'  => false,
      'php|LFCDV' => function($musicianId, $op, $field, $row, $recordId, $pme) use ($insuranceBillSubDir) {

        $musician = $this->findEntity(Entities\Musician::class, $musicianId);
        $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician, dry: true);

        try {
          $filesAppTarget = md5($this->userStorage->getFilesAppLink($participantFolder));
          $filesAppLink = $this->userStorage->getFilesAppLink($participantFolder . $insuranceBillSubDir, subDir: true);
          $filesAppLink = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="'.$this->toolTipsService['page-renderer:instrument-insurances:open-insurance-bills'].'"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
        } catch (\Throwable $t) {
          $this->logException($t, 'Looking for folder ' . $participantFolder . $insuranceBillSubDir);
          $filesAppLink = '';
        }

        $insuranceId = $recordId['id'];
        $requesttoken = \OCP\Util::callRegister();

        $label = $this->l->t('bill');
        $toolTip = $this->toolTipsService['instrument-insurance:bill'];

        $route = implode('.', [ $this->appName(), 'instrument_insurance', 'download', 'get' ]);
        $routeParameters = compact('musicianId', 'insuranceId');
        $downloadLink = $this->urlGenerator()->linkToRoute($route, $routeParameters);
        $downloadLink .= '?' . http_build_query(compact('requesttoken'), '', '&');

        return $filesAppLink . '<a class="download-link ajax-download tooltip-auto" title="' . $toolTip . '" href="' . $downloadLink . '">' . $label . '</a>';
      }
    ];

    //
    //
    ///////////////////////////////////////////////////////////////////////////

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];
    // merge default options

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {

      if (!empty($row[$this->queryField('deleted', $pme->fdd)])) {
        // disable misc-checkboxes for soft-deleted musicians in order to
        // avoid sending them bulk-email.
        $pme->options = str_replace([ 'M', 'D' ], '', $opts['options']);
      } else {
        $pme->options = $opts['options'];
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
   * Cleanup "trigger" which relocates several virtual inputs to their
   * proper destination columns.
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
  public function beforeUpdateOrInsertTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, ?array &$changed, ?array &$newValues):bool
  {
    if ($op === PHPMyEdit::SQL_QUERY_INSERT) {
      // populate the empty $oldValues array with null in order to have
      // less undefined array key accesses.
      $oldValues = array_fill_keys(array_keys($newValues), null);
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    $changed = array_values(array_unique($changed));

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    $this->changeSetSize = count($changed);

    return true;
  }

  /**
   * Find the insurances field. Its name determines the folder in the participants storage.
   *
   * @return null|string
   */
  private function getSupportingDocumentsPathName():?string
  {
    if (empty($this->projectId)) {
      return null;
    }

    /** @var Entities\ProjectParticipantField $insuranceField */
    $insuranceField = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->findOneBy([
      'project' => $this->projectId,
      'multiplicity' => Types\EnumParticipantFieldMultiplicity::RECURRING,
      'dataType' => Types\EnumParticipantFieldDataType::SERVICE_FEE,
      'dataOptions.key:uuid_binary' => Uuid::NIL,
      'dataOptions.data' => InstrumentInsuranceReceivablesGenerator::class,
    ]);

    if (empty($insuranceField)) {
      return null;
    }

    $fieldFolderPath = $this->projectService->getParticipantFieldFolderPath($insuranceField, includeDeleted: $this->showDisabled);

    if (empty($fieldFolderPath)) {
      return null;
    }

    return UserStorage::PATH_SEP . $this->getDocumentsFolderName()
      . UserStorage::PATH_SEP . $fieldFolderPath;
  }
}
