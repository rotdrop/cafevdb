<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Copyright (c) 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  /** @var bool */
  protected $expertMode;

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
        return $this->templateResponse(
          'fragments/mailing-lists/announcements-list-controls-add-copy', [
            'mailingListActionName' => $pme->cgiDataName('mailing_list'),
          ],
        )->render();
      },
      'php|CVD' => function($email, $action, $k, $row, $recordId, $pme) {
        // Do not contact the mailing-list service here, as this really slows
        // down things if the mailing list service is unreachable.
        //
        // $list = $this->getConfigValue('announcementsMailingList');
        // try {
        //   $status = $this->getListsService()->getSubscriptionStatus($list, $email);
        // } catch (\Throwable $t) {
        //   $this->logException($t, $this->l->t('Unable to contact mailing lists service'));
        //   $status = 'unknown';
        // }
        $status = 'unknown';
        return ($this->templateResponse(
          $this->appName(),
          'fragments/mailing-lists/announcements-list-controls', [
            'appName' => $this->appName(),
            'action' => $action,
            'status' => $status,
            'urlGenerator' => $this->urlGenerator(),
            'toolTips' => $this->toolTipsService,
            'expertMode' => $this->expertMode ?? false,
          ],
          'blank',
        ))->render();
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
        $cssClasses = [ 'mailing-list', 'project', 'status' ];
        $registration = empty($row['qf' . $pme->fdn['registration']])
          ? 'preliminary' : 'confirmed';
        $cssClasses[] =  'registration-' . $registration;

        $displayStatus = $this->l->t('unknown');

        // Do not contact the mailing-list service here, as this really slows
        // down things if the mailing list service is unreachable.
        //
        // $this->getListsService();
        // $listId = empty($this->project) ? null : $this->project->getMailingListId();
        // try {
        //   $summary = ProjectParticipantsController::mailingListDeliveryStatus($this->listsService, $listId, $email);
        //   // $status = $summary['subscriptionStatus'];
        //   $statusFlags = $summary['statusTags'];
        //   $displayStatus = $this->l->t($summary['summary']);
        // } catch (\Throwable $t) {
        //   $this->logException($t, $this->l->t('Unable to contact mailing lists service'));
        //   $statusFlags = [ 'status-unknown' ];
        // }
        $statusFlags = [ 'status-unknown' ];

        $statusData = htmlspecialchars(json_encode($statusFlags));
        $cssClasses = array_merge($cssClasses, $statusFlags);

        // add an "action button" for some convenience operations in order to
        // spare the change to the admin page for the list.
        return ($this->templateResponse(
          $this->appName(),
          'fragments/mailing-lists/project-list-controls', [
            'appName' => $this->appName(),
            'displayStatus' => $displayStatus,
            'cssClasses' => $cssClasses,
            'statusData' => $statusData,
            'urlGenerator' => $this->urlGenerator(),
            'toolTips' => $this->toolTipsService,
          ],
          'blank',
        ))->render();
      },
    ];
    return Util::arrayMergeRecursive($fdd, $override ?? []);
  }
}
