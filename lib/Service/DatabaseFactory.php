<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;

class DatabaseFactory
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(ConfigService $configService)
  {
    $this->configService = $configService;
  }

  public function getService() {
    $connectionParams = [
      'dbname' => $this->getConfigValue('dbname'),
      'user' => $this->getConfigValue('dbuser'),
      'password' => $this->getConfigValue('dbpassword'),
      'host' => $this->getConfigValue('dbserver'),
      'driver' => 'pdo_mysql',
      'wrapperClass' => DatabaseService::class,
      'configService' => $this->configService,
    ];
    return DriverManager::getConnection($connectionParams);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
