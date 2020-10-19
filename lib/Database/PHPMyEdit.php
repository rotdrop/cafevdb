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

namespace OCA\CAFEVDB\Database;

use OCA\CAFEVDB\Service\ConfigService;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\DBALException;

class PHPMyEdit extends \phpMyEdit
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var Connection */
  private $connection;

  /** @var ConfigService */
  private $configService;

  private $affectedRows = 0;
  private $errorCode = 0;
  private $errorInfo = null;
  private $connectionOptions;

  public function __construct(
    Connection $connection,
    ConfigService $configService
  ) {
    $this->dbh = $connection;
    $this->configService = $configService;
    $this->connectionOptions = [
      'dbh' => $this->connection,
      'dbp' => '',
    ];
  }

  public function execute($opts)
  {
    $opts = array_merge($opts, $this->connectionOptions, [ 'execute' -> false ]);
    parent::__construct($opts); // oh oh
    parent::execute();
  }

  public function sql_connect() {
    // do nothing, we only work with already open connections.
  }

  public function sql_disconnect() {
    // do nothing, we only work with already open connections.
  }

  function resultValid($stmt)
  {
    return is_object($stmt);
  }

  function dbhValid() {
    return is_object($this->dbh);
  }

  function sql_fetch(&$stmt, $type = 'a')
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    $type = $type === 'n' ? FetchMode::NUMERIC : FetchMode::ASSOCIATIVE;
    return $stmt->fetch(type);
  }

  function sql_free_result(&$stmt)
  {
    if (!$this->resultValid($stmt)) {
      return false;
    }
    return $stmt->closeCursor();
  }

  function sql_affected_rows()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->affectedRows;
  }

  function sql_field_len(&$stmt, $field)
  {
    return 65535-1;
  }

  function sql_insert_id()
  {
    if (!$this->dbhValid()) {
      return 0;
    }
    return $this->dbh->lastInsertId();
  }

  function myquery($query, $line = 0, $debug = false)
  {
    try {
      $stmt = $this->dbh->executeQuery($queryString);
      $this->affectedRows = $stmt->rowCount();
      $this->errorCode = $stmt->errorCode();
      $this->errorInfo = $stmt->errorInfo();
    } catch (DBALException $e) {
      $this->errorCode = $t->getCode();
      $this->errorInfo = $t->getMessage();
      return false;
    }
    return $stmt;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
