<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine
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

use UnexpectedValueException;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/** Substitue some legacy PME placeholder. */
trait SubstituteSQLFragmentTrait
{
  /** @var PHPMyEdit */
  protected PHPMyEdit $pme;

  /**
   * Replace $join_table etc. replacements by their proper table and column
   * aliases, base on the ordering defined in $fdd.
   *
   * @param array $fdd Field description data.
   *
   * @param string $fieldName The field name which defines the pivot for the
   * replacements.
   *
   * @param string $sql SQL fragment with replacements.
   *
   * @param null|int $fieldIndex Field index if known, if not given array_search() is used.
   *
   * @return The result of the substitutions.
   *
   * @throws UnexpectedValueException Thrown if $fieldName is not found in $fdd.
   */
  protected function substituteSQLFragment(array $fdd, string $fieldName, string $sql, ?int $fieldIndex = null):string
  {
    $fieldFdd = $fdd[$fieldName] ?? null;
    if (empty($fieldFdd)) {
      throw new UnexpectedValueException($this->t->t('The field-description-data did not contain data for the field "%s".', $fieldName));
    }
    $joinTable = $fieldFdd['values']['join']['reference'] ?? null;
    if (empty($joinTable)) {
      $fieldIndex = $fieldIndex ?? array_search($fieldName, array_keys($fdd));
      $joinTable = $fieldIndex;
    }
    if (!str_starts_with($joinTable, PHPMyEdit::JOIN_ALIAS) && is_numeric($joinTable)) {
      $joinTable = PHPMyEdit::JOIN_ALIAS . $joinTable;
    }
    $joinColumn = $fieldFdd['values']['column'] ?? null;
    $joinColFqn = $joinTable . '.' . $joinColumn;

    $substitutions = [
      'main_table' => PHPMyEdit::MAIN_ALIAS,
      'field_name' => $fieldName,
      'join_table' => $joinTable,
      'join_col_fqn' => $joinColFqn,
      'join_col_enc' => sprintf($fdd['values']['encode'] ?? '', $joinColFqn),
      'join_column' => $joinColumn,
      'table' => $joinTable,
      'column' => $joinColumn,
    ];
    return $this->pme->substituteVars($sql, $substitutions);
  }
}
