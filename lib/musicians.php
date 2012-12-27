<?php

namespace CAFEVDB
{

class Musicians
  extends Instrumentation
{
  private $projectMode;

  function __construct($mode = false) {
    parent::__construct();
    $this->projectMode = $mode;
  }

  /**Display the list of all musicians. Is $projectMode == true,
   * filter out all musicians present in $projectId and add a
   * hyperlink which will add the Musician to the respective project.
   */
  public function display()
  {
    global $debug_query;
    //Config::$debug_query = true;
    //$debug_query = true;

    $template        = $this->template;
    $project         = $this->project;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->opts;

    if (!$this->projectMode) {

      echo <<<__EOT__
<div class="cafevdb-pme-header-box">
  <div class="cafevdb-pme-header">
    <h2>&Uuml;berblick &uuml;ber alle Musiker</h2>
  </div>
</div>

__EOT__;

    } else {
      $help = Config::$prefix . 'hinzufuegen.html';

      echo <<<__EOT__
<div class="cafevdb-pme-header-box">
  <div class="cafevdb-pme-header">
    <h3>Besetzung &auml;ndern f&uuml;r Projekt $project</h3>
    <h4>F&uuml;r die aktuelle Teilnehmerliste bitte die Buttons "... Display for ... "
      benutzen.<P> Der Weg in ein Projekt f&uuml;hrt nur &uuml;ber
      <EM style="color:#ff0000"><B>diese</B></EM> Tabelle, die Musiker werden dann automatisch in unseren
      Fundus mit aufgenommen (<A HREF="$help" target="_blank">Anleitung</A>)
    </h4>
  </div>
</div>

__EOT__;
    }

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

    $opts['tb'] = 'Musiker';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['cgi']['persist'] = array('Project' => $project,
                                    'ProjectId' => $projectId,
                                    'Template' => $this->projectMode
                                    ? 'add-musicians' : 'all-musicians',
                                    'Table' => $opts['tb']);

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Instrumente','Name','Vorname','Id');

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '5';

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

    if ($this->projectMode) {
       $opts['filters'] = "(SELECT COUNT(*) FROM `Besetzungen` WHERE MusikerId = PMEtable0.Id AND ProjektId = $projectId) = 0";
    }

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

    $bval = strval(L::t('Add to'));
    $tip  = strval(L::t(Config::toolTips('register-musician')));
    if ($this->projectMode) {
      $opts['fdd']['Hinzufuegen'] = array(
        'name' => 'Hinzuf&uuml;gen',
        'select' => 'T',
        'options' => 'VLR',
        'input' => 'V',
        'sql' => "REPLACE('"
."<div class=\"register-musician\">"
."<input type=\"button\" "
."value=\"$bval $project\" "
."title=\"$tip\" "
."name=\""
."Template=add-one-musician&amp;"
."Project=$project&amp;"
."ProjectId=$projectId&amp;"
."MusicianId=@@key@@\""
."class=\"register-musician\" />"
."</div>'"
.",'@@key@@',`PMEtable0`.`Id`)",
        'escape' => false,
        'nowrap' => true,
        'sort' =>false
        //'php' => "AddMusician.php"
        );
    }
    $opts['fdd']['Instrumente'] = array(
                                        'name'     => 'Instrumente',
                                        'select'   => 'C',
                                        'maxlen'   => 137,
                                        'sort'     => true
                                        );
    $opts['fdd']['Instrumente']['values'] = $this->instruments;

    $opts['fdd']['Name'] = array(
                                 'name'     => 'Name',
                                 'select'   => 'T',
                                 'maxlen'   => 128,
                                 'sort'     => true
                                 );
    $opts['fdd']['Vorname'] = array(
                                    'name'     => 'Vorname',
                                    'select'   => 'T',
                                    'maxlen'   => 128,
                                    'sort'     => true
                                    );
    $opts['fdd']['Strasse'] = array(
                                    'name'     => 'Strasse',
                                    'select'   => 'T',
                                    'maxlen'   => 128,
                                    'sort'     => true
                                    );
    $opts['fdd']['Postleitzahl'] = array(
                                         'name'     => 'Postleitzahl',
                                         'select'   => 'T',
                                         'maxlen'   => 11,
                                         'sort'     => true
                                         );
    $opts['fdd']['Stadt'] = array(
                                  'name'     => 'Stadt',
                                  'select'   => 'T',
                                  'maxlen'   => 128,
                                  'sort'     => true
                                  );
    $opts['fdd']['Land'] = array('name'     => 'Land',
                                 'select'   => 'T',
                                 'maxlen'   => 128,
                                 'default'  => 'Deutschland',
                                 'sort'     => true);
    $opts['fdd']['Sprachpräferenz'] = array('name'     => 'Spachpräferenz',
                                            'select'   => 'T',
                                            'maxlen'   => 128,
                                            'default'  => 'Deutschland',
                                            'sort'     => true,
                                            'values'   => Config::$opts['languages']);
    $opts['fdd']['Telefon'] = array(
                                    'name'     => 'Telefon',
                                    'select'   => 'T',
                                    'maxlen'   => 128,
                                    'sort'     => true
                                    );
    $opts['fdd']['Telefon2'] = array(
                                     'name'     => 'Telefon2',
                                     'select'   => 'T',
                                     'maxlen'   => 128,
                                     'sort'     => true
                                     );
    $opts['fdd']['Geburtstag'] = Config::$opts['geburtstag'];
    $opts['fdd']['Email'] = Config::$opts['email'];
    $opts['fdd']['Status'] = array(
                                   'name'     => 'Status',
                                   'css'      => array('postfix' => 'rem'),
                                   'select'   => 'T',
                                   'maxlen'   => 128,
                                   'sort'     => true
                                   );
    $opts['fdd']['Bemerkung'] = array(
                                      'name'     => 'Bemerkung',
                                      'select'   => 'T',
                                      'maxlen'   => 65535,
                                      'css'      => array('postfix' => 'rem'),
                                      'textarea' => array('html' => 'Editor',
                                                          'rows' => 5,
                                                          'cols' => 50),
                                      'escape' => false,
                                      'sort'     => true
                                      );

  $derivedtable =<<<__EOT__
SELECT MusikerId,GROUP_CONCAT(DISTINCT Projekte.Name ORDER BY Projekte.Name ASC SEPARATOR ', ') AS Projekte FROM
Besetzungen
LEFT JOIN Projekte ON Projekte.Id = Besetzungen.ProjektId
GROUP BY MusikerId
__EOT__;

  $opts['fdd']['Projekte'] =
    array('input' => 'VR', // virtual, read perm
          'options' => 'LFV', //just do the join, don't display anything
          'select' => 'T',
          'name' => 'Projekte',
          'sort' => true,
          'sql' => 'PMEjoin'.count($opts['fdd']).'.Projekte',
          'sqlw' => 'PMEjoin'.count($opts['fdd']).'.Projekte',
          'css'      => array('postfix' => 'prjs'),
          'values' => array( //API for currently making a join in PME.
                            'table' =>
                            array('sql' => $derivedtable,
                                  'kind' => 'derived'),
                            'column' => 'MusikerId',
                            'description' => 'Projekte',
                            'join' => '$main_table.Id = $join_table.MusikerId'
                             )
          );

    $opts['fdd']['Id'] = array(
                               'name'     => 'Id',
                               'select'   => 'T',
                               'options'  => 'AVCPDR', // auto increment
                               'maxlen'   => 5,
                               'align'    => 'right',
                               'default'  => '0',
                               'sort'     => true
                               );

    $opts['fdd']['Aktualisiert'] = Config::$opts['calendar'];
    $opts['fdd']['Aktualisiert']['name'] = 'Aktualisiert';
    $opts['fdd']['Aktualisiert']['default'] = date('Y-m-d H:i:s');
    $opts['fdd']['Aktualisiert']['nowrap'] = true;
    $opts['fdd']['Aktualisiert']['options'] = 'LFAVCPDR'; // Set by update trigger.

    $opts['triggers']['update']['before'][0]  = Config::$triggers.'remove-unchanged.TUB.inc.php';
    $opts['triggers']['update']['before'][1]  = Config::$triggers.'update-musician-timestamp.TUB.inc.php';

    new \phpMyEdit($opts);
  } // display()
  
  /**Helper function to add or change one specific musician to an
   * existing project. $this->template determines what to do.
   */
  public function displayAddChangeOne() {

    global $debug_query;
    //Config::$debug_query = true;
    //$debug_query = true;

    $opts            = $this->opts;
    $project         = $this->project;
    $projectId       = $this->projectId;
    $musicianId      = $this->musicianId;
    $template        = $this->template;
    $userExtraFields = $this->userExtraFields;
    $recordsPerPage  = $this->recordsPerPage;

    echo <<<__EOT__
<div class="cafevdb-pme-header-box">
  <div class="cafevdb-pme-header">
    <H4>
      Auf dieser Seite wird <B>nur</B> der neue Musiker f&uuml;r das Projekt angezeigt,
      f&uuml;r die komplette List mu&szlig; man den entsprechenden Button bet&auml;tigen.
    </H4>
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

    $opts['tb'] = 'Besetzungen';

    //$opts['execute'] = false; // started by us explicitly after adding the musician

    if (isset($_POST['ForcedInstrument'])) {
      $forcedInstrument = $_POST['ForcedInstrument'];
    } else {
      $forcedInstrument = false;
    }

    $saved_template = $template;
    $template = 'change-one-musician'; // Add only once!

    $opts['cgi']['persist'] = array('Project' => $project,
                                    'ProjectId' => $projectId,
                                    'Table' => $opts['tb'],
                                    'Template' => $template,
                                    'MusicianId' => $musicianId,
                                    'RecordsPerPage' => $recordsPerPage);

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array();

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'DCVF';

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

    $opts['filters'] = "PMEtable0.MusikerId = $musicianId AND PMEtable0.ProjektId = $projectId";

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
                               'options'  => 'AVCPDR', // auto increment
                               'maxlen'   => 11,
                               'default'  => '0',
                               'sort'     => true
                               );
    $opts['fdd']['ProjektId'] = array(
                                      'name'     => 'ProjektId',
                                      'select'   => 'T',
                                      'maxlen'   => 11,
                                      'sort'     => true,
                                      'values' => array(
                                                        'table' => 'Projekte',
                                                        'column' => 'Id',
                                                        'description' => 'Name',
                                                        'filters' => "Id = $projectId"
                                                        )
                                      );
    $opts['fdd']['MusikerId'] = array(
                                      'name'     => 'MusikerId',
                                      'select'   => 'T',
                                      'maxlen'   => 11,
                                      'sort'     => true,
                                      'values' => array(
                                        'table' => 'Musiker',
                                        'column' => 'Id',
                                        'description' => array(
                                          'columns' => array('Vorname', 'Name'),
                                          'divs' => array(' ')
                                          ),
                                        'filters' => "Id = $musicianId"
                                        )
                                      );
    $opts['fdd']['Instrument'] = array(
                                       'name'     => 'Projekt-Instrument',
                                       'select'   => 'T',
                                       'maxlen'   => 12,
                                       'sort'     => true
                                       );
    $opts['fdd']['Instrument']['values'] = $this->instruments;
    $opts['fdd']['Reihung'] = array('name' => 'Stimme',
                                    'select' => 'T',
                                    'maxlen' => '3',
                                    'sort' => true);
    $opts['fdd']['Stimmführer'] = array('name' => ' &alpha;',
                                        'options'  => 'LAVCPD',
                                        'select' => 'T',
                                        'maxlen' => '3',
                                        'sort' => true,
                                        'escape' => false);
    $opts['fdd']['Stimmführer']['values2'] = array('0' => ' ', '1' => '&alpha;');
    $opts['fdd']['Bemerkungen'] = array('name'     => 'Bemerkungen',
                                        'select'   => 'T',
                                        'maxlen'   => 65535,
                                        'textarea' => array('html' => 'Editor',
                                                            'rows' => 5,
                                                            'cols' => 50),
                                        'escape' => false,
                                        'sort'     => true);

    // Generate input fields for the extra columns
    foreach ($userExtraFields as $field) {
      $name = sprintf('ExtraFeld%02d', $field['pos']);
    
      $opts['fdd']["$name"] = array('name' => $field['name'],
                                    'select'   => 'T',
                                    'maxlen'   => 65535,
                                    'textarea' => array('html' => 'NoEditor',
                                                        'rows' => 2,
                                                        'cols' => 32),
                                    'escape' => false,
                                    'sort'     => true);
    }
    // Check whether the instrument is also mentioned in the musicians
    // data-base. Otherwise add id on request.
    $opts['triggers']['update']['before']  = Config::$triggers.'instrumentation-change-instrument.TUB.inc.php';

    if ($saved_template == "add-one-musician") {

      // Fetch all needed data from Musiker table
      $handle = mySQL::connect($opts);

      $musquery = "SELECT `Instrumente` FROM Musiker WHERE `Id` = $musicianId";
      $musres = mySQL::query($musquery, $handle);
      $musnumrows = mysql_num_rows($musres);

      if ($musnumrows != 1) {
        Util::error("Data inconsisteny, $musicianId is not a unique Id");
      }

      $musrow = mySQL::fetch($musres);
      $instruments = explode(',',$musrow['Instrumente']);

      $instquery = "SELECT `Besetzung` FROM `Projekte` WHERE `Id` = $projectId";
      $instres = mySQL::query($instquery, $handle);
      $instnumrows = mysql_num_rows($instres);

      if ($instnumrows != 1) {
        Util::error("Data inconsisteny, $projectId is not a unique Id");
      }

      $instrow = mySQL::fetch($instres);
      $instrumentation = explode(',',$instrow['Besetzung']);

      unset($musinst);
      foreach ($instruments as $value) {
        if (array_search($value, $instrumentation) !== false) {
          // Choose $musinst as instrument
          $musinst = $value;
          break;
        }
      }
      if (!isset($musinst)) {
        // Warn.
        echo
          '<H4>None of the instruments known by the musicions are mentioned in the
<A HREF="Projekte.php?PME_sys_rec='.$projectId.'&PME_sys_operation=PME_op_Change">instrumentation-list</A>
for the project. The musician is added nevertheless to the project with the instrument '.$instruments[0].'.
Please correct the mis-match.</H4>';
        $musinst = $instruments[0];
      } else {
        echo
          '<H4>Choosing the first instrument known to the musician and mentioned in the instrumentation list
of the project. Please correct that by choosing a different "Projekt-Instrument" below, if necessary.
Choosing "'.$musinst.'" as instrument.</H4>';
      }
    

      $prjquery = "INSERT INTO `Besetzungen` (`MusikerId`,`ProjektId`,`Instrument`)
 VALUES ('$musicianId','$projectId','$musinst')";

      mySQL::query($prjquery, $handle);
      mySQL::close($handle);

    } else if ($forcedInstrument != false) {
      // Add to musicans list in Musiker data-base and to musician Besetzungen

      // Fetch all needed data from Musiker table
      $handle = mySQL::connect($opts);

      $musquery = "SELECT `Instrumente` FROM Musiker WHERE `Id` = $musicianId";

      $musres = mySQL::query($musquery, $handle);
      $musnumrows = mysql_num_rows($musres);

      if ($musnumrows != 1) {
        die ("Data inconsisteny, $musicianId is not a unique Id");
      }

      $musrow = mySQL::fetch($musres);
      $instruments = $musrow['Instrumente'] . "," . $forcedInstrument;
    
      $musquery = "UPDATE `Musiker` SET `Instrumente`='$instruments'
 WHERE `Id` = $musicianId";
  
      mySQL::query($musquery, $handle);

      $prjquery = "UPDATE `Besetzungen` SET `Instrument`='$forcedInstrument'
 WHERE `MusikerId` = $musicianId AND `ProjektId` = $projectId";

      mySQL::query($prjquery, $handle);

      mySQL::close();
    }

    echo <<<__EOT__
   </div>
</div>
__EOT__;

    new \phpMyEdit($opts);
  }

}; // class definition.

}

?>
