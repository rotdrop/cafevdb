<?php

namespace CAFEVDB
{

class Instruments
  extends Instrumentation
{
  public function __construct()
  {
    parent::__construct();
  }

  // called form Instrumentation::display()
  public function display()
  {
    global $debug_query;
    //Config::$debug_query = true;
    //$debug_query = true;


    $action          = $this->action;
    $project         = $this->project;
    $projectId       = $this->projectId;
    $opts            = $this->opts;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;
    $userExtraFields = $this->userExtraFields;

    echo <<<__EOT__
<div class="cafevdb-pme-header-box">
  <div class="cafevdb-pme-header">
    <h3>Instrumente hinzufügen</h3>
    <h4>Löschen ist nicht vorgesehen, dafür bitte phpMyAdmin verwenden.
Auch den Instrumentennamen sollte man nicht ändern</H4>
  </div>
</div>

__EOT__;

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

    $opts['cgi']['persist'] = array('Projekt' => $project,
                                    'ProjektId' => $projectId,
                                    'Action' => $action,
                                    'RecordsPerPage' => $recordsPerPage);

    // Name of field which is the unique key
    $opts['key'] = 'Instrument';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'string';

    // Sorting field(s)
    $opts['sort_field'] = array('Sortierung');

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'APVFC';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = array(
                             'form'  => true,
                             'query' => true,
                             'sort'  => true,
                             'time'  => true,
                             'tabs'  => true
                             );

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

    $opts['fdd']['Instrument'] = array(
                                       'name'     => 'Instrument',
                                       'select'   => 'T',
                                       'options'  => 'ACLFPV',
                                       'maxlen'   => 64,
                                       'sort'     => true
                                       );
    $opts['fdd']['Familie'] = array('name' => 'Familie',
                                    'nowrap' => true,
                                    'select'   => 'C',
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
                                    'options' => 'VLR',
                                    'input' => 'V',
                                    'sql' => "\"Wikipedie.DE\"",
                                    'nowrap' => true
                                    );
    $opts['fdd']['Lexikon']['URL'] = "http://de.wikipedia.org/wiki/\$key";
    $opts['fdd']['Lexikon']['URLdisp'] = "\$key@Wikipedia.DE";

    $opts['triggers']['update']['before']  = Config::$triggers.'instruments.TUB.inc.php';
    $opts['triggers']['insert']['before']  = Config::$triggers.'instruments.TIB.inc.php';

    new \phpMyEdit($opts);
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
      CAFEVerror("Could not fetch instruments for project", true);
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
    $musinst = fetchProjectMusiciansInstruments($projectId, $handle);
    if ($replace) {
      $prjinst = $musinst;
    } else {
      $prjinst = fetchProjectInstruments($projectId, $handle);
    }
    $prjinst = array_unique(array_merge($musinst, $prjinst));
    $prjinst = self::sortOrchestral($prjinst, $handle);

    $query = "UPDATE `Projekte` SET `Besetzung`='".implode(',',$prjinst)."' WHERE `Id` = $projectId";
    mySQL::query($query, $handle);
    //CAFEVerror($query, false);
  
    return $prjinst;
  }

  // Fetch the instruments and sort them according to Instruments.Sortierung
  public static function fetch($handle) {

    $Instruments = mySQL::multiKeys('Musiker', 'Instrumente', $handle);

    $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = mySQL::query($query, $handle);
  
    $final = array();
    while ($line = mySQL::fetch($result)) {
      //CAFEVerror("huh".$line['Instrument'],false);
      $tblInst = $line['Instrument'];
      if (array_search($tblInst, $Instruments) === false) {
        Util::error('"'.$tblInst.'" not found in '.implode(',',$Instruments), true);
      }
      array_push($final, $tblInst);
    }

    return $final;
  }

  // Check for consistency
  public static function check($handle, $silent = false) {

    $checkers = array('Musicians','Projects','Instrumentation Numbers','Instruments');

    $instruments = array();
    $instruments[$checkers[0]] = mySQL::multiKeys('Musiker', 'Instrumente', $handle);
    $instruments[$checkers[1]]  =  mySQL::multiKeys('Besetzungen', 'Instrument', $handle);

    $query = "SHOW COLUMNS FROM BesetzungsZahlen WHERE FIELD NOT LIKE 'ProjektId'";
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
