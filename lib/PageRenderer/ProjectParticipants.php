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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use chillerlan\QRCode\QRCode;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Navigation;

/**Table generator for Instruments table. */
class ProjectParticipants extends PMETableViewBase
{
  const TEMPLATE = 'project-participants';
  const CSS_CLASS = self::TEMPLATE;
  const TABLE = 'ProjectParticipants';
  const MUSICIANS_TABLE = 'Musicians';
  const PROJECTS_TABLE = 'Projects';
  const INSTRUMENTS_TABLE = 'Instruments';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const MUSICIAN_INSTRUMENT_TABLE = 'MusicianInstrument';

  const JOIN_FIELD_NAME_SEPARATOR = ':';

  /**
   * @const list of join-tables which cannot be updated directly,
   * handled in the varous "beforeSOMETHING" trigger functions.
   */
  const JOIN_TABLES = [
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
      'identifier' => [
        'project_id' => 'project_id',
        'musician_id' => 'musician_id',
        'instrument_id' => false,
      ],
      'column' => 'instrument_id',
      'group_by' => true,
    ],
    self::MUSICIAN_INSTRUMENT_TABLE => [
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'instrument_id',
    ],
    // in order to get the participation in all projects
    self::TABLE => [
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'project_id' => false,
        'musician_id' => 'musician_id',
      ],
      'column' => 'project_id',
    ],
  ];

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var \OCA\CAFEVDB\ServicePhoneNumberService */
  private $phoneNumberService;

  /** @var \OCA\CAFEVDB\PageRenderer\Musicians */
  private $musiciansRenderer;

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ChangeLogService $changeLogService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , GeoCodingService $geoCodingService
    , ContactsService $contactsService
    , PhoneNumberService $phoneNumberService
    , Musicians $musiciansRenderer
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->musiciansRenderer = $musiciansRenderer;
  }

  public function cssClass() {
    return self::CSS_CLASS;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove the musician from %s?', [ $this->projectName ]);
    } else if ($this->viewOperation()) {
      return $this->l->t('Display of all stored data for the shown musician.');
    } else if ($this->changeOperation()) {
      return $this->l->t('Edit the data of the displayed musician.');
    }
    return $this->l->t("Instrumentation for Project `%s'", [ $this->projectName ]);
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

    if (empty($projectName) || empty($projectId)) {
      throw new \InvalidArgumentException('Project-id and/or -name must be given ('.$projectName.' / '.$projectId.').');
    }

    $opts['filters']['AND'] = [
      "PMEtable0.project_id = $projectId",
      "PMEtable0.disabled <= ".intval($this->showDisabled),
    ];

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = -1;

    $opts['tb'] = self::TABLE;

    //$opts['debug'] = true;

    $opts['cgi']['persist'] = [
      'template' => self::TEMPLATE,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.self::TEMPLATE,
      'recordsPerPage' => $recordsPerPage,
    ];

    // Name of field which is the unique key
    $opts['key'] = [ 'project_id' => 'int', 'musician_id' => 'int' ];

    // Sorting field(s)
    $opts['sort_field'] = [ 'sort_order', 'voice', '-section_leader', 'name', 'first_name' ];

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'CPVDFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

//  $export = Navigation::tableExportButton();
//  $opts['buttons'] = Navigation::prependTableButton($export, true);

//     // count number of finance fields
//     $extraFinancial = 0;
//     foreach ($userExtraFields as $field) {
//       $extraFinancial += $fieldTypes[$field['Type']]['Kind'] === 'surcharge';
//     }
//     if ($extraFinancial > 0 || $project['Anzahlung'] > 0) {
//       $useFinanceTab = true;
//       $financeTab = 'finance';
//     } else {
//       $useFinanceTab = false;
//       $financeTab = 'project';
//     }

    // Display special page elements
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
        'form'  => true,
        //'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs' => $this->tableTabs($userExtraFields, $useFinanceTab),
        'navigation' => 'VCD',
    ]);

    $projIdIdx = count($opts['fdd']);
    $opts['fdd']['project_id'] = array(
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Project-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
      );

    $musIdIdx = count($opts['fdd']);
    $opts['fdd']['musician_id'] = array(
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => $this->l->t('Musician-Id'),
      'input'    => 'R',
      'select'   => 'T',
      'options'  => 'LACPDV',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true,
    );

    foreach (self::JOIN_TABLES as $table => $joinInfo) {

      $joinIndex[$table] = count($opts['fdd']);
      $joinTable[$table] = 'PMEjoin'.$joinIndex[$table];
      $fqnColumn = $joinTable[$table].'.'.$joinInfo['column'];

      $group = false;
      $joinData = [];
      foreach ($joinInfo['identifier'] as $joinTableKey => $mainTableKey) {
        if (empty($mainTableKey)) {
          $group = true;
          continue;
        }
        $joinData[] = '$main_table.'.$mainTableKey.' = $join_table.'.$joinTableKey;
      }
      $fieldName = $this->joinTableMasterFieldName($table);
      $opts['fdd'][$fieldName] = [
        'tab' => 'all',
        'name' => $fieldName,
        'input' => 'H',
        'sql' => ($group
                  ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $join_col_fqn ASC)'
                  : '$join_col_fqn'),
        'options' => '',
        'sort' => true,
        'values' => [
          'table' => $table,
          'column' => $joinInfo['column'],
          'join' => implode(' AND ', $joinData),
        ],
      ];
      if (!empty($joinInfo['group_by'])) {
        $opts['groupby_fields'][] = $fieldName;
        // use simple field grouping for list operation
        $sql = $opts['fdd'][$fieldName]['sql'];
        $opts['fdd'][$fieldName]['sql|L'] = '$join_col_fqn';
      }
      $this->logInfo('JOIN '.print_r($opts['fdd'][$fieldName], true));
    }
    if (!empty($opts['groupby_fields'])) {
      $opts['groupby_fields'] = array_merge(array_keys($opts['key']), $opts['groupby_fields']);
      $this->logInfo('GROUP_BY '.print_r($opts['groupby_fields'], true));
    }

    $musiciansJoin = $joinTable[self::MUSICIANS_TABLE];

    $opts['fdd']['first_name'] = [
      'name'     => $this->l->t('First Name'),
      'tab'      => [ 'id' => 'tab-all' ], // display on all tabs, or just give -1
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['name'] = [
      'name'     => $this->l->t('Name'),
      'tab'      => [ 'id' => 'tab-all' ], // display on all tabs, or just give -1
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    if ($this->showDisabled) {
      $opts['fdd']['disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'tab'      => [ 'id' => 'tab-all' ], // display on all tabs, or just give -1
        'options' => $expertMode ? 'LAVCPDF' : 'LVCPDF',
        'input'    => $expertMode ? '' : 'R',
        'select'   => 'C',
        'maxlen'   => 1,
        'sort'     => true,
        'escape'   => false,
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /* '&#10004;' */ ],
        'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
        'tooltip'  => $this->toolTipsService['musician-disabled'],
        'css'      => [ 'postfix' => ' musician-disabled' ],
      ];
    }

    $projectInstrumentJoin = $joinTable[self::PROJECT_INSTRUMENTS_TABLE];
    $fieldIndex = $projectInstrumentNameIndex = count($opts['fdd']);
    $projectInstrumentNameJoin = 'PMEjoin'.$projectInstrumentNameIndex;
    $opts['fdd']['project_instrument'] = [
      'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
      'name'        => $this->l->t('Project Instrument'),
      'css'         => ['postfix' => ' project-instruments tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'input'       => 'S', // skip
      'sort'        => true,
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'S',
      'select'      => 'M',
      //'filter'      => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'instrument',
        'orderby'     => '$table.sort_order ASC',
        'join'        => '$join_col_fqn = '.$projectInstrumentJoin.'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];

    $opts['fdd']['sort_order'] = [
      'tab'         => [ 'id' => [ 'instrumentation', 'project' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'input'       => 'HRS',
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $projectInstrumentNameIndex ],
      ],
    ];

    $opts['fdd']['registration'] = [
      'name|LF' => ' &#10004;',
      'name|CAPDV' => $this->l->t("Registration"),
      'tab' => [ 'id' => [ 'project', 'instrumentation' ] ],
      'options'  => 'LAVCPDF',
      'select' => 'C',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'values2|CAP' => [ '1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /* '&#10004;' */ ],
      'values2|LVDF' => [ '0' => '&nbsp;', '1' => '&#10004;' ],
      'tooltip' => $this->l->t("Set to `%s' in order to mark participants who passed a personally signed registration form to us.",
                               [ "&#10004;" ]),
      'display|LF' => [
        'popup' => function($data) {
          return $this->toolTipsService['registration-mark'];
        },
      ],
      'css'      => [ 'postfix' => ' registration tooltip-top' ],
    ];

    $fieldIndex = $musicianInstrumentsIndex = count($opts['fdd']);
    $opts['fdd']['musician_instruments'] = [
      'name'        => $this->l->t('All Instruments'),
      'tab'         => [ 'id' => [ 'musician', 'instrumentation' ] ],
      'css'         => ['postfix' => ' musician-instruments tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'input'       => 'S', // skip
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'S',
      'select'      => 'M',
      'filter'      => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'instrument',
        'orderby'     => '$table.sort_order ASC',
        //        'groups'      => 'Familie',
        'join'        => '$join_col_fqn = '.$joinTable[self::MUSICIAN_INSTRUMENT_TABLE].'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];

    $opts['fdd']['musician_instruments']['values|ACP'] = array_merge(
      $opts['fdd']['musician_instruments']['values'],
      [ 'filters' => '$table.disabled = 0' ]);

    /*
     *
     **************************************************************************
     *
     * several fields from Musicians table
     *
     */

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $opts['fdd']['member_status'] = [
      'name'    => $this->l->t('Member Status'),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => ['postfix' => ' memberstatus tooltip-wide'],
      'values2' => $this->memberStatusNames,
      'tooltip' => $this->toolTipsService['member-status'],
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['remarks'] = [
      'name' => $this->l->t("Remarks")."\n(".$projectName.")",
      'tooltip' => $this->toolTipsService['project-remarks'],
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => [ 'postfix' => ' remarks tooltip-left' ],
      'textarea' => [
        'css' => 'wysiwyg-editor',
        'rows' => 5,
        'cols' => 50,
      ],
      'display|LF' => [ 'popup' => 'data' ],
      'escape' => false,
      'sort'   => true,
      'tab'    => [ 'id' => 'project' ]
    ];

    $opts['fdd']['all_projects'] = [
      'tab' => ['id' => 'musician'],
      'input' => 'VR',
      'options' => 'LFVC',
      'select' => 'M',
      'name' => $this->l->t('Projects'),
      'sort' => true,
      'css'      => ['postfix' => ' projects tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by SEPARATOR \',\')',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table' => 'Projects',
        'column' => 'id',
        'description' => 'name',
        'orderby' => '$table.year ASC, $table.name ASC',
        'groups' => 'year',
        'join' => '$join_col_fqn = '.$joinTable[self::TABLE].'.project_id'
      ],
      // @TODO check whether this is still needed or 'groups' => 'year' is just fine.
      //'values2' => $projects,
      //'valueGroups' => $groupedProjects
    ];

    $opts['fdd']['email'] = $this->defaultFDD['email'];
    $opts['fdd']['email']['tab'] = ['id' => 'musician'];
    $opts['fdd']['email']['values'] = [
      'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
    ];
    $opts['fdd']['email']['input'] = 'S';

    $opts['fdd']['mobile_phone'] = [
      'name'     => $this->l->t('Mobile Phone'),
      'tab'      => [ 'id' => 'musician' ],
      'css'      => [ 'postfix' => ' phone-number' ],
      'input'    => 'S',
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'nowrap'   => true,
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['fixed_line_phone'] = [
      'name'     => $this->l->t('Fixed Line Phone'),
      'tab'      => [ 'id' => 'musician' ],
      'css'      => [ 'postfix' => ' phone-number' ],
      'input'    => 'S',
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'nowrap'   => true,
      'select'   => 'T',
      'maxlen'   => 384,
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['street'] = [
      'name'     => $this->l->t('Street'),
      'tab'      => [ 'id' => 'musician' ],
      'css'      => ['postfix' => ' musician-address street'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
      'input'    => 'S',
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['postal_code'] = [
      'name'     => $this->l->t('Postal Code'),
      'tab'      => [ 'id' => 'musician' ],
      'css'      => ['postfix' => ' musician-address postal-code'],
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true,
      'input'    => 'S',
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['city'] = [
      'name'     => $this->l->t('City'),
      'tab'      => [ 'id' => 'musician' ],
      'css'      => ['postfix' => ' musician-address city'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
      'input'    => 'S',
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $opts['fdd']['country'] = [
      'name'     => $this->l->t('Country'),
      'tab'      => [ 'id' => 'musician' ],
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => $this->getConfigValue('streetAddressCountry'),
      'css'      => ['postfix' => ' musician-address country chosen-dropup'],
      'sort'     => true,
      'input'    => 'S',
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
      'values2'     => $countries,
      'valueGroups' => $countryGroups,
    ];

    $opts['fdd']['birthday'] = $this->defaultFDD['birthday'];
    $opts['fdd']['birthday']['tab'] = [ 'id' => 'musician' ];
    $opts['fdd']['birthday']['values'] = [
      'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
    ];

    $opts['fdd']['musician_remarks'] = [
      'tab'      => ['id' => 'musician'],
      'name'     => $this->l->t('Remarks'),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' remarks tooltip-top'],
      'textarea' => [
        'css' => 'wysiwyg-editor',
        'rows' => 5,
        'cols' => 50,
      ],
      'display|LF' => ['popup' => 'data'],
      'escape' => false,
      'sort'     => true,
      'values' => [
        'column' => 'remarks',
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['language'] = [
      'tab'      => ['id' => 'musician'],
      'name'     => $this->l->t('Language'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
      'values2'  => $this->findAvailableLanguages(),
    ];

    $opts['fdd']['photo'] = [
      'tab'      => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => $this->l->t('Photo'),
      'select' => 'T',
      'options' => 'APVCD',
      'sql' => '`PMEtable0`.`musician_id`', // @TODO: needed?
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        $stampIdx = array_search('Updated', $fds);
        $stamp = strtotime($row['qf'.$stampIdx]);
        return $this->musiciansRenderer->photoImageLink($musicianId, $action, $stamp);
      },
      'css' => ['postfix' => ' photo'],
      'default' => '',
      'sort' => false
    ];

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '`PMEtable0`.`musician_id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        switch($action) {
        case 'change':
        case 'display':
          $data = [];
          foreach($fds as $idx => $label) {
            $data[$label] = $row['qf'.$idx];
          }
          $musician = new Entities\Musician();
          foreach ($data as $key => $value) {
            try {
              $musician[$key] = $value;
            } catch (\Throwable $t) {
              // Don't care, we know virtual stuff is not there
              // $this->logException($t);
            }
          }
          $vcard = $this->contactsService->export($musician);
          unset($vcard->PHOTO); // too much information
          //$this->logDebug(print_r($vcard->serialize(), true));
          return '<img height="231" width="231" src="'.(new QRCode)->render($vcard->serialize()).'"></img>';
        default:
          return '';
        }
      },
      'default' => '',
      'sort' => false
    ];

    $opts['fdd']['uuid'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => 'UUID',
      'options'  => 'LAVCPDR',
      'css'      => ['postfix' => ' musician-uuid clip-long-text tiny-width'],
      'sql'      => 'BIN2UUID($join_col_fqn)',
      'display|LVF' => ['popup' => 'data'],
      'sqlw'     => 'UUID2BIN($val_qas)',
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => true,
      'values' => [
        'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
      ],
    ];

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          "default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR',
          'values' => [
            'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
          ],
        ]
      );

    //////// END Field definitions

    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'beforeUpdateTrigger' ];

//     $opts['triggers']['update']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeUpdateTrigger';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    ///@@@@@@@@@@@@@@@@@@@@@

//     $opts['fdd']['ProjectInstrumentKey'] = array(
//       'name'   => $this->l->t('Project Instrument'),
//       'sql'    => 'GROUP_CONCAT(DISTINCT ProjectInstrumentKey ORDER BY PMEtable0.sort_order ASC)',
//       'select' => 'N',
//       'input'  => 'HR'
//       );

//     $prInstIdx = count($opts['fdd']);
//     $opts['fdd']['ProjectInstrumentId'] = array(
//       'tab'         => array('id' => array('instrumentation', 'project')),
//       'name'        => $this->l->t('Project Instrument'),
//       'select'      => 'M',
//       'sql'         => 'GROUP_CONCAT(DISTINCT ProjectInstrumentId ORDER BY PMEtable0.sort_order ASC)',
//       'filter' => 'having',
//       'maxlen'      => 36,
//       'css'         => array('postfix' => ' project-instrument'),
//       'sort'        => true,
//       'values|VDPC' => array(
//         'table'       => 'Instrumente',
//         'column'      => 'Id',
//         'orderby'     => '$table.sort_order',
//         'description' => 'Instrument',
//         'groups'      => 'Familie',
//         /* This rather fancy fillter masks out all instruments
//          * currently not registerd with the given musician, but allows
//          * for the currently active instrument.
//          */
//         'filters' => ("FIND_IN_SET(`Id`,
//   CONCAT_WS(',',(SELECT GROUP_CONCAT(DISTINCT `ProjectInstrumentId`) FROM `\$main_table` WHERE \$record_id = `\$main_table`.`Id` GROUP BY \$main_table.Id),
//                 (SELECT DISTINCT `MusicianInstrumentId` FROM `\$main_table`
//                           WHERE \$record_id = `\$main_table`.`Id`)))"),
//         'join' => '$join_table.Id = $main_table.ProjectInstrumentId'
//         ),
//       'values|LF' => array(
//         'table'       => 'Instrumente',
//         'column'      => 'Id',
//         'orderby'     => '$table.sort_order',
//         'description' => 'Instrument',
//         'groups'      => 'Familie',
//         'filters'     => ("`Id` IN ".
//                           "(SELECT DISTINCT `ProjectInstrumentId` FROM `\$main_table` WHERE 1)"),
//         'join'        => '$join_table.Id = $main_table.ProjectInstrumentId'
//         ),
//       );

//     $opts['fdd']['ProjectInstrument'] = array(
//       'name'     => $this->l->t('Project Instrument'),
//       'sql'      => 'GROUP_CONCAT(DISTINCT PMEtable0.`ProjectInstrument` ORDER BY PMEtable0.`sort_order` ASC)',
//       'input'    => 'HR',
//       );

//     $voices = [ '' => '&nbsp;' ] + array_combine(range(1, 8), range(1, 8));
//     $opts['fdd']['Voice'] = array(
//       'name'     => $this->l->t('Voice'),
//       'css'      => [ 'postfix' => ' allow-empty no-search instrument-voice' ],
//       'sql|VD' => "GROUP_CONCAT(DISTINCT CONCAT(ProjectInstrument,' ', Voice) ORDER BY PMEtable0.sort_order ASC)",
//       'sql|CP' => "GROUP_CONCAT(DISTINCT CONCAT(ProjectInstrumentId,':', Voice) ORDER BY PMEtable0.sort_order ASC)",
//       'select'  => 'M',
//       'values|CP' => [
//         'table' => "SELECT
//   pi.InstrumentationId,
//   pi.InstrumentId,
//   i.Instrument,
//   i.sort_order,
//   n.N,
//   CONCAT(pi.InstrumentId,':', n.N) AS Value
// FROM ".self::PROJECT_INSTRUMENTS." pi
// LEFT JOIN Instrumente i
//   ON i.Id = pi.InstrumentId
// JOIN numbers n
//   ON n.N <= 8 AND n.N >= 1
// WHERE
//   pi.Projectid = $projectId
// ORDER BY
//   i.sort_order ASC, n.N ASC",
//         'column' => 'Value',
//         'description' => [
//           'columns' => [ 'Instrument', 'N' ],
//           'divs' => ' '
//           ],

//         'orderby' => '$table.sort_order, $table.N',
//         'groups'  => '$table.Instrument',
//         'filters' => 'InstrumentationId = $record_id',
//         'join' => false, //'$join_table.InstrumentationId = $main_table.Id',
//         ],
//       'values2|LF' => $voices,
//       'maxlen'  => '8',
//       'sort'    => true,
//       'escape'  => false,
//       'tab'     => array('id' => 'instrumentation')
//       );

//     $opts['fdd']['SectionLeader'] = array(
//       'name|LF' => ' &alpha;',
//       'name|CAPVD' => $this->l->t("Section Leader"),
//       'tab' => array('id' => 'instrumentation'),
//       'sql|CAPDV' => "GROUP_CONCAT(
//   IF(PMEtable0.`SectionLeader` = 1, PMEtable0.`ProjectInstrumentId`, 0) ORDER BY PMEtable0.`sort_order` ASC)",
//       'values|CAPDV' => [
//         'table' => "SELECT
//   `Id` AS `InstrumentationId`,
//   `ProjectInstrumentId` AS `InstrumentId`,
//   `ProjectInstrument` AS `Instrument`,
//   `sort_order`
// FROM $mainTable",
//         'column' => "InstrumentId",
//         'description' => "Instrument",
//         'filters' => '$table.InstrumentationId = $record_id AND $table.InstrumentId IS NOT NULL',
//         'orderby' => '$table.sort_order',
//         ],
//       'options'  => 'LAVCPDF',
//       'select' => 'C',
//       'maxlen' => '1',
//       'sort' => true,
//       'escape' => false,
//       'values2|LF' => array('0' => '&nbsp;', '1' => '&alpha;'),
//       'tooltip' => $this->l->t("Set to `%s' in order to mark the section leader",
//                         array("&alpha;")),
//       'display|LF' => array('popup' => function($data) {
//           return Config::ToolTips('section-leader-mark');
//         }),
//       'css'      => array('postfix' => ' section-leader tooltip-top'),
//       );

//     $opts['fdd']['Anmeldung'] = array(
//       'name|LF' => ' &#10004;',
//       'name|CAPDV' => $this->l->t("Registration"),
//       'tab' => array('id' => array('project', 'instrumentation')),
//       'options'  => 'LAVCPDF',
//       'select' => 'C',
//       'maxlen' => '1',
//       'sort' => true,
//       'escape' => false,
//       'values2|CAP' => array('1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /* '&#10004;' */),
//       'values2|LVDF' => array('0' => '&nbsp;', '1' => '&#10004;'),
//       'tooltip' => $this->l->t("Set to `%s' in order to mark participants who passed a personally signed registration form to us.",
//                         array("&#10004;")),
//       'display|LF' => array('popup' => function($data) {
//           return Config::ToolTips('registration-mark');
//         }),
//       'css'      => array('postfix' => ' registration tooltip-top'),
//       );

//     $opts['fdd']['MusicianInstrumentKey'] = array(
//       'name'   => $this->l->t('Musican Instrument'),
//       'select' => 'T',
//       'input'  => 'HR'
//       );

//     $opts['fdd']['MusicianInstrumentId'] = array(
//       'tab'         => array('id' => array('musician', 'instrumentation')),
//       'name'        => $this->l->t('All Instruments'),
//       'css'         => array('postfix' => ' musician-instruments tooltip-top'),
//       'display|LF'  => array('popup' => 'data'),
//       'input'       => 'S', // needs to be handled separately
//       //'options'   => 'AVCPD',
//       'select'      => 'M',
//       'maxlen'      => 136,
//       'sort'        => true,
//       'values'      => [
//         'table'       => 'Instrumente',
//         'column'      => 'Id',
//         'description' => 'Instrument',
//         'orderby'     => 'sort_order',
//         'groups'      => 'Familie',
//         ],
//       );
//     $opts['fdd']['MusicianInstrumentId']['values|CP'] = array_merge(
//       $opts['fdd']['MusicianInstrumentId']['values'],
//       [ 'filters' => '$table.Disabled = 0' ]);
//     $opts['fdd']['MusicianInstrumentId']['values|LF'] = array_merge(
//       $opts['fdd']['MusicianInstrumentId']['values'],
//       [ 'filters' =>
//         "`Id` IN (SELECT DISTINCT `MusicianInstrumentId` FROM `\$main_table` WHERE 1)" ]);

//     $opts['fdd']['sort_order'] = array(
//       'name'     => 'Orchester sort_order',
//       'select'   => 'N',
//       'input'    => 'HR',
//       'maxlen'   => 8,
//       'default'  => '0',
//       'sort'     => true
//       );

//     $opts['fdd']['MemberStatus'] = array(
//       'name'     => strval($this->l->t('Member Status')),
//       'tab'      => array('id' => array('musician')), // multiple tabs are legal
//       'select'   => 'D',
//       'maxlen'   => 128,
//       'sort'     => true,
//       'css'     => array('postfix' => ' memberstatus tooltip-wide'),
//       'values2'  => $this->memberStatusNames,
//       'tooltip' => config::toolTips('member-status')
//       );

//     $feeIdx = count($opts['fdd']);
//     $opts['fdd']['Unkostenbeitrag'] = Config::$opts['money'];
//     $opts['fdd']['Unkostenbeitrag']['name'] = "Unkostenbeitrag\n(Gagen negativ)";
//     $opts['fdd']['Unkostenbeitrag']['default'] = $project['Unkostenbeitrag'];
//     $opts['fdd']['Unkostenbeitrag']['css']['postfix'] .= ' fee';
//     $opts['fdd']['Unkostenbeitrag']['tab'] = array('id' => $financeTab);

//     if ($project['Anzahlung'] > 0) {
//       // only include if configured in project
//       $opts['fdd']['Anzahlung'] = Config::$opts['money'];
//       $opts['fdd']['Anzahlung']['name'] = "Anzahlung";
//       $opts['fdd']['Anzahlung']['default'] = $project['Anzahlung'];
//       $opts['fdd']['Anzahlung']['css']['postfix'] .= ' deposit';
//       $opts['fdd']['Anzahlung']['tab'] = array('id' => $financeTab);
//     }

//     $needDebitMandates = Projects::needDebitMandates($projectId);
//     $paymentStatusValues2 = array(
//       'outstanding' => '&empty;',
//       'awaitingdepositdebit' => '&#9972;',
//       'deposited' => '&#9684;',
//       'awaitingdebit' => '&#9951;',
//       'payed' => '&#10004;'
//       );

//     if (Projects::needDebitMandates($projectId)) {

//       $memberTableId = Config::getValue('memberTableId');
//       $monetary = ProjectExtra::monetaryFields($userExtraFields, $fieldTypes);

//       $amountPaidIdx = count($opts['fdd']);
//       $opts['fdd']['AmountPaid'] = array(
//         'input' => 'HR',
//         );

//       $paidCurrentYearIdx = count($opts['fdd']);
//       $opts['fdd']['PaidCurrentYear'] = array(
//         'input' => 'HR',
//         );

//       $opts['fdd']['TotalProjectFees'] = array(
//         'tab'      => array('id' => $financeTab),
//         'name'     => $this->l->t('Total Charges'),
//         'css'      => array('postfix' => ' total-project-fees money'),
//         'sort'    => false,
//         'options' => 'VDLF', // wrong in change mode
//         'input' => 'VR',
//         'sql' => '`PMEtable0`.`Unkostenbeitrag`',
//         'php' => function($amount, $op, $field, $fds, $fdd, $row, $recordId)
//         use ($monetary, $amountPaidIdx, $paidCurrentYearIdx, $projectId, $memberTableId, $musIdIdx)
//         {
//           foreach($fds as $key => $label) {
//             if (!isset($monetary[$label])) {
//               continue;
//             }
//             $qf    = "qf{$key}";
//             $qfidx = $qf.'_idx';
//             if (isset($row[$qfidx])) {
//               $value = $row[$qfidx];
//             } else {
//               $value = $row[$qf];
//             }
//             if (empty($value)) {
//               continue;
//             }
//             $field   = $monetary[$label];
//             $allowed = $field['AllowedValues'];
//             $type    = $field['Type'];
//             $amount += self::extraFieldSurcharge($value, $allowed, $type['Multiplicity']);
//           }

//           if ($projectId === $memberTableId) {
//             $amount += InstrumentInsurance::annualFee($row['qf'.$musIdIdx]);
//             $paid = $row['qf'.$paidCurrentYearIdx];
//           } else {
//             $paid = $row['qf'.$amountPaidIdx];
//           }

//           // display as TOTAL/PAID/REMAINDER
//           $rest = $amount - $paid;

//           $amount = Util::moneyValue($amount);
//           $paid = Util::moneyValue($paid);
//           $rest = Util::moneyValue($rest);
//           return ('<span class="totals finance-state">'.$amount.'</span>'
//                   .'<span class="received finance-state">'.$paid.'</span>'
//                   .'<span class="outstanding finance-state">'.$rest.'</span>');
//         },
//         'tooltip'  => Config::toolTips('project-total-fee-summary'),
//         'display|LFVD' => array('popup' => 'tooltip'),
//         );

//       $opts['fdd']['Lastschrift'] = array(
//         'tab'      => array('id' => $financeTab),
//         'name'     => $this->l->t('Direct Debit'),
//         'css'      => array('postfix' => ' direct-debit-allowed'),
//         'values2|CAP' => array('1' => '&nbsp;&nbsp;&nbsp;&nbsp;' /*'&#10004;'*/),
//         'values2|LVDF' => array('0' => '&nbsp;',
//                                 '1' => '&#10004;'),
//         'escape' => false,
//         //'values2|CAP' => array(1 => ''),
//         //'values2|LVFD' => array(1 => $this->l->t('true'), 0 => $this->l->t('false')),
//         'default'  => '',
//         'select'   => 'O',
//         'sort'     => true,
//         'tooltip'  => Config::toolTips('project-direct-debit-allowed'),
//         'display|LF' => array('popup' => 'tooltip'),
//         );

//       $debitJoinCondition =
//         '('.
//         '$join_table.projectId = '.$projectId.
//         ' OR '.
//         '$join_table.projectId = '.$memberTableId.
//         ')'.
//         ' AND $join_table.musicianId = $main_table.MusikerId'.
//         ' AND $join_table.active = 1';

//       // One virtual field in order to be able to manage SEPA debit
//       // mandates. Note that in rare circumstances there may be two
//       // debit mandates: one for general and one for the project. We
//       // fetch both with the same sort-order and leave it to the calling
//       // code to do THE RIGHT THING (tm).
//       $mandateIdx = count($opts['fdd']);
//       $mandateAlias = "`PMEjoin".$mandateIdx."`";
//       $opts['fdd']['SepaDebitMandate'] = array(
//         'name' => $this->l->t('SEPA Debit Mandate'),
//         'input' => 'VR',
//         'tab' => array('id' => $financeTab),
//         'select' => 'M',
//         'options' => 'LFACPDV',
//         'sql' => "GROUP_CONCAT(DISTINCT ".$mandateAlias.".`mandateReference`
//   ORDER BY ".$mandateAlias.".`projectId` DESC)",
//         'values' => array(
//           'table' => 'SepaDebitMandates',
//           'column' => 'mandateReference',
//           'join' => $debitJoinCondition,
//           'description' => 'mandateReference'
//           ),
//         'nowrap' => true,
//         'sort' => true,
//         'php' => function($mandates, $action, $k, $fds, $fdd, $row, $recordId)
//         use ($musIdIdx, $musFirstNameIdx, $musLastNameIdx)
//         {
//           if ($this->pme_bare) {
//             return $mandates;
//           }
//           $projectId = $this->projectId;
//           $projectName = $this->projectName;
//           // can be multi-valued (i.e.: 2 for member table and project table)
//           $mandateProjects = $row['qf'.($k+1)];
//           $mandates = Util::explode(',', $mandates);
//           $mandateProjects = Util::explode(',', $mandateProjects);
//           if (count($mandates) !== count($mandateProjects)) {
//             throw new \RuntimeException(
//               $this->l->t('Data inconsistency, mandates: "%s", projects: "%s"',
//                    array(implode(',', $mandates),
//                          implode(',', $mandateProjects)))
//               );
//           }

//           // Careful: this changes when rearranging the sort-order of the display
//           $musicianId        = $row['qf'.$musIdIdx];
//           $musicianFirstName = $row['qf'.$musFirstNameIdx];
//           $musicianLastName  = $row['qf'.$musLastNameIdx];
//           $musician = $musicianLastName.', '.$musicianFirstName;

//           $html = array();
//           foreach($mandates as $key => $mandate) {
//             if (empty($mandate)) {
//               continue;
//             }
//             $expired = Finance::mandateIsExpired($mandate);
//             $mandateProject = $mandateProjects[$key];
//             if ($mandateProject === $projectId) {
//               $html[] = self::sepaDebitMandateButton(
//                 $mandate, $expired,
//                 $musicianId, $musician,
//                 $projectId, $projectName);
//             } else {
//               $mandateProjectName = Projects::fetchName($mandateProject);
//               $html[] = self::sepaDebitMandateButton(
//                 $mandate, $expired,
//                 $musicianId, $musician,
//                 $projectId, $projectName,
//                 $mandateProject, $mandateProjectName);
//             }
//           }
//           if (empty($html)) {
//             // Empty default knob
//             $html = array(self::sepaDebitMandateButton(
//                             $this->l->t("SEPA Debit Mandate"), false,
//                             $musicianId, $musician,
//                             $projectId, $projectName));
//           }
//           return implode("\n", $html);
//         },
//         );

//       $mandateProjectIdx = count($opts['fdd']);
//       $opts['fdd']['DebitMandateProject'] = array(
//         'input' => 'VHR',
//         'name' => 'internal data',
//         'options' => 'H',
//         'select' => 'T',
//         'sql' => "GROUP_CONCAT(DISTINCT ".$mandateAlias.".`projectId`
//   ORDER BY ".$mandateAlias.".`projectId` DESC)",
//         );
//     }

//     // Generate input fields for the extra columns
//     foreach ($userExtraFields as $field) {
//       $fieldName = $name = $field['Name'];
//       $fieldId   = $field['Id'];

//       $type = $fieldTypes[$field['Type']];

//       if ($type['Kind'] === 'surcharge') {
//         $tab = array('id' => $financeTab);
//       } else {
//         $tab = array('id' => 'project');
//       }
//       if (!empty($field['Tab'])) {
//         $tabId = self::tableTabId($field['Tab']);
//         $tab = array('id' => $tabId);
//       }

//       $curColIdx = count($opts['fdd']); // current column
//       $opts['fdd'][$name] = array(
//         'name'     => $name, // ."\n(".$projectName.")",
//         'css'      => array('postfix' => ' extra-field'),
//         'tab'      => $tab,
//         'select'   => 'T',
//         'maxlen'   => 65535,
//         'textarea' => array('css' => '',
//                             'rows' => 2,
//                             'cols' => 32),
//         'display|LF' => array('popup' => 'data'),
//         'default'  => $field['DefaultValue'],
//         'escape'   => false,
//         'sort'     => true
//         );

//       $fdd = &$opts['fdd'][$fieldName];
//       if (!empty($field['ToolTip'])) {
//         $opts['fdd'][$name]['tooltip'] = $field['ToolTip'];
//       }

//       $allowed = ProjectExtra::explodeAllowedValues($field['AllowedValues'], false, true);
//       $values2     = array();
//       $valueTitles = array();
//       $valueData   = array();
//       foreach($allowed as $idx => $value) {
//         $key = $value['key'];
//         if (empty($key)) {
//           continue;
//         }
//         if ($value['flags'] === 'deleted') {
//           continue;
//         }
//         $values2[$key] = $value['label'];
//         $valueTitles[$key] = $value['tooltip'];
//         $valueData[$key] = $value['data'];
//       }

//       switch ($type['Name']) {
//       case 'Date':
//         $fdd['maxlen'] = 10;
//         $fdd['datemask'] = 'd.m.Y';
//         $fdd['css']['postfix'] .= ' date';
//         $fdd['maxlen'] = 10;
//         unset($fdd['textarea']);
//         break;
//       case 'HTML':
//         $fdd['textarea'] = array('css' => 'wysiwyg-editor',
//                                  'rows' => 5,
//                                  'cols' => 50);
//         $fdd['css']['postfix'] .= ' hide-subsequent-lines';
//         $fdd['display|LF'] = array('popup' => 'data');
//         $fdd['escape'] = false;
//         break;
//       case 'Money':
//         $fdd = Config::$opts['money'];
//         $fdd['tab'] = $tab;
//         $fdd['name'] = $name."\n(".$projectName.")";
//         $fdd['css']['postfix'] .= ' extra-field';
//         $fdd['default'] = $field['DefaultValue'];
//         break;
//       case 'Integer':
//         $fdd['select'] = 'N';
//         $fdd['mask'] = '%d';
//         unset($fdd['textarea']);
//         break;
//       case 'Float':
//         $fdd['select'] = 'N';
//         $fdd['mask'] = '%g';
//         unset($fdd['textarea']);
//         break;
//       case 'Boolean':
//         reset($values2); $key = key($values2);
//         $fdd['values2|CAP'] = array($key => ''); // empty label for simple checkbox
//         $fdd['values2|LVDF'] = array(0 => $this->l->t('false'),
//                                      $key => $this->l->t('true'));
//         $fdd['select'] = 'O';
//         $fdd['default'] = (string)!!(int)$field['DefaultValue'];
//         $fdd['css']['postfix'] .= ' boolean single-valued';
//         unset($fdd['textarea']);
//         break;
//       case 'Enum':
//       case 'Set':
//         $fdd['values2'] = $values2;
//         $fdd['valueTitles'] = $valueTitles;
//         $fdd['valueData'] = $valueData;
//         if ($type['Multiplicity'] == 'parallel') {
//           $fdd['css']['postfix'] .= ' set';
//           $fdd['select'] = 'M';
//         } else {
//           $fdd['css']['postfix'] .= ' enumeration allow-empty';
//           $fdd['select'] = 'D';
//         }
//         unset($fdd['textarea']);
//         break;
//       case 'SurchargeOption':
//         // just use the amount to pay as label
//         reset($values2); $key = key($values2);
//         $money = Util::moneyValue(reset($valueData));
//         $fdd['values2|CAP'] = array($key => ''); // empty label for simple checkbox
//         $fdd['values2|LVDF'] = array(0 => '-,--',
//                                      $key => $money);
//         $fdd['select'] = 'C';
//         $fdd['default'] = (string)!!(int)$field['DefaultValue'];
//         $fdd['css']['postfix'] .= ' boolean money surcharge single-valued';
//         $fdd['name|LFVD'] = $fdd['name'];
//         $fdd['name'] = '<span class="allowed-option-name money">'.Util::htmlEscape($fdd['name']).'</span><span class="allowed-option-value money">'.$money.'</span>';
//         unset($fdd['textarea']);
//         break;
//       case 'SurchargeEnum':
//       case 'SurchargeSet':
//         foreach($values2 as $key => $value) {
//           $money = Util::moneyValue($valueData[$key], Config::$locale);
//           $value = Util::htmlEscape($value);
//           $value = '<span class="allowed-option-name money multiple-choice">'.$value.'</span>';
//           $money = '<span class="allowed-option-value money">'.'&nbsp;'.$money.'</span>';
//           $values2[$key] = $value.$money;
//         }
//         $fdd['values2'] = $values2;
//         $fdd['values2glue'] = "<br/>";
//         $fdd['valueTitles'] = $valueTitles;
//         $fdd['valueData'] = $valueData;
//         $fdd['escape'] = false;
//         $fdd['display|LF'] = array('popup' => 'data');
//         unset($fdd['textarea']);
//         if ($type['Multiplicity'] == 'parallel') {
//           $fdd['css']['postfix'] .= ' surcharge set hide-subsequent-lines';
//           $fdd['select'] = 'M';
//         } else {
//           $fdd['css']['postfix'] .= ' surcharge enum money allow-empty';
//           $fdd['select'] = 'D';
//         }
//         break;
//       case 'SimpleGroup':
//       case 'SurchargeGroup':
//         // keep the original value as hidden input field and generate
//         // a new group-definition field as yet another column
//         $opts['fdd'][$fieldName.'Group'] = $fdd;
//         $fdd['input'] = 'H';
//         $fdd = &$opts['fdd'][$fieldName.'Group'];
//         $curColIdx++;

//         // define the group stuff
//         $max = $allowed[0]['limit']; // ATM, may change
//         $fdd = array_merge(
//           $fdd, [
//             'select' => 'M',
//             'sql' => "GROUP_CONCAT(DISTINCT PMEjoin{$curColIdx}.InstrumentationId)",
//             'display' => [ 'popup' => 'data' ],
//             'colattrs' => [ 'data-groups' => json_encode([ 'Limit' => $max ]), ],
//             'filter' => 'having',
//             'values' => [
//               'table' => "SELECT
//   b.Id AS InstrumentationId,
//   CONCAT_WS(' ', m.Vorname, m.Name) AS Name,
//   m.Name AS LastName, m.Vorname AS FirstName,
//   fd.FieldValue AS GroupId
// FROM Besetzungen b
// LEFT JOIN Musiker AS m
//   ON b.MusikerId = m.Id
// LEFT JOIN ProjectExtraFieldsData fd
//   ON b.Id = fd.BesetzungenId AND fd.FieldId = $fieldId
// WHERE b.ProjektId = $projectId",
//               'column' => 'InstrumentationId',
//               'description' => 'Name',
//               'groups' => "CONCAT('".$fieldName." ',\$table.GroupId)",
//               'data' => "CONCAT('{\"Limit\":".$max.",\"GroupId\":\"',IFNULL(\$table.GroupId,-1),'\"}')",
//               'orderby' => '$table.GroupId ASC, $table.LastName ASC, $table.FirstName ASC',
//               'join' => '$main_table.`'.$fieldName.'` = $join_table.GroupId',
//               ],
//             'valueGroups' => [ -1 => $this->l->t('without group') ],
//             ]);
//         $fdd['css']['postfix'] .= ' groupofpeople single-valued';

//         if ($type['Name'] === 'SurchargeGroup') {
//           $fdd['css']['postfix'] .= ' surcharge';
//           $money = Util::moneyValue(reset($valueData));
//           $fdd['name|LFVD'] = $fdd['name'];
//           $fdd['name'] = '<span class="allowed-option-name money">'.Util::htmlEscape($fdd['name']).'</span><span class="allowed-option-value money">'.$money.'</span>';
//           $fdd['display|LFVD'] = array_merge(
//             $fdd['display'],
//             [
//               'prefix' => '<span class="allowed-option-name clip-long-text group">',
//               'postfix' => ('</span><span class="allowed-option-value money">'.
//                             $money.
//                             '</span>'),
//               ]);
//         }

//         // in filter mode mask out all non-group-members
//         $fdd['values|LF'] = array_merge(
//           $fdd['values'],
//           [ 'filters' => '$table.GroupId IS NOT NULL' ]);

//         // after all this tweaking, we still need the real group id
//         $opts['fdd'][$fieldName.'GroupId'] = [
//           'name'     => $this->l->t('%s Group Id', array($name)),
//           'css'      => [ 'postfix' => ' groupofpeople-id' ],
//           'input|LFVD' => 'VRH',
//           'input'      => 'SRH',
//           'select'   => 'T',
//           'sql'      => 'PMEtable0.`'.$fieldName.'`',
//           ];
//         break;
//       case 'PredefinedGroups':
//       case 'SurchargeGroups':
//         // keep the original value as hidden input field and generate
//         // a new group-definition field as yet another column
//         $opts['fdd'][$fieldName.'Group'] = $fdd;
//         $fdd['input'] = 'H';
//         $fdd = &$opts['fdd'][$fieldName.'Group'];
//         $curColIdx++;

//         // define the group stuff
//         $groupValues2   = $values2;
//         $groupValueData = $valueData;
//         $values2 = [];
//         $valueGroups = [ -1 => $this->l->t('without group') ];
//         $idx = -1;
//         foreach($allowed as $value) {
//           $valueGroups[--$idx] = $value['key'];
//           $values2[$idx] = $this->l->t('add to this group');
//           $valueData[$idx] = json_encode([ 'GroupId' => $value['key'] ]);
//         }
//         $fdd = array_merge(
//           $fdd, [
//             'select' => 'M',
//             'sql' => "GROUP_CONCAT(DISTINCT PMEjoin{$curColIdx}.InstrumentationId)",
//             'display' => [ 'popup' => 'data' ],
//             'colattrs' => [ 'data-groups' => json_encode($allowed), ],
//             'filter' => 'having',
//             'values' => [
//               'table' => "SELECT
//   b.Id AS InstrumentationId,
//   CONCAT_WS(' ', m.Vorname, m.Name) AS Name,
//   m.Name AS LastName, m.Vorname AS FirstName,
//   fd.FieldValue AS GroupId
// FROM Besetzungen b
// LEFT JOIN Musiker AS m
//   ON b.MusikerId = m.Id
// LEFT JOIN ProjectExtraFieldsData fd
//   ON b.Id = fd.BesetzungenId AND fd.FieldId = $fieldId
// WHERE b.ProjektId = $projectId",
//               'column' => 'InstrumentationId',
//               'description' => 'Name',
//               'groups' => "\$table.GroupId",
//               'data' => "CONCAT('{\"GroupId\":\"',IFNULL(\$table.GroupId, -1),'\"}')",
//               'orderby' => '$table.GroupId ASC, $table.LastName ASC, $table.FirstName ASC',
//               'join' => '$main_table.`'.$fieldName.'` = $join_table.GroupId',
//               ],
//             'valueGroups' => $valueGroups,
//             'valueData' => $valueData,
//             'values2' => $values2,
//             ]);
//         $fdd['css']['postfix'] .= ' groupofpeople predefined clip-long-text';
//         $fdd['css|LFVD']['postfix'] = $fdd['css']['postfix'].' view';

//         // in filter mode mask out all non-group-members
//         $fdd['values|LF'] = array_merge(
//           $fdd['values'],
//           [ 'filters' => '$table.GroupId IS NOT NULL' ]);

//         $css = ' groupofpeople-id predefined';
//         if ($type['Name'] === 'SurchargeGroups') {
//           $css .= ' surcharge';
//           foreach($groupValues2 as $key => $value) {
//             $money = Util::moneyValue($groupValueData[$key], Config::$locale);
//             $groupValues2ACP[$key] = $value.':&nbsp;'.$money;
//             $value = Util::htmlEscape($value);
//             $value = '<span class="allowed-option-name group clip-long-text">'.$value.'</span>';
//             $money = '<span class="allowed-option-value money">'.'&nbsp;'.$money.'</span>';
//             $groupValues2[$key] = $value.$money;
//           }
//         }

//         // after all this tweaking, we still need the real group id
//         $opts['fdd'][$fieldName.'GroupId'] = [
//           'name'        => $this->l->t('%s Group', array($name)),
//           'css'         => [ 'postfix' => $css ],
//           'input|LFVD'  => 'VR',
//           'input'       => 'SR',
//           'select'      => 'D',
//           'sql'         => $fieldName,
//           'values2'     => $groupValues2,
// //          'values2|ACP' => $groupValues2ACP,
//           'display'     => [ 'popup' => 'data' ],
//           'sort'        => true,
//           'escape'      => false,
//           ];
//         if (!empty($groupValues2ACP)) {
//           $opts['fdd'][$fieldName.'GroupId']['values2|ACP'] = $groupValues2ACP;
//         }
//         break;
//       default:
//         break;
//       }

//       // Need also a hidden Id-field
//       $opts['fdd'][$name.'Id'] = array(
//         'name'     => $name.'Id',
//         'tab'      => array('id' => 'project'),
//         'input'    => 'H',
//         'select'   => 'N',
//         'escape'   => false,
//         'sort'     => false);
//     }

//     $opts['fdd']['ProjectRemarks'] =
//       array('name' => $this->l->t("Remarks")."\n(".$projectName.")",
//             'select'   => 'T',
//             'maxlen'   => 65535,
//             'css'      => array('postfix' => ' remarks tooltip-left'),
//             'display|LF' => array('popup' => 'data'),
//             'textarea' => array('css' => 'wysiwyg-editor',
//                                 'rows' => 5,
//                                 'cols' => 50),
//             'escape' => false,
//             'sort'   => true,
//             'tab'    => array('id' => 'project')
//         );

//     // fetch the list of all projects in order to provide a somewhat
//     // cooked filter list
//     $allProjects = Projects::fetchProjects(false /* no db handle */, true /* include years */);
//     $projects = array();
//     $groupedProjects = array();
//     foreach ($allProjects as $proj) {
//       $projects[$proj['Name']] = $proj['Name'];
//       $groupedProjects[$proj['Name']] = $proj['Jahr'];
//     }

//     $opts['fdd']['Projects'] = array(
//       'name' => $this->l->t('Projects'),
//       'tab' => array('id' => array('musician')),
//       'input' => 'R',
//       'options' => 'LFV',
//       'select' => 'M',
//       'display|LF'  => array('popup' => 'data'),
//       'css'      => array('postfix' => ' projects'),
//       'sort' => true,
//       'values2' => $projects,
//       'valueGroups' => $groupedProjects
//       );

//     $opts['fdd']['Email'] = Config::$opts['email'];
//     $opts['fdd']['Email']['tab'] = array('id' => 'musician');

//     $opts['fdd']['MobilePhone'] = array(
//       'name'     => $this->l->t('Mobile Phone'),
//       'tab'      => array('id' => 'musician'),
//       'css'      => array('postfix' => ' phone-number'),
//       'display'  => array('popup' => function($data) {
//           if (PhoneNumbers::validate($data)) {
//             return nl2br(PhoneNumbers::metaData());
//           } else {
//             return null;
//           }
//         }),
//       'nowrap'   => true,
//       'select'   => 'T',
//       'maxlen'   => 384,
//       'sort'     => true
//       );

//     $opts['fdd']['FixedLinePhone'] = array(
//       'name'     => $this->l->t('Fixed Line Phone'),
//       'tab'      => array('id' => 'musician'),
//       'css'      => array('postfix' => ' phone-number'),
//       'display'  => array('popup' => function($data) {
//           if (PhoneNumbers::validate($data)) {
//             return nl2br(PhoneNumbers::metaData());
//           } else {
//             return null;
//           }
//         }),
//       'nowrap'   => true,
//       'select'   => 'T',
//       'maxlen'   => 384,
//       'sort'     => true
//       );

//     $opts['fdd']['Strasse'] = array(
//       'name'     => $this->l->t('Street'),
//       'tab'      => array('id' => 'musician'),
//       'css'      => array('postfix' => ' musician-address street'),
//       'nowrap'   => true,
//       'select'   => 'T',
//       'maxlen'   => 384,
//       'sort'     => true
//       );

//     $opts['fdd']['Postleitzahl'] = array(
//       'name'     => $this->l->t('Postal Code'),
//       'tab'      => array('id' => 'musician'),
//       'css'      => array('postfix' => ' musician-address postal-code'),
//       'select'   => 'T',
//       'maxlen'   => 11,
//       'sort'     => true
//       );

//     $opts['fdd']['Stadt'] = array(
//       'name'     => $this->l->t('City'),
//       'tab'      => array('id' => 'musician'),
//       'css'      => array('postfix' => ' musician-address city'),
//       'select'   => 'T',
//       'maxlen'   => 384,
//       'sort'     => true
//       );

//     $countries = GeoCoding::countryNames();
//     $countryGroups = GeoCoding::countryContinents();

//     $opts['fdd']['Land'] = array(
//       'name'     => $this->l->t('Country'),
//       'tab'      => array('id' => 'musician'),
//       'select'   => 'D',
//       'maxlen'   => 128,
//       'default'  => Config::getValue('streetAddressCountry'),
//       'values2'     => $countries,
//       'valueGroups' => $countryGroups,
//       'css'      => array('postfix' => ' musician-address country tooltip-top'),
//       'sort'     => true,
//       );

//     $opts['fdd']['Geburtstag'] = Config::$opts['birthday'];
//     $opts['fdd']['Geburtstag']['tab'] = 'musician';

//     $opts['fdd']['Remarks'] = array(
//       'name'     => strval($this->l->t('General Remarks')),
//       'tab'      => array('id' => 'musician'),
//       'select'   => 'T',
//       'maxlen'   => 65535,
//       'css'      => array('postfix' => ' remarks tooltip-left'),
//       'display|LF' => array('popup' => 'data'),
//       'textarea' => array('css' => 'wysiwyg-editor',
//                           'rows' => 5,
//                           'cols' => 50),
//       'escape'   => false,
//       'sort'     => true);

//     $opts['fdd']['Sprachpräferenz'] = array(
//       'name'     => $this->l->t('Preferred Language'),
//       'tab'      => array('id' => 'musician'),
//       'select'   => 'D',
//       'maxlen'   => 128,
//       'default'  => 'Deutsch',
//       'sort'     => true,
//       'values'   => Config::$opts['languages']);

//     $opts['fdd']['Portrait'] = array(
//       'name'    => $this->l->t('Photo'),
//       'tab'     => array('id' => 'musician'),
//       'input'   => 'V',
//       'select'  => 'T',
//       'options' => 'ACPDV',
//       'sql'     => '`PMEtable0`.`MusikerId`',
//       'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
//         $stampIdx = array_search('Aktualisiert', $fds);
//         $stamp = strtotime($row['qf'.$stampIdx]);
//         return Musicians::portraitImageLink($musicianId, $action, $stamp);
//       },
//       'css' => array('postfix' => ' photo'),
//       'default' => '',
//       'css' => array('postfix' => ' photo'),
//       'sort' => false);

//     $opts['fdd']['UUID'] = array(
//       'name'     => 'UUID', // no translation
//       'tab'      => array('id' => 'miscinfo'),
//       'options'  => 'AVCPDR', // auto increment
//       'css'      => array('postfix' => ' musician-uuid'),
//       'select'   => 'T',
//       'maxlen'   => 32,
//       'sort'     => false
//       );

//     $opts['fdd']['Aktualisiert'] = array_merge(
//       Config::$opts['datetime'],
//       array("name" => $this->l->t("Last Updated"),
//             'tab'     => array('id' => array('tab-all')),
//             "default" => date(Config::$opts['datetime']['datemask']),
//             "nowrap"  => true,
//             'input'   => 'R',
//             "options" => 'LFAVCPD' // Set by update trigger.
//         ));


//     $opts['triggers']['update']['before'] = [];
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeAnythingTrimAnything';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeUpdateTrigger';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
//     $opts['triggers']['update']['before'][] = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

//     // that one has to be adjusted further ...
//     $opts['triggers']['delete']['before'][] = 'CAFEVDB\DetailedInstrumentation::beforeDeleteTrigger';

//     // fill the numbers table
//     $opts['triggers']['filter']['pre'][]  =
//       $opts['triggers']['update']['pre'][]  =
//       $opts['triggers']['insert']['pre'][]  = 'CAFEVDB\ProjectExtra::preTrigger';

//     //$opts['triggers']['select']['data'][] =
//     $opts['triggers']['update']['data'][] =
//       function(&$pme, $op, $step, &$row) {
//       $prInstIdx        = $pme->fdn['ProjectInstrumentId'];
//       $voiceIdx         = $pme->fdn['Voice'];
//       $sectionLeaderIdx = $pme->fdn['SectionLeader'];
//       $instruments = Util::explode(',', $row["qf{$prInstIdx}_idx"]);
//       //error_log('data '.print_r($row, true));
//       switch (count($instruments)) {
//       case 0:
//         $pme->fdd[$voiceIdx]['input'] = 'R';
//         $pme->fdd[$sectionLeaderIdx]['input'] = 'R';
//         break;
//       case 1:
//         unset($pme->fdd[$voiceIdx]['values']['groups']);
//         //error_log('data '.print_r($pme->fdd[$voiceIdx], true));
//         $pme->fdd[$voiceIdx]['select'] = 'D';
//         break;
//       default:
//         break;
//       }
//       return true;
//     };

//     if ($this->pme_bare) {
//       // disable all navigation buttons, probably for html export
//       $opts['navigation'] = 'N'; // no navigation
//       $opts['options'] = '';
//       // Don't display special page elements
//       $opts['display'] =  array_merge($opts['display'],
//                                       array(
//                                         'form'  => false,
//                                         'query' => false,
//                                         'sort'  => false,
//                                         'time'  => false,
//                                         'tabs'  => false,
//                                         ));
//       // Disable sorting buttons
//       foreach ($opts['fdd'] as $key => $value) {
//         $opts['fdd'][$key]['sort'] = false;
//       }
//     }

//     $opts['execute'] = $this->execute;

//     // Inject the underlying table name as 'querygroup' parameter
//     // s.t. update queries can be split into several queries which
//     // only target one of the underlying tables.
//     $viewStructure = Projects::viewStructure($projectId, $userExtraFields);
//     //print_r($viewStructure);
//     foreach($opts['fdd'] as $name => &$data) {
//       if (isset($viewStructure[$name])) {
//         $joinField = $viewStructure[$name];
//         $table = $joinField['table'];
//         $tablename = $joinField['tablename'];
//         $key = isset($joinField['key']) ? $joinField['key'] : false;
//         if (isset($joinField['update'])) {
//           $column = $joinField['update'];
//         } else if ($joinField['column'] === true) {
//           $column = $name;
//         } else {
//           $column = $joinField['column'];
//         }
//         $data['querygroup'] = array(
//           'table' => $table,
//           'tablename' => $tablename,
//           'column' => $column,
//           'key' => $key
//           );
//       }
//     }

    ///@@@@@@@@@@@@@@@@@@@@@

    $opts = Util::arrayMergeRecursive($this->pmeOptions, $opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
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
   * @param &$newvals Set of new values, which may also be modified.
   *
   * @return bool If returning @c false the operation will be terminated
   */
  public function beforeUpdateTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    $this->logInfo('OLDVALS '.print_r($oldvals, true));
    foreach (self::JOIN_TABLES as $table => $joinInfo) {
      $joinField  = $this->joinTableMasterFieldName($table);
      $entityClass = $joinInfo['entity'];
      $meta = $this->classMetadata($entityClass);
      $fieldNames = $meta->fieldNames;
      $entityChangeSet = array_intersect_key($fieldNames, array_fill_keys($changed, true));

      $this->logInfo('Old: '.print_r($oldvals,true));
      $this->logInfo('New: '.print_r($newvals,true));
      $this->logInfo('Entity: '.print_r($entityClass, true));
      $this->logInfo('FieldNames: '.print_r($meta->fieldNames, true));
      $this->logInfo('Changed: '.print_r($changed, true));
      $this->logInfo('ChangeSet: '.print_r($entityChangeSet, true));
      $this->logInfo('Keys: '.print_r($meta->identifier, true));
      $this->logInfo('Key-Cols: '.print_r($meta->getIdentifierColumnNames(), true));

      $identifier = [];
      $identifierColumns = $meta->getIdentifierColumnNames();
      foreach ($identifierColumns as $key) {
        if (empty($joinInfo['identifier'][$key])) {
        } else {
          $identifier[$key] = $oldvals[$joinInfo['identifier'][$key]];
        }
      }
      $this->logInfo('Keys Values: '.print_r($identifier, true));

      $entity = $this->getDatabaseRepository($entityClass)->find($identifier);
      if (!empty($entityChangeSet)) {
        foreach ($entityChangeSet as $column => $field) {
          $entity[$field] = $newvals[$column];
        }
        $this->persist($entity);
        $this->flush($entity);
      }
      $changed = array_diff($changed, array_keys($entityChangeSet));
    }
    return false;
  }

  private function tableTabId($idOrName)
  {
    $dflt = $this->defaultTableTabs();
    foreach($dflt as $tab) {
      if ($idOrName === $tab['name']) {
        return $idOrName;
      }
    }
    return $idOrName;
  }

  /**
   * Export the default tabs family.
   */
  private function defaultTableTabs($useFinanceTab = false)
  {
    $pre = [
      [
        'id' => 'instrumentation',
        'default' => true,
        'tooltip' => $this->toolTipsService['project-instrumentation-tab'],
        'name' => $this->l->t('Instrumentation related data'),
      ],
      [
        'id' => 'project',
        'tooltip' => $this->toolTipsService['project-metadata-tab'],
        'name' => $this->l->t('Project related data'),
      ],
    ];
    $finance = [
      [
        'id' => 'finance',
        'tooltip' => $this->toolTipsService['project-finance-tab'],
        'name' => $this->l->t('Finance related data'),
      ],
    ];
    $post = [
      [
        'id' => 'musician',
        'tooltip' => $this->toolTipsService['project-personaldata-tab'],
        'name' => $this->l->t('Personal data'),
      ],
      [
        'id' => 'miscinfo',
        'tooltip' => $this->toolTipsService['project-personalmisc-tab'],
        'name' => $this->l->t('Miscinfo'),
      ],
      [
        'id' => 'tab-all',
        'tooltip' => $this->toolTipsService['pme-showall-tab'],
        'name' => $this->l->t('Display all columns'),
      ],
    ];
    if ($useFinanceTab) {
      return array_merge($pre, $finance, $post);
    } else {
      return array_merge($pre, $post);
    }
  }

  /**
   * Export the description for the table tabs.
   */
  private function tableTabs($extraFields = false, $useFinanceTab = false)
  {
    $dfltTabs = $this->defaultTableTabs($useFinanceTab);

    if (!is_array($extraFields)) {
      return $dfltTabs;
    }

    $extraTabs = array();
    foreach($extraFields as $field) {
      if (empty($field['Tab'])) {
        continue;
      }

      $extraTab = $field['Tab'];
      foreach($dfltTabs as $tab) {
        if ($extraTab === $tab['id'] ||
            $extraTab === (string)$tab['name']) {
          $extraTab = false;
          break;
        }
      }
      if ($extraTab !== false) {
        $extraTabs[] = [
          'id' => $extraTab,
          'name' => $this->l->t($extraTab),
          'tooltip' => $this->toolTipsService['extra-fields-extra-tab'],
        ];
      }
    }

    return array_merge($dfltTabs, $extraTabs);
  }

  /**
   * The name of the master join table field which triggers PME to
   * actually do the join.
   */
  private function joinTableMasterFieldName(string $table)
  {
    return $table.'_key';
  }

  /**
   * The name of the field-descriptor for a join-table field
   * referencing a master-join-table.
   */
  private function joinTableFieldName(string $table, string $column)
  {
    return $table.self::JOIN_FIELD_NAME_SEPARATOR.$column;
  }

  /**
   * Generate a join-table field, given join-table and field name.
   */
  // $opts['fdd']['street'] = [
  //   'name'     => $this->l->t('Street'),
  //   'tab'      => [ 'id' => 'musician' ],
  //   'css'      => ['postfix' => ' musician-address street'],
  //   'select'   => 'T',
  //   'maxlen'   => 128,
  //   'sort'     => true,
  //   'input'    => 'S',
  //   'values' => [
  //     'join' => [ 'reference' => $joinIndex[self::MUSICIANS_TABLE] ],
  //   ],
  // ];
  private function makeJoinTableField(array &$fieldDescriptionData, string $table, string $column, array $fdd)
  {
    $masterFieldName = $this->joinTableMasterFieldName($table);
    $joinIndex = array_search($masterFieldName, array_keys($fieldDescriptionData));
    if ($joinIndex === false) {
      throw new \Exception($this->l->t("Master join-table field for %s not found.", $table));
    }
    $fieldName = $this->joinTableFieldName($table, $column);
    $defaultFDD = [
      'select' => 'T',
      'maxlen' => 128,
      'sort' => true,
      'input' => 'S',
      'values' => [
        'column' => $column,
        'join' => [ 'reference' => $joinIndex, ],
      ],
    ];
    $fieldDescriptionData[$fieldName] = Util::arrayMergeRecursive($defaultFDD, $fdd);
  }

}
