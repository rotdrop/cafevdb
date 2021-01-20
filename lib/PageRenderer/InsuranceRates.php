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
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Instruments table. */
class InsuranceRates extends PMETableViewBase
{
  const CSS_CLASS = 'insurance-rates';
  const TABLE = 'InsuranceRates';

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
  }

  public function cssClass() { return self::CSS_CLASS; }

  public function shortTitle()
  {
    return $this->l->t('Instrument Insurance Rates');
  }

  public function headerText()
  {
    return $this->shortTitle();
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

    $template = 'insurance-rates';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'broker_id' => 'string', 'geographical_scope' => 'string', ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'broker_id', 'geographical_scope', ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVD';
    $sort = false; // too few entries

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'UG';

    // Display special page elements
    $opts['display'] = [
      'form'  => true,
      //'query' => true,
      //'sort'  => true,
      'time'  => true,
      'tabs'  => false,
      'navigation' => 'VCPD'
    ];

    // field definitions

    opts['fdd']['broker_id'] = [
        'name'     => $this->l->t('Broker'),
        'css'      => [ 'postfix' => ' broker', ],
        'select'   => 'D',
        'maxlen'   => 128,
        'sort'     => $sort,
        'values'   => $this->broker,
    ];

    $opts['fdd']['geographical_scope'] = [
      'name'        => $this->l->t('Scope'),
      'css'         => [ 'postfix' => ' scope' ],
      'select'      => 'D',
      'maxlen'      => 137,
      'sort'        => $sort,
      'values'      => $this->scope,
    ];

    $opts['fdd']['rate'] = [
      'name'     => $this->l->t('Rate'),
      'css'      => [ 'postfix' => ' rate' ],
      'select'   => 'N',
      'maxlen'   => 11,
      'default'  => 0.0,
      'sort'     => $sort,
    ];

    $opts['fdd']['due_date'] = $this->defaultFDD['date'];
    $opts['fdd']['due_date']['name'] = $this->l->t('Due Date');
    $opts['fdd']['due_date']['sort'] = $sort;

    $opts['fdd']['policy_number'] = [
      'name' => $this->l->t('Policy Number'),
      'css' => [ 'postfix' => ' policy' ],
      'select' => 'T',
      'maxlen' => 127,
      'sort' => $sort
    ];

    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';

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

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];

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
