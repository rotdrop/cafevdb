<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Manage respectively edit and register instruments with the database.
 */
class Instruments
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';

  public function __construct($execute = true)
  {
    parent::__construct($execute);
  }

  public function shortTitle()
  {
    return L::t("Add new Instruments");
  }

  public function headerText()
  {
    $header = $this->shortTitle();

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  // called form Instrumentation::display()
  public function display()
  {
    Config::init();
    global $debug_query;
    $debug_query = Util::debugMode('query');

    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $opts            = $this->opts;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;
    $userExtraFields = $this->userExtraFields;

    /*
     * IMPORTANT NOTE: This generated file contains only a subset of huge amount
     * of options that can be used with phpMyEdit. To get information about all
     * features offered by phpMyEdit, check official documentation. It is available
     * online and also for download on phpMyEdit project management page:
     *
     * http://platon.sk/projects/main_page.php?project_id=5
     *
     * This file was generated by:
     *
     *                    phpMyEdit version: 5.7.1
     *       phpMyEdit.class.php core class: 1.204
     *            phpMyEditSetup.php script: 1.50
     *              generating setup script: 1.50
     */

    $opts['tb'] = 'Instrumente';

    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'Template' => 'instruments',
      'DisplayClass' => 'Instruments',
      'RecordsPerPage' => $recordsPerPage);

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Sortierung');

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'APVC';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] =  array_merge($opts['display'],
                                    array(
                                      'form'  => true,
                                      'query' => true,
                                      'sort'  => true,
                                      'time'  => true,
                                      'tabs'  => false
                                      ));

    // Set default prefixes for variables
    $opts['js']['prefix']               = 'PME_js_';
    $opts['dhtml']['prefix']            = 'PME_dhtml_';
    $opts['cgi']['prefix']['operation'] = 'PME_op_';
    $opts['cgi']['prefix']['sys']       = 'PME_sys_';
    $opts['cgi']['prefix']['data']      = 'PME_data_';

    /* Get the user's default language and use it if possible or you can
       specify particular one you want to use. Refer to official documentation
       for list of available languages. */
    //  $opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';

    /* Table-level filter capability. If set, it is included in the WHERE clause
       of any generated SELECT statement in SQL query. This gives you ability to
       work only with subset of data from table.

       $opts['filters'] = "column1 like '%11%' AND column2<17";
       $opts['filters'] = "section_id = 9";
       $opts['filters'] = "PMEtable0.sessions_count > 200";
    */

    /* Field definitions
   
       Fields will be displayed left to right on the screen in the order in which they
       appear in generated list. Here are some most used field options documented.

       ['name'] is the title used for column headings, etc.;
       ['maxlen'] maximum length to display add/edit/search input boxes
       ['trimlen'] maximum length of string content to display in row listing
       ['width'] is an optional display width specification for the column
       e.g.  ['width'] = '100px';
       ['mask'] a string that is used by sprintf() to format field output
       ['sort'] true or false; means the users may sort the display on this column
       ['strip_tags'] true or false; whether to strip tags from content
       ['nowrap'] true or false; whether this field should get a NOWRAP
       ['select'] T - text, N - numeric, D - drop-down, M - multiple selection
       ['options'] optional parameter to control whether a field is displayed
       L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
       Another flags are:
       R - indicates that a field is read only
       W - indicates that a field is a password field
       H - indicates that a field is to be hidden and marked as hidden
       ['URL'] is used to make a field 'clickable' in the display
       e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
       ['URLtarget']  HTML target link specification (for example: _blank)
       ['textarea']['rows'] and/or ['textarea']['cols']
       specifies a textarea is to be used to give multi-line input
       e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
       ['values'] restricts user input to the specified constants,
       e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
       ['values']['table'] and ['values']['column'] restricts user input
       to the values found in the specified column of another table
       ['values']['description'] = 'desc_column'
       The optional ['values']['description'] field allows the value(s) displayed
       to the user to be different to those in the ['values']['column'] field.
       This is useful for giving more meaning to column values. Multiple
       descriptions fields are also possible. Check documentation for this.
    */

    $opts['fdd']['Id'] = array(
      'name'     => 'Id',
      'select'   => 'T',
      'options'  => '', // auto increment
      'maxlen'   => 11,
      'default'  => '0',
      'sort'     => true,
      );

    $opts['fdd']['Instrument'] = array(
                                       'name'     => 'Instrument',
                                       'select'   => 'T',
                                       'options'  => 'ACLFPV',
                                       'maxlen'   => 64,
                                       'sort'     => true
                                       );
    $opts['fdd']['Familie'] = array('name' => 'Familie',
                                    'nowrap' => true,
                                    'select'   => 'M',
                                    'maxlen'   => 12,
                                    'sort'     => true);
    $opts['fdd']['Familie']['values'] = $this->instrumentFamilies;
    // Provide a link to Wikipedia for fun ...
    $opts['fdd']['Sortierung'] = array(
                                       'name'     => 'Orchester Sortierung',
                                       'select'   => 'T',
                                       'maxlen'   => 8,
                                       'sort'     => true
                                       );
    $opts['fdd']['Lexikon'] = array(
      'name' => 'Wikipedia',
      'select' => 'T',
      'options' => 'VLRF',
      'input' => 'V',
      'sql' => "REPLACE('"
."<a "
."href=\"http://de.wikipedia.org/wiki/@@key@@\" "
."target=\"Wikipedia\" "
.">"
."@@key@@@Wikipedie.DE</a>',"
."'@@key@@',`PMEtable0`.`Instrument`)",
      'escape' => false,
      'nowrap' => true
      );
//$opts['fdd']['Lexikon']['URL'] = "http://de.wikipedia.org/wiki/\$key\" target=\"_blank";
//$opts['fdd']['Lexikon']['URLdisp'] = "\$key@Wikipedia.DE";

    $opts['triggers']['update']['before']  = 'CAFEVDB\Instruments::beforeUpdateTrigger';
    $opts['triggers']['insert']['before']  = 'CAFEVDB\Instruments::beforeInsertTrigger';

    $opts['execute'] = $this->execute;

    $this->pme = new \phpMyEdit($opts);
  }

  /** phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    // $newvals contains the new values

    // Fetch the current list of instruments
    $instruments = mySQL::multiKeys('Musiker', 'Instrumente', $pme->dbh);
    array_push($instruments, $newvals['Instrument']);
    sort($instruments, SORT_FLAG_CASE|SORT_STRING);

    // Now inject the new chain of instruments into Musiker table
    $sqlquery = "ALTER TABLE `Musiker` CHANGE `Instrumente`
 `Instrumente` SET('" . implode("','", $instruments) . "')";
    if (!$pme->myquery($sqlquery)) {
      Util::error(L::t("Could not execute the query\n%s\nSQL-Error: %s",
                       array($sqlquery, mysql_error())), true);
    }

    // Now do the same with the Besetzungen-table
    $instruments = mySQL::multiKeys('Besetzungen', 'Instrument', $pme->dbh);
    array_push($instruments, $newvals['Instrument']);
    sort($instruments, SORT_FLAG_CASE|SORT_STRING);

    $sqlquery = "ALTER TABLE `Besetzungen` CHANGE `Instrument`
 `Instrument` ENUM('" . implode("','", $instruments) . "')";
    if (!$pme->myquery($sqlquery)) {
      Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
    }

    // Now do the same with the Projekte-table
    $instruments = mySQL::multiKeys('Projekte', 'Besetzung', $pme->dbh);
    array_push($instruments, $newvals['Instrument']);
    sort($instruments, SORT_FLAG_CASE|SORT_STRING);

    $sqlquery = "ALTER TABLE `Projekte` CHANGE `Besetzung`
 `Besetzung` SET('" . implode("','", $instruments) . "') COMMENT 'Benötigte Instrumente'";
    if (!$pme->myquery($sqlquery)) {
      Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
    }

    // Now insert also another column into BesetzungsZahlen
    $sqlquery = "ALTER TABLE `BesetzungsZahlen` ADD COLUMN `".$newvals['Instrument']."` TINYINT NOT NULL DEFAULT '0'";
    if (!$pme->myquery($sqlquery)) {
      Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
    }

    return true;
  }

  /** phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   *
   * @bug Convert this to a function triggering a "user-friendly" error message.
   */
  public static function beforeUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    // $newvals contains the new values
    
    //print_r($changed);

    if (array_search('Instrument', $changed) === false) {
      return true;
    } else {
      // This is a nightmare. later

      /* Ok. Attempt to rename. Fetch all multi-keys from all tables,
       * check that the old value is already present and replace it
       * with the new value ..
       */
      $oldInstrument = $oldvals['Instrument'];
      $instrument = $newvals['Instrument'];

      $tables = array();
      $tables[] = array('name' => 'Musiker',
                        'field' => 'Instrumente',
                        'type' =>  'SET',
                        'comment' => '');
      $tables[] = array('name' => 'Besetzungen',
                        'field' => 'Instrument',
                        'type' => 'ENUM',
                        'comment' => '');
      $tables[] = array('name' => 'Projekte',
                        'field' => 'Besetzung',
                        'type' => 'SET',
                        'comment' => L::t('Needed Instruments'));

      foreach ($tables as $table) {
        $instruments = mySQL::multiKeys($table['name'], $table['field'], $pme->dbh);
        if (!array_search($oldInstrument, $instruments)) {
          trigger_error(L::t("Instrument `%s' does not yet exist in table `%s', cannot change its name to `%s'",
                             array($oldInstrument, $table['name'], $instrument)), E_USER_ERROR);
        }
        if (array_search($instrument, $instruments)) {
          trigger_error(L::t("Instrument `%s' already exists in table `%s', cannot change its name from `%s'",
                             array($instrument, $table['name'], $oldInstrument)), E_USER_ERROR);
        }
      }

      // Fine, loop again and exchange it
      foreach ($tables as $table) {
        $instruments = mySQL::multiKeys($table['name'], $table['field'], $pme->dbh);

        $comment = $table['comment'] != '' ? " COMMENT '".$table['comment']."'" : "";
        
        // 1st step: inject new enum value
        $instruments[] = $instrument;
        sort($instruments, SORT_FLAG_CASE|SORT_STRING);

        $sqlQuery = "ALTER TABLE `".$table['name']."` CHANGE `".$table['field']."`
 `".$table['field']."` ".$table['type']."('".implode("','", $instruments)."')";
        
        if (!$pme->myquery($sqlQuery)) {
          Util::error(L::t("SQL-Error ``%s''. Could not execute the query ``%s''",
                           array(mysql_error(), $sqlQuery)), true);
          return false;
        }

        // 2nd step: alter all rows to use the new enum value
        if ($table['type'] == 'ENUM') {
          $sqlQuery = "UPDATE `".$table['name']."`
 SET `".$table['field']."` = '".$instrument."'
 WHERE `".$table['field']."` = '".$oldInstrument."'";
        } else {
          $sqlQuery = "UPDATE `".$table['name']."`
 SET `".$table['field']."` = TRIM(',' FROM CONCAT(`".$table['field']."`,',','".$instrument."'))
 WHERE `".$table['field']."` LIKE '%".$oldInstrument."%'";
        }
        
        if (!$pme->myquery($sqlQuery)) {
          Util::error(L::t("SQL-Error ``%s''. Could not execute the query `` %s ''",
                           array(mysql_error(), $sqlQuery)), true);
          return false;
        }

        // 3rd step: drop old enum value
        $key = array_search($oldInstrument, $instruments);
        unset($instruments[$key]);
        sort($instruments, SORT_FLAG_CASE|SORT_STRING);
        $sqlQuery = "ALTER TABLE `".$table['name']."` CHANGE `".$table['field']."`
 `".$table['field']."` ".$table['type']."('".implode("','", $instruments)."')";
        
        if (!$pme->myquery($sqlQuery)) {
          Util::error(L::t("SQL-Error ``%s''. Could not execute the query ``%s''",
                           array(mysql_error(), $sqlQuery)), true);
          return false;
        }
      }

      // Finally change the column name in "BesetzungsZahlen"
      $sqlQuery = "ALTER TABLE `BesetzungsZahlen` CHANGE `".$oldInstrument."` `".$instrument."`
  TINYINT NOT NULL DEFAULT '0'";
      if (!$pme->myquery($sqlQuery)) {
        Util::error(L::t("SQL-Error ``%s''. Could not execute the query ``%s''",
                         array(mysql_error(), $sqlQuery)), true);
        return false;
      }

      // And in principle, if anything goes wrong, we should clean up ...

      return true;
    }
  }

  // Sort the given list of instruments according to orchestral ordering
  // as defined in the Instrumente table.
  public static function sortOrchestral($list, $handle)
  {
    $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = mySQL::query($query, $handle);
  
    $final = array();
    while ($line = mySQL::fetch($result)) {
      $tblInst = $line['Instrument'];
      if (array_search($tblInst, $list) !== false) {
        $final[] = $tblInst;
      }
    }

    return $final;
  }

  // Fetch the instruments required for a specific project
  public static function fetchProjectInstruments($projectId, $handle) {

    $query = 'SELECT `Besetzung` FROM `Projekte` WHERE `Id` = '.$projectId;
    $result = mySQL::query($query);

    // Ok there should be only one row
    if (!($line = mySQL::fetch($result))) {
      Util::error(L::t("Could not fetch instruments for project-id %s", array($projectId)), true);
    }
    $ProjInsts = explode(',',$line['Besetzung']);

    // Now sort it in "natural" order
    return self::sortOrchestral($ProjInsts, $handle);
  }

  // Fetch the project-instruments of the project musicians, possibly to
  // do some sanity checks with the project's instrumentation, or simply
  // to add all instruments to the projects instrumentation list.
  public static function fetchProjectMusiciansInstruments($projectId, $handle)
  {
    $query = 'SELECT DISTINCT `Instrument` FROM `Besetzungen` WHERE `ProjektId` = '.$projectId;
    $result = mySQL::query($query);

  
    $instruments = array();
    while ($line = mySQL::fetch($result)) {
      $instruments[] = $line['Instrument'];
    }

    // Now sort it in "natural" order
    return self::sortOrchestral($instruments, $handle);
  }

  // Fetch all instruments of the musicians subscribed to the project
  // and add them to the instrumentation. If $replace is true, then
  // remove the old instrumentation, otherwise the new instrumentation
  // is the union of the old instrumentation and the instruments
  // actually subscribed to the project.
  public static function updateProjectInstrumentationFromMusicians($projectId, $handle, $replace = false)
  {
    // Make sure the instrumentation numbers exist
    $query = 'INSERT IGNORE INTO `BesetzungsZahlen` (`ProjektId`) VALUES ('.$projectId.')';
    mySQL::query($query, $handle);

    $musinst = self::fetchProjectMusiciansInstruments($projectId, $handle);

    if ($replace) {
      $prjinst = $musinst;
    } else {
      $prjinst = self::fetchProjectInstruments($projectId, $handle);
    }

    $prjinst = array_unique(array_merge($musinst, $prjinst));
    $prjinst = self::sortOrchestral($prjinst, $handle);


    $query = "UPDATE `Projekte` SET `Besetzung`='".implode(',',$prjinst)."' WHERE `Id` = $projectId";
    mySQL::query($query, $handle);
  
    return $prjinst;
  }

  // Fetch the instruments and sort them according to Instruments.Sortierung
  public static function fetch($handle) {

    $Instruments = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

    $query = 'SELECT `Instrument` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = mySQL::query($query, $handle);
  
    $final = array();
    while ($line = mySQL::fetch($result)) {
      //CAFEVerror("huh".$line['Instrument'],false);
      $tblInst = $line['Instrument'];
      if (array_search($tblInst, $Instruments) === false) {
        Util::error('"'.$tblInst.'" not found in '.implode(',',$Instruments), true);
      }
      $final[] = $tblInst;
    }

    return $final;
  }

  // Fetch all instruments, group by instrument family.
  public static function fetchGrouped($handle) {

    $Instruments = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

    $query = 'SELECT * FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = mySQL::query($query, $handle);

    $resultTable = array();
    while ($line = mySQL::fetch($result)) {
      //CAFEVerror("huh".$line['Instrument'],false);
      $instrument = $line['Instrument'];
      $family     = $line['Familie'];
      if (array_search($instrument, $Instruments) === false) {
        Util::error('"'.$instrument.'" not found in '.implode(',',$Instruments), true);
      }
      $resultTable[$instrument] = L::t($family);
    }
    if (false) {
      // Dummy, in order to generate translation entries
      L::t("Streich,Saiten");
      L::t("Streich,Zupf");
      L::t("Blas,Holz");
      L::t("Blas,Blech");
      L::t("Schlag");
      L::t("Sonstiges");        
    }

    return $resultTable;
  }

  // Check for consistency
  public static function check($handle, $silent = false) {

    $checkers = array('Musicians','Projects','Instrumentation Numbers','Instruments');

    $instruments = array();
    $instruments[$checkers[0]] = mySQL::multiKeys('Musiker', 'Instrumente', $handle);
    $instruments[$checkers[1]]  =  mySQL::multiKeys('Besetzungen', 'Instrument', $handle);

    $query = "SHOW COLUMNS FROM BesetzungsZahlen WHERE NOT FIELD IN ('Id','ProjektId')";
    $result = mySQL::query($query, $handle) or die("Couldn't execute query");

    $instruments[$checkers[2]] = array();
    while ($line = mySQL::fetch($result)) {
      $instruments[$checkers[2]][] = $line['Field'];
    }

    $query = "SELECT Instrument FROM Instrumente WHERE 1";
    $result = mySQL::query($query, $handle) or die("Couldn't execute query");
    $instruments[$checkers[3]] = array();
    while ($line = mySQL::fetch($result)) {
      $instruments[$checkers[3]][] = $line['Instrument'];
    }

    // Diff everything with every other
    $diff = array();
    foreach($checkers as $key1) {
      $diff[$key1] = array();
      foreach($checkers as $key2) {
        $diff[$key1][$key2] = array_diff($instruments[$key1], $instruments[$key2]);
      }
    }

    $result = true;
    foreach ($checkers as $key1) {
      foreach($checkers as $key2) {
        if (count($diff[$key1][$key2]) > 0) {
          $result = false;
          if (!$silent) {
            echo "<P><HR/>
<H4>Instruments in ``$key1''-table which are not in ``$key2''-table:</H4>
<UL>\n";
            foreach ($diff[$key1][$key2] as $instrument) {
              echo "<LI>".$instrument."\n";
            }
            echo "</UL>\n";
          }
        }
      }
    }
    if (!$silent && !$result) {
      echo "<HR/><P>\n";
    } 
    return $result;
  }

  // Make sure the Instrumente table has all instruments used in the Musiker
  // table. Delete everything else.
  public static function sanitizeTable($handle, $deleteexcess = false) {

    $Instrumente = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

    foreach ($Instrumente as $value) {
      $query = "INSERT IGNORE INTO `Instrumente` (`Instrument`) VALUES ('$value')";
      $result = mySQL::query($query, $handle);
    }

    // Now the table contains at least all instruments, now remove excess elements.

    if ($deleteexcess) {
      // Build SQL Query  
      $query = "SELECT `Instrument` FROM `Instrumente` WHERE 1";
      
      // Fetch the result or die
      $result = mySQL::query($query, $query);

      $dropList = array();
      while ($line = mySQL::fetch($result)) {
        $tblInst = $line['Instrument'];
        if (array_search($tblInst, $Instrumente) === false) {
          $dropList[$tblInst] = true;
        }
      }

      foreach ($dropList as $key => $value) {
        $query = "DELETE FROM `Instrumente` WHERE `Instrument` = '$key'";
        $result = mySQL::query($query);
      }
    }
  }

}; // class 

}

?>
