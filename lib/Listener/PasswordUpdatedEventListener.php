<?php
/*
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\User\Events\PasswordUpdatedEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\AuthorizationService;

class PasswordUpdatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var ISubAdmin */
  private $subAdmin;

  /** @var IUserManager */
  private $userManager;

  /** @var IGroupManager */
  private $groupManager;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }

    $user = $event->getUser();
    $userId = $user->getUID();

    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    /** @var AuthorizationService $authorizationService */
    $authorizationService = $this->appContainer->get(AuthorizationService::class);
    if (!$authorizationService->authorized($userId)) {
      // Just make sure that the private/public key-pair is updated. For users
      // in the orchestra-group this is done below. We may want to change this
      // special handling of the orchestra group and instead listen in another
      // listener on the events generated in initEncryptionKeyPair().
      try {
        /** @var AsymmetricKeyService $keyService */
        $keyService = $this->appContainer->get(AsymmetricKeyService::class);
        $keyService->initEncryptionKeyPair($userId, $event->getPassword(), forceNeyKeyPair: true);
      } catch (\Throwable $t) {
        $this->logException($t, $this->l->t('Unable to initialize asymmetric key-pari for user "%s".', $userId ));
      }
      return;
    }

    // initialize only now in order to keep the overhead for unhandled events small
    $this->appName = $this->appContainer->get('appName');
    $this->subAdmin = $this->appContainer->get(ISubAdmin::class);
    $this->groupManager = $this->appContainer->get(IGroupManager::class);
    $this->userManager = $this->appContainer->get(IUserManager::class);
    $this->encryptionService = $this->appContainer->get(EncryptionService::class);

    /** @var AsymmetricKeyService $keyService */
    $keyService = $this->appContainer->get(AsymmetricKeyService::class);

    $needNewKey = false;
    $encUserId = $this->encryptionService->getUserId();
    if ($userId != $encUserId) {
      // This potentially means that a group manager or admin has
      // changed the password. In this case the encrypted personal
      // values are lost and must be reset.
      $this->logInfo('Mismatching users: '.$userId.' / '.$this->encryptionService->getUserId());
      if (!empty($encUserId)) {
        $encUser = $this->userManager->get($encUserId);
        $group = $this->groupManager->get($groupId);
        if ($this->groupManager->isAdmin($encUserId) || $this->subAdmin->isSubAdminOfGroup($encUser, $group)) {
          $this->logInfo('Admin password change for user ' . $userId . ', removing personal encrypted data.');
          $this->encryptionService->deleteUserKeyPair($userId);
          $needNewKey = true;
        }
      } else {
        $this->logInfo('EncryptionService service is not bound, assuming password was forgotten by ' . $userId . ', removing personal encrypted data.');
        $this->encryptionService->deleteUserKeyPair($userId);
        $needNewKey = true;
      }
    } else if (!$this->encryptionService->bound()) {
      $this->logInfo('Encryption service is not bound, assuming password was forgotten by ' . $userId . ', removing personal encrypted data.');
      $this->encryptionService->deleteUserKeyPair($userId);
      $needNewKey = true;
    } else {
      $this->logInfo('Trying to recrypt personal encrypted data for ' . $userId);
      try {
        $this->encryptionService->recryptSharedPrivateValues($event->getPassword());
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    if ($needNewKey) {
      // generate a new key
      try {
        /** @var AsymmetricKeyService $keyService */
        $keyService = $this->appContainer->get(AsymmetricKeyService::class);
        $keyService->initEncryptionKeyPair($userId, $event->getPassword(), forceNeyKeyPair: true);
      } catch (\Throwable $t) {
        $this->logException($t, $this->l->t('Unable to initialize asymmetric key-pair for user "%s".', $userId ));
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
