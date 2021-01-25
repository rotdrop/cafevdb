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
use OCA\CAFEVDB\Database\Doctrine\ORM;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Instruments table. */
class InsuranceBrokers extends PMETableViewBase
{
  const CSS_CLASS = 'insurance-brokers';
  const TABLE = 'InsuranceBrokers';

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
  }

  public function cssClass() { return self::CSS_CLASS; }

  public function shortTitle()
  {
    return $this->l->t('Instrument Insurance Brokers');
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

    $template = 'insurance-brokers';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'short_name';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'string';

    // Sorting field(s)
    $opts['sort_field'] = [ 'long_name' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVD';
    $sort = false; // too few entries

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

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

    $opts['fdd']['short_name'] = [
      'name'     => $this->l->t('Short Name'),
      'css'      => [ 'postfix' => ' broker' ],
      'select'   => 'T',
      'maxlen'   => 40,
      'sort'     => $sort,
    ];

    $opts['fdd']['long_name'] = [
      'name'     => $this->l->t('Name'),
      'css'      => [ 'postfix' => ' brokername' ],
      'select'   => 'T',
      'maxlen'   => 255,
      'sort'     => $sort,
    ];

    $opts['fdd']['address'] = [
      'name'     => $this->l->t('Address'),
      'css'      => [ 'postfix' => ' brokeraddress' ],
      'select'   => 'T',
      'maxlen'   => 512,
      'textarea' => [
        'css' => 'wysiwygeditor',
        'rows' => 5,
        'cols' => 50,
      ],
      'sort'     => $sort,
    ];

    // if ($this->showDisabled) {
    //   $opts['fdd']['disabled'] = [
    //     'name'     => $this->l->t('Disabled'),
    //     'options' => $expertMode ? 'LAVCPDF' : 'LAVCPDF',
    //     'input'    => $expertMode ? '' : 'R',
    //     'select'   => 'C',
    //     'maxlen'   => 1,
    //     'sort'     => true,
    //     'escape'   => false,
    //     'sqlw'     => 'IF($val_qas = "", 0, 1)',
    //     'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' ],
    //     'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
    //     'tooltip'  => $this->toolTipsService['instrument-family-disabled'],
    //     'css'      => [ 'postfix' => ' instrument-family-disabled' ],
    //   ];
    // }

    // $opts['filters'] = "PMEtable0.Disabled <= ".intval($this->showDisabled);

    $opts['triggers']['delete']['before'][] = [ $this, 'beforeDeleteTrigger' ];

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
    // $entity = $this->getDatabaseRepository(ORM\Entities\InsuranceBroker::class)->find($pme->rec);

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
