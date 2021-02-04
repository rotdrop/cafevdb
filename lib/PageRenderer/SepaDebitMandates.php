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
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\Entities;

use OCA\CAFEVDB\Common\Util;

/** TBD. */
class SepaDebitMandates extends PMETableViewBase
{
  const TABLE = 'SepaDebitMandates';
  const PROJECTS_TABLE = 'Projects';
  const MUSICIANS_TABLE = 'Musicians';
  const PAYMENTS_TABLE = 'ProjectPayments';

  protected $cssClass = 'sepa-debit-mandates';

  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'master' => true,
      'entity' => Entities\InsuranceRate::class,
    ],
    [
      'table' => self::PROJECTS_TABLE,
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    [
      'table' => self::MUSICIANS_TABLE,
      'entity' => Entities\Musician::class,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
    ],
    [
      'table' => self::PAYMENTS_TABLE,
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'mandate_sequence' => 'sequence',
      ],
    ],
  ];

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove this Debit-Mandate?');
    } else if ($this->viewOperation()) {
      if ($this->projectId > 0 && $this->projectName != '') {
        return $this->l->t('Debit-Mandate for %s', array($this->projectName));
      } else {
        return $this->l->t('Debit-Mandate');
      }
    } else if ($this->changeOperation()) {
      return $this->l->t('Change this Debit-Mandate');
    }
    if ($this->projectId > 0 && $this->projectName != '') {
      return $this->l->t('Overview over all SEPA Debit Mandates for %s',
                  array($this->projectName));
    } else {
      return $this->l->t('Overview over all SEPA Debit Mandates');
    }
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
    $memberProjectId = $this->getConfigValue('memberTableId');

    $projectMode = $projectId > 0 && !empty($projectName);

    $opts            = [];

    $opts['css']['postfix'] = 'direct-change show-hide-disabled';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $template = 'sepa-debit-mandates';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'musician_id' => 'int', 'sequence' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'musician_id', 'project_id', 'sequence' ];

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

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    $buttons = [];
    if ($projectMode) {
      $debitJob = $this->requestParameters->getParam('debit-job', '');
      $debitAmount = $this->requestParameters->getParam('debit-note-amount', 0);
      $debitSubject = $this->requestParameters->getParam('debit-note-subject', '');

      $debitJobs = '
<span id="pme-debit-note-job" class="pme-debit-note-job pme-menu-block">
  <select data-placeholder="'.$this->l->t('Debit Job').'"
          class="pme-debit-note-job'.' '.($debitJob === 'amount' ? 'custom' : 'predefined').'"
          title="'.Config::toolTips('debit-note-job-choice').'"
          name="debit-job">
    <option value=""></option>';

      if ($projectId === $memberProjectId) {
        $jobOptions = [
          [
            'value' => 'membership-fee',
            'name' => $this->l->t('Membership Fee'),
            'titile' => Config::toolTips('debit-note-job-option-membership-fee'),
            'flags' => ($debitJob === 'membership-fee' ? Navigation::SELECTED : 0),
          ],
          [
            'value' => 'insurance',
            'name' => $this->l->t('Insurance'),
            'titile' => Config::toolTips('debit-note-job-option-insurance'),
            'flags' => ($debitJob === 'insurance' ? Navigation::SELECTED : 0),
          ],
          [
            'value' => 'amount',
            'name' => $this->l->t('Amount'),
            'titile' => Config::toolTips('debit-note-job-option-amount'),
            'flags' => ($debitJob === 'amount' ? Navigation::SELECTED : 0),
          ]
        ];
      } else {
        $jobOptions = [
          [
            'value' => 'deposit',
            'name' => $this->l->t('Deposit'),
            'titile' => Config::toolTips('debit-note-job-option-deposit'),
            'flags' => ($debitJob === 'deposit' ? Navigation::SELECTED : 0),
          ],
          [
            'value' => 'remaining',
            'name' => $this->l->t('Remaining'),
            'titile' => Config::toolTips('debit-note-job-option-remaining'),
            'flags' => ($debitJob === 'remaining' ? Navigation::SELECTED : 0),
          ],
          [
            'value' => 'amount',
            'name' => $this->l->t('Amount'),
            'titile' => Config::toolTips('debit-note-job-option-amount'),
            'flags' => ($debitJob === 'amount' ? Navigation::SELECTED : 0),
          ],
        ];
      }
      $debitJobs .= $this->pageNavigation->selectOptions($jobOptions);
      $debitJobs .= '
  </select>
  <input type="text"
         class="debit-note-amount"
         value="'.$debitAmount.'"
         name="debit-note-amount"
         placeholder="'.$this->l->t('amount').'"/>
  <input type="text"
         class="debit-note-subject"
         value="'.$debitSubject.'"
         name="debit-note-subject"
         placeholder="'.$this->l->t('subject').'"/>
</span>';
      $buttons[] = [ 'code' =>  $debitJobs ];
    }
    $buttons[] = $this->pageNavigation->tableExportButton();

    $opts['buttons'] = $this->pageNavigation->prependTableButtons($buttons, true);

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [
          'id' => 'mandate',
          'tooltip' => $this->l->t('Debit mandate, mandate-id, last used date, recurrence'),
          'name' => $this->l->t('Mandate'),
        ],
        [
          'id' => 'account',
          'tooltip' => $this->l->t('Bank account associated to this debit mandate.'),
          'name' => $this->l->t('Bank Account'),
        ],
      ],
      'navigation' => 'VCD', // 'VCPD',
    ];
    $amountTab = [
      'id' => 'amount',
      'tooltip' => $this->l->t('Show the amounts to draw by debit transfer, including sum of payments
received so far'),
      'name' => $this->l->t('Amount')
    ];
    $projectTab = [
      'id' => 'project',
      'tooltip' => $this->l->t('Show project specific data'),
      'name' => $this->l->t('Project'),
    ];
    $allTab = [
      'id' => 'tab-all',
      'tooltip' => Config::toolTips('pme-showall-tab'),
      'name' => $this->l->t('Display all columns'),
    ];
    if ($projectMode && $projectId !== $memberProjectId) {
      $opts['display']['tabs'][] = $projectTab;
      $opts['display']['tabs'][] = $amountTab;
    }
    $opts['display']['tabs'][] = $allTab;

    //////////////////////////////////////////////////////

    // TODO id fields

    $opts['fdd']['project_id'] = [
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      ];

    $opts['fdd']['musician_id'] = [
      'name'     => $this->l->t('Musician-Id'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    ];

    $opts['fdd']['sequence'] = [
      'name'     => $this->l->t('Mandate-Sequence'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    ];

    $joinTables = $this->defineJoinStructure($opts);

    // field definitions

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'tab'      => [ 'id' => 'tab-all' ],
        'name'     => $this->l->t('Musician'),
        'input'    => 'R',
        'select'   => 'T',
        'maxlen'   => 11,
        'sort'     => true,
        'values' => [
          'description' => [
            'columns' => [ 'sur_name', 'first_name', ],
            'divs' => ', ',
          ],
        ],
      ]);

    $opts['fdd']['mandateReference'] = [
      'tab'    => [ 'id' => 'mandate' ],
      'name'   => $this->l->t('Mandate Reference'),
      'input'  => 'R',
      'select' => 'T',
      'maxlen' => 35,
      'sort'   => true,
    ];

    // soft-deletion
    $opts['fdd'][] = $this->defaultFDD['deleted_at'];

    $opts['fdd']['mandateDate'] = [
      'name'     => $this->l->t('Date Issued'),
      'select'   => 'T',
      'maxlen'   => 10,
      'sort'     => true,
      'css'      => [ 'postfix' => ' sepadate' ],
      'datemask' => 'd.m.Y',
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::PAYMENTS_TABLE, 'date_of_receipt',
      [
        'name'     => $this->l->t('Last-Used Date'),
        'input'    => 'VR',
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
        'css'      => [ 'postfix' => ' last-used-date' ],
        'datemask' => 'd.m.Y'
      ]);

    ///////////////

    // @TODO EXTRA FIELD STUFF / PROJECT MODE

    ///////////////

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      Util::arrayMergeRecursive(
        [
          'tab' => [ 'id' => 'mandate' ],
          'name'     => $this->l->t('Project'),
          'input'    => 'R',
          'select'   => 'D',
          'maxlen'   => 11,
          'sort'     => true,
          //'options'  => 'LFADV', // no change allowed
          'default' => 0,
          'css'      => [ 'postfix' => ' mandate-project' ],
          'values' => [
            'description' => [
              'columns' => [ 'year' => 'year', 'name' => 'name' ],
              'divs' => [ 'year' => ': ' ]
            ],
          ],
        ],
        $projectMode
        ? array_merge(
          [ 'values' => [ 'filters' => '$table.Id in ('.$projectId.','.$memberProjectId.')' ],],
          ($projectId === $memberProjectId)
          ? [
            'select' => 'T',
            'sort' => false,
            'maxlen' => 40,
            'options' => 'VPCDL',
          ]
          : []
        )
        : []
      ));

    // couple of "easy" fields

    $opts['fdd']['iban'] = [
      'tab' => array('id' => 'account'),
      'name'   => 'IBAN',
      'options' => 'LACPDV',
      'select' => 'T',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    $opts['fdd']['bic'] = [
      'name'   => 'BIC',
      'select' => 'T',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    $opts['fdd']['blz'] = [
      'name'   => $this->l->t('Bank Code'),
      'select' => 'T',
      'maxlen' => 12,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    $opts['fdd']['bank_account_owner'] = [
      'name'   => $this->l->t('Bank Account Owner'),
      'select' => 'T',
      'maxlen' => 80,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    ///////////////

    // redirect all updates through Doctrine\ORM.
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
