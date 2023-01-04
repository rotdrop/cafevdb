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

/**
 * @param string $containerTag
 * @param array $containerAttributes
 * @param int $fieldId
 * @param string $optionKey
 * @param string $fileBase
 * @param string $fileName
 * @param string $storage
 * @param string $entityField
 * @param string $filesAppPath
 * @param string $filesAppLink
 * @param string $filesAppTarget
 * @param string $downloadLink
 * @param string $participantFolder
 * @param array $toolTips
 * @param string $toolTipsPrefix
 */

if (empty($filesAppTarget)) {
  $filesAppTarget = md5($filesAppPath ?? '');
}

?>

<<?php p($containerTag); foreach ($containerAttributes as $name => $value) { ?>
  <?php p($name); ?>="<?php p($value); ?>"
 <?php } ?>
    data-field-id="<?php p($fieldId); ?>"
    data-option-key="<?php p($optionKey); ?>"
    data-file-base="<?php p($fileBase); ?>"
    data-file-name="<?php p($fileName); ?>"
    data-storage="<?php p($storage); ?>"
    data-entity-field="<?php p($entityField); ?>"
    data-files-app-path="<?php p($filesAppPath); ?>"
    data-participant-folder="<?php p($participantFolder); ?>"
>
  <span class="dropdown-container dropdown-no-hover">
    <button class="supporting-document menu-title action-menu-toggle"
            title="<?php echo $toolTips[$toolTipsPrefix . ':attachment']; ?>"
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
            title="<?php echo $toolTips[$toolTipsPrefix . ':attachment:open-parent']; ?>"
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
</<?php p($containerTag); ?>>
