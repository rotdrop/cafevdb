<?php
/**@author Claus-Justus Heine
 * @copyright 2012-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * You should have received a copy of the GNU General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**@file
 * Administrative utilities.
 *
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Administrative support functions.
 */
  class Admin
  {
    /**Define functions we need. Can be generated/updated via "expert"
     * operations. The stuff comes without surrounding delimite
     * madness. Oh, mysql is sOoooo SWEET!!!
     */
    private static $dataBaseRoutines = array(
      /**Generate an in-memory table with consecutive numbers,
       * starting from 1. The table is essentially read-only (but for
       * the case that more numbers are needed) and resides in
       * memory. After server restart it needs to be rebuilt.
       */
      'generateNumbers' =>
      'PROCEDURE generateNumbers(IN min INT)
BEGIN
    CREATE TABLE IF NOT EXISTS numbers
        ( N INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY )
        ENGINE memory
        AS SELECT 1 N UNION ALL SELECT 2;
    SELECT COUNT(*) FROM numbers INTO @max;
    IF @max = 0 THEN
        INSERT INTO numbers SELECT 1 N UNION ALL SELECT 2;
        SET @max = 2;
    END IF;
    WHILE @max < min DO
        INSERT IGNORE INTO numbers SELECT (hi.N-1)*(SELECT COUNT(*) FROM numbers)+(lo.N-1)+1 AS N
          FROM numbers lo, numbers hi;
        SELECT COUNT(*) FROM numbers INTO @max;
    END WHILE;
END',
      /**Beware: the securitz model of mySQL is really really really
       * just totally brain-damaged. As a side-effect, you need
       * "super" priviges even for functions withtout any
       * side-effects.
       */
      'splitString' =>
      "FUNCTION splitString(
  x VARCHAR(1023) CHARACTER SET utf8,
  delim VARCHAR(12),
  pos INT
)
RETURNS VARCHAR(255) CHARACTER SET utf8
DETERMINISTIC
RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       CHAR_LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
                        delim, '')",
      /**Compute the number of tokens given delimiter.
       */
      'tokenCount' =>
      "FUNCTION tokenCount(
  x VARCHAR(1023) CHARACTER SET utf8,
  delim VARCHAR(12)
)
RETURNS INTEGER
DETERMINISTIC
RETURN CHAR_LENGTH(x) - CHAR_LENGTH(REPLACE(x, delim, '')) + 1",
      );

    /**Define some SQL-functions ... */
    public static function recreateDataBaseRoutines($handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $success = array();
      $error = array();
      foreach(self::$dataBaseRoutines AS $name => $routine) {
        $query = 'CREATE OR REPLACE '.$routine;

        //error_log($name.': '.$query);

        $result = mySQL::query($query, $handle, false, true);
        if ($result === false) {
          $error[] = mySQL::error($handle).': '.$name;
        } else {
          $success[] = $name;
        }
      };

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return array('error' => $error,
                   'success' => $success);
    }

    /**Get the Unix time-stamp at which the orchestra DB was modified
     * the last time, i.e. the maximum over the modification time of all
     * tables.
     */
    public static function getLastModified()
    {
      Config::init();

      $tzquery = "SELECT @@system_time_zone";

      $query = "SELECT MAX(UPDATE_TIME)
FROM   information_schema.tables
WHERE  TABLE_SCHEMA = '".Config::$dbopts['db']."'";

      $handle = mySQL::connect(Config::$dbopts);

      $result = mySQL::query($tzquery, $handle);
      $numrows = mySQL::numRows($result);
      if ($numrows === 1) {
        foreach (mySQL::fetch($result) as $key => $value) {
          $tz = $value;
        }
      }

      $result = mySQL::query($query, $handle);
      $numrows = mySQL::numRows($result);

      $stamp = -1;
      if ($numrows !== 1) {
        $stamp = time(); // pretend just modified on error
      } else {
        foreach (mySQL::fetch($result) as $key => $value) {
          $stamp = strtotime($value." ".$tz);
        }
      }
      mySQL::close($handle);

      return $stamp;
    }

    /**TBD*/
    public static function fillInstrumentationNumbers()
    {
      Config::init();

      // Fetch the actual list of instruments, we will need it anyway
      $handle = mySQL::connect(Config::$dbopts);

      $instruments = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

      // Current mysql versions do not seem to support "IF NOT EXISTS", so
      // we simply try to do our best and add one column in each request.

      foreach ($instruments as $instr) {
        $query = 'ALTER TABLE `BesetzungsZahl` ADD COLUMN `'.$instr."` TINYINT NOT NULL DEFAULT '0'";
        $result = mySQL::query($query, $handle); // simply ignore any error
      }
      mySQL::close($handle);
    }

    /**TBD*/
    public static function checkInstrumentsTable()
    {
      Config::init();

      $handle = mySQL::connect(Config::$pmeopts);
      if (Instruments::check($handle)) {
        print '<H4>Instruments are consistent.</H4>';
      } else {
        print '<H4>Instruments are inconsistent.</H4>';
      }
      mySQL::close($handle);


    }

    /**TBD*/
    public static function sanitizeInstrumentsTable()
    {
      Config::init();

      $handle = mySQL::connect(Config::$pmeopts);
      if (!Instruments::check($handle)) {
        Instruments::sanitizeTable($handle, false);
        Instruments::check($handle);
      } else {
        print '<H4>Not needed, instruments are consistent.</H4>';
      }
      mySQL::close($handle);
    }

    /**TBD*/
    public static function recreateAllViews()
    {
      Config::init();

      $handle = mySQL::connect(Config::$pmeopts);

      // Fetch the list of projects
      $query = 'SELECT `Id` FROM `Projekte` WHERE 1';
      $result = mySQL::query($query, $handle);

      while ($line = mySQL::fetch($result)) {
        $projectId = $line['Id'];

        print '<H4>Recreating view for project '.$projectId.'</H4><BR/>';

        // Just diagnostic
        //Util::error("Before Create ".$projectId, false);

        Projects::createView($projectId, false, $handle);

        // Just diagnostic
        //Util::error("After Create ".$projectId, false);
      }

      mySQL::close($handle);

      print '<H4>Success</H4><BR/>';

    }

    /**Normalize all phone numbers, and move mobile numbers to their
     * proper column.
     */
    public static function sanitizePhoneNumbers()
    {
      Config::init();

      $handle = mySQL::connect(Config::$pmeopts);

      // Fetch the list of projects
      $query = 'SELECT `Id`, `MobilePhone`, `FixedLinePhone` FROM `Musiker` WHERE 1';
      $result = mySQL::query($query, $handle);

      while ($line = mySQL::fetch($result)) {
        $id = $line['Id'];
        $oldMobile = $mobile = trim($line['MobilePhone']);
        $oldFixedLine = $fixedLine = trim($line['FixedLinePhone']);

        $mobileIsMobile = false;
        $fixedIsMobile = false;

        $mobileValid = false;
        $fixedValid = false;

        if (PhoneNumbers::validate($mobile)) {
          $mobile = PhoneNumbers::format();
          $mobileIsMobile = PhoneNumbers::isMobile();
          $mobileValid = true;
          //print '<H4>Phone number '.$mobile.' validated.</H4><BR/>';
        } else if ($mobile != '') {
          print '<H4>Phone number '.$mobile.' failed to validate.</H4><BR/>';
        }

        if (PhoneNumbers::validate($fixedLine)) {
          $fixedLine = PhoneNumbers::format();
          $fixedIsMobile = PhoneNumbers::isMobile();
          $fixedValid = true;
          //print '<H4>Phone number '.$fixedLine.' validated.</H4><BR/>';
        } else if ($fixedLine != '') {
          print '<H4>Phone number '.$fixedLine.' failed to validate.</H4><BR/>';
        }

        // switch columns as appropriate

        if (!$fixedValid && $mobile != '' && !$mobileIsMobile) {
          $tmp = $fixedLine;
          $fixedLine = $mobile;
          $mobile = $tmp;
          print '<H4>Moving fixed line number '.$fixedLine.' to correct column.</H4><BR/>';
        }

        if (!$mobileValid && $fixedLine != '' && $fixedIsMobile) {
          $tmp = $mobile;
          $mobile = $fixedLine;
          $fixedLine = $tmp;
          $mobileIsMobile = true;
          $fixedIsMobile = false;
          print '<H4>Moving mobile number '.$mobile.' to correct column.</H4><BR/>';
        }

        if ($mobile != '' && $fixedLine != '' && !$mobileIsMobile && $fixedIsMobile) {
          $tmp = $fixedLine;
          $fixedLine = $mobile;
          $mobile = $tmp;
          $mobileIsMobile = true;
          $fixedLineIsMovile = false;
          print '<H4>Exchanging columns of '.$mobile.' and '.$fixedLine.'.</H4><BR/>';
        }

        if ($oldMobile != $mobile || $oldFixedLine != $fixedLine) {
          $query = "UPDATE `Musiker` SET `MobilePhone` = '".$mobile."', `FixedLinePhone` = '".$fixedLine."'
  WHERE `Id` = ".$id;
          mySQL::query($query, $handle);
          //print '<H4>'.$query.'</H4><BR/>';
        }

      }

      mySQL::close($handle);
    }

    public static function sanitizeImageData($imageTable, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // First make sure that the needed fields exist

      $columns = mySQL::columns($imageTable, $handle);

      $afterColumn = $columns[1];

      $query = "ALTER IGNORE TABLE `".$imageTable."`
  ADD `MimeType` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_general_ci NULL
  AFTER `".$afterColumn."`";
      mySQL::query($query, $handle);

      $query = "ALTER IGNORE TABLE `".$imageTable."`
  ADD `MD5` CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NULL
  AFTER `MimeType`";
      mySQL::query($query, $handle);

      // then recompute the stuff
      $query = "SELECT * FROM `".$imageTable."` WHERE 1";
      $result = mySQL::query($query, $handle);
      while ($row = mySQL::fetch($result)) {
        $md5 = md5($row['ImageData']);
        $image = new \OC_Image();
        $image->loadFromBase64($row['ImageData']);
        $mimeType = $image->mimeType();

        mySQL::update($imageTable,
                      "Id = ".$row['Id'],
                      array('MimeType' => $mimeType, 'MD5' => $md5),
                      $handle);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }
    }

  };

}

?>
