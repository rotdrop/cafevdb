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
use OCA\CAFEVDB\Common\Functions;

/** TBD. */
class SepaBankAccounts extends PMETableViewBase
{
  use FieldTraits\ParticipantFieldsTrait;

  const TEMPLATE = 'sepa-bank-accounts';
  const TABLE = self::SEPA_BANK_ACCOUNTS_TABLE;
  const FIXED_COLUMN_SEP = self::VALUES_TABLE_SEP;

  protected $cssClass = 'sepa-bank-accounts';

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\SepaBankAccount::class,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
    ],
    self::SEPA_DEBIT_MANDATES_TABLE => [
      'entity' => Entities\SepaDebitMandate::class,
      'flags' => self::JOIN_READONLY|self::JOIN_GROUP_BY,
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
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'musician_id' => 'musician_id',
        'bank_account_sequence' => 'sequence',
        'project_id' => [
          'table' => self::PROJECT_PARTICIPANTS_TABLE,
          'column' => 'project_id',
        ],
      ],
      'column' => 'id',
    ],
  ];

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var FinanceService */
  private $financeService;

  /** @var Entities\Project */
  private $project = null;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , ProjectParticipantFieldsService $participantFieldsService
    , FinanceService $financeService
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->participantFieldsService = $participantFieldsService;
    $this->financeService = $financeService;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove this Bank-Account?');
    } else if ($this->viewOperation()) {
      if ($this->projectId > 0 && $this->projectName != '') {
        return $this->l->t('Bank-Account for %s', array($this->projectName));
      } else {
        return $this->l->t('Bank-Account');
      }
    } else if ($this->changeOperation()) {
      return $this->l->t('Change this Bank-Account');
    }
    if ($this->projectId > 0 && $this->projectName != '') {
      return $this->l->t('Overview over all SEPA Bank Accounts for %s',
                  array($this->projectName));
    } else {
      return $this->l->t('Overview over all SEPA Bank Accounts');
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

    $projectMode = $this->projectId > 0;
    if ($projectMode)  {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
    }

    $opts = [];

    $opts['css']['postfix'] = [
      self::CSS_TAG_DIRECT_CHANGE,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS,
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
    $opts['key'] = [ 'musician_id' => 'int', 'sequence' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'musician_id', 'sequence' ];

    // Group by for to-many joins
    $opts['groupby_fields'] = $opts['sort_field'];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    if ($projectMode) {
      $opts['options'] .= 'M';
    }
    $opts['misc']['css']['major'] = 'misc';
    $opts['misc']['css']['minor'] = 'debit-note tooltip-bottom';
    $opts['labels']['Misc'] = $this->l->t('Debit');

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $buttons = [];
    if ($projectMode) {
      $debitJob = $this->requestParameters->getParam('debit-job', '');

      $debitJobs = '
<span id="pme-debit-note-job" class="pme-debit-note-job pme-menu-block">
  <select multiple data-placeholder="'.$this->l->t('Debit Job').'"
          class="pme-debit-note-job"
          title="'.$this->toolTipsService['debit-note-job-choice'].'"
          name="debitJobs[]">
    <option value=""></option>';

      $jobOptions = $this->participantFieldsService->monetarySelectOptions($this->project);

      $debitJobs .= $this->pageNavigation->selectOptions($jobOptions);
      $debitJobs .= '
  </select>
</span>';
      $buttons[] = [ 'code' =>  $debitJobs, 'name' => 'debit-jobs' ];
    }

    $button = $this->pageNavigation->tableExportButton();
    $button['name'] = 'export';
    $buttons[] = $button;

    $opts['buttons'] = $this->pageNavigation->prependTableButtons(
      $buttons, [
        'up' => [ 'left' => [ 'debit-jobs', 'misc', 'export' ] ],
        'down' => [ 'left' => [ 'misc', 'export' ], 'right' => [ 'debit-jobs' ] ]
      ]);

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
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
      ],
      'navigation' => 'VCD', // 'VCPD',
    ];
    $amountTab = [
      'id' => 'amount',
      'tooltip' => $this->l->t('Show the amounts to draw by debit transfer, including sum of payments
received so far'),
      'name' => $this->l->t('Amount')
    ];
    $allTab = [
      'id' => 'tab-all',
      'tooltip' => $this->toolTipsService['pme-showall-tab'],
      'name' => $this->l->t('Display all columns'),
    ];
    if ($projectMode && $projectId !== $this->membersProjectId) {
      $opts['display']['tabs'][] = $amountTab;
    }
    $opts['display']['tabs'][] = $allTab;

    if ($this->addOperation()){
      $opts['display']['tabs'] = false;
    }

    ////////////////////////////////////////////////////////////////&&&&&&&&&&&
    //
    // Add the id-columns of the main-table
    //

    $opts['fdd']['musician_id'] = [
      'name'     => $this->l->t('Musician-Id'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => null,
      'sort'     => true,
    ];

    $opts['fdd']['sequence'] = [
      'name'     => $this->l->t('Mandate-Sequence'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => 1,
      'sort'     => true,
    ];

    // Define some further join-restrictions
    array_walk($this->joinStructure, function(&$joinInfo, $table) {
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
      default:
        break;
      }
    });


    // add participant fields-data, if in project-mode
    if ($projectMode) {
      $monetary = $this->participantFieldsService->monetaryFields($this->project);

      list($participantFieldsJoin, $participantFieldsGenerator) =
        $this->renderParticipantFields(
          $monetary, [
            'table' => self::PROJECT_PARTICIPANTS_TABLE,
            'column' => 'project_id',
          ],
          'amount');
      $this->joinStructure = array_merge($this->joinStructure, $participantFieldsJoin);
    }

    $this->defineJoinStructure($opts);

    // field definitions

    ///////////////////////////////////////////////////////////////////////////

    // This is the project from the mandate
    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      Util::arrayMergeRecursive(
        [
          'tab' => [ 'id' => 'mandate' ],
          'name'     => $this->l->t('Mandate Project'),
          'input'    => 'RH',
          'input|A'  => $projectMode ? 'R' : null,
          'select'   => 'D',
          'maxlen'   => 11,
          'sort'     => true,
          'css'      => [ 'postfix' => ' mandate-project allow-empty' ],
          'values' => [
            'description' => [
              'columns' => [ 'year' => "CONCAT(\$table.year, IF(\$table.year IS NULL, '', ': '))", 'name' => 'name' ],
              //'divs' => [ 'year' => ': ' ]
            ],
          ],
        ],
        $projectMode
        ? array_merge(
          [ 'values' => [ 'filters' => '$table.id in ('.$projectId.','.$this->membersProjectId.')' ],],
          ($projectId === $this->membersProjectId)
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

    ///////////////////////////////////////////////////////////////////////////

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'id',
      [
        'tab'      => [ 'id' => 'tab-all' ],
        'name'     => $this->l->t('Musician'),
        'css'      => [ 'postfix' => ' allow-empty' ],
        'input'    => 'R',
        'input|A'  => null,
        'select'   => 'D',
        'maxlen'   => 11,
        'sort'     => true,
        'values' => [
          'description' => [
            'columns' => [ 'sur_name', 'first_name', ],
            'divs' => ', ',
          ],
          'filters' => (!$projectMode
                        ? null
                        : "FIND_IN_SET(id,
  (SELECT GROUP_CONCAT(pp.musician_id)
    FROM ".self::PROJECT_PARTICIPANTS_TABLE." pp
    WHERE pp.project_id = ".$projectId."
    GROUP BY pp.project_id))"),
        ],
      ]);


    ///////////////

    // couple of "easy" fields

    // soft-deletion
    $opts['fdd']['deleted'] = Util::arrayMergeRecursive(
      $this->defaultFDD['deleted'], [
      ]);

    $opts['fdd']['bank_account_owner'] = [
      'tab' => [ 'id' => 'account' ],
      'name'   => $this->l->t('Bank Account Owner'),
      'input' => 'M',
      'options' => 'LFACPDV',
      'select' => 'T',
      'select|LF' => 'D',
      'maxlen' => 80,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
      'values'  => [
        'table' => self::TABLE,
        'column' => 'bank_account_owner',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'filters' => (!$projectMode
                     ? null
                     : '$table.musician_id IN
  (SELECT musician_id
   FROM '.self::PROJECT_PARTICIPANTS_TABLE.' ppblah
   WHERE ppblah.project_id = '.$this->projectId.')'),
      ],
    ];

    $opts['fdd']['iban'] = [
      'tab' => [ 'id' => [ 'account', 'mandate' ] ],
      'name'   => 'IBAN',
      //'input' => 'M',
      'options' => 'LFACPDV',
      'select' => 'T',
      'select|LF' => 'D',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
      'values' => [
        'table' => self::TABLE,
        'column' => 'iban',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'filters' => (!$projectMode
                     ? null
                     : '$table.musician_id IN
  (SELECT musician_id
   FROM '.self::PROJECT_PARTICIPANTS_TABLE.' ppblah
   WHERE ppblah.project_id = '.$this->projectId.')'),
      ],
      'display' => [
        'popup' => function($data) {
          $info  = $this->financeService->getIbanInfo($data);
          $result = '';
          foreach ($info as $key => $value) {
            $result .= $this->l->t($key).': '.$value.'<br/>';
          }
          return $result;
        },
      ],
    ];

    $opts['fdd']['blz'] = [
      'tab' => [ 'id' => 'account' ],
      'name'   => $this->l->t('Bank Code'),
      'select' => 'T',
      'input|LF' => 'H',
      'maxlen' => 12,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    $opts['fdd']['bic'] = [
      'name'   => 'BIC',
      'input|LF' => 'H',
      'select' => 'T',
      'maxlen' => 35,
      'encryption' => [
        'encrypt' => function($value) { return $this->encrypt($value); },
        'decrypt' => function($value) { return $this->decrypt($value); },
      ],
    ];

    ///////////////////////////////////////////////////////////////////////////

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference',
      [
        'tab'    => [ 'id' => 'mandate' ],
        'name'   => $this->l->t('Mandate Reference'),
        'input'  => 'R',
        'select' => 'T',
        'maxlen' => 35,
        'sort'   => true,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'non_recurring',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name' => $this->l->t('Non-Recurring'),
        'select' => 'O',
        'maxlen' => '1',
        'sort' => true,
        'escape' => false,
        'sqlw' => 'IF($val_qas = "", 0, 1)',
        'values2' => [ 0 => '', 1 => '&#10004;' ],
        'values2|CAP' => [ 0 => '', 1 => '' ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'deleted',
      array_merge($this->defaultFDD['deleted'], [
        'tab'    => [ 'id' => 'mandate' ],
        'name'   => $this->l->t('Mandate Revoked'),
      ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_date',
      [
        'tab' => [ 'id' => 'mandate' ],
        'name'     => $this->l->t('Date Issued'),
        'input' => 'M',
        'select'   => 'T',
        'maxlen'   => 10,
        'sort'     => true,
        'css'      => [ 'postfix' => ' sepadate' ],
        'datemask' => 'd.m.Y',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PAYMENTS_TABLE, 'date_of_receipt',
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
        'css'      => [ 'postfix' => ' last-used-date' ],
        'datemask' => 'd.m.Y'
      ]);

    ///////////////

    // @todo PARTICIPANT FIELD STUFF / PROJECT MODE

    if (!$this->addOperation() && $projectMode) {
      if (true) {
        $participantFieldsGenerator($opts['fdd']);
      } else
      foreach ($monetary as $name => $field) {
        $fieldName = $field['name'];
        $fieldId   = $field['id'];
        $multiplicity = $field['multiplicity'];
        $dataType = $field['data_type'];

        $tableName = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::FIXED_COLUMN_SEP.$fieldId;

        $css = [ 'participant-field', 'field-id-'.$fieldId, ];
        list($curColIdx, $fddName) = $this->makeJoinTableField(
          $opts['fdd'], $tableName, 'field_value',
          [
            'name' => $this->l->t($fieldName),
            'tab' => [ 'id' => 'amount' ],
            'css'      => [ 'postfix' => ' '.implode(' ', $css), ],
            'default'  => $field['default_value'],
            'values' => [
              'column' => 'option_value',
              'filters' => ('$table.field_id = '.$fieldId
                            .' AND $table.project_id = '.$projectId
                            .' AND $table.musician_id = $record_id[musician_id]'),
            ],
            'tooltip' => $field['tooltip']?:null,
            'php' => function($value, $op, $field, $row, $recordId, $pme) {
              $amount = 0.0;

              // TODO fill with data

              $money = $this->moneyValue($amount);
              return 'IMPLEMENT ME';
            },
          ]);
        $fdd = &$opts['fdd'][$fddName];

        // TODO accumulate to total amount

        // TODO define join for amountPaid

        // TODO display overview field (also needed for participants table
      }

    //   $extraIdx = count($opts['fdd']);
    //   $opts['fdd']['ExtraProjectFees'] = array_merge(
    //     Config::$opts['money'],
    //     array(
    //       //'tab'      => array('id' => $financeTab),
    //       'name'     => L::t('Extra Charges'),
    //       'css'      => array('postfix' => ' extra-project-fees money'),
    //       'sort'    => false,
    //       'options' => 'VDL', // wrong in change mode
    //       'input' => 'VR',
    //       'sql' => $projectAlias.'.Unkostenbeitrag',
    //       'php' => function($amount, $op, $field, $row, $recordId, $pme)
    //         use ($monetary)
    //         {
    //           $amount = 0.0;
    //           foreach($pme->fds as $key => $label) {
    //             if (!isset($monetary[$label])) {
    //               continue;
    //             }
    //             $value = $row['qf'.$key];
    //             if (empty($value)) {
    //               continue;
    //             }
    //             $field   = $monetary[$label];
    //             $allowed = $field['DataOptions'];
    //             $type    = $field['Type'];
    //             $amount += DetailedInstrumentation::participantFieldSurcharge($value, $allowed, $type['Multiplicity']);
    //           }
    //           return Util::moneyValue($amount);
    //         },
    //       'sort' => true,
    //       'tooltip'  => $this->toolTipsService['project-extra-fee-summary'],
    //       'display|LFVD' => array('popup' => 'tooltip'),
    //     )
    //   );

    //   $amountPaidIdx = count($opts['fdd']);
    //   $opts['fdd']['AmountPaid'] = array_merge(
    //     Config::$opts['money'],
    //     array(
    //       'name' => L::t('Amount Paid'),
    //       'input' => 'VR',
    //       'sql' => $projectAlias.'.AmountPaid',
    //       'sort' => 1
    //     )
    //   );

    //   $totalsIdx = count($opts['fdd']);
    //   $opts['fdd']['TotalProjectFees'] = array(
    //     //'tab'      => array('id' => $financeTab),
    //     'name'     => L::t('Total Charges'),
    //     'css'      => array('postfix' => ' total-project-fees money'),
    //     'sort'    => false,
    //     'options' => 'VDLF', // wrong in change mode
    //     'input' => 'VR',
    //     'sql' => $projectAlias.'.Unkostenbeitrag',
    //     'php' => function($amount, $op, $field, $row, $recordId, $pme)
    //       use ($monetary, $amountPaidIdx)
    //       {
    //         $paid = $row['qf'.$amountPaidIdx];
    //         foreach($pme->fds as $key => $label) {
    //           if (!isset($monetary[$label])) {
    //             continue;
    //           }
    //           $value = $row['qf'.$key];
    //           if (empty($value)) {
    //             continue;
    //           }
    //           $field   = $monetary[$label];
    //           $allowed = $field['DataOptions'];
    //           $type    = $field['Type'];
    //           $amount += DetailedInstrumentation::participantFieldSurcharge($value, $allowed, $type['Multiplicity']);
    //         }
    //         // display as TOTAL/PAID/REMAINDER
    //         $rest = $amount - $paid;

    //         $amount = Util::moneyValue($amount);
    //         $paid = Util::moneyValue($paid);
    //         $rest = Util::moneyValue($rest);
    //         return ('<span class="totals finance-state">'.$amount.'</span>'
    //                 .'<span class="received finance-state">'.$paid.'</span>'
    //                 .'<span class="outstanding finance-state">'.$rest.'</span>');
    //       },
    //     'tooltip'  => $this->toolTipsService['project-total-fee-summary'],
    //     'display|LFVD' => array('popup' => 'tooltip'),
    //   );

    }

    ///////////////

    if ($musicianId > 0) {
      $opts['filters']['AND'][] = '$table.musicianId = '.$musicianId;
    }
    if ($projectMode) {
      $opts['filters']['AND'][] =
        $this->joinTables[self::PROJECT_PARTICIPANTS_TABLE].'.project_id = '.$projectId;
    }
    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // redirect all updates through Doctrine\ORM.
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    // $this->logInfo('FILTERS '.Functions\dump($opts['filters']));
    $this->logInfo('GROUPS '.Functions\dump($opts['groupby_fields']));
    // $this->logInfo('SORT '.Functions\dump($opts['sort_field']));

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
