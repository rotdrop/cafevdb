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
$filesAppTarget = md5($filesAppLink);

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

$filesAppTarget = md5($filesAppLink ?? '');

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
           name="<?php p($optionKeyName); ?>'[<?php p($optionIdx); ?>]"
           value="<?php p($optionKey); ?>"/>
  </td>
  <td class="documents"
      data-field-id="<?php p($fieldId); ?>"
      data-option-key="<?php p($optionKey); ?>"
      data-file-base=""
      data-file-name=""
      data-storage="db"
      data-entity-field="supporting-document"
      data-files-app-path="<?php p($filesAppPath); ?>"
      data-participant-folder="<?php p($participantFolder); ?>"
  >
     <span class="dropdown-container dropdown-no-hover">
       <button class="supporting-document menu-title action-menu-toggle"
               title="<?php echo $toolTips['participant-fields-recurring-data:supporting-document']; ?>"
       >...</button>
       <nav class="dropdown-content dropdown-align-right">
         <ul class="menu-list">
           <li class="menu-item tooltip-auto"
               title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:delete']; ?>"
           >
             <a class="operation delete-undelete<?php empty($downloadLink) && p(' disabled'); ?>" href="#">
               <span class="menu-icon"></span>
               <?php p($l->t('delete attachment')); ?>
             </a>
           </li>
           <li class="menu-item tooltip-auto"
               title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:upload:from-client']; ?>"
           >
             <a class="operation upload-replace" href="#">
               <span class="menu-icon"></span>
               <?php p($l->t('upload from client')); ?>
             </a>
           </li>
           <li class="menu-item tooltip-auto"
               title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:upload:from-cloud']; ?>"
           >
             <a class="operation upload-from-cloud" href="#">
               <span class="menu-icon"></span>
               <?php p($l->t('select from cloud')); ?>
             </a>
           </li>
           <li class="menu-item tooltip-auto"
               title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:download']; ?>"
           >
             <a class="operation open-parent<?php empty($filesAppLink) && p(' disabled'); ?>"
                href="<?php p($filesAppLink); ?>"
                target="<?php p($filesAppTarget); ?>"
             >
               <span class="menu-icon"></span>
               <?php p($l->t('open parent folder')); ?>
             </a>
           </li>
           <li class="menu-item tooltip-auto"
               title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:download']; ?>"
           >
             <a class="download-link static-content ajax-download<?php empty($downloadLink) && p(' disabled'); ?>"
                href="<?php p($downloadLink ?? ''); ?>">
               <span class="menu-icon"></span>
               <?php p($l->t('download')); ?>
             </a>
           </li>
         </ul>
       </nav>
     </span>
     <a class="download-link static-content ajax-download tooltip-auto button<?php empty($downloadLink) && p(' disabled'); ?>"
        href="<?php p($downloadLink ?? ''); ?>"
        title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:download']; ?>"
     >
       <?php p($l->t('download')); ?>
     </a>
 </td>
</tr>
