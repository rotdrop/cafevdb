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

class DatabaseService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  protected $connection;

  public function __construct(ConfigService $configService) {
    $this->configService = $configService;

    $connectionParams = [
      'dbname' => $this->getConfigValue('dbname'),
      'user' => $this->getConfigValue('dbuser'),
      'password' => $this->getConfigValue('dbpassword'),
      'host' => $this->getConfigValue('dbserver'),
      'driver' => 'pdo_mysql',
    ];
    $this->connection = DriverManager::getConnection($connectionParams);
  }

  /**
   * @code
   * $sql = "SELECT * FROM articles";
   * $stmt = $conn->query($sql); // Simple, but has several drawbacks
   * while ($row = $stmt->fetch()) {
   *   echo $row['headline'];
   * }
   * @end code
   */
  public function query($sql) {
    return $this->conection->query($sql);
  }

  /**
   * @code
   * $sql = "SELECT * FROM articles WHERE id = ?";
   * $stmt = $conn->prepare($sql);
   * $stmt->bindValue(1, $id);
   * $stmt->execute();
   * @end code
   */
  public function prepare($sql) {
    return $this->connection->prepare($sql);
  }

  public function getQueryBuilder() {
    return $this->connection->createQueryBuilder();
  }

  /**
   * Executes an, optionally parameterized, SQL query.
   *
   * If the query is parameterized, a prepared statement is used.
   * If an SQLLogger is configured, the execution is logged.
   *
   * @param string $query The SQL query to execute.
   * @param string[] $params The parameters to bind to the query, if any.
   * @param array $types The types the previous parameters are in.
   * @return \Doctrine\DBAL\Driver\Statement The executed statement.
   * @since 8.0.0
   */
  public function executeQuery($query, array $params = [], $types = [])
  {
    return $this->connection->executeQuery($query, $params, $types);
  }

  public function fetchAll($query, array $params = [], $types = [])
  {
    return $this->connection->fetchAll(query, $params, $types);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
