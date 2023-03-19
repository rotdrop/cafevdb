<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Cloud\Traits;

/** Construct the table name from the entity class. */
trait EntityTableNameTrait
{
  /** @return string */
  private function makeEntityClass()
  {
    $backSlashPos = strrpos(__CLASS__, '\\');
    $myName = substr(__CLASS__, $backSlashPos + 1);

    // construct from class-name of child
    $instanceClass = \get_class($this);
    $nameSpaces = explode('\\', $instanceClass);
    $nameSpaceIdx = count($nameSpaces) - 2;
    $classNameIdx = count($nameSpaces) - 1;

    $nameSpaces[$nameSpaceIdx] = 'Entities';
    $nameSpaces[$classNameIdx] = str_replace($myName, '', $nameSpaces[$classNameIdx]);

    $entityClass = implode('\\', $nameSpaces);

    return $entityClass;
  }

  /**
   * @param string $appName
   *
   * @param string $entityClass
   *
   * @return string
   */
  private function makeTableName(string $appName, string $entityClass)
  {
    // construct from $entityClass
    $backSlashPos = strrpos($entityClass, '\\');
    $entityName = substr($entityClass, $backSlashPos + 1);

    // Convert camel-case to underscores
    $words = array_map('lcfirst', preg_split('/(?=[A-Z])/', $entityName));
    $tableName = $appName.implode('_', $words);

    return $tableName;
  }
}
