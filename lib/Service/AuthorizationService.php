<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin as IGroupSubAdminManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

/**
 * Rudimentary service which just checks if a user belongs to the
 * configured orchestra group and/or is a group admin. Also provide
 * contact informations for the group-admins.
 */
class AuthorizationService
{
  use LoggerTrait;

  public const ALL_GROUP_SUFFIX = '';
  public const FRONTEND_GROUP_SUFFIX = '-frontend';
  public const ADDRESSBOOK_GROUP_SUFFIX = '-addressbook';
  public const FILESYSTEM_GROUP_SUFFIX = '-filesystem';
  public const CALENDAR_GROUP_SUFFIX = '-calendar';

  public const PERMISSION_NONE = 0;
  public const PERMISSION_FRONTEND = (1 << 0);
  public const PERMISSION_ADDRESSBOOK = (1 << 1);
  public const PERMISSION_FILESYSTEM = (1 << 2);
  public const PERMISSION_CALENDAR = (1 << 3);
  public const PERMISSION_ALL = self::PERMISSION_FRONTEND|self::PERMISSION_ADDRESSBOOK|self::PERMISSION_FILESYSTEM;

  public const GROUP_SUFFIX_LIST = [
    self::PERMISSION_ALL => self::ALL_GROUP_SUFFIX,
    self::PERMISSION_FRONTEND => self::FRONTEND_GROUP_SUFFIX,
    self::PERMISSION_ADDRESSBOOK => self::ADDRESSBOOK_GROUP_SUFFIX,
    self::PERMISSION_FILESYSTEM => self::FILESYSTEM_GROUP_SUFFIX,
    self::PERMISSION_CALENDAR => self::CALENDAR_GROUP_SUFFIX,
  ];

  /**
   * @var string
   *
   * The group id of cloud users which are allowed to access the backend services like file-system etc.
   */
  private $userGroupId;

  /**
   * @param null|string $appName
   *
   * @param null|string $userId Current logged on user id or null.
   *
   * @param IConfig $config
   *
   * @param IUserManager $userManager
   *
   * @param IGroupManager $groupManager
   *
   * @param IGroupSubAdminManager $groupSubAdminManager
   *
   * @param ILogger $logger In order to satisfy LoggerTrait.
   */
  public function __construct(
    private ?string $appName,
    private ?string $userId,
    protected IConfig $config,
    private IUserManager $userManager,
    private IGroupManager $groupManager,
    private IGroupSubAdminManager $groupSubAdminManager,
    protected ILogger $logger,
  ) {
    $this->userGroupId = $this->config->getAppValue($this->appName, ConfigService::USER_GROUP_KEY);
  }

  /**
   * Log a textual description of the given permission bit-mask.
   *
   * @param null|string $userId The user-id to check, if null use the user-id of
   * the currently logged-on user.
   *
   * @param int $permissions.
   *
   * @param int  $logLevel
   *
   * @return void
   */
  protected function logPermissions(?string $userId, int $permissions, int $logLevel = \OCP\ILogger::INFO):void
  {
    if ($permissions === self::PERMISSION_NONE) {
      $this->log($logLevel, 'User ' . $userId . ' is not allowed to use the app ' . $this->appName);
    } elseif ($permissions === self::PERMISSION_ALL) {
      $this->log($logLevel, 'User ' . $userId . ' has the permission to use all services of the app ' . $this->appName);
    } else {
      $permissionStrings = [];
      if ($permissions & self::PERMISSION_FILESYSTEM) {
        $permissionString[] = 'filesystem';
      }
      if ($permissions & self::PERMISSION_CALENDAR) {
        $permissionString[] = 'calendar';
      }
      if ($permissions & self::PERMISSION_ADDRESSBOOK) {
        $permissionString[] = 'addressbook';
      }
      if ($permissions & self::PERMISSION_FRONTEND) {
        $permissionString[] = 'frontend';
      }
      $this->log($logLevel, 'User ' . $userId . ' has permissions for the following services of the app ' . $this->appName . ': ' . implode(',', $permissionStrings));
    }
  }

  /**
   * Compute the permission mask for the given or the currently logged-on user.
   *
   * @param null|string $userId The user-id to check, if null use the user-id of
   * the currently logged-on user.
   *
   * @return int The bit-mask of permissions, based on the group-memberships
   * of the user.
   */
  public function getUserPermissions(?string $userId):int
  {
    if (empty($userId)) {
      $userId = $this->userId;
    }
    if (empty($userId) || empty($this->userGroupId)) {
      return self::PERMISSION_NONE;
    }
    $userPermissions = self::PERMISSION_NONE;
    foreach (self::GROUP_SUFFIX_LIST as $permissions => $suffix) {
      $groupId = $this->userGroupId . $suffix;
      if ($this->groupManager->isInGroup($userId, $groupId)) {
        $userPermissions |= $permissions;
      }
      if ($userPermissions == self::PERMISSION_ALL) {
        break;
      }
    }
    // $this->logPermissions($userId, $userPermissions);
    return $userPermissions;
  }

  /**
   * Basic check for authorization. Permissions are granted on the basis of group memberships.
   *
   * @param null|string $userId Id of the user to check.
   *
   * @param int $requestedPermissions The permissions to check for. Defaults to everything.
   *
   * @return bool Status of the check.
   */
  public function authorized(?string $userId, int $requestedPermissions = self::PERMISSION_ALL):bool
  {
    $userPermissions = $this->getUserPermissions($userId);
    // $this->logInfo('PERMISSION CHECK: ' . (int)($userPermissions & $requestedPermissions) . ' vs ' . $requestedPermissions);
    return $requestedPermissions == ($userPermissions & $requestedPermissions);
  }

  /**
   * Check whether the given user-id is a sub-admin for the configured
   * orchestra group.
   *
   * @param null|string $userId Id of the user to check.
   *
   * @return bool Status of the check.
   */
  public function isAdmin(?string $userId):bool
  {
    if (empty($userId)) {
      $userId = $this->userId;
    }
    if (empty($this->userGroupId)) {
      return false;
    }
    $user = $this->userManager->get($userId);
    $group = $this->groupManager->get($this->userGroupId);

    if (empty($user) || empty($group)) {
      return false;
    }
    return $this->groupSubAdminManager->isSubAdminofGroup($user, $group);
  }
}
