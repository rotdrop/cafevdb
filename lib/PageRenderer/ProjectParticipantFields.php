<?php
/**
 * Orchestra member, musician and project management application.
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

use \OCA\CAFEVDB\Wrapped\Carbon\Carbon as DateTime;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

/**Table generator for Instruments table. */
class ProjectParticipantFields extends PMETableViewBase
{
  const TEMPLATE = 'project-participant-fields';
  const TABLE = self::PROJECT_PARTICIPANT_FIELDS_TABLE;
  const OPTIONS_TABLE = self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE;
  const DATA_TABLE = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE;

  const OPTION_FIELDS = [ 'key', 'label', 'data', 'deposit', 'limit', 'tooltip', 'deleted', ];

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\ProjectParticipantField::class,
      'identifier' => [ 'id' => 'id' ],
    ],
    self::OPTIONS_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDataOption::class,
      'identifier' => [
        'field_id' => 'id',
        'key' => false,
      ],
      'column' => 'key',
      'encode' => 'BIN2UUID(%s)',
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    self::DATA_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'field_id' => 'id',
        'project_id' => 'project_id',
        'musician_id' => false,
      ],
      'column' => 'musician_id',
    ],
  ];

  /** @var InstrumentationService */
  private $instrumentationService;

  /** @var FuzzyInput */
  private $fuzzyInput;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , InstrumentationService $instrumentationService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , FuzzyInputService $fuzzyInput
    , ProjectParticipantFieldsService $participantFieldsService
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->instrumentationService = $instrumentationService;
    $this->fuzzyInput = $fuzzyInput;
    $this->participantFieldsService = $participantFieldsService;
  }

  public function shortTitle()
  {
    if ($this->projectId > 0) {
      return $this->l->t("Participant-Fields for Project %s",
                         array($this->projectName));
    } else {
      return $this->l->t("Extra Fields for Projects");
    }
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

    $opts['css']['postfix'] = [
      'show-hide-disabled',
    ];

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
    $opts['sort_field'] = [ 'project_id', '-display_order', 'name' ];

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
      'tab'       => [ 'id' => 'tab-all' ],
      'name'      => $this->l->t('Project'),
      'input'     => ($projectMode ? 'HR' : 'M'),
      'css' => [ 'postfix' => [ 'project-instrument-project-name', ], ],
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : -1),
      'sort'     => true,
      'values|ACP' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ 'name' ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        //        'join'        => '$main_col_fqn = $join_col_fqn',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
      ],
      'values|DVFL' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ 'name' ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
        'filters'     => '$table.id IN (SELECT project_id FROM $main_table)',
      ],
    ];
    $this->addSlug('project', $opts['fdd']['project_id']);

    $opts['fdd']['name'] = array_merge(
      [
        'tab'      => [ 'id' => 'tab-all' ],
        'name' => $this->l->t('Field-Name'),
        'css' => [ 'postfix' => [ 'field-name', ], ],
        'input' => 'M',
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-field-name'],
      ],
      $this->makeFieldTranslationFddValues($this->joinStructure[self::TABLE], 'name')
    );

    $opts['fdd']['usage'] = [
      'tab' => [ 'id' => [ 'advanced' ] ],
      'name' => $this->l->t('#Usage'),
      'sql' => 'COUNT(DISTINCT '.$joinTables[self::DATA_TABLE].'.musician_id)',
      'css' => [ 'postfix' => [ 'participant-fields-usage', ], ],
      'select' => 'N',
      'align' => 'right',
      'input' => 'V',
      'sort' => true,
      'tooltip' => $this->toolTipsService['participant-fields-usage'],
    ];

    $opts['fdd']['deleted'] = array_merge(
      $this->defaultFDD['deleted'], [
        'tab' => [ 'id' => 'advanced' ],
        'name' => $this->l->t('Deleted'),
        'css'      => [ 'postfix' => [ 'participant-field-disabled', ], ],
        'tooltip'  => $this->toolTipsService['participant-fields-disabled'],
        'input' => ($this->showDisabled) ? 'T' : 'RH',
      ]);

    $opts['fdd']['multiplicity'] =[
      'name'    => $this->l->t('Multiplicity'),
      'tab' => [ 'id' => 'definition' ],
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => [ 'multiplicity', ], ],
      'default' => Multiplicity::SIMPLE,
      'values2' => $this->participantFieldMultiplicityNames(),
      'valueTitles' => Util::arrayMapAssoc(function($key, $tag) {
        return [ $tag, $this->toolTipsService['participant-field-multiplicity' . ':' . $tag] ];
      }, Multiplicity::toArray()),
      'tooltip' => $this->toolTipsService['participant-field-multiplicity'],
      'valueData' => array_map('json_encode', $this->participantFieldsService->multiplicityTypeMask()),
    ];

    $dataTypeIndex = count($opts['fdd']);
    $opts['fdd']['data_type'] =[
      'name'    => $this->l->t('Data-Type'),
      'tab' => [ 'id' => 'definition' ],
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => [ 'data-type', ], ],
      'default' => 'text',
      'values2' => $this->participantFieldDataTypeNames(),
      'valueTitles' => Util::arrayMapAssoc(function($key, $tag) {
        return [ $tag, $this->toolTipsService['participant-field-data-type' . ':' . $tag] ];
      }, DataType::toArray()),
      'tooltip' => $this->toolTipsService['participant-field-data-type'],
    ];

    $opts['fdd']['due_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['due_date'], [
        'tab' => [ 'id' => 'definition' ],
        'css' => [ 'postfix' => [ 'service-fee-data-type-required', ], ],
      ]);

    $opts['fdd']['deposit_due_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['due_date'], [
        'tab' => [ 'id' => 'definition' ],
        'name' =>  $this->l->t('Deposit Due Date'),
        'css' => [ 'postfix' => [ 'deposit-due-date' ], ],
      ]);

    /**************************************************************************
     *
     * Define field for the option values table in order to fetch the
     * old state automatically.
     *
     */

    foreach (self::OPTION_FIELDS as $column) {
      $this->makeJoinTableField(
        $opts['fdd'], self::OPTIONS_TABLE, $column, [
          'input' => 'H',
          'sql' => ($column == 'key')
          ? "GROUP_CONCAT(DISTINCT BIN2UUID(\$join_col_fqn) ORDER BY \$join_col_fqn ASC)"
          : "GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(\$join_table.key), '".parent::JOIN_KEY_SEP."', \$join_col_fqn) ORDER BY \$join_table.key ASC)",
      ]);
    }

    /*
     *
     *
     *************************************************************************/

    $opts['fdd']['data_options'] = [
      'name' => $this->l->t('Data Options'),
      'input' => 'SR',
      'css|LF' => [ 'postfix' => [ 'data-options hide-subsequent-lines', ], ],
      'css' => ['postfix' => ' data-options' ],
      'css|VD' => [ 'postfix' => [ 'data-options data-options-single', ], ],
      'select' => 'T',
      'sort' => true,
      'display|LF' => [ 'popup' => 'data', ],
      'tooltip' => $this->toolTipsService['participant-fields-data-options'],
      // 'sqlBug-as-of-mariadb-10.5.9'=> 'CONCAT("[",JSON_ARRAYAGG(DISTINCT
      //    JSON_OBJECT(
      //    "key", BIN2UUID($join_table.key),
      //    "label", $join_table.label,
      //    "data", $join_table.data,
      //    "tooltip", $join_table.tooltip,
      //    "flags", IF($join_table.deleted IS NULL, "active", "deleted"),
      //    "limit", $join_table.`limit`
      //    ) ORDER BY $join_table.label),"]")',
      'sql'=> 'CONCAT("[",GROUP_CONCAT(DISTINCT
  JSON_OBJECT(
    "key", BIN2UUID($join_table.key)
    , "label", $join_table.label
    , "data", $join_table.data
    , "deposit", $join_table.deposit
    , "limit", $join_table.`limit`
    , "tooltip", $join_table.tooltip
    , "deleted", $join_table.deleted
) ORDER BY $join_table.label),"]")',
      'values' => [
        'column' => 'key',
        'join' => [ 'reference' => $joinTables[self::OPTIONS_TABLE] ],
      ],
      'php' => function($dataOptions, $op, $field, $row, $recordId, $pme) {
        $multiplicity = $row['qf'.$pme->fdn['multiplicity']]??null;
        $dataType = $row['qf'.$pme->fdn['data_type']]??null;
        return $this->showDataOptions($dataOptions, $op, $recordId['id']??null, $multiplicity, $dataType);
      },
    ];

    foreach (['groupofpeople' => '', 'single' => '', 'simple' => $this->l->t('Default') . ' '] as $variant => $prefix) {
      $opts['fdd']['data_options_' . $variant] = [
        'name' => $this->currencyLabel($this->l->t('Data'), $prefix),
        'css' => [ 'postfix' => [ 'data-options-' . $variant ] ],
        'sql' => '$main_table.id',
        'php' => function($dummy, $op, $field, $row, $recordId, $pme) use ($variant) {
          // allowed values from virtual JSON aggregator field
          $dataOptions = $row['qf'.$pme->fdn['data_options']]??[];
          $multiplicity = $row['qf'.$pme->fdn['multiplicity']]??null;
          $dataType = $row['qf'.$pme->fdn['data_type']]??null;
          return $this->showAllowedSingleValue($dataOptions, $op, $pme->fdd[$field]['tooltip'], $multiplicity, $dataType, $variant);
        },
        'input' => 'SR',
        'options' => 'ACP', // but not in list/view/delete-view
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-data-options' . ':' . $variant],
      ];

      $opts['fdd']['deposit_' . $variant] = [
        'name' => $prefix . $this->l->t('Deposit').' ['.$this->currencySymbol().']',
        'css' => [
          'postfix' => [
            'deposit-' . $variant,
            'multiplicity-' . $variant . '-set-deposit-due-date-required',
          ],
        ],
        'sql' => '$main_table.id',
        'php' => function($dummy, $op, $pmeField, $row, $recordId, $pme) use ($variant) {
          // allowed values from virtual JSON aggregator field
          $dataOptions = $row['qf'.$pme->fdn['data_options']]??[];
          $multiplicity = $row['qf'.$pme->fdn['multiplicity']]??null;
          $dataType = $row['qf'.$pme->fdn['data_type']]??null;
          list($entry,) = $this->getAllowedSingleValue($dataOptions, $multiplicity, $dataType);
          $key = $entry['key'];
          $name  = $this->pme->cgiDataName('data_options_' . $variant);
          $field = 'deposit';
          $value = htmlspecialchars($entry[$field]);
          $tip = $pme->fdd[$pmeField]['tooltip'];
          $html =<<<__EOT__
            <div class="active-value">
            <input class="pme-input data-options-{$variant} multiplicity-{$variant}-set-deposit-due-date-required"
            type="number"
            step="0.01"
            maxlength="29"
            size="30"
            value="{$value}"
            name="{$name}[{$key}][{$field}]"
            title="{$tip}"
            required
            />
            </div>
__EOT__;
          return $html;
        },
        'input' => 'VSR',
        'options' => 'ACP', // but not in list/view/delete-view
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-deposit-' . $variant],
      ];
    }

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
      'sql' => 'MAX(IF($join_table.deleted IS NULL, $join_table.limit, 0))',
      'input' => 'S',
      'input|DV' => 'V',
      'options' => 'ACDPV',
      'select' => 'D',
      'maxlen' => 29,
      'size' => 30,
      'sort' => true,
      'default' => key($values2),
      'values2' => $values2,
      'tooltip' => $this->toolTipsService['participant-fields-maximum-group-size'],
      'values' => [
        'column' => 'key',
        'join' => [ 'reference' => $joinTables[self::OPTIONS_TABLE] ],
      ],
    ];

    $opts['fdd']['default_value'] = [
      'name' => $this->l->t('Default Value'),
      'css' => [ 'postfix' => ' default-value' ],
      'css|VD' =>  [ 'postfix' => ' default-value default-single-value' ],
      'input|ACP' => 'RH',
      'select' => 'T',
      'maxlen' => 29,
      'size' => 30,
      'sort' => true,
      'display|LF' => [ 'popup' => 'data' ],
      'sql' => 'BIN2UUID($main_table.default_value)',
      'default' => false,
      'php|LFDV' => function($value, $op, $field, $row, $recordId, $pme) {
        $multiplicity = $row[$this->queryField('multiplicity', $pme->fdd)];
        $dataType = $row[$this->queryField('data_type', $pme->fdd)];
        if (!empty($value)) {
          // fetch the value from the data-options data
          $allowed = $row[$this->queryField('data_options', $pme->fdd)];
          $allowed = $this->participantFieldsService->explodeDataOptions($allowed);
          $defaultRow = $this->participantFieldsService->findDataOption($value, $allowed);
          if (!empty($defaultRow['data'])) {
            $value = $defaultRow['data'];
          } else if (!empty($defaultRow['label'])) {
            $value = $defaultRow['label'];
          } else {
            $value = null;
          }
        }
        switch ($multiplicity) {
        case Multiplicity::GROUPOFPEOPLE:
        case Multiplicity::GROUPSOFPEOPLE:
        case Multiplicity::RECURRING:
          $value = $this->l->t('n/a');
          break;
        default:
          switch ($dataType) {
          case DataType::CLOUD_FILE:
          case DataType::DB_FILE:
            $value = $this->l->t('n/a');
            break;
          case DataType::BOOLEAN:
            $value = !empty($value) ? $this->l->t('true') : $this->l->t('false');
            break;
          case DataType::SERVICE_FEE:
            $value = $this->moneyValue($value);
            break;
          default:
            break;
          }
        }
        $html = '<span class="';
        if ($dataType != 'text' && $dataType != 'html') {
          $html .= 'align-right';
        }
        $html .= '">';
        $html .= $value;
        $html .= '</span>';
        return $html;
      },
      'tooltip' => $this->toolTipsService['participant-fields-default-value'],
    ];

    $opts['fdd']['default_multi_value'] = [
      'name' => $this->l->t('Default Value'),
      // 'input' => 'V', // not virtual, update handled by trigger
      'options' => 'ACP',
      'sql' => 'BIN2UUID($main_table.default_value)',
      'css' => [ 'postfix' => ' default-multi-value allow-empty' ],
      'select' => 'D', // @todo should be multi for "parallel".
      'values' => [
        'table' => self::OPTIONS_TABLE,
        'column' => 'key',
        'encode' => 'BIN2UUID(%s)',
        'description' => [
          'columns' => [ 'label' ],
          'cast' => [ false ],
        ],
        'filters' => '$table.field_id = $record_id[id] AND $table.deleted IS NULL',
        'join' => '$join_col_fqn = $main_table.default_value',
      ],
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-default-multi-value'],
    ];

    $opts['fdd']['default_single_value'] = [
      'name' => $this->l->t('Default Value'),
      // 'input' => 'V', // not virtual, update handled by trigger
      'options' => 'ACP',
      'sql' => 'BIN2UUID($main_table.default_value)',
      'css' => [ 'postfix' => [ 'default-single-value' ], ],
      'select' => 'O',
      'values2|A' => [ 0 => $this->l->t('no'), 1 => $this->l->t('yes') ],
      'default' => false,
      'values' => [
        'table' => self::OPTIONS_TABLE,
        'column' => 'key',
        'encode' => 'BIN2UUID(%s)',
        'description' => 'IFNULL($table.label, \''.$this->l->t('yes').'\')',
        'filters' => '$table.field_id = $record_id[id] AND $table.deleted IS NULL',
        'join' => '$join_col_fqn = $main_table.default_value',
      ],
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-default-single-value'],
    ];

    $opts['fdd']['default_file_upload_policy'] = [
      'name' => $this->l->t('Upload Policy'),
      // 'input' => 'V', // not virtual, update handled by trigger
      'options' => 'ACPVD',
      'css' => [ 'postfix' => [ 'default-cloud-file-value' ] ],
      'select' => 'D',
      'values2|ACP' => [ 'rename' => $this->l->t('rename'), 'replace' => $this->l->t('replace'), ],
      'values' => [
        'table' => self::OPTIONS_TABLE,
        'column' => 'data',
        'filters' => '$table.field_id = $record_id[id] AND $table.deleted IS NULL',
        'join' => '$join_table.field_id = $main_table.id',
        'group' => true,
      ],
      //'default' => 'rename',
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-default-cloud-file-value'],
    ];

    $opts['fdd']['tooltip'] = array_merge(
      [
        'tab'      => [ 'id' => 'display' ],
        'name' => $this->l->t('Tooltip'),
        'css' => [ 'postfix' => ' participant-field-tooltip hide-subsequent-lines' ],
        'select' => 'T',
        'textarea' => [ 'rows' => 5,
                        'cols' => 28 ],
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'escape' => false,
        'display|LF' => [ 'popup' => 'data' ],
        'tooltip' => $this->toolTipsService['participant-fields-tooltip'],
      ],
      $this->makeFieldTranslationFddValues($this->joinStructure[self::TABLE], 'tooltip')
    );

    $opts['fdd']['display_order'] = [
      'name' => $this->l->t('Display-Order'),
      'css' => [ 'postfix' => ' display-order' ],
      'select' => 'N',
      'maxlen' => 5,
      'sort' => true,
      'align' => 'right',
      'tooltip' => $this->toolTipsService['participant-fields-display-order'],
      'display' => [ 'attributes' => [ 'min' => 0 ], ],
      'default' => null,
    ];

    $opts['fdd']['tab'] = [
      'name' => $this->l->t('Table Tab'),
      'css' => [ 'postfix' => ' tab allow-empty' ],
      'select' => 'D',
      'values' => [
        'table' => $this->makeFieldTranslationsJoin($this->joinStructure[self::TABLE], [ 'tab' ]),
        'column' => 'l10n_tab',
        'join' => '$join_table.id = $main_table.id',
      ],
      'values2' => $tableTabValues2,
      'default' => -1,
      'maxlen' => 128,
      'size' => 30,
      'sort' => true,
      'tooltip' => $this->toolTipsService['participant-fields-tab'],
    ];

    // In order to be able to add a new tab, the select box first
    // has to be emptied (in order to avoid conflicts).
    $opts['fdd']['new_tab'] = [
      'name' => $this->l->t('New Tab Name'),
      'input' => 'S',
      'options' => 'CPA',
      'sql' => "''",
      'css' => [ 'postfix' => ' new-tab' ],
      'select' => 'T',
      'maxlen' => 20,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-new-tab'],
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('name of new tab'),
        ],
      ],
    ];

    if ($expertMode) {

      $opts['fdd']['encrypted'] = [
        'name' => $this->l->t('Encrypted'),
        'tab' => [ 'id' => 'advanced' ],
        'css' => [ 'postfix' => ' encrypted' ],
        'sqlw' => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default' => false,
        'select' => 'O',
        'maxlen' => 1,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-encrypted'],
      ];

      // @todo wildcards?
      $cloudGroups = [];
      /** @var \OCP\IGROUP $group */
      foreach ($this->groupManager()->search('') as $group) {
        $cloudGroups[$group->getGID()] = $group->getDisplayName();
      }

      $opts['fdd']['readers'] = [
        'name' => $this->l->t('Readers'),
        'tab' => [ 'id' => 'advanced' ],
        'css' => [ 'postfix' => ' readers user-groups' ],
        'select' => 'M',
        'values' => $cloudGroups,
        'maxlen' => 10,
        'sort' => true,
        'display' => [ 'popup' => 'data' ],
        'tooltip' => $this->toolTipsService['participant-fields-readers'],
      ];

      $opts['fdd']['writers'] = [
        'name' => $this->l->t('Writers'),
        'tab' => [ 'id' => 'advanced' ],
        'css' => [ 'postfix' => ' writers chosen-dropup user-groups' ],
        'select' => 'M',
        'values' => $cloudGroups,
        'maxlen' => 10,
        'sort' => true,
        'display' => [ 'popup' => 'data' ],
        'tooltip' => $this->toolTipsService['participant-fields-writers'],
      ];
    }

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    $opts['filters'] = [];
    if (!$this->showDisabled) {
      $opts['filters'][] = '$table.deleted IS NULL';
      if ($projectMode === false) {
        $opts['filters'][] = $joinTables[self::PROJECTS_TABLE].'.deleted IS NULL';
      }
    }
    if ($projectMode !== false) {
      $opts['filters'][] = '$table.project_id = '.$this->projectId;
    }

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] =
      $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) {
        $km = $pme->fdn['multiplicity'];
        $kd = $pme->fdn['data_type'];
        $kddd = $pme->fdn['deposit_due_date'];
        $multiplicity = $row['qf'.$km];
        $dataType = $row['qf'.$kd];
        $depositDueDate = $row['qf'.$kddd];
        $pme->fdd[$km]['css']['postfix'][] = 'multiplicity-'.$multiplicity;
        $pme->fdd[$km]['css']['postfix'][] = 'data-type-'.$dataType;
        if (!empty($depositDueDate)) {
          $pme->fdd[$km]['css']['postfix'][] = 'deposit-due-date-set';
        } else {
          $pme->fdd[$km]['css']['postfix'][] = 'deposit-due-date-unset';
        }
        $pme->fdd[$pme->fdn['default_value']]['select'] = $dataType == DataType::SERVICE_FEE ? 'N' : 'T';
        return true;
      };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeDeleteTrigger' ];

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Cleanup "trigger" which relocates several virtual inputs to their
   * proper destination columns.
   *
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
  public function beforeUpdateOrInsertTrigger(&$pme, $op, $step, &$oldvals, &$changed, &$newvals)
  {
    if ($op === PHPMyEdit::SQL_QUERY_INSERT) {
      // populate the empty $oldvals array with null in order to have
      // less undefined array key accesses.
      $oldvals = array_fill_keys(array_keys($newvals), null);
    }

    $this->debug('BEFORE OLD '.print_r($oldvals, true));
    $this->debug('BEFORE NEW '.print_r($newvals, true));
    $this->debug('BEFORE CHG '.print_r($changed, true));

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
     * Add the data from NewTab to Tab
     *
     */
    $tag = 'display_order';
    if (empty($newvals[$tag])) {
      $newvals[$tag] = null;
      Util::unsetValue($changed, $tag);
      if ($newvals[$tag] !== ($oldvals[$tag]??null)) {
        $changed[] = $tag;
      }
    }

    /************************************************************************
     *
     * Add the data from NewTab to Tab
     *
     */

    $tag = 'new_tab';
    if (!empty($newvals[$tag]) && empty($newvals['tab'])) {
      $newvals['tab'] = $newvals[$tag];
      $changed[] = 'tab';
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    if (empty($newvals['tab']) && ($newvals['tab']??null) !== null) {
      $newvals['tab'] = null;
      $changed[] = 'tab';
    }

    /************************************************************************
     *
     * Sanitize tooltip.
     *
     */

    $tag = 'tooltip';
    if (!empty($newvals[$tag])) {
      $purified = $this->fuzzyInput->purifyHTML($newvals[$tag]);
      if (empty($purified)) {
        $this->logDebug('ORIG: '.$newvals[$tag].' PURIFIED '.$purified);
      } else {
        $this->logDebug('PURIFIED '.$purified);
      }
      $newvals[$tag] = $purified;
      Util::unsetValue($changed, $tag);
      if ($newvals[$tag] !== $oldvals[$tag]) {
        $changed[] = $tag;
      }
    }

    /************************************************************************
     *
     * Move the data from default_multi_value to default_value
     *
     */

    $tag = 'default_multi_value';
    if ($newvals['multiplicity'] === 'multiple' ||
        $newvals['multiplicity'] === 'parallel') {
      $value = $newvals[$tag]??null;
      $newvals['default_value'] = strlen($value) < 36 ? null : $value;
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Move the data from default_single_value to default_value
     *
     */

    $tag = 'default_single_value';
    if ($newvals['multiplicity'] == Multiplicity::SINGLE) {
      $value = $newvals[$tag];
      $newvals['default_value'] = strlen($value) < 36 ? null : $value;
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Move the data from default_file_upload_policy to default_value
     *
     */

    $tag = 'default_file_upload_policy';
    if ($newvals['data_type'] == DataType::CLOUD_FILE
        || $newvals['data_type'] == DataType::DB_FILE) {
      if ($op == PHPMyEdit::SQL_QUERY_INSERT && empty($newvals['tab'])) {
        $newvals['tab'] = $this->l->t('file-attachments');
        $changed[] = 'tab';
      }
      if ($newvals['data_type'] == DataType::DB_FILE) {
        $newvals['encrypted'] = true;
        if (empty($oldvals['encrypted'])) {
          $changed[] = 'encrypted';
        }
        $value = 'replace';
      } else {
        $value = $newvals[$tag];
      }
      if ($newvals['multiplicity'] == Multiplicity::SIMPLE) {
        $first = array_key_first($newvals['data_options_simple']);
        $newvals['data_options_simple'][$first]['data'] = $value;
      } else if ($newvals['multiplicity'] == Multiplicity::PARALLEL) {
        foreach ($newvals['data_options'] as &$option) {
          if (empty($option['deleted']) && $option['key'] != Uuid::NIL) {
            $option['data'] = $value;
          }
        }
      }
      // files do not have a default value
      $newvals['default'] = null;
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Recurring fields do not have a default value, the value is computed.
     *
     */
    if ($newvals['multiplicity'] == Multiplicity::RECURRING) {
      unset($newvals['default_value']);
    }

    /************************************************************************
     *
     * groupofpeople is an optional group with only one optional data
     * item and a common maximum group size. A usage example would be
     * the collection of twin-room preferences, where the data would
     * be a potential service-fee for twin-room accomodation.
     *
     * We force the key to be the nil uuid in this case.
     */

    $tag = 'maximum_group_size';
    if ($newvals['multiplicity'] == Multiplicity::GROUPOFPEOPLE) {
      $first = array_key_first($newvals['data_options_single']);
      $newvals['data_options_single'][$first]['key'] = Uuid::NIL;
      $newvals['data_options_single'][$first]['limit'] = $newvals[$tag];
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Move the data from data_options_single to
     * data_options.
     *
     */

    $tag = 'data_options_single';
    if ($newvals['multiplicity'] == Multiplicity::SINGLE) {
      $first = array_key_first($newvals['data_options_single']);
      $newvals[$tag][$first]['label'] = $newvals['name'];
      $newvals[$tag][$first]['tooltip'] = $newvals['tooltip'];
      $newvals['data_options'] = $newvals[$tag];
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Move the data from data_options_groupofpeople to
     * data_options.
     *
     */

    $tag = 'data_options_groupofpeople';
    if ($newvals['multiplicity'] == Multiplicity::GROUPOFPEOPLE) {
      $first = array_key_first($newvals['data_options_single']);
      $newvals[$tag][$first]['label'] = $newvals['name'];
      $newvals[$tag][$first]['tooltip'] = $newvals['tooltip'];
      $newvals['data_options'] = $newvals[$tag];
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Move the data from data_options_simple to data_options and set
     * the default value to just this single option.
     *
     */

    $tag = 'data_options_simple';
    if ($newvals['multiplicity'] == Multiplicity::SIMPLE) {
      $first = array_key_first($newvals['data_options_simple']);
      $newvals[$tag][$first]['label'] = $newvals['name'];
      $newvals[$tag][$first]['tooltip'] = $newvals['tooltip'];
      $newvals['data_options'] = $newvals[$tag];

      $newvals['default_value'] = $first;
      if ($op != PHPMyEdit::SQL_QUERY_INSERT && PHPMyEdit::empty(Uuid::asUuid($first))) {
        throw new \RuntimeException(
          $this->l->t('Simple field-option key is not an UUID: "%s".', $key));
      }
    }
    self::unsetRequestValue($tag, $oldvals, $changed, $newvals);

    /************************************************************************
     *
     * Compute change status for default value
     *
     */

    Util::unsetValue($changed, 'default_value');
    if ($newvals['default_value'] !== ($oldvals['default_value']??null)) {
      $changed[] = 'default_value';
    }

    /************************************************************************
     *
     * Sanitize data_options
     *
     */

    if (!is_array($newvals['data_options'])) {
      // textfield
      $allowed = $this->participantFieldsService->explodeDataOptions($newvals['data_options'], false);
    } else {
      $allowed = $newvals['data_options'];
      if ($newvals['multiplicity'] == Multiplicity::RECURRING) {
        // index -1 holds the generator information

        // sanitize
        $allowed[-1]['data'] = $this->participantFieldsService->resolveReceivableGenerator($allowed[-1]['data']);

        // re-index
        $allowed = array_values($allowed);
      } else {
        // remove dummy data
        unset($allowed[-1]);
      }
    }

    // @todo is this still necessary?
    $this->debug('ALLOWED BEFORE REEXPLODE '.print_r($allowed, true));
    $newvals['data_options'] =
      $this->participantFieldsService->explodeDataOptions(
        $this->participantFieldsService->implodeDataOptions($allowed), false);

    Util::unsetValue($changed, 'data_options');

    $this->debug('ALLOWED BEFORE RESHAPE '.print_r($newvals['data_options'], true));

    // convert allowed values from array to table format as understood by
    // our PME legacy join table stuff.
    $optionValues = [];
    foreach ($newvals['data_options'] as $key => $allowedValue) {
      $field = $this->joinTableFieldName(self::OPTIONS_TABLE, 'key');
      $optionValues[$field][] = $key;
      foreach ($allowedValue as $field => $value) {
        if ($field == 'key') {
          continue;
        }
        $field = $this->joinTableFieldName(self::OPTIONS_TABLE, $field);
        $optionValues[$field][] = $key.self::JOIN_KEY_SEP.$value;
      }
      if (($newvals['multiplicity'] == Multiplicity::SIMPLE
           || $newvals['multiplicity'] == Multiplicity::SINGLE)
          && $key != Uuid::NIL
          && empty($allowedValue['deleted'])) {
        break;
      }
    }
    foreach ($optionValues as $field => $fieldData) {
      $newvals[$field] = implode(',', $fieldData);
      if ($newvals[$field] != ($oldvals[$field]??null)) {
        $changed[] = $field;
      }
    }

    $changed = array_values(array_unique($changed));
    self::unsetRequestValue('data_options', $oldvals, $changed, $newvals);

    $this->debug('AFTER OLD '.print_r($oldvals, true));
    $this->debug('AFTER NEW '.print_r($newvals, true));
    $this->debug('AFTER CHG '.print_r($changed, true));

    $this->changeSetSize = count($changed);

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
   * @todo Check whether something needs to be done with the ORM-cascade
   * stuff.
   *
   */
  public function beforeDeleteTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($pme->rec);

    if (empty($field)) {
      throw new \RuntimeException($this->l->t('Unable to find participant field for id "%s"', $pme->rec));
    }

    $used = false;

    /** @var Entities\ProjectParticipantFieldDataOption $option */
    foreach ($field->getDataOptions() as $option) {
      $this->remove($option, true);
      if ($option->unused()) {
        $this->remove($option, true);
      } else {
        $used = true;
      }
    }

    $this->remove($field, true); // this should be soft-delete
    if (!$used && $field->unused()) {
      $this->remove($field, true); // this should be soft-delete
    }

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
  }

  private function usedFields($projectId = -1, $fieldId = -1)
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)
                ->usedFields($projectId, $fieldId);
  }

  private function fieldValues($fieldId)
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)
                ->fieldValues($fieldId);
  }

  private function optionKeys($fieldId)
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)
                ->optionKeys($fieldId);
  }

  /**
   * Generate a row given values and index for the "change" view
   * corresponding to the multi-choice fields.
   *
   * @param array $value One row of the form as returned form
   * self::explodeDataOptions()
   *
   * @param integer $index A unique row number.
   *
   * @param boolean $used Whether the DB already contains data
   * records referring to this item.
   *
   * @return string HTML data for one row.
   */
  public function dataOptionInputRowHtml($value, $index, $used)
  {
    $pfx = $this->pme->cgiDataName('data_options');
    $key = $value['key'];
    $deleted = !empty($value['deleted']);
    $data = ''
          .' data-index="'.$index.'"' // real index
          .' data-used="'.($used ? 'used' : 'unused').'"'
          .' data-deleted="'.$value['deleted'].'"';
    $html = '';
    $html .= '
    <tr'
    .' class="data-line'
    .' data-options'
    .' '.($deleted ? 'deleted' : 'active')
    .'"'
    .' '.$data.'>';
    $html .= '<td class="operations">
  <input
    class="operation delete-undelete notnot-multiplicity-recurring"
    title="'.$this->toolTipsService['participant-fields-data-options:delete-undelete'].'"
    type="button"/>
  <input
    class="operation regenerate only-multiplicity-recurring"
    title="'.$this->toolTipsService['participant-fields-data-options:regenerate'].'"
    '.($deleted ? ' disabled' : '').'
    type="button"/>
    </td>';
    // key
    $prop = 'key';
    $html .= '<td class="field-'.$prop.' expert-mode-only">'
          .'<input'
          .($used || $deleted || true ? ' readonly="readonly"' : '')
          .' type="text"'
          .' class="field-key expert-mode-only"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
          .' size="9"'
          .' maxlength="8"'
          .'/>'
          .'<input'
          .' type="hidden"'
          .' class="field-deleted"'
          .' name="'.$pfx.'['.$index.'][deleted]"'
          .' value="'.$value['deleted'].'"'
          .'/>'
          .'</td>';
    // label
    $prop = 'label';
    $html .= '<td class="field-'.$prop.'">'
          .'<input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' spellcheck="true"'
          .' type="text"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
          .' size="33"'
          .' maxlength="32"'
          .'/>'
          .'</td>';
    // data
    $prop = 'data';
    $html .= '<td class="field-'.$prop.'"><input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' type="text"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
          .' maxlength="8"'
          .' size="9"'
          .'/></td>';
    // deposit
    $prop = 'deposit';
    $cssClass = implode(' ', [
      'field-'.$prop,
      'not-multiplicity-simple-set-deposit-due-date-required',
      'not-multiplicity-single-set-deposit-due-date-required',
      'not-multiplicity-groupofpeople-set-deposit-due-date-required',
      'set-deposit-due-date-required',
    ]);
    $html .= '<td class="field-'.$prop.'"><input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="'.$cssClass.'"'
          .' type="number"'
          .' step="0.01"'
          .' required'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
          .' maxlength="8"'
          .' size="9"'
          .'/></td>';
    // limit
    $prop = 'limit';
    $html .= '<td class="field-'.$prop.'"><input'
          .($deleted ? ' readonly="readonly"' : '')
          .' class="field-'.$prop.'"'
          .' type="number"'
          .' name="'.$pfx.'['.$index.']['.$prop.']"'
          .' value="'.$value[$prop].'"'
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
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
          .' title="'.$this->toolTipsService['participant-fields-data-options:'.$prop].'"'
          .' cols="32"'
          .' rows="1"'
          .'>'
          .$value[$prop]
          .'</textarea>'
          .'</td>';
    // finis
    $html .= '
    </tr>';
    return $html;
  }

  /**
   * Create the generator field in order to add new input rows. This
   * is one single text input for a new name which triggers creation
   * of a new input row from the JS change event.
   *
   * @param int $fieldId Id of the current field in change mode.
   *
   * @param null|array|Entities\ProjectParticipantFieldDataOption $generatorItem
   *     Special data item with key Uuid::NIL which holds
   *     the data for auto-generated fields.
   *
   * @return string HTML data for the generator button.
   */
  private function dataOptionGeneratorHtml($fieldId, $generatorItem)
  {
    $pfx = $this->pme->cgiDataName('data_options');
    $html = '
<tr class="data-line data-options placeholder active not-multiplicity-recurring"
  data-field-id="'.$fieldId.'" data-index="0">
  <td class="placeholder" colspan="6">
    <input
      class="field-label"
      spellcheck="true"
      type="text"
      name="'.$pfx.'[-1][label]"
      value=""
      title="'.$this->toolTipsService['participant-fields-data-options:placeholder'].'"
      placeholder="'.$this->l->t('new option').'"
      size="33"
      maxlength="32"
    />';
    foreach (['key', 'data', 'deposit', 'limit', 'tooltip'] as $prop) {
      $html .= '
    <input
      class="field-'.$prop.'"
      type="hidden"
      name="'.$pfx.'[-1]['.$prop.']"
      value=""
    />';
    }
    $html .= '
  </td>
</tr>';
    $generator = $generatorItem['data']??null;
    $html .= '
<tr
  class="data-line data-options generator active only-multiplicity-recurring"
  data-generators=\''.json_encode(
    array_merge(
      array_map([ $this->l, 't' ], array_keys(ProjectParticipantFieldsService::recurringReceivablesGenerators())),
      array_values(ProjectParticipantFieldsService::recurringReceivablesGenerators())
    )
  ).'\'
  data-field-id="'.$fieldId.'">
  <td class="operations">
    <input
      class="operation generator-run"
      title="'.$this->toolTipsService['participant-fields-data-options:generator-run'].'"
      type="button"
      '.(empty($generator) ? 'disabled' : '').'
    />
  </td>
  <td class="generator" colspan="5">
    <input
      class="field-data recurring-multiplicity-required"
      spellcheck="true"
      type="text"
      name="'.$pfx.'[-1][data]"
      value="'.$generator.'"
      title="'.$this->toolTipsService['participant-fields-data-options:generator'].'"
      placeholder="'.$this->l->t('field generator').'"
      size="33"
      maxlength="32"
      '.(empty($generator) ? '' : 'readonly="readonly"').'
    />';
    foreach (['key', 'limit', 'deposit', 'label', 'tooltip'] as $prop) {
      $value = ($generatorItem[$prop]??'');
      if (empty($value) && $prop == 'key') {
        $value = Uuid::NIL;
      }
      if (empty($value) && $prop == 'label') {
        $value = IRecurringReceivablesGenerator::GENERATOR_LABEL;
      }
      if ($prop == 'limit') {
        // $value is stored as Unix time-stamp, convert it to locale
        // date.
        $value = $this->dateTimeFormatter()->formatDate($value, 'medium');
        $html .= '
    <input
      class="field-'.$prop.'"
      type="text"
      name="'.$pfx.'[-1]['.$prop.']"
      value="'.$value.'"
      title="'.$this->toolTipsService['participant-fields-data-options:generator-startdate'].'"
      placeholder="'.$this->l->t('start date').'"
      size="10"
      maxlength="10"
    />';
      } else {
        $html .= '
    <input
      class="field-'.$prop.'"
      type="hidden"
      name="'.$pfx.'[-1]['.$prop.']"
      value="'.$value.'"
    />';
      }
    }
    $html .= '
  </td>
</tr>';
    return $html;
  }

  /**
   * Generate a table in order to define field-valus for
   * multi-select stuff.
   */
  private function showDataOptions($value, $op, $fieldId, $multiplicity = null, $dataType = null)
  {
    $this->logDebug('OPTIONS so far: '.print_r($value, true));
    $allowed = $this->participantFieldsService->explodeDataOptions($value);
    if ($op === 'display') {
      if (count($allowed) == 1) {
        // "1" means empty (headerline)
        return '';
      }
      switch ($multiplicity) {
        case Multiplicity::SIMPLE:
          return '';
        case Multiplicity::SINGLE:
          switch ($dataType) {
          case DataType::BOOLEAN:
            return $this->l->t('true').' / '.$this->l->t('false');
          case DataType::SERVICE_FEE:
            return $this->moneyValue(0).' / '.$this->moneyValue(reset($allowed)['data']);
          default:
            return '['.$this->l->t('empty').']'.' / '.reset($allowed)['data'];
          }
      }
    }
    $html = '<div class="pme-cell-wrapper quarter-sized">';
    if ($op === 'add' || $op === 'change') {
      // controls for showing soft-deleted options or normally
      // unneeded inputs
      $showDeletedLabel = $this->l->t("Show deleted items.");
      $showDeletedTip = $this->toolTipsService['participant-fields-show-deleted'];
      $showDataLabel = $this->l->t("Show data-fields.");
      $showDataTip = $this->toolTipsService['participant-fields-show-data'];
      $html .=<<<__EOT__
<div class="field-display-options notnot-multiplicity-recurring">
  <div class="show-deleted">
    <input type="checkbox"
           name="show-deleted"
           class="show-deleted checkbox"
           value="show"
           id="data-options-show-deleted"
           />
    <label class="show-deleted"
           for="data-options-show-deleted"
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
           id="data-options-show-data"
           />
    <label class="show-data"
           for="data-options-show-data"
           title="$showDataTip"
           >
      $showDataLabel
    </label>
  </div>
</div>
__EOT__;
    }

    $cssClass = 'operation-'.$op.' data-options';
    if (!empty($multiplicity)) {
      $cssClass .= ' multiplicity-'.$multiplicity;
    }
    if (!empty($dataType)) {
      $cssClass .= ' data-type-'.$dataType;
    }
    $html .= '<table class="'.$cssClass.'">
  <thead>
     <tr>';
    $html .= '<th class="operations"></th>';
    if ($multiplicity == Multiplicity::RECURRING) {
      $headers = [
        'key' => $this->l->t('Key'),
        'label' => $this->l->t('Label'),
        'data' => $this->l->t('Data'),
        'deposit' => $this->l->t('Deposit'),
        'limit' => $this->l->t('Start Date'),
        'tooltip' => $this->l->t('Tooltip'),
      ];
    } else {
      $headers = [
        'key' => $this->l->t('Key'),
        'label' => $this->l->t('Label'),
        'data' => $this->currencyLabel($this->l->t('Data')),
        'deposit' => $this->l->t('Deposit') . ' ['.$this->currencySymbol().']',
        'limit' => $this->l->t('Limit'),
        'tooltip' => $this->l->t('Tooltip'),
      ];
    }
    foreach ($headers as $key => $value) {
      $css = 'field-'.$key;
      if ($key == 'key') {
        $css .= ' expert-mode-only';
      }
      $html .=
            '<th'
            .' class="'.$css.'"'
            .' title="'.$this->toolTipsService['participant-fields-data-options:'.$key].'"'
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
          if (empty($value['key']) || !empty($value['deleted'])) {
            continue;
          }
          if ($multiplicity == Multiplicity::GROUPOFPEOPLE && $value['key'] != Uuid::NIL) {
            continue;
          }
          $html .= '
    <tr>
      <td class="operations"></td>';
          foreach (['key', 'label', 'data', 'deposit', 'limit', 'tooltip'] as $field) {
            $css = 'field-'.$field;
            if ($field == 'key') {
              $css .= ' expert-mode-only';
            }
            $fieldValue = $value[$field];
            if ($multiplicity == Multiplicity::RECURRING) {
              if ($field == 'limit' && !empty($fieldValue)) {
                $fieldValue = $this->dateTimeFormatter()->formatDate(
                  Util::convertToDateTime($fieldValue),
                  'medium');
              }
            } else {
              if ($field == 'data' || $field == 'deposit') {
                $fieldValue = $this->currencyValue($fieldValue);
              }
            }
            $html .= '<td class="'.$css.'">'.$fieldValue.'</td>';
          }
          $html .= '
    </tr>';
        }
        break; // display
      case 'add':
      case 'change':
        $usedKeys = $this->optionKeys($fieldId);
        $generatorItem = null;
        $idx = 0;
        foreach ($allowed as $value) {
          $key = $value['key'];
          if (empty($key)) {
            continue;
          }
          if ($key == Uuid::NIL) {
            $generatorItem = $value;
            continue;
          }
          $used = array_search(Uuid::uuidBytes($key), $usedKeys) !== false;
          $html .= $this->dataOptionInputRowHtml($value, $idx, $used);
          $idx++;
        }
        $html .= $this->dataOptionGeneratorHtml($fieldId, $generatorItem);
        break;
    }
    $html .= '
  </tbody>
</table></div>';
    return $html;
  }

  /**
   * Fetch the admissible single data options from a field of options which as
   * well may contain deleted and generator options.
   *
   * The strategy is to pick the first non-deleted option or the first
   * non-deleted generator option for Multiplicity::GROUPOFPEOPLE.
   *
   * @return null|array
   */
  private function getAllowedSingleValue($dataOptions, $multiplicity, $dataType)
  {
    $allowed = $this->participantFieldsService->explodeDataOptions($dataOptions, false);
    $entry = null;
    foreach ($allowed as $key => $option) {
      if (!empty($item['deleted'])) {
        continue;
      }
      if ($option['key'] == Uuid::NIL && $multiplicity == Multiplicity::GROUPOFPEOPLE) {
        $entry = $option;
      } else if (empty($entry)) {
        $entry = $option;
      }
    }
    empty($entry) && $entry = $this->participantFieldsService->dataOptionPrototype();
    if ($multiplicity == Multiplicity::GROUPOFPEOPLE) {
      $entry['key'] = Uuid::NIL;
    }
    return [ $entry, $allowed ];
  }

  /**
   * Display the input stuff for a single-value choice and simple input values.
   */
  private function showAllowedSingleValue($dataOptions, $op, $toolTip, $multiplicity, $dataType, $variant)
  {
    list($entry, $allowed) = $this->getAllowedSingleValue($dataOptions, $multiplicity, $dataType);
    $value = $entry['data'];
    if ($op === 'display') {
      return $this->currencyValue($value);
    }
    $key = $entry['key'];
    $name  = $this->pme->cgiDataName('data_options_' . $variant);
    $field = 'data';
    $value = htmlspecialchars($entry[$field]);
    $tip   = $toolTip;
    $html  = '<div class="active-value">';
    if ($dataType == DataType::HTML) {
      $htmlDisabled = [ 'input' => 'disabled', 'textarea' => '' ];
    } else {
      $htmlDisabled = [ 'textarea' => 'disabled', 'input' => '' ];
    }
    $html  .=<<<__EOT__
<input class="pme-input data-options-{$variant} data-type-html-hidden data-type-html-disabled"
       {$htmlDisabled['input']}
       type="text"
       maxlength="29"
       size="30"
       value="{$value}"
       name="{$name}[{$key}][{$field}]"
       title="{$tip}"
/>
__EOT__;
    $html  .=<<<__EOT__
<span class="pme-input data-options-{$variant} not-data-type-html-hidden not-data-type-html-disabled">
  <textarea class="pme-input data-options-{$variant} wysiwyg-editor"
            name="{$name}[{$key}][{$field}]"
            {$htmlDisabled['textarea']}
            title="{$tip}"
            rows="5"
            cols="50">{$value}</textarea>
</span>
__EOT__;
    // deposit displayed in own extra field
    foreach (['key', 'label',/* 'deposit',*/ 'limit', 'tooltip', 'deleted'] as $field) {
      $value = htmlspecialchars($entry[$field]);
      $html .=<<<__EOT__
<input class="pme-input data-options-{$variant}"
       type="hidden"
       value="{$value}"
       name="{$name}[{$key}][{$field}]"
/>
__EOT__;
    }
    $html .= '</div>';
    $html .= '<div class="inactive-values">';
    // Now emit all left-over values. Flag all items as deleted.
    foreach ($allowed as $key => $option) {
      $key = $option['key'];
      if ($key == $entry['key']) {
        continue;
      }
      if ($multiplicity != Multiplicity::GROUPOFPEOPLE) {
        $option['deleted'] = (new DateTime)->getTimestamp();
      }
      foreach(['key', 'label', 'data', 'deposit', 'limit', 'tooltip', 'deleted'] as $field) {
        $value = htmlspecialchars($option[$field]);
        $html .=<<<__EOT__
<input class="pme-input data-options-{$variant}"
       type="hidden"
       value="{$value}"
       name="{$name}[{$key}][{$field}]"
/>
__EOT__;
      }
    }
    $html .= '</div>';
    return $html;
  }

  /**
   * Return a currency value where the number symbol can be hidden
   * by CSS.
   */
  private function currencyValue($value)
  {
    return
      '<span class="general">'.$value.'</span>'
      .'<span class="service-fee currency-amount">'
      .$this->moneyValue($value)
      .'</span>';

  }

  /**
   * Return an alternate "Amount [CUR]" label which can be hidden by
   * CSS.
   */
  private function currencyLabel($label, $prefix = '')
  {
    return
      '<span class="general">'.$prefix.$label.'</span>'
      .'<span class="service-fee currency-label">'
      .$prefix.$this->l->t('Amount').' ['.$this->currencySymbol().']'
      .'</span>';
  }

}
