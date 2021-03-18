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
  private const INSTRUMENTS_TABLE = 'Instruments';
  private const INSTRUMENTS_JOIN_TABLE = 'instrument_instrument_family';

  /** @var BiDirectionalL10N */
  private $musicL10n;

  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'entity' => Entities\InstrumentFamily::class,
      'master' => true,
    ],
    [
      'table' => self::INSTRUMENTS_JOIN_TABLE,
      'entity' => null,
      'identifier' => [
        'instrument_family_id' => 'id',
        'instrument_id' => false,
      ],
      'column' => 'instrument_family_id',
      'read_only' => true,
    ],
    [
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

    $opts['css']['postfix'] = 'direct-change show-hide-disabled';

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
      //'input|AP'  => 'RH',
      'input'     => 'RH',
      'maxlen'    => 11,
      'size'      => 5,
      'align'     => 'right',
      'sort'      => true,
      ];

    // define join tables
    $joinTables = $this->defineJoinStructure($opts);

    $opts['fdd']['family'] = [
      'name'   => $this->l->t('Family'),
      'select' => 'T',
      'maxlen' => 64,
      'sort'   => true,
      'php|LVDF'    => function($value) {
        return $this->musicL10n->t($value);
      },
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
        'php|LVDF'     =>  function($value, $op, $field, $row, $recordId, $pme) {
          if (empty($value)) {
            return $value;
          }
          $parts = Util::explode(',', $value, Util::TRIM);
          foreach ($parts as &$part) {
            $part = $this->musicL10n->t($part);
          }
          return implode(',', $parts);
        },
        'values' => [
          'description' => 'name',
          'orderby'     => 'name ASC',
        ],
      ]);

    $opts['fdd'][$fieldName]['values|ACP'] = array_merge(
      $opts['fdd'][$fieldName]['values'],
      [ 'filters' => 'IFNULL($table.disabled,0) = 0' ]);

    if ($this->showDisabled) {
      $opts['fdd']['disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'options' => $expertMode ? 'LAVCPDF' : 'LAVCPDF',
        'input'    => $expertMode ? '' : 'R',
        'select'   => 'C',
        'maxlen'   => 1,
        'sort'     => true,
        'escape'   => false,
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' ],
        'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
        'tooltip'  => $this->toolTipsService['instrument-family-disabled'],
        'css'      => [ 'postfix' => ' instrument-family-disabled' ],
      ];
    }

    $opts['filters'] = "PMEtable0.Disabled <= ".intval($this->showDisabled);

    $opts['groupby_fields'] = [ 'id' ];

    $opts['triggers']['update']['before'][] =
      $opts['triggers']['insert']['before'][] = function(&$pme, $op, $step, $oldValues, &$changed, &$newValues) {
      $key = 'family';
      $chg = array_search($key, $changed);
      if ($chg === false) {
        return true;
      }
      // a kludge "... did you mean celesta?"
      $value = $newValues[$key];
      $newValues[$key] = $this->musicL10n->backTranslate($value);
      $this->logInfo('BACK TRANSLATE '.$value.' / '.$newValues[$key]);
      return true;
    };

    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][] = [ $this, 'beforeInsertDoInsertAll' ];
    $opts['triggers']['delete']['before'][] = [ $this, 'beforeDeleteTrigger' ];

    $opts['triggers']['select']['data'][] =
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
   * @return boolean. If returning @c false the operation will be terminated
   */
  public function beforeDeleteTrigger(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
  {
    // $this->logInfo("Record key is ".print_r($pme->rec, true));
    // $entity = $this->getDatabaseRepository(ORM\Entities\InstrumentFamily::class)->find($pme->rec);

    // if (false && $entity->usage() > 0) {
    //   $this->logInfo("Soft-delete entity ".print_r($pme->rec));
    //   $entity->setDisabled(true);
    //   $this->persist($entity);
    //   $this->flush();
    //   return false;
    // }

    return true;
  }
}
