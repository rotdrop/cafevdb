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
  const TEMPLATE = 'instruments';
  const TABLE = self::INSTRUMENTS_TABLE;
  private const INSTRUMENT_FAMILIES_TABLE = 'InstrumentFamilies';
  private const INSTRUMENT_FAMILIES_JOIN_TABLE = 'instrument_instrument_family';
  private const TRANSLATIONS_TABLE = 'TableFieldTranslations';

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

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , BiDirectionalL10N $musicL10n
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->projectMode = false;
    $this->musicL10n = $musicL10n;
    $this->getDatabaseRepository(Entities\Instrument::class)->findAll();
    $this->flush();
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Delete Instrument');
    } else if ($this->viewOperation()) {
      return $this->l->t('View Instrument');
    } else if ($this->changeOperation()) {
      return $this->l->t('Change Instrument');
    } else if ($this->addOperation()) {
      return $this->l->t('Add Instrument');
    } else if ($this->copyOperation()) {
      return $this->l->t('Copy Instrument');
    }
    return $this->l->t("Instruments and Instrument Sort-Order");
  }

  public function headerText()
  {
    $header = $this->shortTitle();

    return '<div class="'.$this->cssPrefix().'-header-text">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $instruments     = $this->instruments;
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
      'recordsPerPage' => $recordsPerPage,
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
        $joinInfo['identifier']['locale']['value'] = $this->l10N()->getLanguageCode();
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
        'identifier' => [
          $columns[0] => false,
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
      'sql'    => $joinTables[self::TRANSLATIONS_TABLE].'.content',
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
        'filter'       => 'having',
        'select'       => 'M',
        'values' => [
          'description' => 'l10n_family',
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
          //'datemask' => 'd.m.Y H:i:s',
        ]
      );
    }

    $usageSQL = [];
    foreach ($instrumentTables as $table => $columns) {
      $usageSQL[] = 'COUNT(DISTINCT '.$joinTables[$table].'.'.($columns[0]).')';
    }
    $usageSQL = implode('+', $usageSQL);
    $usageIdx = count($opts['fdd']);
    $opts['fdd']['usage'] = [
      'name'    => $this->l->t('#Usage'),
      'input'   => 'VR',
      'input|D' => 'VR',
      'sql'     => '('.$usageSQL.')',
      'sort'    => false,
      'size'    => 5,
      'select'  => 'N',
      'align'   => 'right',
    ];

    $lang = locale_get_primary_language($this->l->getLanguageCode());

    // Provide a link to Wikipedia for fun ...
    $opts['fdd']['encyclopedia'] = [
      'name'    => 'Wikipedia',
      'select'  => 'T',
      'input'   => 'VR',
      'options' => 'LF',
      'sql'     => '$main_table.id',
      'php'   =>  function($value, $op, $field, $row, $recordId, $pme) use ($lang) {
        $inst = $row[$this->queryField('name', $pme->fdd)];
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

    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts['triggers']['delete']['before'][] = [ $this, 'beforeDeleteTrigger' ];


    $opts['triggers']['select']['data'][] =
      function(&$pme, $op, $step, &$row) use ($usageIdx, $expertMode)  {
        if (!$expertMode && !empty($row['qf'.$usageIdx])) {
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

  /**Convert an array of ids to an array of names.*/
  public function instrumentNames($instrumentIds)
  {
    $byId = $this->instrumentInfo['byId'];
    $result = array();
    foreach($instrumentIds as $id) {
      $result[$id] = empty($byId[$id]) ? $this->l->t('unknown') : $byId[$id];
    }
    return $result;
  }

  /**
   * This is a phpMyEdit before-SOMETHING trigger.
   *
   * phpMyEdit calls the trigger (callback) with
   * the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldValues Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newValues Set of new values, which may also be modified.
   *
   * @return boolean If returning @c false the operation will be terminated
   */
  public function beforeDeleteTrigger(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
  {
    $entity = $this->getDatabaseRepository($this->joinStructure[self::TABLE]['entity'])
                   ->find($pme->rec);
    $this->remove($entity, true);

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
  }

}
