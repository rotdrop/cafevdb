<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
 *
 * Add an "action button" for some convenience operations in order to spare
 * the change to the admin page for the list.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Controller\ProjectParticipantsController;

/**
 * Template parameters needed:
 *
 * @param string $appName
 * @param string $displayStatus
 * @param array $statusData
 * @param array $cssClasses
 * @param \OCP\IURLGenerator $urlGenerator
 * @param \OCA\CAFEVDB\Service\ToolTipsService $toolTips
 */

?>

<span class="status-label <?php echo implode(' ', $cssClasses); ?>"><?php p($displayStatus); ?></span>
<span class="subscription actions dropdown-container dropdown-no-hover <?php echo implode(' ', $cssClasses); ?>" data-status='<?php echo $statusData; ?>'>
  <button class="menu-title action-menu-toggle">...</button>
  <nav class="subscription-dropdown dropdown-content dropdown-align-right">
    <ul>
      <li class="subscription-action subscription-action-subscribe registration-preliminary-disabled expert-mode-enabled status-subscribed-disabled tooltip-auto"
          data-operation="<?php echo ProjectParticipantsController::LIST_ACTION_SUBSCRIBE; ?>"
          title="<?php echo $toolTips['page-renderer:participants:mailing-list:operation:subscribe']; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/add.svg'); ?>"/>
          <?php p($l->t('subscribe')); ?>
        </a>
      </li>
      <li class="subscription-action subscription-action-unsubscribe registration-confirmed-disabled expert-mode-enabled status-not-subscribed-disabled tooltip-auto"
          data-operation="<?php echo ProjectParticipantsController::LIST_ACTION_UNSUBSCRIBE; ?>"
          title="<?php echo $toolTips['page-renderer:participants:mailing-list:operation:unsubscribe']; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/delete.svg'); ?>"/>
          <?php p($l->t('unsubscribe')); ?>
        </a>
      </li>
      <li class="subscription-action subscription-action-delivery delivery-enabled-disabled status-not-subscribed-disabled subscription-action-enable-delivery tooltip-auto"
          data-operation="<?php echo ProjectParticipantsController::LIST_ACTION_ENABLE_DELIVERY; ?>"
          title="<?php echo $toolTips['page-renderer:participants:mailing-list:operation:enable-delivery']; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/play.svg'); ?>"/>
          <?php p($l->t('enable delivery')); ?>
        </a>
      </li>
      <li class="subscription-action subscription-action-delivery delivery-disabled-disabled status-not-subscribed-disabled subscription-action-disable-delivery tooltip-auto"
          data-operation="<?php echo ProjectParticipantsController::LIST_ACTION_DISABLE_DELIVERY; ?>"
          title="<?php echo $toolTips['page-renderer:participants:mailing-list:operation:disable-delivery']; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/pause.svg'); ?>"/>
          <?php p($l->t('disable delivery')); ?>
        </a>
      </li>
      <li class="subscription-action subscription-action-reload status-unknown-enabled tooltip-auto"
          data-operation="<?php echo ProjectParticipantsController::LIST_ACTION_RELOAD_SUBSCRIPTION; ?>"
          title="<?php echo $toolTips['page-renderer:participants:mailing-list:operation:reload-subscription']; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath($appName, 'reload-solid.svg'); ?>"/>
          <?php p($l->t('reload subscription')); ?>
        </a>
      </li>
    </ul>
  </nav>
</span>
