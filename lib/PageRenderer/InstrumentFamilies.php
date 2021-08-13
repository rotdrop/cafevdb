<?php
/*
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

/**Table generator for Instruments table. */
class InstrumentFamilies extends PMETableViewBase
{
  const TEMPLATE = 'instrument-families';
  const TABLE = 'InstrumentFamilies';
  private const INSTRUMENTS_JOIN_TABLE = 'instrument_instrument_family';
  private const TRANSLATIONS_TABLE = 'TableFieldTranslations';

  /** @var BiDirectionalL10N */
  private $musicL10n;

  protected $joinStructure = [
    self::TABLE => [
      'entity' => Entities\InstrumentFamily::class,
      'flags' => self::JOIN_MASTER,
    ],
    self::TRANSLATIONS_TABLE => [
      'entity' => null,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'locale' => [ 'value' => null ], // to be set
        'object_class' => [ 'value' => Entities\InstrumentFamily::class ],
        'field' => [ 'value' => 'family' ],
        'foreign_key' => 'id',
      ],
      'column' => 'content',
    ],
    self::INSTRUMENTS_JOIN_TABLE => [
      'entity' => null,
      'identifier' => [
        'instrument_family_id' => 'id',
        'instrument_id' => false,
      ],
      'column' => 'instrument_family_id',
      'flags' => self::JOIN_READONLY,
    ],
    self::INSTRUMENTS_TABLE => [
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'identifier' => [
        'id' => [
          'table' => self::INSTRUMENTS_JOIN_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
      'association' => 'instruments',
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
    $this->getDatabaseRepository(Entities\InstrumentFamily::class)->findAll();
    $this->flush();
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Delete Instrument-Family');
    } else if ($this->viewOperation()) {
      return $this->l->t('View Instrument-Family');
    } else if ($this->changeOperation()) {
      return $this->l->t('Change Instrument-Family');
    } else if ($this->addOperation()) {
      return $this->l->t('Add Instrument-Family');
    } else if ($this->copyOperation()) {
      return $this->l->t('Copy Instrument-Family');
    }
    return $this->l->t("Instrument-Families");
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

    $opts['cgi']['persist'] = array(
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
      );

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [ 'Family' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDPF';

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
      'options'  => 'VCPDL',
      'maxlen'    => 11,
      'size'      => 11,
      'align'     => 'right',
      'sort'      => true,
      'default'  => '0',  // auto increment
      ];

    // set the locale into the join-structure
    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      switch ($table) {
      case self::TRANSLATIONS_TABLE:
        $joinInfo['identifier']['locale']['value'] = $this->l10N()->getLanguageCode();
        break;
      case self::INSTRUMENTS_TABLE:
        $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
        break;
      default:
        break;
      }
    });

    // define join tables
    $joinTables = $this->defineJoinStructure($opts);

    $opts['fdd']['family'] = [
      'name'   => $this->l->t('Family'),
      'sql'    => 'IFNULL('.$joinTables[self::TRANSLATIONS_TABLE].'.content,$field_name)',
      'select' => 'T',
      'maxlen' => 64,
      'sort'   => true,
    ];

    list(,$fieldName) = $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENTS_TABLE, 'id', [
        'name'         => $this->l->t('Instruments'),
        'css'          => [ 'postfix' => ' family-instruments' ],
        'display|LVFD' => [ 'popup' => 'data' ],
        'sort'         => true,
        'sql'          => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'filter'       => 'having',
        'select'       => 'M',
        'values' => [
          'description' => 'l10n_name',
          'orderby'     => 'sort_order ASC, $description ASC',
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

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    $opts['groupby_fields'] = [ 'id' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeDeleteTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] =
      function(&$pme, $op, $step, &$row) use ($expertMode)  {
        if (!$expertMode && !empty($row[$this->joinQueryField(self::INSTRUMENTS_TABLE, 'id', $pme->fdd)])) {
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
   * This is the phpMyEdit before-delete trigger.
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
