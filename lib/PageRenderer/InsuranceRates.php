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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Instruments table. */
class InsuranceRates extends PMETableViewBase
{
  const TEMPLATE = 'insurance-rates';
  const TABLE = 'InsuranceRates';
  const BROKER_TABLE = 'InsuranceBrokers';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\InsuranceRate::class,
    ],
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);

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
    return $this->l->t('Instrument Insurance Rates');
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
    $opts['key'] = [ 'broker_id' => 'string', 'geographical_scope' => 'string', ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'broker_id', 'geographical_scope', ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVD';
    $sort = false; // too few entries

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'UG';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      //'sort'  => true,
      'time'  => true,
      'tabs'  => false,
      'navigation' => 'VCPD'
    ];

    // field definitions

    $opts['fdd']['broker_id'] = [
      'name'     => $this->l->t('Broker'),
      'css'      => [ 'postfix' => [ 'broker', 'squeeze-subsequent-lines', ], ],
      'select'   => 'D',
      'maxlen'   => 128,
      'sort'     => $sort,
      'values'   => [
        'table' => self::BROKER_TABLE,
        'column' => 'short_name',
        'description' => [
          'columns' => [ 'long_name', 'REPLACE($table.address, "\n", "<br/>")' ],
          'divs' => '<br/>',
          'cast' => [ false, false ],
        ],
        'join' => '$join_col_fqn = $main_table.broker_id',
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

    $opts['fdd']['geographical_scope'] = [
      'name'        => $this->l->t('Scope'),
      'css'         => [ 'postfix' => ' scope' ],
      'select'      => 'D',
      'maxlen'      => 137,
      'sort'        => $sort,
      'values2'      => $this->scopeNames,
    ];

    $opts['fdd']['rate'] = [
      'name'     => $this->l->t('Rate'),
      'css'      => [ 'postfix' => ' rate' ],
      'select'   => 'N',
      'maxlen'   => 11,
      'default'  => 0.0,
      'sort'     => $sort,
      'display' => [
        'attributes' => [
          'step' => '0.0001',
        ],
      ],
    ];

    $opts['fdd']['due_date'] = $this->defaultFDD['due_date'];

    $opts['fdd']['policy_number'] = [
      'name' => $this->l->t('Policy Number'),
      'css' => [ 'postfix' => ' policy' ],
      'select' => 'T',
      'maxlen' => 127,
      'sort' => $sort
    ];

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];
    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
