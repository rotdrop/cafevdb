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
use OCA\CAFEVDB\Common\Navigation;

/**Table generator for Instruments table. */
class ProjectInstrumentation extends PMETableViewBase
{
  const TEMPLATE = 'project-instrumentation';
  const CSS_CLASS = self::TEMPLATE;
  const TABLE = 'ProjectInstrumentation';

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

  public function cssClass() {
    return self::CSS_CLASS;
  }

  public function shortTitle()
  {
    if ($this->projectName) {
      return L::t("Instrumentation Numbers for `%s'", array($this->projectName));
    } else {
      return L::t("Instrumentation Numbers");
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    $header .= "<p>".L::t("The target instrumentation numbers can be filled into this table. ".
                          "The `have'-numbers are the numbers of the musicians ".
                          "already registered for the project.".
                          "In order to transfer the instruments of the already registerd musicions ".
                          "into this table click the `Adjust Instrument' option from the `Actions' menu.");

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template       = $this->template;
    $projectName    = $this->projectName;
    $projectId      = $this->projectId;
    $projectMode    = $projectId > 0;
    $instruments    = $this->instruments;
    $recordsPerPage = $this->recordsPerPage;

    $opts            = [];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    $template = 'project-payments';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'instrument_id' => 'int' ];

    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    if ($projectMode) {
      $opts['options'] = 'ACDF';
      $sort = false;
    } else {
      $opts['options'] = 'ACDF';
      $sort = true;
    }

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '16';

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => $sort,
        'time'  => true,
        'tabs'  => false,
        'navigation' => 'CD',
    ]);

    if ($projectMode) {
      $adjustButton = array(
        'name' => 'transfer_instruments',
        'value' => L::t('Transfer Instruments'),
        'css' => 'transfer-registered-instruments'
        );
      $opts['buttons'] = Navigation::prependTableButton($adjustButton, false, false);
    }

    // field definitions

    $opts['fdd']['project_id'] = array(
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      );

    $opts['fdd']['instrument_id'] = array(
      'name'     => $this->l->t('Instrument-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    );

    $joinTables = $this->defineJoinStructure($opts);

    // trigger

    // go

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

}
