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

  function __construct() {
    parent::__construct();
  }

  public function headerText()
  {
    $header =<<<__EOT__
    <h3>Besetzungszahlen $this->project</h3>
    <H4>Die "Soll"-Besetzungzahl kann hier eingetragen werden, die
"Haben"-Besetzungszahl ist die Anzahl der bereits registrierten Musiker.</H4>

__EOT__;

    return $header;
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

    // Set default prefixes for variables
    $opts['js']['prefix']               = 'PME_js_';
    $opts['dhtml']['prefix']            = 'PME_dhtml_';
    $opts['cgi']['prefix']['operation'] = 'PME_op_';
    $opts['cgi']['prefix']['sys']       = 'PME_sys_';
    $opts['cgi']['prefix']['data']      = 'PME_data_';

    // Note that the value of $transposed is "dual" to the actual
    // button state, so the default valu is "Normal" in order to start
    // in transposed mode.
    $transposed = Util::cgiValue('Transpose','Transposed');
    if (Util::cgiValue('PME_sys_transpose',false) !== false) {
      $transposed = $transposed == 'Normal' ? 'Transposed' : 'Normal';
    }

    // Don't want everything persistent.
    $opts['cgi']['persist'] = array(
      'Project' => $project,
      'ProjectId' => $projectId,
      'Template' => 'project-instruments',
      'Transpose' => $transposed,
      'Table' => $opts['tb'],
      'headervisibility' => Util::cgiValue('headervisibility', 'expanded'));

    // Name of field which is the unique key
    $opts['key'] = 'ProjektId';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    if ($projectId >= 0) {
      unset($opts['sort_field']);
      $opts['options'] = 'CPVDI';
      $opts['navigation'] = 'GUD';
      $sort = false;
    } else {
      // Sorting field(s)
      $opts['sort_field'] = array('ProjektId');

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACVDF';
      $sort = true;
    }

    $transpose = array('name' => 'transpose',
                       'value' => strval(L::t('Transpose')),
                       'css' => 'pme-transpose',
                       'js_validation' =>  false,
                       'disabled' => false,
                       'js' => false);
    $opts['buttons'] = Navigation::prependTableButton($transpose);

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = array(
                             'form'  => true,
                             'query' => true,
                             'sort'  => $sort,
                             'time'  => true,
                             'tabs'  => true
                             );

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

    if ($projectId >= 0) {
      $opts['filters'] = "`ProjektId` = ".$projectId;
      // Also restrict the instruments to the instruments which are required for this project.
      $handle = mySQL::connect($opts);
      $projectInstruments = Instruments::fetchProjectInstruments($projectId, $handle);
      mySQL::close($handle);
      if (count($projectInstruments) == 0) {
        // TODO: figure out how OwnCloud likes to display error messages.
        echo '<H4><span style="color:red">Keine Besetzung f&uuml;r das Projekt gefunden,
bitte bei den <A HREF="Projekte.php?PME_sys_rec='.$projectId.'&PME_sys_operation=PME_op_Change">Projekteigenschaften</A> die Instrumente eintragen, oder mit dem Button oben die Instrumente der bereits "registrierten" Musiker automatisch eintragen lassen.</span></H4>';
      }
    } else {
      $projectInstruments = $instruments;
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

    $idIdx = 0;
    $opts['fdd']['ProjektId'] = array(
      'name'     => 'ProjektId',
      'select'   => 'T',
      'options'  => 'AVCPD', // auto increment
      'maxlen'   => 11,
      'default'  => '0',
      'sort'     => true,
      'values' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name'
        //'filters' => "Id = $projectId"
        )
      );
    
    
    $opts['fdd']['ProjektName'] = array(
      'name'     => 'Projekt-Name',
      'options'  => 'LVR',
      'select'   => 'T',
      'sql'      => 'ProjektId',
      'php|VLF'  => array('type' => 'function',
                          'function' => 'CAFEVDB\Projects::projectButton',
                          'parameters' => array('keyIdx' => $idIdx.'_idx',
                                                'template' => 'brief-instrumentation')),
      'escape'   => false,
      'maxlen'   => 11,
      'sort'     => $sort,
      'values' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'join' => '$main_table.ProjektId = $join_table.Id'
        //'filters' => "Id = $projectId"
        )
      );

    $cnt = 1;
    foreach ($projectInstruments as $value) {
      $opts['fdd']["$value"] = array('name' => $value.' (soll)',
                                     'select' => 'T',
                                     'maxlen' => 4,
                                     'sort' => $sort);
      $opts['fdd']["$value"."Haben"] = array('name' => $value.' (haben)',
                                             'colattrs' => 'style="color:#990000;background-color:#F0F0F0"',
                                             'select' => 'T',
                                             'options' => 'LAVCPDR',
                                             'input' => 'V',
                                             'maxlen' => 4,
                                             'sql' => '(SELECT COUNT(*) FROM `Besetzungen`
   WHERE `PMEtable0`.`ProjektId` = `Besetzungen`.`ProjektId`
         AND
         \''.$value.'\' = `Besetzungen`.`Instrument`)',
                                             'sort' => false);
      // TODO: move the alternating colors to the style sheet. Also:
      // in principle one would like to have a transposed table ...
      if ($cnt++ % 2 == 0) {
        $opts['fdd']["$value"]['colattrs'] = 'style="background-color:#87CEFA"';
        $opts['fdd']["$value"."Haben"]['colattrs'] = 'style="color:#990000;background-color:#7fC2eb"';
      } else {
        $opts['fdd']["$value"]['colattrs'] = 'style="background-color:#FFFFFF"';
        $opts['fdd']["$value"."Haben"]['colattrs'] = 'style="color:#990000;background-color:#F0F0F0"';
      }
    }

    $pme = new \phpMyEdit($opts);

    if ($transposed == 'Transposed' && $pme->list_operation()) {
      $doTranspose = 'true';
      $pageitems = strval(L::t('#columns'));
    } else {
      $doTranspose = 'false';
      $pageitems = strval(L::t('#rows'));
    }
    echo <<<__EOT__
<script type="text/javascript">
$(function() {
    if ($doTranspose) {
      $('.tipsy').remove();
      transposePmeMain('table.pme-main');
    }
    $('input.pme-pagerows').val('$pageitems');

    $('input').tipsy({gravity:'w', fade:true});
    $('button').tipsy({gravity:'w', fade:true});
    $('input.cafevdb-control').tipsy({gravity:'nw', fade:true});
    $('#controls button').tipsy({gravity:'nw', fade:true});
    $('.pme-sort').tipsy({gravity: 'n', fade:true});
    $('.pme-email-check').tipsy({gravity: 'nw', fade:true});
    $('.pme-bulkcommit-check').tipsy({gravity: 'nw', fade:true});

    if (CAFEVDB.toolTips) {
      $.fn.tipsy.enable();
    } else {
      $.fn.tipsy.disable();
    }
});
</script>
__EOT__;

  }

}; // class InstrumentationInstruments

}

?>