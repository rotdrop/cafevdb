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
    public $showDisabledFields;

    public function __construct($recordId = -1, $execute = true)
    {
      $this->execute = $execute;
      $this->pme = false;
      $this->pme_bare = false;

      $this->projectId = Util::cgiValue('ProjectId', false);
      $this->projectName = Util::cgiValue('ProjectName', false);
      $this->recordId = $recordId > 0 ? $recordId : Util::getCGIRecordId();

      $this->showDisabledFields = Util::cgiValue('ShowDisabledFields', false);
      $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
      if (Util::cgiValue($pmeSysPfx.'showdisabled', false) !== false) {
        $this->showDisabledFields = true;
      } else if (Util::cgiValue($pmeSysPfx.'hidedisabled', false) !== false) {
        $this->showDisabledFields = false;
      }

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

    private static function translationPlaceholder()
    {
      L::t('Boolean');
      L::t('Integer');
      L::t('Float');
      L::t('Text');
      L::t('HTML');
      L::t('Money');
      L::t('Set');
      L::t('Enum');
      L::t('Enumeration');
      L::t('SimpleGroup');
      L::t('PredefinedGroups');
      L::t('Date');
      L::t('SurchargeOption');
      L::t('SurchargeEnum');
      L::t('SurchargeSet');
      L::t('SurchargeGroup');
      L::t('SurchargeGroups');

      L::t('simple');
      L::t('single');
      L::t('multiple');
      L::t('parallel');
      L::t('groupofpeople');
      L::t('groupsofpeople');

      L::t('choices');
      L::t('surcharge');
      L::t('general');
      L::t('special');
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

      $projectId    = $this->projectId;
      $projectName  = $this->projectName;
      $projectMode  = $projectId > 0;
      $showDisabled = $this->showDisabledFields;

      $tableTabs   = DetailedInstrumentation::tableTabs(null, true);
      $tableTabValues2 = array();
      foreach($tableTabs as $tabInfo) {
        $tableTabValues2[$tabInfo['id']] = $tabInfo['name'];
      }

      // Inherit a bunch of default options
      $opts = Config::$pmeopts;

      $opts['cgi']['persist'] = array(
        'Template' => 'project-extra',
        'DisplayClass' => 'ProjectExtra',
        'ClassArguments' => array(),
        'ShowDisabledFields' => $this->showDisabledFields,
        );

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

      // Number of records to display on the screen
      // Value of -1 lists all records in a table
      // $opts['inc'] = -1;

      // Options you wish to give the users
      // A - add,  C - change, P - copy, V - view, D - delete,
      // F - filter, I - initial sort suppressed
      $opts['options'] = 'ACPVDF';

      // Number of lines to display on multiple selection filters
      $opts['multiple'] = '6';

      $showButton = array(
        'name' => 'showdisabled',
        'value' => L::t('Show Disabled'),
        'css' => 'show-disabled'
        );
      $hideButton = array(
        'name' => 'hidedisabled',
        'value' => L::t('Hide Disabled'),
        'css' => 'show-disabled'
        );
      if ($this->showDisabledFields) {
        $opts['buttons'] = Navigation::prependTableButton($hideButton, false, false);
      } else {
        $opts['buttons'] = Navigation::prependTableButton($showButton, false, false);
      }

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

      $projectIdx = 0; // just the start here count($opts['fdd']);
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

      $tooltipIdx = -1;
      $nameIdx = count($opts['fdd']);
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
      $typeData = array();
      $typeTitles = array();
      $types = self::fieldTypes();
      if (!empty($types)) {
        foreach($types as $id => $typeInfo) {
          $name = $typeInfo['Name'];
          $multiplicity = $typeInfo['Multiplicity'];
          $group = $typeInfo['Kind'];
          $typeValues[$id] = L::t($name);
          $typeGroups[$id] = L::t($group);
          $typeData[$id] = json_encode(
            array(
              'Multiplicity' => $multiplicity,
              'Group' => $group)
            );
          $typeTitles[$id] = Config::toolTips('extra-field-'.$group.'-'.$multiplicity);
        }
      }

      if ($showDisabled) {
        $opts['fdd']['Disabled'] = array(
          'tab'      => array('id' => 'definition'),
          'name'     => L::t('Disabled'),
          'css'      => array('postfix' => ' extra-field-disabled'),
          'values2|CAP' => array(1 => ''),
          'values2|LVFD' => array(1 => L::t('true'),
                                  0 => L::t('false')),
          'default'  => '',
          'select'   => 'O',
          'sort'     => true,
          'tooltip'  => Config::toolTips('extra-fields-disabled')
          );
      }

      $opts['fdd']['Type'] = array(
        'tab'      => array('id' => 'definition'),
        'name' => L::t('Type'),
        'css' => array('postfix' => ' field-type'),
        'php|VD' => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($typeData) {
          $key = $row['qf'.$field];
          return '<span class="data" data-data=\''.$typeData[$key].'\'></span>'.$value;
        },
        'size' => 30,
        'maxlen' => 24,
        'select' => 'D',
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-type'),
        'values2' => $typeValues,
        'valueGroups' => $typeGroups,
        'valueData' => $typeData,
        'valueTitles' => $typeTitles,
        );

      $opts['fdd']['AllowedValues'] = array(
        'name' => L::t('Allowed Values'),
        'css|LF' => array('postfix' => ' allowed-values hide-subsequent-lines'),
        'css' => array('postfix' => ' allowed-values'),
        'select' => 'T',
        'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId)
        {
          return self::showAllowedValues($value, $op, $recordId);
        },
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'display|LF' => array('popup' => 'data'),
        'tooltip' => Config::toolTips('extra-fields-allowed-values'),
        );

      $opts['fdd']['AllowedValuesSingle'] = array(
        'name' => self::currencyLabel(L::t('Data')),
        'css' => array('postfix' => ' allowed-values-single'),
        'sql' => 'PMEtable0.AllowedValues',
        'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx, $tooltipIdx)
        {
          // provide defaults
          $protoRecord = array_merge(
            self::allowedValuesPrototype(),
            [ 'key' => $recordId,
              'label' => $row['qf'.$nameIdx],
              'tooltip' => $row['qf'.$tooltipIdx] ]);
          return self::showAllowedSingleValue($value, $op, $fdd[$field]['tooltip'], $protoRecord);
        },
        'options' => 'ACDPV',
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => Config::toolTips('extra-fields-allowed-values-single'),
        );

      // Provide "cooked" valus for up to 20 members. Perhaps the
      // max. number should somehow be adjusted ...
      $values2 = array();
      $dpy = 0;
      $values2[$dpy] = $dpy;
      for($dpy = 2; $dpy < 10; ++$dpy) {
        $values2[$dpy] = $dpy;
      }
      for(; $dpy <= 30; $dpy += 5) {
        $values2[$dpy] = $dpy;
      }
      $opts['fdd']['MaximumGroupSize'] = array(
        'name' => L::t('Maximum Size'),
        'css' => array('postfix' => ' maximum-group-size'),
        'sql' => "SUBSTRING_INDEX(PMEtable0.AllowedValues, ':', -1)",
        'input' => 'S',
        'input|DV' => 'V',
        'options' => 'ACDPV',
        'select' => 'D',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'default' => key($values2),
        'values2' => $values2,
        'tooltip' => Config::toolTips('extra-fields-maximum-group-size'),
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
        'sql' => 'PMEtable0.`DefaultValue`',
        'css' => array('postfix' => ' default-multi-value allow-empty'),
        'select' => 'D',
        'values' => array(
          'table' => "SELECT Id,
 splitString(splitString(AllowedValues, '\\n', N), ':', 1) AS Value,
 splitString(splitString(AllowedValues, '\\n', N), ':', 2) AS Label,
 splitString(splitString(AllowedValues, '\\n', N), ':', 5) AS Flags
 FROM
   `ProjectExtraFields`
   JOIN `numbers`
   ON tokenCount(AllowedValues, '\\n') >= `numbers`.N",
          'column' => 'Value',
          'description' => 'Label',
          'subquery' => true,
          'filters' => '$table.`Id` = $record_id AND NOT $table.`Flags` = \'deleted\'',
          'join' => '$join_table.$join_column = $main_table.`DefaultValue`'
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
        'sql' => 'PMEtable0.`DefaultValue`',
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

      $tooltipIdx = count($opts['fdd']);
      $opts['fdd']['ToolTip'] = array(
        'tab'      => array('id' => 'display'),
        'name' => L::t('Tooltip'),
        'css' => array('postfix' => ' extra-field-tooltip hide-subsequent-lines'),
        'select' => 'T',
        'textarea' => array('rows' => 5,
                            'cols' => 28),
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'escape' => false,
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
          'description' => 'Tab',
          ),
        'values2' => $tableTabValues2,
        'default' => -1,
        'maxlen' => 128,
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

      // GROUP BY clause, if needed.
      $opts['groupby_fields'] = 'Id';

      $opts['filters'] = [];
      if (!$showDisabled) {
        $opts['filters'][] = 'NOT `PMEtable0`.`Disabled` = 1';
        if ($projectMode === false) {
          $opts['filters'][] = 'NOT `PMEjoin'.$projectIdx.'`.`Disabled` = 1';
        }
      }
      if ($projectMode !== false) {
        $opts['filters'][] = 'PMEtable0.ProjectId = '.$this->projectId;
      }

      $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['update']['before'][]  = 'CAFEVDB\ProjectExtra::beforeUpdateOrInsertTrigger';
      $opts['triggers']['update']['before'][] =  'CAFEVDB\Util::beforeUpdateRemoveUnchanged';

      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\ProjectExtra::beforeInsertTrigger';
      $opts['triggers']['insert']['before'][]  = 'CAFEVDB\ProjectExtra::beforeUpdateOrInsertTrigger';

      $opts['triggers']['delete']['before'][]  = 'CAFEVDB\ProjectExtra::beforeDeleteTrigger';

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
                            ': mySQL error: '.mySQL::error($handle).' query '.$query,
                            \OCP\Util::ERROR);
      }
      mySQL::freeResult($result);

      $query = 'CALL generateNumbers(@max)';
      $result = mySQL::query($query, $handle);
      if ($result === false) {
        \OCP\Util::writeLog(Config::APP_NAME,
                            __METHOD__.
                            ': mySQL error: '.mySQL::error($handle).' query '.$query,
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

    /**Obtain a new unique group id; this is just a "soft"
     * auto-increment column.
     */
    public static function maxFieldValue($fieldId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $max = mySQL::selectSingleFromTable(
        'MAX(FieldValue)', self::DATA_TABLE, "WHERE FieldId = $fieldId", $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $max;
    }

    /**Make keys unique for multi-choice fields.
     *
     * @param[in] string $key Input key.
     *
     * @param[in] array $keys Existing keys.
     *
     * @return Something "close" to $key, but not contained in $keys.
     *
     * @bug Potentially, this could fail. But will not in real life.
     */
    public static function allowedValuesUniqueKey($key, $keys)
    {
      $key = Util::translitToASCII($key);
      $key = ucwords($key);
      $key = lcfirst($key);
      $key = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $key);
      $key = substr($key, 0, 8);
      $cnt = 1;
      while (($idx = array_search($key, $keys)) !== false && $cnt < 1000) {
        $key = substr($key, 0, 8 - strlen($cnt)).$cnt;
        ++$cnt;
      }
      return $key;
    }

    /**Given an array of multiple choices make its keys unique with
     * respect to itself. Used keys remain fixed (unique or not).
     *
     * @param[in,out] array &$allowed Array of admissible options.
     *
     * @param[in] integer $recordId The record Id for the field.
     */
    private static function allowedValuesUniqueKeys(&$allowed, $recordId)
    {
      if (!empty($recordid)) {
        $usedKeys = self::fieldValuesFromDB($recordId);
      } else {
        $usedKeys = array();
      }
      $keys = array();
      foreach($allowed as $idx => $item) {
        $keys[$idx] = $item['key'];
      }
      foreach($allowed as $idx => &$item) {
        $key = $item['key'];
        if (array_search($key, $usedKeys) !== false) {
          continue; // don't change used keys
        }
        $otherKeys = $keys;
        unset($otherKeys[$idx]);
        $key = self::allowedValuesUniqueKey($key, $otherKeys);
        $item['key'] = $key;
        $keys[$idx] = $key;
      }
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

      $projectId = $newvals['ProjectId'];
      if ($projectId <= 0) {
        $projectId = $newvals['ProjectId'] = Util::cgiValue('ProjectId', false);
      }

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
    public static function beforeDeleteTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
    {
      $used = self::fieldsFromDB(-1, $pme->rec, $pme->dbh);

      if (count($used) === 1 && $used[0] == $pme->rec) {
        // Already used. Just mark the beast as inactive
        mySQL::update(self::TABLE_NAME, '`Id` = '.$pme->rec,
                      array('Disabled' => 1), $pme->dbh);
        return false;
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
      if ($types[$newvals['Type']]['Multiplicity'] === 'multiple' ||
          $types[$newvals['Type']]['Multiplicity'] === 'parallel') {
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

      /************************************************************************
       *
       * Move the data from MaximumGroupSize to
       * AllowedValues. Plural is "misleading" here, of course ;)
       *
       */
      $tag = "MaximumGroupSize";
      $key = array_search($tag, $changed);
      if ($types[$newvals['Type']]['Multiplicity'] === 'groupofpeople')
      {
        $max = $newvals[$tag];
        if ($op === 'update' && !empty($newvals['AllowedValuesSingle'][0])) {
          $maxdata = $newvals['AllowedValuesSingle'];
          $maxdata[0]['column5'] = $max;
        } else {
          $maxdata = 'max:group:::active:'.$max;
        }
        $newvals['AllowedValues'] = $maxdata;
        if ($key !== false) {
          $changed[] = 'AllowedValues';
        }
      }
      unset($newvals[$tag]);
      unset($oldvals[$tag]);
      if ($key !== false) {
        unset($changed[$key]);
      }

      /************************************************************************
       *
       * Move the data from AllowedValuesSingle to
       * AllowedValues. Plural is "misleading" here, of course ;)
       *
       */
      $key = array_search('AllowedValuesSingle', $changed);
      if ($types[$newvals['Type']]['Multiplicity'] === 'single') {
        $newvals['AllowedValues'] = $newvals['AllowedValuesSingle'];
        if ($key !== false) {
          $changed[] = 'AllowedValues';
        }
      }
      unset($newvals['AllowedValuesSingle']);
      unset($oldvals['AllowedValuesSingle']);
      if ($key !== false) {
        unset($changed[$key]);
      }

      /************************************************************************
       *
       * Sanitize AllowedValues
       *
       */

      if (!is_array($newvals['AllowedValues'])) {
        // textfield
        $allowed = self::explodeAllowedValues($newvals['AllowedValues']);

      } else {
        $allowed = $newvals['AllowedValues'];
      }
      // make unused keys unique
      self::allowedValuesUniqueKeys($allowed, $pme->rec);

      //error_log('trigger '.print_r($allowed, true));
      $newvals['AllowedValues'] = self::implodeAllowedValues($allowed);
      if ($oldvals['AllowedValues'] !== $newvals['AllowedValues']) {
        $changed[] = 'AllowedValues';
      }

      /************************************************************************
       *
       * Move the data from DefaultSingleValue to DefaultValue s.t. PME
       * can do its work.
       *
       */
      $key = array_search('DefaultSingleValue', $changed);
      if ($types[$newvals['Type']]['Multiplicity'] === 'single') {
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

      if (!empty($newvals['ToolTip'])) {
        $newvals['ToolTip'] = FuzzyInput::purifyHTML($newvals['ToolTip']);
        if ($newvals['ToolTip'] !== $oldvals['ToolTip']) {
          $changed[] = 'ToolTip';
        } else {
          $key = array_search('NewTab', $changed);
          if ($key !== false) {
            unset($changed[$key]);
          }
        }
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
      static $fieldTypes = null; // cache for the life-time of the request.

      if (empty($fieldTypes)) {
        $types = mySQL::fetchRows(self::TYPE_TABLE, null, '`Kind` ASC, `Multiplicity` ASC, `Name` ASC', $handle);
        $fieldTypes = array();
        foreach($types as $typeInfo) {
          $id = $typeInfo['Id'];
          $fieldTypes[$id] = $typeInfo;
        }
      }

      return $fieldTypes;
    }

    /**Fetch the field definitions in order to generate SQL code to do
     * all the joining and linking.
     *
     * @param[in] int $projectId The project Id.
     *
     * @param[in] bool $full If false, only Id, ProjectId, FieldIndex
     * and DisplayOrder are returned.
     *
     * @return Flat array with the data-base rows. The function will
     * return an empty array in case of error, or if there are no
     * extra fields defined yet.
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
  FROM `".self::TABLE_NAME."`
  WHERE
    `ProjectId` = ".$projectId."
     AND
     NOT `Disabled` = 1
  ORDER BY `DisplayOrder` ASC";

      $result = array();
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        while ($row = mySQL::fetch($qResult)) {
          if ($full && empty($row['Name'])) {
            $row['Name'] = sprintf('Extra%04d', $row['Id']);
          }
          $result[] = $row;
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Data-base queries from the project view will rather use the
     * field labels. In particular, monetary fields are of interest,
     * so filter those out, grouped in an array label =>
     * field-info. Also, the function resolves links into the type
     * info table and explodes the allowed value field.
     *
     * @param[in] mixed $idOrFields $projectId or pre-fetched extra-fields
     * array.
     *
     * @param[in] mixed $typeInfoOrHandle If $idOrFields is an array
     * (i.e. the field descriptions, then $typeInfoOrHandle must be
     * the type-info array as obtained by
     * self::fieldTypes(). Otherwise it may be an existing data-base
     * handle or null.
     */
    public static function monetaryFields($idOrFields, $typeInfoOrHandle = null)
    {
      if (is_array($idOrFields) && !is_array($typeInfoOrHandle)) {
        throw new \InvalidArgumentException('If my first argument is an array of field descriptions, then my second
argument must be an array of type descriptions.');
      }
      if (is_scalar($idOrFields)) {
        $projectId = $idOrFields;
        $handle = $typeInfoOrHandle;
        $extraFields = Instrumentation::getExtraFields($projectId, $handle);
        $fieldTypes = self::fieldTypes($handle);
      } else {
        $extraFields = $idOrFields;
        $fieldTypes = $typeInfoOrHandle;
      }
      $monetary = array(); // Labels for moneary fields
      foreach($extraFields as $field) {
        $type = $fieldTypes[$field['Type']];
        if ($type['Kind'] === 'surcharge') {
          $field['Type'] = $type;
          $field['AllowedValues'] =
            ProjectExtra::explodeAllowedValues($field['AllowedValues'], false, true);
          $monetary[$field['Name']] = $field;
        }
      }
      return $monetary;
    }

    /**Fetch the data-set for the given record id.*/
    public static function fetch($recordId, $handle = false)
    {
      $data = mySQL::fetchRows(self::TABLE_NAME, '`Id` = '.$recordId, null, $handle);
      if (is_array($data) && count($data) === 1) {
        return $data[0];
      } else {
        return false;
      }
    }

    /**Delete the given extra field and associated data.
     *
     * @param[in] int $fieldId The field-id to remove.
     *
     * @param[in] int $projectId The project the field is associated
     * to. For security reasons both, $fieldId and $projectId have to
     * be specified.
     *
     * @param[in] bool $force If @c false, only remove the field if it
     * has no data attached. If @c true, just remove it and all
     * associated data.
     *
     * @param[in] $handle Data-base handle.
     *
     * @return @c true if the field has been removed (and all data),
     * false otherwise. @c false may mean SQL error, or with $force
     * === false that the field has already data associated to it.
     */
    public static function deleteExtraField($fieldId, $projectId, $force = false, $handle = false)
    {
      $result = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      if ($force === true) {
        $query = "DELETE FROM ".self::DATA_TABLE." WHERE FieldId = $fieldId";
        $qResult = mySQL::query($query, $handle);
        if ($qResult !== false && mySQL::changedRows($handle) >= 0) {
          $query = "DELETE FROM ".self::TABLE." WHERE Id = $fieldId AND ProjectId = $projectId";
          $qResult = mySQL::query($query, $handle);
          if ($qResult !== false && mySQL::changedRows($handle) > 0) {
            $result = true;
          }
        }
      } else {
        $dataField = self::fieldsFromDB($projectId, $fieldId, $handle);
        if (empty($dataField)) {
          $result = self::deleteExtraField($fieldId, $projectId, true, $handle);
        } else {
          mySQL::update(self::TABLE_NAME,
                        "`Id` = $fieldId AND `ProjectId` = $projectId",
                        array('Disabled' => 1));
          $result = false;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Fetch all values stored for the given extra-field, e.g. in
     * order to recover or generate select boxes.
     */
    public static function fieldValuesFromDB($recordId, $handle = false)
    {
      $values = mySQL::valuesFromColumn(self::DATA_TABLE, 'FieldValue', $handle,
                                        "`FieldId` = ".$recordId, ',');
      return $values;
    }

    /**Fetch all fields which already have data associated to it.
     *
     * @return Flat array of field-ids.
     */
    public static function fieldsFromDB($projectId = -1, $fieldId = -1, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT DISTINCT d.`FieldId`
  FROM `".self::DATA_TABLE."` d
  LEFT JOIN `Besetzungen` b
  ON d.`BesetzungenId` = b.`Id`
  WHERE
    d.`FieldValue` > ''";
      if ($projectId > 0) {
        $query .= "AND b.`ProjektId` = ".$projectId;
      }
      if ($fieldId > 0) {
        $query .= "AND d.`FieldId` = ".$fieldId;
      }

      //error_log($query);

      $result = array();
      $qResult = mySQL::query($query, $handle);
      if ($qResult !== false) {
        while ($row = mySQL::fetch($qResult)) {
          $result[] = $row['FieldId'];
        }
        mySQL::freeResult($qResult);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Generate a row given values and index for the "change" view
     * corresponding to the multi-choice fields.
     *
     * @param[in] array $value One row of the form as returned form
     * self::explodeAllowedValues()
     *
     * @param[in] integer $index A unique row number.
     *
     * @param[in] boolean $used Whether the DB already contains data
     * records referring to this item.
     *
     * @return sting HTML data for one row.
     */
    public static function allowedValueInputRow($value, $index = -1, $used = false)
    {
      $pfx = Config::$pmeopts['cgi']['prefix']['data'];
      $pfx .= 'AllowedValues';
      $key = $value['key'];
      $placeHolder = empty($key);
      $deleted = $value['flags'] === 'deleted';
      empty($value['flags']) && $value['flags'] = 'active';
      $data = ''
        .' data-index="'.$index.'"' // real index
        .' data-used="'.($used ? 'used' : 'unused').'"'
        .' data-flags="'.$value['flags'].'"';
      $html = '';
      $html .= '
    <tr'
      .' class="data-line'
         .' allowed-values'
         .($placeHolder ? ' placeholder' : '')
         .' '.$value['flags']
         .'"'
         .' '.$data.'>';
      if (!$placeHolder) {
        $html .= '<td class="delete-undelete">'
          .'<input'
          .' class="delete-undelete"'
          .' title="'.Config::toolTips('extra-fields-delete-undelete').'"'
          .' type="button"/>'
          .'</td>';
      } else {
        $index = -1; // move out of the way
      }
      // label
      $prop = 'label';
      $label = ''
        .'<input'
        .($deleted ? ' readonly="readonly"' : '')
        .' class="field-'.$prop.'"'
        .' spellcheck="true"'
        .' type="text"'
        .' name="'.$pfx.'['.$index.']['.$prop.']"'
        .' value="'.$value[$prop].'"'
        .' title="'.Config::toolTips('extra-fields-allowed-values', $placeHolder ? 'placeholder' : $prop).'"'
        .' placeholder="'.($placeHolder ? L::t('new option') : '').'"'
        .' size="33"'
        .' maxlength="32"'
        .'/>';
      if (!$placeHolder) {
        // key
        $prop = 'key';
        $html .= '<td class="field-'.$prop.'">'
          .'<input'
          .($used || $deleted ? ' readonly="readonly"' : '')
          .' type="text"'
          .' class="field-key"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.Config::toolTips('extra-fields-allowed-values', $prop).'"'
          .' size="9"'
          .' maxlength="8"'
          .'/>'
          .'<input'
          .' type="hidden"'
          .' class="field-flags"'
          .' name="'.$pfx.'['.$index.'][flags]"'
          .' value="'.$value['flags'].'"'
          .'/>'
          .'</td>';
        // label
        $prop = 'label';
        $html .= '<td class="field-'.$prop.'">'.$label.'</td>';
        // limit
        $prop = 'limit';
        $html .= '<td class="field-'.$prop.'"><input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' type="text"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.Config::toolTips('extra-fields-allowed-values', $prop).'"'
          .' maxlength="8"'
          .' size="9"'
          .'/></td>';
        // data
        $prop = 'data';
        $html .= '<td class="field-'.$prop.'"><input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' type="text"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.Config::toolTips('extra-fields-allowed-values', $prop).'"'
          .' maxlength="8"'
          .' size="9"'
          .'/></td>';
        // tooltip
        $prop = 'tooltip';
        $html .= '<td class="field-'.$prop.'">'
          .'<textarea'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' title="'.Config::toolTips('extra-fields-allowed-values', $prop).'"'
          .' cols="32"'
          .' rows="1"'
          .'>'
          .$value[$prop]
          .'</textarea>'
          .'</td>';

        // general further fields
        for($i = 6; $i < count($value); ++$i) {
          $prop = 'column'.$i;
          $html .= '<td class="field-'.$prop.'"><input'
            .($deleted ? ' readonly="readonly"' : '')
            .' class="field-'.$prop.'"'
            .' type="text"'
            .' name="'.$pfx.'['.$index.']['.$prop.']"'
            .' value="'.$value[$prop].'"'
            .' title="'.Config::toolTips('extra-fields-allowed-values', $prop).'"'
            .' maxlength="8"'
            .' size="9"'
            .'/></td>';
        }

      } else {
        $html .= '<td class="placeholder" colspan="6">'
          .$label;
        foreach(['key', 'limit', 'data', 'tooltip'] as $prop) {
          $html .= '<input'
            .' class="field-'.$prop.'"'
            .' type="hidden"'
            .' name="'.$pfx.'['.$index.']['.$prop.']"'
            .' value=""'
            .'/>';
        }
        $html .= '</td>';
      }
      // finis
      $html .= '
    </tr>';
      return $html;
    }

    /**Generate a table in order to define field-valus for
     * multi-select stuff.
     */
    static private function showAllowedValues($value, $op, $recordId)
    {
      $allowed = self::explodeAllowedValues($value);
      $protoCount = count(self::allowedValuesPrototype());
      if ($op === 'display' && count($allowed) == 1) {
        return '';
      }
      $maxColumns = 0;
      foreach($allowed as $value) {
        $maxColumns = max(count($value), $maxColumns);
      }
      $html = '<div class="pme-cell-wrapper quarter-sized">';
      if ($op === 'add' || $op === 'change') {
        $showDeletedLabel = L::t("Show deleted items.");
        $showDeletedTip = Config::toolTips('extra-fields-show-deleted');
        $showDataLabel = L::t("Show data-fields.");
        $showDataTip = Config::toolTips('extra-fields-show-data');
        $html .=<<<__EOT__
<div class="field-display-options">
  <div class="show-deleted">
    <input type="checkbox"
           name="show-deleted"
           class="show-deleted checkbox"
           value="show"
           id="allowed-values-show-deleted"
           />
    <label class="show-deleted"
           for="allowed-values-show-deleted"
           title="$showDeletedTip"
           >
      $showDeletedLabel
    </label>
  </div>
  <div class="show-data">
    <input type="checkbox"
           name="show-data"
           class="show-data checkbox"
           value="show"
           id="allowed-values-show-data"
           />
    <label class="show-data"
           for="allowed-values-show-data"
           title="$showDataTip"
           >
      $showDataLabel
    </label>
  </div>
</div>
__EOT__;
      }

      $html .= '<table class="operation-'.$op.' allowed-values">
  <thead>
     <tr>';
        $html .= '<th class="operations"></th>';
        $headers = array('key' => L::t('Key'),
                         'label' => L::t('Label'),
                         'limit' => L::t('Limit'),
                         'data' => self::currencyLabel(L::t('Data')),
                         'tooltip' => L::t('Tooltip'));
        foreach($headers as $key => $value) {
          $html .=
            '<th'
            .' class="field-'.$key.'"'
            .' title="'.Config::toolTips('extra-fields-allowed-values', $key).'"'
            .'>'
            .$value
            .'</th>';
        }
        for($i = $protoCount; $i < $maxColumns; ++$i) {
          $key = 'column'.$i;
          $value = L::t('%dth column', array($i));
          $html .=
            '<th'
            .' class="field-'.$key.'"'
            .' title="'.Config::toolTips('extra-fields-allowed-values', $key).'"'
            .'>'
            .$value
            .'</th>';
        }
        $html .= '
     </tr>
  </thead>
  <tbody>';
          switch ($op) {
          case 'display':
            foreach ($allowed as $idx => $value) {
              if (empty($value['key']) || $value['flags'] === 'deleted') {
                continue;
              }
              $html .= '
    <tr>
      <td class="operations"></td>';
              foreach(['key', 'label', 'limit', 'data', 'tooltip'] as $field) {
                $html .= '<td class="field-'.$field.'">'
                  .($field === 'data'
                    ? self::currencyValue($value[$field])
                    : $value[$field])
                  .'</td>';
              }
              for($i = $protoCount; $i < count($value); ++$i) {
                $field = 'column'.$i;
                $html .= '<td class="field-'.$field.'">'
                  .$value[$field]
                  .'</td>';
              }
              $html .= '
    </tr>';
            }
            break;
          case 'add':
          case 'change':
            $usedKeys = self::fieldValuesFromDB($recordId);
            //error_log(print_r($usedKeys, true));
            $pfx = Config::$pmeopts['cgi']['prefix']['data'];
            $pfx .= 'AllowedValues';
            $css = 'class="allowed-values"';
            foreach ($allowed as $idx => $value) {
              if (!empty($value['key'])) {
                $key = $value['key'];
                $used = array_search($key, $usedKeys) !== false;
              } else {
                $used = false;
              }
              $html .= self::allowedValueInputRow($value, $idx, $used);
            }
            break;
          }
          $html .= '
  </tbody>
</table></div>';
          return $html;
    }

    /**Return a currency value where the number symbol can be hidden
     * by CSS.
     */
    private static function currencyValue($value)
    {
      $money = Util::moneyValue($value, Config::$locale);
      return
        '<span class="surcharge currency-amount">'.$money.'</span>'.
        '<span class="general">'.$value.'</span>';
    }

    /**Return an alternate "Amount [CUR]" label which can be hidden by
     * CSS.
     */
    private static function currencyLabel($label = 'Data')
    {
      return
        '<span class="general">'.$label.'</span>'.
        '<span class="surcharge currencylabel">'
        .L::t('Amount').' ['.Config::$currency.']'
        .'</span>';
    }

    /**Display the input stuff for a single-value choice, probably
     * only for surcharge fields.
     */
    private static function showAllowedSingleValue($value, $op, $toolTip, $protoRecord)
    {
      $allowed = self::explodeAllowedValues($value, false);
      // if there are multiple options available (after a type
      // change) we just pick the first non-deleted.
      $entry = false;
      foreach($allowed as $idx => $item) {
        if (empty($item['key']) || $item['flags'] === 'deleted') {
          continue;
        } else {
          $entry = $item;
          unset($allowed[$idx]);
          break;
        }
      }
      $allowed = array_values($allowed); // compress index range
      $value = empty($entry) ? '' : $entry['data'];
      if ($op === 'display') {
        return self::currencyValue($value);
      }
      empty($entry) && $entry = $protoRecord;
      $protoCount = count($protoRecord);
      $name  = Config::$pmeopts['cgi']['prefix']['data'];
      $name .= 'AllowedValuesSingle';
      $value = htmlspecialchars($entry['data']);
      $tip   = $toolTip;
      $html  = '<div class="active-value">';
      $html  .=<<<__EOT__
<input class="pme-input allowed-values-single"
       type="text"
       maxlength="29"
       size="30"
       value="{$value}"
       name="{$name}[0][data]"
       title="{$tip}"
/>
__EOT__;
      foreach(['key', 'label', 'limit', 'tooltip', 'flags'] as $field) {
        $value = htmlspecialchars($entry[$field]);
        $html .=<<<__EOT__
<input class="pme-input allowed-values-single"
       type="hidden"
       value="{$value}"
       name="{$name}[0][{$field}]"
/>
__EOT__;
      }
      for($i = $protoCount; $i < count($entry); ++$i) {
        $field = 'column'.$i;
        $value = htmlspecialchars($entry[$field]);
        $html .=<<<__EOT__
<input class="pme-input allowed-values-single"
       type="hidden"
       value="{$value}"
       name="{$name}[0][{$field}]"
/>
__EOT__;
      }
      $html .= '</div>';
      $html .= '<div class="inactive-values">';
      // Now emit all left-over values. Flag all items as deleted.
      foreach($allowed as $idx => $item) {
        ++$idx; // shift ...
        $item['flags'] = 'deleted';
        foreach(['key', 'label', 'limit', 'data', 'tooltip', 'flags'] as $field) {
          $value = htmlspecialchars($item[$field]);
          $html .=<<<__EOT__
<input class="pme-input allowed-values-single"
       type="hidden"
       value="{$value}"
       name="{$name}[{$idx}][{$field}]"
/>
__EOT__;
        }
        for($i = $protoCOunt; $i < count($item); ++$i) {
          $field = 'column'.$i;
          $value = htmlspecialchars($item[$field]);
          $html .=<<<__EOT__
<input class="pme-input allowed-values-single"
       type="hidden"
       value="{$value}"
       name="{$name}[{$idx}][{$field}]"
/>
__EOT__;
        }
      }
      $html .= '</div>';
      return $html;
    }

    private static function allowedValuesPrototype()
    {
      return [ 'key' => false,
               'label' => false,
               'data' => false,
               'tooltip' => false,
               'flags' => 'active',
               'limit' => false ];
    }

    /**Sanitize and explode allowed values. Multiple choice items are
     * internally stored as text, items separated by \n, each line may
     * consist of CSV-like data
     *
     * key:label:data:tooltip:flags[:FURTHER_DATA[:FURTHER...]]
     *
     * key and display are initiallly just identical. DATA is the
     * amount to pay for "surcharge" fields. The per-user data stored
     * is KEY. KEY should not be changed.
     *
     */
    public static function explodeAllowedValues($values, $addProto = true, $trimInactive = false)
    {
      //error_log('explode: '.$values);
      $proto = self::allowedValuesPrototype();
      $protoCount = count($proto);
      $values = Util::explode("\n", $values);
      $allowed = array();
      foreach($values as $value) {
        if (empty($value)) {
          continue;
        }
        $parts = Util::quasiCSVSplit($value, ':');
        //error_log('parts: '.print_r($parts, true));
        for($i = count($parts); $i < $protoCount; ++$i) {
          $parts[] = '';
        }
        foreach($parts as &$part) {
          $part = trim($part);
        }
        if (empty($parts[0]) && empty($parts[1])) {
          continue;
        }
        if (empty($parts[0])) {
          $parts[0] = $parts[1];
        }
        if (empty($parts[1])) {
          $parts[1] = $parts[0];
        }
        if (empty($parts[4])) {
          $parts[4] = 'active';
        }
        if ($trimInactive && $parts[4] === 'deleted') {
          continue;
        }
        $allowed[] = array('key' => $parts[0],
                           'label' => $parts[1],
                           'data' => $parts[2],
                           'tooltip' => $parts[3],
                           'flags' => $parts[4],
                           'limit' => $parts[5]);
        $last = count($allowed) - 1;
        for($i = $protoCount; $i < count($parts); $i++) {
          $allowed[$last]['column'.$i] = $parts[$i];
        }
      }
      if ($addProto) {
        $allowed[] = $proto;
      }
      return $allowed;
    }

    /**Implode a list of allowed values in the form
     *
     * array(0 => array('key' => ....))
     *
     * into a compact textual CSV description.
     */
    public static function implodeAllowedValues($values)
    {
      $proto = self::allowedValuesPrototype();
      $protoCount = count($proto);
      $result = '';
      foreach ($values as $value) {
        $value = array_merge($proto, $value);

        $key = empty($value['key']) ? $value['label'] : trim($value['key']);

        //error_log('implode: '.$key.' all '.print_r($value, true));

        if (empty($key)) {
          continue;
        }

        $label = empty($value['label']) ? $key : trim($value['label']);
        $limit = trim($value['limit']);
        $data  = trim($value['data']);
        $tip   = trim($value['tooltip']);
        $flags = trim($value['flags']);
        $csvData = [ $key, $label, $data, $tip, $flags, $limit ];
        for($i = $protoCount; $i < count(array_values($value)); $i++) {
          if (isset($value['column'.$i])) {
            $csvData[] = $value['column'.$i];
          }
        }
        $text = Util::quasiCSVJoin($csvData, ':');
        if (preg_match('/^:+$/', $text)) {
          continue;
        }
        $result .= "\n".$text;
      }
      return substr($result, 1); // strip leading "\n"
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
          $result = mySQL::insert(self::TABLE_NAME, $newVals, $handle, mySQL::IGNORE);

          if ($result === false) {
            \OCP\Util::writeLog(Config::APP_NAME,
                                __METHOD__.
                                ': mySQL error: '.mySQL::error($handle),
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
                                ': mySQL error: '.mySQL::error($handle).' query '.$query,
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
