<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class UserLoggedInEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ Event1::class, Event2::class ];

  /** @var string */
  private $appName;

  /** @var IGroupManager */
  private $groupManager;

  /** @var EncryptionService */
  private $encryptionService;

  public function __construct(
    /*$appName
      , */IGroupManager $groupManager
    , IConfig $containerConfig
    , EncryptionService $encryptionService
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = ''; //$appName; // can this work?
    $this->groupManager = $groupManager;
    $this->encryptionService = $encryptionService;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf Event1 && !($event instanceOf Event2))) {
      return;
    }

    $this->logInfo("Hello Login-Handler!");

    $groupId = $this->encryptionService->getAppValue('usergroup');
    $user = $event->getUser();
    $userId = $user->getUID();
    $password = $event->getPassword();
    $tokenLogin = $event->isTokenLogin();

    if (!empty($groupId) && $this->groupManager->isInGroup($userId, $groupId)) {
      // Fetch the encryption key and store in the session data
      $this->encryptionService->initUserPrivateKey($userId, $password);
      $this->encryptionService->initAppEncryptionKey($userId);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
