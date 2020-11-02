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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;

use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

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
    , ToolTipsService $toolTipsService
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService);
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
    $opts            = $this->pmeOptions;

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
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [ 'Family' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACDPF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '4';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => false,
        'navigation' => 'CPD'
      ]);

    $opts['fdd']['Id'] = [
      'name'      => 'Id',
      'select'    => 'N',
      'input|AP'  => 'RH',
      'input'     => 'R',
      'maxlen'    => 11,
      'size'      => 5,
      'default'   => '0',  // auto increment
      'align'     => 'right',
      'sort'      => true,
      ];

    $opts['fdd']['Family'] = [
      'name'     => $this->l->t('Family'),
      'select'   => 'T',
      'maxlen'   => 64,
      'sort'     => true,
    ];

    $instFamIdx = count($opts['fdd']);
    $opts['fdd']['InstrumentJoin'] = [
      'name' => 'InstrumentDummyJoin',
      'input'    => 'VRH',
      'sql'      => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instFamIdx.'.family_id
  ORDER BY PMEjoin'.$instFamIdx.'.family_id ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table'       => 'instrument_family',
        'column'      => 'instrument_id',
        'description' => [ 'columns' => 'instrument_id' ],
        'join'        => '$join_table.family_id = $main_table.Id',
      )
    ];

    $instIdx = count($opts['fdd']);
    $opts['fdd']['Instruments'] = [
      'name'        => $this->l->t('Instruments'),
      'input'       => 'VR',
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.Id ORDER BY PMEjoin'.$instIdx.'.instrument ASC)',
      'filter'      => 'having',
      'select'      => 'M',
      'maxlen'      => 11,
      'php'   =>  function($value, $op, $field, $fds, $fdd, $row, $recordId) {
        $parts = explode(',', $value);
        foreach ($parts as &$part) {
          $part = $this->l->t($part);
        }
        return implode(',', $parts);
      },
      'values' => [
        'table'       => 'Instruments',
        'column'      => 'id',
        'description' => 'instrument',
        'orderby'     => 'instrument',
        'join'        => '$join_table.id = PMEjoin'.$instFamIdx.'.instrument_id'
      ],
    ];

    if ($this->showDisabled) {
      $opts['fdd']['Disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'css'      => ['postfix' => ' instrument-disabled' ],
        'values2|CAP' => [ 1 => '' ],
        'values2|LVFD' => [ 1 => $this->l->t('true'),
                            0 => $this->l->t('false') ],
        'default'  => '',
        'select'   => 'O',
        'sort'     => true,
        'tooltip'  => $this->toolTipsService['instruments-disabled']
        ];
    }

    // Provide joins with MusicianInstruments, ProjectInstruments,
    // ProjectInstrumentation in order to flag used instruments as
    // undeletable, while allowing deletion for unused ones (more
    // practical after adding new instruments)
    $instrumentTables = [
      'musician_instrument' => [ 'musician_id', 'instrument_id' ],
      'project_instrument' => [ 'project_id', 'instrument_id' ],
      'ProjectInstrumentation' => [ 'ProjectId', 'InstrumentId' ],
    ];
    $usageIdx = count($opts['fdd']);
    foreach ($instrumentTables as $table => $indexes) {
      $opts['fdd'][$table] = [
        'input' => 'VRH',
        'sql' => 'PMEtable0.Id',
        'values' => [
          'table' => $table,
          'column' => $table[1],
          'description' => $indexes[1],
          'join' => '$main_table.Id = $join_table.'.$indexes[1],
          ],
        ];
    }
    $usageSQL = []; $i = 0;
    foreach ($instrumentTables as $table => $indexes) {
      $usageSQL[] = 'COUNT(DISTINCT PMEjoin'.($usageIdx+$i).'.'.($indexes[0]).')';
      $i++;
    }
    $usageSQL = implode('+', $usageSQL);
    $usageIdx = count($opts['fdd']);
    $opts['fdd']['Usage'] = [
      'name'    => $this->l->t('Usage'),
      'input'   => 'VR',
      'input|D' => 'VR',
      'sql'     => '('.$usageSQL.')',
      'sort'    => false,
      'size'    => 5,
      'select'  => 'N',
      'align'   => 'right',
    ];

    $opts['filters'] = "PMEtable0.Disabled <= ".intval($this->showDisabled);

    $opts['groupby_fields'] = [ 'Id' ];

    //$opts['triggers']['update']['before']  = 'CAFEVDB\Instruments::beforeUpdateTrigger';
    //$opts['triggers']['insert']['before']  = 'CAFEVDB\Instruments::beforeInsertTrigger';

    //$opts['triggers']['delete']['before']  = 'CAFEVDB\Instruments::beforeDeleteTrigger';

    $opts['triggers']['delete']['before'][] = [ $this, 'beforeDeleteTrigger' ];

    $opts['triggers']['select']['data'][] =
      function(&$pme, $op, $step, &$row) use ($opts, $usageIdx)  {
        if (!$this->getUserValue('expertmode') && !empty($row['qf'.$usageIdx])) {
          $pme->options = str_replace('D', '', $pme->options);
        }
        return true;
      };

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
  private function beforeDeleteTrigger(&$pme, $op, $step, $oldValues, &$changed, &$newValues)
  {
    // $count = self::usage($pme->rec, $pme->dbh);

    // if ($count > 0) {
    //   $result = mySQL::update('Instrumente', "Id = {$pme->rec}", [ 'Disabled' => 1 ], $pme->dbh);
    //   return false;
    // }

    // return true;
    return false;
  }
}
