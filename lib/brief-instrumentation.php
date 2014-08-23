<?php

/**Orchestra member, musician and project management application.
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

/**Display a brief list of registered musicians for each project.
 */
class BriefInstrumentation
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';

  function __construct($execute = true) {
    parent::__construct($execute);
  }

  public function headerText()
  {
    $header = L::t("Instrumentation for Project `%s'", array($this->project)).'
    <p>
      <ul>
        <li><span style="color:red">'.L::t("Add Musicians").'</span>
          <span style="font-style:italic">'.L::t("see above").'</span>
        <li><span style="color:red">'.L::t("Remove Musicians").'</span>
            <span style="font-style:italic">'.L::t("this table").'</span>
            ("x"-Button)
        <li><span style="color:red">'.L::t("Per-Musician Project Data").'</span>
          <span style="font-style:italic">'.L::t("this table").'</span>
          '.L::t("(project instrument, section leader, remarks etc.)").'
        <li><span style="color:red">'.L::t("General Personal Data").'</span>
          <span style="font-style:italic">'.L::t("Detailed Instrumentation").'</span>
          '.L::t("(see above; address, email, name, picture etc.)").'
      </ul>';

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  function display()
  {
    global $debug_query;
    //Config::$debug_query = true;
    //$debug_query = true;

    $project         = $this->project;
    $projectId       = $this->projectId;
    $opts            = $this->opts;
    $recordsPerPage  = $this->recordsPerPage;
    $userExtraFields = $this->userExtraFields;

    if (false) {
      echo '<PRE>';
      print_r($_POST);
      echo '</PRE>';
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

    $opts['tb'] = 'Besetzungen';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Don't want everything persistent.
    $opts['cgi']['persist'] = array(
      'Project' => $project,
      'ProjectId' => $projectId,
      'Template' => 'brief-instrumentation',
      'Table' => $opts['tb'],
      'RecordsPerPage' => $recordsPerPage,
      'headervisibility' => Util::cgiValue('headervisibility','expanded'));

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Sortierung','Reihung','-Stimmführer','MusikerId');

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    $export = Navigation::tableExportButton();
    $opts['buttons'] = Navigation::prependTableButton($export, true);

    // Display special page elements
    $opts['display'] = array_merge($opts['display'],
                                   array(
                                     'form'  => true,
                                     'query' => true,
                                     'sort'  => true,
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

    $opts['filters'] = "ProjektId = $projectId";

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
                                      'options'  => 'AVCPDR', // auto increment
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
                                      //'options'  => 'LFADV', // no change allowed
                                      'values' => array('table' => 'Musiker',
                                                        'column' => 'Id',
                                                        'description' => array('columns' => array('Name', 'Vorname'),
                                                                               'divs' => array(', ')
                                                                               ))
                                      );
    $opts['fdd']['Anmeldung'] = $this->registrationColumn;

    $opts['fdd']['Instrument'] = array(
      'name'     => 'Instrument',
      'select'   => 'D',
      'maxlen'   => 36,
      'css'      => array('postfix' => 'instruments'),
      'sort'     => true,
      'values' => array(
        'table'   => 'Instrumente',
        'column'  => 'Instrument',
        'orderby' => '$table.Sortierung',
        'description' => array('columns' => array('Instrument')),
        ),
      'values|LF' => array(
        'table'   => 'Instrumente',
        'column'  => 'Instrument',
        'orderby' => '$table.Sortierung',
        'description' => array('columns' => array('Instrument')),
        'filters' => ("`Instrument` IN ".
                      "(SELECT `Instrument` FROM \$main_table WHERE `ProjektId` = $projectId)"),
        ),
      'valueGroups' => $this->groupedInstruments,
      );
    $opts['fdd']['Sortierung'] = array('name' => 'Orchester-Sortierung',
                                       'select' => 'T',
                                       'options' => 'VCPR',
                                       'input' => 'V',
                                       'sql' => '`PMEjoin4`.`Sortierung`', // this is `Instrumente`
                                       'sort' => true);
    $opts['fdd']['Reihung'] = array('name' => 'Stimme',
                                    'select' => 'N',
                                    'maxlen' => '1',
                                    'sort' => true);
    $opts['fdd']['Stimmführer'] = $this->sectionLeaderColumn;
    $opts['fdd']['Bemerkungen'] = array('name'     => 'Bemerkungen',
                                        'select'   => 'T',
                                        'maxlen'   => 65535,
                                        'css'      => array('postfix' => 'remarks'),
                                        'textarea' => array('css' => Config::$opts['editor'],
                                                            'rows' => 5,
                                                            'cols' => 50),
                                        'escape' => false,
                                        'sort'   => true);
    $opts['fdd']['Unkostenbeitrag'] = Config::$opts['money'];
    $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";

    // One virtual field in order to be able to manage SEPA debit mandas
    $opts['fdd']['SepaDebitMandate'] = array(
      'input' => 'V',
      'name' => L::t('SEPA Debit Mandate'),
      'select' => 'T',
      'options' => 'LACPDV',
      'sql' => '`PMEjoin'.count($opts['fdd']).'`.`mandateReference`', // dummy, make the SQL data base happy
      'sqlw' => '`PMEjoin'.count($opts['fdd']).'`.`mandateReference`', // dummy, make the SQL data base happy
      'values' => array(
        'table' => 'SepaDebitMandates',
        'column' => 'id',
        'join' => '$join_table.projectId = $main_table.ProjektId AND $join_table.musicianId = $main_table.MusikerId',
        'description' => 'mandateReference'
        ),
      'nowrap' => true,
      'sort' => true,
      'php' => array(
        'type' => 'function',
        'function' => 'CAFEVDB\BriefInstrumentation::sepaDebitMandatePME',
        'parameters' => array('project' => $project,
                              'projectId' => $projectId)
        )
      );

    // Generate input fields for the extra columns
    foreach ($userExtraFields as $field) {
      $name = sprintf('ExtraFeld%02d', $field['pos']);
    
      $opts['fdd']["$name"] = array('name' => $field['name'],
                                    'select'   => 'T',
                                    'maxlen'   => 65535,
                                    'textarea' => array('css' => '',
                                                        'rows' => 2,
                                                        'cols' => 32),
                                    'escape' => false,
                                    'sort'     => true);
      if ($field['tooltip'] !== false) {
        $opts['fdd']["$name"]['tooltip'] = $field['tooltip'];
      }
    }

    // Check whether the instrument is also mentioned in the musicians
    // data-base. Otherwise add id on request.
    $opts['triggers']['insert']['before']  = 'CAFEVDB\Instrumentation::beforeInsertFixProjectTrigger';
    $opts['triggers']['update']['before']  = 'CAFEVDB\Instrumentation::beforeUpdateInstrumentTrigger';

    if ($this->pme_bare) {
      // disable all navigation buttons, probably for html export
      $opts['navigation'] = 'N'; // no navigation
      $opts['options'] = '';
      // Don't display special page elements
      $opts['display'] = array_merge($opts['display'],
                                     array(
                                       'form'  => false,
                                       'query' => false,
                                       'sort'  => false,
                                       'time'  => false,
                                       'tabs'  => false
                                       ));
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }

    $opts['execute'] = $this->execute;

    // Generate and possibly display the table
    $this->pme = new \phpMyEdit($opts);
  }

  public static function sepaDebitMandatePME($referenceId, $opts, $action, $k, $fds, $fdd, $row)
  {
    // Fetch the data from the array $row.
    $projectId = $opts['projectId'];
    $project   = $opts['project'];

    // Careful: this changes when rearranging the ordering of the display
    $musican    = $row['qf2'];
    $musicianId = $row['qf2_idx'];

    if ($row['qf'.$k] != '') {
      $value = $row['qf'.$k];
    } else {
      $value = L::t("SEPA Debit Mandate");
    }

    return self::sepaDebitMandateButton($value, $musicianId, $musician, $projectId, $project);
  }

  /**Generate a clickable form element which finally will display the
   * debit-mandate dialog, i.e. load some template stuff by means of
   * some java-script and ajax blah.
   */
  public static function sepaDebitMandateButton($value, $musicianId, $musician, $projectId, $project)
  {
    $button = '<div class="sepa-debit-mandate">'
      .'<input type="button" '
      .'       value="'.$value.'" '
      .'       title="'.L::t("Click to enter details of a potential SEPA debit mandate").' " '
      .'       name="'
      .'MusicianId='.$musicianId.'&amp;'
      .'MusicianName='.$musician.'&amp;'
      .'ProjectId='.$projectId.'&amp;'
      .'ProjectName='.$project.'" '
      .'       class="sepa-debit-mandate" />'
      .'</div>';
    return $button;
  }
};

}

?>
