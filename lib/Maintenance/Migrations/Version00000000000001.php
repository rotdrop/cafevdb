<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Generate some needed procedures and functions. MySQL specific.
 */
class Version00000000000001 implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const SQL = [
    "CREATE OR REPLACE PROCEDURE `generateNumbers`(IN `min` INT)
BEGIN
    CREATE TABLE IF NOT EXISTS numbers
        ( n INTEGER NOT NULL PRIMARY KEY )
        ENGINE memory
        AS SELECT 0 n UNION ALL SELECT 1;
    SELECT COUNT(*) FROM numbers INTO @max;
    IF @max = 0 THEN
        INSERT INTO numbers SELECT 0 n UNION ALL SELECT 1;
        SET @max = 1;
    END IF;
    WHILE @max < min DO
        INSERT IGNORE INTO numbers SELECT (hi.n)*(SELECT COUNT(*) FROM numbers)+(lo.n)+1 AS n
          FROM numbers lo, numbers hi;
        SELECT COUNT(*) FROM numbers INTO @max;
    END WHILE;
END",
    "CREATE OR REPLACE FUNCTION `BIN2UUID`(`b` BINARY(16)) RETURNS char(36) CHARSET ascii
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN BIN_TO_UUID(b, 0);
END",
    "CREATE OR REPLACE FUNCTION `UUID2BIN`(`uuid` CHAR(36)) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN UUID_TO_BIN(uuid, 0);
END",
    "CREATE OR REPLACE FUNCTION `UUID_TO_BIN`(`uuid` CHAR(36), `f` BOOLEAN) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN UNHEX(CONCAT(
  IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
  SUBSTRING(uuid, 10, 4),
  IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
  SUBSTRING(uuid, 20, 4),
  SUBSTRING(uuid, 25))
  );
END",
    "CREATE OR REPLACE FUNCTION `BIN_TO_UUID`(`b` BINARY(16), `f` BOOLEAN) RETURNS char(36) CHARSET ascii
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
DECLARE hexStr CHAR(32);
SET hexStr = HEX(b);
RETURN LOWER(CONCAT(
        IF(f,SUBSTR(hexStr, 9, 8),SUBSTR(hexStr, 1, 8)), '-',
        IF(f,SUBSTR(hexStr, 5, 4),SUBSTR(hexStr, 9, 4)), '-',
        IF(f,SUBSTR(hexStr, 1, 4),SUBSTR(hexStr, 13, 4)), '-',
        SUBSTR(hexStr, 17, 4), '-',
        SUBSTR(hexStr, 21)
    ));
END",
  ];

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
  }

  public function execute():bool
  {
    $connection = $this->entityManager->getConnection();

    foreach (self::SQL as $sql) {
      $statement = $connection->prepare($sql);
      $statement->execute();
    }
    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
