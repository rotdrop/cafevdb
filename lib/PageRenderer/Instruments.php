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

use OCP\IL10N;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\L10N\BiDirectionalL10N;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

/** Table generator for Instruments table. */
class Instruments extends PMETableViewBase
{
  use \OCA\CAFEVDB\PageRenderer\FieldTraits\QueryFieldTrait;

  const TEMPLATE = 'instruments';
  const TABLE = self::INSTRUMENTS_TABLE;
  private const INSTRUMENT_FAMILIES_TABLE = 'InstrumentFamilies';
  private const INSTRUMENT_FAMILIES_JOIN_TABLE = 'instrument_instrument_family';
  private const TRANSLATIONS_TABLE = self::FIELD_TRANSLATIONS_TABLE;

  /** @var BiDirectionalL10N */
  private $musicL10n;

  /**
   * @var array
   * @see PMETableViewBase::defineJoinStructure()
   */
  protected $joinStructure = [
    self::TABLE => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_MASTER,
    ],
    self::TRANSLATIONS_TABLE => [
      'entity' => null,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'locale' => [ 'value' => null ], // to be set
        'object_class' => [ 'value' => Entities\Instrument::class ],
        'field' => [ 'value' => 'name' ],
        'foreign_key' => 'id',
      ],
      'column' => 'content',
    ],
    self::INSTRUMENT_FAMILIES_JOIN_TABLE => [
      'entity' => null,
      'identifier' => [
        'instrument_id' => 'id',
        'instrument_family_id' => false,
      ],
      'column' => 'instrument_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::INSTRUMENT_FAMILIES_TABLE => [
      'table' => self::INSTRUMENT_FAMILIES_TABLE,
      'entity' => Entities\InstrumentFamily::class,
      'identifier' => [
        'id' => [
          'table' => self::INSTRUMENT_FAMILIES_JOIN_TABLE,
          'column' => 'instrument_family_id',
        ],
      ],
      'column' => 'id',
      'association' => 'families',
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
    BiDirectionalL10N $musicL10n,
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->projectMode = false;
    $this->musicL10n = $musicL10n;
    $this->getDatabaseRepository(Entities\Instrument::class)->findAll();
    $this->flush();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Delete Instrument');
    } elseif ($this->viewOperation()) {
      return $this->l->t('View Instrument');
    } elseif ($this->changeOperation()) {
      return $this->l->t('Change Instrument');
    } elseif ($this->addOperation()) {
      return $this->l->t('Add Instrument');
    } elseif ($this->copyOperation()) {
      return $this->l->t('Copy Instrument');
    }
    return $this->l->t("Instruments and Instrument Sort-Order");
  }

  /** {@inheritdoc} */
  public function headerText()
  {
    $header = $this->shortTitle();

    return '<div class="'.$this->cssPrefix().'-header-text">'.$header.'</div>';
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $template        = $this->template;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['css']['postfix'] = [
      'direct-change',
      'show-hide-disabled',
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

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
    $opts['sort_field'] = [ 'sort_order' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => false,
      'navigation' => 'VCPD'
    ];

    $opts['fdd']['id'] = [
      'name'      => 'id',
      'select'    => 'N',
      'input'     => 'RH',
      'options'   => 'VCPDL',
      'maxlen'    => 11,
      'size'      => 11,
      'align'     => 'right',
      'sort'      => true,
      'default'   => '0',  // auto increment
    ];

    // set the locale into the joinstructure
    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      switch ($table) {
        case self::TRANSLATIONS_TABLE:
          $joinInfo['identifier']['locale']['value'] = $this->getTranslationLanguage();
          break;
        case self::INSTRUMENT_FAMILIES_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'family');
          break;
        default:
          break;
      };
    });

    // Provide joins with MusicianInstruments, ProjectInstruments,
    // ProjectInstrumentationNumbers in order to flag used instruments as
    // undeletable, while allowing deletion for unused ones (more
    // practical after adding new instruments)
    $instrumentTables = [
      self::MUSICIAN_INSTRUMENTS_TABLE => [ 'musician_id', 'instrument_id' ],
      self::PROJECT_INSTRUMENTS_TABLE => [ 'project_id', 'instrument_id' ],
      self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE => [ 'project_id', 'instrument_id' ],
    ];
    foreach ($instrumentTables as $table => $columns) {
      $this->joinStructure[$table] = [
        'entity' => null,
        'sql' => "SELECT $columns[1], COUNT(DISTINCT $columns[0]) AS count
FROM $table
GROUP BY $columns[1]",
        'identifier' => [
          'count' => false,
          $columns[1] => 'id'
        ],
        'column' => $columns[1],
        'flags' => self::JOIN_READONLY,
      ];
    }

    // define join tables
    $joinTables = $this->defineJoinStructure($opts);

    $opts['fdd']['name'] = [
      'name'   => $this->l->t('Instrument'),
      'sql'    => 'COALESCE('.$joinTables[self::TRANSLATIONS_TABLE].'.content, $main_table.$field_name)',
      'select' => 'T',
      'maxlen' => 64,
      'sort'   => true,
    ];

    $opts['fdd']['sort_order'] = [
      'name'     => $this->l->t('sort order'),
      'select'   => 'N',
      'default'  => 0,
      'sqlw'     => 'IF($val_qas = "", 0, $val_qas)',
      'maxlen'   => 9,
      'size'     => 6,
      'sort'     => true,
      'align'    => 'right',
      ];

    list(, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENT_FAMILIES_TABLE, 'id', [
        'name'         => $this->l->t('Families'),
        'css'          => [ 'postfix' => ' instrument-families' ],
        'display|LVFD' => [ 'popup' => 'data' ],
        'sort'         => true,
        'sql'          => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'filter'       => [
          'having' => true,
        ],
        'select'       => 'M',
        'values' => [
          'description' => [
            'columns' => [ 'l10n_family' ],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'orderby'     => '$description ASC',
        ],
      ]);

    $opts['fdd'][$fieldName]['values|ACP'] = array_merge(
      $opts['fdd'][$fieldName]['values'],
      [ 'filters' => '$table.deleted IS NULL' ]);

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
        ]
      );
    }

    $usageSQL = [];
    foreach ($instrumentTables as $table => $columns) {
      $usageSQL[] = $joinTables[$table]  . '.count';
    }
    $usageSQL = 'COALESCE(' . implode('+', $usageSQL) . ', 0)';
    $usageIdx = count($opts['fdd']);
    $opts['fdd']['usage'] = [
      'name'    => $this->l->t('#Usage'),
      'input'   => 'VR',
      'input|D' => 'VR',
      'sql'     => '('.$usageSQL.')',
      'sort'    => true,
      'size'    => 5,
      'select'  => 'N',
      'align'   => 'right',
    ];

    $lang = locale_get_primary_language($this->getTranslationLanguage());

    // Provide a link to Wikipedia for fun ...
    $opts['fdd']['encyclopedia'] = [
      'name'    => 'Wikipedia',
      'select'  => 'T',
      'input'   => 'VR',
      'options' => 'LF',
      'sql'     => '$main_table.id',
      'php'   =>  function($value, $op, $field, $row, $recordId, $pme) use ($lang) {
        $inst = $row[$this->queryField('name')];
        return '<a '
          .'href="http://'.$lang.'.wikipedia.org/wiki/'.$inst.'" '
          .'target="Wikipedia.'.$lang.'" '
          .'>'
          .$inst.'@Wikipedia.'.$lang.'</a>';
      },
      'escape' => false,
      'nowrap' => true,
    ];

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    $opts['groupby_fields'] = [ 'id' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteSimplyDoDelete' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      function(&$pme, $op, $step, &$row) use ($usageIdx, $expertMode) {
        if (!$expertMode && !empty($row[PHPMyEdit::QUERY_FIELD . $usageIdx])) {
          $pme->options = str_replace('D', '', $pme->options);
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
   * Convert an array of ids to an array of names.
   *
   * @param array $instrumentIds
   *
   * @return array
   */
  public function instrumentNames(array $instrumentIds)
  {
    $byId = $this->instrumentInfo['byId'];
    $result = [];
    foreach ($instrumentIds as $id) {
      $result[$id] = empty($byId[$id]) ? $this->l->t('unknown') : $byId[$id];
    }
    return $result;
  }
}
