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

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ProjectExtraFieldsService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/** TBD. */
class SepaDebitNotes extends PMETableViewBase
{
  const TABLE = 'SepaDebitNotes';
  const DATA_TABLE = 'SepaDebitNoteData';
  const PROJECTS_TABLE = 'Projects';

  protected $cssClass = 'sepa-debit-notes';

  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'master' => true,
      'entity' => Entities\SepaDebitNote::class,
    ],
    [
      'table' => self::DATA_TABLE,
      'entity' => Entities\SepaDebitNoteData::class,
      'identifier' => [ 'debit_note_id' => 'id' ],
      'column' => 'debit_note_id',
    ],
    [
      'table' => self::PROJECTS_TABLE,
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
  ];

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project */
  private $project = null;

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

  public function shortTitle()
  {
    return $this->l->t('Debit-notes for project "%s"', array($this->projectName));
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
    $memberProjectId = $this->getConfigValue('memberProjectId', -1);

    $projectMode = $projectId > 0 && !empty($projectName);

    $opts            = [];

    $opts['css']['postfix'] = 'direct-change show-hide-disabled';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $template = 'sepa-debit-notes';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'id' => 'int', ];

    // Sorting field(s)
    $opts['sort_field'] = [ '-submission_dead_line', '-date_issued', ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CDFLV';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'UG';

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
    ];

    ///////////////////////////////////////////////////////////////////////////
    //
    // Add the id-columns of the main-table
    //

    $opts['fdd']['id'] = [
      'name'     => $this->l->t('Id'),
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH',
      'options'  => 'AVCPD',
      'maxlen'   => 11,
      'default'  => '0', // auto increment
      'sort'     => true
    ];

    $opts['fdd']['project_id'] = [
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'H',
      'select'   => 'N',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => $projectMode ? $projectId : -1,
      'sort'     => true,
      ];

    $joinTables = $this->defineJoinStructure($opts);

    $projectIdIdx = count($opts['fdd']);
    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECTS_TABLE, 'id',
      Util::arrayMergeRecursive(
        [
          'name'     => $this->l->t('Project'),
          'input'    => $projectMode ? 'H' : null,
          'select'   => 'D',
          'sort'     => true,
          'values' => [
            'description' => [
              'columns' => [ 'year' => 'year', 'name' => 'name' ],
              'divs' => [ 'year' => ': ' ]
            ],
            'groups' => 'year',
            'orderby' => '$table.year DESC, $table.name ASC',
          ],
        ]));

    $opts['fdd']['date_issued'] = array_merge(
      $this->defaultFDD['datetime'],
      [
        'name' => $this->l->t('Time Created'),
        'input' => 'R',
        'tooltip' => $this->toolTipsService['debit-note-creation-time'],
      ]);

    $opts['fdd']['submission_deadline'] = array_merge(
      $this->defaultFDD['date'],
      [
        'name' => $this->l->t('Submission Deadline'),
        'input' => 'R',
        'tooltip' => $this->toolTipsService['debit-note-submission-deadline'],
      ]);

    $submitIdx = count($opts['fdd']);
    $opts['fdd']['submit_date'] = array_merge(
      $this->defaultFDD['date'],
      [
        'name' => $this->l->t('Date of Submission'),
        'tooltip' => $this->toolTipsService['debit-note-date-of-submission'],
      ]);

    $opts['fdd']['due_date'] = array_merge(
      $this->defaultFDD['date'],
      [
        'name' => $this->l->t('Due Date'),
        'input' => 'R',
        'tooltip' => $this->toolTipsService['debit-note-due-date'],
      ]);

    $jobIdx = count($opts['fdd']);
    $opts['fdd']['job'] = [
      'name' => $this->l->t('Kind'),
      'css'  => [ 'postfix' => ' debit-note-job' ],
      'input' => 'R',
      'select' => 'D',
      'sort' => true,
      'values2' => [
        'deposit' => $this->l->t('deposit'),
        'remaining' => $this->l->t('remaining'),
        'amount' => $this->l->t('amount'),
        'insurance' => $this->l->t('insurance'),
        'membership-fee' => $this->l->t('membership-fee'),
      ],
    ];

    $opts['fdd']['actions'] = [
      'name'  => $this->l->t('Actions'),
      'css'   => [ 'postfix' => ' debit-note-actions' ],
      'input' => 'VR',
      'sql' => 'PMEtable0.id',
      'sort'  => false,
      'php' => function($value, $op, $field, $fds, $fdd, $row, $recordId)
        use ($projectIdIdx, $jobIdx) {
          $post = [
            'debitNoteId' => $recordId,
            'requesttoken' => \OCP\Util::callRegister(),
            'projectId' => $row['qf'.$projectIdIdx.'_idx'],
            'projectName' => $row['qf'.$projectIdIdx]
          ];
          $postEmail = [
            'emailTemplate' => self::emailTemplate($row['qf'.$jobIdx])
          ];
          $actions = [
            'download' => [
              'label' =>  $this->l->t('download'),
              'post' => json_encode($post),
              'title' => $this->toolTipsService['debit-notes-download'],
            ],
            'announce' => [
              'label' => $this->l->t('announce'),
              'post'  => json_encode(array_merge($post, $postEmail)),
              'title' => $this->toolTipsService['debit-notes-announce'],
            ],
          ];
          $html = '';
          foreach($actions as $key => $action) {
            $html .=<<<__EOT__
<li class="nav tooltip-left inline-block ">
  <a class="nav {$key}"
     href="#"
     data-post='{$action['post']}'
     title="{$action['title']}">
{$action['label']}
  </a>
</li>
__EOT__;
          }
          return $html;
        },
    ];

    //
    ///////////////////////////////////////////////////////////////////////////

    if ($projectMode) {
      $opts['filters'] = '`PMEtable0`.`project_id` = '.$projectId;
    }

    // redirect all updates through Doctrine\ORM.
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['insert']['before'][]  = [ $this, 'beforeInsertDoInsertAll' ];

    $opts['triggers']['select']['data'][] = function(&$pme, $op, $step, &$row) use ($submitIdx, $opts)  {
      if (empty($row['qf'.$submitIdx])) {
        $pme->options = $opts['options'];
      } else {
        $pme->options = 'LFV';
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
}
