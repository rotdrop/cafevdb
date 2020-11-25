<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Legacy;

class Util
{
  /**
   * Generate a select for a join from a descriptive array structure.
   *
   * $joinStructure = array(
   *   'JoinColumnName' => array(
   *     'table' => TABLE,
   *     'column' => ORIGINALNAME,
   *     'join' => array(
   *       'type' => 'INNER'|'LEFT' (don't know if OUTER and RIGHT could work ...)
   *       'condition' => STRING sql condition, must be there on first joined field
   *     ),
   *  ...
   *
   * Example:
   *
   * $viewStructure = array(
   *   'MusikerId' => array(
   *     'table' => 'Musiker',
   *     'column' => 'Id',
   *     // join condition need not be here
   *     'join' => array('type' => 'INNER')
   *     ),
   *   'Instrument' => array(
   *     'table' => 'Besetzungen',
   *     'column' => true,
   *     'join' => array(
   *       'type' => 'INNER',
   *       // one and only one of the fields need to provide the join conditions,
   *       'condition' => ('`Musiker`.`Id` = `Besetzungen`.`MusikerId` '.
   *                     'AND '.
   *                     $projectId.' = `Besetzungen`.`ProjektId`')
   *       ),
   *     ),
   *
   * The left-most join table is always the table of the first element
   * from $joinStructure.
   */
  public static function generateJoinSelect($joinStructure)
  {
    $bt = '`';
    $dot = '.';
    $ind = '  ';
    $nl = "\n";

    $firstTable = reset($joinStructure);
    if ($firstTable == false) {
      return false;
    }

    $joinDflt = [ 'table' => false,
                  'tablename' => false,
                  'column' => true,
                  'verbatim' => false ];

    $firstTable = array_merge($joinDflt, $firstTable);
    $table = $firstTable['table'];
    $tablename = $firstTable['tablename'];
    !empty($table) || $table = $tablename;
    !empty($tablename) || $tablename = $table;

    $join = $ind.'FROM '.$bt.$table.$bt;
    if ($tablename !== $table) {
      $join .= ' '.$tablename.' ';
    }
    $join .= $nl;
    $select = 'SELECT'.$nl;
    foreach ($joinStructure as $joinColumn => $joinedColumn) {
      // Set default options. The default options array MUST come
      // first, later arrays override (see manual for array_merge())
      $joinedColumn = array_merge($joinDflt, $joinedColumn);
      $table = $joinedColumn['table'];
      $tablename = $joinedColumn['tablename'];
      !empty($table) || $table = $tablename;
      !empty($tablename) || $tablename = $table;
      if ($joinedColumn['column'] === true) {
        $name = $joinColumn;
        $as = '';
      } else {
        $name = $joinedColumn['column'];
        $as = ' AS '.$bt.$joinColumn.$bt;
      }
      if (!$joinedColumn['verbatim']) {
        $column = $bt.$tablename.$bt.$dot.$bt.$name.$bt;
      } else {
        $column = $name;
      }
      $select .= $ind.$ind.$column.$as.','.$nl;
      if (isset($joinedColumn['join']['condition'])) {
        $type = $joinedColumn['join']['type'];
        $cond = $joinedColumn['join']['condition'];
        $join .=
              $ind.$ind.
              $type.' JOIN ';
        if (!$joinedColumn['verbatim']) {
          $join .= $bt.$table.$bt;
        } else {
          $join .= $table;
        }
        if ($tablename != $table) {
          $join .= ' '.$tablename.' ';
        }
        $join .= $nl.
              $ind.$ind.$ind.'ON '.$cond.$nl;
      }
    }
    return rtrim($select, "\n,").$nl.$join;
  }
}
