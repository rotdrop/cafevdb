<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Ramsey\Uuid\Uuid;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\ProjectExtraFieldsService;
use OCA\CAFEVDB\Service\FuzzyInputService;
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
  //const OPTIONS_TABLE = 'ProjectExtraFieldValueOptions';
  const DATA_TABLE = 'ProjectExtraFieldsData';
  const PROJECTS_TABLE = 'Projects';

  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'master' => true,
      'entity' => Entities\ProjectExtraField::class,
    ],
    [
      'table' => self::PROJECTS_TABLE,
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
  ];

  protected $cassClass = self::CSS_CLASS;

  /** @var InstrumentationService */
  private $instrumentationService;

  /** @var FuzzyInput */
  private $fuzzyInput;

  /** @var ExtraFieldsService */
  private $extraFieldsService;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , InstrumentationService $instrumentationService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , FuzzyInputService $fuzzyInput
    , ProjectExtraFieldsService $extraFieldsService
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->instrumentationService = $instrumentationService;
    $this->fuzzyInput = $fuzzyInput;
    $this->extraFieldsService = $extraFieldsService;
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

    //$this->logInfo('PROJECT MODE '.$projectMode);

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
    $opts['sort_field'] = [ 'project_id', 'display_order', 'name' ];

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

    // field definitions

    $joinTables = $this->defineJoinStructure($opts);

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

    $opts['fdd']['project_id'] = [
      'name'      => $this->l->t('Project'),
      'input'     => ($projectMode ? 'R' : ''),
      'css' => [ 'postfix' => ' project-instrument-project-name' ],
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : -1),
      'sort'     => true,
      'values|ACP' => [
        'column'      => 'id',
        'description' => 'name',
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        //        'join'        => '$main_col_fqn = $join_col_fqn',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
      ],
      'values|DVFL' => [
        'column'      => 'id',
        'description' => 'name',
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
        'filters'     => '$table.id IN (SELECT project_id FROM $main_table)',
      ],
    ];
    $this->addSlug('project', $opts['fdd']['project_id']);

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
        'select'   => 'C',
        'maxlen'   => 1,
        'sort'     => true,
        'escape'   => false,
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ 1 => '' ],
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default'  => false,
        'tooltip'  => $this->toolTipsService['extra-fields-disabled']
      ];
    }

    $opts['fdd']['multiplicity'] =[
      'name'    => $this->l->t('Multiplicity'),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => ' multiplicity' ],
      'default' => 'simple',
      'values2' => $this->extraFieldMultiplicityNames,
      'valueTitles' => array_map(function($tag) { $this->toolTipsService['extra-field-multiplicity-'.$tag]; }, $this->extraFieldMultiplicities),
      'tooltip' => $this->toolTipsService['extra-field-multiplicity'],
    ];

    $opts['fdd']['data_type'] =[
      'name'    => $this->l->t('Data-Type'),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => ' data-type' ],
      'default' => 'text',
      'values2' => $this->extraFieldDataTypeNames,
      'valueTitles' => array_map(function($tag) { $this->toolTipsService['extra-field-data-type-'.$tag]; }, $this->extraFieldDataTypes),
      'tooltip' => $this->toolTipsService['extra-field-data-type'],
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
      'sql' => 'PMEtable0.allowed_values',
      'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($nameIdx, $tooltipIdx) {
        // provide defaults
        $protoRecord = array_merge(
          $this->extraFieldsService->allowedValuesPrototype(),
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
      'sql' => "SUBSTRING_INDEX(PMEtable0.allowed_values, ':', -1)",
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
      'sql' => 'PMEtable0.`default_value`',
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
      'sql' => 'PMEtable0.`default_value`',
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
        'column' => 'tab',
        'description' => 'tab',
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

    if ($expertMode) {

      $opts['fdd']['encrypted'] = [
        'name' => $this->l->t('Encrypted'),
        'css' => [ 'postfix' => ' encrypted' ],
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default' => false,
        'select' => 'C',
        'maxlen' => 1,
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
      $opts['filters'][] = 'NOT `PMEtable0`.`disabled` = 1';
      if ($projectMode === false) {
        $opts['filters'][] = 'NOT '.$joinTables[self::PROJECTS_TABLE].'.disabled = 1';
      }
    }
    if ($projectMode !== false) {
      $opts['filters'][] = 'PMEtable0.project_id = '.$this->projectId;
    }

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    // needed ?
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

    // make sure writer-acls are a subset of reader-acls
    $writers = preg_split('/\s*,\s*/', $newvals['writers'], -1, PREG_SPLIT_NO_EMPTY);
    $readers = preg_split('/\s*,\s*/', $newvals['readers'], -1, PREG_SPLIT_NO_EMPTY);
    $missing = array_diff($writers, $readers);
    if (!empty($missing)) {
      $readers = array_merge($readers, $missing);
      $newvals['Readers'] = implode(',', $readers);
    }

    /************************************************************************
     *
     * Move the data from default_multi_value to default_value s.t. PME
     * can do its work.
     *
     */
    $key = array_search('default_multi_value', $changed);
    if ($newvals['multiplicity'] === 'multiple' ||
        $newvals['multiplicity'] === 'parallel') {
      $newvals['default_value'] = $newvals['default_multi_value'];
      if ($key !== false) {
        $changed[] = 'default_value';
      }
    }
    unset($newvals['default_multi_value']);
    unset($oldvals['default_multi_value']);
    if ($key !== false) {
      unset($changed[$key]);
    }

    /************************************************************************
     *
     * Move the data from MaximumGroupSize to
     * allowed_values. Plural is "misleading" here, of course ;)
     *
     */
    $tag = "MaximumGroupSize";
    $key = array_search($tag, $changed);
    if ($types[$newvals['TypeId']]['multiplicity'] === 'groupofpeople')
    {
      $max = $newvals[$tag];
      if ($op === 'update' && !empty($newvals['allowed_values_single'][0])) {
        $maxdata = $newvals['allowed_values_single'];
        $maxdata[0]['column5'] = $max;
      } else {
        $maxdata = 'max:group:::active:'.$max;
      }
      $newvals['allowed_values'] = $maxdata;
      if ($key !== false) {
        $changed[] = 'allowed_values';
      }
    }
    unset($newvals[$tag]);
    unset($oldvals[$tag]);
    if ($key !== false) {
      unset($changed[$key]);
    }

    /************************************************************************
     *
     * Move the data from allowed_values_single to
     * allowed_values. Plural is "misleading" here, of course ;)
     *
     */
    $key = array_search('allowed_values_single', $changed);
    if ($types[$newvals['TypeId']]['multiplicity'] === 'single') {
      $newvals['allowed_values'] = $newvals['allowed_values_single'];
      if ($key !== false) {
        $changed[] = 'allowed_values';
      }
    }
    unset($newvals['allowed_values_single']);
    unset($oldvals['allowed_values_single']);
    if ($key !== false) {
      unset($changed[$key]);
    }

    /************************************************************************
     *
     * Sanitize allowed_values
     *
     */

    if (!is_array($newvals['allowed_values'])) {
      // textfield
      $allowed = $this->extraFieldsService->explodeAllowedValues($newvals['allowed_values']);

    } else {
      $allowed = $newvals['allowed_values'];
    }

    // make unused keys unique @TODO make it a uuid
    //self::allowedValuesUniqueKeys($allowed, $pme->rec);

    //error_log('trigger '.print_r($allowed, true));
    $newvals['allowed_values'] = $this->extraFieldsService->implodeAllowedValues($allowed);
    if ($oldvals['allowed_values'] !== $newvals['allowed_values']) {
      $changed[] = 'allowed_values';
    }

    /************************************************************************
     *
     * Move the data from default_single_value to default_value s.t. PME
     * can do its work.
     *
     */
    $key = array_search('default_single_value', $changed);
    if ($types[$newvals['TypeId']]['multiplicity'] === 'single') {
      $newvals['default_value'] = $newvals['default_single_value'];
      if ($key !== false) {
        $changed[] = 'default_value';
      }
    }
    unset($newvals['default_single_value']);
    unset($oldvals['default_single_value']);
    if ($key !== false) {
      unset($changed[$key]);
    }


    // $this->logInfo('OLD: '.print_r($oldvals['allowed_values'], true));
    // $this->logInfo('NEW: '.print_r($newvals['allowed_values'], true));
    // $this->logInfo('CHG: '.$changed['allowed_values']);

    /************************************************************************
     *
     * Add the data from NewTab to Tab s.t. PME can do its work.
     *
     */
    $key = array_search('new_tab', $changed);
    if (!empty($newvals['new_tab']) && empty($newvals['tab'])) {
      $newvals['tab'] = $newvals['new_tab'];
      $changed[] = 'tab';
    }
    unset($newvals['new_tab']);
    unset($oldvals['new_tab']);
    if ($key !== false) {
      unset($changed[$key]);
    }

    if (!empty($newvals['tool_tip'])) {
      $newvals['tool_tip'] = $this->fuzzyInput->purifyHTML($newvals['tool_tip']);
      if ($newvals['tool_tip'] !== $oldvals['tool_tip']) {
        $changed[] = 'tool_tip';
      } else {
        $key = array_search('new_tab', $changed);
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
    $pfx = $this->pme->cgiDataName('allowed_values');
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
      $html .= '<td class="field-'.$prop.' expert-mode-only">'
            .'<input'
            .($used || $deleted || true ? ' readonly="readonly"' : '')
            .' type="text"'
            .' class="field-key expert-mode-only"'
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
    $allowed = $this->extraFieldsService->explodeAllowedValues($value);
    if ($op === 'display' && count($allowed) == 1) {
      // "1" means empty (headerline)
      return '';
    }
    $protoCount = count($this->extraFieldsService->allowedValuesPrototype());
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
    $headers = [
      'key' => $this->l->t('Key'),
      'label' => $this->l->t('Label'),
      'limit' => $this->l->t('Limit'),
      'data' => $this->currencyLabel($this->l->t('Data')),
      'tooltip' => $this->l->t('Tooltip'),
    ];
    foreach ($headers as $key => $value) {
      $css = 'field-'.$key;
      if ($key == 'key') {
        $css .= ' expert-mode-only';
      }
      $html .=
            '<th'
            .' class="'.$css.'"'
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
            $css = 'field-'.$field;
            if ($field == 'key') {
              $css .= ' expert-mode-only';
            }
            $html .= '<td class="'.$css.'">'
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
        //$pfx = $this->pme->cgiDataName('allowed_values');
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
   * only for service-fee fields.
   */
  private function showAllowedSingleValue($value, $op, $toolTip, $protoRecord)
  {
    $allowed = $this->extraFieldsService->explodeAllowedValues($value, false);
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
    $name  = $this->pme->cgiDataName('allowed_values_single');
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
      '<span class="service-fee currency-amount">'.$money.'</span>'.
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
      '<span class="glue general currenylabel">/</span>'.
      '<span class="service-fee currencylabel">'
      .$this->l->t('Amount').' ['.$this->currencySymbol().']'
      .'</span>';
  }

}
