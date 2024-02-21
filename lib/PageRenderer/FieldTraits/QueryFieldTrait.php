<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/**
 * Some convenience methods in order to ease access the $row array used by
 * legacy PHPMyEdit.
 */
trait QueryFieldTrait
{
  /** @var PHPMyEdit */
  protected $pme;

  /**
   * @param string $column
   *
   * @return null|int the numeric field description index
   */
  protected function queryFieldIndex(string $column):?int
  {
    return $this->pme->fdn[$column] ?? null;
  }

  /**
   * @param string|array $tableInfo Table-description-data.
   *
   * @param string $column
   *
   * @return null|int the numeric field description index
   */
  protected function joinQueryFieldIndex($tableInfo, string $column):?int
  {
    return $this->pme->fdn[$this->joinTableFieldName($tableInfo, $column)] ?? null;
  }

  /**
   * @param string $key
   *
   * @return string PHPMyEdit::QUERY_FIELD . N where N is determined by a
   * lookup table of PHPMyEdit.
   */
  protected function queryField(string $key):string
  {
    return PHPMyEdit::QUERY_FIELD . $this->queryFieldIndex($key);
  }

  /**
   * Compute the key in to the PME $row array for the given column.
   *
   * @param string $key
   *
   * @return string PHPMyEdit::QUERY_FIELD . N . PHPMyEdit::QUERY_FIELD_IDX
   * where N is determined by a lookup table of PHPMyEdit.
   */
  protected function queryIndexField(string $key)
  {
    return $this->queryField($key) . PHPMyEdit::QUERY_FIELD_IDX;
  }

  /***
   * Compute the key in to the PME $row array for the given column.
   *
   * @param string|array $tableInfo Table-description-data.
   *
   * @param string $column
   *
   * @return string
   *
   * @see joinTableFieldName()
   * @see queryField()
   */
  protected function joinQueryField($tableInfo, string $column)
  {
    return $this->queryField($this->joinTableFieldName($tableInfo, $column));
  }

  /**
   * Compute the key in to the PME $row array for the given column.
   *
   * @param string|array $tableInfo Table-description-data.
   *
   * @param string $column
   *
   * @return string
   *
   * @see joinTableFieldName()
   * @see queryIndexField()
   */
  protected function joinQueryIndexField($tableInfo, string $column)
  {
    return $this->queryIndexField($this->joinTableFieldName($tableInfo, $column));
  }
}