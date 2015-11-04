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

/**Display the detailed instrumentation for a project.
 */
class DetailedInstrumentation
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  const CSS_CLASS = 'instrumentation';

  function __construct($execute = true) {
    parent::__construct($execute);
    //$this->recordsPerPage = 20;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return L::t('Remove the musician from %s?', array($this->projectName));
    } else if ($this->viewOperation()) {
      return L::t('Display of all stored data for the shown musician.');
    } else if ($this->changeOperation()) {
      return L::t('Edit the data of the displayed musician.');
    }
    return L::t("Instrumentation for Project `%s'", array($this->projectName));
  }

  public function headerText()
  {
    return $this->shortTitle();
  }

  function display()
  {
    global $debug_query;
    $debug_query = Util::debugMode('query');

    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $opts            = $this->opts;
    $recordsPerPage  = $this->recordsPerPage;
    $userExtraFields = $this->userExtraFields;

    $project = Projects::fetchById($projectId);

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

    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = $projectName . 'View';

    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'Template' => 'detailed-instrumentation',
      'Table' => $opts['tb'],
      'DisplayClass' => 'DetailedInstrumentation');

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Sortierung','Reihung','-Stimmführer','Name','Vorname');

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    // This is a view, undeletable.
    $opts['options'] = 'CPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    $export = Navigation::tableExportButton();
    $opts['buttons'] = Navigation::prependTableButton($export, true);

    // Display special page elements
    $opts['display'] = array_merge(
      $opts['display'],
      array(
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs' => array(
          array('id' => 'instrumentation',
                'default' => true,
                'tooltip' => Config::toolTips('project-instrumentation-tab'),
                'name' => L::t('Instrumentation related data')),
          array('id' => 'project',
                'tooltip' => Config::toolTips('project-metadata-tab'),
                'name' => L::t('Project related data')),
          array('id' => 'musician',
                'tooltip' => Config::toolTips('project-personaldata-tab'),
                'name' => L::t('Personal data')),
          array('id' => 'tab-all',
                'tooltip' => Config::toolTips('pme-showall-tab'),
                'name' => L::t('Display all columns'))
          )
        )
      );

    // Set default prefixes for variables
    $opts['js']['prefix']               = 'PME_js_';
    $opts['dhtml']['prefix']            = 'PME_dhtml_';
    $opts['cgi']['prefix']['operation'] = 'PME_op_';
    $opts['cgi']['prefix']['sys']       = 'PME_sys_';
    $opts['cgi']['prefix']['data']      = 'PME_data_';

    //$opts['cgi']['append']['PME_sys_fl'] = 1;

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

    $opts['fdd'] = array();

    $opts['fdd']['Id'] = array(
      'name'     => L::t('Instrumentation Id'),
      'select'   => 'T',
      'options'  => 'AVCPDR', // auto increment
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      'tab'      => array('id' => 'instrumentation')
      );

    $musIdIdx = count($opts['fdd']);
    $opts['fdd']['MusikerId'] = array(
      'name'     => L::t('Musician Id'),
      //'input'    => 'H',
      'select'   => 'T',
      'options'  => 'AVCPDR', // auto increment
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      'tab'      => array('id' => 'musician')
      );

    $musFirstNameIdx = count($opts['fdd']);
    $opts['fdd']['Vorname'] = array(
      'name'     => 'Vorname',
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'tab'      => array('id' => 'tab-all') // display on all tabs, or just give -1
      );

    $musLastNameIdx = count($opts['fdd']);
    $opts['fdd']['Name'] = array(
      'name'     => 'Name',
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'tab'      => array('id' => 'tab-all')
      );

    $opts['fdd']['Instrument'] = array(
      'name'     => 'Instrument',
      'select'   => 'D',
      'maxlen'   => 36,
      'css'      => array('postfix' => ' project-instrument'),
      'sort'     => true,
      'values' => array(
        'table'   => 'Instrumente',
        'column'  => 'Instrument',
        'orderby' => '$table.Sortierung',
        'description' => array('columns' => array('Instrument')),
        /* This rather fancy fillter masks out all instruments
         * currently not registerd with the given musician, but allows
         * for the currently active instrument.
         */
        'filters' => ("FIND_IN_SET(`Instrument`,
  CONCAT_WS(',',(SELECT `Instrument` FROM `\$main_table` WHERE \$record_id = `\$main_table`.`Id`),
                (SELECT `Instrumente` FROM `\$main_table`
                          WHERE \$record_id = `\$main_table`.`Id`)))"),
        ),
      'values|LF' => array(
        'table'   => 'Instrumente',
        'column'  => 'Instrument',
        'orderby' => '$table.Sortierung',
        'description' => array('columns' => array('Instrument')),
        'filters' => ("`Instrument` IN ".
                      "(SELECT `Instrument` FROM `\$main_table` WHERE 1)"),
        ),
      'valueGroups' => $this->groupedInstruments,
      'tab' => array('id' => 'instrumentation')
      );

    $opts['fdd']['Reihung'] = array(
      'name' => 'Stimme',
      'select' => 'N',
      'maxlen' => '3',
      'sort' => true,
      'tab' => array('id' => 'instrumentation'));

    $opts['fdd']['Stimmführer'] = $this->sectionLeaderColumn;
    $opts['fdd']['Stimmführer']['tab'] = array('id' => 'instrumentation');

    $opts['fdd']['Anmeldung'] = $this->registrationColumn;
    $opts['fdd']['Anmeldung']['tab'] = array('id' => 'project');

    $opts['fdd']['Instrumente'] = array(
      'name'     => L::t('All Instruments'),
      'css'      => array('postfix' => ' musician-instruments tooltip-top'),
      'display|LF'  => array('popup' => 'data'),
      //'options'  => 'AVCPD',
      'select'   => 'M',
      'maxlen'   => 136,
      'sort'     => true,
      'values'   => $this->instruments,
      'valueGroups' => $this->groupedInstruments,
      'tab'      => array('id' => array('musician', 'instrumentation'))
      );

    $opts['fdd']['Sortierung'] = array(
      'name'     => 'Orchester Sortierung',
      'select'   => 'T',
      'options'  => 'R',
      'maxlen'   => 8,
      'default'  => '0',
      'sort'     => true
      );
    $opts['fdd']['MemberStatus'] = array(
      'name'     => strval(L::t('Member Status')),
      'tab'      => array('id' => array('musician')), // multiple tabs are legal
      'select'   => 'M',
      'maxlen'   => 384,
      'sort'     => true,
      'values2'  => $this->memberStatusNames
      );

    $opts['fdd']['Unkostenbeitrag'] = Config::$opts['money'];
    $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";
    $opts['fdd']['Unkostenbeitrag']['tab'] = array('id' => 'project');
    $opts['fdd']['Unkostenbeitrag']['default'] = $project['Unkostenbeitrag'];

    if (Projects::needDebitMandates($projectId)) {

      $memberTableId = Config::getValue('memberTableId');
      $debitJoinCondition =
        '('.
        '$join_table.projectId = '.$projectId.
        ' OR '.
        '$join_table.projectId = '.$memberTableId.
        ')'.
        ' AND $join_table.musicianId = $main_table.MusikerId';

      // One virtual field in order to be able to manage SEPA debit mandates
      $mandateIdx = count($opts['fdd']);
      $opts['fdd']['SepaDebitMandate'] = array(
        'input' => 'V',
        'tab' => array('id' => 'project'),
        'name' => L::t('SEPA Debit Mandate'),
        'select' => 'T',
        'options' => 'LFACPDV',
        'sql' => '`PMEjoin'.$mandateIdx.'`.`mandateReference`', // dummy, make the SQL data base happy
        'sqlw' => '`PMEjoin'.$mandateIdx.'`.`mandateReference`', // dummy, make the SQL data base happy
        'values' => array(
          'table' => 'SepaDebitMandates',
          'column' => 'id',
          'join' => $debitJoinCondition,
          'description' => 'mandateReference'
          ),
        'nowrap' => true,
        'sort' => true,
        'php' => array(
          'type' => 'function',
          'function' => 'CAFEVDB\DetailedInstrumentation::sepaDebitMandatePME',
          'parameters' => array('projectName' => $projectName,
                                'projectId' => $projectId,
                                'musicianIdIdx' => $musIdIdx,
                                'musicianFirstNameIdx' => $musFirstNameIdx,
                                'musicianLastNameIdx' => $musLastNameIdx,
                                'naked' => $this->pme_bare)
          )
        );

      $opts['fdd']['DebitMandateProject'] = array(
        'input' => 'V',
        'name' => 'internal data',
        'select' => 'T',
        'options' => 'H',
        'sql' => '`PMEjoin'.$mandateIdx.'`.`projectId`', // dummy, make the SQL data base happy
        'sqlw' => '`PMEjoin'.$mandateIdx.'`.`projectId`' // dummy, make the SQL data base happy
        );
    }

    // Generate input fields for the extra columns
    foreach ($userExtraFields as $field) {
      $name = $field['name'];
      $opts['fdd']["$name"] = array('name'     => $name."\n(".$projectName.")",
                                    'tab' => array('id' => 'project'),
                                    'select'   => 'T',
                                    'maxlen'   => 65535,
                                    'textarea' => array('css' => '',
                                                        'rows' => 2,
                                                        'cols' => 32),
                                    'escape'   => false,
                                    'sort'     => true);
      if ($field['tooltip'] !== false) {
        $opts['fdd']["$name"]['tooltip'] = $field['tooltip'];
      }
    }
    $opts['fdd']['ProjectRemarks'] =
      array('name' => L::t("Remarks")."\n(".$projectName.")",
            'select'   => 'T',
            'maxlen'   => 65535,
            'css'      => array('postfix' => ' remarks tooltip-left'),
            'display|LF' => array('popup' => 'data'),
            'textarea' => array('css' => 'wysiwygeditor',
                                'rows' => 5,
                                'cols' => 50),
            'escape' => false,
            'sort'   => true,
            'tab'    => array('id' => 'project')
        );

    // fetch the list of all projects in order to provide a somewhat
    // cooked filter list
    $allProjects = Projects::fetchProjects(false /* no db handle */, true /* include years */);
    $projectQueryValues = array('*' => '*'); // catch-all filter
    $projectQueryValues[''] = L::t('no projects yet');
    $projects = array();
    $groupedProjects = array();
    foreach ($allProjects as $proj) {
      $projectQueryValues[$proj['Name']] = $proj['Jahr'].': '.$proj['Name'];
      $projects[$proj['Name']] = $proj['Name'];
      $groupedProjects[$proj['Name']] = $proj['Jahr'];
    }

    $opts['fdd']['Projects'] = array(
      'tab' => array('id' => array('musician')),
      'input' => 'R',
      'options' => 'LFV',
      'select' => 'M',
      'display|LF'  => array('popup' => 'data'),
      'css'      => array('postfix' => ' projects'),
      'name' => L::t('Projects'),
      'sort' => true,
      'values' => array('queryvalues' => $projectQueryValues),
      'values2' => $projects,
      'valueGroups' => $groupedProjects
      );

    $opts['fdd']['Email'] = Config::$opts['email'];
    $opts['fdd']['Email']['tab'] = array('id' => 'musician');

    $opts['fdd']['MobilePhone'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('Mobile Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'nowrap'   => true,
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true
      );

    $opts['fdd']['FixedLinePhone'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('Fixed Line Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'nowrap'   => true,
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true
      );

    $opts['fdd']['Strasse'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('Street'),
      'css'      => array('postfix' => ' musician-address street'),
      'nowrap'   => true,
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true
      );

    $opts['fdd']['Postleitzahl'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('Postal Code'),
      'css'      => array('postfix' => ' musician-address postal-code'),
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true
      );

    $opts['fdd']['Stadt'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('City'),
      'css'      => array('postfix' => ' musician-address city'),
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true
      );

    $countries = GeoCoding::countryNames();
    $countryGroups = GeoCoding::countryContinents();

    $opts['fdd']['Land'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => L::t('Country'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => Config::getValue('streetAddressCountry'),
      'values2'     => $countries,
      'valueGroups' => $countryGroups,
      'css'      => array('postfix' => ' musician-address country chosen-dropup tooltip-top'),
      'sort'     => true,
      );

    $opts['fdd']['Geburtstag'] = Config::$opts['birthday'];
    $opts['fdd']['Geburtstag']['tab'] = 'musician';

    $opts['fdd']['Remarks'] = array(
      'name'     => strval(L::t('General Remarks')),
      'tab'      => array('id' => 'musician'),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => array('postfix' => ' remarks tooltip-left'),
      'display|LF' => array('popup' => 'data'),
      'textarea' => array('css' => 'wysiwygeditor',
                          'rows' => 5,
                          'cols' => 50),
      'escape'   => false,
      'sort'     => true);

    $opts['fdd']['Sprachpräferenz'] = array(
      'tab'      => array('id' => 'musician'),
      'name'     => 'Spachpräferenz',
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutsch',
      'sort'     => true,
      'values'   => Config::$opts['languages']);

    $opts['fdd']['Portrait'] = array(
      'tab'     => array('id' => 'musician'),
      'input'   => 'V',
      'name'    => L::t('Photo'),
      'select'  => 'T',
      'options' => 'ACPDV',
      'sql'     => '`PMEtable0`.`MusikerId`',
      'php'     => array(
        'type' => 'function',
        'function' => 'CAFEVDB\Musicians::portraitImageLinkPME',
        'parameters' => array()
        ),
      'css' => array('postfix' => ' photo'),
      'default' => '',
      'css' => array('postfix' => ' photo'),
      'sort' => false);

    $opts['fdd']['UUID'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'name'     => 'UUID',
      'options'  => 'AVCPDR', // auto increment
      'css'      => array('postfix' => ' musician-uuid'),
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => false
      );

    $opts['fdd']['Aktualisiert'] = array_merge(
      Config::$opts['datetime'],
      array("name" => L::t("Last Updated"),
            'tab'     => array('id' => array('project', 'musician', 'instrumentation')),
            "default" => date(Config::$opts['datetime']['datemask']),
            "nowrap"  => true,
            "options" => 'LFAVCPDR' // Set by update trigger.
        ));

    $opts['triggers']['update']['before'] = array();
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';
    $opts['triggers']['delete']['before'][]  = 'CAFEVDB\DetailedInstrumentation::beforeDeleteTrigger';

    if ($this->pme_bare) {
      // disable all navigation buttons, probably for html export
      $opts['navigation'] = 'N'; // no navigation
      $opts['options'] = '';
      // Don't display special page elements
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => false,
                                        'query' => false,
                                        'sort'  => false,
                                        'time'  => false,
                                        'tabs'  => false,
                                        ));
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }

    $opts['execute'] = $this->execute;

    // Inject the underlying table name as 'querygroup' parameter
    // s.t. update queries can be split into several queries which
    // only target one of the underlying tables.
    $viewStructure = Projects::viewStructure($projectId, $userExtraFields);
    //print_r($viewStructure);
    foreach($opts['fdd'] as $name => &$data) {
      if (isset($viewStructure[$name])) {
        $joinField = $viewStructure[$name];
        $table = $joinField['table'];
        $key = isset($joinField['key']) ? $joinField['key'] : false;
        $column = $joinField['column'] === true ? $name : $joinField['column'];
        $data['querygroup'] = array(
          'table' => $table,
          'column' => $column,
          'key' => $key
          );
      }
    }

    $this->pme = new \phpMyEdit($opts); // Generate and possibly display the table

    if (Util::debugMode('request')) {
      echo '<PRE>';
      print_r($_POST);
      echo '</PRE>';
    }

  } // display()

  /**This is the phpMyEdit before-delete trigger. We cannot delete
   * lines from the view directly, we have to resort to the underlying
   * 'Besetzungen' table (which obviously is also what we want here!).
   *
   * phpMyEdit calls the trigger (callback) with
   * the following arguments:
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
  public static function beforeDeleteTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {//DELETE FROM Spielwiese2013View WHERE (Id = 146)
    $id = $oldvals['Id'];
    $where = "`Id` = ".$id;
    $realOldVals = mySQL::fetchRows('Besetzungen', $where, $pme->dbh);
    $query = "DELETE FROM `Besetzungen` WHERE ".$where;
    $result = mySQL::query($query, $pme->dbh);
    if ($result !== false && count($realOldVals) == 1) {
      mySQL::logDelete('Besetzungen', 'Id', $realOldVals[0], $pme->dbh);
    }
    return false;
  }

  public static function sepaDebitMandatePME($referenceId, $opts, $action, $k, $fds, $fdd, $row)
  {
    if ($opts['naked']) {
      return $row['qf'.$k];
    }

    // Fetch the options ...
    $projectId        = $opts['projectId'];
    $projectName      = $opts['projectName'];
    $musIdIdx         = $opts['musicianIdIdx'];
    $musFirstNameIdx  = $opts['musicianFirstNameIdx'];
    $musLastNameIdx   = $opts['musicianLastNameIdx'];

    // Careful: this changes when rearranging the ordering of the display
    $musicianId        = $row['qf'.$musIdIdx];
    $musicianFirstName = $row['qf'.$musFirstNameIdx];
    $musicianLastName  = $row['qf'.$musLastNameIdx];

    $musician = $musicianLastName.', '.$musicianFirstName;

    if ($row['qf'.$k] != '') {
      $value = $row['qf'.$k];
      if ($row['qf'.($k+1)] != $projectId) {
        $projectId = $row['qf'.($k+1)];
        $projectName = Projects::fetchName($projectId);
      }
    } else {
      $value = L::t("SEPA Debit Mandate");
    }

    return self::sepaDebitMandateButton($value, $musicianId, $musician, $projectId, $projectName);
  }

  /**Generate a clickable form element which finally will display the
   * debit-mandate dialog, i.e. load some template stuff by means of
   * some java-script and ajax blah.
   */
  public static function sepaDebitMandateButton($value, $musicianId, $musician, $projectId, $projectName)
  {
    $data = array('MusicianId' => $musicianId,
                  'MusicianName' => $musician,
                  'ProjectId' => $projectId,
                  'ProjectName' => $projectName);
    $data = htmlspecialchars(json_encode($data));

    $css= ($value == L::t("SEPA Debit Mandate")) ? "no-sepa-debit-mandate" : "sepa-debit-mandate";
    $button = '<div class="sepa-debit-mandate">'
      .'<input type="button" '
      .'       id="sepa-debit-mandate-'.$musicianId.'-'.$projectId.'"'
      .'       class="'.$css.'" '
      .'       value="'.$value.'" '
      .'       title="'.L::t("Click to enter details of a potential SEPA debit mandate").' " '
      .'       name="SepaDebitMandate" '
      .'       data-debit-mandate="'.$data.'" '
      .'/>'
      .'</div>';
    return $button;
  }

}; // class DetailedInstrumentation

}

?>
