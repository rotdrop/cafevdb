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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use Exception;

use OCP\IL10N;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\ToolTipsService;

/**
 * Field definition for total fees and salaries for
 * participants
 */
trait ParticipantTotalFeesTrait
{
  use ParticipantFieldsCgiNameTrait;
  use SubstituteSQLFragmentTrait;

  /** @var IL10N */
  protected $l;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var PHPMyEdit */
  protected $pme;

  /** @var string */
  protected static $toolTipsPrefix = 'page-renderer:participant-fields:display';

  /**
   * Create a "virtual" total-fees field with a summary of the total amount to
   * pay or receive, the amount already paid and the reminaing amount. This is
   * used in both "directions", so values to be received by the orchestra are
   * positive, values to pay to participants are negative.
   *
   * @param array $fdd Field description data.
   *
   * @param iterable $monetaryFields
   *
   * @param string $financeTab The tab to move the field to.
   *
   * @return void
   *
   * @see ProjectParticipantFieldsService::monetaryFields()
   */
  protected function makeTotalFeesField(array &$fdd, iterable $monetaryFields, string $financeTab):void
  {
    $this->makeJoinTableField(
      $fdd, PMETableViewBase::PROJECT_PAYMENTS_TABLE, 'amount',
      [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Total Project Fees'),
        'css'      => [ 'postfix' => [ 'total-project-fees project-fees-summary', 'money', ], ],
        'sort'    => true,
        'options' => 'VDL', // wrong in change mode
        'input' => 'VR',
        'select|FL' => 'N',
        'sql' => 'IF($join_col_fqn IS NULL,
  0.0,
  SUM($join_col_fqn)
  * COUNT(DISTINCT $join_table.id)
  / COUNT($join_table.id))',
        'filter' => [
          'having' => true,
        ],
        'php' => function($amountPaid, $op, $k, $row, $recordId, $pme) use ($monetaryFields) {

          // $musicianId = $recordId['musician_id'];

          $amountInvoiced = 0.0;
          /** @var Entities\ProjectParticipantField $participantField */
          foreach ($monetaryFields as $participantField) {
            $fieldId = $participantField->getId();

            $table = self::participantFieldTableName($fieldId);
            $fieldValues = [ 'key' => null, 'value' => null ];
            foreach ($fieldValues as $fieldName => &$fieldValue) {
              $label = $this->joinTableFieldName($table, 'option_'.$fieldName);
              if (!isset($pme->fdn[$label])) {
                throw new Exception($this->l->t('Data for monetary field "%s" not found', $label));
              }
              $rowIndex = $pme->fdn[$label];
              $qf = 'qf'.$rowIndex;
              $qfIdx = $qf.'_idx';
              if (isset($row[$qfIdx])) {
                $fieldValue = $row[$qfIdx];
              } else {
                $fieldValue = $row[$qf];
              }
            }
            unset($fieldValue); // break reference to previous field

            if (empty($fieldValues['key']) && empty($fieldValues['value'])) {
              continue;
            }

            $amountInvoiced += $this->participantFieldsService->participantFieldSurcharge(
              $fieldValues['key'], $fieldValues['value'], $participantField);
          }

          // display as TOTAL/PAID/REMAINDER
          $rest = $amountInvoiced - $amountPaid;

          $amountInvoiced = $this->moneyValue($amountInvoiced);
          $amountPaid = $this->moneyValue($amountPaid);
          $rest = $this->moneyValue($rest);
          return ('<span class="totals finance-state">'.$amountInvoiced.'</span>'
                  .'<span class="received finance-state">'.$amountPaid.'</span>'
                  .'<span class="outstanding finance-state">'.$rest.'</span>');
        },
        'tooltip'  => $this->toolTipsService[self::$toolTipsPrefix . ':total-fees:summary'],
        'display|LFVD' => [ 'popup' => 'tooltip' ],
      ]);
  }

  /**
   * Generate three distinct columns for the total invoice amount, the amount
   * already transferred and the open amount which still needs to be
   * transferred.
   *
   * @param array $fdd Field description data, will be modified in place.
   *
   * @param array $subTotals Array of SQL selectable expressions as
   * returned by the renderer-callback returned by
   * ParticipantFieldsTrait::renderParticipantFields().
   *
   * @param string $financeTab The tab to move the field to.
   *
   * @return void
   */
  protected function makeTotalFeesFields(array &$fdd, array $subTotals, string $financeTab):void
  {
    // generate monster SQL fragment for the total amount to pay
    $totalAmountInvoicedSql = '(' . implode(' + ', $subTotals) . ')';
    $this->makeJoinTableField(
      $fdd, PMETableViewBase::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE, 'total_amount_invoiced', [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Invoiced'),
        'css'      => [ 'postfix' => [ 'total-project-fees project-fees-details project-fees-invoiced', 'money', ], ],
        'sort'    => true,
        'options' => 'VDFL', // wrong in change mode
        'input' => 'VR',
        'select|FL' => 'N',
        'sql' => $totalAmountInvoicedSql,
        'filter' => [
          'having' => true,
        ],
        'values' => [
          'column' => 'field_id',
        ],
        'php' => fn($value) => '<span class="totals finance-state">' . $this->moneyValue($value) . '</span>',
        'tooltip' => $this->toolTipsService[self::$toolTipsPrefix . ':total-fees:invoiced'],
      ]);

    $totalAmountTransferredSql = 'CAST(
  IF(
    $join_col_fqn IS NULL,
    0.0,
    SUM($join_col_fqn)
    * COUNT(DISTINCT $join_table.id)
    / COUNT($join_table.id)
  ) AS DECIMAL(7, 2))';

    $column = 'amount';
    list($fddIndex, $fddName) = $this->makeJoinTableField(
      $fdd, PMETableViewBase::PROJECT_PAYMENTS_TABLE, 'total_amount_transferred', [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Transferred'),
        'css'      => [ 'postfix' => [ 'total-project-fees project-fees-details project-fees-received', 'money', ], ],
        'sort'    => true,
        'options' => 'VDFL', // wrong in change mode
        'input' => 'VR',
        'select|FL' => 'N',
        'sql' => $totalAmountTransferredSql,
        'filter' => [
          'having' => true,
        ],
        'values' => [
          'column' => $column,
        ],
        'php' => fn($value) => '<span class="received finance-state">' . $this->moneyValue($value) . '</span>',
        'tooltip' => $this->toolTipsService[self::$toolTipsPrefix . ':total-fees:received'],
      ]);

    $totalAmountTransferredSql = $this->substituteSQLFragment($fdd, $fddName, $totalAmountTransferredSql, $fddIndex);

    $this->makeJoinTableField(
      $fdd, PMETableViewBase::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE, 'total_amount_outstanding', [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Outstanding'),
        'css'      => [ 'postfix' => [ 'total-project-fees project-fees-details project-fees-outstanding', 'money', ], ],
        'sort'    => true,
        'options' => 'VDFL', // wrong in change mode
        'input' => 'VR',
        'select|FL' => 'N',
        'sql' => $totalAmountInvoicedSql . ' - ' . $totalAmountTransferredSql,
        'filter' => [
          'having' => true,
        ],
        'values' => [
          'column' => 'field_id',
        ],
        'php' => fn($value) => '<span class="outstanding finance-state">' . $this->moneyValue($value) . '</span>',
        'tooltip' => $this->toolTipsService[self::$toolTipsPrefix . ':total-fees:outstanding'],
      ]);
  }

  /**
   * Move the search request from the summary to the respective detail field.
   *
   * @param PHPMyEdit $pme
   *
   * @param string $op
   *
   * @param string $step
   *
   * @return bool
   */
  public function totalFeesPreFilterTrigger(PHPMyEdit $pme, string $op, string $step):bool
  {
    $summaryName = $this->joinTableFieldName(PMETableViewBase::PROJECT_PAYMENTS_TABLE, 'amount');
    $outstandingName = $this->joinTableFieldName(PMETableViewBase::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE, 'total_amount_outstanding');

    if (!isset($pme->fdn[$summaryName])) {
      // happens if there are no finance fields.
      return true;
    }

    $summaryIndex = $pme->fdn[$summaryName];
    $outstandingIndex = $pme->fdn[$outstandingName];

    $searchField = array_search($summaryIndex, $pme->sfn);
    if ($searchField === false) {
      $searchField = array_search(-$summaryIndex, $pme->sfn);
    }

    // sfn needs to be a string
    if ($searchField !== false) {
      $pme->sfn[$searchField] = $pme->sfn[$searchField] < 0
        ? '-' . $outstandingIndex
        : (string)$outstandingIndex;
    }

    return true;
  }
}
