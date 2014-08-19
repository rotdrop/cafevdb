<?php

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Display the instruments used or required by a project.
 */
class ProjectInstruments
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  private $postProjectId;

  function __construct($recordId = -1) {
    parent::__construct();
    // if set, the recordId is the projectId
    
    if (false) {
      echo "<PRE>\n";
      echo $recordId;
      echo "</PRE>\n";
    }

    if ($recordId > 0 && empty($this->cancelSave)) {
      $this->projectId = $recordId;
    }

    $this->postProjectId = Util::cgiValue('ProjectId', -1) > 0;
  }

  public function headerText()
  {
    if ($this->project) {
      $header = L::t("Instrumentation Numbers for `%s'",
                     array($this->project));
    } else {
      $header = L::t("Instrumentation Numbers");
    }
    $header .= "<p>".L::t("The target instrumentation numbers can be filled into this table. The `have'-numbers are the numbers of the musicians already registered for the project.");

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  public function transferInstruments()
  {
    // Slight abuse, but much of the old phpMyEdit stuff relies on
    // simple submit behaviour. So do the check here.
    $xferStatus = false;
    if (Util::cgiValue('Action','') == 'transfer-instruments') {
      // Transfer them ...
      $replace = false;
      $handle = mySQL::connect(Config::$pmeopts);
      Instruments::updateProjectInstrumentationFromMusicians(
        $this->projectId, $handle, $replace);
      mySQL::close($handle);
      $xferStatus = true;
    }
    return $xferStatus;
  }

  function display()
  {
    global $debug_query;
    //Config::$debug_query = true;
    //$debug_query = true;

    if (false) {
      echo '<PRE>';
      /* print_r($_SERVER); */
      print_r($_POST);
      print $this->projectId;
      echo '</PRE>';
    }

    $template        = $this->template;
    $project         = $this->project;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
    $opts            = $this->opts;
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

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = 'BesetzungsZahlen';

    // Get the desired transposed state.
    $transposed = Util::cgiValue('Transpose', 'transposed');
    // Don't want everything persistent.
    $opts['cgi']['persist'] = array(
      'Template' => 'project-instruments',
      'Transpose' => $transposed,
      'InhibitInitialTranspose' => $this->projectId >= 0 ? 'true' : 'false',
      'Table' => $opts['tb'],
      'headervisibility' => Util::cgiValue('headervisibility', 'expanded'));

    if ($this->postProjectId) {
      $opts['cgi']['persist']['Project'] = $project;
      $opts['cgi']['persist']['ProjectId'] = $projectId;
    }

    // Name of field which is the unique key
    $opts['key'] = 'ProjektId';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    if ($projectId > 0) {
      unset($opts['sort_field']);
      $opts['options'] = 'CVDI';
      $opts['navigation'] = 'GUD';
      $sort = false;
    } else {
      // Sorting field(s)
      $opts['sort_field'] = array('ProjektId');

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACVDF';
      $sort = true; // but only for the project!!!
    }
    
    /* When we got here with PME_sys_morechange, moreadd, operation
     * and if we have $projectId, then we stay on this page. The idea
     * is then to repeat the form submit (which will do no harm, as
     * the valus did not change) after possibly adjusting the
     * instrumenation from the actual registered musicians.
     */
    $adjustOperation = false;
    if ($projectId > 0) {
      $operations = Util::cgiKeySearch('/'.Config::$pmeopts['cgi']['prefix']['sys'].'(more|operation)/');
      if (count($operations) == 1) {
        foreach ($operations as $key => $value) {
          $adjustOperation = $key."=".str_replace("?", "&", $value);
        }
        $adjustOperation .= "&".Config::$pmeopts['cgi']['prefix']['sys']."rec=".$projectId;
      } else if ($this->postProjectId) {
        // Explicitly called for given project
        $adjustOperation =
          Config::$pmeopts['cgi']['prefix']['sys']."rec=".$projectId;
      }
    }

    $actions = self::projectInstrumentsActions('pme-'.$transposed, $adjustOperation);
    $opts['buttons'] = Navigation::prependTableButton($actions, false, true);

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '16';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = array_merge($opts['display'],
                                   array(
                                     'form'  => true,
                                     'query' => true,
                                     'sort'  => $sort,
                                     'time'  => true,
                                     'tabs'  => true
                                     ));

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

    $handle = mySQL::connect($opts);
    if ($projectId > 0) {
      $opts['filters'] = "`ProjektId` = ".$projectId;

      // Also restrict the instruments to the instruments which are required for this project.
      $projectInstruments = Instruments::fetchProjectInstruments($projectId, $handle);
      if (count($projectInstruments) == 0) {

        Util::alert(L::t('Keine Besetzung für das Projekt gefunden, bitte bei den <A
HREF=%s>Projekteigenschaften</A> die Instrumente eintragen, oder im
<u>%s-Menü den ``%s\'\'-Eintrag</u> auswählen, um die Instrumente der bereits
``registrierten\'\' Musiker automatisch eintragen lassen.',
                         array('"?app=cafevdb&Template=projects&PME_sys_rec='.$projectId.'&PME_sys_operation=PME_op_Change"',
                               L::t('Actions'),
                               L::t('Adjust Instruments'))
                      ),
                    L::t("Instrumentation-Numbers not Found"), self::CSS_PREFIX);

      }
      
      // Check whether there are instrumentation numbers, simply create it if non-existant
      if ($this->postProjectId &&
          mySQL::queryNumRows("FROM ".$opts['tb']." WHERE `ProjektId` = $projectId", $handle) == 0) {
        // Make sure the instrumentation numbers exist
        $query = 'INSERT IGNORE INTO `BesetzungsZahlen` (`ProjektId`) VALUES ('.$projectId.')';
        mySQL::query($query, $handle);
      }

    } else {
      $projectInstruments = $instruments;

      // Check whether it makes sense to enable the "add" button ...

      $missingQuery = ''.
        'FROM `Projekte` '.
        '  WHERE NOT `Id` IN (SELECT `ProjektId` FROM `BesetzungsZahlen`)';
      $numMissing = mySQL::queryNumRows($missingQuery, $handle, false, true);
      if ($numMissing == 0) {
        $opts['options'] = str_replace("A", "", $opts['options']);
      }
    }
    mySQL::close($handle);

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
       ['select'] T - text, N - numeric, D - drop-down, M - multiple selection,
                  O - radio buttons, C - check-boxes
       ['options'] optional parameter to control whether a field is displayed
       L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
       Another flags are:
       R - indicates that a field is read only
       0 - indicates that a field is disabled
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

    $idIdx = 0;
    $opts['fdd']['ProjektId'] = array(
      'name'      => L::t('Projekt-Name'),
      'options'   => 'FLVDA', // auto increment
      'select|FLVA' => 'D',
      'select|CD'   => 'T',
      'options|CD'  => 'FLVCDAR',
      'php|LVFCD'   => array('type' => 'function',
                             'function' => 'CAFEVDB\Projects::projectActionsPME',
                             'parameters' => array("idIndex" => $idIdx.'_idx')),
      'maxlen'   => 11,
      'default'  => '-1',
      'sort'     => $sort,
      'values|LFV' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'join' => '$main_table.ProjektId = $join_table.Id',
        'filters' => "`Id` IN (SELECT `ProjektId` FROM \$main_table)",
        ),
      'values|A' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'join' => '$main_table.ProjektId = $join_table.Id',
        'filters' => "NOT `Id` IN (SELECT `ProjektId` FROM \$main_table)",
        ),
      'values|CD' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'join' => '$main_table.ProjektId = $join_table.Id',
        'filters' => "`Id` IN (SELECT `ProjektId` FROM \$main_table WHERE `Id` = \$record_id)",
        ),
      );    

    $cnt = 0;
    foreach ($projectInstruments as $value) {
      $opts['fdd']["$value"] = array(
        'name' => $value.' ('.L::t("target").')',
        'select' => 'T',
        'maxlen' => 4,
        'options' => 'LAVCPD',
        'sort' => false,
        'css' => array('postfix' => 'want-'.($cnt % 2))
        );
      $opts['fdd']["$value"."Haben"] = array(
        'name' => $value.' ('.L::t("have").')',
        'select' => 'T',
        'options' => 'LAVCPDR',
        'input' => 'V',
        'maxlen' => 4,
        'sql' => '(SELECT COUNT(*) FROM `Besetzungen`
   WHERE `PMEtable0`.`ProjektId` = `Besetzungen`.`ProjektId`
         AND
         \''.$value.'\' = `Besetzungen`.`Instrument`)',
        'sort' => false,
        'css' => array('postfix' => 'have-'.($cnt % 2))
        );

      $cnt ++;
    }

    $opts['triggers']['delete']['after'] = 'CAFEVDB\ProjectInstruments::insertDeleteCallback';
    $opts['triggers']['insert']['after'] = 'CAFEVDB\ProjectInstruments::insertDeleteCallback';

    $pme = new \phpMyEdit($opts);
  }

  /**Can be used as a trigger callback (instead of loading one from disk).
   */
  public static function insertDeleteCallback($pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    $missingQuery = ''.
      'FROM `Projekte` '.
      '  WHERE NOT `Id` IN (SELECT `ProjektId` FROM `BesetzungsZahlen`)';
    $numMissing = mySQL::queryNumRows($missingQuery, $pme->dbh, false, true);
    if ($numMissing == 0) {
      $pme->options = str_replace("A", "", $pme->options);
    }
    
    return true;        
  }

  /**Another multi-select which adds a pull-down menu to the
   * project-instrumentation table.
   */
  public static function projectInstrumentsActions($transposed, $adjustOperation = false)
  {
    $data = ''
      .'<span id="pme-instrumentation-actions-block" class="pme-instrumentation-actions-block">
  <label>
    <select 
      data-placeholder="'.L::t('Actions').'"
      class="pme-instrumentation-actions-choice"
      id="pme-instrumentation-actions-choice"
      title="'.Config::toolTips('pme-instrumentation-actions').'"
      name="actions" >
      <option value=""></option>
      <option
        title="'.Config::toolTips('pme-transpose').'"
        id="pme-transpose"
        class="pme-transpose '.$transposed.'"
        value="transpose" >
        '.L::t('Transpose').'
      </option>
      <option '.($adjustOperation === false ? 'disabled ' : '').'
        title="'.Config::toolTips('transfer-instruments').'"
        id="pme-transfer-instruments"
        class="pme-transfer-instruments"
        value="transfer-instruments?'.$adjustOperation.'" >
        '.L::t('Adjust Instruments').'
      </option>
    </select>
  </label>
</span>';

    $button = array('code' => $data);

    return $button;
  }

}; // class InstrumentationInstruments

}

?>
