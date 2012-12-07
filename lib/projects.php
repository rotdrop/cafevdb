<?php

/* require_once("favicon.php"); */
/* require ("pme/enablejscal.html"); */
/* require ("pme/enabletinymce.html"); */
/* require_once("ProjektFunktionen.php"); */
/* require_once('Instruments.php'); */

/** Helper class for displaying projects.
 */
class CAFEVDB_Projects
{
  static public function display()
  {
    CAFEVDB_Config::init();

    /* echo '<PRE>'; */
    /* print_r($_SERVER); */
    /* echo '</PRE>'; */

    $handle = CAFEVDB_mySQL::connect(CAFEVDB_Config::$pmeopts);

    $Instrumente = CAFEVDB_Instruments::fetch($handle);

    CAFEVDB_mySQL::close($handle);

    $cafevclass = 'cafev-pme';
    echo '<div class="'.$cafevclass.'">'."\n";

    echo '<h2>Camerata Projekte</h2> <h4>Bitte auf das Projekt-K&uuml;rzel
klicken, um die Besetzungliste zu editieren. F&uuml;r allgemeine
Eigenschaften bitte die "add", "change" etc. Buttons unten anklicken.</h4>';

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

    $opts = CAFEVDB_Config::$pmeopts;
    $opts['cgi']['persist'] = CAFEVDB_Util::cgiValue('app');

    $opts['tb'] = 'Projekte';

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Name');

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = 15;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

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
    //$opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE']; // . '-UTF8';

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
                               'options'  => 'LAVCPDR', // auto increment
                               'maxlen'   => 11,
                               'default'  => '0',
                               'sort'     => true
                               );
    $opts['fdd']['Name'] = array(
                                 'name'     => 'Projekt-Name',
                                 'select'   => 'T',
                                 'maxlen'   => 64,
                                 'sort'     => true,
                                 );
    $opts['fdd']['Name']['URL'] = '?app=cafevdb&Template=instrumentation&Projekt=$value&ProjektId=$key&Action=ShortDisplayProjectMusicians';

    $opts['fdd']['Programm'] = array(
                                     'name'     => 'Programm',
                                     'select'   => 'T',
                                     'maxlen'   => 65535,
                                     'textarea' => array('html' => 'Editor',
                                                         'rows' => 5,
                                                         'cols' => 50),
                                     'sort'     => true,
                                     'escape' => false
                                     );

    $opts['fdd']['Besetzung'] = array('name'     => 'Besetzung',
                                      'options'  => 'AVCPD',
                                      'nowrap'   => false,
                                      'select'   => 'C',
                                      'maxlen'   => 136,
                                      'sort'     => true);
    $opts['fdd']['Besetzung']['values'] = $Instrumente;

    $opts['fdd']['Bemerkungen'] = array(
                                        'name'     => 'Bemerkungen',
                                        'select'   => 'T',
                                        'maxlen'   => 65535,
                                        'css'      => array('postfix' => 'prjrem'),
                                        'textarea' => array('html' => 'Editor',
                                                            'rows' => 5,
                                                            'cols' => 50),
                                        'sort'     => true,
                                        'escape'   => false,
                                        'default'  => 'Kosten, Teilnahmebedingungen etc.'
                                        );

    $opts['fdd']['ExtraFelder'] = array('name'     => 'Extra Felder für Teilnehmer',
                                        'options'  => 'LAVCPD',
                                        'select'   => 'T',
                                        'maxlen'   => 1024,
                                        'css'      => array('postfix' => 'prjxtra'),
                                        'textarea' => array('html' => 'NoEditor',
                                                            'rows' => 1,
                                                            'cols' => 128),
                                        'sort'     => false,
                                        'escape' => false,
                                        'help' => false,
                                        'tooltip' => 'Komma-separierte Liste von Extra-Feldern, z.B.:

  DZ:1,Beitrag:2

oder

  DZ,Beitrag

Die Zahl nach dem Doppelpunkt (und der Doppelpunkt) ist optional,
falls vorhanden, gibt das die Zuordnung zu den Spalten in der
"Besetzungen"-Tabelle in der Datenbank. Dort heißen die Felder einfach
"ExtraFeld01" etc. Die Reihenfolge bei Anzeige der Tabelle entspricht
der angegebenen Reihenfolge. Falls man die ändert, sollte man die Zuordnung
zur Extra-Spalte in der Datenbank angeben, z.B. so:

  Beitrag:2,DZ:1

Dann wird die Reihenfolge bei der Anzeige der Tabelle geändert, aber die
Zuordnung zu den Informationen in der Datenbank bleibt erhalten.');

    $opts['fdd']['Konzert1'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Konzert1']['name'] = 'Konzert1';

    $opts['fdd']['KonzertOrt1'] = array(
                                        'name'     => 'KonzertOrt1',
                                        'select'   => 'T',
                                        'maxlen'   => 65535,
                                        'textarea' => array('html' => 'Editor',
                                                            'rows' => 5,
                                                            'cols' => 50),
                                        'sort'     => true,
                                        'escape' => false
                                        );

    $opts['fdd']['Konzert2'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Konzert2']['name'] = 'Konzert2';
    $opts['fdd']['KonzertOrt2'] = array(
                                        'name'     => 'KonzertOrt2',
                                        'select'   => 'T',
                                        'maxlen'   => 65535,
                                        'textarea' => array('html' => 'Editor',
                                                            'rows' => 5,
                                                            'cols' => 50),
                                        'sort'     => true,
                                        'escape' => false
                                        );

    $opts['fdd']['Proben1'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Proben1']['name'] = 'Proben1';
    $opts['fdd']['ProbenKommentar1'] = array(
                                             'name'     => 'ProbenKommentar1',
                                             'select'   => 'T',
                                             'maxlen'   => 65535,
                                             'css'      => array('postfix' => 'rhrslrem'),
                                             'textarea' => array('html' => 'Editor',
                                                                 'rows' => 5,
                                                                 'cols' => 50),
                                             'sort'     => true,
                                             'escape' => false,
                                             );

    $opts['fdd']['Proben2'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Proben2']['name'] = 'Proben2';
    $opts['fdd']['ProbenKommentar2'] = array(
                                             'name'     => 'ProbenKommentar2',
                                             'select'   => 'T',
                                             'maxlen'   => 65535,
                                             'css'      => array('postfix' => 'rhrslrem'),
                                             'textarea' => array('html' => 'Editor',
                                                                 'rows' => 5,
                                                                 'cols' => 50),
                                             'sort'     => true,
                                             'escape' => false,
                                             );

    $opts['fdd']['Proben3'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Proben3']['name'] = 'Proben3';
    $opts['fdd']['ProbenKommentar3'] = array(
                                             'name'     => 'ProbenKommentar3',
                                             'select'   => 'T',
                                             'maxlen'   => 65535,
                                             'css'      => array('postfix' => 'rhrslrem'),
                                             'textarea' => array('html' => 'Editor',
                                                                 'rows' => 5,
                                                                 'cols' => 50),
                                             'sort'     => true,
                                             'escape' => false,
                                             );

    $opts['fdd']['Proben4'] = CAFEVDB_Config::$opts['calendar'];
    $opts['fdd']['Proben4']['name'] = 'Proben4';
    $opts['fdd']['ProbenKommentar4'] = array(
                                             'name'     => 'ProbenKommentar4',
                                             'select'   => 'T',
                                             'maxlen'   => 65535,
                                             'css'      => array('postfix' => 'rhrslrem'),
                                             'textarea' => array('html' => 'Editor',
                                                                 'rows' => 5,
                                                                 'cols' => 50),
                                             'sort'     => true,
                                             'escape' => false,
                                             );

    $opts['triggers']['update']['after'] = CAFEVDB_Config::$triggers.'projects.TUA.inc.php';
    $opts['triggers']['insert']['after'] = CAFEVDB_Config::$triggers.'projects.TIA.inc.php';

    // Maybe we want to keep the view.
    // $opts['triggers']['delete']['after']  = 'Projekte.TDA.inc.php';

    // Now important call to phpMyEdit
    //require_once 'phpMyEdit.class.php';
    //require_once 'phpMyEdit.class.php';
    //new phpMyEdit($opts);
    //require_once 'extensions/phpMyEdit-mce-cal.class.php';
    //new phpMyEdit_mce_cal($opts);

    require_once 'pme/phpMyEdit.class.php';
    new phpMyEdit($opts);

    echo '</div>
';
  }

  /**Generate an associative array of extra-fields. The key is the
   * field-name, the value the number of the extra-field in the
   * Besetzungen-table. We fetch and parse the "ExtraFelder"-field from
   * the "Projekte"-table. The following rules apply:
   *
   * - "ExtraFelder" contains a comma-seprarated field list of the form
   *   FIELD1[:NR1] ,     FIELD2[:NR2] etc.
   *
   * - the explicit association in square brackets is optional, if
   *   omitted than NR is the position of the token in the "ExtraFields"
   *   value. Of course, the square-brackets must not be present, they
   *   have the meaning: "hey, this is optional".
   *
   * Note: field names must be unique.
   */
  public static function extraFields($ProjektId, $handle = false)
  {
    CAFEVDB_Util::debugMsg(">>>>ProjektExtraFelder: Id = $ProjektId");

    $query = 'SELECT `ExtraFelder` FROM `Projekte` WHERE `Id` = '.$ProjektId;
    $result = CAFEVDB_mySQL::query($query, $handle);
    
    // Get the single line
    $line = CAFEVDB_mySQL::fetch($result) or CAFEVDB_Util::error("Couldn't fetch the result for '".$query."'");
    
    if (CAFEVDB_Util::debugMode()) {
      print_r($line);
    }
    
    if ($line['ExtraFelder'] == '') {
      return array();
    } else {
      CAFEVDB_Util::debugMsg("Extras: ".$line['ExtraFelder']);
    }
  
    // Build an array of name - size pairs
    $tmpfields = explode(',',$line['ExtraFelder']);
    if (CAFEVDB_Util::debugMode()) {
      print_r($tmpfields);
    }
    $fields = array();
    $fieldno = 1; // This time we start at ONE _NOT_ ZERO
    foreach ($tmpfields as $value) {
      $value = trim($value);
      $value = explode(':',$value);
      $fields[] = array('name' => $value[0],
                        'pos' => isset($value[1]) ? $value[1] : $fieldno);
      ++$fieldno;
    }

    CAFEVDB_Util::debugMsg("<<<<ProjektExtraFelder");

    return $fields;
  }

  public static function fetchName($ProjektId, $handle = false)
  {
    $query = 'SELECT `Name` FROM `Projekte` WHERE `Id` = '.$ProjektId;
    $result = CAFEVDB_mySQL::query($query, $handle);

    // Get the single line
    $line = CAFEVDB_mySQL::fetch($result) or CAFEVDB_Util::error("Couldn't fetch the result for '".$query."'");

    return $line['Name'];
  }

  /**Make sure the "Besetzungen"-table has enough extra fields. All
   * extra-fields are text-fields.
   *
   */
  public static function createExtraFields($ProjektId, $handle = false)
  {
    CAFEVDB_Util::debugMsg(">>>> ProjektCreateExtraFelder");

    // Fetch the extra-fields.
    $extra = self::extraFields($ProjektId, $handle);
    if (CAFEVDB_Util::debugMode()) {
      print_r($extra);
    }

    /* Then walk the table and simply execute one "ALTER TABLE"
     * statement for each field, ignoring the result, but we check later
     * for a possible error.
     */

    foreach ($extra as $field) {
      // forget about $name, not an issue here.  

      $query = sprintf(
                       'ALTER TABLE `Besetzungen`
   ADD `ExtraFeld%02d` TEXT
   CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
                       $field['pos']);
      $result = @CAFEVDB_mySQL::query($query, $handle, false, true); // ignore the result, be silent
    }

    // Now make sure we have it ...
    $query = "SHOW COLUMNS FROM `Besetzungen` LIKE 'ExtraFeld%'";
    $result = CAFEVDB_mySQL::query($query, $handle);

    // See what we got ...
    $fields = array();
    while ($row = CAFEVDB_mySQL::fetch($result)) {
      if (CAFEVDB_Util::debugMode()) {
        print_r($row);
      }
      $fields[] = $row['Field'];
    }
    if (CAFEVDB_Util::debugMode()) {
      print_r($fields);
    }

    foreach ($extra as $field) {
      $name = sprintf('ExtraFeld%02d', $field['pos']);
      CAFEVDB_Util::debugMsg("Check ".$name);
      if (array_search($name, $fields) === false) {
        CAFEVDB_Util::error('Extra-Field '.$field['pos'].' not Found in Table Besetzungen');
      }
    }

    CAFEVDB_Util::debugMsg("<<<< ProjektCreateExtraFelder");

    return true; // if someone cares
  }

  // Create a sensibly sorted view, fit for being exported via
  // phpmyadmin. Take all extra-fields into account, add them at end.
  public static function createView($ProjektId, $Projekt = false, $handle = false)
  {
    CAFEVDB_Util::debugMsg(">>>> ProjektCreateView");

    if (! $Projekt) {
      // Get the name
      $Projekt = ProjektFetchName($ProjektId, $handle);
    }

    // Make sure all extra-fields exist
    self::createExtraFields($ProjektId, $handle);

    // Fetch the extra-fields
    $extra = self::extraFields($ProjektId, $handle);

    // "Extra"'s will be added at end. Generate a suitable "SELECT"
    // string for that. Ordering of field in the table is just the
    // ordering in the "$extra" table.
    $extraquery = '';
    CAFEVDB_Util::debugMsg(">>>> ProjektCreateView before extra");
    foreach ($extra as $field) {
      $extraquery .= sprintf(', `Besetzungen`.`ExtraFeld%02d` AS `'.$field['name'].'`', $field['pos']);
    }
    CAFEVDB_Util::debugMsg(">>>> ProjektCreateView after extra");

    // Now do all the stuff, do not forget the proper sorting to satisfy
    // all dummies on earth
    $sqlquery = 'CREATE OR REPLACE VIEW `'.$Projekt.'View` AS
 SELECT
   `Musiker`.`Id` AS `MusikerId`,
   `Besetzungen`.`Instrument`,`Besetzungen`.`Reihung`,
   `Besetzungen`.`Stimmführer`,`Instrumente`.`Familie`,`Instrumente`.`Sortierung`,
    `Name`,`Vorname`,
   `Email`,`Telefon`,`Telefon2`,
   `Strasse`,`Postleitzahl`,`Stadt`,`Land`,
   `Besetzungen`.`Unkostenbeitrag`,
   `Besetzungen`.`Bemerkungen` AS `ProjektBemerkungen`'.
      ($extraquery != '' ? $extraquery : '').','
      .' `Instrumente` AS `AlleInstrumente`,`Sprachpräferenz`,`Geburtstag`,
   `Status`,`Musiker`.`Bemerkung`,`Aktualisiert`';

    // Now do the join
    $sqlquery .= ' FROM `Musiker`
   JOIN `Besetzungen`
     ON `Musiker`.`Id` = MusikerId AND '.$ProjektId.'= `ProjektId`
   LEFT JOIN `Instrumente`
     ON `Besetzungen`.`Instrument` = `Instrumente`.`Instrument`';

    // And finally force a sensible default sorting:
    // 1: sort on the natural orchestral ordering defined in Instrumente
    // 2: sort (reverse) on the Stimmfuehrer attribute
    // 3: sort on the sur-name
    // 4: sort on the pre-name
    $sqlquery .= 'ORDER BY `Instrumente`.`Sortierung` ASC,
 `Besetzungen`.`Reihung` ASC,
 `Besetzungen`.`Stimmführer` DESC,
 `Musiker`.`Name` ASC,
 `Musiker`.`Vorname` ASC';
 
    CAFEVDB_mySQL::query($sqlquery, $handle);

    return true;
  }

}; // class CAFEVDB_Projects

?>
