<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**Display all or selected musicians.
 */
class Musicians
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  const CSS_CLASS = 'musicians';
  const TABLE = 'Musiker';
  const INSTRUMENTS = 'MusicianInstruments';
  private $projectMode;

  /**Constructor.
   *
   * @param[in] boolean $mode Start in "project-mode" which will mask
   * out all musicians for the project passed as CGI-Variable ProjectId.
   *
   * @param[in] boolean $execute If @c true, emit HTML code, with
   * data-base-query side-effect. Otherwise self::execute() has to be
   * called explicitly.
   */
  function __construct($mode = false, $execute = true) {
    parent::__construct($execute);
    $this->projectMode = $mode;
    //$this->recordsPerPage = 25;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return L::t('Remove all data of the displayed musician?');
    } else if ($this->copyOperation()) {
      return L::t('Copy the displayed musician?');
    } else if ($this->viewOperation()) {
      return L::t('Display of all stored personal data for the shown musician.');
    } else if ($this->changeOperation()) {
      return L::t('Edit the personal data of the displayed musician.');
    } else if ($this->addOperation()) {
      return L::t('Add a new musician to the data-base.');
    } else if (!$this->projectMode) {
      return L::t('Overview over all registered musicians');
    } else {
      return L::t("Add musicians to the project `%s'", array($this->projectName));
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    if ($this->projectMode) {
      $header .= "
<p>
This page is the only way to add musicians to projects in order to
make sure that the musicians are also automatically added to the
`global' musicians data-base (and not only to the project).";
    }

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  /**Display the list of all musicians. If $projectMode == true,
   * filter out all musicians present in $projectId and add a
   * hyperlink which will add the Musician to the respective project.
   */
  public function display()
  {
    global $debug_query;
    $debug_query = Util::debugMode('query');

    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->opts;

    $opts['tb'] = self::TABLE;

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'Template' => $this->projectMode
      ? 'add-musicians' : 'all-musicians',
      'Table' => $opts['tb'],
      'DisplayClass' => 'Musicians',
      'ClassArguments' => array($this->projectMode));

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Instrumente','Name','Vorname','Id');

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'Id';

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '5';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    if (!$this->projectMode) {
      $export = Navigation::tableExportButton();
      $opts['buttons'] = Navigation::prependTableButton($export, true);
    }

    // Display special page elements
    $opts['display'] =  array_merge(
      $opts['display'],
      array(
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => array(
          array('id' => 'orchestra',
                'default' => true,
                'tooltip' => Config::toolTips('musician-orchestra-tab'),
                'name' => L::t('Instruments and Status')),
          array('id' => 'contact',
                'tooltip' => Config::toolTips('musican-contact-tab'),
                'name' => L::t('Contact Information')),
          array('id' => 'miscinfo',
                'tooltip' => Config::toolTips('musician-miscinfo-tab'),
                'name' => L::t('Miscellaneous Data')),
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
      'tab'      => array('id' => 'miscinfo'),
      'name'     => 'Id',
      'select'   => 'T',
      'options'  => 'AVCPDR', // auto increment
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true
      );

    $bval = strval(L::t('Add to %s', array($projectName)));
    $tip  = strval(Config::toolTips('register-musician'));
    if ($this->projectMode) {
      $opts['fdd']['AddMusicians'] = array(
        'tab' => array('id' => 'orchestra'),
        'name' => L::t('Add Musicians'),
        'select' => 'T',
        'options' => 'VLR',
        'input' => 'V',
        'sql' => "REPLACE('"
."<div class=\"register-musician\">"
."<input type=\"button\" "
."value=\"$bval\" "
."data-musician-id=\"@@key@@\" "
."title=\"$tip\" "
."name=\"registerMusician\" "
."class=\"register-musician\" />"
."</div>'"
.",'@@key@@',`PMEtable0`.`Id`)",
        'escape' => false,
        'nowrap' => true,
        'sort' =>false,
        //'php' => "AddMusician.php"
        );
    }

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    $opts['fdd']['Name'] = array(
      'tab'      => array('id' => 'tab-all'),
      'name'     => L::t('Surname'),
      'css'      => array('postfix' => ' musician-name'.' '.$addCSS),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Vorname'] = array(
      'tab'      => array('id' => 'tab-all'),
      'name'     => L::t('Forename'),
      'css'      => array('postfix' => ' musician-name'.' '.$addCSS),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $musInstIdx = count($opts['fdd']);
    $opts['fdd']['MusicianInstrumentsJoin'] = array(
      'name'   => L::t('Instrument Join Pseudo Field'),
      'sql'    => 'GROUP_CONCAT(DISTINCT PMEjoin'.$musInstIdx.'.Id ORDER BY PMEjoin'.$musInstIdx.'.InstrumentId ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table'       => 'MusicianInstruments',
        'column'      => 'Id',
        'description' => array('columns' => 'Id'),
        'join'        => '$join_table.MusicianId = $main_table.Id',
        )
      );

    $opts['fdd']['InstrumentKey'] = array(
      'name'  => L::t('Instrument Key'),
      'sql'   => 'GROUP_CONCAT(DISTINCT PMEjoin'.$musInstIdx.'.Id ORDER BY PMEjoin'.$musInstIdx.'.InstrumentId ASC)',
      'input' => 'SRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      );

    $instIdx = count($opts['fdd']);
    $opts['fdd']['Instruments'] = array(
      'tab'         => array('id' => 'orchestra'),
      'name'        => L::t('Instruments'),
      'css'         => array('postfix' => ' musician-instruments tooltip-top'),
      'display|LVF' => array('popup' => 'data'),
      'input'       => 'S', // skip
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.Id ORDER BY PMEjoin'.$instIdx.'.Id ASC)',
      //'input' => 'V', not virtual, tweaked by triggers
      'select'      => 'M',
      //'filter'      => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table'       => 'Instrumente',
        'column'      => 'Id',
        'description' => 'Id',
        'orderby'     => 'Sortierung',
        'join'        => '$join_table.Id = PMEjoin'.$musInstIdx.'.InstrumentId'
        ),
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
      );

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $opts['fdd']['MemberStatus'] = array(
      'name'    => strval(L::t('Member Status')),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => array('postfix' => ' memberstatus tooltip-wide'),
      'values2' => $this->memberStatusNames,
      'tooltip' => Config::toolTips('member-status')
      );

    // fetch the list of all projects in order to provide a somewhat
    // cooked filter list
    $allProjects = Projects::fetchProjects(false /* no db handle */, true /* include years */);
    $projects = array();
    $groupedProjects = array();
    foreach ($allProjects as $proj) {
      $projects[$proj['Name']] = $proj['Name'];
      $groupedProjects[$proj['Name']] = $proj['Jahr'];
    }

    // Dummy field in order to get the Besetzungen table for the Projects field
    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['MusikerId'] = array(
      'input' => 'VH',
      'sql' => '`'.$join_table.'`.`MusikerId`',
//    'sqlw' => '`'.$join_table.'`.`MusikerId`',
      'options' => '',
      'values' => array(
        'table' => 'Besetzungen',
        'column' => 'MusikerId',
        'description' => 'MusikerId',
        'join' => '$main_table.`Id` = $join_table.`MusikerId`'
        )
      );

    $projectsIdx = count($opts['fdd']);
    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['Projects'] = array(
      'tab' => array('id' => 'orchestra'),
      'input' => 'VR',
      'options' => 'LFV',
      'select' => 'M',
      'name' => L::t('Projects'),
      'sort' => true,
      'css'      => array('postfix' => ' projects tooltip-top'),
      'display|LVF' => array('popup' => 'data'),
      'sql' => "GROUP_CONCAT(DISTINCT `".$join_table."`.`Name` ORDER BY `".$join_table."`.`Name` ASC SEPARATOR ',')",
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table' => 'Projekte',
        'column' => 'Name',
        'description' => 'Name',
        'join' => '`PMEjoin'.($idx-1).'`.`ProjektId` = $join_table.`Id`',
        ),
      'values2' => $projects,
      'valueGroups' => $groupedProjects
      );

    $opts['fdd']['MobilePhone'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Mobile Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['FixedLinePhone'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Fixed Line Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Email'] = Config::$opts['email'];
    $opts['fdd']['Email']['tab'] = array('id' => 'contact');

    $opts['fdd']['Strasse'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Street'),
      'css'      => array('postfix' => ' musician-address street'),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Postleitzahl'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Postal Code'),
      'css'      => array('postfix' => ' musician-address postal-code'),
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true
      );

    $opts['fdd']['Stadt'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('City'),
      'css'      => array('postfix' => ' musician-address city'),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $countries = GeoCoding::countryNames();
    $countryGroups = GeoCoding::countryContinents();

    $opts['fdd']['Land'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Country'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => Config::getValue('streetAddressCountry'),
      'values2'     => $countries,
      'valueGroups' => $countryGroups,
      'css'      => array('postfix' => ' musician-address country chosen-dropup'),
      'sort'     => true);

    $opts['fdd']['Geburtstag'] = Config::$opts['birthday'];
    $opts['fdd']['Geburtstag']['tab'] = array('id' => 'miscinfo');

    $opts['fdd']['Remarks'] = array(
      'tab'      => array('id' => 'orchestra'),
      'name'     => strval(L::t('Remarks')),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => array('postfix' => ' remarks tooltip-top'),
      'textarea' => array('css' => 'wysiwygeditor',
                          'rows' => 5,
                          'cols' => 50),
      'display|LF' => array('popup' => 'data'),
      'escape' => false,
      'sort'     => true);

    $opts['fdd']['Sprachpräferenz'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'name'     => L::t('Language'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values2'   => Config::$opts['languages']);

    $opts['fdd']['Insurance'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'input' => 'V',
      'name' => L::t('Instrument Insurance'),
      'select' => 'T',
      'options' => 'CPDV',
      'sql' => "`PMEtable0`.`Id`",
      'escape' => false,
      'nowrap' => true,
      'sort' =>false,
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        return self::instrumentInsurance($musicianId);
      }
      );

    $opts['fdd']['Portrait'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'input' => 'V',
      'name' => L::t('Photo'),
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '`PMEtable0`.`Id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        $stampIdx = array_search('Aktualisiert', $fds);
        $stamp = strtotime($row['qf'.$stampIdx]);
        return self::portraitImageLink($musicianId, $action, $stamp);
      },
      'css' => array('postfix' => ' photo'),
      'default' => '',
      'sort' => false);

    ///////////////////// Test

    $opts['fdd']['VCard'] = array(
      'tab' => array('id' => 'miscinfo'),
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '`PMEtable0`.`Id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        switch($action) {
        case 'change':
        case 'display':
          //$data = self::fetchMusicianPersonalData($musicianId);
          //return nl2br(print_r($fds, true).print_r($row, true));
          $data = array();
          foreach($fds as $idx => $label) {
            $data[$label] = $row['qf'.$idx];
          }
          //return nl2br(print_r($data, true));
          $vcard = VCard::export($data);
          if (true) {
            unset($vcard->PHOTO);
            ob_start();
            \QRcode::png($vcard->serialize());
            $image = ob_get_contents();
            ob_end_clean();
            return '<img height="231" width="231" src="data:image/png;base64,'."\n".base64_encode($image).'"></img>';
//                '<pre style="font-family:monospace;">'.$vcard->serialize().'</pre>';
          } else {
            return '<pre style="font-family:monospace;">'.$vcard->serialize().'</pre>';
          }
        default:
          return '';
        }
      },
      'default' => '',
      'sort' => false
      );

    /////////////////////////

    $opts['fdd']['UUID'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'name'     => 'UUID',
      'options'  => 'AVCPDR', // auto increment
      'css'      => array('postfix' => ' musician-uuid'.' '.$addCSS),
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => false,
      );

    $opts['fdd']['Aktualisiert'] =
      array_merge(
        Config::$opts['datetime'],
        array(
          'tab' => array('id' => 'miscinfo'),
          "name" => L::t("Last Updated"),
          "default" => date(Config::$opts['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger.
          )
        );

    if ($this->projectMode) {
      $key = 'qf'.$projectsIdx;
      $opts['having']['AND'] = "($key IS NULL OR NOT FIND_IN_SET('$projectName', $key))";
      $opts['misc']['css']['major']   = 'bulkcommit';
      $opts['labels']['Misc'] = strval(L::t('Add all to %s', array($projectName)));
    }

    $opts['triggers']['update']['before'] = array();
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Musicians::addOrChangeInstruments';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    $opts['triggers']['insert']['before'] = array();
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Musicians::addUUIDTrigger';
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';
    $opts['triggers']['insert']['after'][]  = 'CAFEVDB\Musicians::addOrChangeInstruments';

    // We never delete any instrument-relatins from the
    //MusicianInstruments table as this might ruine old projects.
    //$opts['triggers']['delete']['after'][] = 'CAFEVDB\Musicians::deleteInstruments';

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
                                        'tabs'  => false
                                        ));
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }

    $opts['execute'] = $this->execute;

    $this->pme = new \phpMyEdit($opts);

    if (Util::debugMode('request')) {
      echo '<PRE>';
      print_r($_POST);
      echo '</PRE>';
    }

  } // display()

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
  public static function beforeTriggerSetTimestamp($pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    $key = 'Aktualisiert';
    $k = array_search($key, $changed);
    if (count($changed) > 0) {
      $k === false && $changed[] = $key;
      $newvals[$key] = date(\CAFEVDB\mySQL::DATEMASK);
    } else {
      if ($k !== false) {
        unset($changed[$k]);
      }
    }
    echo '<!-- '.print_r($newvals, true).'-->';
    return true;
  }

  /**Instruments are stored in a separate pivot-table, hence we have
   * to take care of them from outside PME or use a view.
   *
   * @copydoc beforeTriggerSetTimestamp
   */
  public static function addOrChangeInstruments($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    //error_log(__METHOD__.' '.print_r($newValues,true));
    //error_log(__METHOD__.' '.print_r($oldValues,true));
    //error_log(__METHOD__.' '.print_r($changed,true));
    $field = 'Instruments';
    $keyField = 'InstrumentKey';
    $key = array_search($field, $changed);
    if ($key !== false) {
      //error_log('key: '.$key.' value: '.$changed[$key]);
      $table          = self::INSTRUMENTS;
      $musicianId     = $pme->rec;
      $oldIds  = Util::explode(',', $oldValues[$field]);
      $newIds  = Util::explode(',', $newValues[$field]);
      $oldKeys = Util::explode(',', $oldValues[$keyField]);
      $oldRecords = array_combine($oldIds, $oldKeys);

      // we have to delete any removed instruments and to add any new instruments

      foreach(array_diff($oldIds, $newIds) as $id) {
        $query = "DELETE FROM $table WHERE Id = ".$oldRecords[$id] ;
        if (mySQL::query($query, $pme->dbh) !== false) {
          $old = array('Id' => $oldRecords[$id],
                       'MusicianId' => $musicianId,
                       'InstrumentId' => $id);
          mySQL::logDelete($table, 'Id', $old, $pme->dbh);
        }
      }
      foreach(array_diff($newIds, $oldIds) as $id) {
        $new = array('MusicianId' => $musicianId, 'InstrumentId' => $id);
        $result = mySQL::insert($table, $new, $pme->dbh);
        $rec = mySQL::newestIndex($pme->dbh);
        if($result !== false && $rec > 0) {
          mySQL::logInsert($table, $rec, $new, $pme->dbh);
        }
      }
      unset($changed[$key]);
      unset($newValues[$field]);
      unset($newValues[$keyField]);
    }

    return true;
  }

  public static function addUUIDTrigger($pme, $op, $step, $oldvalues, &$changed, &$newvals)
  {
    $uuid = self::generateUUID($pme->dbh);

    if ($uuid === false) {
      return false;
    }

    $key = 'UUID';
    $changed[] = $key;
    $newvals[$key] = $uuid;

    return true;
  }

  public static function generateUUID($handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $uuid = Util::generateUUID();
    $cnt = 0;
    while (mySQL::queryNumRows("FROM `".self::TABLE."` WHERE `UUID` LIKE '".$uuid."'", $handle) > 0) {
      ++$cnt;
      if ($cnt > 10) {
        // THIS JUST CANNOT BE. SOMETHING ELSE MUST BE WRONG. BAIL OUT.
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.': '.
                            'Failed ' . $cnt . ' times to generate a unique UUID. ' .
                            'Something else must be wrong. Giving up.',
                            \OCP\Util::ERRPR);
        return false; // refuse to add anything
      }
      $uuid = Util::generateUUID();
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $uuid;
  }

  public static function instrumentInsurance($musicianId)
  {
    $amount = InstrumentInsurance::insuranceAmount($musicianId);
    $fee    = InstrumentInsurance::annualFee($musicianId);
    $bval = L::t('Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;',
                 array($amount, $fee));
    $tip = strval(Config::toolTips('musician-instrument-insurance'));
    $button = "<div class=\"musician-instrument-insurance\">"
      ."<input type=\"button\" "
      ."value=\"$bval\" "
      ."title=\"$tip\" "
      ."name=\""
      ."Template=instrument-insurance&amp;"
      ."MusicianId=".$musicianId."\" "
      ."class=\"musician-instrument-insurance\" />"
      ."</div>";
    return $button;
  }

  public static function portraitImageLink($musicianId, $action = 'display', $timeStamp = '')
  {
    switch ($action) {
    case 'add':
      return L::t("Portraits or Avatars can only be added to an existing musician's profile; please add the new musician without protrait image first.");
    case 'display':
      $div = ''
        .'<div class="photo"><img class="cafevdb_inline_image portrait zoomable tooltip-top" src="'
        .\OCP\UTIL::linkTo('cafevdb', 'inlineimage.php').'?ItemId='.$musicianId.'&ImageItemTable=Musiker&ImageSize=1200&TimeStamp='.$timeStamp
        .'" '
        .'title="'.L::t("Photo, if available").'" /></div>';
      return $div;
    case 'change':
      $photoarea = ''
        .'<div id="contact_photo_upload">
  <div class="tip portrait propertycontainer tooltip-top" id="cafevdb_inline_image_wrapper" title="'
      .L::t("Drop photo to upload (max %s)", array(\OCP\Util::humanFileSize(Util::maxUploadSize()))).'"'
        .' data-element="PHOTO">
    <ul id="phototools" class="transparent hidden contacts_property">
      <li><a class="svg delete" title="'.L::t("Delete current photo").'"></a></li>
      <li><a class="svg edit" title="'.L::t("Edit current photo").'"></a></li>
      <li><a class="svg upload" title="'.L::t("Upload new photo").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.L::t("Select photo from ownCloud").'"></a></li>
    </ul>
  </div>
</div> <!-- contact_photo -->
';

      return $photoarea;
    default:
      return L::t("Internal error, don't know what to do concerning portrait images in the given context.");
    }
  }

  /**Fetch all known data from the Musiker table for the respective musician.  */
  public static function fetchMusicianById($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT
 m.*,
 GROUP_CONCAT(DISTINCT mi.`InstrumentId` ORDER BY i.`Sortierung`) AS InstrumentIds,
 GROUP_CONCAT(DISTINCT i.`Instrument` ORDER BY i.`Sortierung`) AS Instruments
FROM `".self::TABLE."` AS m
LEFT JOIN `MusicianInstruments` mi
  ON m.`Id` = mi.`MusicianId`
LEFT JOIN Instrumente i
  ON i.`Id` = mi.`InstrumentId`
WHERE m.`Id` = $musicianId
GROUP BY m.`Id`
";

    //throw new \Exception($query);

    $result = mySQL::query($query, $handle);
    if ($result !== false && mySQL::numRows($result) == 1) {
      $row = mySQL::fetch($result);
    } else {
      $row = false;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /**Fetch all known data from the Musiker table for the respective musician.  */
  public static function fetchMusicianByUUID($musicianUUID, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT
 GROUP_CONCAT(DISTINCT mi.`InstrumentId` ORDER BY i.`Sortierung`) AS InstrumentIds,
 GROUP_CONCAT(DISTINCT i.`Instrument` ORDER BY i.`Sortierung`) AS Instruments
FROM `".self::TABLE."` AS m
LEFT JOIN `MusicianInstruments` mi
  ON m.`Id` = mi.`MusicianId`
LEFT JOIN Instrumente i
  ON i.`Id` = mi.`InstrumentId`
WHERE `UUID` = '$musicianUUID'
GROUP BY m.`Id`
";

    $result = mySQL::query($query, $handle);
    if ($result !== false && mySQL::numRows($result) == 1) {
      $row = mySQL::fetch($result);
    } else {
      $row = false;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /**In principle a musician can have multiple entries per
   * project. Unique is only the combination
   * project-musician-instrument-position. In principle, if a musician
   * plays more than one instrument in different pieces in a project,
   * he or she could be listed twice.
   */
  public static function fetchMusicianProjectData($musicianId, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = " SELECT *
 FROM `Besetzungen`
     WHERE `Besetzungen`.`MusikerId` = $musicianId
       AND `Besetzungen`.`ProjektId` = $projectId";

    $result = mySQL::query($query, $handle);
    if ($result !== false) {
      $rows = array();
      while ($row = mySQL::fetch($result)) {
        $rows[] = $row;
      }
    } else {
      $rows = false;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $rows;
  }

  /**Fetch the street address of the respected musician. Needed in
   * order to generate automated snail-mails.
   *
   * Return value is a flat array:
   *
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'street' => ...,
   *       'city' => ...,
   *       'ZIP' => ...);
   */
  public static function fetchStreetAddress($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query =
      'SELECT '.
      '`Name` AS `surName`'.
      ', '.
      '`Vorname` AS `firstName`'.
      ', '.
      '`Strasse` AS `street`'.
      ', '.
      '`Stadt` AS `city`'.
      ', '.
      '`Postleitzahl` AS `ZIP`'.
      ', '.
      '`FixedLinePhone` AS `phone`'.
      ', '.
      '`MobilePhone` AS `cellphone`';
    $query .= ' FROM `'.self::TABLE.'` WHERE `Id` = '.$musicianId;

    \OCP\Util::writeLog(Config::APP_NAME,
                        __METHOD__.' Query: '.$query,
                        \OCP\Util::DEBUG);

      $result = mySQL::query($query, $handle);

    $row = false;
    if ($result !== false && mySQL::numRows($result) == 1) {
      $row = mySQL::fetch($result);
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /** Fetch the musician-name name corresponding to $musicianId.
   */
  public static function fetchName($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = 'SELECT `Name`,`Vorname`,`Email` FROM `'.self::TABLE.'` WHERE `Id` = '.$musicianId;
    $result = mySQL::query($query, $handle);

    $row = false;
    if ($result !== false && mySQL::numRows($result) == 1) {
      $row = mySQL::fetch($result);
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return array('firstName' => (isset($row['Vorname']) && $row['Vorname'] != '') ? $row['Vorname'] : 'X',
                 'lastName' => (isset($row['Name']) && $row['Name'] != '') ? $row['Name'] : 'X',
                 'email' => (isset($row['Email']) && $row['Email'] != '') ? $row['Email'] : 'X');
  }

  /** Check for duplicate records by Id, UUID, firstName, surName.
   *
   * @param[in] array $records Associate array with records to check
   * for. Supported fields are Id, UUID, Name and Vorname. Name and
   * Vorname will be combined with an AND junctor, Id and UUID, if
   * present, are added with an OR junctor.
   *
   * @param[in] mixed $handle Data-base handle or false.
   *
   * @return @c true if duplicates are found, @c false otherwise.
   */
  public static function findDuplicates($records, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $where = '0';
    if (isset($records['Name']) && isset($records['Vorname'])) {
      $where .= " OR (`Name` = '".$records['Name']."' AND `Vorname` = '".$records['Vorname']."')";
    }
    if (isset($records['UUID'])) {
      $where .= " OR `UUID` = '".$records['UUID']."'";
    }
    if (isset($records['Id'])) {
      $where .= " OR `Id` = '".$records['Id']."'";
    }

    $count = mySQL::queryNumRows("FROM `".self::TABLE."` WHERE ".$where, $handle);

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $count > 0;
  }

  /** Fetch the entire mess of duplicate musicians by name.
   */
  public static function musiciansByName($firstName, $surName, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT * FROM `".self::TABLE."` WHERE `Name` = '".$surName."' AND `Vorname` = '".$firstName."'";
    $result = mySQL::query($query, $handle);

    $musicians = array();
    while ($row = mySQL::fetch($result)) {
      $musicians[] = $row;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $musicians;
  }

  /**Add missing UUID field */
  public static function ensureUUIDs($handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT `Id` FROM `".self::TABLE."` WHERE `UUID` IS NULL";
    $result = mySQL::query($query, $handle);

    $changed = 0;
    while ($row = mySQL::fetch($result)) {
      $query = "UPDATE `".self::TABLE."`
 SET `UUID` = '".Util::generateUUID()."'
 WHERE `Id` = ".$row['Id'];
      if (mySQL::query($query, $handle)) {
        ++$changed;
      }
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $changed;
  }


}; // class

} // namespace CAFEVDB

?>
