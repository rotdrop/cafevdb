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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Instruments table. */
class InstrumentFamilies extends PMETableViewBase
{
  const CSS_CLASS = 'instrument-families';
  const TABLE = 'InstrumentFamilies';

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->projectMode = false;
  }

  public function cssClass() { return self::CSS_CLASS; }

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

    $template = 'instrument-families';
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
      'input|AP'  => 'RH',
      'input'     => 'R',
      'maxlen'    => 11,
      'size'      => 5,
      'default'   => '0',  // auto increment
      'align'     => 'right',
      'sort'      => true,
      ];

    $opts['fdd']['family'] = [
      'name'   => $this->l->t('Family'),
      'select' => 'T',
      'maxlen' => 64,
      'sort'   => true,
      'php|LVDF'    => function($value) {
        return $this->l->t($value);
      },
    ];

    $instFamIdx = count($opts['fdd']);
    $opts['fdd']['instrument_join'] = [
      'name' => 'InstrumentDummyJoin',
      'input'    => 'VRH',
      'sql'      => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table'       => 'instrument_instrument_family',
        'column'      => 'instrument_family_id',
        'orderby'     => 'instrument_family_id ASC',
        'description' => [ 'columns' => 'instrument_id' ],
        'join'        => '$join_table.instrument_family_id = $main_table.id',
      )
    ];

    $instIdx = count($opts['fdd']);
    $opts['fdd']['instruments'] = [
      'name'        => $this->l->t('Instruments'),
      'input'       => 'VR',
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'filter'      => 'having',
      'select'      => 'M',
      'maxlen'      => 11,
      'php'   =>  function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        if (empty($value)) {
          return $value;
        }
        $parts = explode(',', $value);
        foreach ($parts as &$part) {
          $part = $this->l->t($part);
        }
        return implode(',', $parts);
      },
      'values' => [
        'table'       => 'Instruments',
        'column'      => 'id',
        'description' => 'name',
        'orderby'     => 'name ASC',
        'join'        => '$join_col_fqn = PMEjoin'.$instFamIdx.'.instrument_id'
      ],
    ];

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

    $opts['triggers']['delete']['before'][] = [ $this, 'beforeDeleteTrigger' ];

    $opts['triggers']['select']['data'][] =
      function(&$pme, $op, $step, &$row) use ($opts, $instIdx, $expertMode)  {
        if (!$expertMode && !empty($row['qf'.$instIdx])) {
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

  /**This is the phpMyEdit before-delete trigger.
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
