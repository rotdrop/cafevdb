<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\PageRenderer;

use chillerlan\QRCode\QRCode;

use function OCA\CAFEVDB\Common\Functions\strcat as cat;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * (Legal-)persons associated to a project, primarily for organizing financial
 * affairs and in order to keep things together. This table view refers also
 * to the ProjectParticipants table as well as the ProjectParticipants view
 * does, but comes with a reduced set of fields.
 */
class ProjectAssociates extends ProjectParticipants
{
  use FieldTraits\AllProjectsTrait;
  use FieldTraits\FinanceModeNavigationItemTrait;
  use FieldTraits\InstrumentsTrait;
  use FieldTraits\MailingListsTrait;
  use FieldTraits\MusicianAvatarTrait;
  use FieldTraits\MusicianEmailsTrait;
  use FieldTraits\MusicianEnsureUserIdSlugTrait;
  use FieldTraits\MusicianFromRowTrait;
  use FieldTraits\MusicianGenderTrait;
  use FieldTraits\MusicianInstrumentsRankingTrait;
  use FieldTraits\MusicianPublicNameTrait;
  use FieldTraits\ParticipantFieldsTrait;
  use FieldTraits\ParticipantTotalFeesTrait;
  use FieldTraits\ProjectEntityTrait;
  use FieldTraits\QueryFieldTrait;
  use FieldTraits\SepaAccountsTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const TEMPLATE = 'project-associates';
  const TABLE = self::PROJECT_PARTICIPANTS_TABLE;

  private const EXTRA_VOICES = 2;
  private const INSERT_VOICES = 8;

  /**
   * Join table structure. All updates are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    self::TABLE => [
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\ProjectParticipant::class,
    ],
    self::MUSICIANS_TABLE => [
      'entity' => Entities\Musician::class,
      'identifier' => [ 'id' => 'musician_id' ],
      'column' => 'id',
    ],
    self::PROJECTS_TABLE => [
      'entity' => Entities\Project::class,
      'identifier' => [ 'id' => 'project_id' ],
      'column' => 'id',
    ],
    self::PROJECT_INSTRUMENTS_TABLE => [
      'entity' => Entities\ProjectInstrument::class,
      // 'flags' => self::JOIN_GROUP_BY,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'instrument_id' => false,
        'voice' => [ 'self' => true ],
      ],
      'column' => 'instrument_id',
    ],
    self::INSTRUMENTS_TABLE => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::PROJECT_INSTRUMENTS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    self::MUSICIAN_INSTRUMENTS_TABLE => [
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'instrument_id',
    ],
    self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians' => [
      'entity' => Entities\Instrument::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'id' => [
          'table' => self::MUSICIAN_INSTRUMENTS_TABLE,
          'column' => 'instrument_id',
        ],
      ],
      'column' => 'id',
    ],
    self::PROJECT_PAYMENTS_TABLE => [
      'entity' => Entities\ProjectPayment::class,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
      ],
      'column' => 'id',
    ],
    // extra input fields depending on the type of the project,
    // e.g. service fees etc.
    self::PROJECT_PARTICIPANT_FIELDS_TABLE => [
      'entity' => Entities\ProjectParticipantField::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'project_id' => 'project_id',
        'id' => false,
      ],
      'column' => 'id',
    ],
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDatum::class,
      'flags' => self::JOIN_REMOVE_EMPTY,
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'field_id' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_TABLE,
          'column' => 'id',
        ],
        'option_key' => false,
      ],
      'column' => 'option_key',
      'encode' => 'BIN2UUID(%s)',
    ],
    // the data for the extra input fields
    self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE => [
      'entity' => Entities\ProjectParticipantFieldDataOption::class,
      'flags' => 0,
      'identifier' => [
        'field_id' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_TABLE,
          'column' => 'id',
        ],
        'key' => [
          'table' => self::PROJECT_PARTICIPANT_FIELDS_DATA_TABLE,
          'column' => 'option_key',
        ],
      ],
      'column' => 'key',
      'encode' => 'BIN2UUID(%s)',
    ],
  ];

  /** {@inheritdoc} */
  public function shortTitle(): string
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove the person from %s?', [ $this->projectName ]);
    } elseif ($this->viewOperation()) {
      return $this->l->t('Display of all stored data for the shown person.');
    } elseif ($this->changeOperation()) {
      return $this->l->t('Edit the data of the displayed person.');
    }
    return $this->l->t('Business Contacts and Associates for Project "%s"', [ $this->projectName ]);
  }

  /** {@inheritdoc} */
  public function render(bool $execute = true):void
  {
    $expertMode      = $this->expertMode;
    $instrumentInfo  = $this->getInstrumentInfo();
    $instrumentsFilter = array_keys($this->getNonInstruments());

    $opts            = [];

    $opts['css']['postfix'] = [
      self::CSS_TAG_DIRECT_CHANGE,
      self::CSS_TAG_SHOW_HIDE_DISABLED,
      self::CSS_TAG_PROJECT_PARTICIPANT_FIELDS_DISPLAY,
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      PersistentCGIKeys::TEMPLATE => static::TEMPLATE,
      PersistentCGIKeys::TABLE => $opts['tb'],
      PersistentCGIKeys::TEMPLATE_RENDERER => DataConstants::RENDERER_PREFIX_TAG . static::TEMPLATE,
      PersistentCGIKeys::DATA_PREFIX => [
        'musicians' => self::MUSICIANS_TABLE . self::JOIN_FIELD_NAME_SEPARATOR,
      ],
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'musician_id' => 'int' ];
    $opts['groupby_fields'] = array_keys($opts['key']);

    // Sorting field(s)
    $opts['sort_field'] = [
      self::joinTableFieldName(self::MUSICIANS_TABLE, 'display_name'),
      self::joinTableFieldName(self::MUSICIANS_TABLE, 'organization'),
      self::joinTableFieldName(self::MUSICIANS_TABLE, 'sur_name'),
      self::joinTableFieldName(self::MUSICIANS_TABLE, 'first_name'),
      self::joinTableFieldName(self::MUSICIANS_TABLE, 'nick_name'),
    ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDF';
    $opts['options'] .= 'M'; // misc

    // controls display an location of edit/misc buttons
    $opts['navigation'] = self::PME_NAVIGATION_MULTI;

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    $export = $this->pageNavigation->tableExportButton();
    $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);

    $participantFields = $this->project->getParticipantFields(ParticipationContext::ASSOCIATES);

    // count number of finance fields
    $extraFinancial = 0;
    foreach ($participantFields as $field) {
      $extraFinancial += (int)($field->getDataType() == FieldDataType::RECEIVABLES);
      $extraFinancial += (int)($field->getDataType() == FieldDataType::LIABILITIES);
    }
    if ($extraFinancial > 0) {
      $useFinanceTab = true;
      $financeTab = 'finance';
    } else {
      $useFinanceTab = false;
      $financeTab = 'project';
    }

    // Tweak the join-structure with dynamic data.
    list($allProjectsJoin, $allProjectsFieldGenerator) = $this->renderAllProjectsField(
      musicianIdField: 'musician_id',
      tableTab: 'musician',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $allProjectsJoin);

    list($musicanAvatarJoin, $musicianAvatarFieldGenerator) = $this->renderMusicianAvatarField(
      tableTab: 'miscinfo',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $musicanAvatarJoin);

    list($emailJoin, $emailFieldGenerator) = $this->renderMusicianEmailFields(
      musicianIdField: 'musician_id',
      tableTab: 'contactdata',
      css: [],
    );
    $this->joinStructure = array_merge($this->joinStructure, $emailJoin);

    list($sepaJoin, $sepaFieldGenerator) = $this->renderSepaAccounts(
      'musician_id', $this->projectId, $this->membersProjectId, $financeTab);
    $this->joinStructure = array_merge($this->joinStructure, $sepaJoin);

    list($participantFieldsJoin, $participantFieldsGenerator) =
      $this->renderParticipantFields($participantFields, 'project_id', $financeTab);
    $this->joinStructure = array_merge($this->joinStructure, $participantFieldsJoin);

    /*
     *
     **************************************************************************
     *
     * General display options
     *
     */

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'] ?? [], [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs' => $this->tableTabs($participantFields, $useFinanceTab),
        'navigation' => 'VCD',
      ]);

    /*
     *
     **************************************************************************
     *
     * Field descriptions
     *
     */

    $opts['fdd']['project_id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Project-Id'),
      'input'    => ($expertMode ? 'R' : 'RH'),
      'select'   => 'N',
      'options'  => 'LFACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      ];

    $opts['fdd']['musician_id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Musician-Id'),
      'input'    => ($expertMode ? 'R' : 'RH'),
      'select'   => 'N',
      'options'  => 'LFACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    ];

    array_walk($this->joinStructure, function(&$joinInfo, $table) {
      $joinInfo['table'] = $table;
      switch ($table) {
        case self::INSTRUMENTS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
          list($select, $join) = explode(' FROM ' . self::INSTRUMENTS_TABLE . ' t', $joinInfo['sql']);
          $joinInfo['sql'] = $select
            . ', GROUP_CONCAT(DISTINCT __t3.family ORDER BY __t3.family ASC) AS families'
            . ' FROM ' . self::INSTRUMENTS_TABLE . ' t
INNER JOIN ' . self::INSTRUMENT_FAMILIES_JOIN_TABLE . ' __t2
ON t.id = __t2.instrument_id
INNER JOIN ' . self::INSTRUMENT_FAMILIES_TABLE . ' __t3
ON __t2.instrument_family_id = __t3.id
' . $join . '
WHERE __t3.family = "' . Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY . '"
GROUP BY t.id';
          break;
        case self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians':
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'name');
          list($select, $join) = explode(' FROM ' . self::INSTRUMENTS_TABLE . ' t', $joinInfo['sql']);
          $joinInfo['sql'] = $select
            . ', GROUP_CONCAT(DISTINCT __t3.family ORDER BY __t3.family ASC) AS families'
            . ' FROM ' . self::INSTRUMENTS_TABLE . ' t
INNER JOIN ' . self::INSTRUMENT_FAMILIES_JOIN_TABLE . ' __t2
ON t.id = __t2.instrument_id
INNER JOIN ' . self::INSTRUMENT_FAMILIES_TABLE . ' __t3
ON __t2.instrument_family_id = __t3.id
' . $join . '
WHERE __t3.family = "' . Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY . '"
GROUP BY t.id';
          break;
        case self::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE:
          $joinInfo['sql'] = $this->makeFieldTranslationsJoin($joinInfo, 'label');
          break;
        default:
          break;
      }
    });

    $this->joinStructure[self::PROJECT_INSTRUMENTS_TABLE]['filter'] = [
      'instrument_id' => [ 'value' => $instrumentsFilter ],
    ];
    $this->joinStructure[self::INSTRUMENTS_TABLE]['filter'] = [
      'id' => [ 'value' => $instrumentsFilter ],
    ];
    $this->defineJoinStructure($opts);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'organization',
      [
        'name'     => $this->l->t('Organization'),
        'tab'      => [ 'id' => 'tab-all' ],
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'job_title',
      [
        'name'     => $this->l->t('Job-Title'),
        'tab'      => [ 'id' => 'musician' ],
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'sur_name',
      [
        'name'     => $this->l->t('Name'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'first_name',
      [
        'name'     => $this->l->t('First Name'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'nick_name',
      [
        'name'     => $this->l->t('Nickname'),
        'tab'      => [ 'id' => 'musician' ],
        'input|LF' => $this->pmeBare ? '' : 'H',
        'sql|LFVD' => 'IF($join_col_fqn IS NULL OR $join_col_fqn = \'\', $table.first_name, $join_col_fqn)',
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $nickNamePlaceholder = $this->l->t('e.g. Cathy');
            $firstName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'first_name')];
            $lockedPlaceholder = $firstName ?: $nickNamePlaceholder;
            $unlockedPlaceholder = $this->l->t('e.g. Cathy');
            if (empty($row[PHPMyEdit::QUERY_FIELD . $k])) {
              return [
                'placeholder' => $lockedPlaceholder,
                'readonly' => true,
                'data-placeholder' => $unlockedPlaceholder,
              ];
            } else {
              return [
                'placeholder' => $unlockedPlaceholder,
                'readonly' => false,
                'data-placeholder' => $lockedPlaceholder,
              ];
            }
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = empty($row[PHPMyEdit::QUERY_FIELD . $k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-nickname"
  '.$checked.'
  type="checkbox"
  class="pme-input pme-input-lock lock-empty"/>
<label class="pme-input pme-input-lock lock-empty"
       title="'.$this->toolTipsService['pme:input:lock:empty'].'"
       for="pme-musician-nickname"></label>';
          },
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name', [
        'name'     => $this->l->t('Display-Name'),
        'tab'      => [ 'id' => 'tab-all' ],
        'css'      => [ 'postfix' => [ 'default-readonly', 'tab-musician-readwrite', 'tab-all-readwrite', 'musician-public-name' ], ],
        'sql|LFVD' => static::musicianPublicNameSql(),
        'maxlen'   => 384,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $displayNamePlaceholder = $this->l->t('e.g. Doe, Cathy');
            $surName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'sur_name')];
            $firstName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'first_name')];
            $nickName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'nick_name')];
            $lockedPlaceholder = $op == 'add' ? $displayNamePlaceholder : $surName.', '.($nickName?:$firstName);
            $unlockedPlaceholder = $this->l->t('e.g. Doe, Cathy');
            if (empty($row[PHPMyEdit::QUERY_FIELD . $k])) {
              return [
                'placeholder' => $lockedPlaceholder,
                'readonly' => true,
                'data-locked-placeholder' => $lockedPlaceholder,
                'data-unlocked-placeholder' => $unlockedPlaceholder,
              ];
            } else {
              return [
                'placeholder' => $unlockedPlaceholder,
                'readonly' => false,
                'data-locked-placeholder' => $lockedPlaceholder,
                'data-unlocked-placeholder' => $unlockedPlaceholder,
              ];
            }
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = empty($row[PHPMyEdit::QUERY_FIELD . $k]) ? '' : 'checked="checked" ';
            return '<input id="pme-musician-displayname"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-empty"
/><label class="pme-input pme-input-lock lock-empty"
         title="'.$this->toolTipsService['pme:input:lock:empty'].'"
         for="pme-musician-displayname"></label>';
          },
        ],
      ]);

    // soft-deletion
    $opts['fdd']['deleted'] = Util::arrayMergeRecursive(
      $this->defaultFDD['deleted'], [
        'name' => $this->l->t('Deleted'),
        'tab'  => [ 'id' => 'tab-all' ],
        'options' => 'LFACPDV',
        'dateformat' => 'medium',
        'timeformat' => 'short',
        'maxlen' => 19,
        'input' => ($this->showDisabled ? '' : 'RH'),
      ]
    );
    Util::unsetValue($opts['fdd']['deleted']['css']['postfix'], 'date');
    $opts['fdd']['deleted']['css']['postfix'][] = 'datetime';

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'display_name_personal', [
        'name'     => $this->l->t('Display-Name (pers.)'),
        'tab'      => [ 'id' => 'musician' ],
        'css'      => [ 'postfix' => [ 'default-readonly', 'tab-musician-readwrite', 'tab-all-readwrite', 'musician-personal-public-name' ], ],
        'options'  => 'LFAVCPD',
        'input' => $this->pmeBare ? 'R' : 'HR', // handy for export
        'sql' => static::musicianPublicNameSql(firstNameFirst: true),
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'gender', [
        'name'    => strval($this->l->t('Gender')),
        'tab'     => [ 'id' => [ 'musician' ], ],
        'select'  => 'D',
        'maxlen'  => 128,
        'sort'    => true,
        'options'  => 'LFAVCPD',
        'input|LF' => $this->pmeBare ? 'R' : 'HR', // handy for export
        'css'     => [ 'postfix' => [ 'gender', 'tooltip-wide', 'allow-empty' ], ],
        'values2' => Types\EnumGender::getL10NValues($this->l),
        'tooltip' => $this->toolTipsService['page-renderer:musicians:gender'],
        'display|LF' => [
          'popup' => function($cellData, int $k, array $row, PHPMyEdit $pme) {
            if (!empty($cellData) || empty($row) || !empty($row[$this->queryField($k)])) {
              return '';
            }
            $genderTypes = $this->guessGender($row);
            if (!empty($genderTypes)) {
              return $this->l->t('auto-detected gender: %s', implode(', ', $genderTypes));
            }
            return '';
          }
        ],
        'display|CPVD' => [
          'postfix' => fn(string $op, string $pos, int $k, array $row, PHPMyEdit $pme) => $this->genderDisplayPostfix($op, $k, $row),
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'user_id_slug', [
        'tab'      => [ 'id' => 'musician' ],
        'name'     => $this->l->t('User Id'),
        'css'      => [ 'postfix' => [ 'musician-name', ], ],
        'input|LF' => $this->pmeBare ? 'R' : 'HR',
        // 'options'  => 'AVCPD',
        'select'   => 'T',
        'maxlen'   => 256,
        'sort'     => true,
        'display|ACP' => [
          'attributes' => function($op, $k, $row, $pme) {
            $surName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'sur_name')];
            $firstName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'first_name')];
            $nickName = $firstName = $row[$this->joinQueryField(static::MUSICIANS_TABLE, 'nick_name')];
            $placeHolder = $this->projectService->defaultUserIdSlug($surName, $firstName, $nickName);
            return [
              'placeholder' => $placeHolder,
              'readonly' => true,
            ];
          },
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = 'checked="checked" ';
            return '<input id="pme-musician-user-id-slug"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock lock-unlock"
/><label class="pme-input pme-input-lock lock-unlock"
         title="'.$this->toolTipsService['pme:input:lock:unlock'].'"
         for="pme-musician-user-id-slug"></label>';
          },
        ],
      ]);

    $fdd = [
      'tab'         => [ 'id' => [ 'instrumentation' ] ],
      'name'        => $this->l->t('Role'),
      // 'input'    => ($expertMode ? 'R' : 'RH'),
      'css'         => [
        'postfix' => [
          'project-instruments',
          'tooltip-top',
          'select-wide',
        ],
      ],
      'display|LVF' => ['popup' => 'data'],
      'sql|LFVDCP'    => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'select'      => 'D',
      'filter' => [
        'having' => true,
      ],
      'values' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ '$table.l10n_name' ],
          'ifnull' => [ false ],
          'cast' => [ false ],
        ],
        'orderby'     => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE], ],
      ],
      // 'valueGroups' => $instrumentInfo['idGroups'], not needed here
    ];
    $fdd['values|VDPC'] = array_merge($fdd['values'], [
      PHPMyEdit::OPT_FILTERS => '$table.id IN (SELECT DISTINCT instrument_id
  FROM ' . self::MUSICIAN_INSTRUMENTS_TABLE . ' mi
  WHERE '
      . '$record_id[project_id] = ' . $this->projectId
      . ' AND '
      . '$record_id[musician_id] = mi.musician_id'
      . ' AND '
      . 'mi.instrument_id IN ("' . implode('","', $instrumentsFilter) . '")'
      .')',
    ]);
    $fdd['values|LFV'] = array_merge($fdd['values'], [
      PHPMyEdit::OPT_FILTERS => '$table.id IN (SELECT DISTINCT instrument_id
  FROM ' . self::PROJECT_INSTRUMENTS_TABLE . ' pi
  WHERE '
      . $this->projectId . ' = pi.project_id'
      . ' AND '
      . 'pi.instrument_id IN ("' . implode('","', $instrumentsFilter) . '")'
      . ')',
    ]);

    // Use $fdd defined above after tweaking its values
    list($instrumentsFddIndex,) = $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::PROJECT_INSTRUMENTS_TABLE, 'voice',
      [
        'tab'      => [ 'id' => 'instrumentation' ],
        'name'     => $this->l->t('Voice'),
        'default'  => 0, // keep in sync with ProjectInstrumentationNumbers
        'input' => 'RH',
        'select'   => 'M',
        'css'      => [
          'postfix' => [
            'allow-empty',
            'no-search',
            'instrument-voice',
            'select-wide',
          ],
        ],
        'sql|VD' => "GROUP_CONCAT(DISTINCT
  IF(\$join_col_fqn > 0,
     CONCAT(".$this->joinTables[self::INSTRUMENTS_TABLE].".l10n_name,
            ' ',
            \$join_col_fqn),
     NULL)
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
        // copy/change only include non-zero voice
        'sql|CP' => "GROUP_CONCAT(
  DISTINCT
  IF(".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice > 0,
    CONCAT_WS(
      '".self::JOIN_KEY_SEP."',
      ".$this->joinTables[self::INSTRUMENTS_TABLE].".id,
      ".$this->joinTables[self::PROJECT_INSTRUMENTS_TABLE].".voice),
    NULL
  )
  ORDER BY ".$this->joinTables[self::INSTRUMENTS_TABLE].".sort_order ASC)",
      ]);

    $musicianInstrumentsJoin = $this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE];

    $fdd = [
      'name' => $this->l->t('Possible Roles'),
      'tab'  => [ 'id' => [ 'musician', 'instrumentation' ] ],
      'css'  => [
        'postfix' => [
          'musician-instruments',
          'tooltip-top',
          'no-chosen',
          'selectize',
        ],
      ],
      'display|LVF' => ['popup' => 'data'],
      'display|ACP' => [
        'attributes' => [
          'data-selectize-options' => [
            'create' => false,
            'plugins' => [ 'drag_drop', ],
          ],
        ],
      ],
      'sql'         => 'GROUP_CONCAT(DISTINCT
  IF(' . $musicianInstrumentsJoin . '.deleted IS NOT NULL, NULL, $join_col_fqn)
  ORDER BY '.$this->joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)',
      'select'      => 'M',
      'values' => [
        'column'      => 'id',
        'description' => [
          'columns' => [ 'l10n_name', ],
          'cast' => [ false ],
          'ifnull' => [ false ],
        ],
        'orderby'     => '$table.sort_order ASC',
        'join' => [ 'reference' => $this->joinTables[self::INSTRUMENTS_TABLE . self::VALUES_TABLE_SEP . 'musicians'], ],
      ],
      'valueGroups' => $instrumentInfo['idGroups'],
      'filter' => [
        'having' => true,
      ],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    // Use $fdd defined above after tweaking its values
    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'deleted', [
        'name'    => $this->l->t('Disabled Roles'),
        'tab'     => [ 'id' => [ 'musician', 'instrumentation' ] ],
        'css'     => [ 'postfix' => [ 'selectize', 'no-chosen', ], ],
        'sql'     => 'GROUP_CONCAT(DISTINCT IF($join_col_fqn IS NULL, NULL, $join_table.instrument_id))',
        'default' => null,
        'select'  => 'M',
        'input'   => 'SR',
        'tooltip' => $this->toolTipsService['page-renderer:musicians:instruments-disabled'],
        'values2' => $instrumentInfo['byId'],
        'valueGroups' => $instrumentInfo['idGroups'],
        'filter' => [
          'having' => true,
          // 'flags' => PHPMyEdit::OMIT_SQL|PHPMyEdit::OMIT_DESC,
        ],
      ]);

    /*
     *
     **************************************************************************
     *
     * participation-status from the musicians table
     *
     */

    $participationStatusFddIndex = count($opts['fdd']);
    $opts['fdd']['participation_status'] = [
      'name'    => strval($this->l->t('Participation Status')),
      // 'tab'     => [ 'id' => [ 'orchestra' ] ],
      // 'input'    => ($expertMode ? 'R' : 'RH'),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => [ 'postfix' => [ 'participation-status', 'tooltip-wide', ], ],
      'values2' => Types\EnumParticipationStatus::getL10NValues($this->l),
      'tooltip' => $this->toolTipsService['page-renderer:musicians:participation-status'],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'default_participation_status',
      [
        'name'    => $this->l->t('Email Status'),
        'select'  => 'D',
        'maxlen'  => 128,
        'input'   => 'HR',
        'css'     => ['postfix' => [ 'participation-status', 'tooltip-wide', ], ],
        'values2' => Types\EnumParticipationStatus::getL10NValues($this->l),
        'tooltip' => $this->toolTipsService['page-renderer:musicians:participation-status'],
      ]);

    // soft-deleted musician kept to keep the instrumentation for the old project
    list(, $fieldName) = $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'deleted', array_merge(
        $this->defaultFDD['deleted'], [
          'input' => ($this->showDisabled && $this->expertMode ? '' : 'HR'),
          'name' => $this->l->t('Musician Deleted'),
          'dateformat' => 'medium',
          'timeformat' => 'short',
          'maxlen' => 19,
        ]
      )
    );
    Util::unsetValue($opts['fdd'][$fieldName]['css']['postfix'], 'date');
    $opts['fdd'][$fieldName]['css']['postfix'][] = 'datetime';

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'cloud_account_deactivated', [
        'name' => $this->l->t('Cloud Account Deactivated'),
        'tab'     => [ 'id' => [ 'miscinfo' ] ],
        'input' => null,
        'select' => 'C',
        'css' => [ 'postfix' => [ 'cloud-account-deactivated', ], ],
        'sort' => true,
        'default' => null,
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVDF' => [
          '' => '',
          1 => '&#10004;',
        ],
        'align|LF' => 'center',
        'sql|LVDF' => 'COALESCE($join_col_fqn, "")',
        'tooltip' => $this->toolTipsService['page-renderer:musicians:cloud-account-deactivated'],
        'display' => [ 'popup' => 'tooltip' ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'cloud_account_disabled', [
        'name' => $this->l->t('Hidden from Cloud'),
        'tab'     => [ 'id' => [ 'miscinfo' ] ],
        'input' => null,
        'select' => 'C',
        'css' => [ 'postfix' => [ 'cloud-account-disabled', ], ],
        'sort' => true,
        'default' => 1,
        'values2|CAP' => [ 1 => '' ], // empty label for simple checkbox
        'values2|LVDF' => [
          '' => '',
          1 => '&#10004;',
        ],
        'align|LF' => 'center',
        'sql|LVDF' => 'COALESCE($join_col_fqn, "")',
        'tooltip' => $this->toolTipsService['page-renderer:musicians:cloud-account-disabled'],
        'display' => [ 'popup' => 'tooltip' ],
      ]);

    /*
     *
     **************************************************************************
     *
     * project fee and debit mandates information
     *
     */

    $monetary = $this->participantFieldsService->monetaryFields($this->project, ParticipationContext::ASSOCIATES);
    if ($monetary->count() > 0 || ($this->projectId == $this->membersProjectId)) {
      $this->makeTotalFeesField($opts['fdd'], $monetary, $financeTab);
    }

    $totalFeesTargetIndex = count($opts['fdd']);

    /*
     *
     **************************************************************************
     *
     * extra columns like project fee, deposit etc.
     *
     */

    // Generate input fields for the extra columns
    $subTotals = [];
    $participantFieldsGenerator($opts['fdd'], $subTotals);

    if (!empty($subTotals)) {
      $totalFeesIndex = count($opts['fdd']);
      $this->makeTotalFeesFields($opts['fdd'], $subTotals, $financeTab);

      $baseFdd = array_slice($opts['fdd'], 0, $totalFeesTargetIndex);
      $participantFieldsFdd = array_slice($opts['fdd'], $totalFeesTargetIndex, $totalFeesIndex - $totalFeesTargetIndex);
      $totalFeesFdd = array_slice($opts['fdd'], $totalFeesIndex);

      $opts['fdd'] = $baseFdd + $totalFeesFdd + $participantFieldsFdd;
    }

    /*
     *
     **************************************************************************
     *
     * several further fields from Musicians table
     *
     */

    $allProjectsFieldGenerator($opts['fdd']);

    $emailFieldGenerator($opts['fdd']);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'mobile_phone',
      [
        'name'     => $this->l->t('Mobile Phone'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'phone-number', ], ],
        'display'  => [
          'popup' => function($data) {
            return $this->phoneNumberService->metaData($data, null, '<br/>');
          }
        ],
        'nowrap'   => true,
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'fixed_line_phone',
      [
        'name'     => $this->l->t('Fixed Line Phone'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'phone-number', ], ],
        'display'  => [
          'popup' => function($data) {
            return $this->phoneNumberService->metaData($data, null, '<br/>');
          }
        ],
        'nowrap'   => true,
        'maxlen'   => 384,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'address_supplement',
      [
        'name'     => $this->l->t('Address Supplement'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'address-supplement', ], ],
        'maxlen'   => 128,
        'input|LF' => $expertMode ? '' : 'H',
        'tooltip'  => $this->toolTipsService['page-renderer:musicians:address-supplement'],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'street',
      [
        'name'     => $this->l->t('Street'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'street', ], ],
        'maxlen'   => 128,
        'sql|FL'   => 'CONCAT(
  IF(COALESCE($join_table.address_supplement, "") <> "",
    CONCAT($join_table.address_supplement, ", "),
    ""),
  $join_table.street, COALESCE(CONCAT(" ", $join_table.street_number), ""))',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'street_number',
      [
        'name'     => $this->l->t('Street Number'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'street-number', ], ],
        'maxlen'   => 32,
        'size'     => 11,
        'input|LF' => $expertMode ? '' : 'H',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'po_box',
      [
        'name'     => $this->l->t('P.O. Box '),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'po-box', ], ],
        'maxlen'   => 128,
        'size'     => 11,
        'input|LF' => ($expertMode || $this->pmeBare) ? '' : 'H',
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'postal_code',
      [
        'name'     => $this->l->t('Postal Code'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'postal-code', ], ],
        'maxlen'   => 11,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'city',
      [
        'name'     => $this->l->t('City'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'css'      => [ 'postfix' => [ 'musician-address', 'city', ], ],
        'maxlen'   => 128,
      ]);

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'country',
      [
        'name'     => $this->l->t('Country'),
        'tab'      => [ 'id' => [ /* 'musician', */ 'contactdata', ], ],
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => $this->getConfigValue(ConfigConstants::STREET_ADDRESS_COUNTRY),
        'css'      => [ 'postfix' => [ 'musician-address', 'country', 'chosen-dropup', 'allow-empty', ], ],
        'values2'     => $countries,
        'valueGroups' => $countryGroups,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'birthday',
      array_merge($this->defaultFDD['birthday'], [ 'tab' => [ 'id' => 'musician' ], ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'remarks',
      [
        'tab'      => ['id' => 'musician'],
        'name'     => $this->l->t('General Remarks'),
        'maxlen'   => 65535,
        'css'      => ['postfix' => [ 'remarks', 'tooltip-top', 'squeeze-subsequent-lines', 'maximize', ], ],
        'textarea' => [
          'css' => 'wysiwyg-editor',
          'rows' => 5,
          'cols' => 66,
        ],
        'display|LF' => [
          'popup' => 'data',
          'prefix' => '<div class="pme-cell-wrapper half-line-width"><div class="pme-cell-squeezer">',
          'postfix' => '</div></div>',
        ],
        'escape' => false,
        'display' => [
          'attributes' => [ 'readonly' => true, ],
          'popup' => 'data',
        ],
        'display|ACP' => [
          'attributes' => [ 'readonly' => true, ],
          'popup' => 'data',
          'prefix' => '<div class="flex-container" style="position:relative;">',
          'postfix' => function($op, $pos, $k, $row, $pme) {
            $checked = 'checked="checked" ';
            return '  <input id="pme-musician-remarks"
  ' . $checked . '
    type="checkbox"
    class="pme-input pme-input-lock lock-unlock top-zero"/>
  <label class="pme-input pme-input-lock lock-unlock top-zero"
         title="' . $this->toolTipsService['pme:input:lock:unlock'].'"
         for="pme-musician-remarks"></label>
</div>';
          },
        ],
        'tooltip' => $this->l->t('General, not project-specific remarks. The field cannot be changed from here.'),
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'language',
      [
        'tab'      => ['id' => 'musician'],
        'name'     => $this->l->t('Language'),
        'css'      => [ 'postfix' => [ 'musician-language', 'chosen-dropup', 'allow-empty', ], ],
        'select'   => 'D',
        'maxlen'   => 128,
        'default'  => 'Deutschland',
        'values2'  => $this->localeLanguageNames(),
      ]);

    $musicianAvatarFieldGenerator($opts['fdd']);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'address_book_uri',
      [
        'tab'      => [ 'id' => 'miscinfo' ],
        'name'     => $this->l->t('Address Book'),
        'css'      => [ 'postfix' => [ 'address-book-uri', ], ],
        'input'    => 'R',
        'select'   => 'T',
        'maxlen'   => 128,
        'sort'     => true,
      ]);

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '$main_table.musician_id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        switch ($action) {
          case 'change':
          case 'display':
            list('musician' => $musician, 'categories' => $categories) = $this->musicianFromRow($row, $pme);
            if ($musician == null) {
              // throw new \InvalidArgumentException(
              //   'No musician id in data-base row '
              //   . print_r($row, true) . ' queries: '
              //   . print_r($this->pme->queryLog(), true)
              // );
              return '';
            }
            $vcard = $this->contactsService->export($musician);
            unset($vcard->PHOTO); // too much information
            $categories = array_merge($categories, $vcard->CATEGORIES->getParts());
            sort($categories);
            $vcard->CATEGORIES->setParts($categories);
            //$this->logInfo($vcard->serialize());
            return '<img height="231" width="231" src="'.(new QRCode)->render($vcard->serialize()).'"></img>';
          default:
            return '';
        }
      },
      'default' => '',
      'sort' => false
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'uuid',
      [
        'tab'      => [ 'id' => 'miscinfo' ],
        'name'     => 'UUID',
        'options'  => 'LFAVCPDR',
        'css'      => [ 'postfix' => [ 'musician-uuid', 'clip-long-text', 'tiny-width', ], ],
        'sql'      => 'BIN2UUID($join_col_fqn)',
        'display|LVF' => ['popup' => 'data'],
        'sqlw'     => 'UUID2BIN($val_qas)',
        'maxlen'   => 32,
        'sort'     => true,
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'updated',
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
          'timeformat' => 'medium',
        ]));

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIANS_TABLE, 'created',
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Created"),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
          'timeformat' => 'medium',
        ]));

    /*
     *
     **************************************************************************
     *
     * SEPA information
     *
     */

    if ($monetary->count() > 0 || $this->membersProjectId == $this->projectId) {
      $sepaFieldGenerator($opts['fdd']);
    }

    /*
     *
     *
     **************************************************************************
     *
     * End field definitions.
     *
     */

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'ensureUserIdSlug' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateSanitizeParticipantFields' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateEnsureInstrumentationNumbers' ];
    // $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateCheckParticipationStatus' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'extractInstrumentRanking' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_UPDATE][PHPMyEdit::TRIGGER_BEFORE][] = [ $this, 'cleanupParticipantFields' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_DELETE][PHPMyEdit::TRIGGER_BEFORE][]  = [ $this, 'beforeDeleteTrigger' ];

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::SQL_QUERY_SELECT][PHPMyEdit::TRIGGER_DATA][] = function(&$pme, $op, $step, &$row) use ($opts) {

      if (!empty($row[$this->queryField('deleted')])
          || !empty($row[$this->joinQueryField(self::MUSICIANS_TABLE, 'deleted')])) {
        // disable misc-checkboxes for soft-deleted musicians in order to
        // avoid sending them bulk-email.
        $pme->options = str_replace('M', '', $opts['options']);
      } else {
        $pme->options = $opts['options'];
      }
      return true;
    };

    $opts[PHPMyEdit::OPT_TRIGGERS][PHPMyEdit::OPERATION_LIST][PHPMyEdit::TRIGGER_PRE][] = [ $this, 'totalFeesPreFilterTrigger' ];

    // The following are in order to aid the email form to extract
    // pre-selected musiancs from the form-data.
    $opts['cgi']['persist'][PersistentCGIKeys::PARTICIPATION_STATUS_FDD_INDEX] = $participationStatusFddIndex;
    $opts['cgi']['persist'][PersistentCGIKeys::INSTRUMENTS_FDD_INDEX] = $instrumentsFddIndex;

    $opts[PHPMyEdit::OPT_FILTERS]['AND'] = [
      '$table.project_id = ' . $this->projectId,
    ];
    if (!$this->showDisabled) {
      $opts[PHPMyEdit::OPT_FILTERS]['AND'][] = '$table.deleted IS NULL';
    }

    $projectInstrumentsJoin =  $this->joinTables[self::PROJECT_INSTRUMENTS_TABLE];
    $opts[PHPMyEdit::OPT_FILTERS]['AND'][] = '('
      . '$table.participation_status = "' . cat(ParticipationStatus::ASSOCIATED) . '"'
      . ' OR '
      . $projectInstrumentsJoin . '.instrument_id IN ("' . implode('","', $instrumentsFilter) . '")'
      . ')';

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * This is a phpMyEdit before-SOMETHING trigger.
   *
   * @param PHPMyEdit $pme The phpMyEdit instance.
   *
   * @param string $op The operation, 'insert', 'update' etc.
   *
   * @param string $step 'before' or 'after'.
   *
   * @param array $oldValues Self-explanatory.
   *
   * @param array $changed Set of changed fields, may be modified by the callback.
   *
   * @param null|array $newValues Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeDeleteTrigger(PHPMyEdit &$pme, string $op, string $step, array &$oldValues, array &$changed, array &$newValues):bool
  {
    $entity = $this->legacyRecordToEntity($pme->rec);

    $this->projectService->deleteProjectParticipant($entity, ParticipationContext::ASSOCIATES());

    $changed = []; // disable PME delete query

    return true; // but run further triggers if appropriate
  }
}
