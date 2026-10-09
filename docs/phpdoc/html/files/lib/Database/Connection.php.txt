<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Configuration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DBALConnection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Driver;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;

/**
 * DBAL wrapper. In principle no longer neccessary, but we keep it in order to
 * separate the DI features of the app-container from the actual DB backend.
 */
class Connection extends DBALConnection
{
  /**
   * Create a new instance and bind it to the given database, keeping all
   * other params.
   *
   * @param string $database
   *
   * @param null|string $user
   *
   * @param null|string $password
   *
   * @param null|string $host
   *
   * @return Connection
   */
  public function bind(
    string $database,
    ?string $user = null,
    ?string $password = null,
    ?string $host = null,
  ):Connection {
    $params = $this->getParams();
    $params[ConfigConstants::APP_DB_NAME] = $database;
    if ($user !== null) {
      $params['user'] = $user;
    }
    if ($password !== null) {
      $params['password'] = $password;
    }
    if ($host !== null) {
      $params['host'] = $host;
    }

    if ($this->getConfiguration()->getSchemaManagerFactory() === null) {
      $this->getConfiguration()->setSchemaManagerFactory(new DefaultSchemaManagerFactory);
    }

    $connection = new Connection(
      $params,
      $this->getDriver(),
      $this->getConfiguration(),
    );
    $connection->connect();
    return $connection;
  }
}
