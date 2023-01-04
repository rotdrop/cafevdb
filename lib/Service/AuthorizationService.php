<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

/**
 * Rudimentary service which just checks if a user belongs to the
 * configured orchestra group and/or is a group admin. Also provide
 * contact informations for the group-admins.
 */
class AuthorizationService
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  /** @var string */
  private $appName;

  /** @var \OCP\IUserManager */
  private $userManager;

  /** @var \OCP\IGroupManager */
  private $groupManager;

  /** @var IGroupSubAdminManager */
  private $groupSubAdminManager;

  /** @var \OCP\IConfig */
  private $config;

  /** @var string */
  private $userGroup;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IConfig $config,
    IUserManager $userManager,
    IGroupManager $groupManager,
    IGroupSubAdminManager $groupSubAdminManager,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->config = $config;
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
    $this->groupSubAdminManager = $groupSubAdminManager;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->userGroup = $this->config->getAppValue($this->appName, 'usergroup');
  }
  // phpcs:enable

  /**
   * Basic check for authorization. Just check if the orchestra group
   * is configured and the given user id is a member of it.
   *
   * @param null|string $userId Id of the user to check.
   *
   * @return bool Status of the check.
   */
  public function authorized(?string $userId):bool
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
    return $this->groupSubAdminManager->isSubAdminofGroup($user, $group);
  }
}
