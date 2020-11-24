<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**@file*/

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /**Support class for connecting to a mySQL database.
   */
  class mySQL
  {
    const DATEMASK = "Y-m-d H:i:s"; /**< Something understood by mySQL. */
    const REGULAR = 0; ///< Default action, no update on duplicate etc.
    const IGNORE  = 1; ///< Ignore errors on insert, update
    const UPDATE  = 2; ///< Update on duplicate key

    /**Return a flat array with the column names for the given table.*/
    public static function columns($table, $handle, $die = false, $silent = false)
    {
      // Build SQL Query
      $query = "SHOW COLUMNS FROM `".$table."`";

      // Fetch the result or die
      $result = self::query($query, $handle);
      $columns = array();
      while ($line = self::fetch($result)) {
        $columns[] = $line['Field'];
      }
      return $columns;
    }

    /**Query the number of rows in a table. */
    public static function queryNumRows($querypart, $handle = false, $die = true, $silent = false)
    {
      $numRows = 0;
      $query = 'SELECT COUNT(*) '.$querypart;
      $result = self::query($query, $handle, $die, $silent);
      $row = self::fetch($result, MYSQLI_NUM);
      (isset($row[0]) && $numRows = $row[0]) || $numRows = 0;
      return $numRows;
    }

    public static function changedRows($handle, $die = true, $silent = false)
    {
      return $handle->affected_rows;
    }

    public static function newestIndex($handle, $die = true, $silent = false)
    {
      return $handle->insert_id;
    }

    public static function numRows($res)
    {
      return $res->num_rows;
    }

    public static function fetch(&$res, $type = MYSQLI_ASSOC)
    {
      $result = @$res->fetch_array($type);
      //if (Util::debugMode('query')) {
        //print_r($result);
      //}
      return $result;
    }

    public static function escape($string, $handle)
    {
      return @$handle->real_escape_string($string);
    }

    // Extract keys from table, splitting "multi-value" fields
    public static function valuesFromColumn($table, $column, $handle = false, $where = 1, $sep = ',')
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $query = "SELECT DISTINCT `".$column."`
  FROM `".$table."`
  WHERE (".$where.")";

      // Fetch all values
      $result = self::query($query, $handle);
      $values = array();
      while ($line = self::fetch($result)) {
        $values = array_merge($values, Util::explode($sep, $line[$column]));
      }
      $values = array_unique($values);

      if ($ownConnection) {
        self::close($handle);
      }

      return $values;
    }

    /**Insert a couple of values into a table.
     *
     * @param $table The affected SQL table.
     *
     * @param $newValues An associative array where the keys are
     * the column names and the values are the respective values to be
     * inserted.
     *
     * @param $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param $modifier One of self::REGULAR, self::UPDATE, self::IGNORE
     *
     * @return The result returned by the SQL query statement.
     */
    public static function insert($table, $newValues, $handle = false, $flags = self::REGULAR)
    {
      if (empty($newValues)) {
        return true; // don't care
      }

      $nl = "\n";
      is_array(reset($newValues)) || $newValues = [ $newValues ];
      $keys = array_keys(current($newValues));
      $queryValues = [];
      foreach($newValues as $newValue) {
        $values = array_values($newValue);
        $queryValues[] = "('".implode("','", $values)."')".$nl;
      }
      $queryValues = implode(",\n", $queryValues);

      // build the query ...
      switch ($flags) {
      default:
      case self::REGULAR:
        $query = "INSERT INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES $queryValues";
        break;
      case self::IGNORE:
        $query = "INSERT IGNORE INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES $queryValues";
        break;
      case self::UPDATE:
        $updates = array_map(function($key) {
            return "`".$key."` = VALUES($key)";
          }, $keys);
        $query = "INSERT INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES $queryValues
  ON DUPLICATE KEY UPDATE ".implode(",", $updates);
        break;
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      //throw new \Exception($query);

      $result = self::query($query, $handle);

      /* if ($result === false) { */
      /*   error_log('Error: '.$query.' '.mySQL::error($handle)); */
      /* } */

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Update a couple of values into a table.
     *
     * @param string $table The data-base table to modify
     *
     * @param sting $where Condition (e.g. id field etc.), "WHERE"
     * is added by the funcion.
     *
     * @param array $newValues Associative array where keys are
     * the field names and the values are the values to inject into
     * the table.
     *
     * @param resource $handle Database handle.
     */
    public static function update($table, $where, $newValues, $handle)
    {
      if (empty($newValues)) {
        return true; // don't care
      }

      $query = "UPDATE `".$table."` SET ";
      $setter = array();
      foreach ($newValues as $key => $value) {
        $value = $value === null ? 'NULL' : "'".$value."'";
        $setter[] = "`".$key."` = ".$value;
      }
      $query .= implode(", ", $setter);
      $query .= " WHERE (".$where.")";

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }
      //throw new \Exception($query);
      $result = self::query($query, $handle);
      //if ($result === false) throw new \Exception($query);

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Convenience function: fetch some rows of a table.
     *
     * @param $table The table to fetch data from.
     *
     * @param $where The conditions (excluding the WHERE keyword)
     *
     * @param $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param $die Die or not on error.
     *
     * @param $silent Suppress some diagnostic messages on error.
     *
     * @return An array with all matching rows. Return false in case
     * of error.
     */
    public static function fetchRows($table, $where = '1', $sort = null,
                                     $handle = false, $die = true, $silent = false)
    {
      empty($where) && $where = '1';

      $query = "SELECT * FROM `".$table."` WHERE (".$where.")";
      if (!empty($sort)) {
        $query .= " ORDER BY ".$sort;
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $qResult = self::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = self::fetch($qResult)) {
          $result[] = $row;
        }
        self::freeResult($qResult);
      } else {
        $result = false;
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Convenience function: fetch one column of a table.
     *
     * @param $table The table to fetch data from.
     *
     * @param $col The column name.
     *
     * @param $where The conditions (excluding the WHERE keyword)
     *
     * @param $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param $die Die or not on error.
     *
     * @param $silent Suppress some diagnostic messages on error.
     *
     * @return An array with all matching rows. Return false in case
     * of error.
     */
    public static function fetchColumn($table, $col, $where = '1', $handle = null,
                                       $die = true, $silent = false)
    {
      $query = "SELECT `".$col."` FROM `".$table."` WHERE (".$where.")";

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $qResult = self::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($qRow = self::fetch($qResult)) {
          $result[] = $qRow[$col];
        }
      } else {
        $result = false;
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**"Touch" the last-modified time-stamp, e.g. after updating data
     * not directly stored in the projects table.
     */
    public static function storeModified($itemId, $itemTable, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $query = "UPDATE IGNORE `".$itemTable."`
    SET `Aktualisiert` = '".date(self::DATEMASK)."'
    WHERE `Id` = ".$itemId;

      $result = self::query($query, $handle);

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Retrieve the last-modified time-stamp. */
    public static function fetchModified($itemId, $itemTable, $handle = false)
    {
      $modified = 0;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $query = "SELECT `Aktualisiert` FROM `".$itemTable."` WHERE `Id` = ".$itemId.";";

      $result = self::query($query, $handle);
      if ($result !== false && self::numRows($result) == 1) {
        $row = self::fetch($result);
        if (isset($row['Aktualisiert'])) {
          $modified = strtotime($row['Aktualisiert']);
        }
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $modified;
    }

    /**Fetch the maximum of all modification time-stamps and return as
     * Unix timestamp.
     */
    public static function fetchLastModified($table, $handle = false, $column = "Aktualisiert")
    {
      $lastModified = self::selectSingleFromTable("MAX(`".$column."`)", $table, null, $handle);

      return strtotime($lastModified);
    }

    public static function fetchFirstModified($table, $handle = false, $column = "Aktualisiert")
    {
      $lastModified = self::selectSingleFromTable("MIN(`".$column."`)", $table, null, $handle);

      return strtotime($lastModified);
    }

    public static function queryConnect($callable, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $args = func_get_args();
      array_shift($args);
      $args[0] = $handle;
      $result = call_user_func_array($callable, $args);

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Convenience function, select something from a table.
     *
     * @param string $select Some select statement,
     * e.g. "MAX(`column`)" for the case that @a $table contains a
     * field "column".
     *
     * @param string $table The name of the table.
     *
     * @param string $cond "WHERE" conditions, or sort modifyers,
     * if applicable. Defaults to "WHERE 1".
     *
     * @param mixed $handle Database handle or false.
     *
     * @return It is assumed that the function applied yields a single
     * result value. In case of success, this value is the result,
     * otherwise @c false is returned.
     */
    public static function selectSingleFromTable($select, $table, $cond = "WHERE 1", $handle = false)
    {
      $result = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      if (!$cond) {
        $cond = "WHERE 1";
      }

      $query = "SELECT ".$select." FROM `".$table."` ".$cond;

      $queryRes = self::query($query, $handle);
      $row = array();
      if ($queryRes !== false &&
          self::numRows($queryRes) == 1 &&
          ($row = self::fetch($queryRes, MYSQLI_NUM)) &&
          count($row) == 1) {
        $result = $row[0];

        /* \OCP\Util::writeLog(Config::APP_NAME, */
        /*                     __METHOD__.': '. 'result: ' . (string)$result, */
        /*                     \OCP\Util::DEBUG); */

      } else {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': '. 'query: ' . $query,
                            \OCP\Util::DEBUG);
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__ . ': ' .
                            'query failed: ' .
                            'result: ' . ($queryRes !== false) . ' ' .
                            'rows: ' . ($queryRes !== false ? self::numRows($queryRes) : -1) . ' ' .
                            'row: ' . print_r($row, true),
                            \OCP\Util::DEBUG);
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Convenience function, select the next free hole from an integral column.
     *
     * @param string $table The name of the table.
     *
     * @param string $col The name of the integer column.
     *
     * @param string $cond "WHERE" conditions, or sort modifyers,
     * if applicable. Defaults to "WHERE 1".
     *
     * @param mixed $handle Database handle or false.
     *
     * @return It is assumed that the function applied yields a single
     * result value. In case of success, this value is the result,
     * otherwise @c false is returned.
     */
    public static function selectFirstHoleFromTable($table, $col, $cond = "WHERE 1", $handle = false)
    {
      $result = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      if (!$cond) {
        $cond = "WHERE 1";
      }

      $cond1 = str_replace($table, 't1', $cond);
      $cond2 = str_replace($table, 't2', $cond);

      $query = "SELECT MIN(t1.`".$col."`)+1 FROM `".$table."` t1
  WHERE
   (".$cond1.")
   AND
   NOT EXISTS (SELECT t2.`".$col."` FROM `".$table."` t2
                 WHERE (".$cond2.") AND t2.`".$col."` = t1.`".$col."`+1)";

      $queryRes = self::query($query, $handle);
      $row = array();
      if ($queryRes !== false &&
          self::numRows($queryRes) == 1 &&
          ($row = self::fetch($queryRes, MYSQLI_NUM)) &&
          count($row) == 1) {
        $result = $row[0];

        /* \OCP\Util::writeLog(Config::APP_NAME, */
        /*                     __METHOD__.': '. 'result: ' . (string)$result, */
        /*                     \OCP\Util::DEBUG); */

      } else {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': '. 'query: ' . $query,
                            \OCP\Util::DEBUG);
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__ . ': ' .
                            'query failed: ' .
                            'result: ' . ($queryRes !== false) . ' ' .
                            'rows: ' . ($queryRes !== false ? self::numRows($queryRes) : -1) . ' ' .
                            'row: ' . print_r($row, true),
                            \OCP\Util::DEBUG);
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

  };

} // namespace

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
