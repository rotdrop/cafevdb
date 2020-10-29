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
class Instruments extends PMETableViewBase
{
  const CSS_CLASS = 'instruments';
  const TABLE = 'Instruments';

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
    $opts            = $this->pmeOptions;

    $opts['css']['postfix'] = 'direct-change show-hide-disabled';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $template = 'instruments';
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
    $opts['sort_field'] = [ 'Sortierung' ];

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

    $opts['fdd']['Instrument'] = [
      'name'     => $this->l->t('Instrument'),
      'select'   => 'T',
      'maxlen'   => 64,
      'sort'     => true,
    ];

    $opts['fdd']['Sortierung'] = [
      'name'     => $this->l->t('sort order'),
      'select'   => 'N',
      'maxlen'   => 9,
      'size'     => 6,
      'sort'     => true,
      'align'    => 'right',
      ];

    $instFamIdx = count($opts['fdd']);
    $opts['fdd']['FamilyJoin'] = [
      'name' => 'FamilyDummyJoin',
      'input'    => 'VRH',
      'sql'      => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instFamIdx.'.family_id
  ORDER BY PMEjoin'.$instFamIdx.'.family_id ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => array(
        'table'       => 'instrument_family',
        'column'      => 'family_id',
        'description' => [ 'columns' => 'family_id' ],
        'join'        => '$join_table.instrument_id = $main_table.Id',
      )
    ];

    $famIdx = count($opts['fdd']);
    $opts['fdd']['Familie'] = [
      'name'        => $this->l->t('Family'),
      'input'       => 'VR',
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$famIdx.'.Id ORDER BY PMEjoin'.$famIdx.'.Id ASC)',
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
        'table'       => 'InstrumentFamilies',
        'column'      => 'id',
        'description' => 'family',
        'orderby'     => 'family',
        'join'        => '$join_table.id = PMEjoin'.$instFamIdx.'.family_id'
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

    $lang = locale_get_primary_language($this->l->getLanguageCode());

    // Provide a link to Wikipedia for fun ...
    $opts['fdd']['Lexikon'] = [
      'name'    => 'Wikipedia',
      'select'  => 'T',
      'input'   => 'VR',
      'options' => 'LF',
      'sql'     => '`PMEtable0`.`Id`',
      'php'   =>  function($value, $op, $field, $fds, $fdd, $row, $recordId) use ($lang) {
        $inst = $this->l->t($row['qf1']);
        return '<a '
          .'href="http://'.$lang.'.wikipedia.org/wiki/'.$inst.'" '
          .'target="Wikipedia.'.$lang.'" '
          .'>'
          .$inst.'@Wikipedia.'.$lang.'</a>';
      },
      'escape' => false,
      'nowrap' => true,
    ];

    $opts['filters'] = "PMEtable0.Disabled <= ".intval($this->showDisabled);

    $opts['groupby_fields'] = [ 'Id' ];

    //$opts['triggers']['update']['before']  = 'CAFEVDB\Instruments::beforeUpdateTrigger';
    //$opts['triggers']['insert']['before']  = 'CAFEVDB\Instruments::beforeInsertTrigger';

    //$opts['triggers']['delete']['before']  = 'CAFEVDB\Instruments::beforeDeleteTrigger';

    // $opts['triggers']['select']['data'][] =
    //   function(&$pme, $op, $step, &$row) use ($opts, $usageIdx)  {
    //     $pme->options = $opts['options'];
    //     if (!$this->getUserValue('expertmode') && !empty($row['qf'.$usageIdx])) {
    //       $pme->options = str_replace('D', '', $pme->options);
    //     } else {
    //       $pme->options = $opts['options'];
    //     }
    //     return true;
    //   };

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


}
