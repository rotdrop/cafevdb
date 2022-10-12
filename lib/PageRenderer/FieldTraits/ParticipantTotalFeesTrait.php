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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

/**
 * Field definition for total fees and salaries for
 * participants
 */
trait ParticipantTotalFeesTrait
{
  use ParticipantFieldsCgiNameTrait;

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
      $fdd, self::PROJECT_PAYMENTS_TABLE, 'amount',
      [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Total Project Fees'),
        'css'      => [ 'postfix' => [ 'total-project-fees', 'money', ], ],
        'sort'    => true,
        'options' => 'VDFL', // wrong in change mode
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
        'tooltip'  => $this->toolTipsService['project-total-fee-summary'],
        'display|LFVD' => [ 'popup' => 'tooltip' ],
      ]);
  }
}
