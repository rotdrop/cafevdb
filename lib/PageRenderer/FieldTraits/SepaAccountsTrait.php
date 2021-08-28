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

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/** SEPA bank account. */
trait SepaAccountsTrait
{
  /**
   * Generate join-structure and field-descriptions for the SEPA information.
   *
   * @return array
   * ```
   * [ JOIN_STRUCTURE_FRAGMENT, GENERATOR(&$fdd) ]
   * ```
   */
  public function renderSepaAccounts($musicianIdField = 'id', $projectRestrictions = [], $financeTab = 'finance')
  {
    $joinStructure = [
      self::SEPA_BANK_ACCOUNTS_TABLE => [
        'entity' => Entities\SepaBankAccount::class,
        'flags' => self::JOIN_READONLY,
        'identifier' => [
          'musician_id' => $musicianIdField,
          'sequence' => false,
        ],
        'column' => 'sequence',
      ],
      self::SEPA_DEBIT_MANDATES_TABLE => [
        'entity' => Entities\SepaDebitMandate::class,
        'flags' => self::JOIN_READONLY,
        'identifier' => [
          'musician_id' => $musicianIdField,
          'sequence' => false,
        ],
        'filter' => [
          'bank_account_sequence' => [
            'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
            'column' => 'sequence',
          ],
        ],
        'column' => 'sequence',
      ],
    ];
    if (!empty($projectRestrictions)) {
      $joinStructure[self::SEPA_DEBIT_MANDATES_TABLE]['filter']['project_id'] = [ 'value' => $projectRestrictions ];
      // $projectWhere = " AND sdm.project_id IN ('".implode("','", $projectRestrictions)."')";
    }

    $generator = function(&$fdd) use ($musicianIdField, $projectRestrictions, $financeTab) {
      $this->makeJoinTableField(
        $fdd, self::SEPA_DEBIT_MANDATES_TABLE, 'mandate_reference', [
          'name' => $this->l->t('SEPA Debit Mandate Reference'),
          'tab' => ['id' => $financeTab],
          'input' => 'H',
          'sql' => 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    "'.self::JOIN_KEY_SEP.'",
    CONCAT_WS(
      "'.self::COMP_KEY_SEP.'",
      $join_table.musician_id,
      $join_table.bank_account_sequence,
      $join_table.sequence),
    $join_col_fqn)
  ORDER BY $order_by)',
          'filter' => [
            'having' => true,
          ],
          'sort' => true,
          'select' => 'M',
          'values' => [
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
            'grouped' => true,
            'orderby' => '$table.musician_id ASC, $table.bank_account_sequence ASC, $table.sequence ASC',
          ],
      ]);

      $this->makeJoinTableField(
        $fdd, self::SEPA_DEBIT_MANDATES_TABLE, 'deleted', [
          'name' => $this->l->t('SEPA Debit Mandate Deleted'),
          'tab' => ['id' => $financeTab],
          'input' => 'H',
          'sql' => 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    "'.self::JOIN_KEY_SEP.'",
    CONCAT_WS(
      "'.self::COMP_KEY_SEP.'",
      $join_table.musician_id,
      $join_table.bank_account_sequence,
      $join_table.sequence),
    $join_col_fqn)
  ORDER BY $order_by)',
          'filter' => [
            'having' => true,
          ],
          'sort' => true,
          'select' => 'M',
          'values' => [
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
            'grouped' => true,
            'orderby' => '$table.musician_id ASC, $table.bank_account_sequence ASC, $table.sequence ASC',
          ],
      ]);

      $this->makeJoinTableField(
        $fdd, self::SEPA_BANK_ACCOUNTS_TABLE, 'iban', [
          'name' => $this->l->t('SEPA Bank Accounts'),
          'tab' => ['id' => $financeTab],
          'input' => 'S',
          'input|ACP' => 'H',
          'encryption' => [
            'encrypt' => function($value) {
              $values = Util::explode(',', $value);
              foreach ($values as &$value) {
                $value = $this->encrypt($value);
              }
              return implode(',', $values);
            },
            'decrypt' => function($value) {
              $values = Util::explode(',', $value);
              foreach ($values as &$value) {
                $value = $this->decrypt($value);
              }
              return implode(',', $values);
            },
          ],
          'encryption|ACP' => [
            'encrypt' => function($value) {
              $values = Util::explodeIndexed($value);
              foreach ($values as $key => $value) {
                $values[$key] = $key.self::JOIN_KEY_SEP.$this->encrypt($value);
              }
              return implode(',', $values);
            },
            'decrypt' => function($value) {
              $values = Util::explodeIndexed($value);
              foreach ($values as $key => $value) {
                $values[$key] = $key.self::JOIN_KEY_SEP.$this->decrypt($value);
              }
              return implode(',', $values);
            },
          ],
          'sql|ACP' => 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    "'.self::JOIN_KEY_SEP.'",
    CONCAT_WS(
      "'.self::COMP_KEY_SEP.'",
      $join_table.musician_id,
      $join_table.sequence,
      '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence),
    $join_col_fqn)
  ORDER BY $order_by, '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence ASC)',
          'filter' => [
            'having' => true,
          ],
          'display|LFDV' => [
            'popup' => 'data',
            // For an unknown reason we need two divs. Otherwise the
            // subsequent lines gets squeezed but somehow their overflow
            // still enters at least partly into the calculation of the
            // height of the table cell.
            'prefix' => '<div class="pme-cell-wrapper"><div class="pme-cell-squeezer">',
            'postfix' => '</div></div>',
          ],
          'sort' => true,
          'select' => 'M',
          'values' => [
            // description needs to be there in order to trigger drop-down on change
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
            'grouped' => true,
            'orderby' => '$table.musician_id ASC, $table.sequence ASC',
          ],
          'values2glue' => '<br/>',
          'css' => [ 'postfix' => ' squeeze-subsequent-lines' ],
      ]);

      $this->makeJoinTableField(
        $fdd, self::SEPA_BANK_ACCOUNTS_TABLE, 'deleted', [
          'name' => $this->l->t('Bank Account Deleted'),
          'tab' => ['id' => $financeTab],
          'input' => 'H',
          'sql' => 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    "'.self::JOIN_KEY_SEP.'",
    CONCAT_WS(
      "'.self::COMP_KEY_SEP.'",
      $join_table.musician_id,
      $join_table.sequence,
     '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence),
    $join_col_fqn)
  ORDER BY $order_by, '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence ASC)',
          'filter' => [
            'having' => true,
          ],
          'sort' => true,
          'select' => 'M',
          'values' => [
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
            'grouped' => true,
            'orderby' => '$table.musician_id ASC, $table.sequence ASC',
          ],
      ]);

      $this->makeJoinTableField(
        $fdd, self::SEPA_BANK_ACCOUNTS_TABLE, 'sepa_id', [
          'name' => $this->l->t('SEPA Bank Accounts'),
          'tab' => ['id' => $financeTab],
          'input' => 'VS',
          'input|LFDV' => 'VH',
          'select' => 'D',
          'css' => [ 'postfix' => ' sepa-bank-accounts' ],
          'sql|ACP' => 'GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    "'.self::COMP_KEY_SEP.'",
    $join_table.musician_id,
    $join_table.sequence,
    '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence)
  ORDER BY $order_by, '.$this->joinTables[self::SEPA_DEBIT_MANDATES_TABLE].'.sequence ASC)',
          'values' => [
            'column' => 'sequence',
            'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
            'grouped' => true,
            'orderby' => '$table.musician_id ASC, $table.sequence ASC',
          ],
          'php' => function($value, $op, $k, $row, $recordId, $pme) use ($musicianIdField, $projectRestrictions) {
            // $this->logInfo('RECORD ID '.$recordId.' PME REC '.print_r($pme->rec, true));

            //$valInfo = $pme->set_values($k-1);

            //$this->logInfo('VALUE '.$value.' ROW '.print_r($row, true));
            //$this->logInfo('VALINFO '.print_r($valInfo, true));

            // more efficient would perhaps be JSON
            $sepaIds = Util::explode(',', $value);
            $accountDeleted = Util::explodeIndexed($row['qf'.($k-1)]);
            $ibans = Util::explodeIndexed($row['qf'.($k-2)]);
            $mandateDeleted = Util::explodeIndexed($row['qf'.($k-3)]);
            $references = Util::explodeIndexed($row['qf'.($k-4)]);

            $this->logInfo('M DELETED '.print_r($mandateDeleted, true));
            $this->logInfo('A DELETED '.print_r($accountDeleted, true));

            $html = '<table class="'.($this->showDisabled ? 'show-deleted' : 'hide-deleted').' row-count-'.count($ibans).'">
  <tbody>';
            foreach ($ibans as $mandateSepaId => $iban) {
              list($musicianId, $bankAccountSequence, $mandateSequence) = Util::explode(self::COMP_KEY_SEP, $mandateSepaId);
              $accountInactive = $accountDeleted[$mandateSepaId];
              $mandateInactive = $mandateDeleted[$mandateSepaId];
              $sepaIds = [];
              if (!$accountInactive && $mandateInactive) {
                // build a second row without the deactivated mandate
                $sepaIds[] = implode(self::COMP_KEY_SEP, [ $musicianId, $bankAccountSequence, 0 ]);
                $this->logInfo('SEPA IDS 0 '.print_r($sepaIds, true));
              }
              $sepaIds[] = $mandateSepaId;
              $this->logInfo('SEPA IDS 1 '.print_r($sepaIds, true));
              foreach ($sepaIds as $sepaId) {
                list($musicianId, $bankAccountSequence, $mandateSequence) = Util::explode(self::COMP_KEY_SEP, $sepaId);
                $accountInactive = $accountDeleted[$sepaId];
                $mandateInactive = $mandateDeleted[$sepaId];
                $sepaData = json_encode([
                  'projectId' => (empty($projectRestrictions) ? 0 : $projectRestrictions[0]),
                  'musicianId' => $musicianId,
                  'bankAccountSequence' => $bankAccountSequence,
                  'mandateSequence' => $mandateSequence,
                ]);
                $fakeValue = $iban;
                $reference = $references[$sepaId];
                if (!empty($reference)) {
                  $fakeValue .= ' -- ' . $reference;
                }
                $cssClass = [ 'bank-account-data', ];
                $mandateInactive && $cssClass[] = 'mandate-deleted';
                $accountInactive && $cssClass[] = 'account-deleted';
                ($mandateInactive || $accountInactive) && $cssClass[] = 'deleted';
                $html .= '
    <tr class="'.implode(' ', $cssClass).'" data-sepa-id="'.$sepaId.'">
      <td class="operations">
        <!-- <input
          class="operation delete-undelete"
          title="'.$this->toolTipsService['sepa-bank-account:delete-undelete'].'"
          type="button"/> -->
        <input
          class="operation info sepa-debit-mandate"
          title="'.$this->toolTipsService['sepa-bank-account:info'].'"
          data-debit-mandate=\''.$sepaData.'\'
          type="button"/>
      </td>
      <td class="iban">
        <input
          class="bank-account-data dialog sepa-debit-mandate"
          title="'.$this->toolTipsService['sepa-bank-account:info'].'"
          type="text"
          value="'.$fakeValue.'"
          data-debit-mandate=\''.$sepaData.'\'
          readonly
        />
      </td>
    </tr>';
              }
            }
            $sepaData = json_encode([
              'projectId' => (empty($projectRestrictions) ? 0 : $projectRestrictions[0]),
              'musicianId' => $pme->rec[$musicianIdField],
              'bankAccountSequence' => 0,
              'mandateSequence' => 0,
            ]);
            $html .= '
    <tr class="placeholder">
      <td colspan="2" class="operation">
        <input
          class="operation add sepa-debit-mandate"
          title="'.$this->toolTipsService['sepa-bank-account:add'].'"
          type="button"
          value="'.$this->l->t('Add a new bank account').'"
          data-debit-mandate=\''.$sepaData.'\'
        />
      </td>
    </tr>
  </tbody>
</table>
<div class="display-options">
  <div class="show-deleted">
    <input type="checkbox"
           name="show-deleted"
           class="show-deleted checkbox"
           value="show"
          '.($this->showDisabled ? 'checked' : '').'
           id="sepa-bank-accounts-show-deleted"
           />
    <label class="show-deleted"
           for="sepa-bank-accounts-show-deleted"
           title="'.$this->toolTipsService['sepa-bank-acocunt:show-deleted'].'"
           >
           '.$this->l->t('Show deleted.').'
    </label>
  </div>
</div>';
            return $html;
          },
      ]);

      return $fdd;
    }; // generator

    return [ $joinStructure, $generator ];
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
