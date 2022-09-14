<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

/**
 * @param Entities\ProjectParticipantField $field
 * @param Entities\ProjectParticipantFieldDataOption $fieldOption
 * @param string $optionValue
 * @param string $optionLabelName
 * @param string $optionKeyName
 * @param string $optionValueName
 * @param int $optionIdx Should go away, really
 * @param string $filesAppPath
 * @param string $filesAppLink
 * @param string $downloadLink
 * @param string $participantFolder
 * @param array $toolTips
 * @param string $toolTipsPrefix
 */

$fieldId = $field->getId();
$dataType = $field->getDataType();
$optionKey = $fieldOption->getKey();
$optionLabel = $fieldOption->getLabel();

$locked = !empty($optionValue) || $dataType == FieldType::SERVICE_FEE;
$valueInputType = $dataType == FieldType::SERVICE_FEE ? 'type="number" step="0.01"' : 'type="text"';
$filesAppTarget = md5($filesAppPath ?? '');

$labelled = $optionLabel !== null;
$lockCssClass = [
  'pme-input',
  'pme-input-lock',
  'lock-unlock',
  'left-of-input',
];
$lockCssClass = implode(' ', $lockCssClass);

$lockRightCssClass = $lockCssClass . ' position-right';
if ($dataType != FieldType::SERVICE_FEE) {
  $lockCssClass = $lockRightCssClass;
}

?>

<tr class="field-datum"
    data-field-id="<?php p($fieldId); ?>"
    data-option-key="<?php p($optionKey); ?>"
>
  <td class="operations">
    <input
      class="operation delete-undelete"
      title="<?php echo $toolTips['participant-fields-recurring-data:delete-undelete']; ?>"
      type="button"
    />
    <input
      class="operation regenerate"
      title="<?php echo $toolTips['participant-fields-recurring-data:regenerate']; ?>"
      type="button"
    />
  </td>
  <td class="label">
    <input id="receivable-option-label-lock-<?php p($optionKey); ?>"
           type="checkbox"
           <?php $locked && p('checked'); ?>
           class="<?php p($lockCssClass); ?>"
    />
    <label class="<?php p($lockRightCssClass); ?>"
           title="<?php echo $toolTips['pme:input:lock-unlock']; ?>"
           for="receivable-option-label-lock-<?php p($optionKey); ?>"
    >
    </label>
    <input class="pme-input"
           type="text"
           value="<?php p($optionLabel); ?>"
           name="<?php p($optionLabelName); ?>[<?php p($optionIdx); ?>]"
           readonly
           title="<?php echo $toolTips['participant-fields-recurring-data:set-label']; ?>"
    />
  </td>
  <td class="input">
    <input id="receivable-option-value-lock-<?php p($optionKey); ?>"
           type="checkbox"
           <?php $locked && p('checked'); ?>
           class="<?php p($lockCssClass); ?>"
    />
    <label class="<?php p($lockCssClass); ?>"
           title="<?php echo $toolTips['pme:input:lock-unlock']; ?>"
           for="receivable-option-value-lock-<?php p($optionKey); ?>"></label>
    <input class="pme-input <?php p($dataType); ?>"
           type="text"
           <?php $locked && p('readonly'); ?>
           name="<?php p($optionValueName); ?>[<?php p($optionIdx); ?>]"
           value="<?php p($optionValue); ?>"
    />
    <input class="pme-input <?php p($dataType); ?>"
           type="hidden"
           name="<?php p($optionKeyName); ?>[<?php p($optionIdx); ?>]"
           value="<?php p($optionKey); ?>"/>
  </td>
  <?php echo $this->inc('fragments/participant-fields/attachment-file-upload-menu', [
    'containerTag' => 'td',
    'containerAttributes' => [
      'class' => 'documents'
    ],
    'fieldId' => $fieldId,
    'optionKey' => $optionKey,
    'entityField' => 'supportingDocument',
    'storage' => 'db',
    'fileBase' => '',
    'fileName' => '',
  ]); ?>
</tr>
