<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Copyright (c) 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Controller\MailingListsController;
use OCA\CAFEVDB\Controller\ProjectParticipantsController;
use OCA\CAFEVDB\Common\Util;

/** Field-trait for reusable field definitions. */
trait MailingListsTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var MailingListsService */
  private $listsService;

  /** @var Entities\Project */
  private $project;

  /** @return MailingListsService */
  protected function getListsService():MailingListsService
  {
    if (empty($this->listsService)) {
      $this->listsService = $this->di(MailingListsService::class);
    }
    return $this->listsService;
  }

  /**
   * Return fdd for controlling the global announcements subscription.
   *
   * @param string $emailSql SQL table field with the email address.
   *
   * @param array $columnTabs Table-tabs definitions.
   *
   * @param array $override Generic override FDD fields.
   *
   * @return array The merge field definitions.
   */
  protected function announcementsSubscriptionControls(
    string $emailSql = '$table.email',
    array $columnTabs = [],
    array $override = [],
  ):array {
    $fdd = [
      'name'    => $this->l->t('Mailing List'),
      'tab'     => [ 'id' => $columnTabs ],
      'css'     => [ 'postfix' => [ 'mailing-list', 'announcements', 'tooltip-wide', ], ],
      'sql'     => $emailSql,
      'options' => 'ACPVD',
      'input'   => 'V',
      'input|AP' => 'R',
      'tooltip' => $this->toolTipsService['page-renderer:musicians:mailing-list'],
      'php|AP' =>  function($email, $action, $k, $row, $recordId, PHPMyEdit $pme) {
        return '<input class="radio" id="mailing-list-action-invite" type="radio" value="invite" name="' . $pme->cgiDataName('mailing_list') . '" checked/>
<label for="mailing-list-action-invite">' . $this->l->t('invite') . '</label>
<input class="radio" type="radio" id="mailing-list-action-subscribe" value="subscribe" name="' . $pme->cgiDataName('mailing_list') . '"/>
<label for="mailing-list-action-subscribe">' . $this->l->t('subscribe') . '</label>
<input class="radio" type="radio" id="mailing-list-action-noop" value="subscribe" name="' . $pme->cgiDataName('mailing_list') . '"/>
<label for="mailing-list-action-noop">' . $this->l->t('no action') . '</label>
';
      },
      'php|CVD' => function($email, $action, $k, $row, $recordId, $pme) {
        $list = $this->getConfigValue('announcementsMailingList');
        try {
          $status = $this->getListsService()->getSubscriptionStatus($list, $email);
        } catch (\Throwable $t) {
          $this->logException($t, $this->l->t('Unable to contact mailing lists service'));
          $status = 'unknown';
        }
        $statusText = $this->l->t($status);
        $operations = [
          MailingListsController::OPERATION_INVITE,
          MailingListsController::OPERATION_ACCEPT,
          MailingListsController::OPERATION_SUBSCRIBE,
          MailingListsController::OPERATION_REJECT,
          MailingListsController::OPERATION_UNSUBSCRIBE,
          MailingListsController::OPERATION_RELOAD,
        ];
        // $disabled = [
        //   MailingListsController::OPERATION_INVITE => ($status != MailingListsService::STATUS_UNSUBSCRIBED),
        //   MailingListsController::OPERATION_ACCEPT => ($status != MailingListsService::STATUS_WAITING),
        //   MailingListsController::OPERATION_REJECT => ($status != MailingListsService::STATUS_INVITED && $status != MailingListsService::STATUS_WAITING),
        //   MailingListsController::OPERATION_SUBSCRIBE => (!$this->expertMode || $status != MailingListsService::STATUS_UNSUBSCRIBED),
        //   MailingListsController::OPERATION_UNSUBSCRIBE => ($status != MailingListsService::STATUS_SUBSCRIBED),
        // ];
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
            'expert-mode-only' => true,
          ],
          MailingListsController::OPERATION_UNSUBSCRIBE => [
            'status-subscribed-visible' => true,
          ],
          MailingListsController::OPERATION_RELOAD => [
            'status-unsubscribed-visible' => true,
            'status-waiting-visible' => true,
            'status-invited-visible' => true,
            'status-subscribed-visible' => true,
          ],
        ];
        $icons = [
          MailingListsController::OPERATION_INVITE => [ 'app' => 'core', 'image' => 'actions/confirm.svg' ],
          MailingListsController::OPERATION_ACCEPT => [ 'app' => 'core', 'image' => 'actions/checkmark.svg' ],
          MailingListsController::OPERATION_REJECT => [ 'app' => 'core', 'image' => 'actions/close.svg' ],
          MailingListsController::OPERATION_SUBSCRIBE => [ 'app' => 'core', 'image' => 'actions/add.svg' ],
          MailingListsController::OPERATION_UNSUBSCRIBE => [ 'app' => 'core', 'image' => 'actions/delete.svg' ],
          MailingListsController::OPERATION_RELOAD => [ 'app' => $this->appName(), 'image' => 'reload-solid.svg' ],
        ];
        $menuLabels = [
          MailingListsController::OPERATION_INVITE => $this->l->t('invite'),
          MailingListsController::OPERATION_ACCEPT => $this->l->t('accept'),
          MailingListsController::OPERATION_REJECT => $this->l->t('reject'),
          MailingListsController::OPERATION_SUBSCRIBE => $this->l->t('subscribe'),
          MailingListsController::OPERATION_UNSUBSCRIBE => $this->l->t('unsubscribe'),
          MailingListsController::OPERATION_RELOAD =>  $this->l->t('reload subscription'),
        ];
        $html = '
<span class="mailing-list announcements subscription status status-label action-' . $action . ' status-' . $status . '" data-status="' . $status. '">' . $statusText . '</span>
';
        $html .= '
<span class="dropdown-container dropdown-no-hover mailing-list announcements subscription operations action-' . $action . ' status-' . $status . '" data-status="' . $status. '">
  <button class="menu-title action-menu-toggle">...</button>
  <nav class="announcements subscription-dropdown dropdown-content dropdown-align-right">
    <ul>';
        foreach ($operations as $operation) {
          $operationClasses = $cssClasses[$operation];
          $icon = $icons[$operation];
          $visible = !empty($operationClasses['status-' . $status . '-visible']);
          $disabled = !$visible || (!$this->expertMode && !empty($operationClasses['expert-mode-only']));
          $css = implode(' ', array_merge($defaultCss, array_keys($operationClasses), [ $operation ]));
          $css .= ($disabled ? ' disabled' : '');
          $html .= '
      <li class="subscription-action tooltip-auto ' . $css . '"
          title="' . $this->toolTipsService['page-renderer:musicians:mailing-list:actions:' . $operation] . '"
          data-operation="' .  $operation . '"
          ' .  ($disabled ? 'disabled' : '') . '
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath($icon['app'], $icon['image']) . '"/>
          ' . $menuLabels[$operation] . '
        </a>
      </li>
';
        }
        $html .= '
    </ul>
  </nav>
</span>';
        return $html;
      },
    ];

    return Util::arrayMergeRecursive($fdd, $override ?? []);
  }

  /**
   * @param string $emailSql SQL table field with the email address.
   *
   * @param array $columnTabs Table-tabs definitions.
   *
   * @param array $override Generic override FDD fields.
   *
   * @return array The merge field definitions.
   */
  protected function projectListSubscriptionControls(
    string $emailSql = '$table.email',
    array $columnTabs = [],
    array $override = [],
  ):array {
    $fdd = [
      'name' => $this->l->t('Project Mailing List'),
      'tab' => [ 'id' => $columnTabs ],
      'css' => [ 'postfix' => [ 'mailing-list', 'tooltip-wide', 'project', ] ],
      'sql' => $emailSql,
      'options' => 'ACPVD',
      'input'   => 'V',
      'tooltip' => $this->toolTipsService['page-renderer:participants:mailing-list'],
      // copy and add are disabled
      'php|CVD' => function($email, $action, $k, $row, $recordId, $pme) {
        $displayStatus = $this->l->t($status = 'unknown');
        $this->getListsService();
        $cssClasses = [ 'mailing-list', 'project', 'status' ];
        $registration = empty($row['qf' . $pme->fdn['registration']])
          ? 'preliminary' : 'confirmed';
        $cssClasses[] =  'registration-' . $registration;
        if (empty($this->project)
            || empty($listId = $this->project->getMailingListId())
            || !$this->listsService->isConfigured()
        ) {
          $cssClasses[] = 'status-' . $status;
          return '<span class="' . implode(' ', $cssClasses) . '">' . $displayStatus . '</span>';
        }

        // @todo This is duplicate of the corresponding code in
        // ProjectParticipantsController.
        try {
          $summary = ProjectParticipantsController::mailingListDeliveryStatus($this->listsService, $listId, $email);
          $status = $summary['subscriptionStatus'];
          $statusFlags = $summary['statusTags'];
          $displayStatus = $this->l->t($summary['summary']);
        } catch (\Throwable $t) {
          $this->logException($t, $this->l->t('Unable to contact mailing lists service'));
          $statusFlags = [];
        }

        $statusData = htmlspecialchars(json_encode($statusFlags));
        $cssClasses = array_merge($cssClasses, $statusFlags);
        $html = '<span class="status-label ' . implode(' ', $cssClasses) . '">' . $displayStatus . '</span>';

        if ($status == 'unknown') {
          return $html;
        }

        // add an "action button" for some convenience operations in order to
        // spare the change to the admin page for the list.
        $html .= '
<span class="subscription actions dropdown-container dropdown-no-hover ' . implode(' ', $cssClasses) . '" data-status=\'' . $statusData . '\'>
  <button class="menu-title action-menu-toggle">...</button>
  <nav class="subscription-dropdown dropdown-content dropdown-align-right">
    <ul>
      <li class="subscription-action subscription-action-subscribe registration-preliminary-disabled expert-mode-enabled status-subscribed-disabled tooltip-auto"
          data-operation="' . ProjectParticipantsController::LIST_ACTION_SUBSCRIBE . '"
          title="' . $this->toolTipsService['page-renderer:participants:mailing-list:operation:subscribe'] . '"
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/add.svg') . '"/>
          ' . $this->l->t('subscribe') . '
        </a>
      </li>
      <li class="subscription-action subscription-action-unsubscribe registration-confirmed-disabled expert-mode-enabled status-not-subscribed-disabled tooltip-auto"
          data-operation="' . ProjectParticipantsController::LIST_ACTION_UNSUBSCRIBE . '"
          title="' . $this->toolTipsService['page-renderer:participants:mailing-list:operation:unsubscribe'] . '"
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/delete.svg') . '"/>
          ' . $this->l->t('unsubscribe') . '
        </a>
      </li>
      <li class="subscription-action subscription-action-delivery delivery-enabled-disabled status-not-subscribed-disabled subscription-action-enable-delivery tooltip-auto"
          data-operation="' . ProjectParticipantsController::LIST_ACTION_ENABLE_DELIVERY . '"
          title="' . $this->toolTipsService['page-renderer:participants:mailing-list:operation:enable-delivery'] . '"
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/play.svg') . '"/>
          ' . $this->l->t('enable delivery') . '
        </a>
      </li>
      <li class="subscription-action subscription-action-delivery delivery-disabled-disabled status-not-subscribed-disabled subscription-action-disable-delivery tooltip-auto"
          data-operation="' . ProjectParticipantsController::LIST_ACTION_DISABLE_DELIVERY . '"
          title="' . $this->toolTipsService['page-renderer:participants:mailing-list:operation:disable-delivery'] . '"
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/pause.svg') . '"/>
          ' . $this->l->t('disable delivery') . '
        </a>
      </li>
      <li class="subscription-action subscription-action-reload tooltip-auto"
          data-operation="' . ProjectParticipantsController::LIST_ACTION_RELOAD_SUBSCRIPTION . '"
          title="' . $this->toolTipsService['page-renderer:participants:mailing-list:operation:reload-subscription'] . '"
      >
        <a href="#">
          <img alt="" src="' . $this->urlGenerator()->imagePath($this->appName(), 'reload-solid.svg') . '"/>
          ' . $this->l->t('reload subscription') . '
        </a>
      </li>
    </ul>
  </nav>
</span>';
        return $html;
      },
    ];
    return Util::arrayMergeRecursive($fdd, $override ?? []);
  }
}
