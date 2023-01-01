<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
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

  private const EXTRA_VOICES = 4;
  private const INSERT_VOICES = 8;

  // Projects Instruments ProjectInstruments
  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\ProjectInstrumentationNumber::class,
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [
        'id' => 'project_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
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
      'flags' => self::JOIN_READONLY,
    ],
    self::INSTRUMENTS_TABLE => [
      'entity' => Entities\Instrument::class,
      'identifier' => [
        'id' => 'instrument_id',
      ],
      'column' => 'id',
      'flags' => self::JOIN_READONLY,
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
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);

    if ($this->listOperation()) {
      $this->pme->overrideLabel('Add', $this->l->t('New Voice'));
    }
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->projectName) {
      return $this->l->t("Instrumentation Numbers for `%s'", [ $this->projectName ]);
    } else {
      return $this->l->t("Instrumentation Numbers");
    }
  }

  /** {@inheritdoc} */
  public function headerText()
  {
    $header = $this->shortTitle();
    $header .= "<p>".$this->l->t("The target instrumentation numbers can be filled into this table. ".
                          "The `have'-numbers are the numbers of the musicians ".
                          "already registered for the project.");

    return '<div class="'.$this->cssClass().'-header-text">'.$header.'</div>';
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template       = $this->template;
    $projectId      = $this->projectId;
    $projectMode    = $this->projectId > 0;

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
      'input'     => ($projectMode ? 'R' : ''),
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
          'columns' => [ '$table.name' ],
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
          'columns'   => [ '$table.name' ],
          'cast'   => [ false ],
          'ifnull' => [ false ],
        ],
        'groups'      => 'year',
        'orderby'     => '$table.year DESC',
        'join'        => false, // [ 'reference' => $joinTables[self::PROJECTS_TABLE], ],
        'filters'     => '$table.id IN (SELECT project_id FROM $main_table)',
      ],
    ];
    $this->addSlug('project', $opts['fdd']['project_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'name',
      [
        'name'  => $this->l->t('Project Name'),
        'input' => 'VHR',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'year',
      [
        'name'  => $this->l->t('Project Year'),
        'input' => 'VHR',
      ]);

    $l10nInstrumentsTable = $this->makeFieldTranslationsJoin([
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'identifier' => [ 'id' => true ], // just need the key
    ], 'name');

    $opts['fdd']['instrument_id'] = [
      'name'     => $this->l->t('Instrument'),
      'select'   => 'D',
      'options'  => 'LACPDVF',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => $sort,
      'sql'      => '$main_table.instrument_id',
      'values|ACP'   => [
        'table' => $l10nInstrumentsTable, // self::INSTRUMENTS_TABLE,
        'column' => 'id',
        'description' => [
          'columns' => [ 'l10n_name' ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby' => '$table.sort_order',
        'join' => false, // [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      'values|DVFL'   => [
        'table' => $l10nInstrumentsTable, // self::INSTRUMENTS_TABLE,
        'column' => 'id',
        'description' => [
          'columns' => [ 'l10n_name' ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby' => '$table.sort_order',
        'join' => false, // [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
        'filters' => '$table.id in (SELECT instrument_id FROM $main_table)',
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
      'css'      => [ 'postfix' => [ 'allow-empty', ], ],
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
      'values2|A' => [ '0' => $this->l->t('n/a') ] + array_combine(range(1, self::INSERT_VOICES), range(1, self::INSERT_VOICES)),
    ];
    $opts['fdd']['voice']['values|CP'] = array_merge(
      $opts['fdd']['voice']['values'], [
        'table' => 'SELECT
  t.project_id, t.instrument_id, n.seq AS voice
  FROM ' . self::TABLE . ' t
  JOIN ' . self::SEQUENCE_TABLE . ' n
    ON n.seq <= GREATEST(' . self::EXTRA_VOICES . ', (t.voice + 1)) AND n.seq <= GREATEST(' . self::EXTRA_VOICES . ', 1 + (SELECT MAX(t2.voice) FROM ' . self::TABLE . ' t2))
  WHERE t.project_id = $record_id[project_id] AND t.instrument_id = $record_id[instrument_id]
  GROUP BY n.seq',
        'filters' => null,
      ]);
    $this->addSlug('voice', $opts['fdd']['voice']);

    $opts['fdd']['num_voices'] = [
      'name' => $this->l->t('#Voices'),
      'select' => 'N',
      'input' => 'VSR',
      'sql' => 'MAX($join_col_fqn)',
      'values' => [
        'table' => self::TABLE,
        'column' => 'voice',
        'join' => '$main_table.instrument_id = $join_table.instrument_id AND $main_table.project_id = $join_table.project_id',
      ],
      'align' => 'right',
      'sort' => $sort,
      'filter'       => [
        'having' => true,
      ],
    ];

    // required quantity
    $opts['fdd']['quantity'] = [
      'name' => $this->l->t('Required'),
      'select' => 'N',
      'sort' => $sort,
      'align' => 'right',
      'display' => [
        'attributes' => [
          'min' => 0,
        ],
      ],
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
        'sql'    => 'COUNT(DISTINCT $join_col_fqn)',
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
        'sql'    => 'COUNT(DISTINCT $join_col_fqn)',
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
  COUNT(DISTINCT ".$joinTables[self::PROJECT_INSTRUMENTS_TABLE].".musician_id) - \$main_table.quantity,
  ':',
  COUNT(DISTINCT ".$joinTables[self::PROJECT_PARTICIPANTS_TABLE].".musician_id) - \$main_table.quantity
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
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = function(PHPMyEdit &$pme, $op, $step, $oldVals, &$changed, &$newVals) {
      if (array_search('voice', $changed) === false
          && array_search('instrument_id', $changed) === false
          && array_search('project_id', $changed) === false) {
        return true;
      }

      if ($oldVals['voice'] == 0 || $oldVals['registered'] > 0) {
        // if this voice is already used or is the catch-all voice, then "just" create a new record
        if (!$this->beforeInsertDoInsertAll($pme, $op, $step, $oldVals, $changed, $newVals)) {
          return false;
        }
        $pme->rec['project_id'] = $newVals['project_id'];
        $pme->rec['instrument_id'] = $newVals['instrument_id'];
        $pme->rec['voice'] = $newVals['voice'];
      }
      return true;
    };
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    // $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {
    //   $voiceIndex = $pme->fdn['voice'];
    //   $instrumentIndex = $pme->fdn['instrument_id'];
    //   $registeredIndex = $pme->fdn[$this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'musician_id')];
    //   if ($row['qf' . $voiceIndex] == 0 || !empty($row['qf' . $registeredIndex])) {
    //     // disallow changes to instrument and voice as the integrity
    //     // constraints would not allow changing these anyway.
    //     $pme->fdd[$instrumentIndex]['input'] .= 'R';
    //     $pme->fdd[$voiceIndex]['input'] .= 'R';
    //   }

    //   return true;
    // };

    // @todo: here we need still delete triggers etc.
    // go

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }
}
