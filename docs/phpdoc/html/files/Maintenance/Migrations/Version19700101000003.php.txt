<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Add functions and routines to the initial database.
 */
final class Version19700101000003 extends AbstractMigration
{
  private const CREATE_FUNCTION_PREFIX = 'CREATE OR REPLACE FUNCTION';
  private const FUNCTIONS = [
    'BIN_TO_UUID' => "(`b` BINARY(16), `f` BOOLEAN) RETURNS char(36) CHARSET ascii COLLATE ascii_general_ci
    NO SQL
    DETERMINISTIC
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
    'BIN2UUID' => "(`b` BINARY(16)) RETURNS char(36) CHARSET ascii COLLATE ascii_general_ci
    NO SQL
    DETERMINISTIC
BEGIN
  RETURN BIN_TO_UUID(b, 0);
END",
    'UUID_TO_BIN' => "(`uuid` CHAR(36), `f` BOOLEAN) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
BEGIN
  RETURN UNHEX(CONCAT(
  IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
  SUBSTRING(uuid, 10, 4),
  IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
  SUBSTRING(uuid, 20, 4),
  SUBSTRING(uuid, 25))
  );
END",
    'UUID2BIN' => "(`uuid` CHAR(36)) RETURNS binary(16)
    NO SQL
    DETERMINISTIC
BEGIN
  RETURN UUID_TO_BIN(uuid, 0);
END",
    'EXPLODE' => "(`delimiters` VARCHAR(12), `inputString` TEXT, `position` INT) RETURNS text CHARSET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci
    NO SQL
    DETERMINISTIC
RETURN
    REPLACE(
      SUBSTRING(
        SUBSTRING_INDEX(`inputString`, `delimiters`, `position`),
        LENGTH(SUBSTRING_INDEX(`inputString`, `delimiters`, `position` - 1)) + 1
      ),
      `delimiters`,
      ''
    )",
//     'MUSICIAN_USER_ID' => "() RETURNS varchar(256) CHARSET ascii COLLATE ascii_general_ci
//     NO SQL
//     DETERMINISTIC
// BEGIN
//   RETURN COALESCE(@CLOUD_USER_ID, SUBSTRING_INDEX(USER(), '@', 1));
// END",
  ];

  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Add functions.');
  }

  /**
   * {@inheritdoc}
   *
   * This is a structural migration and thus cannot be transactional on
   * MariaDB / MySQL.
   */
  public function isTransactional(): bool
  {
    return false;
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    foreach (self::FUNCTIONS as $name => $definition) {
      $this->addSql(self::CREATE_FUNCTION_PREFIX . ' ' . $name . $definition);
    }
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    foreach (array_reverse(array_keys(self::FUNCTIONS)) AS $name) {
      $this->addSql('DROP FUNCTION IF EXISTS ' . $name);
    }
  }
}
