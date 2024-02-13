<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\PageRenderer;

use RuntimeException;

use \OCA\CAFEVDB\Wrapped\Carbon\Carbon as DateTime;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Http\TemplateResponse;
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
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Constants;

/**Table generator for Instruments table. */
class ProjectParticipantFields extends PMETableViewBase
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  const TEMPLATE = 'project-participant-fields';
  const TABLE = self::PROJECT_PARTICIPANT_FIELDS_TABLE;
  const OPTIONS_TABLE = self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE;
  const DATA_TABLE = self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE;

  const OPTION_FIELDS = [ 'key', 'label', 'data', 'deposit', 'limit', 'tooltip', 'deleted', ];

  const OPTION_DATA_SHOW_MASK = [
    'key' => [
      'default-hidden',
      'not-expert-mode-hidden',
    ],
    'data' => [
      'data-type-cloud-file-hidden',
      'data-type-db-file-hidden',
      'data-type-cloud-folder-hidden',
    ],
    'deposit' => [
      'default-hidden',
      'not-data-type-receivables-hidden',
      'not-data-type-liabilities-hidden',
      'multiplicity-recurring-hidden',
      'not-show-data-hidden',
    ],
    'limit' => [
      'default-hidden',
      'not-multiplicity-groupofpeople-hidden',
      'not-multiplicity-groupsofpeople-hidden',
      'not-data-type-date-hidden',
      'not-data-type-date-time-hidden',
      'not-show-data-hidden',
    ],
  ];

  const OPTION_DATA_INPUT_SIZE = [
    'default' => 9,
    DataType::RECEIVABLES => 9,
    DataType::LIABILITIES => 9,
    DataType::DATE => 7,
    DataType::DATETIME => 12,
  ];

  /** @var string */
  protected static $toolTipsPrefix = 'page-renderer:participant-fields:definition';

  /**
   * @var string
   * SQL sub-query in order to join with   self::FIELD_TRANSLATIONS_TABLE
   */
  protected $optionsTable;

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    RequestParameterService $requestParameters,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    private InstrumentationService $instrumentationService,
    private FuzzyInputService $fuzzyInput,
    private ProjectParticipantFieldsService $participantFieldsService,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->projectId > 0) {
      return $this->l->t("Participant-Fields for Project %s", [ $this->projectName ]);
    } else {
      return $this->l->t("Extra Fields for Projects");
    }
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;

    $opts = [];

    $expertMode = $this->getUserValue('expertMode');

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
    $opts['sort_field'] = [
      '-' . $this->joinTableFieldName(self::PROJECTS_TABLE, 'year'),
      'project_id',
      '-display_order',
      'name' ,
    ];

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
          'id' => 'access',
          'tooltip' => $this->toolTipsService['page-renderer:participant-fields:tabs:access'],
          'name' => $this->l->t('Access Control'),
        ],
        [
          'id' => 'miscinfo',
          'tooltip' => $this->toolTipsService['page-renderer:miscinfo-tab'],
          'name' => $this->l->t('Miscellaneous Data'),
        ],
        [
          'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns'),
        ],
      ],
    ];


    // field definitions
    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      $joinInfo['table'] = $table;
      switch ($table) {
        case self::OPTIONS_TABLE:
          $this->optionsTable =
            $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, ['label', 'tooltip']);
          break;
        default:
          break;
      }
    });

    $joinTables = $this->defineJoinStructure($opts);

    // outside the expertMode "if", this is the index!
    $opts['fdd']['id'] = [
      'tab'      => ['id' => 'miscinfo' ],
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

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year', [
        'tab'       => [ 'id' => 'tab-all' ],
        'name'      => $this->l->t('Year'),
        'input'     => 'VHRS',
        'input|FL'  => ($projectMode ? 'HR' : 'R'),
        'align'     => 'right',
      ],
    );

    $opts['fdd']['project_id'] = [
      'tab'       => [ 'id' => 'tab-all' ],
      'name'      => $this->l->t('Project'),
      'input'     => ($projectMode ? 'HR' : 'M'),
      'css' => [ 'postfix' => [ 'project-instrument-project-name', ], ],
      'select|DV' => 'T', // delete, filter, list, view
      'select|ACPFL' => 'D',  // add, change, copy
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : null),
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
        'maxlen' => 64,
        'size' => 40,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-field-name'],
      ],
      $this->makeFieldTranslationFddValues($this->joinStructure[self::TABLE], 'name') //
    );

    $opts['fdd']['name']['sql'] = self::ifFileSystemEntry('$main_table.$field_name', $opts['fdd']['name']['sql']);

    $opts['fdd']['deleted'] = array_merge(
      $this->defaultFDD['deleted'], [
        'tab' => [ 'id' => 'tab-all' ],
        'name' => $this->l->t('Deleted'),
        'css'      => [ 'postfix' => [ 'participant-field-disabled', ], ],
        'tooltip'  => $this->toolTipsService['participant-fields-disabled'],
        'dateformat' => 'medium',
        'timeformat' => 'short',
        'maxlen' => 19,
        'input' => ($this->showDisabled) ? 'T' : 'RH',
      ]);
    Util::unsetValue($opts['fdd']['deleted']['css']['postfix'], 'date');
    $opts['fdd']['deleted']['css']['postfix'][] = 'datetime';

    $opts['fdd']['usage'] = [
      'tab' => [ 'id' => [ 'miscinfo', 'definition' ] ],
      'name' => $this->l->t('#Usage'),
      'sql' => 'COUNT(DISTINCT '.$joinTables[self::DATA_TABLE].'.musician_id)',
      'css' => [ 'postfix' => [ 'participant-fields-usage', ], ],
      'options' => 'CDVLF',
      'select' => 'N',
      'align' => 'right',
      'input' => 'V',
      'sort' => true,
      'tooltip' => $this->toolTipsService['participant-fields-usage'],
    ];

    $opts['fdd']['multiplicity'] = [
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
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $usage = $row[$this->queryField('usage', $pme->fdd)];
          return [
            'data-field-usage' => $usage,
          ];
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $usage = $row[$this->queryField('usage', $pme->fdd)];
          return '<input id="pme-field-multiplicity-lock"
' . ($usage > 0 ? 'checked' : '') . '
       type="checkbox"
       class="pme-input pme-select-lock"
/>
<label for="pme-field-multiplicity-lock"
       class="pme-input pme-select-lock lock-unlock tooltip-auto"
       title="' . $this->toolTipsService[self::$toolTipsPrefix . ':multiplicity:lock'] . '"
></label>';
        },
      ],
    ];

    // $dataTypeIndex = count($opts['fdd']);
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
      'display|ACP' => [
        'attributes' => function($op, $k, $row, $pme) {
          $usage = $row[$this->queryField('usage', $pme->fdd)];
          return [
            'data-field-usage' => $usage,
          ];
        },
        'postfix' => function($op, $pos, $k, $row, $pme) {
          $usage = $row[$this->queryField('usage', $pme->fdd)];
          return '<input id="pme-field-data-type-lock"
' . ($usage > 0 ? 'checked' : '') . '
       type="checkbox"
       class="pme-input pme-select-lock"
/>
<label for="pme-field-data-type-lock"
       class="pme-input pme-select-lock lock-unlock tooltip-auto"
       title="' . $this->toolTipsService[self::$toolTipsPrefix . ':data-type:lock'] . '"
></label>';
        },
      ],
    ];

    $opts['fdd']['due_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['due_date'], [
        'tab' => [ 'id' => 'definition' ],
        'css' => [
          'postfix' => [
            'due-date',
            'default-hidden',
            'not-data-type-receivables-hidden',
            'not-data-type-liabilities-hidden',
            'receivables-data-type-required',
            'liabilities-data-type-required',
          ],
        ],
      ]);

    $opts['fdd']['deposit_due_date'] = Util::arrayMergeRecursive(
      $this->defaultFDD['due_date'], [
        'tab' => [ 'id' => 'definition' ],
        'name' =>  $this->l->t('Deposit Due Date'),
        'css' => [
          'postfix' => [
            'deposit-due-date',
            'default-hidden',
            'not-data-type-receivables-hidden',
            'not-data-type-liabilities-hidden',
            'multiplicity-recurring-hidden',
          ],
        ],
      ]);

    /*-*************************************************************************
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
      'css|LF' => [
        'postfix' => [
          'data-options',
          'default-hidden',
          'not-multiplicty-groupsofpeople-hidden',
          'not-multiplicty-parallel-hidden',
          'not-multiplicty-multiple-hidden',
          'not-multiplicty-recurring-hidden',
          'hide-subsequent-lines',
        ],
      ],
      'css' => [
        'postfix' => [
          'data-options',
          'default-hidden',
          'not-multiplicity-groupsofpeople-hidden',
          'not-multiplicity-parallel-hidden',
          'not-multiplicity-multiple-hidden',
          'not-multiplicity-recurring-hidden',
        ],
      ],
      'css|VD' => [
        'postfix' => [
          'data-options',
          'data-options-single',
          'default-hidden',
          'not-multiplicity-groupsofpeople-hidden',
          'not-multiplicity-parallel-hidden',
          'not-multiplicity-multiple-hidden',
          'not-multiplicity-recurring-hidden',
          'not-multiplicity-single-hidden',
        ],
      ],
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
    , "label", ' . self::ifFileSystemEntry('$join_table.label', '$join_table.l10n_label') . '
    , "data", $join_table.data
    , "deposit", $join_table.deposit
    , "limit", $join_table.`limit`
    , "tooltip", $join_table.l10n_tooltip
    , "deleted", $join_table.deleted
) ORDER BY ' . self::ifFileSystemEntry('$join_table.label', '$join_table.l10n_label') . ' ASC, $join_table.data ASC),"]")',
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

    foreach (['groupofpeople' => '', 'single' => '', 'simple' => $this->l->t('Default') . ' '] as $multiplicityVariant => $prefix) {
      $opts['fdd']['data_options_' . $multiplicityVariant] = [
        'name' => $this->currencyLabel($this->l->t('Data'), $prefix),
        'css' => [
          'postfix' => [
            'data-options-' . $multiplicityVariant,
            'default-hidden',
            'not-multiplicity-' . $multiplicityVariant . '-hidden',
            'data-type-cloud-folder-hidden',
            'data-type-cloud-file-hidden',
            'data-type-db-file-hidden',
          ],
        ],
        'sql' => '$main_table.id',
        'php' => function($dummy, $op, $field, $row, $recordId, $pme) use ($multiplicityVariant) {
          // allowed values from virtual JSON aggregator field
          $dataOptions = $row['qf'.$pme->fdn['data_options']]??[];
          $multiplicity = $row['qf'.$pme->fdn['multiplicity']]??null;
          $dataType = $row['qf'.$pme->fdn['data_type']]??null;
          return $this->showAllowedSingleValue($dataOptions, $op, $pme->fdd[$field]['tooltip'], $multiplicity, $dataType, $multiplicityVariant);
        },
        'input' => 'SR',
        'options' => 'ACP', // but not in list/view/delete-view
        'select' => 'T',
        'maxlen' => 29,
        'size' => 30,
        'sort' => true,
        'tooltip' => $this->toolTipsService['participant-fields-data-options' . ':' . $multiplicityVariant],
      ];

      $opts['fdd']['deposit_' . $multiplicityVariant] = [
        'name' => $prefix . $this->l->t('Deposit').' ['.$this->currencySymbol().']',
        'css' => [
          'postfix' => [
            'deposit-' . $multiplicityVariant,
            'default-hidden',
            'not-multiplicity-' . $multiplicityVariant . '-data-type-receivables-hidden',
            'not-multiplicity-' . $multiplicityVariant . '-data-type-liabilities-hidden',
            'multiplicity-' . $multiplicityVariant . '-set-deposit-due-date-' . ($multiplicityVariant == 'simple' ? 'not-' : '') . 'required',
          ],
        ],
        'sql' => '$main_table.id',
        'php' => function($dummy, $op, $pmeField, $row, $recordId, $pme) use ($multiplicityVariant) {
          // allowed values from virtual JSON aggregator field
          $dataOptions = $row['qf'.$pme->fdn['data_options']]??[];
          $multiplicity = $row['qf'.$pme->fdn['multiplicity']]??null;
          $dataType = $row['qf'.$pme->fdn['data_type']]??null;
          list($entry,) = $this->getAllowedSingleValue($dataOptions, $multiplicity, $dataType);
          $key = $entry['key'];
          $name  = $this->pme->cgiDataName('data_options_' . $multiplicityVariant);
          $field = 'deposit';
          $value = htmlspecialchars($entry[$field]);
          $tip = $pme->fdd[$pmeField]['tooltip'];
          $html =<<<__EOT__
            <div class="active-value">
            <input class="pme-input data-options-{$multiplicityVariant} multiplicity-{$multiplicityVariant}-set-deposit-due-date-required"
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
        'tooltip' => $this->toolTipsService['participant-fields-deposit-' . $multiplicityVariant],
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
      'css' => [
        'postfix' => [
          'maximum-group-size',
          'default-hidden',
          'not-multiplicity-groupofpeople-hidden',
          'no-search',
        ],
      ],
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
      'css' => [
        'postfix' => [
          'default-single-value',
          'squeeze-subsequent-lines', // for html
          'default-hidden',
          'not-multiplicity-simple-hidden',
          'not-multiplicity-single-hidden',
          'data-type-db-file-hidden',
          'data-type-cloud-folder-hidden',
          'data-type-cloud-file-hidden',
        ],
      ],
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
          } elseif ($multiplicity != Multiplicity::SIMPLE && !empty($defaultRow['label'])) {
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
                $value = !empty($value) ? $this->l->t($value) : '';
                break;
              case DataType::DB_FILE:
              case DataType::CLOUD_FOLDER:
                $value = $this->l->t('n/a');
                break;
              case DataType::BOOLEAN:
                $value = !empty($value) ? $this->l->t('true') : $this->l->t('false');
                break;
              case DataType::RECEIVABLES:
              case DataType::LIABILITIES:
                $value = $this->moneyValue($value);
                break;
              case DataType::DATE:
                if (!empty($value)) {
                  try {
                    $date = DateTime::parse($value, $this->getDateTimeZone());
                    $value = $this->dateTimeFormatter()->formatDate($date, 'medium');
                  } catch (\Throwable $t) {
                    // ignore
                  }
                }
                break;
              case DataType::DATETIME:
                if (!empty($value)) {
                  try {
                    $date = DateTime::parse($value, $this->getDateTimeZone());
                    $value = $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
                  } catch (\Throwable $t) {
                    // ignore
                  }
                }
                break;
              default:
                break;
            }
        }
        $cssClass = ($dataType != DataType::HTML && $dataType != DataType::TEXT) ? ' align-right' : '';
        $html = '<span class="pme-cell-wrapper'.$cssClass.'">';
        $html .= ($dataType == DataType::HTML)
          ? '<span class="pme-cell-squeezer">'.$value.'</span>'
          : $value;
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
      'css' => [
        'postfix' => [
          'default-multi-value',
          'default-hidden',
          'data-type-db-file-hidden',
          'data-type-cloud-folder-hidden',
          'data-type-cloud-file-hidden',
          'not-multiplicity-multiple-hidden',
          'not-multiplicity-parallel-hidden',
          'allow-empty',
        ],
      ],
      'select' => 'D', // @todo should be multi for "parallel".
      'values' => [
        'table' => $this->optionsTable,
        'column' => 'key',
        'encode' => 'BIN2UUID(%s)',
        'description' => [
          'columns' => [ 'l10n_label' ],
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
      'css' => [
        'postfix' => [
          'default-single-value',
          'default-hidden',
          'data-type-db-file-hidden',
          'data-type-cloud-folder-hidden',
          'data-type-cloud-file-hidden',
          'not-multiplicity-single-hidden',
        ],
      ],
      'select' => 'O',
      'values2|A' => [ 0 => $this->l->t('no'), 1 => $this->l->t('yes') ],
      'default' => false,
      'values' => [
        'table' => $this->optionsTable,
        'column' => 'key',
        'encode' => 'BIN2UUID(%s)',
        'description' => 'IFNULL($table.l10n_label, \''.$this->l->t('yes').'\')',
        'filters' => '$table.field_id = $record_id[id] AND $table.deleted IS NULL',
        'join' => '$join_col_fqn = $main_table.default_value',
      ],
      'maxlen' => 29,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-default-single-value'],
    ];

    $opts['fdd']['tooltip'] = array_merge(
      [
        'tab'      => [ 'id' => 'display' ],
        'name' => $this->l->t('Tooltip'),
        'css' => [ 'postfix' => [ 'participant-field-tooltip', 'squeeze-subsequent-lines', 'hide-subsequent-lines', 'wysiwyg-editor', ], ],
        'select' => 'T',
        'textarea' => [ 'rows' => 5,
                        'cols' => 28 ],
        'maxlen' => 1024,
        'size' => 30,
        'sort' => true,
        'escape' => false,
        'display|LF' => [
          'popup' => 'data',
          'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
        ],
        'tooltip' => $this->toolTipsService['participant-fields-tooltip'],
      ],
      $this->makeFieldTranslationFddValues($this->joinStructure[self::TABLE], 'tooltip')
    );

    $opts['fdd']['display_order'] = [
      'name' => $this->l->t('Display-Order'),
      'css' => [ 'postfix' => [ 'display-order', ], ],
      'select' => 'N',
      'maxlen' => 5,
      'sort' => true,
      'align' => 'right',
      'tooltip' => $this->toolTipsService['participant-fields-display-order'],
      // 'display' => [ 'attributes' => [ 'min' => 0 ], ],
      'default' => null,
    ];

    $opts['fdd']['tab'] = [
      'name' => $this->l->t('Table Tab'),
      'css' => [ 'postfix' => [ 'tab', 'allow-empty', ], ],
      'select' => 'D',
      'sql' => '$join_col_fqn',
      'values' => [
        'table' => $this->makeFieldTranslationsJoin($this->joinStructure[self::TABLE], [ 'tab' ]),
        'column' => 'original_tab',
        'description' => [
          'columns' => [ 'GROUP_CONCAT(DISTINCT $table.l10n_tab)' ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'join' => '$join_table.id = $main_table.id',
      ],
      'values2' => $tableTabValues2,
      'default' => null,
      'maxlen' => 128,
      'size' => 30,
      'sort' => true,
      'tooltip' => $this->toolTipsService['participant-fields-tab'],
    ];

    // In order to be able to add a new tab, the select box first
    // has to be emptied (in order to avoid conflicts).
    $opts['fdd']['new_tab'] = [
      'name' => $this->l->t('New Tab Name'),
      'input' => 'S', // skip during update
      'options' => 'CPA',
      'sql' => "''",
      'css' => [ 'postfix' => [ 'new-tab', ], ],
      'select' => 'T',
      'maxlen' => 20,
      'size' => 30,
      'sort' => false,
      'tooltip' => $this->toolTipsService['participant-fields-new-tab'],
      'default' => null,
      'display' => [
        'attributes' => [
          'placeholder' => $this->l->t('name of new tab'),
        ],
      ],
    ];

    $opts['fdd']['participant_access'] = [
      'name' => $this->l->t('Participant Access'),
      'tab' => [ 'id' => 'access' ],
      'css' => [ 'postfix' => [ 'participant-access', 'access' ], ],
      'select' => 'O',
      'values2' => [
        Types\EnumAccessPermission::NONE => $this->l->t('no access'),
        Types\EnumAccessPermission::READ => $this->l->t('read'),
        Types\EnumAccessPermission::READ_WRITE => $this->l->t('read / write'),
      ],
      'default' => Types\EnumAccessPermission::NONE,
      'sort' => true,
      'align' => 'center',
      'tooltip' => $this->toolTipsService['page-renderer:participant-fields:participant-access'],
    ];

    if ($expertMode) {
      $opts['fdd']['encrypted'] = [
        'name' => $this->l->t('Encrypted'),
        'tab' => [ 'id' => 'access' ],
        'css' => [ 'postfix' => [ 'encrypted', ], ],
        'sqlw' => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default' => false,
        'select' => 'O',
        'maxlen' => 1,
        'sort' => true,
        'align' => 'center',
        'tooltip' => $this->toolTipsService['participant-fields-encrypted'],
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
        switch ($dataType) {
          case DataType::RECEIVABLES:
          case DataType::LIABILITIES:
            $selectValue = 'N';
          default:
            $selectValue = 'T';
            break;
        }
        $pme->fdd[$pme->fdn['default_value']]['select'] = $selectValue;
        return true;
      };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateOrInsertTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeDeleteTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS]['*'][PHPMyEdit::TRIGGER_DATA][] =
      function(&$pme, $op, $step, &$row) {
        $dataType = $row[$this->queryField('data_type', $pme->fdd)]??null;
        if (empty($row[$this->queryField('tab', $pme->fdd)])) {
          $tab = null;
          switch ($dataType) {
            case DataType::RECEIVABLES:
            case DataType::LIABILITIES:
              $tab = 'finance';
              break;
            case DataType::CLOUD_FILE:
            case DataType::CLOUD_FOLDER:
            case DataType::DB_FILE:
              $tab = 'file-attachments';
              break;
            default:
              $tab = 'project';
              break;
          }
          $row[$this->queryField('tab', $pme->fdd)] = $tab;
          $row[$this->queryIndexField('tab', $pme->fdd)] = $tab;
        }
        return true;
      };

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
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateOrInsertTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    if ($op === PHPMyEdit::SQL_QUERY_INSERT) {
      // populate the empty $oldValues array with null in order to have
      // less undefined array key accesses.
      $oldValues = array_fill_keys(array_keys($newValues), null);
    }

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'before');

    if ($newValues['name'] === Constants::README_NAME) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t(
          'The name "%1$s" is reserved by the app in order to provide general help texts in the file-system'
          . ' and may not be used as a field-name.',
          Constants::README_NAME));
    }

    /*-**********************************************************************
     *
     * Add the data from NewTab to Tab
     *
     */
    $tag = 'display_order';
    if (empty($newValues[$tag])) {
      $newValues[$tag] = null;
      Util::unsetValue($changed, $tag);
      if ($newValues[$tag] !== ($oldValues[$tag]??null)) {
        $changed[] = $tag;
      }
    }

    /*-**********************************************************************
     *
     * Move the data from NewTab to Tab
     *
     */

    $tag = 'new_tab';
    if (!empty($newValues[$tag]) && empty($newValues['tab'])) {
      $newValues['tab'] = $newValues[$tag];
      $changed[] = 'tab';
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Remove tab definitions resulting from data-type specs
     *
     */

    $tag = 'tab';
    foreach (['oldvals', 'newvals'] as $dataSet) {
      if (!empty(${$dataSet}[$tag])) {
        $defaultTabId = ProjectParticipantFieldsService::defaultTabId(${$dataSet}['multiplicity'], ${$dataSet}['data_type']);
        if (${$dataSet}[$tag] == $defaultTabId) {
          ${$dataSet}[$tag] = null;
        }
      }
    }

    if (empty($newValues[$tag]) && ($newValues[$tag]??null) !== null) {
      $newValues[$tag] = null;
    }

    if (($oldValues[$tag] ?? null) !== ($newValues[$tag] ?? null)) {
      $changed[] = $tag;
    }

    /*-**********************************************************************
     *
     * Sanitize tooltip.
     *
     */

    $tag = 'tooltip';
    if (!empty($newValues[$tag])) {
      $purified = $this->fuzzyInput->purifyHTML($newValues[$tag]);
      if (empty($purified)) {
        $this->logDebug('ORIG: '.$newValues[$tag].' PURIFIED '.$purified);
      } else {
        $this->logDebug('PURIFIED '.$purified);
      }
      $newValues[$tag] = $purified;
      Util::unsetValue($changed, $tag);
      if ($newValues[$tag] !== $oldValues[$tag]) {
        $changed[] = $tag;
      }
    }

    /*-**********************************************************************
     *
     * Move the data from default_multi_value to default_value
     *
     */

    $tag = 'default_multi_value';
    if ($newValues['multiplicity'] === Multiplicity::MULTIPLE ||
        $newValues['multiplicity'] === Multiplicity::PARALLEL) {
      $value = $newValues[$tag]??null;
      $newValues['default_value'] = strlen($value) < 36 ? null : $value;
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Move the data from default_single_value to default_value
     *
     */

    $tag = 'default_single_value';
    if ($newValues['multiplicity'] == Multiplicity::SINGLE) {
      $value = $newValues[$tag];
      $newValues['default_value'] = strlen($value) < 36 ? null : $value;
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Recurring fields do not have a default value, the value is computed.
     *
     */
    if ($newValues['multiplicity'] == Multiplicity::RECURRING) {
      unset($newValues['default_value']);
    }

    /*-**********************************************************************
     *
     * The groupofpeople is an optional group with only one optional data
     * item and a common maximum group size. A usage example would be
     * the collection of twin-room preferences, where the data would
     * be a potential service-fee for twin-room accomodation.
     *
     * We force the key to be the nil uuid in this case.
     */

    $tag = 'maximum_group_size';
    if ($newValues['multiplicity'] == Multiplicity::GROUPOFPEOPLE) {
      $first = array_key_first($newValues['data_options_groupofpeople']);
      $newValues['data_options_groupofpeople'][$first]['key'] = Uuid::NIL;
      $newValues['data_options_groupofpeople'][$first]['limit'] = $newValues[$tag];
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Move the data from data_options_single to
     * data_options.
     *
     */

    $tag = 'data_options_single';
    if ($newValues['multiplicity'] == Multiplicity::SINGLE) {
      $first = array_key_first($newValues[$tag]);
      $newValues[$tag][$first]['label'] = $newValues['name'];
      $newValues[$tag][$first]['tooltip'] = $newValues['tooltip'] ?? null;
      $newValues['data_options'] = $newValues[$tag];
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Move the data from data_options_groupofpeople to
     * data_options.
     *
     */

    $tag = 'data_options_groupofpeople';
    if ($newValues['multiplicity'] == Multiplicity::GROUPOFPEOPLE) {
      $first = array_key_first($newValues[$tag]);
      $newValues[$tag][$first]['label'] = $newValues['name'];
      $newValues[$tag][$first]['tooltip'] = $newValues['tooltip'];
      $newValues['data_options'] = $newValues[$tag];
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Move the data from data_options_simple to data_options and set
     * the default value to just this single option.
     *
     */

    $tag = 'data_options_simple';
    if ($newValues['multiplicity'] == Multiplicity::SIMPLE) {
      $first = array_key_first($newValues[$tag]);
      $newValues[$tag][$first]['label'] = $newValues['name'];
      $newValues[$tag][$first]['tooltip'] = $newValues['tooltip'] ?? '';
      $newValues['data_options'] = $newValues[$tag];

      $newValues['default_value'] = $first;
      if ($op != PHPMyEdit::SQL_QUERY_INSERT && empty(Uuid::asUuid($first))) {
        throw new RuntimeException(
          $this->l->t('Simple field-option key is not an UUID: "%s".', $key));
      }
    }
    self::unsetRequestValue($tag, $oldValues, $changed, $newValues);

    /*-**********************************************************************
     *
     * Compute change status for default value
     *
     */

    Util::unsetValue($changed, 'default_value');
    if ($newValues['default_value'] !== ($oldValues['default_value']??null)) {
      $changed[] = 'default_value';
    }

    /*-**********************************************************************
     *
     * Sanitize data_options
     *
     */

    if (!is_array($newValues['data_options'])) {
      // textfield
      $allowed = $this->participantFieldsService->explodeDataOptions($newValues['data_options'], false);
    } else {
      $allowed = $newValues['data_options'];
      if ($newValues['multiplicity'] == Multiplicity::RECURRING) {
        // index -1 holds the generator information

        // sanitize
        $allowed[-1]['data'] = $this->participantFieldsService->resolveReceivableGenerator($allowed[-1]['data']);

        // limit is the start date, convert to time-stamp
        $newStartDate = self::convertToDateTime($allowed[-1]['limit']);
        $allowed[-1]['limit'] = empty($newStartDate) ? null : $newStartDate->getTimestamp();


        // re-index
        $allowed = array_values($allowed);
      } else {
        // remove dummy data
        unset($allowed[-1]);
      }
    }

    // @todo is this still necessary?
    $this->debug('ALLOWED BEFORE REEXPLODE '.print_r($allowed, true));
    $newValues['data_options'] =
      $this->participantFieldsService->explodeDataOptions(
        $this->participantFieldsService->implodeDataOptions($allowed), false);

    Util::unsetValue($changed, 'data_options');

    $this->debug('ALLOWED BEFORE RESHAPE '.print_r($newValues['data_options'], true));

    // convert allowed values from array to table format as understood by
    // our PME legacy join table stuff.
    $optionValues = [];
    foreach ($newValues['data_options'] as $key => $allowedValue) {
      $keyField = $this->joinTableFieldName(self::OPTIONS_TABLE, 'key');
      $optionValues[$keyField][] = $key;
      foreach ($allowedValue as $field => $value) {
        if ($field == 'key') {
          continue;
        }
        if (empty($value)) {
          $value = is_numeric($value) ? $value : null;
        } elseif ($key != Uuid::NIL && $field == 'data') {
          switch ($newValues['data_type']) {
            case DataType::DATE:
              $date = DateTime::parseFromLocale($value, $this->getLocale(), 'UTC');
              $value = $date->format('Y-m-d');
              break;
            case DataType::DATETIME:
              $date = DateTime::parseFromLocale($value, $this->getLocale(), $this->getDateTimeZone());
              $value = $date->setTimezone('UTC')->toIso8601String();
              break;
          }
        }
        $field = $this->joinTableFieldName(self::OPTIONS_TABLE, $field);
        $optionValues[$field][] = $value === null ? null : $key.self::JOIN_KEY_SEP.$value;
      }
      if (($newValues['multiplicity'] == Multiplicity::SIMPLE
           || $newValues['multiplicity'] == Multiplicity::SINGLE)
          && $key != Uuid::NIL
          && empty($allowedValue['deleted'])) {
        break;
      }
    }

    $this->debug('OPTION VALUES AFTER RESHAPE '.print_r($optionValues, true));

    foreach ($optionValues as $field => $fieldData) {
      //  TODO: eliminate empty field-data
      $newValues[$field] = Util::implode(self::VALUES_SEP, $fieldData);
      if ($newValues[$field] != ($oldValues[$field]??null)) {
        $changed[] = $field;
      }
    }

    $changed = array_values(array_unique($changed));
    self::unsetRequestValue('data_options', $oldValues, $changed, $newValues);

    $this->debugPrintValues($oldValues, $changed, $newValues, null, 'after');

    $this->changeSetSize = count($changed);

    return true;
  }

  /**
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @todo Check whether something needs to be done with the ORM-cascade
   * stuff.
   */
  public function beforeDeleteTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /*** @var Entities\ProjectParticipantField $field */
    $field = $this->legacyRecordToEntity($pme->rec);
    /** @var Entities\ProjectEvent $projectEvent */
    $projectEvent = $field->getProjectEvent();
    if ($field->usage() == 1 && !empty($field->getProjectEvent())) {
      // if the logged in user decides to delete this field (again) then we
      // should not hinder it too much ...
      $projectEvent->setAbsenceField(null);
      $field->setProjectEvent(null);
    }

    $this->participantFieldsService->deleteField($field);

    $changed = []; // disable PME delete query

    $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);

    return true; // but run further triggers if appropriate
  }

  /**
   * @param null|int $fieldId May be null in add mode.
   *
   * @return iterable All option kleys for the given field.
   */
  private function optionKeys(?int $fieldId):iterable
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class)->optionKeys($fieldId);
  }

  /**
   * Generate a row given values and index for the "change" view
   * corresponding to the multi-choice fields.
   *
   * @param mixed $value One row of the form as returned from
   * \OCA\CAFEVDB\Service\ProjectParticipantFieldsService::explodeDataOptions().
   *
   *
   * @param int $index A unique row number.
   *
   * @param boolean $used Whether the DB already contains data
   * records referring to this item.
   *
   * @param string $dataType Curent data-type in order to establish some
   * default values.
   *
   * @return string HTML data for one row.
   */
  public function dataOptionInputRowHtml(mixed $value, int $index, bool $used, ?string $dataType):string
  {
    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/recurring-receivable-definition-input-row', [
        'rowData' => $value,
        'index' => $index,
        'used' => $used,
        'dataType' => $dataType,
        'inputName' => $this->pme->cgiDataName('data_options'),
        'toolTips' => $this->toolTipsService,
      ],
      'blank'
    ))->render();
  }

  /**
   * Create the generator field in order to add new input rows. This
   * is one single text input for a new name which triggers creation
   * of a new input row from the JS change event.
   *
   * @param null|int $fieldId Id of the current field in change mode, may be null in add mode.
   *
   * @param int $numberOfOptions The number of already set options s.t. the
   * placeholder row can generator indices for new options.
   *
   * @param null|array|Entities\ProjectParticipantFieldDataOption $generatorItem
   *     Special data item with key Uuid::NIL which holds
   *     the data for auto-generated fields.
   *
   * @return string HTML data for the generator button.
   */
  private function dataOptionGeneratorHtml(?int $fieldId, int $numberOfOptions, mixed $generatorItem):string
  {
    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/recurring-receivable-definition-generator-row', [
        'fieldId' => $fieldId,
        'generatorItem' => $generatorItem,
        'generators' => $this->participantFieldsService->recurringReceivablesGenerators(),
        'numberOfOptions' => $numberOfOptions,
        'inputName' => $this->pme->cgiDataName('data_options'),
        'toolTips' => $this->toolTipsService,
        'dateTimeFormatter' => $this->dateTimeFormatter(),
      ],
      'blank'
    ))->render();
  }

  /**
   * Generate a table in order to define field-valus for
   * multi-select stuff.
   *
   *      |  key        | label | data | limit          | deposit     |  tooltip
   * ==================================================================================
   * show | expert-mode |       |      | groupofpeople  | receivables |
   *      |             |       |      | groupsofpeople | liabilities |
   *      |             |       |      | date/time (?)  |             |
   *
   * @param mixed $value
   *
   * @param string $op
   *
   * @param null|int $fieldId May be null in add mode.
   *
   * @param null|string $multiplicity
   *
   * @param null|string $dataType
   *
   * @return string
   */
  private function showDataOptions(
    mixed $value,
    string $op,
    ?int $fieldId,
    ?string $multiplicity = null,
    ?string $dataType = null,
  ):string {
    $this->logDebug('OPTIONS so far: '.print_r($value, true));
    $allowed = $this->participantFieldsService->explodeDataOptions($value);
    if ($op === PHPMyEdit::OPERATION_DISPLAY) {
      if (count($allowed) == 1) {
        // "1" means empty (headerline)
        return '';
      }
      switch ($multiplicity) {
        case Multiplicity::SIMPLE:
          return '';
        case Multiplicity::SINGLE:
          $singleOption = reset($allowed);
          switch ($dataType) {
            case DataType::BOOLEAN:
              return $this->l->t('true') . ' / ' . $this->l->t('false');
            case DataType::RECEIVABLES:
            case DataType::LIABILITIES:
              return $this->moneyValue(0) . ' / ' . $this->moneyValue($singleOption['data']);
            case DataType::DATE:
              $fieldValue = $singleOption['data'];
              if (!empty($fieldValue)) {
                $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
                $fieldValue = $this->dateTimeFormatter()->formatDate($date, 'medium');
              }
              return $fieldValue;
            case DataType::DATETIME:
              $fieldValue = $singleOption['data'];
              if (!empty($fieldValue)) {
                $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
                $fieldValue = $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
              }
              return $fieldValue;
            default:
              return '['.$this->l->t('empty').']'.' / '.$singleOption['data'];
          }
      }
    }
    $html = '<div class="pme-cell-wrapper quarter-sized">';
    if ($op === 'add' || $op === 'change') {
      // controls for showing soft-deleted options or normally
      // unneeded inputs
      $showDeletedLabel = $this->l->t("Show deleted items.");
      $showDeletedTip = $this->toolTipsService['page-renderer:participant-fields:show-deleted'];
      $showDataLabel = $this->l->t("Show data-fields.");
      $showDataTip = $this->toolTipsService['page-renderer:participant-fields:show-data'];
      $displayOptions = '
<div class="field-display-options dropdown-container">
  <div class="field-display-options dropdown-button icon-menu"
       title="' . $this->toolTipsService['page-renderer:participant-fields:show-display-options'] . '">
  </div>
  <nav class="field-display-options dropdown-content dropdown-dropup dropdown-align-right">
    <ul class="menu-list">
      <li class="menu-item show-deleted tooltip-left" title="' . $showDeletedTip . '">
        <label class="show-deleted menu-item" for="data-options-show-deleted">
          <input type="checkbox"
                 name="show-deleted"
                 class="show-deleted checkbox"
                 value="show"
                 id="data-options-show-deleted"
                 ' . ($this->showDisabled ? 'checked' : '') . '
          />
          <img class="show-deleted checkmark"
               alt=""
               src="'.$this->urlGenerator()->imagePath('core', 'actions/checkmark.svg').'"
          >
          ' . $showDeletedLabel . '
        </label>
      </li>
      <li class="menu-item show-data tooltip-left" title="'.$showDataTip.'">
        <label class="show-data menu-item" for="data-options-show-data">
          <input type="checkbox"
                 name="show-data"
                 class="show-data checkbox"
                 value="show"
                 id="data-options-show-data"
          />
          <img class="show-data checkmark"
               alt=""
               src="'.$this->urlGenerator()->imagePath('core', 'actions/checkmark.svg').'"
          >
          '.$showDataLabel.'
        </label>
      </li>
    </ul>
  </nav>
</div>
';
      $html .= $displayOptions;
    }

    $cssClass = [
      'operation-'.$op,
      'data-options',
    ];
    if (!empty($multiplicity)) {
      $cssClass[] = 'multiplicity-'.$multiplicity;
    }
    if (!empty($dataType)) {
      $cssClass[] = 'data-type-'.$dataType;
    }

    if ($multiplicity == Multiplicity::RECURRING) {
      foreach ($allowed as $value) {
        if ($value['key'] === Uuid::NIL) {
          $generatorItem = $value;
          break;
        }
      }
      $generator = $generatorItem['data']??null;
      if (!empty($generator)) {
        $cssClass[] = 'recurring-generator-' . $generator::slug();
      }
      $operationLabels = $generator::operationLabels();
      foreach ($operationLabels as $slug => $value) {
        if (is_callable($value)) {
          $value = true;
        }
        $cssClass[] = 'recurring-' . $slug . '-' . ($value ? 'en' : 'dis') . 'abled';
      }
    }
    $cssClass[] = $this->showDisabled ? 'show-deleted' : 'hide-deleted';

    $cssClass = implode(' ', $cssClass);
    $html .= '<table
  class="'.$cssClass.'"
  data-size=\'' . json_encode(self::OPTION_DATA_INPUT_SIZE) . '\'>
  <thead>
     <tr>';
    $html .= '<th class="operations"></th>';
    if ($multiplicity == Multiplicity::RECURRING) {
      $headers = [
        'key' => $this->l->t('Key'),
        'label' => $this->l->t('Label'),
        'data' => $this->l->t('Data'),
        'deposit' => $this->l->t('Deposit') . ' ['.$this->currencySymbol().']',
        'limit' => $this->l->t('Limit'),
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
      $cssClass = self::OPTION_DATA_SHOW_MASK[$key]??[];
      $cssClass[] = 'field-'.$key;
      $cssClass = implode(' ', $cssClass);
      $html .=
            '<th'
            .' class="'.$cssClass.'"'
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
            $fieldValue = $value[$field];
            if ($multiplicity == Multiplicity::RECURRING) {
              if ($field == 'limit' && !empty($fieldValue)) {
                $fieldValue = $this->dateTimeFormatter()->formatDate(
                  Util::convertToDateTime($fieldValue),
                  'medium');
              }
            } else {
              switch ($dataType) {
                case DataType::RECEIVABLES:
                case DataType::LIABILITIES:
                  $fieldValue = $this->currencyValue($fieldValue);
                  break;
                case DataType::DATE:
                  if (!empty($fieldValue)) {
                    try {
                      $reporting = error_reporting(0);
                      $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
                      $fieldValue = $this->dateTimeFormatter()->formatDate($date, 'medium');
                      error_reporting($reporting);
                    } catch (\Throwable $t) {
                      error_reporting($reporting);
                      $this->logInfo('IGNORE DATE PARSE ERROR');
                      // ignore
                    }
                  }
                  break;
                case DataType::DATETIME:
                  if (!empty($fieldValue)) {
                    try {
                      $reporting = error_reporting(0);
                      $date = DateTime::parse($fieldValue, $this->getDateTimeZone());
                      $fieldValue = $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
                      error_reporting($reporting);
                    } catch (\Throwable $t) {
                      error_reporting($reporting);
                      $this->logInfo('IGNORE DATE PARSE ERROR');
                      // ignore
                    }
                  }
                  break;
                default:
                  break;
              }
            }
            if ($field == 'deposit') {
              $fieldValue = $this->currencyValue($fieldValue);
            }
            $css = self::OPTION_DATA_SHOW_MASK[$field]??[];
            $css[] = 'field-'.$field;
            $css = implode(' ', $css);
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
          if ($key == Uuid::NIL && $multiplicity != Multiplicity::GROUPOFPEOPLE) {
            $generatorItem = $value;
            continue;
          }
          $used = array_search(Uuid::uuidBytes($key), $usedKeys) !== false;
          $html .= $this->dataOptionInputRowHtml($value, $idx, $used, $dataType);
          $idx++;
        }
        $html .= $this->dataOptionGeneratorHtml($fieldId, $idx, $generatorItem);
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
   * @param mixed $dataOptions
   *
   * @param null|string $multiplicity May be null in add mode.
   *
   * @param null|string $dataType May be null in add mode.
   *
   * @return array
   */
  private function getAllowedSingleValue(mixed $dataOptions, ?string $multiplicity, ?string $dataType):array
  {
    $allowed = $this->participantFieldsService->explodeDataOptions($dataOptions, false);
    $entry = null;
    foreach ($allowed as $option) {
      if (!empty($option['deleted'])) {
        continue;
      }
      if ($option['key'] == Uuid::NIL && $multiplicity == Multiplicity::GROUPOFPEOPLE) {
        $entry = $option;
      } elseif (empty($entry)) {
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
   *
   * @param mixed $dataOptions
   *
   * @param string $op
   *
   * @param null|string $toolTip
   *
   * @param null|string $multiplicity May be null in add mode.
   *
   * @param null|string $dataType May be null in add mode.
   *
   * @param string $multiplicityVariant The multiplicity-variant this is emitted for.
   *
   * @return string HTML fragment.
   */
  private function showAllowedSingleValue(
    mixed $dataOptions,
    string $op,
    ?string $toolTip,
    ?string $multiplicity,
    ?string $dataType,
    string $multiplicityVariant,
  ):string {
    list($entry, $allowed) = $this->getAllowedSingleValue($dataOptions, $multiplicity, $dataType);
    $value = $entry['data'];
    if ($op === PHPMyEdit::OPERATION_DISPLAY) {
      return $this->currencyValue($value);
    }
    $key = $entry['key'];
    $name  = $this->pme->cgiDataName('data_options_' . $multiplicityVariant);
    $field = 'data';
    if (!empty($value)) {
      try {
        switch ($dataType) {
          case DataType::DATE:
            $date = DateTime::parse($value, $this->getDateTimeZone());
            $value = $this->dateTimeFormatter()->formatDate($date, 'medium');
            break;
          case DataType::DATETIME:
            $date = DateTime::parse($value, $this->getDateTimeZone());
            $value = $this->dateTimeFormatter()->formatDateTime($date, 'medium', 'short');
            break;
        }
      } catch (\Throwable $t) {
        // ignore, may be no valid data when data-type changes.
      }
    }
    $value = htmlspecialchars($value);
    $tip   = $toolTip;
    $html  = '<div class="active-value">';
    if ($dataType == DataType::HTML) {
      $htmlDisabled = [ 'input' => 'disabled', 'textarea' => '' ];
    } else {
      $htmlDisabled = [ 'textarea' => 'disabled', 'input' => '' ];
    }
    $cssBase = [
      'pme-input',
      'data-options-'.$multiplicityVariant,
    ];
    $simple = $multiplicityVariant == 'simple';
    $cssClass = implode(' ', array_merge(
      $cssBase, [
        'field-'.$field,
        'data-type-html-hidden',
        'data-type-html-disabled',
        $simple ? null : 'receivables-data-type-required',
        $simple ? null : 'liabilities-data-type-required',
        $simple ? null : 'only-multiplicity-' . $multiplicityVariant . '-multiplicity-required',
      ]));
    $html  .=<<<__EOT__
<input class="{$cssClass}"
       {$htmlDisabled['input']}
       type="text"
       maxlength="29"
       size="30"
       value="{$value}"
       name="{$name}[{$key}][{$field}]"
       title="{$tip}"
/>
__EOT__;
    $cssClass = implode(' ', array_merge(
      $cssBase, [
        'field-'.$field,
        'default-hidden',
        'not-data-type-html-hidden',
        'not-data-type-html-disabled',
      ]));
    $html  .=<<<__EOT__
<span class="{$cssClass} pme-cell-wrapper">
  <textarea class="pme-input data-options-{$multiplicityVariant} data-type-html-wysiwyg-editor"
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
      $cssClass = implode(' ', array_merge(
        $cssBase, [
          'field-'.$field,
        ]));
      $html .=<<<__EOT__
<input class="{$cssClass}"
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
      foreach (['key', 'label', 'data', 'deposit', 'limit', 'tooltip', 'deleted'] as $field) {
        $value = htmlspecialchars($option[$field]);
        $html .=<<<__EOT__
<input class="pme-input data-options-{$multiplicityVariant}"
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
   *
   * @param mixed $value
   *
   * @return string HTML fragment.
   */
  private function currencyValue(mixed $value):string
  {
    return
      '<span class="service-fee-alternatives">
  <span class="general">'.$value.'</span>
  <span class="receivables liabilities currency-amount">' . $this->moneyValue($value) . '</span>
</span>';
  }

  /**
   * Return an alternate "Amount [CUR]" label which can be hidden by
   * CSS.
   *
   * @param string $label
   *
   * @param string $prefix
   *
   * @return string HTML fragment.
   */
  private function currencyLabel(string $label, string $prefix = ''):string
  {
    return
      '<span class="service-fee-alternatives">
  <span class="general">'.$prefix.$label.'</span>
  <span class="receivables liabilities currency-label">'.$prefix.$this->l->t('Amount').' ['.$this->currencySymbol().']'.'</span>
</span>';
  }

  /**
   * @param string $ifTrue SQL value if this is a FS entry.
   *
   * @param string $ifFalse SQL value if this is not a FS entry.
   *
   * @return string SQL fragment.
   */
  private static function ifFileSystemEntry(string $ifTrue, string $ifFalse):string
  {
    return 'IF($main_table.data_type IN ("'
      . DataType::CLOUD_FILE . '", "'
      . DataType::CLOUD_FOLDER . '"), ' . $ifTrue . ', ' . $ifFalse . ')';
  }
}
