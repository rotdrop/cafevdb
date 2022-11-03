<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;

/**
 * @param string $projectType The type of the project, temporary, permanent, template.
 * @param string $listId The list id. The value stored in the DB.
 * @param string $status Mailing-list status
 * @param string $l10nStatus Translated status
 * @param string $listAddress The mailing list email address.
 * @param ?string $configUrl Configuration URL if list is configured.
 * @param OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit $pme
 * @param OCA\CAFEVDB\Service\ToolTipsService $toolTips
 * @param OCP\IUrlgenerator $urlGenerator
 * @param string $cssClassName
 */

if (!empty($configUrl)) {
  $configAnchor = '<a href="' . $configUrl . '" target="' . md5($listAddress) . '">' . $listAddress . '</a>';
} else {
  $configAnchor = $listAddress;
}

?>

<div class="cell-wrapper flex-container flex-center">
  <span class="list-id display status-<?php p($status); ?> tooltip-top"
        data-status="<?php p($status); ?>"
        title="<?php echo $toolTips['projects:mailing-list']; ?>"
  >
    <?php echo $pme->htmlHiddenData('mailing_list_id', $value, $cssClassName); ?>
    <span class="list-label"><?php echo $configAnchor; ?></span>
    <span class="list-status"><?php p($l10nStatus); ?></span>
  </span>
  <span class="list-id actions status-<?php p($status); ?> dropdown-container dropdown-no-hover" data-status="<?php p($status); ?>">
    <button class="menu-title action-menu-toggle"
            title="<?php echo $toolTips['projects:mailing-list:dropdown']; ?>">...</button>
    <nav class="mailing-list-dropdown dropdown-content dropdown-align-right">
      <ul>
        <li class="list-action list-action-create tooltip-auto"
            data-operation="create"
            title="<?php echo $toolTips['projects:mailing-list:create']; ?>"
        >
          <a href="#" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/add.svg'); ?>">
            <?php p($l->t('create')); ?>
          </a>
        </li>
        <li class="list-action list-action-manage tooltip-auto"
            title="<?php echo $toolTips['projects:mailing-list:manage']; ?>"
          >
          <a href="' . $configUrl . '" target="' . md5($listAddress); ?>" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/settings-dark.svg'); ?>">
            <?php p($l->t('manage')); ?>
          </a>
        </li>
        <li class="list-action list-action-subscribe tooltip-auto"
            data-operation="subscribe"
            title="<?php echo $toolTips['projects:mailing-list:subscribe']; ?>"
        >
          <a href="#" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/confirm.svg'); ?>">
            <?php p($l->t('subscribe')); ?>
          </a>
        </li>
        <li class="list-action list-action-close tooltip-auto"
            data-operation="close"
            title="<?php echo $toolTips['projects:mailing-list:close']; ?>"
        >
          <a href="#" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/pause.svg'); ?>">
            <?php p($l->t('close')); ?>
          </a>
        </li>
        <li class="list-action list-action-reopen tooltip-auto"
            data-operation="reopen"
            title="<?php echo $toolTips['projects:mailing-list:reopen']; ?>"
        >
          <a href="#" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/play.svg'); ?>">
            <?php p($l->t('reopen')); ?>
          </a>
        </li>
        <li class="list-action list-action-delete expert-mode-only tooltip-auto"
            data-operation="delete"
            title="<?php echo $toolTips['projects:mailing-list:delete']; ?>"
        >
          <a href="#" class="flex-container flex-center">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/delete.svg'); ?>">
            <?php p($l->t('delete')); ?>
          </a>
        </li>
      </ul>
    </nav>
  </span>
</div>
