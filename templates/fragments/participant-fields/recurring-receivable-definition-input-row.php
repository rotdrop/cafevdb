<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\PageRenderer\ProjectParticipantFields as PageRenderer;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;

/**
 * @param mixed $value One row of the form as returned from
 * \OCA\CAFEVDB\Service\ProjectParticipantFieldsService::explodeDataOptions().
 *
 * @param int $index
 * @param bool $used
 * @param string $inputName
 * @param OCA\CAFEVDB\Service\ToolTipsService $toolTips
 */

$deleted = !empty($rowData['deleted']);
?>
<tr class="data-line data-options data-option-row<?php $deleted && p(' deleted') ?>"
    data-index="<?= $index ?>"
    data-used="<?= ($used ? 'used' : 'unused') ?>"
    data-deleted="<?= $rowData['deleted'] ?>"
>
  <td class="operations">
    <input
      class="operation delete-undelete"
      title="<?= $toolTips['participant-fields-data-options:delete-undelete'] ?>"
      type="button"
    />
    <input
      class="operation regenerate only-multiplicity-recurring"
      title="<?= $toolTips['participant-fields-recurring-data:regenerate'] ?>"
      <?php $deleted && p('disabled') ?>
      type="button"
    />
  </td>
  <?php
  $prop = 'key';
  $cssClass = PageRenderer::OPTION_DATA_SHOW_MASK[$prop]??[];
  $cssClass[] = 'field-' . $prop;
  $cssClass = implode(' ', $cssClass);
  ?>
  <td class="<?= $cssClass ?>">
    <input readonly
           type="text"
           class="<?= $cssClass ?>"
           name="<?= $inputName ?>[<?= $index ?>][<?= $prop ?>]"
           value="<?= $rowData[$prop] ?>"
           title="<?= $rowData[$prop] ?>"
           size="5"
           maxlength="<?= strlen($rowData[$prop]) ?>"
    />
    <input type="hidden"
           class="field-deleted"'
           name="<?=$inputName?>[<?=$index?>][deleted]"
           value="<?=$rowData['deleted']?>"
    />
  </td>
  <?php
  // label
  $prop = 'label';
  $cssClass = 'field-' . $prop;
  ?>
  <td class="<?= $cssClass ?>">
    <input class="<?= $cssClass ?>"
           spellcheck="true"
           type="text"
           name="<?=$inputName?>[<?=$index?>][<?=$prop?>]"
           value="<?=$rowData[$prop]?>"
           title="<?=$toolTips['participant-fields-data-options:' . $prop]?>"
           size="16"
           maxlength="32"
           <?php ($deleted && p('readonly')) ?>
    />
  </td>
  <?php
  $prop = 'data';
  $cssClass = PageRenderer::OPTION_DATA_SHOW_MASK[$prop]??[];
  $cssClass[] = 'field-' . $prop;
  $cssClass = implode(' ', $cssClass);
  $size = PageRenderer::OPTION_DATA_INPUT_SIZE[$dataType]??PageRenderer::OPTION_DATA_INPUT_SIZE['default'];
  $fieldValue = $rowData[$prop];
  if (!empty($fieldValue)) {
    switch ($dataType) {
      case DataType::DATE:
        $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
        $fieldValue = $this->dateTimeFormatter()->formatDate($date, 'medium');
        break;
      case DataType::DATETIME:
        $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
        $fieldValue = $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
        break;
    }
  }
  ?>
  <td class="<?= $cssClass ?>">
    <input class="<?= $cssClass ?>"
           type="text"
           name="<?=$inputName?>[<?=$index?>][<?=$prop?>]"
           value="<?=$fieldValue?>"
           title="<?=$toolTips['participant-fields-data-options:' . $prop]?>"
           size="<?=$size?>"
           <?php ($deleted && p('readonly')) ?>
    />
  </td>
  <?php
  $prop = 'deposit';
  $cssClass = implode(
    ' ',
    array_merge(
      PageRenderer::OPTION_DATA_SHOW_MASK[$prop] ?? [], [
        'field-' . $prop,
        'not-multiplicity-simple-set-deposit-due-date-required',
        'not-multiplicity-single-set-deposit-due-date-required',
        'not-multiplicity-groupofpeople-set-deposit-due-date-required',
        'set-deposit-due-date-required',
        'not-data-type-receivables-hidden',
        'not-data-type-liabilities-hidden',
    ])
  );
  ?>
  <td class="<?= $cssClass ?>">
    <input class="<?= $cssClass ?>"
           type="number"
           step="0.01"
           required
           name="<?=$inputName?>[<?=$index?>][<?=$prop?>]"
           value="<?=$rowData[$prop]?>"
           title="<?=$toolTips['participant-fields-data-options:' . $prop]?>"
           maxlength="8"
           size="9"
           <?php ($deleted && p('readonly')) ?>
    />
  </td>
  <?php
  $prop = 'limit';
  $cssClass = implode(' ', array_merge(['field-' . $prop], PageRenderer::OPTION_DATA_SHOW_MASK[$prop]));
  ?>
  <td class="<?= $cssClass ?>">
    <input class="<?= $cssClass ?>"
           type="number"
           name="<?=$inputName?>[<?=$index?>][<?=$prop?>]"
           value="<?=$rowData[$prop]?>"
           title="<?=$toolTips['participant-fields-data-options:' . $prop]?>"
           maxlength="8"
           size="9"
           <?php ($deleted && p('readonly')) ?>
    />
  </td>
  <?php
  $prop = 'tooltip';
  $cssClass = 'field-'.$prop;
  ?>
  <td class="<?= $cssClass ?>">
    <textarea class="<?= $cssClass ?>"
              name="<?=$inputName?>[<?=$index?>][<?=$prop?>]"
              title="<?=$toolTips['participant-fields-data-options:' . $prop]?>"
              cols="32"
              rows="1"
              <?php ($deleted && p('readonly')) ?>
    ><?=$rowData[$prop]?></textarea>
  </td>
</tr>
