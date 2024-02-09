<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023, 2024 Claus-Justus Heine
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
use OCP\Group\Events\BeforeUserAddedEvent;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig as ICloudConfig;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin as SubAdminManager;
use OCP\IUserManager;
use OCP\User\Backend\ICreateUserBackend;
use OCP\IUserBackend;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\CloudAccountsService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Do something special if the membership to the orchestra group changes.
 *
 * @todo
 */
class GroupMembershipListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = [ UserAddedEvent::class, UserRemovedEvent::class, BeforeUserAddedEvent::class ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $eventClass = get_class($event);
    if (!in_array($eventClass, self::EVENT)) {
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
    $managementGroupId = $orchestraGroupId . AuthorizationService::MANAGEMENT_GROUP_SUFFIX;

    /** @var CloudAccountsService $cloudAccountsService */
    $cloudAccountsService = $this->appContainer->get(CloudAccountsService::class);

    $group = $event->getGroup();
    try {
      switch ($group->getGID()) {
        default:
          break;
        case $adminGroupId:
          $user = $event->getUser();
          switch ($eventClass) {
            case UserAddedEvent::class:
              $cloudAccountsService->addGroupSubAdmin($user);
              break;
            case UserRemovedEvent::class:
              $cloudAccountsService->removeGroupSubAdmin($user);
              break;
            case BeforeUserAddedEvent::class:
              // nothing
              break;
          }
          break;
        case $managementGroupId:
          $user = $event->getUser();
          $userId = $user->getUID();

          /** @var OrganizationalRolesService $rolesService */
          $rolesService = $this->appContainer->get(OrganizationalRolesService::class);

          $boardMember = $rolesService->getBoardMember($userId);

          /** @var EncryptionService $encryptionService */
          $encryptionService = $this->appContainer->get(EncryptionService::class);
          list(, $emailFromDomain) = array_pad(explode('@', $encryptionService->getConfigValue(ConfigService::EMAIL_FORM_ADDRESS_KEY)), null, 2);

          /** @var IAccountManager $accountManager */
          $accountManager = $this->appContainer->get(IAccountManager::class);
          $account = $accountManager->getAccount($user);

          $personalizedOrchestraEmail = $userId. '@' . $emailFromDomain;
          $emailCollection = $account->getPropertyCollection(IAccountManager::COLLECTION_EMAIL);
          $emailProperty = $emailCollection->getPropertyByValue($personalizedOrchestraEmail);

          switch (true) {
            case ($event instanceof BeforeUserAddedEvent):
              // make in particular sure that the person is also added to
              // the configured orchestra user backend
              $orchestraUserAndGroupBackend = $cloudConfig->getAppValue($appName, ConfigService::USER_AND_GROUP_BACKEND_KEY);
              if (!empty($orchestraUserAndGroupBackend)) {
                $cloudAccountsService->addUserToBackend($user, $orchestraUserAndGroupBackend);
              }
              break;
            case ($event instanceof UserAddedEvent):
              $cloudAccountsService->promoteGroupMembership($user, $group);

              if (empty($boardMember)) {
                $executiveBoardProject = $rolesService->executiveBoardProject();
                $cloudAccountsService->addCloudUserToProject($user, $executiveBoardProject);
              }

              if (empty($emailProperty)) {
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
              // this could be a per-account setting -- or if Dovecot would
              // maintain message tags on a per user basis in shared folders.
              $cloudConfig->setUserValue($userId, 'mail', 'tag-classified-messages', 'false');
              break;
            case ($event instanceof UserRemovedEvent):
              // core already removes the user from all backends

              // remove in any case from the cloud user's emails
              if (!empty($emailProperty)) {
                $emailCollection->removeProperty($emailProperty);
                $accountManager->updateAccount($account);
              }
              if (!empty($boardMember)) {
                // the person must not remain a member of the executive board
                /** @var ProjectService $projectService */
                $projectService = $this->appContainer->get(ProjectService::class);
                $projectService->deleteProjectParticipant($boardMember);
              }
              // @todo
              // We could also remove the user from the orchestra backend, but ...
              break;
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
