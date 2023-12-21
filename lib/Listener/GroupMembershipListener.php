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
use OCP\IGroupManager;
use OCP\Group\ISubAdmin as SubAdminManager;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
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

    $adminGroupId = $orchestraGroupId . ConfigService::ADMIN_GROUP_SUFFIX;

    $group = $event->getGroup();
    try {
      switch ($group->getGID()) {
        default:
          break;
        case $adminGroupId:
          $user = $event->getUser();

          $administrableGroupGids = [];
          foreach (AuthorizationService::GROUP_SUFFIX_LIST as $groupSuffix) {
            $administrableGroupGids[] = $orchestraGroupId . $groupSuffix;
          }

          /** @var CloudUserConnectorService $cloudUserConnector */
          $cloudUserConnector = $this->appContainer->get(CloudUserConnectorService::class);
          if ($cloudUserConnector->haveCloudUserBackendConfig()) {
            $administrableGroupGids[] = CloudUserConnectorService::CLOUD_USER_GROUP_ID;
          }
          /** @var SubAdminManager $subAdminManager*/
          $subAdminManager = $this->appContainer->get(SubAdminManager::class);

          /** @var IGroupManager $groupManager */
          $groupManager = $this->appContainer->get(IGroupManager::class);

          if ($event instanceof UserAddedEvent) {
            foreach ($administrableGroupGids as $gid) {
              $group = $groupManager->get($gid);
              if (!empty($group) && !$subAdminManager->isSubAdminOfGroup($user, $group)) {
                $subAdminManager->createSubAdmin($user, $group);
              }
            }
          } else {
            foreach ($administrableGroupGids as $gid) {
              $group = $groupManager->get($gid);
              if (!empty($group) && $subAdminManager->isSubAdminOfGroup($user, $group)) {
                $subAdminManager->deleteSubAdmin($user, $group);
              }
            }
          }
          break;
        case $orchestraGroupId:
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
      } // switch
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
