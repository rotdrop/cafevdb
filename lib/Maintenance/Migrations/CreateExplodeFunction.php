<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * Remember the id of a mailing list.
 */
class CreateExplodeFunction extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE FUNCTION IF NOT EXISTS `EXPLODE` (`delimiters` VARCHAR(12), `inputString` TEXT, `position` INT)
  RETURNS TEXT DETERMINISTIC NO SQL SQL SECURITY DEFINER
  RETURN
    REPLACE(
      SUBSTRING(
        SUBSTRING_INDEX(`inputString`, `delimiters`, `position`),
        LENGTH(SUBSTRING_INDEX(`inputString`, `delimiters`, `position` - 1)) + 1
      ),
      `delimiters`,
      ''
    );"
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a simple convenience explode function in order to use it in later migrations.');
  }
}
