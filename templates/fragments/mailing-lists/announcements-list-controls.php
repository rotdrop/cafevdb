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

use OCA\CAFEVDB\Controller\MailingListsController;

/**
 * @param string $action PME action.
 * @param string $status Mailing list status.
 * @param bool $expertMode
 * @param OCP\IURLGenerator $urlGenerator
 * @param OCA\CAFEVDB\Service\ToolTipsService $toolTips
 */

$statusText = $l->t($status);
$operations = [
  MailingListsController::OPERATION_INVITE,
  MailingListsController::OPERATION_ACCEPT,
  MailingListsController::OPERATION_SUBSCRIBE,
  MailingListsController::OPERATION_REJECT,
  MailingListsController::OPERATION_UNSUBSCRIBE,
  MailingListsController::OPERATION_RELOAD,
];
$defaultCss = [ 'mailing-list', 'operation' ];
$cssClasses = [
  MailingListsController::OPERATION_INVITE => [
    'status-unsubscribed-visible' => true,
  ],
  MailingListsController::OPERATION_ACCEPT => [
    'status-waiting-visible' => true,
  ],
  MailingListsController::OPERATION_REJECT => [
    'status-invited-visible' => true,
    'status-waiting-visible' => true,
  ],
  MailingListsController::OPERATION_SUBSCRIBE => [
    'status-unsubscribed-visible' => true,
    // 'expert-mode-only' => true,
  ],
  MailingListsController::OPERATION_UNSUBSCRIBE => [
    'status-subscribed-visible' => true,
  ],
  MailingListsController::OPERATION_RELOAD => [
    'status-unsubscribed-visible' => true,
    'status-waiting-visible' => true,
    'status-invited-visible' => true,
    'status-subscribed-visible' => true,
    'status-unknown-visible' => true,
  ],
];
$icons = [
  MailingListsController::OPERATION_INVITE => [ 'app' => 'core', 'image' => 'actions/confirm.svg' ],
  MailingListsController::OPERATION_ACCEPT => [ 'app' => 'core', 'image' => 'actions/checkmark.svg' ],
  MailingListsController::OPERATION_REJECT => [ 'app' => 'core', 'image' => 'actions/close.svg' ],
  MailingListsController::OPERATION_SUBSCRIBE => [ 'app' => 'core', 'image' => 'actions/add.svg' ],
  MailingListsController::OPERATION_UNSUBSCRIBE => [ 'app' => 'core', 'image' => 'actions/delete.svg' ],
  MailingListsController::OPERATION_RELOAD => [ 'app' => $appName, 'image' => 'reload-solid.svg' ],
];
$menuLabels = [
  MailingListsController::OPERATION_INVITE => $l->t('invite'),
  MailingListsController::OPERATION_ACCEPT => $l->t('accept'),
  MailingListsController::OPERATION_REJECT => $l->t('reject'),
  MailingListsController::OPERATION_SUBSCRIBE => $l->t('subscribe'),
  MailingListsController::OPERATION_UNSUBSCRIBE => $l->t('unsubscribe'),
  MailingListsController::OPERATION_RELOAD =>  $l->t('reload subscription'),
];
?>

<span class="mailing-list announcements subscription status status-label action-<?php echo $action; ?> status-<?php echo $status; ?>"
      data-status="<?php echo $status; ?>"><?php p($statusText); ?></span>
<span class="dropdown-container dropdown-no-hover mailing-list announcements subscription operations action-<?php echo $action; ?> status-<?php echo $status; ?>"
      data-status="<?php echo $status; ?>">
  <button class="menu-title action-menu-toggle">...</button>
  <nav class="announcements subscription-dropdown dropdown-content dropdown-align-right">
    <ul>
      <?php foreach ($operations as $operation) {
        $operationClasses = $cssClasses[$operation];
        $icon = $icons[$operation];
        $visible = !empty($operationClasses['status-' . $status . '-visible']);
        $disabled = !$visible || (!$expertMode && !empty($operationClasses['expert-mode-only']));
        $css = implode(' ', array_merge($defaultCss, array_keys($operationClasses), [ $operation ]));
        $css .= ($disabled ? ' disabled' : ''); ?>
      <li class="subscription-action tooltip-auto <?php echo $css; ?>"
          title="<?php echo $toolTips['page-renderer:musicians:mailing-list:actions:' . $operation]; ?>"
          data-operation="<?php echo $operation; ?>"
          <?php $disabled && p('disabled'); ?>
      >
        <a href="#" class="flex-container flex-center">
          <img alt="" src="<?php echo $urlGenerator->imagePath($icon['app'], $icon['image']); ?>"/>
          <?php p($menuLabels[$operation]); ?>
        </a>
      </li>
      <?php } ?>
    </ul>
  </nav>
</span>
