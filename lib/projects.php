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

  /** Helper class for displaying projects.
   */
  class Projects
  {
    const CSS_PREFIX = 'cafevdb-page';
    const IMAGE_PLACEHOLDER = 'flyerdummy.svg';
    const NAME_LENGTH_MAX = 20;
    const TABLE_NAME = 'Projekte';
    const INSTRUMENTATION= 'ProjectInstrumentation';
    const REGISTERED = 'ProjectInstruments';
    const INSTRUMENTS = 'Instrumente';
    private $pme;
    private $pme_bare;
    private $execute;
    private $projectId;
    private $projectName;
    private $showDisabled;

    public function __construct($execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;
      $this->projectId = false;
      $this->projectName = false;

      $projectId = Util::cgiValue('ProjectId', false);
      $projectName = Util::cgiValue('ProjectName', false);
      if ($projectId === false  || !$projectName) {
        $projectId = Util::getCGIRecordId();
        $projectName = false;
        if ($projectId >= 0) {
          $projectName = self::fetchName($projectId);
        }
      }

      $this->projectId = $projectId;
      $this->projectName = $projectName;

      Config::init();
      $this->showDisabled = Config::getUserValue('showdisabled', false) === 'on';
    }

    public function deactivate()
    {
      $this->execute = false;
    }

    public function activate()
    {
      $this->execute = true;
    }

    public function execute()
    {
      if ($this->pme) {
        $this->pme->execute();
      }
    }

    public function navigation($enable)
    {
      $this->pme_bare = !$enable;
    }

    public function shortTitle()
    {
      if ($this->projectName !== false) {
        return L::t("%s Project %s",
                    array(ucfirst(Config::getValue('orchestra')),
                          $this->projectName));
      } else {
        return L::t("%s Projects", array(ucfirst(Config::getValue('orchestra'))));
      }
    }

    public function headerText()
    {
      return $this->shortTitle();
    }

    public function display()
    {
      global $debug_query;
      $debug_query = Util::debugMode('query');

      if (Util::debugMode('request')) {
        echo '<PRE>';
        /* print_r($_SERVER); */
        print_r($_POST);
        echo '</PRE>';
      }

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['css']['postfix'] = ' show-hide-disabled';

      $opts['cgi']['persist'] = array(
        'Template' => 'projects',
        'DisplayClass' => 'Projects',
        );

      $opts['tb'] = 'Projekte';

      // Name of field which is the unique key
      $opts['key'] = 'Id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array('-Jahr', 'Name');

      // GROUP BY clause, if needed.
      $opts['groupby_fields'] = 'Id';

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      // $opts['inc'] = -1;

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
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => true,
                                        //'query' => true,
                                        'sort'  => true,
                                        'time'  => true,
                                        'tabs'  => false
                                        ));

      /* Get the user's default language and use it if possible or you can
         specify particular one you want to use. Refer to official documentation
         for list of available languages. */
      //$opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE']; // . '-UTF8';

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
      $opts['fdd']['Id'] = array(
        'name'     => 'Id',
        'select'   => 'T',
        'input'    => 'R',
        'input|AP' => 'RH', // always auto-increment
        'options'  => 'AVCPD',
        'maxlen'   => 11,
        'default'  => '0', // auto increment
        'sort'     => true,
        );

      $currentYear = date('Y');
      $yearRange = self::fetchYearRange();
      $yearValues = array(' ');
      for ($year = $yearRange["min"] - 1; $year < $currentYear + 5; $year++) {
        $yearValues[] = $year;
      }

      $yearIdx = count($opts['fdd']);
      $opts['fdd']['Jahr'] = array(
        'name'     => 'Jahr',
        'select'   => 'N',
        //'options'  => 'LAVCPDF'
        'maxlen'   => 5,
        'default'  => $currentYear,
        'sort'     => true,
        'values'   => $yearValues,
        );

      $nameIdx = count($opts['fdd']);
      $opts['fdd']['Name'] = array(
        'name'     => L::t('Projekt-Name'),
        'php|LF'  => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
          //error_log('project-id: '.$recordId);
          $projectId = $recordId;
          $projectName = $value;
          $placeHolder = false;
          $overview = true;
          return self::projectActions($projectId, $projectName, $placeHolder, $overview);
        },
        'select'   => 'T',
        'select|LF' => 'D',
        'maxlen'   => self::NAME_LENGTH_MAX + 6,
        'css'      => array('postfix' => ' projectname control'),
        'sort'     => true,
        'values|LF'   => array(
          'table' => 'Projekte',
          'column' => 'Name',
          'description' => 'Name',
          'groups' => 'Jahr',
          'orderby' => '$table.`Jahr` DESC',
          ),
        );

      if ($this->showDisabled) {
        $opts['fdd']['Disabled'] = array(
          'name'     => L::t('Disabled'),
          'css'      => array('postfix' => ' project-disabled'),
          'values2|CAP' => array(1 => ''),
          'values2|LVFD' => array(1 => L::t('true'),
                                  0 => L::t('false')),
          'default'  => '',
          'select'   => 'O',
          'sort'     => true,
          'tooltip'  => Config::toolTips('extra-fields-disabled')
          );
      }

      $opts['fdd']['Art'] = array(
        'name'     => L::t('Kind'),
        'select'   => 'D',
        'options'  => 'AVCPD', // auto increment
        'maxlen'   => 11,
        'css'      => array('postfix' => ' tooltip-right'),
        'values2'  => array('temporary' => L::t('temporary'),
                            'permanent' => L::t('permanent')),
        'default'  => 'temporary',
        'sort'     => false,
        'tooltip' => Config::toolTips('project-kind')
        );

      $opts['fdd']['Actions'] = array(
        'name'     => L::t('Actions'),
        'input'    => 'RV',
        'sql'      => '`PMEtable0`.`Name`',
        'php|VCLDF'    => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
          $projectId = $recordId;
          $projectName = $value;
          $overview = false;
          $placeHolder = L::t("Actions");
          return self::projectActions($projectId, $projectName, $placeHolder, $overview);
        },
        'select'   => 'T',
        'options'  => 'VD',
        'maxlen'   => 11,
        'default'  => '0',
        'css'      => array('postfix' => ' control'),
        'sort'     => false
        );

      $instrumentInfo = Instruments::fetchInfo();

      if (false) {
        $groupedInstruments = $instrumentInfo['nameGroups'];
        $instruments        = $instrumentInfo['byName'];

        $opts['fdd']['Besetzung'] = array(
          'name'     => 'Besetzung',
          'options'  => 'LAVCPD',
          'select'   => 'M',
          'maxlen'   => 11,
          'sort'     => true,
          'display|LF' => array("popup" => 'data',
                                "prefix" => '<div class="projectinstrumentation">',
                                "postfix" => '</div>'),
          'css'      => array('postfix' => ' projectinstrumentation tooltip-top'),
          'values'   => $instruments,
          'valueGroups' => $groupedInstruments,
          );
      }

      $projInstIdx = count($opts['fdd']);
      $opts['fdd']['ProjectInstrumentationJoin'] = [
        'name'   => L::t('Instrumentation Join Pseudo Field'),
        'sql'    => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.Id
  ORDER BY PMEjoin'.$projInstIdx.'.InstrumentId ASC)',
        'input'  => 'VRH',
        'filter' => 'having', // need "HAVING" for group by stuff
        'values' => array(
          'table'       => self::INSTRUMENTATION,
          'column'      => 'Id',
          'description' => array('columns' => 'Id'),
          'join'        => '$join_table.ProjectId = $main_table.Id',
          )
        ];

      $opts['fdd']['InstrumentationKey'] = array(
        'name'  => L::t('Instrumentation Key'),
        'sql'   => 'GROUP_CONCAT(DISTINCT PMEjoin'.$projInstIdx.'.Id
  ORDER BY PMEjoin'.$projInstIdx.'.InstrumentId ASC)',
        'input' => 'SRH',
        'filter' => 'having', // need "HAVING" for group by stuff
        );

      $instIdx = count($opts['fdd']);
      $opts['fdd']['Instrumentation'] = array(
        'name'        => L::t('Instrumentation'),
        'input'       => 'S', // skip
        'sort'        => true,
        'display|LF'  => ["popup" => 'data',
                          "prefix" => '<div class="projectinstrumentation">',
                          "postfix" => '</div>'],
        'css'         => ['postfix' => ' projectinstrumentation tooltip-top'],
        'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.Id ORDER BY PMEjoin'.$instIdx.'.Id ASC)',
        //'input' => 'V', not virtual, tweaked by triggers
        'filter'      => 'having',
        'select'      => 'M',
        'maxlen'      => 11,
        'values' => array(
          'table'       => 'Instrumente',
          'column'      => 'Id',
          'description' => 'Id',
          'orderby'     => 'Sortierung',
          'join'        => '$join_table.Id = PMEjoin'.$projInstIdx.'.InstrumentId'
          ),
        'values2'     => $instrumentInfo['byId'],
        'valueGroups' => $instrumentInfo['idGroups'],
        );

      $opts['fdd']['Tools'] = array(
        'name'     => L::t('Toolbox'),
        'input'    => 'V',
        'options'  => 'VCD',
        'select'   => 'T',
        'maxlen'   => 65535,
        'css'      => array('postfix' => ' projecttoolbox'),
        'sql'      => '`PMEtable0`.`Name`',
        'php|CV'   =>  function($value, $op, $field, $fds, $fdd, $row, $recordId) {
          $projectName = $value;
          $projectId = $recordId;
          return self::projectToolbox($projectId, $projectName);
        },
        'sort'     => true,
        'escape'   => false
        );

      $opts['fdd']['Unkostenbeitrag'] = Config::$opts['money'];
      $opts['fdd']['Unkostenbeitrag']['name'] = L::t("Project Fee");
      $opts['fdd']['Unkostenbeitrag']['maxlen'] = 8;
      $opts['fdd']['Unkostenbeitrag']['tooltip'] = L::t('Default project fee for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
      $opts['fdd']['Unkostenbeitrag']['display|LF'] = array('popup' => 'tooltip');
      $opts['fdd']['Unkostenbeitrag']['css']['postfix'] .= ' tooltip-top';

      $opts['fdd']['Anzahlung'] = Config::$opts['money'];
      $opts['fdd']['Anzahlung']['name'] = L::t("Deposit");
      $opts['fdd']['Anzahlung']['maxlen'] = 8;
      $opts['fdd']['Anzahlung']['tooltip'] = L::t('Default project deposit for ordinary participants. This should NOT include reductions of any kind. The value displayed here is the default value inserted into the instrumentation table for the project.');
      $opts['fdd']['Anzahlung']['display|LF'] = array('popup' => 'tooltip');
      $opts['fdd']['Anzahlung']['css']['postfix'] .= ' tooltip-top';

      $idx = count($opts['fdd']);
      $join_table = 'PMEjoin'.$idx;
      $opts['fdd']['ExtraFelderJoin'] = array(
        'options'  => 'FLCVD',
        'input'    => 'VRH',
        'sql'      => '`PMEtable0`.`Id`',
        'filter'   => 'having',
        'values'   => array(
          'table'  => 'ProjectExtraFields',
          'column' => 'Name',
          'description' => 'Name',
          'join'   => '$main_table.`Id` = $join_table.`ProjectId`'
          ),
        );

      $opts['fdd']['ExtraFelder'] = array(
        'name' => L::t('Extra Member Data'),
        'options'  => 'FLCVD',
        'input'    => 'VR',
        'sql'      => ("GROUP_CONCAT(DISTINCT NULLIF(`".$join_table."`.`Name`,'') ".
                       "ORDER BY `".$join_table."`.`Name` ASC SEPARATOR ', ')"),
        'php|VCP'  => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx) {
            $post = array('ProjectExtraFields' => $value,
                          'Template' => 'project-extra',
                          'ProjectName' => $row['qf'.$nameIdx],
                          'ProjectId' => $recordId);
            $post = http_build_query($post, '', '&');
            $title = Config::toolTips('project-action-extra-fields');
            $link =<<<__EOT__
<li class="nav tooltip-top" title="$title">
  <a class="nav" href="#" data-post="$post">
$value
  </a>
</li>
__EOT__;
          return $link;
        },
        'select'   => 'T',
        'maxlen'   => 30,
        'css'      => array('postfix' => ' projectextra'),
        'sort'     => false,
        'escape'   => false,
        'display|LF' => array('popup' => 'data'),
        );

      $opts['fdd']['Programm'] = array(
        'name'     => L::t('Program'),
        'input'    => 'V',
        'options'  => 'VCD',
        'select'   => 'T',
        'maxlen'   => 65535,
        'css'      => array('postfix' => ' projectprogram'),
        'sql'      => '`PMEtable0`.`Id`',
        'php|CV'    => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
          $projectId = $recordId; // and also $value
          return self::projectProgram($projectId, $op);
        },
        'sort'     => true,
        'escape' => false
        );

      $opts['fdd']['Flyer'] = array(
        'input' => 'V',
        'name' => L::t('Flyer'),
        'select' => 'T',
        'options' => 'CPDV',
        'sql'      => '`PMEtable0`.`Aktualisiert`',
        'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
          $projectId = $recordId;
          $stamp = $value;
          return self::flyerImageLink($projectId, $op, $stamp);
        },
        'css' => array('postfix' => ' projectflyer'),
        'default' => '',
        'sort' => false);

      $opts['fdd']['Aktualisiert'] =
        array_merge(
          Config::$opts['datetime'],
          array(
            //'tab' => array('id' => 'miscinfo'),
            "name" => L::t("Last Updated"),
            "default" => date(Config::$opts['datetime']['datemask']),
            "nowrap" => true,
            "options" => 'LFAVCPDR' // Set by update trigger.
            )
          );

      /* Table-level filter capability. If set, it is included in the WHERE clause
         of any generated SELECT statement in SQL query. This gives you ability to
         work only with subset of data from table.

         $opts['filters'] = "column1 like '%11%' AND column2<17";
         $opts['filters'] = "section_id = 9";
         $opts['filters'] = "PMEtable0.sessions_count > 200";

         $opts['filters']['OR'] = expression or array;
         $opts['filters']['AND'] = expression or array;

         $opts['filters'] = andexpression or array(andexpression1, andexpression2);
      */
      $sysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
      $opts['filters'] = [ 'OR' => [], 'AND' => [] ];
      if (!empty(Util::cgiValue($sysPfx.'qf'.$nameIdx.'_id', false))) {
        // unset the year filter, as it does not make sense
        unset($_POST[$sysPfx.'qf'.$yearIdx]);
        unset($_GET[$sysPfx.'qf'.$yearIdx]);
      } else {
        $opts['filters']['OR'][] = "`PMEtable0`.`Art` = 'permanent'";
      }
      $opts['filters']['AND'][] = '`PMEtable0`.`Disabled` <= '.intval($this->showDisabled);

      // We could try to use 'before' triggers in order to verify the
      // data. However, at the moment the stuff does not work without JS
      // anyway, and we use Ajax calls to verify the form data.

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Projects::addOrChangeInstrumentation';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Projects::beforeUpdateTrigger';
      $opts['triggers']['update']['after'][]   = 'CAFEVDB\Projects::afterUpdateTrigger';

      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Projects::beforeInsertTrigger';
      $opts['triggers']['insert']['after'][]   = 'CAFEVDB\Projects::addOrChangeInstrumentation';
      $opts['triggers']['insert']['after'][]   = 'CAFEVDB\Projects::afterInsertTrigger';

      $opts['triggers']['delete']['before'][] = 'CAFEVDB\Projects::deleteTrigger';
      $opts['triggers']['delete']['after'][] = 'CAFEVDB\Projects::deleteTrigger';

      $opts['execute'] = $this->execute;
      $this->pme = new \phpMyEdit($opts);

    }

    /**Validate the name, no spaces, camel case, last four characters
     * are either digits of the form 20XX.
     *
     * @param[in] string $projectName The name to validate.
     *
     * @param[in] boolean $requireYear Year in last four characters is
     * mandatory.
     */
    public static function sanitizeName($projectName, $requireYear = false)
    {
      $projectYear = substr($projectName, -4);
      if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
        $projectYear = null;
      } else {
        $projectName = substr($projectName, 0, -4);
      }
      if ($requireYear && !$projectYear) {
        return false;
      }

      $projectName = ucwords($projectName);
      $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);

      if ($projectYear) {
        $projectName .= $projectYear;
      }
      return $projectName;
    }

    /**Instruments are stored in a separate pivot-table, hence we have
     * to take care of them from outside PME or use a view.
     *
     * @copydoc beforeTriggerSetTimestamp
     */
    public static function addOrChangeInstrumentation($pme, $op, $step, &$oldValues, &$changed, &$newValues)
    {
      //error_log(__METHOD__.' '.print_r($newValues,true));
      //error_log(__METHOD__.' '.print_r($oldValues,true));
      //error_log(__METHOD__.' '.print_r($changed,true));
      $field = 'Instrumentation';
      $keyField = 'InstrumentationKey';
      $key = array_search($field, $changed);
      if ($key !== false) {
        //error_log('key: '.$key.' value: '.$changed[$key]);
        $table      = self::INSTRUMENTATION;
        $projectId  = $pme->rec;
        $oldIds     = Util::explode(',', $oldValues[$field]);
        $newIds     = Util::explode(',', $newValues[$field]);
        $oldKeys    = Util::explode(',', $oldValues[$keyField]);
        $oldRecords = array_combine($oldIds, $oldKeys);

        // we have to delete any removed instruments and to add any new instruments

        foreach(array_diff($oldIds, $newIds) as $id) {
          $query = "DELETE FROM $table WHERE Id = ".$oldRecords[$id] ;
          if (mySQL::query($query, $pme->dbh) !== false) {
            $old = array('Id' => $oldRecords[$id],
                         'ProjectId' => $musicianId,
                         'InstrumentId' => $id);
            mySQL::logDelete($table, 'Id', $old, $pme->dbh);
          }
        }
        foreach(array_diff($newIds, $oldIds) as $id) {
          $new = array('ProjectId' => $projectId, 'InstrumentId' => $id);
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
      if (isset($newvals['Name']) && $newvals['Name']) {
        $newvals['Name'] = self::sanitizeName($newvals['Name']);
        if ($newvals['Name'] === false) {
          return false;
        }
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
      if (array_search('Name', $changed) === false) {
        return true;
      }
      if (isset($newvals['Name']) && $newvals['Name']) {
        $newvals['Name'] = self::sanitizeName($newvals['Name']);
        if ($newvals['Name'] === false) {
          return false;
        }
      }
      return true;
    }

    // $newvals contains the new values

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
    public static function afterInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      // $newvals contains the new values
      $projectId   = $pme->rec;
      $projectName = $newvals['Name'];

      // Create the view and make sure we have enough extra fields in the
      // Besetzungen table
      self::createView($projectId, $projectName, $pme->dbh);

      // Also create the project folders.
      $projectPaths = self::maybeCreateProjectFolder($projectId, $projectName);

      // Maybe create a wiki-page with just the project-title

      if (false) {
        $orchestra = Config::$opts['orchestra']; // for the name-space
        $pagename = $orchestra.":projekte:".$projectName;

        $page = "===== ".$projectName." im Jahr ".$newvals['Jahr']." =====";

        $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
        $dwembed = new \DWEMBED\App($wikiLocation);
        $dwembed->putPage($pagename, $page, array("sum" => "Automatic CAFEVDB page creation",
                                                  "minor" => false));
      }
      self::generateWikiOverview();
      self::generateProjectWikiPage($projectId, $projectName, $handle);

      // Generate an empty offline page template in the public web-space
      self::createProjectWebPage($projectId, 'concert', $pme->dbh);
      self::createProjectWebPage($projectId, 'rehearsals', $pme->dbh);

      return true;
    }

    /**@copydoc Projects::afterInsertTrigger() */
    public static function afterUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      // Simply recreate the view, update the extra tables etc.
      self::createView($pme->rec, $newvals['Name'], $pme->dbh);

      if (array_search('Name', $changed) === false) {
        // Nothing more has to be done if the name stays the same
        return true;
      }

      // Drop the old view, which still exists with the old name
      $sqlquery = 'DROP VIEW IF EXISTS `'.$oldvals['Name'].'View`';
      mySQL::query($sqlquery, $pme->dbh);

      // Now that we link events to projects using their short name as
      // category, we also need to update all linke events in case the
      // short-name has changed.
      $events = Events::events($pme->rec, $pme->dbh);

      foreach ($events as $event) {
        // Last parameter "true" means to also perform string substitution
        // in the summary field of the event.
        Events::replaceCategory($event, $oldvals['Name'], $newvals['Name'], true);
      }

      // Now, we should also rename the project folder. We simply can
      // pass $newvals and $oldvals
      self::renameProjectFolder($newvals, $oldvals);


      // Fetch the old wiki-page, if any, push as new page to the wiki,
      // push a new "old" page to the wiki with a "has been renamed"
      // notice", then update the overview page

      $orchestra = Config::$opts['orchestra']; // for the name-space

      $oldname = $oldvals['Name'];
      $newname = $newvals['Name'];
      $oldpagename = $orchestra.":projekte:".$oldname;
      $newpagename = $orchestra.":projekte:".$newname;

      $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
      $dwembed = new \DWEMBED\App($wikiLocation);

      $oldpage =
        " *  ".$oldvals['Name']." wurde zu [[".$orchestra.":projekte:".$newname."]] umbenant\n";
      $newpage = $dwembed->getPage($oldpagename);
      if ($newpage) {
        // Geneate stuff if there is an old page
        $dwembed->putPage($oldpagename, $oldpage, array("sum" => "Automatic CAFEVDB page renaming",
                                                        "minor" => false));
        $dwembed->putPage($newpagename, $newpage, array("sum" => "Automatic CAFEVDB page renaming",
                                                        "minor" => false));
      }

      self::generateWikiOverview();

      // TODO: if the name changed, then change also the template, but
      // is not so important, OTOH, would just look better.
      self::nameProjectWebPages($pme->rec, $newvals['Name'], $pme->dbh);

      return true;
    }

    /**@copydoc Projects::afterInsertTrigger()
     *
     * This trigger, in particular, tries to take care to remove all
     * "side-effects" the existance of the project had. However, there
     * is some data which must not be removed automatically
     */
    public static function deleteTrigger(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
    {
      $projectId   = $pme->rec;
      $projectName = $oldvals['Name'];
      if (!$projectName) {
        $projectName = Projects::fetchName($projectId, $pme->dbh);
        $oldvals['Name'] = $projectName;
      }

      $safeMode = false;
      if ($step === 'before') {
        $payments = ProjectPayments::payments($projectId, $pme->dbh);
        if ($payments === false) {
          return false; // play safe, don't try to remove anything if we
                        // catch an error early
        }

        $safeMode = !empty($payments); // don't really remove if we have finance data
      }

      if ($step === 'after' || $safeMode) {
        // And now remove the project folder ... OC has undelete
        // functionality and we have a long-ranging backup.
        self::removeProjectFolder($oldvals);

        // Regenerate the TOC page in the wiki.
        self::generateWikiOverview();

        // Delete the page template from the public web-space. However,
        // here we only move it to the trashbin.
        $webPages = self::fetchProjectWebPages($projectId, $pme->dbh);
        foreach ($webPages as $page) {
          // ignore errors
          \OCP\Util::writeLog(Config::APP_NAME, "Attempt to delete for ".$projectId.": ".$page['ArticleId']." all ".print_r($page, true), \OCP\Util::DEBUG);

          self::deleteProjectWebPage($projectId, $page['ArticleId'], $handle);
        }

        // Remove all attached events. This really deletes stuff.
        $projectEvents = Events::projectEvents($projectId, $pme->dbh);
        foreach($projectEvents AS $event) {
          Events::deleteEvents($event, $pme->dbh);
        }
      }

      if ($safeMode) {
        mySQL::update(self::TABLE_NAME, "`Id` = $projectId", ['Disabled' => 1], $pme->dbh);
        return false; // clean-up has to be done manually later
      }

      // remaining part cannot be reached if project-payments need to be maintained, as in this case the 'before' trigger already has aborted the deletion. Only events, web-pages and wiki are deleted, and in the case of the wiki and the web-pages the respective underlying "external" services make a backup-copy of their own (respectively CAFEVDB just moves web-pages to the Redaxo "trash" category).

      // delete all extra fields and associated data.
      $projectExtra = ProjectExtra::projectExtraFields($projectId, false, $pme->dbh);
      foreach($projectExtra as $fieldInfo) {
        $fieldId = $fieldInfo['Id'];
        ProjectExtra::deleteExtraField($fieldId, $projectId, true, $pme->dbh);
      }

      // in principle, if we have potentially dangling finance data
      // (payments) hanging around, then the project structure should
      // not be deleted ... later.

      $sqlquery = 'DROP VIEW IF EXISTS `'.$projectName.'View`';
      mySQL::query($sqlquery, $pme->dbh);

      $deleteTables = [
        [ 'table' => 'Besetzungen', 'column' => 'ProjektId' ],
        [ 'table' => 'ProjectInstruments', 'column' => 'ProjectId' ],
        [ 'table' => 'ProjectWebPages', 'column' => 'ProjectId' ],
        // [ 'table' => 'ProjectExtraFields', 'column' => 'ProjectId' ], handled above
        // [ 'table' => 'ProjectEvents', 'column' => 'ProjectId' ], handled above
        ];

      $triggerResult = true;
      foreach($deleteTables as $table) {
        $query = "DELETE FROM ".$table['table']." WHERE ".$table['column']." = $projectId";
        if (mySQL::query($query, $pme->dbh) === false) {
          $triggerResult = false;
          break; // stop on error
        }
      }

      return $triggerResult;
    }

    /**Extract the year from the name (if appropriate). Return false
     * if the year is not attached to the name.
     */
    public static function yearFromName($projectName)
    {
      if (preg_match('/^(.*\D)?(\d{4})$/', $projectName, $matches) == 1) {
        $name = $matches[1];
        $year = $matches[2];
        if ($name.$year == $projectName) {
          return $year;
        }
      }
      return false;
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
    public static function extraFields($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = 'SELECT `ExtraFelder` FROM `Projekte` WHERE `Id` = '.$projectId;
      $result = mySQL::query($query, $handle);
      // Get the single line
      $line = mySQL::fetch($result) or Util::error("Couldn't fetch the result for '".$query."'");

      if ($ownConnection) {
        mySQL::close($handle);
      }

      if (Util::debugMode()) {
        print_r($line);
      }

      if ($line['ExtraFelder'] == '') {
        return array();
      } else {
        Util::debugMsg("Extras: ".$line['ExtraFelder']);
      }

      // Build an array of name - size pairs
      $tmpfields = Util::explode(',',$line['ExtraFelder']);
      if (Util::debugMode()) {
        print_r($tmpfields);
      }
      $fields = array();
      $numbers = array();
      foreach ($tmpfields as $value) {
        $value = trim($value);
        $value = Util::explode(':',$value);
        $fields[] = array('name' => $value[0],
                          'pos' => isset($value[1]) ? $value[1] : false,
                          'tooltip' =>  isset($value[2]) ? $value[2] : false);
        if (isset($value[1])) {
          $numbers[$value[1]] = true;
        }
      }

      // Add the missing field-numbers and make sure they do not
      // conflict with the explicitly specified ordering
      $fieldno = 1; // This time we start at ONE _NOT_ ZERO
      foreach ($fields as &$field) {
        if ($field['pos'] !== false) {
          continue;
        }
        while(isset($numbers[$fieldno++]));
        $field['pos'] = $fieldno;
      }

      Util::debugMsg("<<<<ProjektExtraFelder");

      return $fields;
    }

    /**Genereate the input data for the link to the CMS in order to edit
     * the project's public web articles inline.
     *
     * @todo Do something more useful in the case of an error (database
     * or CMS unavailable)
     */
    public static function projectProgram($projectId, $action)
    {
      $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
      $rex = new \Redaxo\RPC($redaxoLocation);

      /* Fetch all the data available. */
      $webPages = self::fetchProjectWebPages($projectId);
      if ($webPages === false) {
        return L::t("Unable to fetch public web pages for project id %d",
                    array($projectId));
      }
      $articleIds = array();
      foreach ($webPages as $idx => $article) {
        // this is cheap, there are only few articles attached to a project
        $articleIds[$article['ArticleId']] = $idx;
      }

      $categories = array(array('id' => Config::getValue('redaxoPreview'),
                                'name' => L::t('Preview')),
                          array('id' => Config::getValue('redaxoRehearsals'),
                                'name' => L::t('Rehearsals')),
                          array('id' => Config::getValue('redaxoArchive'),
                                'name' => L::t('Archive')),
                          array('id' => Config::getValue('redaxoTrashbin'),
                                'name' => L::t('Trashbin')));
      $detachedPages = array();
      foreach ($categories as $category) {
        // Fetch all articles and remove those already registered
        $pages = $rex->articlesByName('.*', $category['id']);
        \OCP\Util::writeLog(Config::APP_NAME, "Projects: ".$category['id'], \OCP\Util::DEBUG);
        if (is_array($pages)) {
          foreach ($pages as $idx => $article) {
            $article['CategoryName'] = $category['name'];
            $article['Linked'] = isset($articleIds[$article['ArticleId']]);
            $detachedPages[] = $article;
            \OCP\Util::writeLog(Config::APP_NAME, "Projects: ".print_r($article, true), \OCP\Util::DEBUG);
          }
        }
      }

      $tmpl = new \OCP\Template(Config::APP_NAME, 'project-web-articles');
      $tmpl->assign('projectId', $projectId);
      $tmpl->assign('projectArticles', $webPages);
      $tmpl->assign('detachedArticles', $detachedPages);
      $urlTemplate = $rex->redaxoURL('%ArticleId%', $action == 'change');
      if ($action != 'change') {
        $urlTemplate .= '&rex_version=1';
      }
      $tmpl->assign('cmsURLTemplate', $urlTemplate);
      $tmpl->assign('action', $action);
      $tmpl->assign('app', Config::APP_NAME);
      $html = $tmpl->fetchPage();
      return $html;
    }

    public static function projectActions($projectId, $projectName, $placeHolder = false, $overview = false)
    {
      $projectPaths = self::maybeCreateProjectFolder($projectId, $projectName);

      if ($placeHolder === false) {
        // Strip the 4-digit year from the end, if present
        // $placeHolder = preg_replace("/^(.*\D)(\d{4})$/", "$1", $projectName);
        $placeHolder = $projectName; // or maybe don't strip.
      }

      $control = '
<span class="project-actions-block">
  <select data-placeholder="'.$placeHolder.'"
          class="project-actions"
          title="'.Config::toolTips('project-actions').'"
          data-project-id="'.$projectId.'"
          data-project-name="'.Util::htmlEncode($projectName).'">
    <option value=""></option>
'
                     .($overview
                       ? Navigation::htmlTagsFromArray(
                         array('pre' => '<optgroup>', 'post' => '</optgroup>',
                               array('type' => 'option',
                                     'title' => Config::toolTips('project-infopage'),
                                     'value' => 'project-infopage',
                                     'name' => L::t('Project Overview')
                                 )
                           ))
                       : '')
                     .Navigation::htmlTagsFromArray(
                       array('pre' => '<optgroup>', 'post' => '</optgroup>',

                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-detailed-instrumentation'),
                                   'value' => 'detailed-instrumentation',
                                   'name' => L::t('Instrumentation')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-instrumentation-numbers'),
                                   'value' => 'project-instruments',
                                   'name' => L::t('Instrumentation Numbers')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-extra-fields'),
                                   'value' => 'project-extra',
                                   'name' => L::t('Extra Member Data')
                               )
                         ))
                     .Navigation::htmlTagsFromArray(
                       array('pre' => '<optgroup>', 'post' => '</optgroup>',
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-files'),
                                   'value' => 'project-files',
                                   'data' => array('projectFiles' => $projectPaths['project']),
                                   'name' => L::t('Project Files')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-wiki'),
                                   'value' => 'project-wiki',
                                   'data' => array(
                                     'wikiPage' => self::projectWikiLink($projectName),
                                     'wikiTitle' => L::t('Project Wiki for %s', array($projectName))
                                     ),
                                   'name' => L::t('Project Notes')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-events'),
                                   'value' => 'events',
                                   'name' => L::t('Events')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-email'),
                                   'value' => 'project-email',
                                   'name' => L::t('Em@il')
                               ),
                         ))
                     .Navigation::htmlTagsFromArray(
                       array('pre' => '<optgroup>', 'post' => '</optgroup>',
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-debit-mandates'),
                                   'value' => 'sepa-debit-mandates',
                                   'disabled' => !Config::isTreasurer(),
                                   'name' => L::t('Debit Mandates')
                               ),
                             array('type' => 'option',
                                   'title' => Config::toolTips('project-action-financial-balance'),
                                   'value' => 'profit-and-loss',
                                   'data' => array('projectFiles' => $projectPaths['balance']),
                                   'name' => L::t('Profit and Loss Account')
                               )
                         ))
                     .'
  </select>
</span>
';
      return $control;
    }

    /**Generate an option table with all participants, suitable to be
     * staffed into Navigation::selectOptions(). This is a single
     * select, only one musician may be preselected. The key is the
     * musician id. The options are meant for a single-choice select box.
     *
     * @param $projectId The id of the project to fetch the musician options from
     *
     * @param $projectName Optional project name, will be queried from
     * DB if not specified.
     *
     * @param $musicianId A pre-selected musician, defaults to none.
     *
     * @param $handle Data-base handle, new connection will be opened
     * if not specified.
     */
    public static function participantOptions($projectId, $projectName = false, $musicianId = -1, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      if ($projectName === false) {
        $projectName = self::fetchName($projectId, $handle);
      }

      $table = $projectName.'View';

      $options = array();

      // simply fetch all participants
      $query = "SELECT `Name`,`Vorname`,`MusikerId` FROM `".$table."` WHERE 1";

      $result = mySQL::query($query, $handle, true);
      while($row = mySQL::fetch($result)) {
        $key = $row['MusikerId'];
        $name = $row['Vorname'].' '.$row['Name'];
        $flags = ($key == $musicianId) ? Navigation::SELECTED : 0;
        $options[] = array('value' => $key,
                           'name' => $name,
                           'flags' => $flags);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $options;
    }

    /**Check for the existence of the project folders. Returns an array
     * of folders (balance and general files).
     */
    public static function maybeCreateProjectFolder($projectId, $projectName = false, $only = false)
    {
      $project = self::fetchProject($projectId);
      if (!$projectName) {
        $projectName = $project['Name'];
      } else if ($projectName != $project['Name']) {
        return false;
      }

      $sharedFolder   = Config::getSetting('sharedfolder','');
      $projectsFolder = Config::getSetting('projectsfolder','');
      $balanceFolder  = Config::getSetting('projectsbalancefolder','');

      $fileView = \OC\Files\Filesystem::getView();

      $paths = array('project' => '/'.$sharedFolder.'/'.$projectsFolder,
                     'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder);
      $returnPaths = array();
      foreach($paths as $key => $path) {
        if ($only && $key != $only) {
          continue;
        }
        if (!$fileView->is_dir($path)) {
          $fileView->mkdir($path);
        }
        $path .= "/".$project['Jahr'];
        if (!$fileView->is_dir($path)) {
          $fileView->mkdir($path);
        }
        $path .= "/".$project['Name'];
        if (!$fileView->is_dir($path)) {
          $fileView->mkdir($path);
        }
        $returnPaths[$key] = $path;
      }
      return $returnPaths;
    }

    public static function removeProjectFolder($oldProject)
    {
      $sharedFolder = Config::getSetting('sharedfolder','');
      $projectsFolder = Config::getSetting('projectsfolder','');
      $balanceFolder = Config::getSetting('projectsbalancefolder','');

      $prefixPath = array(
        'project' => '/'.$sharedFolder.'/'.$projectsFolder.'/',
        'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder."/",
        );

      $fileView = \OC\Files\Filesystem::getView();

      foreach($prefixPath as $key => $prefix) {

        $oldPath = $prefix.$oldProject['Jahr']."/".$oldProject['Name'];

        if ($fileView->is_dir($oldPath)) {
          if (!$fileView->deleteAll($oldPath)) {
            return false;
          }
        }
      }

      return true;
    }

    public static function renameProjectFolder($newProject, $oldProject)
    {
      $sharedFolder = Config::getSetting('sharedfolder','');
      $projectsFolder = Config::getSetting('projectsfolder','');
      $balanceFolder = Config::getSetting('projectsbalancefolder','');

      $prefixPath = array(
        'project' => '/'.$sharedFolder.'/'.$projectsFolder.'/',
        'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder."/",
        );

      $fileView = \OC\Files\Filesystem::getView();

      $returnPaths = array();
      foreach($prefixPath as $key => $prefix) {

        $oldPath = $prefix.$oldProject['Jahr']."/".$oldProject['Name'];
        $newPrefixPath = $prefix.$newProject['Jahr'];

        $newPath = $newPrefixPath.'/'.$newProject['Name'];

        if ($fileView->is_dir($oldPath)) {
          // If the year has changed it may be necessary to create a new
          // directory.
          if (!$fileView->is_dir($newPrefixPath)) {
            if (!$fileView->mkdir($newPrefixPath)) {
              return false;
            }
          }
          if (!$fileView->rename($oldPath, $newPath)) {
            return false;
          }
          $returnPaths[$key] = $newPath;
        } else {
          // Otherwise there is nothing to move; we simply create the new directory.
          $returnPaths = array_merge($returnPaths,
                                     self::maybeCreateProjectFolder($projectId, $projectName, $only = $key));
        }
      }

      return $returnPaths;
    }

    /**Gather events, instrumentation numbers and the wiki-page in a
     * form-set for inclusion into some popups etc.
     */
    public static function projectToolbox($projectId, $projectName, $value = false, $eventSelect = array())
    {
      $toolbox = Navigation::htmlTagsFromArray(
        array(
          'pre' => ('<fieldset class="projectToolbox" '.
                    'data-project-id="'.$projectId.'" '.
                    'data-project-name="'.Util::htmlEncode($projectName).'">'),
          'post' => '</fieldset>',
          array('type' => 'button',
                'title' => Config::toolTips('project-action-wiki'),
                'data' => array(
                  'wikiPage' => self::projectWikiLink($projectName),
                  'wikiTitle' => L::t('Project Wiki for %s', array($projectName))
                  ),
                'class' => 'project-wiki tooltip-top',
                'value' => 'project-wiki',
                'name' => L::t('Project Notes')
            ),
          array('type' => 'button',
                'title' => Config::toolTips('project-action-events'),
                'class' => 'events tooltip-top',
                'value' => 'events',
                'name' => L::t('Events')
            ),
          array('type' => 'button',
                'title' => Config::toolTips('project-action-email'),
                'class' => 'project-email tooltip-top',
                'value' => 'project-email',
                'name' => L::t('Em@il')
            ),
          array('type' => 'button',
                'title' => Config::toolTips('project-action-instrumentation-numbers'),
                'class' => 'project-instruments tooltip-top',
                'value' => 'project-instruments',
                'name' => L::t('Instrumentation Numbers')
            )
          ));
      return '<div class="projectToolbox">
'.$toolbox.'
</div>
';
    }

    /**Fetch all project data for the given id or name.
     */
    public static function fetchById($id, $handle = false)
    {
      $projects = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT *";
      $query .= " FROM `Projekte`";
      $query .= " WHERE `Id` = ".$id;

      $result = mySQL::query($query, $handle, true);
      $project = false;
      if ($result !== false && mySQL::numRows($result) == 1) {
        $project = mySQL::fetch($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $project;
    }

    /**Fetch the list of projects from the data base as a short id=>name
     * field.
     *
     * @param[in] mixed $handle Database handle, maybe false
     *
     * @param[in] bool $year Whether to include the year.
     *
     * @param[in] bool $newestFirst Whether to sort for most recent
     * years first.
     *
     * @return array('Id', 'Name', 'Jahr');
     *
     */
    public static function fetchProjects($handle = false, $year = false, $newestFirst = false)
    {
      $projects = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT `Id`,`Name`".($year === true ? ",`Jahr`" : "");
      $query .= " FROM `Projekte` WHERE 1 ORDER BY ";
      if ($year === true) {
        if ($newestFirst) {
          $query .= "`Jahr` DESC, `Name` ASC";
        } else {
          $query .= "`Jahr` ASC, `Name` ASC";
        }
      } else {
        $query .= "`Name` ASC";
      }
      $result = mySQL::query($query, $handle, true);
      if ($year === false) {
        while ($line = mySQL::fetch($result)) {
          $projects[$line['Id']] = $line['Name'];
        }
      } else {
        while ($line = mySQL::fetch($result)) {
          $projects[$line['Id']] = array('Name' => $line['Name'], 'Jahr' => $line['Jahr']);
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $projects;
    }

    /**Fetch the project id for the given web-article id,
     *
     * @return The project id or false if no project found.
     */
    public static function fetchWebPageProjects($articleId, $handle = false)
    {
      $projects = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT * FROM `ProjectWebPages` WHERE ";
      $query .= " `ArticleId` = ".$articleId;
      $query .= " ORDER BY `ProjectId` ASC, `ArticleId` ASC";

      \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query, \OCP\Util::DEBUG);

      $webPages = array();
      $result = mySQL::query($query, $handle, true);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query." failed", \OCP\Util::DEBUG);
        return false;
      }
      while ($line = mySQL::fetch($result)) {
        $projects[] = $line['ProjectId'];
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $projects;
    }

    /**Fetch the ids of the public web pages related to this
     * project. Often there will be only one, but this need not be the
     * case.
     */
    public static function fetchProjectWebPages($projectId, $handle = false)
    {
      $projects = array();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT * FROM `ProjectWebPages` WHERE 1";
      if ($projectId > 0) {
        $query .= " AND `ProjectId` = ".$projectId;
      }
      $query .= " ORDER BY `ProjectId` ASC, `ArticleId` ASC";

      \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query, \OCP\Util::DEBUG);

      $webPages = array();
      $result = mySQL::query($query, $handle, true);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query." failed", \OCP\Util::DEBUG);
        return false;
      }
      while ($line = mySQL::fetch($result)) {
        $webPages[] = $line;
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      \OCP\Util::writeLog(Config::APP_NAME, "WebPages for ".$projectId." ".print_r($webPages, true), \OCP\Util::DEBUG);

      return $webPages;
    }

    /**Create and add a new web-page. The first one will have the name
     * of the project, subsequent one have a number attached like
     * Tango2014-5.
     *
     * @param $projectId Id of the project
     *
     * @param $kind One of 'concert' or 'rehearsals'
     *
     * @param $handle Optional active data-base handle.
     */
    public static function createProjectWebPage($projectId, $kind = 'concert', $handle = false)
    {
      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $projectName = self::fetchName($projectId, $handle);
      if ($projectName === false) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      switch ($kind) {
      case 'rehearsals':
        $prefix = L::t('Rehearsals').' ';
        $category = Config::getValue('redaxoRehearsals');
        $module = Config::getValue('redaxoRehearsalsModule');
        break;
      default:
        // Don't care about the archive, new pages go to preview, and the
        // id will be unique even in case of a name clash
        $prefix = '';
        $category = Config::getValue('redaxoPreview');
        $module = Config::getValue('redaxoConcertModule');
        break;
      }

      // General page template
      $pageTemplate = Config::getValue('redaxoTemplate');

      $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
      $rex = new \Redaxo\RPC($redaxoLocation);

      $pageName = $prefix.$projectName;
      $articles = $rex->articlesByName($pageName.'(-[0-9]+)?', $category);
      if (!is_array($articles)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $names = array();
      foreach ($articles as $article) {
        $names[] = $article['ArticleName'];
      }
      if (array_search($pageName, $names) !== false) {
        for ($i = 1; ; ++$i) {
          if (array_search($pageName.'-'.$i, $names) === false) {
            // this will teminate ;)
            $pageName = $pageName.'-'.$i;
            break;
          }
        }
      }

      $article = $rex->addArticle($pageName, $category, $pageTemplate);

      if ($article === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Error generating web page template", \OCP\Util::DEBUG);
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      // just forget about the rest, we can't help it anyway if the
      // names are not unique
      $article = $article[0];

      // insert into the db table to form the link
      if (self::attachProjectWebPage($projectId, $article, $handle) === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Error attaching web page template", \OCP\Util::DEBUG);
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $rex->addArticleBlock($article['ArticleId'], $module);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $article;
    }

    /**Delete a web page. This is implemented by moving the page to the
     * Trashbin category, leaving the real cleanup to a human being.
     */
    public static function deleteProjectWebPage($projectId, $articleId, $handle = false)
    {
      if (self::detachProjectWebPage($projectId, $articleId) === false) {
        return false;
      }
      $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
      $rex = new \Redaxo\RPC($redaxoLocation);

      $trashCategory = Config::getValue('redaxoTrashbin');
      $result = $rex->moveArticle($articleId, $trashCategory);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Failed moving ".$articleId." to ".$trashCategory, \OCP\Util::DEBUG);
      }
      return $result;
    }

    /**Detach a web page, but do not delete it. Meant as utility routine
     * for the UI (in order to correct wrong associations).
     */
    public static function detachProjectWebPage($projectId, $articleId, $handle = false)
    {
      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "DELETE IGNORE FROM `ProjectWebPages`
 WHERE `ProjectId` = ".$projectId." AND `ArticleId` = ". $articleId;
      $result = mySQL::query($query, $handle);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query." failed", \OCP\Util::DEBUG);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Attach an existing web page to the project.
     *
     * @param $projectId Project Id.
     *
     * @param $article Article array as returned from $rex->articlesByName().
     *
     * @param $handle mySQL handle.
     *
     */

    public static function attachProjectWebPage($projectId, $article, $handle = false)
    {
      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "INSERT INTO `ProjectWebPages`
 (`ProjectId`, `ArticleId`, `ArticleName`, `CategoryId`, `Priority`) VALUES(".
        $projectId.",".
        $article['ArticleId'].", ".
        "'".$article['ArticleName']."', ".
        $article['CategoryId'].", ".
        $article['Priority'].")
   ON DUPLICATE KEY UPDATE ".
        "`ArticleName` = '".$article['ArticleName']."', ".
        "`CategoryId` = ".$article['CategoryId'].", ".
        "`Priority` = ".$article['Priority'];

      $result = mySQL::query($query, $handle);
      if ($ownConnection) {
        mySQL::close($handle);
      }

      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME, "Query ".$query." failed", \OCP\Util::DEBUG);
      } else {
        // Try to remove from trashbin, if appropriate.
        $trashCategory = Config::getValue('redaxoTrashbin');
        if ($article['CategoryId'] == $trashCategory) {
          if (stristr($article['ArticleName'], L::t('Rehearsals')) !== false) {
            $destinationCategory = Config::getValue('redaxoRehearsals');
          } else {
            $destinationCategory = Config::getValue('redaxoPreview');
          }
          $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
          $rex = new \Redaxo\RPC($redaxoLocation);
          $articleId = $article['ArticleId'];
          $result = $rex->moveArticle($articleId, $destinationCategory);
          if ($result === false) {
            \OCP\Util::writeLog(Config::APP_NAME, "Failed moving ".$articleId." to ".$destinationCategory, \OCP\Util::DEBUG);
          }
        }
      }

      return $result !== false;
    }

    /**Set the name of all registered web-pages to the canonical name,
     * project name given.
     */
    public static function nameProjectWebPages($projectId, $projectName = false, $handle = false)
    {
      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      /* Fetch the name if necessary */
      if ($projectName === false) {
        $projectName = self::fetchName($projectId, $handle);
      }
      if ($projectName === false) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      /* Fetch all the data available. */
      $webPages = self::fetchProjectWebPages($projectId);
      if ($webPages === false) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
      $rex = new \Redaxo\RPC($redaxoLocation);

      $concertNr = 0;
      $rehearsalNr = 0; // should stay at zero
      foreach ($webPages as $article) {
        if (stristr($article['ArticleName'], L::t('Rehearsals')) !== false) {
          $newName = L::t('Rehearsals').' '.$projectName;
          if ($rehearsalNr > 0) {
            $newName .= '-'.$rehearsalNr;
          }
          ++$rehearsalNr;
        } else {
          $newName = $projectName;
          if ($concertNr > 0) {
            $newName .= '-'.$concertNr;
          }
          ++$concertNr;
        }
        if ($rex->setArticleName($article['ArticleId'], $newName)) {
          // if successful then also update the date-base entry
          $query = "UPDATE IGNORE `ProjectWebPages`
    SET `ArticleName` = '".$newName."'
    WHERE `ArticleId` = ".$article['ArticleId'];
          $result = mySQL::query($query, $handle);
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    /**Search through the list of all projects and attach those with a
     * matching name. Something which should go to the "expert"
     * controls.
     */
    public static function attachMatchingWebPages($projectId, $handle = false)
    {
      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $projectName = self::fetchName($projectId, $handle);
      if ($projectName === false) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $previewCat    = Config::getValue('redaxoPreview');
      $archiveCat    = Config::getValue('redaxoArchive');
      $rehearsalsCat = Config::getValue('redaxoRehearsals');

      $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
      $rex = new \Redaxo\RPC($redaxoLocation);

      $cntRe = '(?:-[0-9]+)?';

      $preview = $rex->articlesByName($projectName.$cntRe, $previewCat);
      if (!is_array($preview)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }
      $archive = $rex->articlesByName($projectName.$cntRe, $archiveCat);
      if (!is_array($archive)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }
      $rehearsals = $rex->articlesByName(L::t('Rehearsals').' '.$projectName.$cntRe, $rehearsalsCat);
      if (!is_array($rehearsals)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
        return false;
      }

      $articles = array_merge($preview, $archive, $rehearsals);

      //\OCP\Util::writeLog(Config::APP_NAME, "Web pages for ".$projectName.": ".print_r($articles, true), \OCP\Util::DEBUG);

      foreach ($articles as $article) {
        // ignore any error
        self::attachProjectWebPage($projectId, $article, $handle);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    /**Fetch minimum and maximum project years from the Projekte table.
     */
    public static function fetchYearRange($handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $yearCol = "Jahr";
      $query = "SELECT MIN(`$yearCol`),MAX(`$yearCol`) FROM `Projekte` WHERE 1";
      $result = mySQL::query($query, $handle);
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
        $yearRange = array();
        foreach ($row as $key => $value) {
          $yearRange[] = $value;
        }
        return array_combine(array('min', 'max'), $yearRange);
      }
      return false;
    }

    /**Fetch the project identified by $projectId.
     */
    public static function fetchProject($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT * FROM `Projekte` WHERE `Id` = $projectId";
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

    /**Fetch the count of missing musicians per voice. For this to work
     * the instrumentation number have to be present in the respective
     * table, of course.
     */
    public static function fetchMissingInstrumentation($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT
  i.Instrument,
  pi.Quantity AS Required,
  COUNT(mi.Id) AS Registered,
  COUNT(b.Id) AS Confirmed
  FROM `".self::INSTRUMENTATION."` pi
LEFT JOIN `".self::REGISTERED."` mi
  ON mi.ProjectId = pi.ProjectId AND mi.InstrumentId = pi.InstrumentId
LEFT JOIN `Besetzungen` b
  ON b.Id = mi.InstrumentationId AND b.Anmeldung = 1
LEFT JOIN `".self::INSTRUMENTS."` i
  ON i.Id = pi.InstrumentId
WHERE pi.ProjectId = $projectId
GROUP BY pi.ProjectId, pi.InstrumentId
ORDER BY i.Sortierung ASC";

      $missing = array();
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        while ($row = mySQL::fetch($qResult)) {
          $missing[$row['Instrument']] = [
            'Registered' => $row['Required'] - $row['Registered'],
            'Confirmed' => $row['Required'] - $row['Confirmed']
            ];
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }


      return $missing;
    }

    /**Create a HTML table with the missing musicians. */
    public static function missingInstrumentationTable($projectId, $handle = false)
    {
      $output = '';
      $numbers = self::fetchMissingInstrumentation($projectId, $handle);

      //error_log(print_r($numbers, true));

      $missing = array_filter($numbers, function ($val) {
          return $val['Registered'] > 0 || $val['Confirmed'] > 0;
        });
      if (count($missing) > 0) {
        $output .= '
<div class="missing-musicians"
     id="missing-musicians-block"
     title="'.L::t("Missing Musicians").'">
  <span class="missing-musicians-title">'.L::t("Missing Musicians").'</span>
  <table class="missing-musicians">
    <tr>
      <th>'.L::t("Instrument").'</th>
      <th>'.L::t("Registered").'</th>
      <th>'.L::t("Confirmed").'</th>
    </tr>
';
        $cnt = 0;
        foreach ($missing as $instrument => $deficit) {
          if ($deficit['Confirmed'] <= 0) {
            continue;
          }
          $output .= '    <tr class="row-'.($cnt%2).'"><td class="instrument">'.$instrument.'</td><td class="deficit registered">'.$deficit['Registered'].'</td><td class="deficit confirmed">'.$deficit['Confirmed'].'</td></tr>'."\n";
          $cnt++;
        }
        $output .= '  </table>
  </div>';
      }
      return $output;
    }

    /**Fetch the list of needed instruments. */
    public static function fetchInstrumentation($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT
 GROUP_CONCAT(pi.InstrumentId ORDER BY i.Sortierung ASC) AS InstrumentIds,
 GROUP_CONCAT(i.Instrument ORDER BY i.Sortierung ASC) AS Instruments,
 GROUP_CONCAT(pi.Quantity ORDER BY i.Sortierung ASC) AS Quantity
FROM ".self::INSTRUMENTATION." pi
LEFT JOIN Instrumente i
  ON pi.InstrumentId = i.Id
WHERE pi.`ProjectId` = $projectId";
      $result = mySQL::query($query, $handle);

      //throw new \Exception($query);

      $instrumentation = false;
      $row = false;
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
        $instrumentation = [
          'InstrumentIds' => explode(',', $row['InstrumentIds']),
          'Instruments' => explode(',', $row['Instruments']),
          'Quantity' => explode(',', $row['Quantity']),
          ];
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $instrumentation;
    }

    /** Fetch the project-fees for the given project.
     */
    public static function fetchFees($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $columns = array('Unkostenbeitrag',
                       'Anzahlung');

      $query = 'SELECT `'.implode('`,`', $columns).'` FROM `Projekte` WHERE `Id` = '.$projectId;
      $result = mySQL::query($query, $handle);

      $row = false;
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      if ($row) {
        return array('fee' => floatval($row['Unkostenbeitrag']),
                     'deposit' => floatval($row['Anzahlung']));
      } else {
        return false;
      }
    }

    /**Return true if this project has a potential need for debit
     * mandates.
     */
    public static function needDebitMandates($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $memberTableId = Config::getValue('memberTableId');
      $result = $projectId == $memberTableId;

      if (!$result) {
        $fees = self::fetchFees($projectId, $handle);
        $result = $fees !== false;
      }

      if (!$result) {
        $query = "SELECT GREATEST(0,MAX(Unkostenbeitrag)) as MaximumFee
 FROM `Besetzungen` WHERE `ProjektId` = $projectId";
        $qres = mySQL::query($query, $handle);

        $max = 0;
        if ($qres !== false && mySQL::numRows($qres) == 1) {
          $row = mySQL::fetch($qres);
          if (isset($row['MaximumFee'])) {
            $max = floatval($row['MaximumFee']);
          }
        }
        $result = $max > 0;
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /** Fetch the project-name name corresponding to $projectId.
     */
    public static function fetchName($projectId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = 'SELECT `Name` FROM `Projekte` WHERE `Id` = '.$projectId;
      $result = mySQL::query($query, $handle);

      $row = false;
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $row && isset($row['Name']) ? $row['Name'] : false;
    }

    /** Fetch the project-id corresponding to $projectName
     */
    public static function fetchId($projectName, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT `Id` FROM `Projekte` WHERE `Name` = '".$projectName."'";
      $result = mySQL::query($query, $handle);

      $row = false;
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $row && isset($row['Id']) ? $row['Id'] : false;
    }

    /**Returns an associative array which describes the project view:
     * columns names, alias names, underlying table, type of join. The
     * ordering of the columns here is the ordering of the columns in
     * the view. The key is the alias name of the column in the view.
     *
     * This structure is also used in the PME-stuff in
     * detailed-instrumentation.php to group the fields of the view
     * s.t. update queries can be split into updates for single tables
     * (either Besetzungen or Musiker). mySQL allows write-through
     * through certain views, but only if the target is a single table.
     */
    public static function viewStructure($projectId, $extraFields)
    {
      $viewStructure1 = array(
        // Principal key is still the key from the Besetzungen ==
        // Instrumentation table.
        'Id' => array('table' => 'Besetzungen',
                      'tablename' => 'b',
                      'column' => true,
                      'key' => true,
                      'join' => array('type' => 'INNER')),

        'MusikerId' => array(
          'table' => 'Musiker',
          'tablename' => 'm',
          'column' => 'Id',
          'key' => true,
          'join' => array(
            'type' => 'INNER',
            'condition' => (
              'm.`Id` = b.`MusikerId` '.
              'AND '.
              $projectId.' = b.`ProjektId`')
            )),

        'ProjectId' => array('table' => 'b',
                             'column' => 'ProjektId',
                             'join' => array('type' => 'INNER')),

        'Projects' => array(
          'table' => 'Besetzungen',
          'tablename' => 'b2',
          'column' => "GROUP_CONCAT(DISTINCT p.`Name` ORDER BY p.`Name` ASC SEPARATOR ',')",
          'verbatim' => true,
          'join' => array(
            'type' => 'INNER',
            'condition' => 'm.`Id` = b2.`MusikerId`
  LEFT JOIN `Projekte` p
  ON b2.`ProjektId` = p.`Id`'
            ),
          ),

        'ProjectCount' => array(
          'tablename' => 'b2',
          'column' => 'COUNT(DISTINCT p.Id)',
          'verbatim' => true),

        'MusicianInstrumentKey' => array(
          'table' => 'MusicianInstruments',
          'tablename' => 'mi',
          'key' => true,
          'column' => "GROUP_CONCAT(DISTINCT mi.`Id` ORDER BY i2.`Sortierung` ASC SEPARATOR ',')",
          'verbatim' => true,
          'join' => array(
            'type' => 'LEFT',
            'condition' => 'mi.`MusicianId` = b.`MusikerId`'
            )
          ),

        'MusicianInstrumentId' => array(
          'table' => 'Instrumente',
          'tablename' => 'i2',
          'column' => "GROUP_CONCAT(DISTINCT i2.`Id` ORDER BY i2.`Sortierung` ASC SEPARATOR ',')",
          'verbatim' => true,
          'join' => array(
            'type' => 'LEFT',
            'condition' => 'mi.`InstrumentId` = i2.`Id`',
            ),
          ),

        'MusicianInstrument' => array(
          'table' => 'i2',
          'column' => "GROUP_CONCAT(DISTINCT i2.`Instrument` ORDER BY i2.`Sortierung` ASC SEPARATOR ',')",
          'verbatim' => true,
          'join' => array('type' => 'LEFT'),
          ),

        'MusicianInstrumentCount' => array(
          'table' => 'i2',
          'column' => "COUNT(DISTINCT i2.`Id`)",
          'verbatim' => true,
          'join' => array('type' => 'LEFT'),
          ),

        'ProjectInstrumentKey' => array(
          'table' => 'ProjectInstruments',
          'tablename' => 'pi',
          'key' => true,
          'column' => 'Id',
          'join' => array(
            'type' =>'LEFT',
            'condition' => 'pi.`InstrumentationId` = b.`Id`'
            )
          ),

        'ProjectInstrumentId' => array(
          'table' => 'pi',
          'column' => 'InstrumentId',
          'join' => array('type' =>'LEFT'),
          ),

        'Voice' => [
          'table' => 'pi',
          'column' => true,
          'join' => array('type' => 'LEFT'),
          ],

        'SectionLeader' => [
          'table' => 'pi',
          'column' => true,
          'join' => array('type' => 'LEFT'),
          ],

        'ProjectInstrument' => array(
          'table' => 'Instrumente',
          'tablename' => 'i',
          'column' => 'Instrument',
          'join' => array(
            'type' =>'LEFT',
            'condition' => 'pi.`InstrumentId` = i.`Id`'
            )
          ),

        'Familie' => array(
          'table' => 'Instrumente',
          'tablename' => 'i',
          'column' => true,
          /* 'join' => array( */
          /*   'type' => 'LEFT', */
          /*   'condition' => ('b.`Instrument` = i.`Instrument`') */
          /*   ) */
          ),
        'Sortierung' => array('table' => 'i',
                              'column' => true,
                              'join' => array('type' => 'LEFT')),

        'Anmeldung' => array('table' => 'b',
                             'column' => true,
                             'join' => array('type' => 'INNER')),

        'Disabled' => array('table' => 'b',
                            'column' => true,
                             'join' => array('type' => 'INNER')),

        'Name' => array('table' => 'm',
                        'column' => true,
                        'join' => array('type' => 'INNER')),
        'Vorname' => array('table' => 'm',
                           'column' => true,
                           'join' => array('type' => 'INNER')),
        'Email' => array('table' => 'm',
                         'column' => true,
                         'join' => array('type' => 'INNER')),
        'MobilePhone' => array('table' => 'm',
                               'column' => true,
                               'join' => array('type' => 'INNER')),
        'FixedLinePhone' => array('table' => 'm',
                                  'column' => true,
                                  'join' => array('type' => 'INNER')),
        'Strasse' => array('table' => 'm',
                           'column' => true,
                           'join' => array('type' => 'INNER')),
        'Postleitzahl' => array('table' => 'm',
                                'column' => true,
                                'join' => array('type' => 'INNER')),
        'Stadt' => array('table' => 'm',
                         'column' => true,
                         'join' => array('type' => 'INNER')),
        'Land' => array('table' => 'm',
                        'column' => true,
                        'join' => array('type' => 'INNER')),

        'Unkostenbeitrag' => array('table' => 'b',
                                   'column' => true,
                                   'join' => array('type' => 'INNER')),
        'Anzahlung' => array('table' => 'b',
                             'column' => true,
                             'join' => array('type' => 'INNER')),
        'AmountPaid' => array('table' => 'ProjectPayments',
                              'tablename' => 'f',
                              'column' => 'IFNULL(SUM(IF(b.Id = b2.Id, f.Amount, 0)), 0)
/
IF(i2.Id IS NULL, 1, COUNT(DISTINCT i2.Id))',
                              'verbatim' => true,
                              'join' => array(
                                'type' =>'LEFT',
                                'condition' => 'f.`InstrumentationId` = b.`Id`'
                                )),
        'PaidCurrentYear' => array(
          'tablename' => 'f',
          'column' => 'IFNULL(SUM(IF(b.Id = b2.Id AND YEAR(NOW()) = YEAR(f.DateOfReceipt), f.Amount, 0)), 0)
/
IF(i2.Id IS NULL, 1, COUNT(DISTINCT i2.Id))',
          'verbatim' => true,
          'join' => array('type' => 'INNER')
          ),
        'Lastschrift' => array('table' => 'b',
                               'column' => true,
                               'join' => array('type' => 'INNER')),
        'ProjectRemarks' => array('table' => 'b',
                                  'column' => 'Bemerkungen',
                                  'join' => array('type' => 'INNER'))
        );

      $extraColumns = array();
      // new code
      $idx = 0;
      foreach($extraFields as $field) {
        $name = $field['Name'];
        $extraColumns[$name.'Id'] = array('table' => 'fd'.$idx, // field-data
                                          'column' => 'Id',
                                          'key' => true,
                                          'join' => array('type' => 'INNER'));
        $short = 'fd'.$idx;
        $extraColumns[$name] = array(
          'table' => 'ProjectExtraFieldsData',
          'tablename' => $short,
          'column' => 'FieldValue',
          'join' => array(
            'type' => 'LEFT',
            'condition' =>
            $short.'.`BesetzungenId` = b.`Id`'.
            ' AND '.
            $short.'.`FieldId` = '.$field['Id']
            )
          );

        // and don't forget:
        ++$idx;
      }

      $viewStructure2 = array(
        'Sprachpräferenz' => array('table' => 'm',
                                   'column' => true,
                                   'join' => array('type' => 'INNER')),
        'Geburtstag' => array('table' => 'm',
                              'column' => true,
                              'join' => array('type' => 'INNER')),
        'MemberStatus' => array('table' => 'm',
                                'column' => true,
                                'join' => array('type' => 'INNER')),
        'Remarks' => array('table' => 'm',
                           'column' => true,
                           'join' => array('type' => 'INNER')),

        'Portrait' => array(
          'table' => 'ImageData',
          'tablename' => 'img',
          'column' => "CONCAT('data:',img.`MimeType`,';base64,',img.`Data`)",
          'verbatim' => true,
          'join' => array(
            'type' => 'LEFT',
            'condition' => (
              'img.`ItemId` = b.`MusikerId` '.
              'AND '.
              "img.`ItemTable` = 'Musiker'")
            )),

        'UUID' => array('table' => 'm',
                        'column' => true,
                        'join' => array('type' => 'INNER')),

        'Aktualisiert' => array('table' => 'm',
                                'column' => true,
                                'join' => array('type' => 'INNER')),


        );

      $viewStructure = array_merge($viewStructure1, $extraColumns, $viewStructure2);
      $tableAlias = array();
      foreach($viewStructure as $column => $data) {
        // here table and tablename neeed to be defined correctly, if
        // both are given.
        if (isset($data['table']) && isset($data['tablename'])) {
          $tableAlias[$data['tablename']] = $data['table'];
        }
      }
      foreach($viewStructure as $column => &$data) {
        if (!isset($data['key'])) {
          $data['key'] = false;
        }
        isset($data['table']) || $data['table'] = $data['tablename'];
        $table = $data['table'];
        if (isset($tableAlias[$table])) {
          // switch, this is the alias
          $data['table'] = $tableAlias[$table];
          $data['tablename'] = $table;
        }
        isset($data['tablename']) || $data['tablename'] = $data['table'];
      }

      //error_log(print_r($tableAlias, true));
      //error_log(print_r($viewStructure, true));

      return $viewStructure;
    }

    // Create a sensibly sorted view, fit for being exported via
    // phpmyadmin. Take all extra-fields into account, add them at end.
    public static function createView($projectId, $projectName = false, $handle = false)
    {
      Util::debugMsg(">>>> ProjektCreateView");

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      if (!$projectName) {
        // Get the name
        $projectName = self::fetchName($projectId, $handle);
      }

      // Fetch the extra-fields
      //$extra = self::extraFields($projectId, $handle);
      $extra = ProjectExtra::projectExtraFields($projectId, true, $handle);

      //error_log(print_r($extra, true));

      $structure = self::viewStructure($projectId, $extra);
      $sqlSelect = mySQL::generateJoinSelect($structure);

      $groupBy = 'GROUP BY b.`Id`, pi.`InstrumentId`
';

      // Force a sensible default sorting:
      // 1: sort on the natural orchestral ordering defined in Instrumente
      // 2: sort (reverse) on the Stimmfuehrer attribute
      // 3: sort on the sur-name
      // 4: sort on the pre-name
      $sqlSort = 'ORDER BY i.`Sortierung` ASC,
 pi.`Voice` ASC,
 pi.`SectionLeader` DESC,
 m.`Name` ASC,
 m.`Vorname` ASC';

      $sqlQuery = "CREATE OR REPLACE VIEW `".$projectName."View` AS\n"
        .$sqlSelect
        .$groupBy
        .$sqlSort;

      \OCP\Util::writeLog(Config::APP_NAME, __METHOD__.": ".$sqlQuery, \OCP\Util::DEBUG);
      $result = mySQL::query($sqlQuery, $handle);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': mySQL error: '.mySQL::error($handle).' query '.$sqlQuery,
                            \OCP\Util::ERROR);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    public static function flyerImageLink($projectId, $action = 'display', $timeStamp = '')
    {
      switch ($action) {
      case 'add':
        return L::t("Flyers can only be added to existing projects, please add the new
project without a flyer first.");
      case 'display':
        $div = ''
          .'<div class="photo"><img class="cafevdb_inline_image flyer zoomable" src="'
          .\OCP\Util::linkTo('cafevdb', 'inlineimage.php').'?ItemId='.$projectId.'&ImageItemTable=Projekte&ImageSize=1200&PlaceHolder='.self::IMAGE_PLACEHOLDER.'&TimeStamp='.$timeStamp
          .'" '
          .'title="Flyer, if available" /></div>';
        return $div;
      case 'change':
        $imagearea = ''
          .'<div id="project_flyer_upload">
  <div class="tip project_flyer propertycontainer" id="cafevdb_inline_image_wrapper" title="'
        .L::t("Drop image to upload (max %s)", array(\OCP\Util::humanFileSize(Util::maxUploadSize()))).'"'
           .' data-element="PHOTO">
    <ul id="phototools" class="transparent hidden contacts_property">
      <li><a class="svg delete" title="'.L::t("Delete current flyer").'"></a></li>
      <li><a class="svg edit" title="'.L::t("Edit current flyer").'"></a></li>
      <li><a class="svg upload" title="'.L::t("Upload new flyer").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.L::t("Select image from ownCloud").'"></a></li>
    </ul>
  </div>
</div> <!-- project_flyer -->
';

        return $imagearea;
      default:
        return L::t("Internal error, don't know what to do concerning project-flyers in the given context.");
      }
    }

    public static function projectWikiLink($name)
    {
      Config::init();
      $orchestra = Config::$opts['orchestra'];

      return $orchestra.":projekte:".$name;
    }

    /** Generate an automated overview. Actually, the orchestra-title
     * should be made configurable.
     */
    public static function generateWikiOverview($handle = false)
    {
/*
  ====== Projekte der Camerata Academica Freiburg e.V. ======

  ==== 2011 ====
  * [[Auvergne2011|Auvergne]]
  * [[Weihnachten2011]]

  ==== 2012 ====
  * [[Listenpunkt]]
  * [[Blah]]

  ==== 2013 ====
  * [[Listenpunkt]]
  */
      $orchestra = Config::$opts['orchestra']; // for the name-space

      $projects = self::fetchProjects(false, true);

      $page = "====== Projekte der Camerata Academica Freiburg e.V. ======\n\n";

      $year = -1;
      foreach($projects as $id => $row) {
        if ($row['Jahr'] != $year) {
          $year = $row['Jahr'];
          $page .= "\n==== ".$year."====\n";
        }
        $name = $row['Name'];

        $matches = false;
        if (preg_match('/^(.*\D)?(\d{4})$/', $name, $matches) == 1) {
          $bareName = $matches[1];
          //$projectYear = $matches[2];
        } else {
          $bareName = $name;
        }

        // A page is tagged with the project name; if this ever should
        // be changed (which is possible), the change-trigger should
        // create a new page as coppy from the old one and change the
        // text of the old one to contain a link to the new page.

        $page .= "  * [[".self::projectWikiLink($name)."|".$bareName."]]\n";
      }

      $pagename = self::projectWikiLink('projekte');

      $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
      $dwembed = new \DWEMBED\App($wikiLocation);
      $dwembed->putPage($pagename, $page,
                        array("sum" => "Automatic CAFEVDB synchronization",
                              "minor" => true));

    }

    /**Generate an almost empty project page. This spares people the
     * need to click on "new page".
     *
     * - We insert a proper title heading
     *
     * - We insert a sub-title "Contacts"
     *
     * - We insert a sub-title "Financial Arrangements"
     *
     * - We insert a sub-title "Location"
     */
    public static function generateProjectWikiPage($projectId, $projectName, $handle)
    {
      $orchestra = Config::$opts['orchestra']; // for the name-space

      $page = L::t('====== Project %s ======

===== Forword =====

This wiki-page is useful to store selected project related
informations in comfortable and structured form. This can be useful
for "permant information" like details about supplementary fees,
contact informations and the like. In particular, this page could be
helpful to reduce unnecessary data-digging in our email box.

===== Contacts =====
Please add any relevant email and mail-adresses here. Please use the wiki-syntax
* [[foobar@important.com|Mister Universe]]

===== Financial Arrangements =====
Please add any special financial arrangements here. For example:
single-room fees, double-roome fees. Please consider using an
unordered list for this like so:
  * single room fee: 3000€
  * double room fee: 6000€
  * supplemenrary fee for Cello-players: 1500€

===== Location =====
Whatever.',
                   array($projectName));

      $pagename = self::projectWikiLink($projectName);

      $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
      $dwembed = new \DWEMBED\App($wikiLocation);
      $dwembed->putPage($pagename, $page,
                        array("sum" => "Automatic CAFEVDB synchronization",
                              "minor" => true));

    }

  }; // class Projects

}

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
