<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine
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
 */
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MailingListsService;

use OCA\CAFEVDB\Common\Util;

/** Handle subscription management callbacks */
class MailingListsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const OPERATION_INVITE = 'invite';
  const OPERATION_SUBSCRIBE = 'subscribe';
  const OPERATION_UNSUBSCRIBE = 'unsubscribe';
  const OPERATION_ACCEPT = 'accept';
  const OPERATION_REJECT = 'reject';
  const OPERATION_RELOAD = 'reload';
  const OPERATIONS = [
    self::OPERATION_INVITE,
    self::OPERATION_SUBSCRIBE,
    self::OPERATION_UNSUBSCRIBE,
    self::OPERATION_ACCEPT,
    self::OPERATION_REJECT,
    self::OPERATION_RELOAD,
  ];

  /** @var MailingListsService */
  private $listsService;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IRequest $request,
    ConfigService $configService,
    MailingListsService $listsService,
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->listsService = $listsService;
    $this->l = $this->l10n();
  }

  /**
   * @param string $operation Operation to perform.
   *
   * @param string $list List id of FQDN.
   *
   * @param string $email Subscription email address.
   *
   * @param null|string $displayName Display name on the list.
   *
   * @param null|string $role Rold, member vs. moderator vs. owner.
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(
    string $operation,
    string $list,
    string $email,
    ?string $displayName = null,
    ?string $role = null,
  ):DataResponse {
    if ($list == 'announcements') {
      $list = $this->getConfigValue('announcementsMailingList');
    }
    switch ($operation) {
      case self::OPERATION_INVITE:
        $this->logInfo('INVITE ' . $list . ' / ' . $email);
        $this->listsService->invite($list, email: $email, displayName: $displayName);
        break;
      case self::OPERATION_SUBSCRIBE:
        $this->logInfo('SUBSCRIBE ' . $list . ' / ' . $email);
        $this->listsService->subscribe($list, email: $email, displayName: $displayName, role: $role);
        break;
      case self::OPERATION_UNSUBSCRIBE:
        $this->logInfo('UNSUBSCRIBE ' . $list . ' / ' . $email);
        $this->listsService->unsubscribe($list, $email);
        break;
      case self::OPERATION_ACCEPT:
        $this->logInfo('ACCEPT SUBSCRIPTION '  . $list  . ' / ' . $email);
        $this->listsService->handleSubscriptionRequest($list, $email, MailingListsService::MODERATION_ACTION_ACCEPT);
        break;
      case self::OPERATION_REJECT:
        $this->logInfo('REJECT SUBSCRIPTION '  . $list  . ' / ' . $email);
        $this->listsService->handleSubscriptionRequest($list, $email, MailingListsService::MODERATION_ACTION_REJECT, 'test reason');
        break;
      case self::OPERATION_RELOAD:
        // just fetch the status
        break;
      default:
        return self::grumble($this->l->t('Unknown mailing list operation "%s"', $operation));
    }
    $status = $this->listsService->getSubscriptionStatus($list, $email);
    return self::dataResponse([
      'status' =>  $status,
    ]);
  }

  /**
   * Get the status of the queried email-address.
   *
   * @param string $listId The list id.
   *
   * @param string $email Subscription email address.
   *
   * @return DataResponse
   * ```
   * [
   *   'status' => { 'subscribed', 'unsubscribed', 'invited', 'waiting' }
   * ]
   * ```
   *
   * @NoAdminRequired
   */
  public function getStatus(string $listId, string $email):DataResponse
  {
    if (empty($listId)) {
      return self::grumble($this->l->t('List-id must not be empty.'));
    }
    if (empty($email)) {
      return self::grumble($this->l->t('Email-address must not be empty.'));
    }
    $status = $this->listsService->getSubscriptionStatus($listId, $email);
    return self::dataResponse([ 'status' => $status ]);
  }
}
