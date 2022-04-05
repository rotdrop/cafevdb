<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
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

class MailingListsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const OPERATION_INVITE = 'invite';
  const OPERATION_SUBSCRIBE = 'subscribe';
  const OPERATION_UNSUBSCRIBE = 'unsubscribe';
  const OPERATION_ACCEPT = 'accept';
  const OPERATION_REJECT = 'reject';
  const OPERATIONS = [
    self::OPERATION_INVITE,
    self::OPERATION_SUBSCRIBE,
    self::OPERATION_UNSUBSCRIBE,
    self::OPERATION_ACCEPT,
    self::OPERATION_REJECT,
  ];

  /** @var MailingListsService */
  private $listsService;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , MailingListsService $listsService
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->listsService = $listsService;
    $this->l = $this->l10n();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($operation, $list, $email, $displayName = null, $role = null)
  {
    if ($list == 'announcements') {
      $list = $this->getConfigValue('announcementsMailingList');
    }
    switch ($operation) {
      case self::OPERATION_INVITE:
        $this->logInfo('INVITE ' . $list . ' / ' . $email);
        $this->listsService->invite($list, email: $email, displayName: $displayName);
        $status = 'invited';
        break;
      case self::OPERATION_SUBSCRIBE:
        $this->logInfo('SUBSCRIBE ' . $list . ' / ' . $email);
        $this->listsService->subscribe($list, email: $email, displayName: $displayName, role: $role);
        $status = 'subscribed';
        break;
      case self::OPERATION_UNSUBSCRIBE:
        $this->logInfo('UNSUBSCRIBE ' . $list . ' / ' . $email);
        $this->listsService->unsubscribe($list, $email);
        $status = 'unsubscribed';
        break;
      case self::OPERATION_ACCEPT:
        break;
      case self::OPERATION_REJECT:
        break;
      default:
        return self::grumble($this->l->t('Unknown mailing list operation "%s"', $operation));
    }
    if (empty($status)) {
      return self::grumble($this->l->t('List-operations are not yet implemented.'));
    }
    return self::dataResponse([
      'status' =>  $status,
    ]);
  }

  /**
   * @NoAdminRequired
   *
   * Get the status of the queried email-address.
   *
   * @return array
   * ```
   * [
   *   'status' => { 'subscribed', 'unsubscribed', 'invited', 'waiting' }
   * ]
   * ```
   *
   */
  public function getStatus(string $listId, string $email)
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
