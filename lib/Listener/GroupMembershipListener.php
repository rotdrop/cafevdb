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
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Do something special if the membership to the orchestra group changes.
 */
class GroupMembershipListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

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

    $userId = $user->getUID();

    $personalizedOrchestraEmail = $userId. '@' . $emailFromDomain;

    $emailCollection = $account->getPropertyCollection(IAccountManager::COLLECTION_EMAIL);

    $emailProperty = $emailCollection->getPropertyByValue($personalizedOrchestraEmail);

    try {
      if ($event instanceof UserAddedEvent) {
        if (empty($emailProperty)) {
          $executiveBoardProjectId = $encryptionService->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_ID_KEY);
          if ($executiveBoardProjectId >= 0) {
            return;
          }
          $this->entityManager = $this->appContainer->get(EntityManager::class);

          $repository = $this->getDatabaseRepository(Entities\ProjectParticipant::class);

          /** @var Entities\ProjectParticipant $boardMember */
          $boardMember = $repository->findOneBy([
            'project' => $executiveBoardProjectId,
            'musician.userIdSlug' => $userId,
          ]);
          if (empty($boardMember)) {
            // only enforce the email-address for executive board members
            return;
          }

          // add it to the cloud account s.t. email provisioning may work ...
          $emailCollection->addPropertyWithDefaults($personalizedOrchestraEmail);
          $emailProperty = $emailCollection->getPropertyByValue($personalizedOrchestraEmail);
          $emailProperty->setLocallyVerified(IAccountManager::VERIFIED);
          $emailProperty->setVerified(IAccountManager::VERIFIED);
          $accountManager->updateAccount($account);

          // add it to the database as well
          $boardMember->getMusician()->addEmailAddress($personalizedOrchestraEmail);
          $this->flush();
        }

        // message tagging on shared email accounts is really really
        // annoying as it troubles also all other users. Would be nice if
        // this could be a per-account setting.
        $cloudConfig->setUserValue($userId, 'mail', 'tag-classified-messages', 'false');

      } else {
        // remove in any case from the cloud user's emails
        if (!empty($emailProperty)) {
          $emailCollection->removeProperty($emailProperty);
          $accountManager->updateAccount($account);
        }

        $repository = $this->getDatabaseRepository(Entities\ProjectParticipant::class);

        /** @var Entities\ProjectParticipant $boardMember */
        $boardMember = $repository->findOneBy([
          'project' => $executiveBoardProjectId,
          'musician.userIdSlug' => $userId,
        ]);
        if (empty($boardMember)) {
          return;
        }

        // the person must not remain a member of the executive board
        /** @var ProjectService $projectService */
        $projectService = $this->appContainer->get(ProjectService::class);
        $projectService->deleteProjectParticipant($boardMember);
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
