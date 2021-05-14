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
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Common\Util;

/** TBD. */
class InstrumentInsurances extends PMETableViewBase
{
  const TEMPLATE = 'instrument-insurance';
  const TABLE = self::INSTRUMENT_INSURANCES_TABLE;
  const BROKER_TABLE = 'InsuranceBrokers';
  const RATES_TABLE = 'InsuranceRates';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\InstrumentInsurance::class,
    ],
    // [
    //   'table' => self::MUSICIANS_TABLE,
    //   'entity' => Entities\Musician::class,
    //   'identifier' => [ 'id' => 'musician_id' ],
    //   'column' => 'id',
    // ],
    // [
    //   'table' => self::MUSICIANS_TABLE.parent::VALUE_TABLE_SEP.'BillToParty',
    //   'entity' => Entities\Musician::class,
    //   'identifier' => [ 'id' => 'bill_to_party_id' ],
    //   'column' => 'id',
    // ],
    // [
    //   'table' => self::BROKERS_TABLE,
    //   'entity' => Entities\InsuranceBroker::class,
    //   'identifier' => [ 'short_name' => 'broker_id' ],
    //   'column' => 'short_name',
    // ],
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
      'identifier' => [
        'instrument_holder_id' => 'instrument_holder_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
  ];

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project */
  private $project = null;

  /** @var array<Types\EnumGeographicalScope> */
  private $geographicalScopes;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , InstrumentInsuranceService $insuranceService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);

    $this->insuranceService = $insuranceService;

    $this->projectName = $this->getClubMembersProjectName();
    $this->projectId = $this->getClubMembersProjectId();

    $scopes = array_values(Types\EnumGeographicalScope::toArray());

    $this->scopeNames = [];
    foreach ($scopes as $tag) {
      $this->scopeNames[$tag] = $this->l->t($tag);
    }
  }

  public function shortTitle()
  {
    return $this->l->t('Overview over the Bulk Instrument Insurances');
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $musicianId      = $this->musicianId;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['css']['postfix'] = [
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
    $opts['sort_field'] = [ 'broker', 'geographical_scope', 'musician_id', 'accessory', ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACVDFM';
    $opts['misc']['css']['major'] = 'debit-note';
    $opts['misc']['css']['minor'] = 'debit-note insurance tooltip-bottom';
    $opts['labels']['Misc'] = $this->l->t('Debit');

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
          'tooltip' => $this->toolTipsService['insurences-miscinfo-tab'],
          'name' => $this->l->t('Miscellaneous Data'),
        ],
        [
          'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];

    if ($musicianId > 0) {
      $opts['filters'] = "$table.musician_id = ".$musicianId;
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

    $joinTables = $this->defineJoinStructure($opts);

    $joinTables[self::MUSICIANS_TABLE] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['instrument_holder_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Musician'),
      'css'      => [ 'postfix' => ' musician-id' ],
      'select'   => 'D',
      'maxlen'   => 11,
      'sort'     => true,
      'default' => 0,
      'values' => [
        'table' => self::MUSICIANS_TABLE,
        'column' => 'id',
        'description' => parent::musicianPublicNameSql(),
        'join' => ' $join_col_fqn = $main_table.instrument_holder_id',
        'filters' => parent::musicianInProjectSql($this->projectId),
      ],
    ];

    $joinTables[self::MUSICIANS_TABLE.parent::VALUES_TABLE_SEP.'BillToParty'] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['bill_to_party_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Bill-to Party'),
      'css'      => [ 'postfix' => ' bill-to-party allow-empty' ],
      'select'   => 'D',
      'maxlen'   => 11,
      'sort'     => true,
      'default' => 0,
      'values' => [
        'table' => self::MUSICIANS_TABLE,
        'column' => 'id',
        'description' => parent::musicianPublicNameSql(),
        'join' => ' $join_col_fqn = $main_table.bill_to_party_id',
        'filters' => parent::musicianInProjectSql($this->projectId),
      ],
    ];

    $joinTables[self::BROKER_TABLE] = 'PMEjoin'.count($opts['fdd']);
    $opts['fdd']['broker_id'] = [
      'tab'      => [ 'id' => 'overview'],
      'name'     => $this->l->t('Insurance Broker'),
      'css'      => [ 'postfix' => ' broker-select' ],
      'select'   => 'D',
      'maxlen'   => 384,
      'sort'     => true,
      'default'  => '',
      'values' => [
        'table' => 'SELECT
  b.*,
  GROUP_CONCAT(DISTINCT r.geographical_scope ORDER BY r.geographical_scope ASC) AS geographical_scopes
FROM '.self::BROKER_TABLE.' b
LEFT JOIN '.self::RATES_TABLE.' r
  ON r.broker_id = b.short_name
GROUP BY b.short_name',
        'column' => 'short_name',
        'description' => [
          'columns' => [ 'long_name', 'address' ],
          'divs' => ' / ',
        ],
        'join' => '$join_col_fqn = $main_table.broker_id',
        'data' => '$table.geographical_scopes',
      ],
    ];

    $opts['fdd']['geographical_scope'] = [
      'tab'      => [ 'id' => 'overview' ],
      'name'     => $this->l->t('Geographical Scope'),
      'css'      => [ 'postfix' => ' scope-select' ],
      'select'   => 'D',
      'maxlen'   => 384,
      'sort'     => true,
      'default'  => '',
      'values2'  => $this->scopeNames,
    ];

    $opts['fdd']['object'] = [
      'tab'      => [ 'id' => [ 'item', 'overview' ]],
      'name'     => $this->l->t('Insured Object'),
      'css'      => [ 'postfix' => ' insured-item' ],
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
    ];

    $opts['fdd']['accessory'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Accessory'),
      'css'      => [ 'postfix' => ' accessory' ],
      'select'   => 'O',
      'sort'     => true,
      'default' => false,
      'sqlw' => 'IF($val_qas = "", 0, 1)',
      'values2' => [ 0 => '', 1 => '&#10004;' ],
      'values2|CAP' => [ 0 => '', 1 => '' ],
    ];

    $opts['fdd']['manufacturer'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Manufacturer'),
      'css'      => [ 'postfix' => ' manufacturer' ],
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
    ];

    $opts['fdd']['year_of_construction'] = [
      'tab'      => [ 'id' => 'item' ],
      'name'     => $this->l->t('Year of Construction'),
      'css'      => [ 'postfix' => ' construction-year' ],
      'select'   => 'T',
      'maxlen'   => 20,
      'sort'     => true,
    ];

    $opts['fdd']['insurance_amount'] = array_merge(
      $this->defaultFDD['money'],
      [
        'tab'  => [ 'id' => 'finance' ],
        'name' => $this->l->t('Insurance Amount'),
        'css'  => [ 'postfix' => ' amount align-right' ],
        'php|LFPDV' => [$this, 'moneyValue' ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::RATES_TABLE, 'rate',
      [
        'tab'  => [ 'id' => 'finance' ],
        'css' => [ 'postfix' => ' align-right' ],
        'name' => $this->l->t('Insurance Rate'),
        'options' => 'LFACPDV',
        'sql' => '$join_table.rate',
        'php' => function($rate) {
          return $this->floatValue((float)$rate*100.0).' %';
        }
      ]);

    $opts['fdd']['insurance_fee'] = [
      'tab'  => [ 'id' => 'finance' ],
      'input' => 'V',
      'css' => [ 'postfix' => ' align-right' ],
      'name' => $this->l->t('Insurance Fee').'<br/>'.$this->l->t('including taxes'),
      'options' => 'LFACPDV',
      'sql' => 'ROUND($table.insurance_amount
 * '.$joinTables[self::RATES_TABLE].'.rate
 * (1+'.floatval(InstrumentInsuranceService::TAXES).'), 2)',
      'php' => [ $this, 'moneyValue' ],
    ];

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
      'sort' =>false,
      'php' => function($totalAmount, $action, $k, $row, $recordId, $pme) {
        $musicianId = $row[$this->queryField('instrument_holder_id', $pme->fdd)];
        $annualFee = $this->insuranceService->insuranceFee($musicianId, null, true);
        $bval = $this->l->t(
          'Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;', [ $totalAmount, $annualFee ]);
        $tip = $this->toolTipsService['musician-instrument-insurance'];
        $button = "<div class=\"musician-instrument-insurance\">"
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

    $opts['fdd']['start_of_insurance'] = array_merge(
      $this->defaultFDD['date'],
      [
        'tab'  => [ 'id' => 'overview' ],
        'name' => $this->l->t('Start of Insurance'),
      ]);

    $opts['fdd']['bill'] = [
      'tab'   => [ 'id' => 'tab-all' ],
      'name'  => $this->l->t('Bill'),
      'css'   => [ 'postfix' => ' instrument-insurance-bill' ],
      'input' => 'VR',
      'sql'   => '$main_table.bill_to_party_id',
      'sort'  => false,
      'php' => function($musicianId, $op, $field, $row, $recordId, $pme) {
        $post = [
          'musicianId' => $musicianId,
          'insuranceId' => $recordId,
          'requesttoken' => \OCP\Util::callRegister(),
        ];
        $actions = [
          'bill' => [
            'label' => $this->l->t('bill'),
            'post'  => json_encode($post),
            'title' => $this->toolTipsService['instrument-insurance-bill'],
          ],
        ];
        $html = '';
        foreach ($actions as $key => $action) {
          $html .=<<<__EOT__
<li class="nav tooltip-left inline-block ">
  <a class="nav {$key}"
     href="#"
     data-post='{$action['post']}'
     {$action['properties']}
     title="{$action['title']}">
{$action['label']}
  </a>
</li>
__EOT__;
        }
          return $html;
        }
    ];

    //
    //
    ///////////////////////////////////////////////////////////////////////////

    // redirect all updates through Doctrine\ORM.
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertDoInsertAll' ];

    // merge default options

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
