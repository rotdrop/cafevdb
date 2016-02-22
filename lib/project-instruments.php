<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**Display the instruments used or required by a project.
 */
class ProjectInstruments
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  const TABLE_NAME = 'ProjectInstrumentation';
  private $postProjectId;

  function __construct($execute = true) {
    parent::__construct($execute);
  }

  public function shortTitle()
  {
    if ($this->projectName) {
      return L::t("Instrumentation Numbers for `%s'", array($this->projectName));
    } else {
      return L::t("Instrumentation Numbers");
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    $header .= "<p>".L::t("The target instrumentation numbers can be filled into this table. ".
                          "The `have'-numbers are the numbers of the musicians ".
                          "already registered for the project.".
                          "In order to transfer the instruments of the already registerd musicions ".
                          "into this table click the `Adjust Instrument' option from the `Actions' menu.");

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  function display()
  {
    global $debug_query;
    $debug_query = Util::debugMode('query');

    if (Util::debugMode('request')) {
      echo '<PRE>';
      /* print_r($_SERVER); */
      print_r($_POST);
      print $this->projectId;
      echo '</PRE>';
    }

    $template       = $this->template;
    $projectName    = $this->projectName;
    $projectId      = $this->projectId;
    $recordsPerPage = $this->recordsPerPage;
    $projectMode    = $projectId > 0;
    $opts           = $this->opts;

    $opts['css']['postfix'] = 'direct-change';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE_NAME;

    // Don't want everything persistent.
    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'Template' => 'project-instruments',
      'Table' => $opts['tb'],
      'DisplayClass' => 'ProjectInstruments',
      'ClassArguments' => [],
      );

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    if ($projectMode) {
      $opts['options'] = 'ACDF';
      $sort = false;
    } else {
      $opts['options'] = 'ACDF';
      $sort = true;
    }

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '16';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';
    $opts['navigation'] = 'GUM';

    // Display special page elements
    $opts['display'] = array_merge(
      $opts['display'],
      array(
        'form'  => true,
        //'query' => true,
        'sort'  => $sort,
        'time'  => true,
        'tabs'  => false,
        'navigation' => 'CD',
        ));

    if ($projectMode) {
      $adjustButton = array(
        'name' => 'transfer_instruments',
        'value' => L::t('Transfer Instruments'),
        'css' => 'transfer-registered-instruments'
        );
      $opts['buttons'] = Navigation::prependTableButton($adjustButton, false, false);
    }

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

    $opts['fdd']['Id'] = array(
      'name'     => 'Id',
      'input'    => 'HRO',
      'select'   => 'T',
      'maxlen'   => 11,
      'default'  => 0, // 0 normally triggers auto-increment.
      'sort'     => $sort,
      );

    $projectIdx = count($opts['fdd']);
    $opts['fdd']['ProjectId'] = array(
      'name'      => L::t('Project-Name'),
      'input'     => ($projectMode ? 'HR' : ''),
      'css' => array('postfix' => ' project-instrument-project-name'),
//      'options'   => ($projectMode ? 'VCDAPR' : 'FLVCDAP'),
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : -1),
      'sort'     => $sort,
      'values|ACP' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'groups' => 'Jahr',
        'orderby' => '$table.`Jahr` DESC',
        'join' => '$main_table.ProjectId = $join_table.Id',
        ),
      'values|DVFL' => array(
        'table' => 'Projekte',
        'column' => 'Id',
        'description' => 'Name',
        'groups' => 'Jahr',
        'orderby' => '$table.`Jahr` DESC',
        'join' => '$main_table.`ProjectId` = $join_table.`Id`',
        'filters' => '$table.`Id` IN (SELECT `ProjectId` FROM $main_table)',
        ),
      );

    $opts['fdd']['ProjectYear'] = array(
      'name'  => L::T('Project Year'),
      'input' => 'VHR',
      'sql'   => 'PMEjoin'.$projectIdx.'.Jahr',
      );

    $instIdx = count($opts['fdd']);
    $opts['fdd']['InstrumentJoin'] = array(
      'name'  => L::t('Instrumets Joint Pseudo Field'),
      'sql'   => 'PMEjoin'.$instIdx.'.Id',
      'input' => 'VRH',
      'values' => array(
        'table'       => 'Instrumente',
        'column'      => 'Id',
        'description' => ['columns' => 'Id'],
        'join'        => '$join_table.Id = $main_table.InstrumentId',
        ),
      );

    $opts['fdd']['Sortierung'] = array(
      'name'  => L::t('Orchestral Sorting'),
      'input' => 'VHR',
      'sql'   => 'PMEjoin'.$instIdx.'.Sortierung',
      );

    $procInstIdx = count($opts['fdd']);
    $opts['fdd']['InstrumentId'] = array(
      'name'        => L::t('Instrument'),
      'input|CP'    => 'R',
      'select'      => 'D',
      'sort'        => $sort,
      'values|A' => [
        'table' => 'Instrumente',
        'column' => 'Id',
        'description' => 'Instrument',
        'orderby' => '$table.Sortierung',
        'filters' => "NOT \$table.Id
 IN
 (SELECT InstrumentId FROM \$main_table WHERE ProjectId = $projectId)",
        ],
      'values2|VCPDLF'     => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
      );

    // required quantity
    $opts['fdd']['Quantity'] = array(
      'name' => L::t('Required'),
      'select' => 'N',
      'css'    => [ 'postfix' => ' instrumentation-required' ],
      'sort' => $sort,
      'align' => 'right',
      );

    // quantity registered
    $haveIdx = count($opts['fdd']);
    $opts['fdd']['RegisteredJoin'] = array(
      'name'   => L::t('Registered'),
      'input'  => 'VHR',
      'sql'    => 'COUNT(PMEjoin'.$haveIdx.'.Id)',
      'values' => array(
        'table'       => 'ProjectInstruments',
        'column'      => 'Id',
        'description' => ['columns' => 'Id'],
        'join'        => '$join_table.ProjectId = $main_table.ProjectId
AND
$join_table.InstrumentId = $main_table.InstrumentId',
        ),
      );

    $registeredIdx = count($opts['fdd']);
    $opts['fdd']['Registered'] = array(
      'name'   => L::t('Registered'),
      'input'  => 'VR',
      'sort'   => $sort,
      'select' => 'N',
      'sql'    => 'COUNT(PMEjoin'.$haveIdx.'.Id)',
      'css'    => [ 'postfix' => ' instrumentation-registered' ],
      'align'  => 'right',
      );

    $opts['fdd']['Missing'] = array(
      'name'   => L::t('Balance'),
      'input'  => 'VR',
      'sort'   => $sort,
      'select' => 'N',
      'sql'    => 'COUNT(PMEjoin'.$haveIdx.'.Id) - PMEtable0.Quantity',
      'css'    => [ 'postfix' => ' signed-number instrumentation-balance' ],
      'align'  => 'right',
      );

    if ($projectMode) {
      $opts['filters']['AND'][] = "PMEtable0.ProjectId = $projectId";
    }

    // Sorting field(s)
    $opts['sort_field'] = [
      '-ProjectYear',
      'ProjectId',
      'Sortierung',
      ];

    $opts['groupby_fields'] = [ 'ProjectId', 'InstrumentId' ];

    $opts['execute'] = $this->execute;

    $pme = new \phpMyEdit($opts);
  }

}; // class InstrumentationInstruments

}

?>
