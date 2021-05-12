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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Field definition for total fees and salaries for
 * participants
 */
trait ParticipantTotalFeesTrait
{
  protected function makeTotalFeesField(&$fdd, $monetaryFields, $financeTab)
  {
    $this->makeJoinTableField(
      $fdd, self::PROJECT_PAYMENTS_TABLE, 'amount',
      [
        'tab'      => [ 'id' => $financeTab ],
        'name'     => $this->l->t('Total Project Fees'),
        'css'      => [ 'postfix' => ' total-project-fees money' ],
        'sort'    => false,
        'options' => 'VDLF', // wrong in change mode
        'input' => 'VR',
        'sql' => 'IF($join_col_fqn IS NULL,
  0.0,
  SUM($join_col_fqn)
  * COUNT(DISTINCT $join_table.id)
  / COUNT($join_table.id))',
        'php' => function($amountPaid, $op, $k, $row, $recordId, $pme) use ($monetaryFields) {

          $project_id = $recordId['project_id'];
          $musicianId = $recordId['musician_id'];

          $amountInvoiced = 0.0;
          /** @var Entities\ProjectParticipantField $participantField */
          foreach ($monetaryFields as $participantField) {
            $fieldId = $participantField->getId();

            $table = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE.self::VALUES_TABLE_SEP.$fieldId;
            $fieldValues = [ 'key' => null, 'value' => null ];
            foreach ($fieldValues as $fieldName => &$fieldValue) {
              $label = $this->joinTableFieldName($table, 'option_'.$fieldName);
              if (!isset($pme->fdn[$label])) {
                throw new \Exception($this->l->t('Data for monetary field "%s" not found', $label));
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
