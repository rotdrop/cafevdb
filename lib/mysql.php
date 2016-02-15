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

    /**Connect to the server specified by @a $opts.
     *
     * @param[in] $opts Associative array with keys 'hn', 'un', 'pw' and
     * 'db' for "hostname", "username", "password" and
     * "database",repectively.
     *
     * @param[in] $die Bail out on error. Default is @c true. If @c
     * false then go on and return @c false in case of an error.
     *
     * @param[in] $silent If exception-based error handling is not in
     * effect, then control whehter something is printed to the standard
     * output channel.
     *
     * @return Mixed, @c false in case of error. Otherwise the data-base
     * handle.
     * @callgraph
     * @callergraph
     */
    public static function connect($opts, $die = true, $silent = false)
    {
      // Open a new connection to the given data-base.
      $handle = new \mysqli($opts['hn'], $opts['un'], $opts['pw'], $opts['db']);
      if ($handle->connect_error) {
        Util::error(L::t("Could not connect to data-base server `%s': %s",
                         array($opts['hn'], self::error($handle))), $die, $silent);
        return false;
      }

      $handle->set_charset('utf8');

      return $handle;
    }

    /**Close the mySQL data-base connection previously opened by
     * self::connect().
     *
     * @param[in] $handle Database handle.
     *
     * @return @c true, always.
     */
    public static function close($handle)
    {
      $handle->close();
      return true;
    }

    public static function error($handle)
    {
      return $handle->error;
    }

    public static function query($query, $handle, $die = false, $silent = false)
    {
      if (($result = $handle->query($query, MYSQLI_STORE_RESULT)) === false) {
        $err = self::error($handle);
        Util::error('mySQL query failed: "'.$err.'", query: "'.$query.'"', $die, $silent);
      }
      return $result;
    }

    public static function freeResult($result)
    {
      if ($result instanceof mysqli_result) {
        $result->free();
      }
    }

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
        $values = array_merge($values, explode($sep, $line[$column]));
      }
      $values = array_unique($values);

      if ($ownConnection) {
        self::close($handle);
      }

      return $values;
    }

    // Extract set or enum keys
    public static function multiKeys($table, $column, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      // Build SQL Query
      $query = "SHOW COLUMNS FROM $table LIKE '$column'";

      // Fetch the result or die
      $result = self::query($query, $handle) or die("Couldn't execute query");
      $line = self::fetch($result);
      self::freeResult($result);

      $set = $line['Type'];

      if (strcasecmp(substr($set,0,3),'set') == 0) {
        $settype = 'set';
      } elseif (strcasecmp(substr($set,0,4),'enum') == 0) {
        $settype = 'enum';
      } else {
        $settype = 'column';
      }

      if ($settype === 'column') {
        // fetch all values
        $query = "SELECT DISTINCT `".$column."` FROM `".$table."` WHERE 1";
        $result = self::query($query, $handle) or die("Couldn't execute query");
        $values = array();
        while ($line = self::fetch($result)) {
          $values[] = $line[$column];
        }
      } else {
        // enum or set
        $set = substr($set,strlen($settype)+2,strlen($set)-strlen($settype)-strlen("();")-1); // Remove "set(" at start and ");" at end
        $values = preg_split("/','/",$set); // Split into an array
      }

      if ($ownConnection) {
        self::close($handle);
      }

      return $values;
    }

    /**Generate a select for a join from a descriptive array structure.
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
      $nl = '
';
      $firstTable = reset($joinStructure);
      if ($firstTable == false) {
        return false;
      }

      $joinDflt = array('table' => false,
                        'tablename' => false,
                        'column' => true,
                        'verbatim' => false);

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
      foreach($joinStructure as $joinColumn => $joinedColumn) {
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

    /**Insert a couple of values into a table.
     *
     * @param[in] $table The affected SQL table.
     *
     * @param[in] $newValues An associative array where the keys are
     * the column names and the values are the respective values to be
     * inserted.
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $modifier One of self::REGULAR, self::UPDATE, self::IGNORE
     *
     * @return The result returned by the SQL query statement.
     */
    public static function insert($table, $newValues, $handle = false, $flags = self::REGULAR)
    {
      if (empty($newValues)) {
        return true; // don't care
      }
      $keys = array_keys($newValues);
      $values = array_values($newValues);

      // build the query ...
      switch ($flags) {
      default:
      case self::REGULAR:
        $query = "INSERT INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES ('".implode("','", $values)."')";
        break;
      case self::IGNORE:
        $query = "INSERT IGNORE INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES ('".implode("','", $values)."')";
        break;
      case self::UPDATE:
        $updates = array_map(function($key) {
            return "`".$key."` = VALUES($key)";
          }, $keys);
        $query = "INSERT INTO `".$table."`
  (`".implode("`,`", $keys)."`)
  VALUES ('".implode("','", $values)."')
  ON DUPLICATE KEY UPDATE ".implode(",", $updates);
        break;
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

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
     * @param[in] string $table The data-base table to modify
     *
     * @param[in] sting $where Condition (e.g. id field etc.), "WHERE"
     * is added by the funcion.
     *
     * @param[in] array $newValues Associative array where keys are
     * the field names and the values are the values to inject into
     * the table.
     *
     * @param[in] resource $handle Database handle.
     */
    public static function update($table, $where, $newValues, $handle)
    {
      if (empty($newValues)) {
        return true; // don't care
      }

      $query = "UPDATE `".$table."` SET ";
      $setter = array();
      foreach ($newValues as $key => $value) {
        $setter[] = "`".$key."`='".$value."'";
      }
      $query .= implode(", ", $setter);
      $query .= " WHERE (".$where.")";

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      $result = self::query($query, $handle);

      if ($ownConnection) {
        self::close($handle);
      }

      return $result;
    }

    /**Convenience function: fetch some rows of a table.
     *
     * @param[in] $table The table to fetch data from.
     *
     * @param[in] $where The conditions (excluding the WHERE keyword)
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $die Die or not on error.
     *
     * @param[in] $silent Suppress some diagnostic messages on error.
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
     * @param[in] $table The table to fetch data from.
     *
     * @param[in] $col The column name.
     *
     * @param[in] $where The conditions (excluding the WHERE keyword)
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $die Die or not on error.
     *
     * @param[in] $silent Suppress some diagnostic messages on error.
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
     * @param[in] string $select Some select statement,
     * e.g. "MAX(`column`)" for the case that @a $table contains a
     * field "column".
     *
     * @param[in] string $table The name of the table.
     *
     * @param[in] string $cond "WHERE" conditions, or sort modifyers,
     * if applicable. Defaults to "WHERE 1".
     *
     * @param[in] mixed $handle Database handle or false.
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
     * @param[in] string $table The name of the table.
     *
     * @param[in] string $col The name of the integer column.
     *
     * @param[in] string $cond "WHERE" conditions, or sort modifyers,
     * if applicable. Defaults to "WHERE 1".
     *
     * @param[in] mixed $handle Database handle or false.
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

    /**Insert an "insert" entry into the changelog table. The function
     * will log the inserted data, the user name and the remote IP
     * address.
     *
     * @param[in] $table The affected SQL table.
     *
     * @param[in] $recId The row-key.
     *
     * @param[in] $newValues An associative array where the keys are
     * the column names and the values are the respective values to be
     * inserted.
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $changeLog The name of the change-log table.
     *
     * @return true
     *
     */
    static public function logInsert($table, $recId, $newValues,
                                     $handle = null, $changeLog = 'changelog')
    {
      if (empty($newValues) || empty($changeLog)) {
        return true; // don't care
      }

      // log the result, but ignore any errors generated by the log query
      $logQuery = sprintf('INSERT INTO %s'
                          .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                          .' VALUES (NOW(), "%s", "%s", "insert", "%s", "%s", "", "", "%s")',
                          $changeLog, addslashes(\OC_User::getUser()),
                          addslashes($_SERVER['REMOTE_ADDR']), addslashes($table),
                          addslashes($recId), addslashes(serialize($newValues)));

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      self::query($logQuery, $handle, false, true);

      if ($ownConnection) {
        self::close($handle);
      }
      return true; // don't care
    }

    /**Insert a "delete" entry into the changelog table. The function
     * will log the inserted data, the user name and the remote IP
     * address.
     *
     * @param[in] $table The affected SQL table.
     *
     * @param[in] $recIdColumn The column name of the row-key.
     *
     * @param[in] $oldValues An associative array where the keys are
     * the column names and the values are the respective old values
     * which will be removed. $oldValues[$recIdColumn] should be the
     * respective row-key which has been removed.
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $changeLog The name of the change-log table.
     *
     * @return true
     *
     */
    static public function logDelete($table, $recIdColumn, $oldValues,
                                     $handle = null, $changeLog = 'changelog')
    {
      if (empty($oldValues) || empty($changeLog)) {
        return true; // don't care
      }

      // log the result, but ignore any errors generated by the log query
      $recId = $oldValues[$recIdColumn];
      $logQuery = sprintf('INSERT INTO %s'
                          .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                          .' VALUES (NOW(), "%s", "%s", "delete", "%s", "%s", "%s", "%s", "")',
                          $changeLog, addslashes(\OC_User::getUser()),
                          addslashes($_SERVER['REMOTE_ADDR']), addslashes($table),
                          addslashes($recId), addslashes($recIdColumn), addslashes(serialize($oldValues)));

      //\OCP\Util::writeLog(Config::APP_NAME, "QUERY: ".$logQuery, \OCP\Util::DEBUG);

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      self::query($logQuery, $handle, false, true);

      if ($ownConnection) {
        self::close($handle);
      }
      return true; // don't care
    }

    /**Insert an "update" entry into the changelog table. The function
     * will log the inserted data, the user name and the remote IP
     * address.
     *
     * @param[in] $table The affected SQL table.
     *
     * @param[in] $recIdColumn The column name of the row-key.
     *
     * @param[in] $oldValues An associative array where the keys are
     * the column names and the values are the respective old values
     * which will be removed. $oldValues[$recIdColumn] should be the
     * respective row-key for the affected row.
     *
     * @param[in] $newValues An associative array where the keys are
     * the column names and the values are the respective new values
     * which were injected into the table. The change-log entry will
     * only record changed values.
     *
     * @param[in] $handle Data-base connection, as returned by
     * self::open(). If null, then a new connection is opened.
     *
     * @param[in] $changeLog The name of the change-log table.
     *
     * @return true
     *
     */
    static public function logUpdate($table, $recIdColumn, $oldValues, $newValues,
                                     $handle = null, $changeLog = 'changelog')
    {
      if (empty($newValues) || empty($changeLog)) {
        return true; // don't care
      }

      $recId = $oldValues[$recIdColumn];
      $changed = array();
      foreach($newValues as $col => $value) {
        if (isset($oldValues[$col]) && $oldValues[$col] == $value) {
          continue;
        }
        $changed[$col] = $value;
      }

      if (count($changed) == 0) {
        return true; // nothing changed
      }

      // log the result, but ignore any errors generated by the log query
      $recId = $oldValues[$recIdColumn];

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = self::connect(Config::$pmeopts);
      }

      foreach ($changed as $key => $value) {
        $oldValue = isset($oldValues[$key]) ? $oldValues[$key] : '';
        $logQuery = sprintf('INSERT INTO %s'
                            .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
                            .' VALUES (NOW(), "%s", "%s", "update", "%s", "%s", "%s", "%s", "%s")',
                            $changeLog, addslashes(\OC_User::getUser()),
                            addslashes($_SERVER['REMOTE_ADDR']), addslashes($table),
                            addslashes($recId), addslashes($key),
                            addslashes($oldValue), addslashes($value));
        //\OCP\Util::writeLog(Config::APP_NAME, "QUERY: ".$logQuery, \OCP\Util::DEBUG);

        self::query($logQuery, $handle, false, true);
      }

      if ($ownConnection) {
        self::close($handle);
      }
      return true; // don't care
    }

  };

} // namespace

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
