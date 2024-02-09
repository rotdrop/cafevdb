<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Throwable;
use Exception;
use RuntimeException;

use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\IConfig as CloudConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Backend\INamedBackend;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\Group\Backend\ISetDisplayNameBackend;
use OCP\Group\Backend\ICreateGroupBackend;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin as ISubAdminManager;
use OCP\IUser;
use OCP\IUserBackend;
use OCP\IUserManager;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use OCP\UserInterface;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;

/**
 * Add some cloud account service related actions which are needed by the
 * current layout. More specifically:
 *
 * - the orchestra users group has also a meaning outside of the cloud, in
 *   particular it is used by the email services (Dovecot, Postfix). It is
 *   strictly necessary that members of the executive board are also known to
 *   the LDAP backend.
 *
 * - it is also necessary to be able to add further less-privileged members to
 *   the various authorization groups (file system access, calendars and so
 *   on). This would not be a problem with the builtin Database group backend,
 *   as it does not check whether the group-membership is backed by an
 *   existing user. Unfortunately, the NC core user and group management for
 *   one does not promote group creation to all backends, and additionally
 *   errors out if the first backend it accesses refuses to add a user to a
 *   group.
 */
class CloudAccountsService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\EnsureEntityTrait;

  /**
   * @param string $appName
   *
   * @param LoggerInterface $logger
   *
   * @param CloudConfig $cloudConfig
   *
   * @param IAvatarManager $avatarManager
   *
   * @param IL10NFactory $l10nFactory
   *
   * @param IEventDispatcher $eventDispatcher
   *
   * @param ISecureRandom $secureRandom
   *
   * @param IUserManager $userManager
   *
   * @param NewUserMailHelper $newUserMailHelper
   *
   * @param IGroupManager $groupManager
   *
   * @param ISubAdminManager $subAdminManager
   *
   * @param EntityManager $entityManager
   *
   * @param ProjectService $projectService
   */
  public function __construct(
    protected string $appName,
    protected LoggerInterface $logger,
    protected CloudConfig $cloudConfig,
    protected IAvatarManager $avatarManager,
    protected IL10NFactory $l10nFactory,
    protected IEventDispatcher $eventDispatcher,
    protected ISecureRandom $secureRandom,
    protected IUserManager $userManager,
    protected NewUserMailHelper $newUserMailHelper,
    protected IGroupManager $groupManager,
    protected ISubAdminManager $subAdminManager,
    protected EntityManager $entityManager,
    protected ProjectService $projectService,
  ) {
  }

  /**
   * Add the given user the the specified backend.
   *
   * @param IUser $user
   *
   * @param string|UserInterface $newBackend
   *
   * @return bool
   *
   * @throws Throwable
   */
  public function addUserToBackend(IUser $user, string|UserInterface $newBackend)
  {
    if (is_string($newBackend)) {
      $newBackend = array_filter(
        $this->userManager->getBackends(),
        fn(IUserBackend $backend) => $backend->getBackendName() == $newBackend,
      );
      if (count($newBackend) != 1) {
        return false;
      }
      $newBackend = reset($newBackend);
    }

    $oldBackend = $user->getBackend();

    if (!($oldBackend instanceof IUserBackend) || !($newBackend instanceof IUserBackend)) {
      $this->logError('Only support moving between sane user-backends.');
      return false;
    }

    $oldBackendName = $oldBackend->getBackendName();
    $newBackendName = $newBackend->getBackendName();

    if ($oldBackendName == $newBackendName) {
      return true;
    }

    $email = $user->getEMailAddress() ?? '';

    if ($email === '') {
      // This also means that the setup is somehow totally broken ...
      $this->logError('User "' . $userId . '" does not have an email.');
      return false;
    }

    $userId = $user->getUID();
    $displayName = $user->getDisplayName();
    $quota = $user->getQuota();

    // try to transfer the avatar
    /** @var \OCP\IAvatar $avatar */
    $avatar = $this->avatarManager->getAvatar($userId);
    if ($avatar->isCustomAvatar()) {
      $avatarImage = $avatar->get(-1);
    }

    $newUser = $this->doAddUserToBackend(
      userId: $userId,
      UserInterface: $newBackend,
      displayName: $displayName,
      email: $email,
      quota: $quota,
      avatarImage: $avatarImage ?? null,
    );

    if (empty($newUser)) {
      $this->logger->error('Failed to transfer "' . $userId . '" to the user-backend "' . $newBackend->getBackendName());
      return false;
    }

    // If this has succeeded, perhaps the user should be deleted in the old backend, if possible
    try {
      /** @var UserInterface $oldBackend */
      $oldBackend->deleteUser($user->getUID());
      $this->logInfo('Deleted user "' . $userId . '" in old backend "' . $oldBackendName . '"');
    } catch (Throwable $t) {
      $this->logException($t, 'Cannot delete user "' . $userId . '" in old backend "' . $oldBackendName . '"');
    }

    return true;
  }

  /**
   * @param string $userId
   *
   * @param UserInterface $backend
   *
   * @param string $displayName
   *
   * @param string $email
   *
   * @param string $quota
   *
   * @param null|IAvatar $avatarImage
   *
   * @return null|IUser
   */
  protected function doAddUserToBackend(
    string $userId,
    UserInterface $backend,
    string $displayName,
    string $email,
    string $quota,
    ?IAvatar $avatarImage,
  ):?IUser {
    $backendName = $backend->getBackendName();
    if ($backend->userExists($userId)) {
      $this->logWarn('User "' . $userId . '" already exists in backend "' . $backendName . '"');
      if (!($this->userManager instanceof \OC\User\Manager) || !method_exists($this->userManager, 'getUserObject')) {
        $this->logError('The IUserManager implementation seems to have changed, cannot retrieve the user object from the backend.');
        return null;
      }
      /** @var \OC\User\Manager $userManager */
      $userManager = $this->userManager;
      return $userManager->getUserObject($userId, $backend);
    }

    $passwordEvent = new GenerateSecurePasswordEvent();
    $this->eventDispatcher->dispatchTyped($passwordEvent);
    $password = $passwordEvent->getPassword();

    if ($password === null) {
      // Fallback: ensure to pass password_policy in any case
      $password = $this->secureRandom->generate(10)
        . $this->secureRandom->generate(1, ISecureRandom::CHAR_UPPER)
        . $this->secureRandom->generate(1, ISecureRandom::CHAR_LOWER)
        . $this->secureRandom->generate(1, ISecureRandom::CHAR_DIGITS)
        . $this->secureRandom->generate(1, ISecureRandom::CHAR_SYMBOLS);
    }
    $generatePasswordResetToken = true;

    try {
      $newUser = $this->userManager->createUserFromBackend($userId, $password, $backend);

      $this->logger->info('Successful addUser call with userid: ' . $userId, ['app' => 'ocs_api']);

      $newUser->setDisplayName($displayName);
      $newUser->setQuota($quota);

      if (!empty($avatarImage)) {
        try {
          $avatar = $this->avatarManager->getAvatar($userId);
          $avatar->set($avatarImage);
        } catch (Throwable $t) {
          $this->logException($t, 'Unable to restore the avatar image after moving "' . $userId . '" to the new backend "' . $backendName . '".');
        }
      }

      $newUser->setEMailAddress($email);
      if ($this->cloudConfig->getAppValue('core', 'newUser.sendEmail', 'yes') === 'yes') {
        try {
          $emailTemplate = $this->newUserMailHelper->generateTemplate($newUser, $generatePasswordResetToken);
          $this->newUserMailHelper->sendMail($newUser, $emailTemplate);
        } catch (Throwable $e) {
          // Mail could be failing hard or just be plain not configured
          // Logging error as it is the hardest of the two
          $this->logger->error(
            "Unable to send the invitation mail to $email",
            [
              'app' => 'ocs_api',
              'exception' => $e,
            ]
          );
        }
      }
      return $newUser;
    } catch (HintException $e) {
      $this->logger->warning(
        'Failed addUser attempt with hint exception.',
        [
          'app' => 'ocs_api',
          'exception' => $e,
        ]
      );
      return null;
    } catch (InvalidArgumentException $e) {
      $this->logger->error(
        'Failed addUser attempt with invalid argument exception.',
        [
          'app' => 'ocs_api',
          'exception' => $e,
        ]
      );
      return null;
    } catch (Throwable $e) {
      $this->logger->error(
        'Failed addUser attempt with exception.',
        [
          'app' => $this->appName,
          'exception' => $e
        ]
      );
      return null;
    }
  }

  /**
   * Promote a changed group display name to all backends which provide the
   * group and are able to change the display name.
   *
   * @param IGroup $group
   *
   * @return void
   */
  public function promoteGroupDisplayName(IGroup $group):void
  {
    $gid = $group->getGID();
    $displayName = $group->getDisplayName();
    $groupBackendNames = $group->getBackendNames();
    foreach ($this->groupManager->getBackends() as $backend) {
      if (!($backend instanceof ISetDisplayNameBackend)
          || (($backend instanceof INamedBackend) && !in_array($backend->getBackendName(), $groupBackendNames))
          || !in_array(get_class($backend), $groupBackendNames)) {
        continue;
      }
      if (($backend instanceof IGetDisplayNameBackend) && $backend->getDisplayName($gid) == $displayName) {
        continue;
      }
      $backend->setDisplayName($gid, $displayName);
      // ignore the result, not so important
    }
  }

  /**
   * Greedily try to add the user to all group backends.
   *
   * @param IUser $user
   *
   * @param IGroup $group
   *
   * @return void
   */
  public function promoteGroupMembership(IUser $user, IGroup $group)
  {
    $groupId = $group->getGID();
    foreach ($this->groupManager->getBackends() as $backend) {
      if ($backend->implementsActions(\OC\Group\Backend::ADD_TO_GROUP)) {
        $userId = $user->getUID();
        try {
          if (!$backend->inGroup($userId, $groupId)) {
            $backend->addToGroup($userId, $groupId);
          }
        } catch (Throwable $t) {
          // Can happen if the user does not exist in the backend
          $this->logException($t, 'Backend ' . get_class($backend) . ' cannot add user ' . $user->getUID());
        }
      }
    }
  }

  /**
   * Ensure that the given group exists in all required backends. The use case
   * is to add people to user groups although they do not live in the primary
   * group backend. Nextcloud's group manager just creates groups in one
   * backend, then stops and also uses a different ordering of backends
   * depending on the manager method called. In particular the database group
   * backend allows to add non-existing users to its user-group mapping. This
   * is what we want.
   *
   * @param IGroup $group
   *
   * @param array $requiredBackends Array with the names of the required
   * backends.
   *
   * @return array<string, bool> Success statuses for the required
   * backends. \true means the group is present in the respective backend.
   *
   * @todo Is this really necessary? We do need the management group in the
   * backend, but do we really need also all the others?
   */
  public function ensureGroupBackends(IGroup $group, array $requiredBackends = []):array
  {
    if (empty($requiredBackends)) {
      $orchestraUserAndGroupBackend = $this->cloudConfig->getAppValue(
        $this->appName,
        ConfigService::USER_AND_GROUP_BACKEND_KEY
      );
      $requiredBackends = [ $orchestraUserAndGroupBackend, 'Database' ];
    }

    $status = array_fill_keys($requiredBackends, true); // set to \false on failure below
    $gid = $group->getGID();
    $displayName = $group->getDisplayName();
    $groupBackendNames = $group->getBackendNames();
    $missingBackends = array_diff($requiredBackends, $groupBackendNames);
    foreach ($this->groupManager->getBackends() as $backend) {
      if (!($backend instanceof INamedBackend)) {
        continue;
      }
      $backendName = $backend->getBackendName();
      if (!in_array($backendName, $missingBackends)) {
        continue;
      }
      if (!($backend instanceof ICreateGroupBackend)) {
        $this->logError('Group ' . $gid . ' is missing in backend "' . $backendName . '", but the backend is not able to create groups.');
        $status[$backendName] = false;
        continue;
      }
      if (!$backend->createGroup($gid)) {
        $this->logError('Group ' . $gid . ' is missing in backend "' . $backendName . '", but the backend failed to create it.');
        $status[$backendName] = false;
        continue;
      }
      if (!$backend->groupExists($gid)) {
        $this->logError('Backend "' . $backendName . '" did not error out creating the group "' . $gid . '" but the group does not exist in the backend.');
        $status[$backendName] = false;
        continue;
      }
      if (!($backend instanceof ISetDisplayNameBackend)) {
        continue;
      }
      if (($backend instanceof IGetDisplayNameBackend) && $backend->getDisplayName($gid) == $displayName) {
        continue;
      }
      $backend->setDisplayName($gid, $displayName);
      // ignore the result, not so important
    }
    if (($this->groupManager instanceof \OC\Group\Manager) && method_exists($this->groupManager, 'clearCaches')) {
      $this->groupManager->clearCaches();
    }
    return $status;
  }

  /**
   * @param IUser $user
   *
   * @param null|array $gids
   *
   * @return void
   */
  public function addGroupSubAdmin(IUser $user, array $gids = null):void
  {
    $groups = $this->getAdminstrableGroups($gids);
    foreach ($groups as $group) {
      if (!empty($group) && !$this->subAdminManager->isSubAdminOfGroup($user, $group)) {
        $this->subAdminManager->createSubAdmin($user, $group);
      }
    }

  }

  /**
   * @param IUser $user
   *
   * @param null|array $gids
   *
   * @return void
   */
  public function removeGroupSubAdmin(IUser $user, ?array $gids = null):void
  {
    $groups = $this->getAdminstrableGroups($gids);
    foreach ($groups as $group) {
      if (!empty($group) && $this->subAdminManager->isSubAdminOfGroup($user, $group)) {
        $this->subAdminManager->deleteSubAdmin($user, $group);
      }
    }
  }

  /**
   * @return array<int, string> Return the array of orchestra group GIDs which
   * should have the members of the orchestra admin group as sub-admins.
   */
  public function getAdminstrableGroupsGIDs():array
  {
    $orchestraGroupId = $this->cloudConfig->getAppValue($this->appName, ConfigService::USER_GROUP_KEY);
    $administrableGroupGids = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $groupSuffix) {
      $administrableGroupGids[] = $orchestraGroupId . $groupSuffix;
    }
    $administrableGroupGids[] = CloudUserConnectorService::CLOUD_USER_GROUP_ID;

    return $administrableGroupGids;
  }

  /**
   * @param null|array $gids
   *
   * @return array<int, ?IGroup> Return the array of orchestra groups which
   * should have the members of the orchestra admin group as sub-admins.
   */
  public function getAdminstrableGroups(?array $gids = null):array
  {
    if ($gids === null) {
      $gids = $this->getAdminstrableGroupGIDs();
    }
    $groups = [];
    foreach ($gids as $gid) {
      $groups[$gid] = $this->groupManager->get($gid);
    }
    return $groups;
  }

  /**
   * @param string|IUser $user
   *
   * @param int|Entities\Project $project
   *
   * @return ?Entities\ProjectParticipant
   */
  public function addCloudUserToProject(string|IUser $user, int|Entities\Project $project):?Entities\ProjectParticipant
  {
    $project = $this->ensureProject($project);
    if (empty($project)) {
      return null;
    }
    if (is_string($user)) {
      $user = $this->userManager->get($user);
    }
    if (empty($user)) {
      return null;
    }
    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->findByUserId($user->getUID());
    if (empty($musician)) {
      return null;
    }

    $this->projectService->addMusicians([ $musician->getId(), ], $project->getId());

    return $musician->getProjectParticipantOf($project);
  }
}
