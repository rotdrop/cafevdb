<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or1
 * modify it under th52 terms of the GNU GENERAL PUBLIC LICENSE
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

namespace OCA\CAFEVDB\Service;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;

/**
 * Rudimentary service which just checks if a user belongs to the
 * configured orchestra group and/or is a group admin. Also provide
 * contact informations for the group-admins.
 */
class AuthorizationService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var string */
  private $appName;

  /** @var \OCP\IUserManager */
  private $userManager;

  /** @var \OCP\IGroupManager */
  private $groupManager;

  /** @var \OCP\IConfig */
  private $config;

  /** @var string */
  private $userGroup;

  public function __construct(
    $appName
    , IConfig $config
    , IUserManager $userManager
    , IGroupManager $groupManager
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->config = $config;
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->userGroup = $this->config->getAppValue($this->appName, 'usergroup');
  }

  /**
   * Basic check for authorization. Just check if the orchestra group
   * is configured and the given user id is a member of it.
   *
   * @param string $userId Id of the user to check.
   *
   * @return bool Status of the check.
   */
  public function authorized($userId):bool
  {
    return !empty($userId) && !empty($this->userGroup) && $this->groupManager->isInGroup($userId, $this->userGroup);
  }

  /**
   * Check whether the given user-id is a sub-admin for the configured
   * orchestra group.
   *
   * @param string $userId Id of the user to check.
   *
   * @return bool Status of the check.
   */
  public function isAdmin(string $userId):bool
  {
    if (empty($this->userGroup)) {
      return false;
    }
    $user = $this->userManager->get($userId);
    $group = $this->groupManager->get($this->userGroup);

    if (empty($user) || empty($group)) {
      return false;
    }
    return $this->groupSubAdmin->isSubAdminofGroup($user, $group);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
