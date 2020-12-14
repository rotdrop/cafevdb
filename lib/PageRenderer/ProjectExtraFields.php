<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\PageRenderer\Util\FuzzyInput;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

/**Table generator for Instruments table. */
class ProjectExtraFields extends PMETableViewBase
{
  const CSS_CLASS = 'project-extra-fields';
  const TABLE = 'ProjectExtraFields';
  const TYPE_TABLE = 'ProjectExtraFieldTypes';
  //const OPTIONS_TABLE = 'ProjectExtraFieldValueOptions';
  const DATA_TABLE = 'ProjectExtraFieldsData';
  const PROJECTS_TABLE = 'Projects';

  /** @var InstrumentationService */
  private $instrumentationService;

  /** @var FuzzyInput */
  private $fuzzyInput;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , InstrumentationService $instrumentationService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , FuzzyInput $fuzzyInput
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->instrumentationService = $instrumentationService;
    $this->fuzzyInput = $fuzzyInput;
  }

  public function cssClass() {
    return self::CSS_CLASS;
  }

  public function shortTitle()
  {
    if ($this->projectId > 0) {
      return $this->l->t("Extra-Fields for Project %s",
                         array($this->projectName));
    } else {
      return $this->l->t("Extra Fields for Projects");
    }
  }

  public function headerText()
  {
    return $this->shortTitle();
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
    $recordsPerPage  = $this->recordsPerPage;

    $opts = [];

    $expertMode = $this->getUserValue('expertmode');

    $projectMode  = $projectId > 0;

    $tableTabs   = $this->instrumentationService->tableTabs(null, true);
    $tableTabValues2 = [];
    foreach ($tableTabs as $tabInfo) {
      $tableTabValues2[$tabInfo['id']] = $tabInfo['name'];
    }

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = ' show-hide-disabled';

    $template = 'project-extra-fields';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [ 'project_id', 'DisplayOrder', 'Name' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [
          'id' => 'definition',
          'default' => true,
          'tooltip' => $this->l->t('Definition of name, type, allowed values, default values.'),
          'name' => $this->l->t('Defintion'),
        ],
        [
          'id' => 'display',
          'tooltip' => $this->l->t('Ordering, linking to and defining newe tabs, '.
                                   'definition of tooltips (help text).'),
          'name' => $this->l->t('Display'),
        ],
        [
          'id' => 'advanced',
          'toolttip' => $this->l->t('Advanced settings and information, restricted access, '.
                                    'encryption, information about internal indexing.'),
          'name' => $this->l->t('Advanced'),
        ],
        [
          'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];

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
    $allProjects = $this->getDatabaseRepository(Entities\Project::class)->findAll();
    $projectQueryValues = [ '*' => '*' ]; // catch-all filter
    $projectQueryValues[''] = $this->l->t('no projects yet');
    $projects = [];
    $groupedProjects = [];
    foreach ($allProjects as $proj) {
      $id = $proj['id'];
      $name = $proj['Name'];
      $year = $proj['Year'];
      $projectQueryValues[$id] = $year.': '.$name;
      $projects[$id] = $name;
      $groupedProjects[$id] = $year;
    }

    $projectIdx = 0; // just the start here count($opts['fdd']);
    $opts['fdd']['project_id'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'      => $this->l->t('Project-Name'),
      'css' => [ 'postfix' => ' project-extra-project-name' ],
      'options'   => ($projectMode ? 'VCDAPR' : 'FLVCDAP'),
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : -1),
      'sort'     => true,
      'values|ACP' => [
        'table' => self::PROJECTS_TABLE,
        'column' => 'id',
        'description' => 'name',
        'groups' => 'year',
        'orderby' => '$table.`year` DESC',
        'join' => '$main_table.project_id = $join_table.Id',
      ],
      'values|DVFL' => [
        'table' => self::PROJECTS_TABLE,
        'column' => 'id',
        'description' => 'name',
        'groups' => 'year',
        'orderby' => '$table.`year` DESC',
        'join' => '$main_table.`project_id` = $join_table.`id`',
        'filters' => '$table.`id` IN (SELECT `project_id` FROM $main_table)',
      ],
    ];

    $tooltipIdx = -1;
    $nameIdx = count($opts['fdd']);
    $opts['fdd']['name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name' => $this->l->t('Field-Name'),
      'css' => [ 'postfix' => ' field-name' ],
      'select' => 'T',
      'maxlen' => 29,
      'size' => 30,
      'sort' => true,
      'tooltip' => $this->toolTipsService['extra-fields-field-name'],
    ];

    if ($this->showDisabled) {
      $opts['fdd']['disabled'] = [
        'tab'      => [ 'id' => 'definition' ],
        'name'     => $this->l->t('Disabled'),
        'css'      => [ 'postfix' => ' extra-field-disabled' ],
        'values2|CAP' => [ 1 => '' ],
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default'  => '',
        'select'   => 'O',
        'sort'     => true,
        'tooltip'  => $this->toolTipsService['extra-fields-disabled']
      ];
    }

    // TODO: maybe get rid of enums and sets alltogether
    $typeValues = [];
    $typeGroups = [];
    $typeData = [];
    $typeTitles = [];

    $types = $this->fieldTypes();
    if (!empty($types)) {
      foreach ($types as $id => $typeInfo) {
        $name = $typeInfo['name'];
        $multiplicity = $typeInfo['multiplicity'];
        $group = $typeInfo['kind'];

        $typeValues[$id] = $this->l->t($name);
        $typeGroups[$id] = $this->l->t($group);
        $typeData[$id] = json_encode(
          [
            'Multiplicity' => $multiplicity,
            'Group' => $group,
          ]
        );
        $typeTitles[$id] = $this->toolTipsService['extra-field-'.$group.'-'.$multiplicity];
      }
    }

    $opts['fdd']['type_id'] = [
      'tab'      => [ 'id' => 'definition' ],
      'name' => $this->l->t('Type'),
      'css' => [ 'postfix' => ' field-type' ],
      'php|VD' => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($typeData) {
        $key = $row['qf'.$field];
        return '<span class="data" data-data=\''.$typeData[$key].'\'></span>'.$value;
      },
      'size' => 30,
      'maxlen' => 24,
      'select' => 'D',
      'sort' => true,
      'tooltip' => $this->toolTipsService['extra-fields-type'],
      'values2' => $typeValues,
      'valueGroups' => $typeGroups,
      'valueData' => $typeData,
      'valueTitles' => $typeTitles,
    ];

    $opts['fdd']['allowed_values'] = [
      'name' => $this->l->t('Allowed Values'),
      'css|LF' => [ 'postfix' => ' allowed-values hide-subsequent-lines' ],
      'css' => ['postfix' => ' allowed-values' ],
      'select' => 'T',
      'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        return $this->showAllowedValues($value, $op, $recordId);
      },
      'maxlen' => 1024,
      'size' => 30,
      'sort' => true,
      'display|LF' => [ 'popup' => 'data' ],
      'tooltip' => $this->toolTipsService['extra-fields-allowed-values'],
    ];

    $opts['fdd']['allowed_values_single'] = [
      'name' => $this->currencyLabel($this->l->t('Data')),
      'css' => [ 'postfix' => ' allowed-values-single' ],
      'sql' => 'PMEtable0.AllowedValues',
      'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx, $tooltipIdx) {
        // provide defaults
        $protoRecord = array_merge(
          $this->allowedValuesPrototype(),
          [
            'key' => $recordId,
            'label' => $row['qf'.$nameIdx],
            'tooltip' => $row['qf'.$tooltipIdx]
          ]);
        return $this->showAllowedSingleValue($value, $op, $fdd[$field]['tooltip'], $protoRecord);
      },
      'options' => 'ACDPV',
      'select' => 'T',
      'maxlen' => 29,
      'size' => 30,
      'sort' => true,
      'tooltip' => $this->toolTipsService['extra-fields-allowed-values-single'],
    ];

    // Provide "cooked" valus for up to 20 members. Perhaps the
    // max. number should somehow be adjusted ...
    $values2 = [];
    $dpy = 0;
    $values2[$dpy] = $dpy;
    for ($dpy = 2; $dpy < 10; ++$dpy) {
      $values2[$dpy] = $dpy;
    }
    for (; $dpy <= 30; $dpy += 5) {
      $values2[$dpy] = $dpy;
    }
    $opts['fdd']['maximum_group_size'] = [
      'name' => $this->l->t('Maximum Size'),
      'css' => [ 'postfix' => ' no-search maximum-group-size' ],
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
      'tooltip' => $this->toolTipsService['extra-fields-maximum-group-size'],
    ];

    $opts['fdd']['default_value'] = [
      'name' => $this->l->t('Default Value'),
      'css' => [ 'postfix' => ' default-value' ],
      'select' => 'T',
      'maxlen' => 29,
      'size' => 30,
      'sort' => true,
      'display|LF' => [ 'popup' => 'data' ],
      'tooltip' => $this->toolTipsService['extra-fields-default-value'],
    ];

    $opts['fdd']['default_multi_value'] = [
      'name' => $this->l->t('Default Value'),
      // 'input' => 'V', // not virtual, update handled by trigger
      'options' => 'CPA',
      'sql' => 'PMEtable0.`DefaultValue`',
      'css' => [ 'postfix' => ' default-multi-value allow-empty' ],
      'select' => 'D',
      'values' => [
        'table' => "SELECT id AS field_id,
    JSON_VALUE(JSON_QUERY(allowed_values, CONCAT('$[', n.n, ']')),'$.key') AS 'key',
    JSON_VALUE(JSON_QUERY(allowed_values, CONCAT('$[', n.n, ']')),'$.value') AS 'value',
    JSON_VALUE(JSON_QUERY(allowed_values, CONCAT('$[', n.n, ']')),'$.label') AS 'label',
    JSON_VALUE(JSON_QUERY(allowed_values, CONCAT('$[', n.n, ']')),'$.flags') AS 'flags'
  FROM `ProjectExtraFields`
  JOIN `numbers` n
    ON JSON_LENGTH(allowed_values) >= n.n",
        'column' => 'value',
        'description' => 'label',
        'filters' => '$table.`field_id` = $record_id AND $table.`flags` = \'deleted\'',
        'join' => '$join_table.$join_column = $main_table.`default_value`'
      ],
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['extra-fields-default-multi-value'],
    ];

    $opts['fdd']['default_single_value'] = [
      'name' => $this->l->t('Default Value'),
      // 'input' => 'V', // not virtual, update handled by trigger
      'options' => 'CPA',
      'sql' => 'PMEtable0.`DefaultValue`',
      'css' => [ 'postfix' => ' default-single-value' ],
      'select' => 'O',
      'values2' => [ '0' => $this->l->t('false'),
                     '1' => $this->l->t('true') ],
      'default' => '0',
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['extra-fields-default-single-value'],
    ];

    $tooltipIdx = count($opts['fdd']);
    $opts['fdd']['tool_tip'] = [
      'tab'      => [ 'id' => 'display' ],
      'name' => $this->l->t('Tooltip'),
      'css' => [ 'postfix' => ' extra-field-tooltip hide-subsequent-lines' ],
      'select' => 'T',
      'textarea' => [ 'rows' => 5,
                      'cols' => 28 ],
      'maxlen' => 1024,
      'size' => 30,
      'sort' => true,
      'escape' => false,
      'display|LF' => [ 'popup' => 'data' ],
      'tooltip' => $this->toolTipsService['extra-fields-tooltip'],
    ];

    $opts['fdd']['display_order'] = [
      'name' => $this->l->t('Display-Order'),
      'css' => [ 'postfix' => ' display-order' ],
      'select' => 'N',
      'maxlen' => 5,
      'sort' => true,
      'align' => 'right',
      'tooltip' => $this->toolTipsService['extra-fields-display-order'],
    ];

    $opts['fdd']['tab'] = [
      'name' => $this->l->t('Table Tab'),
      'css' => [ 'postfix' => ' tab allow-empty' ],
      'select' => 'D',
      'values' => [
        'table' => self::TABLE,
        'column' => 'Tab',
        'description' => 'Tab',
      ],
      'values2' => $tableTabValues2,
      'default' => -1,
      'maxlen' => 128,
      'size' => 30,
      'sort' => true,
      'tooltip' => $this->toolTipsService['extra-fields-tab'],
    ];

    if ($recordMode) {
      // In order to be able to add a new tab, the select box first
      // has to be emptied (in order to avoid conflicts).
      $opts['fdd']['new_tab'] = [
        'name' => $this->l->t('New Tab Name'),
        'options' => 'CPA',
        'sql' => "''",
        'css' => [ 'postfix' => ' new-tab' ],
        'select' => 'T',
        'maxlen' => 20,
        'size' => 30,
        'sort' => false,
        'tooltip' => $this->toolTipsService['extra-fields-new-tab'],
      ];
    }

    // outside the expertmode "if", this is the index!
    $opts['fdd']['id'] = [
      'tab'      => ['id' => 'advanced' ],
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH',
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'align'    => 'right',
      'default'  => '0', // auto increment
      'sort'     => true,
    ];

    if ($expertMode) {

      $opts['fdd']['encrypted'] = [
        'name' => $this->l->t('Encrypted'),
        'css' => [ 'postfix' => ' encrypted' ],
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default' => '',
        'select' => 'O',
        'maxlen' => 5,
        'sort' => true,
        'tooltip' => $this->toolTipsService['extra-fields-encrypted'],
      ];

      // @TODO wildcards?
      $cloudGroups = $this->groupManager()->search('');
      $opts['fdd']['readers'] = [
        'name' => $this->l->t('Readers'),
        'css' => [ 'postfix' => ' readers user-groups' ],
        'select' => 'M',
        'values' => $cloudGroups,
        'maxlen' => 10,
        'sort' => true,
        'display' => [ 'popup' => 'data' ],
        'tooltip' => $this->toolTipsService['extra-fields-readers'],
      ];

      $opts['fdd']['writers'] = [
        'name' => $this->l->t('Writers'),
        'css' => [ 'postfix' => ' writers chosen-dropup_ user-groups' ],
        'select' => 'M',
        'values' => $cloudGroups,
        'maxlen' => 10,
        'sort' => true,
        'display' => [ 'popup' => 'data' ],
        'tooltip' => $this->toolTipsService['extra-fields-writers'],
      ];
    }

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    $opts['filters'] = [];
    if (!$this->showDisabled) {
      $opts['filters'][] = 'NOT `PMEtable0`.`Disabled` = 1';
      if ($projectMode === false) {
        $opts['filters'][] = 'NOT `PMEjoin'.$projectIdx.'`.`Disabled` = 1';
      }
    }
    if ($projectMode !== false) {
      $opts['filters'][] = 'PMEtable0.project_id = '.$this->projectId;
    }

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    $opts['triggers']['update']['before'][] = [ __CLASS__, 'beforeUpdateRemoveUnchanged' ];

    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertTrigger' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];

    $opts['triggers']['delete']['before'][]  = [ $this, 'beforeDeleteTrigger' ];

    // $opts['triggers']['filter']['pre'][]  =
    //   $opts['triggers']['update']['pre'][]  =
    //   $opts['triggers']['insert']['pre'][]  = 'CAFEVDB\ProjectExtra::preTrigger';

    // $opts['triggers']['insert']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';
    // $opts['triggers']['update']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';
    // $opts['triggers']['delete']['after'][]  = 'CAFEVDB\ProjectExtra::afterTrigger';

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateOrInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
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
    $types = $this->fieldTypes();
    if ($types[$newvals['TypeId']]['multiplicity'] === 'multiple' ||
        $types[$newvals['TypeId']]['multiplicity'] === 'parallel') {
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
    if ($types[$newvals['TypeId']]['multiplicity'] === 'groupofpeople')
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
    if ($types[$newvals['TypeId']]['multiplicity'] === 'single') {
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
      $allowed = $this->explodeAllowedValues($newvals['AllowedValues']);

    } else {
      $allowed = $newvals['AllowedValues'];
    }

    // make unused keys unique @TODO make it a uuid
    self::allowedValuesUniqueKeys($allowed, $pme->rec);

    //error_log('trigger '.print_r($allowed, true));
    $newvals['AllowedValues'] = $this->implodeAllowedValues($allowed);
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
    if ($types[$newvals['TypeId']]['multiplicity'] === 'single') {
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
      $newvals['ToolTip'] = $this->fuzzyInput->purifyHTML($newvals['ToolTip']);
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

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @todo Is this necessary? Just require the project-id not to be empty?
   */
  public function beforeInsertTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    $projectId = $newvals['project_id'];
    if ($projectId <= 0) {
      $projectId = $newvals['project_id'] = $this->projectId;
    }

    if (empty($projectId) || $projectId < 0) {
      return false;
    }

    // Oh well. This is the only place this is used and presumably was
    // purely cosmetic.

    // // insert the beast with the next available field id
    // $index = mySQL::selectFirstHoleFromTable(self::TABLE_NAME, 'FieldIndex',
    //                                          "`project_id` = ".$projectId,
    //                                          $pme->dbh);

    // if ($index === false) {
    //   return false;
    // }

    // $newvals['FieldIndex'] = $index;

    return true;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   */
  public static function beforeDeleteTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    $used = $this->usedFields(-1, $pme->rec);

    if (count($used) === 1 && $used[0] == $pme->rec) {
      $this->disable($pme->rec);
      return false;
    }
    return true;
  }

  private function fieldTypes()
  {
    return $this->getDatabaseRepository(Entities\ProjectExtraFieldType::class)
                ->indexedBy('id');
  }

  private function usedFields($projectId = -1, $fieldId = -1)
  {
    return $this->getDatabaseRepository(Entities\ProjectExtraFieldDatum::class)
                ->usedFields($projectId, $fieldId);
  }

  private function fieldValues($fieldId)
  {
    return $this->getDatabaseRepository(Entities\ProjectExtraFieldDatum::class)
                ->fieldValues($fieldId);
  }

  private function disable($fieldId, $disable = true)
  {
    $this->getDatabaseRepository(Entities\ProjectExtraField::class)
         ->disable($fieldId);
  }

  /**
   * Generate a row given values and index for the "change" view
   * corresponding to the multi-choice fields.
   *
   * @param array $value One row of the form as returned form
   * self::explodeAllowedValues()
   *
   * @param integer $index A unique row number.
   *
   * @param boolean $used Whether the DB already contains data
   * records referring to this item.
   *
   * @return string HTML data for one row.
   */
  public function allowedValueInputRow($value, $index = -1, $used = false)
  {
    $pfx = $this->pme->cgiDataName('AllowedValues');
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
            .' title="'.$this->toolTipsService['extra-fields-delete-undelete'].'"'
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
           .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.($placeHolder ? 'placeholder' : $prop)].'"'
           .' placeholder="'.($placeHolder ? $this->l->t('new option') : '').'"'
           .' size="33"'
           .' maxlength="32"'
           .'/>';
    if (!$placeHolder) {
      // key
      // TODO: use a UUID
      $prop = 'key';
      $html .= '<td class="field-'.$prop.'">'
            .'<input'
            .($used || $deleted ? ' readonly="readonly"' : '')
            .' type="text"'
            .' class="field-key"'
            .' name="'.$pfx.'['.$index.']['.$prop.']"'
            .' value="'.$value[$prop].'"'
            .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$prop].'"'
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
            .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$prop].'"'
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
            .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$prop].'"'
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
            .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$prop].'"'
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
              .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$prop].'"'
              .' maxlength="8"'
              .' size="9"'
              .'/></td>';
      }

    } else {
      $html .= '<td class="placeholder" colspan="6">'
            .$label;
      foreach (['key', 'limit', 'data', 'tooltip'] as $prop) {
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

  /**
   * Generate a table in order to define field-valus for
   * multi-select stuff.
   */
  private function showAllowedValues($value, $op, $recordId)
  {
    $allowed = $this->explodeAllowedValues($value);
    if ($op === 'display' && count($allowed) == 1) {
      // "1" means empty (headerline)
      return '';
    }
    $protoCount = count($this->allowedValuesPrototype());
    $maxColumns = 0;
    foreach($allowed as $value) {
      $maxColumns = max(count($value), $maxColumns);
    }
    $html = '<div class="pme-cell-wrapper quarter-sized">';
    if ($op === 'add' || $op === 'change') {
      $showDeletedLabel = $this->l->t("Show deleted items.");
      $showDeletedTip = $this->toolTipsService['extra-fields-show-deleted'];
      $showDataLabel = $this->l->t("Show data-fields.");
      $showDataTip = $this->toolTipsService['extra-fields-show-data'];
      $html .=<<<__EOT__
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
    $headers = array('key' => $this->l->t('Key'),
                     'label' => $this->l->t('Label'),
                     'limit' => $this->l->t('Limit'),
                     'data' => $this->currencyLabel($this->l->t('Data')),
                     'tooltip' => $this->l->t('Tooltip'));
    foreach ($headers as $key => $value) {
      $html .=
            '<th'
            .' class="field-'.$key.'"'
            .' title="'.$this->toolTipsService['extra-fields-allowed-values:'.$key].'"'
            .'>'
            .$value
            .'</th>';
    }
    for ($i = $protoCount; $i < $maxColumns; ++$i) {
      $key = 'column'.$i;
      $value = $this->l->t('%dth column', [ $i ]);
      $html .=
            '<th'
            .' class="field-'.$key.'"'
            .' title="'.$this->toolTipsService('extra-fields-allowed-values:'.$key).'"'
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
          foreach (['key', 'label', 'limit', 'data', 'tooltip'] as $field) {
            $html .= '<td class="field-'.$field.'">'
                  .($field === 'data'
                    ? $this->currencyValue($value[$field])
                    : $value[$field])
                  .'</td>';
          }
          for ($i = $protoCount; $i < count($value); ++$i) {
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
        $usedKeys = $this->fieldValues($recordId);
        //error_log(print_r($usedKeys, true));
        //$pfx = $this->pme->cgiDataName('AllowedValues');
        //$css = 'class="allowed-values"';
        foreach ($allowed as $idx => $value) {
          if (!empty($value['key'])) {
            $key = $value['key'];
            $used = array_search($key, $usedKeys) !== false;
          } else {
            $used = false;
          }
          $html .= $this->allowedValueInputRow($value, $idx, $used);
        }
        break;
    }
    $html .= '
  </tbody>
</table></div>';
    return $html;
  }

  /**
   * Display the input stuff for a single-value choice, probably
   * only for surcharge fields.
   */
  private function showAllowedSingleValue($value, $op, $toolTip, $protoRecord)
  {
    $allowed = $this->explodeAllowedValues($value, false);
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
      return $this->currencyValue($value);
    }
    empty($entry) && $entry = $protoRecord;
    $protoCount = count($protoRecord);
    $name  = $this->pme->cgiDataName('AllowedValuesSingle');
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
    foreach (['key', 'label', 'limit', 'tooltip', 'flags'] as $field) {
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
    foreach ($allowed as $idx => $item) {
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
      for ($i = $protoCount; $i < count($item); ++$i) {
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

  /**Return a currency value where the number symbol can be hidden
   * by CSS.
   */
  private function currencyValue($value)
  {
    $money = $this->moneyValue($value);
    return
      '<span class="surcharge currency-amount">'.$money.'</span>'.
      '<span class="general">'.$value.'</span>';
  }

  /**
   * Return an alternate "Amount [CUR]" label which can be hidden by
   * CSS.
   */
  private function currencyLabel($label = 'Data')
  {
    return
      '<span class="general">'.$label.'</span>'.
      '<span class="surcharge currencylabel">'
      .$this->l->t('Amount').' ['.$this->currencySymbol().']'
      .'</span>';
  }

  /**
   * Prototype for allowed values, i.e. multiple-value options.
   */
  private static function allowedValuesPrototype()
  {
    return [
      'key' => false,
      'label' => false,
      'data' => false,
      'tooltip' => false,
      'flags' => 'active',
      'limit' => false,
    ];
  }

  /**
   * Explode the given json encoded string into a PHP array.
   */
  public function explodeAllowedValues($values, $addProto = true, $trimInactive = false)
  {
    $options = empty($values) ? [] : json_decode($values);
    if (count($options) > 0 && array_keys($options) !== range(0, count($options) - 1)) {
      throw new \InvalidArgumentException($this->l->t('Value options do not yield a sequential array'));
    }
    $protoType = $this->allowedValuesPrototype();
    $protoKeys = array_keys($protoType);
    foreach ($options as $index => &$option) {
      $keys = array_keys($option);
      if ($keys !== $protoKeys) {
        throw new \InvalidArgumentException(
          $this->l->t('Prototype keys "%s" and options keys "%s" differ',
                      [ implode(',', $protoKeys), implode(',', $keys) ])
        );
      }
      if ($trimInactive && $option['disabled'] === true) { //  @TODO check for string boolean conversion
        unset($option);
      }
    }
    if ($addProto) {
      $options[] = $this->allowedValuesPrototype();
    }
    return $options;
  }

  /**
   * Serialize a list of allowed values in the form
   * ```
   * [
   *   [ 'key' => KEY1, ... ],
   *   [ 'key' => KEY2, ... ],
   * ]
   * ```
   * as json for storing in the database.
   */
  public static function implodeAllowedValues($options)
  {
    $proto = $this->allowedValuesPrototype();
    foreach ($options as &$option) {
      $option = array_merge($proto, $option);
    }
    return json_encode($values);
  }
}
