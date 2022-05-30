<?php // Hey, Emacs, we are -*- php -*- mode!
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Projects table. */
class Projects extends PMETableViewBase
{
  const TEMPLATE = 'projects';
  const TABLE = self::PROJECTS_TABLE;
  const ENTITY = Entities\Project::class;
  const NAME_LENGTH_MAX = 20;

  const NUM_VOICES_MIN = 2;
  const NUM_VOICES_EXTRA = 1;

  private const MAX_POSTER_COLUMNS = 4;

  /** @var ProjectService */
  protected $projectService;

  /** @var EventsService */
  private $eventsService;

  /** @var ImagesService */
  private $imagesService;

  /** @var MailingListsService */
  private $listsService;

  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => self::ENTITY,
    ],
    self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE => [
      'entity' => Entities\ProjectInstrumentationNumber::class,
      'identifier' => [
        'project_id' => 'id',
        'instrument_id' => false,
        'voice' => [ 'self' => true, ],
      ],
      'column' => 'instrument_id',
    ],
    self::INSTRUMENTS_TABLE => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => 'id',
        'id' => false,
      ],
      'column' => 'id',
    ],
  ];

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , ProjectService $projectService
    , EventsService $eventsService
    , ImagesService $imagesService
    , MailingListsService $listsService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct(self::TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->projectService = $projectService;
    $this->eventsService = $eventsService;
    $this->imagesService = $imagesService;
    $this->listsService = $listsService;

    if (empty($this->projectId)) {
      $this->projectId = $this->pmeRecordId['id']??null;
      if (!empty($this->projectId)) {
        $this->projectName = $this->projectService->fetchName($this->projectId);
      }
    }

    if ($this->listOperation()) {
      $this->pme->overrideLabel('Add', $this->l->t('New Project'));
    }

    if (empty($this->requestParameters['template'])) {
      // "booted" as default Page
      $this->requestParameters[$this->pme->cgiSysName('qfyear_comp')] = '>=';
      $this->requestParameters[$this->pme->cgiSysName('qfyear')] = date('Y') - 1;
    }
  }

  public function needPhpSession():bool
  {
    return !$this->listOperation();
  }

  /** Short title for heading. */
  public function shortTitle() {
    if (!empty($this->projectName)) {
      return $this->l->t("%s Project %s",
                         [ ucfirst($this->getConfigValue('orchestra')),
                           $this->projectName]);
    } else {
      return $this->l->t("%s Projects", [ ucfirst($this->getConfigValue('orchestra')) ]);
    }
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;

    $opts            = [];

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = [
      'show-hide-disabled',
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Install values for after form-submit, e.g. $this->template ATM
    // is just the request parameter, while Template below will define
    // the value of $this->template after form submit.
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
      // overwrite with record id to catch changes after copy/insert
      'projectId' => $this->projectId,
      'projectName' => $this->projectName,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = ['-year', 'name'];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDF';

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
      'tabs'  => false
    ];

    $idIdx = 0;
    $opts['fdd']['id'] = [
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|LFAP' => 'RH', // always auto-increment
      'options'  => 'LFAVCPD',
      'maxlen'   => 11,
      'default'  => null, // auto increment
      'sort'     => true,
    ];

    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      $joinInfo['table'] = $table;
      switch ($table) {
      case self::PROJECT_PARTICIPANT_FIELDS_TABLE:
        $tweakedJoinInfo = $joinInfo;
        unset($tweakedJoinInfo['identifier']['project_id']);
        $joinInfo['sql'] = $this->makeFieldTranslationsJoin($tweakedJoinInfo, 'name');
        break;
      default:
        break;
      }
    });

    $joinTables = $this->defineJoinStructure($opts);

    $currentYear = date('Y');
    $yearRange = $this->getDatabaseRepository(self::ENTITY)->findYearRange();
    $yearValues = [' '];
    for ($year = $yearRange["min"] - 1; $year < $currentYear + 5; $year++) {
      $yearValues[] = $year;
    }

    $yearIdx = count($opts['fdd']);
    $opts['fdd']['year'] = [
      'name'     => $this->l->t('year'),
      'select'   => 'N',
      //'options'  => 'LAVCPDF'
      'maxlen'   => 5,
      'default'  => $currentYear,
      'sort'     => true,
      'values'   => $yearValues,
      'align'    => 'center',
    ];

    $nameIdx = count($opts['fdd']);
    $opts['fdd']['name'] = [
      'name'     => $this->l->t('Project-Name'),
      'php|LF'  => function($value, $op, $field, $row, $recordId, $pme) {
        //error_log('project-id: '.$recordId);
        $projectId = $recordId['id'];
        $projectName = $value;
        $placeHolder = false;
        $overview = true;
        return $this->projectActions($projectId, $projectName, $placeHolder, $overview);
      },
      'select'   => 'T',
      'select|LF' => 'D',
      'maxlen'   => self::NAME_LENGTH_MAX + 6,
      'css'      => ['postfix' => ' projectname control'],
      'sort'     => true,
      'values|LF'   => [
        'table' => self::TABLE,
        'column' => 'name',
        'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
        'groups' => 'year',
        'orderby' => '$table.`year` DESC',
      ],
    ];

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
        ]
      );
    }

    $opts['fdd']['type'] = [
      'name'     => $this->l->t('Kind'),
      'select'   => 'D',
      'options'  => 'LFAVCPD', // auto increment
      'maxlen'   => 11,
      'css'      => ['postfix' => [ 'tooltip-right', ], ],
      'values2'  => $this->projectTypeNames,
      'default'  => 'temporary',
      'sort'     => true,
      'align'    => 'center',
    ];
    $this->addSlug('type', $opts['fdd']['type']);

    $opts['fdd']['actions'] = [
      'name'     => $this->l->t('Actions'),
      'input'    => 'RV',
      'sql'      => '$main_table.name',
      'php|VCLDF'    => function($value, $op, $field, $row, $recordId, $pme) {
        $projectId = $recordId['id'];
        $projectName = $value;
        $overview = false;
        $placeHolder = $this->l->t("Actions");
        return $this->projectActions($projectId, $projectName, $placeHolder, $overview);
      },
      'select'   => 'T',
      'options'  => 'VD',
      'maxlen'   => 11,
      'default'  => '0',
      'css'      => ['postfix' => ' control'],
      'sort'     => false
    ];

    $opts['fdd']['tools'] = [
      'name'     => $this->l->t('Toolbox'),
      'input'    => 'V',
      'options'  => 'VCD',
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' projecttoolbox'],
      'sql'      => '$main_table.name',
      'php|CV'   =>  function($value, $op, $field, $row, $recordId, $pme) {
        $projectName = $value;
        $projectId = $recordId['id'];
        return $this->projectToolbox($projectId, $projectName);
      },
      'sort'     => true,
      'escape'   => false
    ];

    $l10nInstrumentsTable = $this->makeFieldTranslationsJoin([
      'table' => self::INSTRUMENTS_TABLE,
      'entity' => Entities\Instrument::class,
      'identifier' => [ 'id' => true ], // just need the key
      'column' => 'id',
    ], 'name');

    list($index, $name) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'instrument_id',
      [
        'name'        => $this->l->t('Instrumentation'),
        'decoration'  => [ 'slug' => 'instrumentation' ],
        'options'     => 'CAP',
        'display|LF'  => [
          'popup' => 'data',
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|VDC'  => [
          'popup' => false,
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|P' => [
          'popup' => false,
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '<input type="button"
       id="projects--clear-participant-fields"
       title="'.$this->toolTipsService['pme:clear-input-field'].'"
       class="clear-field"
       value="'.$this->l->t('Clear').'"
/>
</div>',
        ],
        'tooltip|ACP' => $this->toolTipsService[$this->tooltipSlug('instrumentation').'-ACP'],
        'css'         => ['postfix' => [ 'tooltip-top', ], ],
        'sql'         => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
        'select'      => 'M',
        'values|ACP' => [
          'table'       => $l10nInstrumentsTable,
          'column'      => 'id',
          'description' => [
            'columns' => [ 'l10n_name' ],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].'.instrument_id',
        ],
        'values|LFVD' => [
          'table'       => $l10nInstrumentsTable,
          'column'      => 'id',
          'description' => [
            'columns' => [ '$table.l10n_name' ],
            'ifnull' => [ false ],
            'cast' => [ false ],
          ],
          'orderby'     => '$table.sort_order ASC',
          'join'        => '$join_col_fqn = '.$this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].'.instrument_id',
          'filters'     => '$table.id IN (SELECT DISTINCT instrument_id
  FROM '.self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE.' pi
  WHERE '.$this->projectId.' <= 0 OR '.$this->projectId.' = pi.project_id)',
        ],
        'valueGroups' => $this->instrumentInfo['idGroups'],
        'php|VD' => function($value, $op, $field, $row, $recordId, $pme) {
          $post = [
            'projectInstruments' => $value,
            'template' => 'project-instrumentation-numbers',
            'projectName' => $row[$this->queryField('name', $pme->fdd)],
            'project_id' => $recordId['id'],
            'projectId' => $recordId['id'],
          ];
          $json = json_encode($post);
          $post = http_build_query($post, '', '&');
          $title = $this->toolTipsService['project-action:project-instrumentation-numbers'];
          $link =<<<__EOT__
                <li class="nav tooltip-top" title="$title">
                <a class="nav" href="#" data-post="$post" data-json='$json'>
                $value
                </a>
                </li>
__EOT__;
          return $link;
        },
        'filter' => [
          'having' => true,
        ],
      ]);

    // Blow up the value-groups for the voices ... actually does not
    // matter too much, this is just a lookup table.
    $voicesValueGroups = [];
    foreach ($this->instrumentInfo['idGroups'] as $instrumentId => $groupName) {
      for ($i = 0; $i < 32; ++$i) {
        $voicesValueGroups[$instrumentId . self::JOIN_KEY_SEP . $i] = $groupName;
      }
    }

    list($index, $name) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'voice',
      [
        'name|CAP'  => $this->l->t('Voices'),
        'name|LFVD' => $this->l->t('Instrumentation'),
        'decoration' => [ 'slug' => 'instrumentation-voices' ],
        // 'default|A' => '',
        'values|A' => null,
        'values2|A' => [],
        'input|A' => 'R',
        'default'  => 0, // keep in sync with ProjectInstrumentationNumbers
        'select'   => 'M',
        'css'      => [ 'postfix' => [ 'allow-empty', 'no-search', ], ],
        'display|LF' => [
          'popup' => 'data',
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|VD' => [
          'popup' => false,
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|CAP' => [ // add, change, paste (copy)
          'popup' => false,
          'prefix' => function($op, $when, $k, $row, $pme) {
            return '<div class="cell-wrapper">
  <div class="dropdown-menu">
';
          },
          'postfix' => function($op, $when, $k, $row, $pme) {
            $html = '';
            if ($op == 'copy') {
              $html .= '
    <input type="button"
           id="projects--clear-participant-fields"
           title="'.$this->toolTipsService['pme:clear-input-field'].'"
           class="clear-field"
           value="'.$this->l->t('Clear').'"
    />
';
            }
            $html .= '</div>
'; // close dropdown-menu

            if ($op == 'add') {
              $instruments = array_keys($this->instruments);
              $instrumentNames = $this->instruments;
            } else {
              $instrumentsIndex = $k - 1;
              $instruments = explode(',', $row['qf'.$instrumentsIndex]);
              $instrumentNames = $pme->set_values($instrumentsIndex)['values'];
            }

            $templateParameters = [
              'instruments' => $instruments,
              'dataName' => $pme->cgiDataName($this->joinTableFieldName(self::PROJECT_INSTRUMENTS_TABLE, 'voice').'[]'),
              'inputLabel' => function($instrument) use ($instrumentNames) {
                return $this->l->t('%1$s, #voices', $instrumentNames[$instrument]);
              },
              'toolTips' => $this->toolTipsService,
              'toolTipSlug' => $this->toolTipSlug('instrument-voice-request'),
            ];

            $template = new TemplateResponse($this->appName(), 'fragments/instrument-voices', $templateParameters, 'blank');
            $html .= $template->render();

            return $html;
          },
        ],
        'tooltip|ACP' => $this->toolTipsService[$this->tooltipSlug('instrumentation-voices').'-ACP'],
        'sql' => "GROUP_CONCAT(
  DISTINCT
  IF(".$this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].".voice >= 0,
    CONCAT_WS(
      '".self::JOIN_KEY_SEP."',
      ".$this->joinTables[self::INSTRUMENTS_TABLE].".id,
      ".$this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].".voice),
    NULL
  )
  ORDER BY
    " . $this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC,
    " . $this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].".voice ASC)",
        // Values for copy and change, including excess voices to select
        'values|CP' => [
          'table' => "SELECT
  CONCAT(
    pin.instrument_id,
   '".self::JOIN_KEY_SEP."',
   IF(n.seq <= GREATEST(".self::NUM_VOICES_MIN.", ".self::NUM_VOICES_EXTRA." + MAX(pin.voice)), n.seq, '?')
  ) AS value,
  pin.project_id,
  i.id AS instrument_id,
  i.name,
  COALESCE(ft.content, i.name) AS l10n_name,
  i.sort_order,
  pin.quantity,
  GREATEST(".self::NUM_VOICES_MIN.", ".self::NUM_VOICES_EXTRA." + MAX(pin.voice)) AS voices_limit,
  n.seq
  FROM ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pin.instrument_id
  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." ft
    ON ft.locale = '".($this->l10n()->getLanguageCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
  JOIN ".self::SEQUENCE_TABLE." n
    ON n.seq <= 1+GREATEST(".self::NUM_VOICES_MIN.", (pin.voice + ".self::NUM_VOICES_EXTRA.")) AND n.seq > 0 AND n.seq <= 1+GREATEST(".self::NUM_VOICES_MIN.", ".self::NUM_VOICES_EXTRA." + (SELECT MAX(pin2.voice) FROM ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin2))
  WHERE
    pin.project_id = \$record_id[id]
  GROUP BY
    project_id, instrument_id, n.seq
  HAVING
    MAX(pin.voice) = 0 OR n.seq > 0
  ORDER BY
    i.sort_order ASC, n.seq ASC",
          'column' => 'value',
          'description' => [
            'columns' => [ 'l10n_name', 'IF($table.seq > 0, CONCAT(" ", IF($table.seq <= $table.voices_limit, $table.seq, \'?\')), "")' ],
            'divs' => '',
          ],
          'orderby' => '$table.sort_order ASC, $table.seq ASC',
          'join' => false,
        ],
        // Values for for view mode, without excess voices.
        'values|LFVD' => [
          'table' => "SELECT
  CONCAT(pin.instrument_id,'".self::JOIN_KEY_SEP."', n.seq) AS value,
  pin.project_id,
  i.id AS instrument_id,
  i.name,
  COALESCE(ft.content, i.name) AS l10n_name,
  i.sort_order,
  pin.quantity,
  n.seq
  FROM ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin
  LEFT JOIN ".self::INSTRUMENTS_TABLE." i
    ON i.id = pin.instrument_id
  LEFT JOIN ".self::FIELD_TRANSLATIONS_TABLE." ft
    ON ft.locale = '".($this->l10n()->getLanguageCode())."'
      AND ft.object_class = '".addslashes(Entities\Instrument::class)."'
      AND ft.field = 'name'
      AND ft.foreign_key = i.id
  JOIN ".self::SEQUENCE_TABLE." n
    ON n.seq <= pin.voice AND n.seq >= 0 AND n.seq <= (SELECT MAX(pin2.voice) FROM ".self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE." pin2)
  WHERE
    pin.project_id = " . ($this->projectId?:0) . " OR " . ($this->projectId?:0) . " <= 0
  GROUP BY
    pin.project_id, pin.instrument_id, n.seq
  HAVING
    MAX(pin.voice) = 0 OR n.seq > 0
  ORDER BY
    pin.project_id ASC, i.sort_order ASC, n.seq ASC",
          'column' => 'value',
          'description' => [
            'columns' => [ 'l10n_name', 'IF($table.seq > 0, CONCAT(" ", $table.seq), "")' ],
            'divs' => ''
          ],
          'orderby' => '$table.sort_order ASC, $table.seq ASC',
          'join' => '$join_table.project_id = $main_table.id',
        ],
        'align|LF' => 'center',
        'valueGroups' => $voicesValueGroups,
        'php|VD' => function($value, $op, $field, $row, $recordId, $pme) {
          $post = [
            'projectInstruments' => $value,
            'template' => 'project-instrumentation-numbers',
            'projectName' => $row[$this->queryField('name', $pme->fdd)],
            'project_id' => $recordId['id'],
            'projectId' => $recordId['id'],
          ];
          $json = htmlspecialchars(json_encode($post));
          $post = http_build_query($post, '', '&');
          $title = Util::htmlEscape($value);
          $link =<<<__EOT__
                <li class="nav tooltip-top" title="$title">
                <a class="nav" href="#" data-post="$post" data-json='$json'>
                $value
                </a>
                </li>
__EOT__;
          return $link;
        },
        'filter' => [
          'having' => true,
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'quantity',
      [
        'name' => $this->l->t('Quantity'),
        'input' => 'RH',

        'sql' => "GROUP_CONCAT(
  DISTINCT
  CONCAT_WS(
    '".self::JOIN_KEY_SEP."',
    CONCAT_WS(
      '".self::COMP_KEY_SEP."',
      ".$this->joinTables[self::INSTRUMENTS_TABLE].".id,
      ".$this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].".voice),
    \$join_col_fqn)
  ORDER BY
    " . $this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC,
    " . $this->joinTables[self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE].".voice ASC)",
        'default' => 0,
      ]);

    $opts['fdd']['mailing_list_id'] = [
      'name'    => $this->l->t('Mailing List'),
      'css'     => [ 'postfix' => [ 'mailing-list', 'tooltip-auto', ], ],
      'tooltip|AP' => $this->toolTipsService['projects:mailing-list:create'],
      'select|AP' => 'C',
      'values2|AP' => [ 1 => $this->l->t('create') ],
      'select'  => 'T',
      'sort'    => true,
      'align'   => 'right',
      'default' => 1,
      'display|LFD'  => [
        'popup' => 'data',
        'prefix' => '<div class="cell-wrapper">',
        'postfix' => '</div>'
      ],
      'php|LFD' => function($value, $op, $field, $row, $recordId, $pme) {
        $html = $value;
        if (!empty($value)) {
          $listAddress = preg_replace('/\./', '@', $value, 1);
          $configUrl = $this->listsService->getConfigurationUrl($value);
          $html = '<a href="' . $configUrl . '" target="' . md5($listAddress) . '">' . $listAddress . '</a>';
        }
        return $html;
      },
      'display|CV' => [ 'popup' => false ],
      'php|CV' => function($value, $op, $field, $row, $recordId, $pme) {
        $projectId = $recordId['id'];
        $listAddress = strtolower($row[$this->queryField('name', $pme->fdd)]);
        $listAddress = $listAddress . '@' . $this->getConfigValue('mailingListEmailDomain');
        $l10nStatus = $this->l->t($status = 'unset');
        $configUrl = '';
        $archiveUrl = '';
        if (!empty($value)) {
          try {
            // fetch the basic list-info from the lists-server
            $listInfo = $this->listsService->getListInfo($value);
            $listAddress = $listInfo[MailingListsService::LIST_CONFIG_FQDN_LISTNAME];
            if (empty($this->listsService->getListConfig($value, 'emergency'))) {
              $l10nStatus = $this->l->t($status = 'active');
            } else {
              $l10nStatus = $this->l->t($status = 'closed');
            }
            $configUrl = Util::htmlEscape($this->listsService->getConfigurationUrl($listAddress));
            $archiveUrl = Util::htmlEscape($this->listsService->getArchiveUrl($listAddress));
          } catch (\Throwable $t) {
            $this->logException($t, 'Unable to communicate with mailing list server.');
            $l10nStatus = $this->l->t($status = 'unknown');
            $listAddress = preg_replace('/\./', '@', $value, 1);
            $configUrl = Util::htmlEscape($this->listsService->getConfigurationUrl($value));
          }
          $configAnchor = '<a href="' . $configUrl . '" target="' . md5($listAddress) . '">' . $listAddress . '</a>';
        } else {
          $configAnchor = $listAddress;
        }
        return '<div class="cell-wrapper flex-container flex-center">
  <span class="list-id display status-' . $status . '" data-status="' . $status . '">
    <span class="list-label">' . $configAnchor . '</span>
    <span class="list-status">' . $l10nStatus . '</span>
  </span>
  <span class="list-id actions status-' . $status . ' dropdown-container dropdown-no-hover" data-status="' . $status . '">
    <button class="menu-title action-menu-toggle">...</button>
    <nav class="mailing-list-dropdown dropdown-content dropdown-align-right">
      <ul>
        <li class="list-action list-action-create tooltip-auto"
            data-operation="create"
            title="' . $this->toolTipsService['projects:mailing-list:create'] . '"
        >
          <a href="#">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/add.svg') . '">
            ' . $this->l->t('create') . '
          </a>
        </li>
        <li class="list-action list-action-manage tooltip-auto"
            title="' . $this->toolTipsService['projects:mailing-list:manage'] . '"
          >
          <a href="' . $configUrl . '" target="' . md5($listAddress) . '">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/settings-dark.svg') . '">
            ' . $this->l->t('manage') . '
          </a>
        </li>
        <li class="list-action list-action-subscribe tooltip-auto"
            data-operation="subscribe"
            title="' . $this->toolTipsService['projects:mailing-list:subscribe'] . '"
        >
          <a href="#">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/confirm.svg') . '">
            ' . $this->l->t('subscribe') . '
          </a>
        </li>
        <li class="list-action list-action-close tooltip-auto"
            data-operation="close"
            title="' . $this->toolTipsService['projects:mailing-list:close'] . '"
        >
          <a href="#">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/pause.svg') . '">
            ' . $this->l->t('close') . '
          </a>
        </li>
        <li class="list-action list-action-reopen tooltip-auto"
            data-operation="reopen"
            title="' . $this->toolTipsService['projects:mailing-list:reopen'] . '"
        >
          <a href="#">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/play.svg') . '">
            ' . $this->l->t('reopen') . '
          </a>
        </li>
        <li class="list-action list-action-delete expert-mode-only tooltip-auto"
            data-operation="delete"
            title="' . $this->toolTipsService['projects:mailing-list:delete'] . '"
        >
          <a href="#">
            <img alt="" src="' . $this->urlGenerator()->imagePath('core', 'actions/delete.svg') . '">
            ' . $this->l->t('delete') . '
          </a>
        </li>
      </ul>
    </nav>
  </span>
</div>
';
      }
    ];
    $this->addSlug('mailing-list', $opts['fdd']['mailing_list_id']);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_PARTICIPANT_FIELDS_TABLE, 'id',
      [
        'name'         => $this->l->t('Participant Fields'),
        'decoration'   => [ 'slug' => 'participant-fields' ],
        'tooltip|ACP'  => $this->toolTipsService[$this->tooltipSlug('participant-fields').'-ACP'],
        'filter' => [
          'having' => true,
        ],
        'select'       => 'M',
        'input|P'      => '',
        'values2|P'    => [],
        'display|LF'  => [
          'popup' => 'data',
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|VDC'  => [
          'popup' => false,
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '</div>'
        ],
        'display|P'  => [
          'popup' => false,
          'prefix' => '<div class="cell-wrapper">',
          'postfix' => '<input type="button"
       id="projects--clear-participant-fields"
       title="'.$this->toolTipsService['pme:clear-input-field'].'"
       class="clear-field"
       value="'.$this->l->t('Clear').'"
/>
</div>',
        ],
        'css'      => [ 'postfix' => [ 'allow-empty', 'no-search', 'tooltip-top', ], ],
        'options'  => 'LFCPVD',
        'input'    => 'R',
        'sql'      => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_table.display_order DESC, $join_table.l10n_name ASC)',
        'values' => [
          'description' => [
            'columns' => '$table.l10n_name',
          ],
        ],
        'php|VDC'  => function($value, $op, $field, $row, $recordId, $pme) {
          $projectId = $recordId['id'];
          $post = [
            'ProjectParticipantFields' => $value,
            'template' => 'project-participant-fields',
            'projectName' => $row[$this->queryField('name', $pme->fdd)],
            'project_id' => $projectId,
            'projectId' => $projectId,
          ];
          $value = preg_replace('/,(\S)/', ', $1', $value);
          $json = json_encode($post);
          $post = http_build_query($post, '', '&');
          $tooltip = Util::htmlEscape($value);
          $link =<<<__EOT__
<li class="nav tooltip-top" title="$tooltip">
  <a class="nav" href="#" data-post="$post" data-json='$json'>
$value
  </a>
</li>
__EOT__;
          return $link;
        },
        'maxlen'   => 30,
        'escape'   => false,
      ]);

    /**
     * @var array $opts['fdd']['copy_participants'] An articifical
     * flag values which indicates whether also the participants
     * should be copied.
     */
    $opts['fdd']['copy_participants'] = [
      'name'       => $this->l->t('Participants'),
      'css'        => ['postfix' => [ 'tooltip-top', ], ],
      'options'    => 'P',
      'input'      => '',
      'select'     => 'O',
      'sql'        => 'CONCAT_WS("' . self::JOIN_KEY_SEP . '", $main_table.id, 0)',
      'values2'    => [
        $this->projectId.self::JOIN_KEY_SEP.'1' => $this->l->t('Copy participants'),
        $this->projectId.self::JOIN_KEY_SEP.'0' => $this->l->t('Do not copy participants'),
      ],
      'default' => 0,
    ];
    $this->addSlug('copy-participants', $opts['fdd']['copy_participants']);

    $programDisplay = 'VC';
    $opts['fdd']['program'] = [
      'name'     => $this->l->t('Program'),
      'input'    => 'V',
      'options'  => $programDisplay,
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => [ 'projectprogram', ], ],
      'sql'      => '$main_table.id',
      'php|' . $programDisplay => function($value, $action, $field, $row, $recordId, $pme) {
        $projectId = $recordId['id']; // and also $value
        return $this->projectProgram($projectId, $action);
      },
      'sort'     => true,
      'escape' => false
    ];

    $posterDisplay = 'VC';
    $opts['fdd']['poster'] = [
      'name'     => $this->l->t('Posters'),
      'input'    => 'V',
      'options'  => $posterDisplay,
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => [ 'projectposters', ], ],
      'sql'      => '$main_table.id',
      'php|' . $posterDisplay => function($value, $action, $field, $row, $recordId, $pme) {
        $projectId = $recordId['id'];
        $postersFolder = $this->projectService->ensurePostersFolder($projectId);
        $imageIds = $this->imagesService->getImageIds(ImagesService::USER_STORAGE, $postersFolder);
        if (empty($imageIds) || ($action != 'display')) {
          $imageIds[] = ImagesService::IMAGE_ID_PLACEHOLDER;
        }
        $numImages = count($imageIds);
        $rows = ($numImages + self::MAX_POSTER_COLUMNS - 1) / self::MAX_POSTER_COLUMNS;
        $columns = min(($numImages + $rows - 1)/ $rows, self::MAX_POSTER_COLUMNS);
        $columns = self::MAX_POSTER_COLUMNS;
        $html = '';
        for ($i = 0; $i < $numImages; ++$i) {
          $html .= $this->posterImageLink($postersFolder, $action, $columns, $imageIds[$i]);
        }
        return $html;
      },
      'css' => ['postfix' => [ 'projectposter', ], ],
      'default' => '',
      'sort'     => false,
      'escape' => false
    ];

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          'name' => $this->l->t('Last Updated'),
          'nowrap' => true,
          'options' => 'LFAVCPDR' // Set by update trigger.
        ]
      );

    $opts['filters'] = [ 'OR' => [], 'AND' => [] ];
    if (!empty($this->requestParameters[$this->pme->cgiSysName('qf'.$nameIdx.'_idx')])) {
      // unset the year filter, as it does not make sense
      unset($this->parameterService[$this->pme->cgiSysName('qf'.$yearIdx)]);
    } else {
      $opts['filters']['OR'][] = "\$table.type = 'permanent'";
    }
    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // We could try to use 'before' triggers in order to verify the
    // data. However, at the moment the stuff does not work without JS
    // anyway, and we use Ajax calls to verify the form data.

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_AFTER][]   = [ $this, 'afterUpdateTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertTrigger' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeInsertDoInsertAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_AFTER][]   = [ $this, 'afterInsertTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this , 'deleteTrigger' ];


    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_INSERT][PHPMyEdit::TRIGGER_DATA][] = function($pme, $op, $step, &$row) {
      if ($this->copyOperation()) {
        // tweak the name
        $row['qf'.$pme->fdn['name']] = $this->l->t('Copy of %s', $row['qf'.$pme->fdn['name']]);
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

  public function posterImageLink($postersFolder, $action, $imageColumns, $imageId)
  {
    if ($imageColumns <= 1) {
      $sizeCss = 'full';
    } else if ($imageColumns <= 2) {
      $sizeCss = 'half';
    } else {
      $sizeCss = 'quarter';
    }
    switch ($action) {
      case 'add':
        return $this->l->t("Posters can only be added to existing projects, please add the new
project without a poster first.");
      case 'display':
        $url = $this->urlGenerator()->linkToRoute(
          'cafevdb.images.get', [
            'joinTable' => ImagesService::USER_STORAGE,
            'ownerId' => urlencode($postersFolder),
          ]);
        $url .= '?timeStamp='.time();
        if ((int)$imageId >= ImagesService::IMAGE_ID_PLACEHOLDER) {
          $url .= '&imageId='.urlencode($imageId);
          $url .= '&preview=128';
        }
        $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
        $div = ''
             . '<div class="photo image-wrapper multi '.$sizeCss.'">'
             . '<img class="cafevdb_inline_image poster zoomable" src="'.$url.'" '
             . 'title="'.$imageId.'" />'
             . '</div>';
        return $div;
      case 'change':
        $imageInfo = json_encode([
          'joinTable' => ImagesService::USER_STORAGE,
          'ownerId' => urlencode($postersFolder),
          'imageId' => urlencode($imageId),
          'imageSize' => -1,
          'preview' => 128,
        ]);
        $imagearea = ''
          .'<div data-image-info=\''.$imageInfo.'\' class="tip project-poster propertycontainer cafevdb_inline_image_wrapper image-wrapper multi '.$sizeCss.'" title="'
        .$this->l->t("Drop image to upload (max %s)", [\OCP\Util::humanFileSize(Util::maxUploadSize())]).'"'
        .' data-element="PHOTO">
  <ul class="phototools transparent hidden contacts_property">
    <li><a class="svg delete" title="'.$this->l->t("Delete current poster").'"></a></li>
    <li><a class="svg edit" title="'.$this->l->t("Edit current poster").'"></a></li>
    <li><a class="svg upload" title="'.$this->l->t("Upload new poster").'"></a></li>
    <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select image from Cloud").'"></a></li>
  </ul>
</div> <!-- project-poster -->
';
        return $imagearea;
      default:
        return $this->l->t("Internal error, don't know what to do concerning project-posters in the given context.");
    }
  }

  public function projectActions($projectId, $projectName, $placeHolder = false, $overview = false)
  {
    $projectPaths = $this->projectService->ensureProjectFolders($projectId, $projectName, null, true);

    if ($placeHolder === false) {
      // Strip the 4-digit year from the end, if present
      // $placeHolder = preg_replace("/^(.*\D)(\d{4})$/", "$1", $projectName);
      $placeHolder = $projectName; // or maybe don't strip.
    }

    $control = ''
            .'
<span class="project-actions-block">
  <select data-placeholder="'.$placeHolder.'"
          class="project-actions"
          title="'.$this->toolTipsService['project-actions'].'"
          data-project-id="'.$projectId.'"
          data-project-name="'.htmlentities($projectName).'">
    <option value=""></option>
'
             .($overview
               ? $this->pageNavigation->htmlTagsFromArray([
                 'pre' => '<optgroup>',
                 'post' => '</optgroup>',
                 [
                   'type' => 'option',
                   'title' => $this->toolTipsService['project-infopage'],
                   'value' => 'project-infopage',
                   'name' => $this->l->t('Project Overview')
                 ]
               ])
               : '')
             .$this->pageNavigation->htmlTagsFromArray([
               'pre' => '<optgroup>',
               'post' => '</optgroup>',
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:project-participants'],
                 'value' => 'project-participants',
                 'name' => $this->l->t('Participants') ],
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:project-instrumentation-numbers'],
                 'value' => 'project-instrumentation-numbers',
                 'name' => $this->l->t('Instrumentation Numbers') ],
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:participant-fields'],
                 'value' => 'project-participant-fields',
                 'name' => $this->l->t('Extra Member Data') ], ])
             .$this->pageNavigation->htmlTagsFromArray([
               'pre' => '<optgroup>',
               'post' => '</optgroup>',
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:files'],
                 'value' => 'project-files',
                 'data' => [ 'projectFiles' => $projectPaths['project'] ],
                 'name' => $this->l->t('Project Files')
               ],
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:wiki'],
                 'value' => 'project-wiki',
                 'data' => [
                   'wikiPage' => $this->projectService->projectWikiLink($projectName),
                   'wikiTitle' => $this->l->t('Project Wiki for %s', [ $projectName ])
                 ],
                 'name' => $this->l->t('Project Notes')
               ],
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:events'],
                 'value' => 'events',
                 'name' => $this->l->t('Events')
               ],
               [
                 'type' => 'option',
                 'title' => $this->toolTipsService['project-action:email'],
                 'value' => 'project-email',
                 'name' => $this->l->t('Em@il')
               ], ])
            .$this->pageNavigation->htmlTagsFromArray([
              'pre' => '<optgroup>',
              'post' => '</optgroup>',
              [
                'type' => 'option',
                'title' => $this->toolTipsService['project-action:debit-mandates'],
                'value' => 'sepa-bank-accounts',
                'disabled' => false, // @todo !Config::isTreasurer(),
                'name' => $this->l->t('Debit Mandates')
              ],
              [
                'type' => 'option',
                'title' => $this->toolTipsService['project-action:payments'],
                'value' => 'project-payments',
                'disabled' => false, // @todo !Config::isTreasurer(),
                'name' => $this->l->t('Payments')
              ],
              [
                'type' => 'option',
                'title' => $this->toolTipsService['project-action:financial-balance'],
                'value' => 'profit-and-loss',
                'data' => [ 'projectFiles' => $projectPaths['balance'] ],
                'name' => $this->l->t('Profit and Loss Account')
              ] ])
            .'
  </select>
</span>
';

    $templateParameters = [
      'projectId' => $projectId,
      'projectName' => $projectName,
      'urlGenerator' => $this->urlGenerator(),
      'toolTips' => $this->toolTipsService,
      'isOverview' => $overview,
      'projectFolders' => $projectPaths
    ];
    $template = new TemplateResponse($this->appName(), 'fragments/projects/project-actions', $templateParameters, 'blank');
    $control .= $template->render();

    return $control;
  }

  /**Gather events, instrumentation numbers and the wiki-page in a
   * form-set for inclusion into some popups etc.
   */
  public function projectToolbox($projectId, $projectName, $value = false, $eventSelect = [])
  {
    $toolbox = $this->pageNavigation->htmlTagsFromArray(
      [
        'pre' => ('<fieldset class="projectToolbox" '.
                  'data-project-id="'.$projectId.'" '.
                  'data-project-name="'.htmlentities($projectName).'">'), // @todo: standard way to do this
        'post' => '</fieldset>',
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action:wiki'],
          'data' => [
            'wikiPage' => $this->projectService->projectWikiLink($projectName),
            'wikiTitle' => $this->l->t('Project Wiki for %s', [ $projectName ])
          ],
          'class' => 'project-wiki tooltip-top',
          'value' => 'project-wiki',
          'name' => $this->l->t('Project Notes')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action:events'],
          'class' => 'events tooltip-top',
          'value' => 'events',
          'name' => $this->l->t('Events')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action:email'],
          'class' => 'project-email tooltip-top',
          'value' => 'project-email',
          'name' => $this->l->t('Em@il')
        ],
        [ 'type' => 'button',
          'title' => $this->toolTipsService['project-action:project-instrumentation-numbers'],
          'class' => 'project-instrumentation-numbers tooltip-top',
          'value' => 'project-instrumentation-numbers',
          'name' => $this->l->t('Instrumentation Numbers')
        ],
      ]);
    return '<div class="projectToolbox">
'.$toolbox.'
</div>
';
  }

  /**
   * Genereate the input data for the link to the CMS in order to edit
   * the project's public web articles inline.
   *
   * @todo Do something more useful in the case of an error (database
   * or CMS unavailable)
   */
  public function projectProgram($projectId, $action)
  {
    $projectPages = $this->projectService->projectWebPages($projectId);
    $urlTemplate = $this->projectService->webPageCMSURL('%articleId%', $action == 'change');
    if ($action != 'change') {
      $urlTemplate .= '&rex_version=1';
    }
    $templateParameters = array_merge(
      $projectPages,
      [
        'appName' => $this->appName(),
        'urlGenerator' => $this->urlGenerator(),
        'pageNavigation' => $this->pageNavigation,
        'projectId' => $projectId,
        'action' => $action,
        'cmsURLTemplate' => $urlTemplate,
        'toolTips' => $this->toolTipsService,
      ]
    );

    $template = new TemplateResponse(
      $this->appName(),
      'project-web-articles',
      $templateParameters,
      'blank'
    );

    return $template->render();
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldvals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newVals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeInsertTrigger(&$pme, $op, $step, $oldVals, &$changed, &$newVals)
  {
    $this->debugPrintValues($oldVals, $changed, $newVals, null, 'before');

    if (empty($newVals['name'])) {
      return false;
    }
    $newVals['name'] = $this->projectService->sanitizeName($newVals['name']);
    if (empty($newVals['name'])) {
      return false;
    }

    // sanitize instrumentation numbers
    $instrumentsColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'instrument_id');
    $voicesColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'voice');

    // Add zeros to the voices data as the "0" voice is needed for
    // convenience in either case, otherwise adding musicians to the
    // ProjectParticipants table fails.
    $instruments = Util::explode(',', $newVals[$instrumentsColumn]??'');
    foreach (Util::explode(',', $newVals[$instrumentsColumn]??'') as $instrument) {
      $newVals[$voicesColumn] = $instrument . self::JOIN_KEY_SEP . '0' . ','
                              . ($newVals[$voicesColumn]??'');
    }
    $voiceItems = Util::explode(',', $newVals[$voicesColumn]);
    foreach ($voiceItems as $key => $voiceItem) {
       list($instrument, $voice) = explode(self::JOIN_KEY_SEP, $voiceItem);
       if (array_search($instrument, $instruments) === false) {
         $this->debug('REMOVE VOICE ' . $voice . ' FOR INSTRUMENT ' . $instrument);
         unset($voiceItems[$key]);
       }
    }
    sort($voiceItems, SORT_NATURAL);
    $newVals[$voicesColumn] = implode(',', array_unique($voiceItems));

    foreach ([$instrumentsColumn, $voicesColumn] as $column) {
      Util::unsetValue($changed, $column);
      if (!empty($newVals[$column])) {
        $changed[] = $column;
      }
    }

    // unset 'copy_participants'
    Util::unsetValue($changed, 'copy_participants');

    $this->debugPrintValues($oldVals, $changed, $newVals, null, 'after');

    return true;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param $pme The phpMyEdit instance
   *
   * @param $op The operation, 'insert', 'update' etc.
   *
   * @param $step 'before' or 'after'
   *
   * @param $oldVals Self-explanatory.
   *
   * @param &$changed Set of changed fields, may be modified by the callback.
   *
   * @param &$newVals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   *
   * @bug Convert this to a function triggering a "user-friendly" error message.
   */
  public function beforeUpdateTrigger(&$pme, $op, $step, &$oldVals, &$changed, &$newVals)
  {
    $this->debugPrintValues($oldVals, $changed, $newVals, null, 'before');

    if (array_search('name', $changed) !== false) {

      if (isset($newVals['name']) && $newVals['name']) {
        $newVals['name'] = $this->projectService->sanitizeName($newVals['name']);
        if ($newVals['name'] === false) {
          return false;
        }
      }
    }

    $instrumentsColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'instrument_id');
    $voicesColumn = $this->joinTableFieldName(self::PROJECT_INSTRUMENTATION_NUMBERS_TABLE, 'voice');
    if (array_search($instrumentsColumn, $changed) !== false
        || array_search($voicesColumn, $changed) !== false) {

      // Add zeros to the voices data as the "0" voice is needed for
      // convenience in either case, otherwise adding musicians to the
      // ProjectParticipants table fails.
      foreach (['old', 'new'] as $dataSet) {
        $dataArray = $dataSet . 'Vals';
        ${$dataSet . 'Instruments'} = Util::explode(',', ${$dataArray}[$instrumentsColumn]??'');
        foreach (${$dataSet . 'Instruments'} as $instrument) {
          ${$dataArray}[$voicesColumn] = $instrument . self::JOIN_KEY_SEP . '0' . ','
                                     . (${$dataArray}[$voicesColumn]??'');
        }
        $items = Util::explode(',', ${$dataArray}[$voicesColumn]);
        sort($items, SORT_NATURAL);
        ${$dataArray}[$voicesColumn] = implode(',', array_unique($items));
      }

      // Remove the voices definitions for removed instruments
      $newVoiceItems = Util::explode(',', $newVals[$voicesColumn]??[]);
      foreach ($newVoiceItems as $key => $voiceItem) {
        list($instrument, $voice) = explode(self::JOIN_KEY_SEP, $voiceItem);
        if (array_search($instrument, $newInstruments) === false) {
          $this->debug('REMOVE VOICE ' . $voice . ' FOR INSTRUMENT ' . $instrument);
          unset($newVoiceItems[$key]);
        }
      }
      $newVals[$voicesColumn] = implode(',', $newVoiceItems);

      // Update changed to reflect the manipulation
      foreach ([$instrumentsColumn, $voicesColumn] as $column) {
        Util::unsetValue($changed, $column);
        if  ($oldVals[$column] != $newVals[$column]) {
          $changed[] = $column;
        }
      }
    }

    $this->debugPrintValues($oldVals, $changed, $newVals, null, 'after');

    return true;
  }

  /**
   * phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit $pme The phpMyEdit instance
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'
   *
   * @param array $oldVals Self-explanatory.
   *
   * @param array &$changed Set of changed fields, may be modified by the callback.
   *
   * @param array &$newVals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function afterInsertTrigger(&$pme, $op, $step, $oldVals, &$changed, &$newVals)
  {
    $this->debug('OLDVALS '.print_r($oldVals, true));
    $this->debug('NEWVALS '.print_r($newVals, true));
    $this->debug('CHANGED '.print_r($changed, true));

    $newProjectId = $newVals['id'];
    if (empty($newProjectId)) {
      throw new \RuntimeException($this->l->t('Copying participants is requested, but the new project id is not given.'));
    }

    // add the new project id to the persistent CGI array
    $pme->addPersistentCgi([
      'projectId' => $newProjectId,
      'projectName' => $newVals['name'],
    ]);

    if ($this->copyOperation()) {
      $oldProjectId = $this->pmeRecordId['id']??null;
      if (empty($oldProjectId))  {
        throw new \RuntimeException($this->l->t('Copying is requested, but the old project id is not given.'));
      }

      $this->debug('Operation: ' . $this->operation());
      $this->debug('OLD PROJECT ID ' . $oldProjectId);
      $this->debug('NEW PROJECT ID ' . $newProjectId);

      // clone participants fields if not empty, list of names is given
      $participantFields = $newVals[$this->joinTableFieldName(self::PROJECT_PARTICIPANT_FIELDS_TABLE, 'id')];
      $participantFields = Util::explode(',', $participantFields);
      $this->debug('PARTICIPANT FIELDS ' . print_r($participantFields, true));

      if (!empty($participantFields)) {
        /** @var Repositories\ProjectsRepository $repository */
        $repository = $this->getDatabaseRepository(self::ENTITY);

        /** @var Entities\Project $oldProject */
        $oldProject = $repository->find($oldProjectId);

        /** @var Entities\Project $newProject */
        $newProject = $repository->find($newProjectId);

        $this->debug('#EXISTING FIELDS NEW PROJECT ' . $newProject->getParticipantFields()->count());

        /** @var Entities\ProjectParticipantField $oldField */
        foreach ($oldProject->getParticipantFields()->matching(DBUtil::criteriaWhere([ 'id' => $participantFields ])) as $oldField) {
          /** @var Entities\ProjectParticipantField $newField */
          $newField = clone $oldField;
          $newField->setProject($newProject);
          $this->persist($newField);
          $newProject->getParticipantFields()->add($newField);
        }
        $this->flush();
      }

      // clone participants if requested
      list(, $copyParticipants) = explode(self::JOIN_KEY_SEP, $newVals['copy_participants']);
      if ($copyParticipants) {

        /** @var Repositories\ProjectsRepository $repository */
        $repository = $this->getDatabaseRepository(self::ENTITY);

        /** @var Entities\Project $oldProject */
        $oldProject = $repository->find($oldProjectId);

        /** @var Entities\Project $oldProject */
        $newProject = $repository->find($newProjectId);

        /** @var Entities\ProjectParticipant $oldParticipant */
        foreach ($oldProject->getParticipants() as $oldParticipant) {
          /** @var Entities\Musician $musician */
          $musician = $oldParticipant->getMusician();

          /** @var Entities\ProjectParticipant $newParticipant */
          $newParticipant = new Entities\ProjectParticipant;
          $newParticipant->setMusician($musician)
                         ->setProject($newProject);

          /** @var Entities\ProjectInstrument $oldProjectInstrument */
          foreach ($oldParticipant->getProjectInstruments() as $oldProjectInstrument) {
            /** @var Entities\ProjectInstrument $newProjectInstrument */
            $newProjectInstrument = new Entities\ProjectInstrument;
            $newProjectInstrument->setProject($newProject)
                                 ->setMusician($musician)
                                 ->setInstrument($oldProjectInstrument->getInstrument())
                                 ->setVoice($oldProjectInstrument->getVoice())
                                 ->setSectionLeader($oldProjectInstrument->getVoice())
                                 ->setProjectParticipant($newParticipant);
            $newParticipant->getProjectInstruments()->add($newProjectInstrument);
          }
        }
        $this->flush();
      }
    }

    //throw new \RuntimeException('DEBUG STOPPER');

    $this->projectService->createProjectInfraStructure($newVals);

    $this->projectId = $newProjectId;
    $this->projectName = $newVals['name'];

    return true;
  }

  /**
   * @copydoc Projects::afterInsertTrigger()
   */
  public function afterUpdateTrigger(&$pme, $op, $step, $oldVals, &$changed, &$newVals)
  {
    if (array_search('name', $changed) === false) {
      // Nothing more has to be done if the name stays the same
      return true;
    }

    $this->projectService->renameProject($oldVals,  $newVals);
    $this->projectName = $newVals['name'];

    return true;
  }

  /**
   * @copydoc Projects::afterInsertTrigger()
   *
   * This trigger, in particular, tries to take care to remove all
   * "side-effects" the existance of the project had. However, there
   * is some data which must not be removed automatically
   */
  public function deleteTrigger(&$pme, $op, $step, &$oldVals, &$changed, &$newVals)
  {
    $this->projectService->deleteProject($pme->rec);

    $changed = []; // signal nothing more to delete

    $this->projectName = $this->l->t('%s (deleted)', $this->projectName);

    return true;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
