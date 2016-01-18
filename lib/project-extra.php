<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2016-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  class ProjectExtra
  {
    const CSS_PREFIX = 'cafevdb-page';
    const TABLE_NAME = 'ProjectExtraFields';
    const TYPE_TABLE = 'ProjectExtraFieldTypes';
    const DATA_TABLE = 'ProjectExtraFieldsData';
    private $pme;
    private $pme_bare;
    private $execute;
    public $projectId;
    public $projectName;
    public $recordId;

    public function __construct($recordId = -1, $execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;

      $this->projectId = Util::cgiValue('ProjectId', false);
      $this->projectName = Util::cgiValue('ProjectName', false);
      $this->recordId = $recordId > 0 ? $recordId : Util::getCGIRecordId();

      Config::init();
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
      if ($this->projectId > 0) {
        return L::t("Extra-Fields for Project %s",
                    array($this->projectName));
      } else {
        return L::t("Extra Fields for Projects");
      }
    }

    public function headerText()
    {
      return $this->shortTitle();
    }

    function display()
    {
      global $debug_query;
      $debug_query = Util::debugMode('query');

      if (Util::debugMode('request')) {
        echo '<PRE>';
        /* print_r($_SERVER); */
        print_r($_POST);
        echo '</PRE>';
      }

      $recordId    = $this->recordId;
      $recordMode  = $recordId > 0;
      $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
      if (!empty(Util::cgiValue($pmeSysPfx.'operation', false))) {
        // only table listings have no operation
        $recordMode = true;
      }

      $projectId   = $this->projectId;
      $projectName = $this->projectName;
      $projectMode = $projectId > 0;
      $tableTabs   = DetailedInstrumentation::tableTabs();
      $tableTabValues2 = array();
      foreach($tableTabs as $tabInfo) {
        $tableTabValues2[$tabInfo['id']] = $tabInfo['name'];
      }

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'project-extra',
        'DisplayClass' => 'ProjectExtra',
        'ClassArguments' => array());

      if ($projectMode || true) {
        $opts['cgi']['persist']['ProjectName'] = $projectName;
        $opts['cgi']['persist']['ProjectId']   = $projectId;
      }

      $opts['tb'] = self::TABLE_NAME;

      // Name of field which is the unique key
      $opts['key'] = 'Id';

      // Type of key field (int/real/string/date etc.)
      $opts['key_type'] = 'int';

      // Sorting field(s)
      $opts['sort_field'] = array(
        'ProjectId',
        'DisplayOrder',
        'Name');

      if ($projectMode !== false) {
        $opts['filters'] = 'ProjectId = '.$this->projectId;
      }

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
      $opts['display'] =  array_merge(
        $opts['display'],
        array(
          'form'  => true,
          //'query' => true,
          'sort'  => true,
          'time'  => true,
          'tabs'  => array(
            array('id' => 'definition',
                  'default' => true,
                  'tooltip' => L::t('Definition of name, type, allowed values, default values.'),
                  'name' => L::t('Defintion')),
            array('id' => 'display',
                  'tooltip' => L::t('Ordering, linking to and defining newe tabs, '.
                                    'definition of tooltips (help text).'),
                  'name' => L::t('Display')),
            array('id' => 'advanced',
                  'toolttip' => L::t('Advanced settings and information, restricted access, '.
                                     'encryption, information about internal indexing.'),
                  'name' => L::t('Advanced')),
            array('id' => 'tab-all',
                  'tooltip' => Config::toolTips('pme-showall-tab'),
                  'name' => L::t('Display all columns'))
            )
          )
        );

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

      /************************************************************************
       *
       * Bug: the following is just too complicated.
       *
       * Goal:
       * Display a list of projects, sorted by year, then by name, constraint:
       *
       */

      // fetch the list of all projects in order to provide a somewhat
      // cooked filter list
      $allProjects = Projects::fetchProjects(false /* no db handle */,
                                             true /* include years */,
                                             true /* most recent years first */);
      $projectQueryValues = array('*' => '*'); // catch-all filter
      $projectQueryValues[''] = L::t('no projects yet');
      $projects = array();
      $groupedProjects = array();
      foreach ($allProjects as $id => $proj) {
        $projectQueryValues[$id] = $proj['Jahr'].': '.$proj['Name'];
        $projects[$id] = $proj['Name'];
        $groupedProjects[$id] = $proj['Jahr'];
      }

      $opts['fdd']['ProjectId'] = array(
        'tab'      => array('id' => 'tab-all'),
        'name'      => L::t('Project-Name'),
        'css' => array('postfix' => ' project-extra-project-name'),
        'options'   => ($projectMode ? 'VCDAPR' : 'FLVCDAP'),
        'select|DV' => 'T', // delete, filter, list, view
        'select|ACPFL' => 'D',  // add, change, copy
        'maxlen'   => 20,
        'size'     => 16,
        'default'  => ($projectMode ? $projectId : -1),
        'sort'     => true,
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

      $opts['fdd']['Name'] = array(
        'tab'      => array('id' => 'tab-all'),
        'name' => L::t('Field-Name'),
        'css' => array('postfix' => ' field-name'),
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-field-name'),
        );

      // TODO: maybe get rid of enums and sets alltogether
      $typeValues = array();
      $typeGroups = array();
      $types = self::fieldTypes();
      if (!empty($types)) {
        foreach($types as $id => $typeInfo) {
          $name = $typeInfo['Name'];
          $group = $typeInfo['Kind'];
          $typeValues[$id] = L::t($name);
          $typeGroups[$id] = L::t($group);
        }
      }

      $opts['fdd']['Type'] = array(
        'tab'      => array('id' => 'definition'),
        'name' => L::t('Type'),
        'css' => array('postfix' => ' field-type'),
        'size' => 30,
        'maxlen' => 24,
        'select' => 'D',
        'sort' => true,
        'values2' => $typeValues,
        'valueGroups' => $typeGroups,
        'tooltip' => Config::toolTips('extra-fields-type'),
        );

      $opts['fdd']['AllowedValues'] = array(
        'name' => L::t('Allowed Values'),
        'css' => array('postfix' => ' allowed-values hide-subsequent-lines'),
        'select' => 'T',
        'textarea' => array('rows' => 5,
                            'cols' => 30),
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-allowed-values'),
        );

      $opts['fdd']['DefaultValue'] = array(
        'name' => L::t('Default Value'),
        'css' => array('postfix' => ' default-value'),
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-default-value'),
        );

      $opts['fdd']['DefaultMultiValue'] = array(
        'name' => L::t('Default Value'),
        // 'input' => 'V', // not virtual, update handled by trigger
        'options' => 'CPA',
        'sql' => '`DefaultValue`',
        'css' => array('postfix' => ' default-multi-value allow-empty'),
        'select' => 'D',
        'values' => array(
          'table' => "SELECT Id, splitString(AllowedValues, '\\n', N) AS Item
 FROM
   ProjectExtraFields
   JOIN numbers
   ON tokenCount(AllowedValues, '\\n') >= N",
          'column' => 'Item',
          'subquery' => true,
          'filters' => '$table.`Id` = $record_id'
          ),
        'maxlen' => 29,
        'size' => 30,
        'sort' => false,
        'tooltip' => Config::toolTips('extra-fields-default-multi-value'),
        );

      $opts['fdd']['DefaultSingleValue'] = array(
        'name' => L::t('Default Value'),
        // 'input' => 'V', // not virtual, update handled by trigger
        'options' => 'CPA',
        'sql' => '`DefaultValue`',
        'css' => array('postfix' => ' default-single-value'),
        'select' => 'O',
        'values2' => array('0' => L::t('false'),
                           '1' => L::t('true')),
        'default' => '0',
        'maxlen' => 29,
        'size' => 30,
        'sort' => false,
        'tooltip' => Config::toolTips('extra-fields-default-single-value'),
        );

      $opts['fdd']['ToolTip'] = array(
        'tab'      => array('id' => 'display'),
        'name' => L::t('Tooltip'),
        'css' => array('postfix' => ' extra-field-tooltip hide-subsequent-lines'),
        'select' => 'T',
        'textarea' => array('rows' => 5,
                            'cols' => 30),
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-tooltip'),
        );

      $opts['fdd']['DisplayOrder'] = array(
        'name' => L::t('Display-Order'),
        'css' => array('postfix' => ' display-order'),
        'select' => 'N',
        'maxlen' => 5,
        'sort' => true,
        'align' => 'right',
        'tooltip' => Config::toolTips('extra-fields-display-order'),
        );

      $opts['fdd']['Tab'] = array(
        'name' => L::t('Table Tab'),
        'css' => array('postfix' => ' tab allow-empty'),
        'select' => 'D',
        'values' => array(
          'table' => self::TABLE_NAME,
          'column' => 'Tab',
          ),
        'values2' => $tableTabValues2,
        'default' => -1,
        'maxlen' => 20,
        'size' => 30,
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-tab'),
        );

      if ($recordMode) {
        // In order to be able to add a new tab, the select box first
        // has to be emptied (in order to avoid conflicts).
        $opts['fdd']['NewTab'] = array(
          'name' => L::t('New Tab Name'),
          'options' => 'CPA',
          'sql' => "''",
          'css' => array('postfix' => ' new-tab'),
          'select' => 'T',
          'maxlen' => 20,
          'size' => 30,
          'sort' => false,
          'tooltip' => Config::toolTips('extra-fields-new-tab'),
          );
      }

      // outside the expermode "if", this is the index!
      $opts['fdd']['Id'] = array(
        'tab'      => array('id' => 'advanced'),
        'name'     => 'Id',
        'select'   => 'T',
        'options'  => 'LFAVCPDR', // auto increment
        'maxlen'   => 11,
        'align'    => 'right',
        'default'  => '0',
        'sort'     => true,
        );

      if (Config::$expertmode) {

        // will hide this later
        $opts['fdd']['FieldIndex'] = array(
          'tab' => array('id' => 'advanced'),
          'name' => L::t('Field-Index'),
          'css' => array('postfix' => ' field-index'),
          // 'options' => 'VCDAPR',
          'align'    => 'right',
          'select' => 'N',
          'maxlen' => 5,
          'sort' => true,
          'input' => 'R',
          'tooltip' => Config::toolTips('extra-fields-field-index'),
          );

        $opts['fdd']['Encrypted'] = array(
          'name' => L::t('Encrypted'),
          'css' => array('postfix' => ' encrypted'),
          'values2|CAP' => array(1 => ''), // empty label for simple checkbox
          'values2|LVFD' => array(1 => L::t('true'),
                                  0 => L::t('false')),
          'default' => '',
          'select' => 'O',
          'maxlen' => 5,
          'sort' => true,
          'tooltip' => Config::toolTips('extra-fields-encrypted'),
          );

        $ownCloudGroups = \OC_Group::getGroups();
        $opts['fdd']['Readers'] = array(
          'name' => L::t('Readers'),
          'css' => array('postfix' => ' readers user-groups'),
          'select' => 'M',
          'values' => $ownCloudGroups,
          'maxlen' => 10,
          'sort' => true,
          'display' => array('popup' => 'data'),
          'tooltip' => Config::toolTips('extra-fields-readers'),
          );

        $opts['fdd']['Writers'] = array(
          'name' => L::t('Writers'),
          'css' => array('postfix' => ' writers chosen-dropup_ user-groups'),
          'select' => 'M',
          'values' => $ownCloudGroups,
          'maxlen' => 10,
          'sort' => true,
          'display' => array('popup' => 'data'),
          'tooltip' => Config::toolTips('extra-fields-writers'),
          );
      }

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\ProjectExtra::beforeUpdateOrInsertTrigger';
      $opts['triggers']['update']['before'][] =  'CAFEVDB\Util::beforeUpdateRemoveUnchanged';

      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\ProjectExtra::beforeInsertTrigger';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\ProjectExtra::beforeUpdateOrInsertTrigger';

      $opts['triggers']['filter']['pre'][]  =
        $opts['triggers']['update']['pre'][]  =
        $opts['triggers']['insert']['pre'][]  = 'CAFEVDB\ProjectExtra::preTrigger';

      $opts['triggers']['insert']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';
      $opts['triggers']['update']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';
      $opts['triggers']['delete']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';

      $opts['execute'] = $this->execute;

      $pme = new \phpMyEdit($opts);
    }

    /** phpMyEdit calls the trigger (callback) with the following arguments:
     *
     * @param[in] $pme The phpMyEdit instance
     *
     * @param[in] $op The operation, 'insert', 'update' etc.
     *
     * @param[in] $step 'before' or 'after' or 'pre'
     *
     * @return boolean. If returning @c false the operation will be terminated
     */
    public static function preTrigger(&$pme, $op, $step)
    {
      self::generateNumbers($pme->dbh);
      return true;
    }

    public static function generateNumbers($handle = false)
    {
      //$start = microtime(true);
      $result = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = 'SELECT MAX(tokenCount(AllowedValues, \'\n\'))
  FROM '.self::TABLE_NAME.' INTO @max';

      $result = mySQL::query($query, $handle);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': mySQL error: '.mySQL::error().' query '.$query,
                            \OCP\Util::ERROR);
      }

      $query = 'CALL generateNumbers(@max)';
      $result = mySQL::query($query, $handle);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': mySQL error: '.mySQL::error().' query '.$query,
                            \OCP\Util::ERROR);
      }

      $numRows = mySQL::queryNumRows('FROM numbers', $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      /* $elapsed = microtime(true) - $start; */

      /* error_log('elapsed: '.$elapsed); */
      /* \OCP\Util::writeLog(Config::APP_NAME, */
      /*                     __METHOD__.': elapsed: '.$elapsed, */
      /*                     \OCP\Util::DEBUG); */

      return $result;
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
    public static function afterTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      if ($op === 'update') {
        // only need to rebuild the view if the name or display-order
        // has changed (index must not changed)
        if (array_search('Name', $changed) === false &&
            array_search('DisplayOrder', $changed) === false) {

          //error_log('bail out'.print_r($changed, true));

          return true;
        }
      }

      error_log(print_r($newvals, true));
      error_log(print_r($changed, true));

      $projectId = $newvals['ProjectId'];
      if ($projectId <= 0) {
        $projectId = $newvals['ProjectId'] = Util::cgiValue('ProjectId', false);
      }

      error_log('prid '.$projectId);

      return Projects::createView($projectId, false, $pme->dbh);
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
      $projectId = $newvals['ProjectId'];
      if ($projectId <= 0) {
        $projectId = $newvals['ProjectId'] = Util::cgiValue('ProjectId', false);
      }

      /* error_log('******* before ******************'); */
      /* error_log(print_r($oldvals, true)); */
      /* error_log(print_r($newvals, true)); */
      /* error_log(print_r($changed, true)); */

      if (empty($projectId)) {
        return false;
      }

      // insert the beast with the next available field id
      $index = mySQL::selectFirstHoleFromTable(self::TABLE_NAME, 'FieldIndex',
                                               "`ProjectId` = ".$projectId,
                                               $pme->dbh);

      if ($index === false) {
        return false;
      }

      $newvals['FieldIndex'] = $index;

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
    public static function beforeUpdateOrInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      /* error_log('******* before ******************'); */
      /* error_log(print_r($oldvals, true)); */
      /* error_log(print_r($newvals, true)); */
      /* error_log(print_r($changed, true)); */

      if ($op === 'update') {
        /************************************************************************
         *
         * The field-index should not change, but the input field is
         * disabled. Make sure it does not change.
         *
         */
        if (isset($newvals['FieldIndex']) &&
            $oldvals['FieldIndex'] != $newvals['FieldIndex']) {
          return false;
        }
      }

      // make sure writer-acls are a subset of reader-acls
      $writers = preg_split('/\s*,\s*/', $newvals['Writers'], -1, PREG_SPLIT_NO_EMPTY);
      $readers = preg_split('/\s*,\s*/', $newvals['Readers'], -1, PREG_SPLIT_NO_EMPTY);
      $missing = array_diff($writers, $readers);
      if (!empty($missing)) {
        $readers = array_merge($readers, $missing);
        $newvals['Readers'] = implode(',', $readers);
      }

      /************************************************************************
       *
       * Move the data from DefaultMultiValue to DefaultValue s.t. PME
       * can do its work.
       *
       */
      $key = array_search('DefaultMultiValue', $changed);
      $types = self::fieldTypes($pme->dbh);
      if ($types[$newvals['Type']]['Kind'] === 'multiple') {
        $newvals['DefaultValue'] = $newvals['DefaultMultiValue'];
        if ($key !== false) {
          $changed[] = 'DefaultValue';
        }
      }
      unset($newvals['DefaultMultiValue']);
      unset($oldvals['DefaultMultiValue']);
      if ($key !== false) {
        unset($changed[$key]);
      }

      $allowed = self::explodeAllowedValues($newvals['AllowedValues']);
      $newvals['AllowedValues'] = implode("\n", $allowed);
      if ($oldvals['AllowedValues'] != $newvals['AllowedValues']) {
        $changed[] = 'AllowedValues';
      }

      /************************************************************************
       *
       * Move the data from DefaultMultiValue to DefaultValue s.t. PME
       * can do its work.
       *
       */
      $key = array_search('DefaultSingleValue', $changed);
      if ($newvals['Type'] === '1') {
        $newvals['DefaultValue'] = $newvals['DefaultSingleValue'];
        if ($key !== false) {
          $changed[] = 'DefaultValue';
        }
      }
      unset($newvals['DefaultSingleValue']);
      unset($oldvals['DefaultSingleValue']);
      if ($key !== false) {
        unset($changed[$key]);
      }

      /************************************************************************
       *
       * Add the data from NewTab to Tab s.t. PME can do its work.
       *
       */
      $key = array_search('NewTab', $changed);
      if (!empty($newvals['NewTab']) && empty($newvals['Tab'])) {
        $newvals['Tab'] = $newvals['NewTab'];
        $changed[] = 'Tab';
      }
      unset($newvals['NewTab']);
      unset($oldvals['NewTab']);
      if ($key !== false) {
        unset($changed[$key]);
      }

      /* error_log('*************************'); */
      /* error_log(print_r($oldvals, true)); */
      /* error_log(print_r($newvals, true)); */
      /* error_log(print_r($changed, true)); */

      return true;
    }

    /**Fetch all registered data-types. */
    public static function fieldTypes($handle = false)
    {
      $types = mySQL::fetchRows(self::TYPE_TABLE);

      $result = array();
      foreach($types as $typeInfo) {
        $id = $typeInfo['Id'];
        $result[$id] = $typeInfo;
      }
      return $result;
    }

    /**Fetch the field definitions in order to generate SQL code to do
     * all the joining and linking.
     */
    public static function projectExtraFields($projectId, $full = false, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      if ($full) {
        $query = "SELECT *";
      } else {
        $query = "SELECT `Id`, `ProcjectId`, `FieldIndex, `DisplayOrder`";
      }
      $query .= "
  FROM `".self::TABLE_NAME."` WHERE `ProjectId` = ".$projectId."
  ORDER BY `DisplayOrder` ASC";

      $result = false;
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        $result = array();
        while ($row = mySQL::fetch($qResult)) {
          if ($full && empty($row['Name'])) {
            $row['Name'] = sprintf('Extra%04d', $row['Id']);
          }
          $result[] = $row;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Fetch the data-set for the given record id.*/
    public static function fetch($recordId, $handle = false)
    {
      $data = mySQL::fetchRows(self::TABLE_NAME, '`Id` = '.$recordId, $handle);
      if (is_array($data) && count($data) == 1) {
        return $data[0];
      } else {
        return false;
      }
    }

    /**Decide whether the given field type corresponds to a multe-value field. */
    public static function multiValueField($type)
    {
      // maybe also GroupOfPeople, but that one is special anyway.
      return $type === 'Set' || $type === 'Enumeration';
    }

    /**Sanitize and explode allowed values. We allow either "whatever", where
     * fields are separated by newlines, or a fancy CSV line with ;,:
     * as delimiters and qutoes and escaped quotes and delimiters. The
     * function converts everything to a "text" with newline separator.
     */
    public static function explodeAllowedValues($values)
    {
      if (strstr($values, "\n")) {
        $values = explode("\n", $values);
      } else {
        $values = Util::quasiCSVSplit($values);
      }
      return array_map('trim', $values);
    }

    /**Copy any extra-field definitions from the old "string"
     * description to the new table representation.
     */
    public static function moveExtraFieldDefinitions($handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $projects = Projects::fetchProjects($handle, false);
      foreach($projects as $projectId => $projectName) {
        $oldExtra  = Projects::extraFields($projectId);

        /* if (!empty($oldExtra)) { */
        /*   error_log(print_r($oldExtra, true)); */
        /* } */

        $newVals = array(
          'ProjectId' => $projectId,
          'Type' => 4, // text
          );
        $cnt = 1;
        foreach($oldExtra as $extraField) {
          $newVals['FieldIndex'] = $extraField['pos'];
          $newVals['DisplayOrder'] = $cnt++;
          $newVals['Name'] = $extraField['name'];
          $newVals['ToolTip'] = $extraField['tooltip'];
          $newVals['Tab'] = 'project';
          $result = mySQL::insert(self::TABLE_NAME, $newVals, $handle, mySQL::UPDATE);

          if ($result === false) {
            \OCP\Util::writeLog(Config::APP_NAME,
                                __METHOD__.
                                ': mySQL error: '.mySQL::error(),
                                \OCP\Util::ERROR);
          }
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    /**Copy any extra-field data from the old "string"
     * description to the new table representation.
     */
    public static function moveExtraFieldData($handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $projects = Projects::fetchProjects($handle, false);
      foreach($projects as $projectId => $projectName) {
        $oldExtra  = Projects::extraFields($projectId);

        /* if (!empty($oldExtra)) { */
        /*   error_log(print_r($oldExtra, true)); */
        /* } */

        foreach($oldExtra as $extraField) {
          $fieldIndex = $extraField['pos'];
          $oldExtra = sprintf('ExtraFeld%02d', $fieldIndex);
          $query = "INSERT INTO ".self::DATA_TABLE."
  (BesetzungenId, FieldId, FieldValue)
  SELECT b.Id, f.Id as FieldId, b.".$oldExtra."
  FROM Besetzungen b
  LEFT JOIN ".self::TABLE_NAME." f
    ON b.ProjektId = f.ProjectId AND ".$fieldIndex. " = f.FieldIndex
  WHERE f.ProjectId = ".$projectId." AND ".$fieldIndex. " = f.FieldIndex";

          $result = mySQL::query($query, $handle);
          if ($result === false) {
            \OCP\Util::writeLog(Config::APP_NAME,
                                __METHOD__.
                                ': mySQL error: '.mySQL::error().' query '.$query,
                                \OCP\Util::ERROR);
          }
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

  }; // class ProjectExtra

}

?>
