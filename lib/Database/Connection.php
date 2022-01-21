<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\DriverManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\EventManager;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Configuration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Driver;

/**
 * DBAL wrapper. In principle no longer neccessary, but we keep it in order to
 * separate the DI features of the app-container from the actual DB backend.
 */
class Connection extends \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection
{
  public function __construct(array $params, Driver $driver, Configuration $config = null, EventManager $eventManager = null) {
    parent::__construct($params, $driver, $config, $eventManager);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
