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
class ProjectPayments extends PMETableViewBase
{
  const CSS_CLASS = 'project-payments';
  const TABLE = 'ProjectPayments';
  const DEBIT_NOTES = 'DebitNotes';
  const DEBIT_NOTE_DATA = 'DebitNoteData';

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
    return $this->l->t('Payments for project "%s"', [ $this->projectName ]);
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
    $opts            = $this->pmeOptions;

    if (empty($projectName) || empty($projectId)) {
      throw new \InvalidArgumentException('Project-id and/or -name must be given.');
    }

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $template = 'project-payments';
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
    $opts['sort_field'] = array('-DateOfReceipt', 'DebitNoteId', 'InstrumentationId');

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => false
    ]);

    $idIdx = 0;
    $opts['fdd']['id'] = [
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH',
      'options'  => 'AVCPD', // auto increment
      'maxlen'   => 11,
      'default'  => '0',
      'sort'     => true,
    ];

    $instrumentationIdx = count($opts['fdd']);
    $opts['fdd']['instrumentation_id'] = [
      'name'     => $this->l->t('Musician'),
      'css'      => [ 'postfix' => ' instrumentation-id' ],
      'values'   => [
        'table'  => "SELECT b.ProjektId AS ProjectId, b.Id, CONCAT(b.Id,': ',m.Vorname,' ',m.Name) AS Name
  FROM Besetzungen b
  LEFT JOIN Musicians m
  ON b.MusikerId = m.Id
  WHERE b.ProjektId = ".$projectId."
  ORDER BY m.Vorname ASC, m.Name ASC",
        'column' => 'Id',
        'description' => 'Name',
        'join'   => '$join_table.Id = $main_table.instrumentation_id'
      ],
      'select'   => 'T',
      'maxlen'   => 40,
      'sort'     => true,
    ];

    $opts['fdd']['amount'] = $this->defaultFDD['money'];
    $opts['fdd']['amount']['name'] = $this->l->t('Amount');

    $receiptIdx = count($opts['fdd']);
    $opts['fdd']['date_of_receipt'] = array_merge(
      $this->defaultFDD['date'],
      [
        'name' => $this->l->t('Date of Receipt'),
      ]
    );

    $subjectIdx = count($opts['fdd']);
    $opts['fdd']['subject'] = array(
      'name' => $this->l->t('Subject'),
      'css'  => [ 'postfix' => ' subject hide-subsequent-lines' ],
      'select' => 'T',
      'textarea' => [ 'css' => ' subject-text',
                      'rows' => 4,
                      'cols' => 32 ],
      'display|LF' => [ 'popup' => 'data' ],
      'escape' => true,
      'sort' => true
    );

    $debitIdIdx = count($opts['fdd']);
    $opts['fdd']['debit_note_id'] = array_merge(
      $this->defaultFDD['datetime'],
      [
        'name' => $this->l->t('Direct Debit Date'),
        'input' => 'R',
        'input|C' => 'H',
        'options' => 'LCFVD',
        'values' => [
          'table' => self::DEBIT_NOTES,
          'column' => 'Id',
          'description' => 'DateIssued',
        ],
        'sort' => true,
      ],
    );

    $opts['fdd']['mandate_reference'] = [
      'name' => $this->l->t('Mandate Reference'),
      'input'  => 'R',
      'options' => 'LFVD',
      'css'  => [ 'postfix' => ' mandate-reference' ],
      'input' => 'R',
      'select' => 'T',
      'escape' => true,
      'sort' => true
    ];

    $opts['fdd']['debit_message_id'] = [
      'name' => $this->l->t('Message-ID'),
      'input'  => 'R',
      'options' => 'LFVD',
      'css'  => [ 'postfix' => ' message-id hide-subsequent-lines' ],
      'input' => 'R',
      'select' => 'T',
      'escape' => true,
      'sort' => true,
      'tooltip' => $this->toolTipsService['debit-note-email-message-id'],
      'display|LF' => [ 'popup' => 'data' ],
    ];

    $opts['triggers']['select']['data'][] =
      function(&$pme, $op, $step, &$row) use ($debitIdIdx, $opts) {
        //error_log('called '.$op.' '.$debitIdIdx);
        //error_log('called '.print_r($row, true));
        if (!empty($row['qf'.$debitIdIdx])) {
          $pme->options = 'LVF';
          if ($op !== 'select' ) {
            throw new \BadFunctionCallException(
              $this->l->t('Payments resulting from direct debit transfers cannot be changed.')
            );
          }
        } else {
          $pme->options = $opts['options'];
        }
      };
    $opts['triggers']['update']['data'] = $opts['triggers']['select']['data'];

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];

    $opts['filters'] = 'PMEjoin'.$instrumentationIdx.'.ProjectId = '.$projectId;

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

}
