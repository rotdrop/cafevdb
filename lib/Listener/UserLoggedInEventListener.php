<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Listener;

use OCP\User\Events\UserLoggedInEvent as Event1;
use OCP\User\Events\UserLoggedInWithCookieEvent as Event2;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\AuthorizationService;

class UserLoggedInEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ Event1::class, Event2::class ];

  /** @var string */
  private $appName;

  /** @var AuthorizationService */
  private $authorization;

  public function __construct(
    $appName
    , AuthorizationService $authorization
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->authorization = $authorization;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function handle(Event $event): void
  {
    return; // ATM not needed

    if (!($event instanceOf Event1) && !($event instanceOf Event2)) {
      return;
    }

    $userId = $event->getUser()->getUID();

    if (!$this->authorization->authorized($userId)) {
      return;
    }

    // the listener should not throw ...
    try {
      // in principle the constructor should do it all, i.e. generate
      // any missing keys and check for the global encryption key

      /** @var EncryptionService $encryptionService */
      $encryptionService = \OC::$server->query(EncryptionService::class);

      // but play safe
      $encryptionService->bind($userId, $event->getPassword());
      $encryptionService->initAppEncryptionKey();
    } catch (\Throwable $t) {
      $this->logException($t, $this->l->t('Unable to bind encryption service for user "%s".', $userId));
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
