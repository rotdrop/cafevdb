<?php
/**@author Claus-Justus Heine
 * @copyright 2012-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    $numrows = mysql_num_rows($result);
    if ($numrows === 1) {
      foreach (mySQL::fetch($result) as $key => $value) {
        $tz = $value;    
      }
    }

    $result = mySQL::query($query, $handle);
    $numrows = mysql_num_rows($result);

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

};

}

?>