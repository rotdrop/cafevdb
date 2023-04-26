<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use Throwable;

use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig as ICloudConfig;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\IAccountPropertyCollection;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;

/**
 * Do something special if the membership to the orchestra group changes.
 */
class GroupMembershipListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = [ UserAddedEvent::class, UserRemovedEvent::class ];

  /** @var IAppContainer */
  private $appContainer;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof UserAddedEvent) && !($event instanceof UserRemovedEvent)) {
      return;
    }

    /** @var UserAddedEvent $event */
    /** @var UserRemovedEvent $event */

    $appName = $this->appContainer->get('appName');

    /** @var ICloudConfig $cloudConfig */
    $cloudConfig = $this->appContainer->get(ICloudConfig::class);
    $orchestraGroupId = $cloudConfig->getAppValue($appName, ConfigService::USER_GROUP_KEY);

    if (empty($orchestraGroupId)) {
      return;
    }

    $group = $event->getGroup();
    if ($group->getGID() != $orchestraGroupId) {
      return;
    }

    $user = $event->getUser();

    /** @var IAccountManager $accountManager */
    $accountManager = $this->appContainer->get(IAccountManager::class);
    $account = $accountManager->getAccount($user);

    /** @var EncryptionService $encryptionService */
    $encryptionService = $this->appContainer->get(EncryptionService::class);

    list(, $emailFromDomain) = array_pad(explode('@', $encryptionService->getConfigValue(ConfigService::EMAIL_FORM_ADDRESS_KEY)), null, 2);
    if (empty($emailFromDomain)) {
      return;
    }

    $personalizedOrchestraEmail = $user->getUID() . '@' . $emailFromDomain;

    $emailCollection = $account->getPropertyCollection(IAccountManager::COLLECTION_EMAIL);

    $emailProperty = $emailCollection->getPropertyByValue($personalizedOrchestraEmail);

    try {
      if ($event instanceof UserAddedEvent) {
        if (empty($emailProperty)) {
          $emailCollection->addPropertyWithDefaults($personalizedOrchestraEmail);
          $emailProperty = $emailCollection->getPropertyByValue($personalizedOrchestraEmail);
          $emailProperty->setLocallyVerified(IAccountManager::VERIFIED);
          $emailProperty->setVerified(IAccountManager::VERIFIED);
          $accountManager->updateAccount($account);
        }
      } else {
        if (!empty($emailProperty)) {
          $emailCollection->removeProperty($emailProperty);
          $accountManager->updateAccount($account);
        }
      }
    } catch (Throwable $t) {
      try {
        $this->logger = $this->appContainer->get(ILogger::class);
        $this->logException($t, 'Unable to update account for user ' . $user->getUID());
      } catch (Throwable $ignore) {
        // ignore
      }
    }

    return;
  }
}
