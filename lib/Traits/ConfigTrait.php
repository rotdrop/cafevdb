<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

//WIP
namespace OCA\CAFEVDB\Traits;

use OCP\IUser;
use OCP\IConfig;

use OCA\CAFEVDB\Common\Config;

trait ConfigTrait {

  /** @var string */
  private $configService;

  protected function appName()
  {
    return $this->configService->getAppName();
  }

  protected function userManager()
  {
    return $this->configService->getUserManager();
  }

  protected function groupManager()
  {
    return $this->configService->getGroupManager();
  }

  protected function getUserValue($key, $default = null, $userId = null)
  {
    return $this->configService->getUserValue($key, $default, $userId);
  }

  protected function setUserValue($key, $value, $userId = null)
  {
    return $this->configService->setUserValue($key, $value, $userId);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  protected function getAppValue($key, $default = null)
  {
    return $this->configService->getAppValue($key, $default);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  protected function setAppValue($key, $value)
  {
    return $this->configService->setAppValue($key, $value);
  }

  protected function user($userId = null)
  {
    return $this->configService->getUser($userId);
  }

  protected function userId()
  {
    return $this->configService->getUserId();
  }

  protected function groupId()
  {
    return $this->configService->getGroupId();
  }

  protected function group($groupId = null)
  {
    return $this->configService->getGroup($groupId);
  }

  protected function groupExists($groupId = null)
  {
    return $this->configService->groupExists($groupId);
  }

  protected function isSubAdminOfGroup($userId = null, $groupId = null) {
    return $this->configService->isSubAdminOfGroup($userId, $groupId);
  }

  protected function l10N()
  {
    return $this->configService->getL10N();
  }

  public function getIcon() {
    return $this->configService->getIcon();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
