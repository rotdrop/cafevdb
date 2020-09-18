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
  protected $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var IUser */
  private $user;

  /** @var string */
  private $userId;

  protected function getUserValue($key, $default = null, $userId = null)
  {
    empty($userId) && ($userId = $this->userId);
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  protected function setUserValue($key, $value, $userId = null)
  {
    empty($userId) && ($userId = $this->userId);
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  protected function getAppValue($key, $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  protected function setAppValue($key, $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
