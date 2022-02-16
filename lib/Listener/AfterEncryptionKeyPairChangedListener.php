<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\Notification\IManager as NotificationManager;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events\AfterEncryptionKeyPairChanged as HandledEvent;
use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Notifications\Notifier;

class AfterEncryptionKeyPairChangedListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void
  {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    // initialize only now in order to keep the overhead for unhandled events small
    $this->appName = $this->appContainer->get('appName');
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $ownerId = $event->getOwnerId();
    $oldKeyPair = $event->getOldKeyPair();
    $newKeyPair = $event->getNewKeyPair();

    /** @var AsymmetricKeyService $keyService */
    $keyService = $this->appContainer->get(AsymmetricKeyService::class);

    $this->logInfo('HELLO WORLD ' . print_r($oldKeyPair, true));

    if (empty($oldKeyPair[AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG])) {
      // this cannot be helped, remove the old values. In the future we may
      // want to enqueue a restore request if this happened
      $keyService->removeSharedPrivateValues($ownerId);

      // for the code below install the new keys
      $cryptor = $keyService->getCryptor($ownerId)
        ->setPrivateKey($newKeyPair[AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG] ?? null)
        ->setPublicKey($newKeyPair[AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG] ?? null);

      // @todo the following should be moved into a service class

      /** @var \OCP\IConfig $cloudConfig */
      $cloudConfig = $this->appContainer->get(\OCP\IConfig::class);
      $cloudConfig->setUserValue($ownerId, $appName, Notifier::RECRYPT_USER_SUBJECT, $newKeyPair[AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG]);

      /** @var NotificationManager $notificationManager */
      $notificationManager = $this->appContainer->get(NotificationManager::class);
      $notification = $notificationManager->createNotification();

      $notification->setApp($this->appContainer->get('appName'))
        ->setDateTime(new \DateTime)
        ->setObject('owner_id', $ownerId)
        ->setSubject(Notifier::RECRYPT_USER_SUBJECT, [ AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG => $newKeyPair[AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG] ])
        ->addAction($notification->createAction()
          ->setLabel(Notifier::ACCEPT_ACTION)
          ->setLink('user_recrypt_request', 'POST'))
        ->addAction($notification->createAction()
          ->setLabel(Notifier::DECLINE_ACTION)
          ->setLink('user_recrypt_request', 'DELETE'));

      /** @var OrganizationalRolesService $organizationalRoles */
      $organizationalRoles = $this->appContainer->get(OrganizationalRolesService::class);
      /** @var \OCP\IUser $groupAdmin */
      foreach ($organizationalRoles->getGroupAdmins() as $groupAdmin) {
        $notification->setUser($groupAdmin->getUID());
        $notificationManager->notify($notification);
      }

      // $this->logInfo('CREATED NOTIFICATION ' . (new \Exception('blah'))->getTraceAsString());

    } else {
      $configValues = $keyService->getSharedPrivateValues($ownerId);
      $cryptor = $keyService->getCryptor($ownerId)
        ->setPrivateKey($newKeyPair[AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG] ?? null)
        ->setPublicKey($newKeyPair[AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG] ?? null);
      foreach ($configValues as $configKey => $configValue)  {
        $keyService->setSharedPrivateValue($ownerId, $configKey, $configValue);
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
