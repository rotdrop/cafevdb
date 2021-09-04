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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

/**Table generator for Instruments table. */
class ProjectInstrumentationNumbers extends PMETableViewBase
{
  const TEMPLATE = 'project-instrumentation-numbers';
  const TABLE = self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE;

  // Projects Instruments ProjectInstruments
  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\ProjectInstrumentationNumber::class,
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    self::PROJECT_INSTRUMENTS_TABLE => [
      'entity' => Entities\ProjectInstrument::class,
      'identifier' => [
        'project_id' => 'project_id',
        'instrument_id' => 'instrument_id',
        'voice' => 'voice',
        'musician_id' => false,
      ],
      'column' => 'musician_id',
    ],
    self::INSTRUMENTS_TABLE => [
      'entity' => Entities\Instrument::class,
      'identifier' => [
        'id' => 'instrument_id',
      ],
      'column' => 'id',
    ],
    // [
    //   'table' => self::PROJECT_PARTICIPANTS_TABLE,
    //   'entity' => Entities\ProjectParticipant::class,
    //   'identifier' => [
    //     'project_id' => 'project_id',
    //     'musician_id' => [
    //       'table' => self::PROJECT_INSTRUMENTS_TABLE,
    //       'column' => 'musician_id',
    //     ],
    //   ],
    //   'column' => 'registration',
    // ],
  ];

  public function __construct(
    ConfigService $configService
  , RequestParameterService $requestParameters
  , EntityManager $entityManager
  , PHPMyEdit $phpMyEdit
  , ToolTipsService $toolTipsService
  , PageNavigation $pageNavigation
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
  }

  public function shortTitle()
  {
    if ($this->projectName) {
      return $this->l->t("Instrumentation Numbers for `%s'", [ $this->projectName ]);
    } else {
      return $this->l->t("Instrumentation Numbers");
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    $header .= "<p>".$this->l->t("The target instrumentation numbers can be filled into this table. ".
                          "The `have'-numbers are the numbers of the musicians ".
                          "already registered for the project.");

    return '<div class="'.$this->cssClass().'-header-text">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template       = $this->template;
    $projectName    = $this->projectName;
    $projectId      = $this->projectId;
    $projectMode    = $this->projectId > 0;
    $instruments    = $this->instruments;
    $recordsPerPage = $this->recordsPerPage;

    $opts            = [];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'instrument_id' => 'int', 'voice' => 'int' ];
    $opts['groupby_fields'] = array_keys($opts['key']);
    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'year'),
      $this->joinTableFieldName(self::PROJECTS_TABLE, 'name'),
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      'voice',
    ];

    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    if ($projectMode) {
      $opts['options'] = 'ACDFVP';
      $sort = false;
    } else {
      $opts['options'] = 'ACDFVP';
      $sort = true;
    }

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '16';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => $sort,
      'time'  => true,
      'tabs'  => false,
      'navigation' => 'CDVP',
    ];

    // field definitions

    $joinTables = $this->defineJoinStructure($opts);

    $opts['fdd']['project_id'] = [
      'name'      => $this->l->t('Project'),
      'input'     => ($projectMode ? 'HR' : ''),
      'css' => [ 'postfix' => [ 'project-instrument-project-name', ], ],
      'select|DV' => 'T', // delete, view
      'select|ACPFL' => 'D',  // add, change, copy, filter, list
      'maxlen'   => 20,
      'size'     => 16,
      'default'  => ($projectMode ? $projectId : null),
      'sort'     => $sort,
      'values|ACP' => [
        'table'       => self::PROJECTS_TABLE,
        'column'      => 'id',
        'description' => [
          'columns' => [ 'name' ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => false, // [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
      ],
      'values|DVFL' => [
        'table'       => self::PROJECTS_TABLE,
        'column'      => 'id',
        'description' => [
          'columns'   => [ 'name' ],
          'cast'   => [ false ],
          'ifnull' => [ false ],
        ],
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
        'filters'     => '$table.id IN (SELECT project_id FROM $main_table)',
        'join'        => false, // [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
      ],
    ];
    $this->addSlug('project', $opts['fdd']['project_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year',
      [
        'name'  => $this->l->t('Project Year'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['instrument_id'] = [
      'name'     => $this->l->t('Instrument'),
      'select'   => 'D',
      'options'  => 'LACPDVF',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => $sort,
      'sql'      => '$main_table.instrument_id',
      'values'   => [
        'table' => self::INSTRUMENTS_TABLE,
        'column' => 'id',
        'description' => [
          'columns' => [ 'name' ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby' => '$table.sort_order',
        'join' => false, // [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      //'values2|AVCPDLF' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $this->addSlug('instrument', $opts['fdd']['instrument_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENTS_TABLE, 'sort_order',
      [
        'name'  => $this->l->t('Orchestral Sorting'),
        'input' => 'VHR',
      ]);

    $opts['fdd']['voice'] = [
      'name'     => $this->l->t('Voice'),
      'select'   => 'D',
      'options'  => 'LACPDVF',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => $sort,
      'values' => [
        'table' => self::TABLE,
        'column' => 'voice',
        'description' => [
          'columns' => [ 'IF($table.$column = 0, "' . $this->l->t('n/a') . '", $table.$column)', ],
          'cast' => [ false, ],
        ],
        'filters' => $projectMode ? '$table.project_id = ' . $this->projectId : null,
        'join' => false,
      ],
      //'values2' => [ '0' => $this->l->t('n/a') ] + array_combine(range(1, 8), range(1, 8)),
    ];
    $opts['fdd']['voice']['values|ACP'] = array_merge(
      $opts['fdd']['voice']['values'], [
        'table' => 'SELECT
  n.n AS voice
  FROM ' . self::TABLE . ' t
  JOIN numbers n
    ON n.n <= GREATEST(4, (t.voice + 1))
  ' . ($projectMode ? 'WHERE t.project_id = ' . $this->projectId : '') .'
  GROUP BY n.n',
        'filters' => null,
      ]);
    $this->addSlug('voice', $opts['fdd']['voice']);

    // required quantity
    $opts['fdd']['quantity'] = [
      'name' => $this->l->t('Required'),
      'select' => 'N',
      'sort' => $sort,
      'align' => 'right',
    ];
    $this->addSlug('required', $opts['fdd']['quantity']);

    list($index, $name) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'musician_id',
      [
        'name'   => $this->l->t('Registered'),
        'options' => 'VDLFCP',
        'input'  => 'VR',
        'sort'   => $sort,
        'select' => 'N',
        'align' => 'right',
        'sql'    => 'COUNT($join_col_fqn)',
      ]);
    $this->addSlug('registered', $opts['fdd'][$name]);

    list($index, $name) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANTS_TABLE, 'musician_id',
      [
        'name'   => $this->l->t('Confirmed'),
        'options' => 'VDLFCP',
        'input'  => 'VR',
        'sort'   => $sort,
        'select' => 'N',
        'align' => 'right',
        'sql'    => 'COUNT($join_col_fqn)',
        'values' => [
          'join' => '$join_table.project_id = $main_table.project_id
  AND $join_col_fqn = '.$joinTables[self::PROJECT_INSTRUMENTS_TABLE].'.musician_id
  AND $join_table.registration = 1',
        ],
      ]);
    $this->addSlug('confirmed', $opts['fdd'][$name]);

    $joinTables[self::PROJECT_PARTICIPANTS_TABLE] = 'PMEjoin'.$index;

    // @todo tooltips
    $opts['fdd']['missing'] = [
      'name'   => $this->l->t('Balance'),
      'options' => 'VDLFCP',
      'input'  => 'VR',
      'sort'   => $sort,
      'select' => 'N',
      'sql'    => "CONCAT(
  COUNT(".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".musician_id) - \$main_table.quantity,
  ':',
  COUNT(".$joinTables[self::PROJECT_PARTICIPANTS_TABLE].".musician_id) - \$main_table.quantity
)",
      'php' => function($balance, $op, $field, $row, $recordId, $pme) {
        $values = Util::explode(':', $balance);
        $html = '';
        $html .= '<span'.($values[0] < 0 ? ' class="negative"' : '').'>'.$values[0].'</span>';
        $html .= ' / ';
        $html .= '<span'.($values[1] < 0 ? ' class="negative"' : '').'>'.$values[1].'</span>';
        return $html;
      },
      'escape' => false,
      'align'  => 'right',
    ];
    $this->addSlug('balance', $opts['fdd']['missing']);

    if ($projectMode) {
      $opts['filters'] = '$table.project_id = '.$projectId;
    }

    // trigger

    // redirect all updates through Doctrine\ORM.
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];

    // go

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

}
