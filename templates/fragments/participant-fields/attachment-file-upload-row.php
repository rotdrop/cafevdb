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

/*
 * @param int $fieldId
 * @param string $optionKey
 * @param string $optionValue
 * @param int $musicianId
 * @param int $projectId
 * @param string $subDir
 * @param string $fileBase
 * @param string $fileName
 * @param string $filesAppPath
 * @param string $filesAppTarget
 * @param string $dataStorage
 * @param string $entityfield Either "option-value" or "supporting-document".
 * @param string $participantFolder
 * @param string $filesAppLink
 * @param string $downloadLink
 * @param string $optionValueName
 * @param string $uploadPlaceHolder
 * @param array $toolTips
 * @param string $toolTipsPrefix
 */

use OCA\CAFEVDB\Common\Util;

if (empty($filesAppTarget)) {
  $filesAppTarget = md5($filesAppPath);
}

?>
<tr class="file-upload-row field-datum"
    data-field-id="<?php p($fieldId); ?>"
    data-option-key="<?php p($optionKey); ?>"
    data-sub-dir="<?php p($subDir); ?>"
    data-file-base="<?php p($fileBase); ?>"
    data-file-name="<?php !empty($fileBase) && p($fileName); ?>"
    data-storage="<?php p($dataStorage); ?>"
    data-entity-field="<?php p($entityField ?? 'option-value'); ?>"
    data-files-app-path="<?php p($filesAppPath); ?>"
    data-participant-folder="<?php echo Util::htmlEscape($participantFolder); ?>"
>
  <td class="operations">
    <input type="button"
           <?php empty($optionValue) && p('disabled'); ?>
           title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:delete']; ?>"
           class="operation delete-undelete tooltip-auto"
    />
    <input type="button"
           title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:upload:from-client']; ?>"
           class="operation upload-replace tooltip-auto"
    />
    <input type="button"
           title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:upload:from-cloud']; ?>"
           class="operation upload-from-cloud tooltip-auto"
    />
    <a href="<?php echo $filesAppLink; ?>" target="<?php echo $filesAppTarget; ?>"
       title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:open-parent']; ?>"
       class="button operation open-parent tooltip-auto<?php empty($filesAppLink) && p(' disabled'); ?>"
    ></a>
  </td>
  <td class="<?php p($dataStorage); ?>-file input">
    <a class="download-link ajax-download tooltip-auto"
       title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:download']; ?>"
       href="<?php echo $downloadLink; ?>"
    ><?php p($fileName); ?></a>
    <input class="upload-placeholder tooltip-auto"
           title="<?php echo $toolTips[$toolTipsPrefix . ':upload:placeholder']; ?>"
           placeholder="<?php p($uploadPlaceHolder); ?>"
           type="text"
           name="<?php echo $optionValueName; ?>"
           value="<?php echo Util::htmlEscape($optionValue??''); ?>"
    />
  </td>
</tr>
