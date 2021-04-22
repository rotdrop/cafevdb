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

use chillerlan\QRCode\QRCode;

use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\Finance\InsuranceService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Controller\ImagesController;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

/**Table generator for Musicians table. */
class Musicians extends PMETableViewBase
{
  const ALL_TEMPLATE = 'all-musicians';
  const ADD_TEMPLATE = 'add-musicians';
  const CSS_CLASS = 'musicians';
  const TABLE = self::MUSICIANS_TABLE;
  const PHOTO_JOIN_TABLE = 'MusicianPhoto';

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var OCA\CAFEVDB\Service\PhoneNumberService */
  private $phoneNumberService;

  /** @var OCA\CAFEVDB\Service\Finance\InsuranceService */
  private $insuranceService;

  /**
   * @var bool Called with project-id in order to add musicians to an
   * existing project
   */
  private $projectMode;

  /**
   * Join table structure. All update are handled in
   * parent::beforeUpdateDoUpdateAll().
   */
  protected $joinStructure = [
    [
      'table' => self::TABLE,
      'flags' => self::JOIN_MASTER,
      'entity' => Entities\Musician::class,
    ],
    [
      'table' => self::MUSICIAN_INSTRUMENTS_TABLE,
      'entity' => Entities\MusicianInstrument::class,
      'identifier' => [
        'instrument_id' => false,
        'musician_id' => 'id',
      ],
      'column' => 'instrument_id',
    ],
    [
      // join all bank-accounts for this musician duplicating lines
      // for all SEPA-mandates.
      'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
      'sql' => 'SELECT
  CONCAT_WS(\''.self::COMP_KEY_SEP.'\', sba.musician_id, sba.sequence, IFNULL(sdm.sequence, 0)) as sepa_id,
  sba.*,
  sdm.sequence AS debit_mandate_sequence,
  sdm.mandate_reference AS debit_mandate_reference,
  sdm.deleted AS debit_mandate_deleted
  FROM '.self::SEPA_BANK_ACCOUNTS_TABLE.' sba
  LEFT JOIN '.self::SEPA_DEBIT_MANDATES_TABLE.' sdm
    ON (sba.musician_id = sdm.musician_id
        AND sba.sequence = sdm.bank_account_sequence
        AND sdm.project_id IS NULL)
  GROUP BY sba.musician_id, sba.sequence, sdm.sequence',
      'entity' => Entities\SepaBankAccount::class,
      'identifier' => [
        'musician_id' => 'id',
        'sepa_id' => false,
      ],
      'column' => 'sepa_id',
    ],
    [
      'table' => self::SEPA_DEBIT_MANDATES_TABLE,
      'entity' => Entities\SepaDebitMandate::class,
      'identifier' => [
        'musician_id' => 'id',
        'sequence' => [
          'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
          'column' => 'debit_mandate_sequence',
        ],
      ],
      'column' => 'sequence',
    ],
    [
      'table' => self::PROJECT_PARTICIPANTS_TABLE,
      'entity' => Entities\ProjectParticipant::class,
      'identifier' => [
        'project_id' => false,
        'musician_id' => 'id',
      ],
      'column' => 'project_id',
      'flags' => self::JOIN_READONLY,
    ],
    // [
    //   'table' => self::PROJECTS_TABLE,
    //   'entity' => Entities\Project::class,
    //   'identifier' => [
    //     'id' => [
    //       'table' => self::PROJECT_PARTICIPANTS_TABLE,
    //       'column' => 'project_id',
    //     ],
    //   ],
    //   'column' => 'name',
    //   'flags' => self::JOIN_READONLY,
    // ],
    [
      'table' => self::INSTRUMENT_INSURANCES_TABLE,
      'entity' => Entities\InstrumentInsurance::class,
      'identifier' => [
        'id' => false,
        'instrument_holder_id' => 'id',
      ],
      'column' => 'bill_to_party_id',
      'flags' => self::JOIN_READONLY,
    ],
    [
      'table' => self::PHOTO_JOIN_TABLE,
      'entity' => Entities\MusicianPhoto::class,
      'flags' => self::JOIN_READONLY,
      'identifier' => [
        'owner_id' => 'id',
        'image_id' => false,
      ],
      'column' => 'image_id',
    ],
  ];

  public function __construct(
    ConfigService $configService
    , RequestParameterService $requestParameters
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , GeoCodingService $geoCodingService
    , ContactsService $contactsService
    , PhoneNumberService $phoneNumberService
    , InsuranceService $insuranceService
  ) {
    parent::__construct(self::ALL_TEMPLATE, $configService, $requestParameters, $entityManager, $phpMyEdit, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->phoneNumberService = $phoneNumberService;
    $this->insuranceService = $insuranceService;
    $this->projectMode = false;
  }

  public function cssClass() { return self::CSS_CLASS; }

  public function enableProjectMode()
  {
    $this->projectMode = true;
    $this->setTemplate(self::ADD_TEMPLATE);
  }

  public function disableProjectMode()
  {
    $this->projectMode = false;
    $this->setTemplate(self::ALL_TEMPLATE);
  }

  /** Short title for heading. */
  public function shortTitle() {
    if ($this->deleteOperation()) {
      return $this->l->t('Remove all data of the displayed musician?');
    } else if ($this->copyOperation()) {
      return $this->l->t('Copy the displayed musician?');
    } else if ($this->viewOperation()) {
      return $this->l->t('Display of all stored personal data for the shown musician.');
    } else if ($this->changeOperation()) {
      return $this->l->t('Edit the personal data of the displayed musician.');
    } else if ($this->addOperation()) {
      return $this->l->t('Add a new musician to the data-base.');
    } else if (!$this->projectMode) {
      return $this->l->t('Overview over all registered musicians');
    } else {
      return $this->l->t("Add musicians to the project `%s'", [ $this->projectName ]);
    }
  }

  /** Header text informations. */
  public function headerText() {
    $header = $this->shortTitle();
    if ($this->projectMode) {
      $title = $this->l->t("This page is the only way to add musicians to projects in order to
make sure that the musicians are also automatically added to the
`global' musicians data-base (and not only to the project).");
    }

    return '<div class="'.$this->cssPrefix().'-header-text" title="'.$title.'">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $expertMode      = $this->expertMode;

    $opts            = [];

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = ' show-hide-disabled';

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
    ];

    // Name of field which is the unique key
    $opts['key'] = 'id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = [
      $this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order'),
      'sur_name',
      'first_name',
      'id'
    ];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'id';

    if (!$this->showDisabled) {
      $opts['filters']['AND'][] = '$table.deleted IS NULL';
    }

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

    // needed early as otherwise the add_operation() etc. does not work.
    $this->pme->setOptions($opts);

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '5';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    if (!$this->projectMode) {
      $export = $this->pageNavigation->tableExportButton();
      $opts['buttons'] = $this->pageNavigation->prependTableButton($export, true);
    }

    // Display special page elements
    $opts['display'] =  [
      'form'  => true,
      //'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => [
        [ 'id' => 'orchestra',
          'default' => true,
          'tooltip' => $this->toolTipsService['musician-orchestra-tab'],
          'name' => $this->l->t('Instruments and Status') ],
        [ 'id' => 'contact',
          'tooltip' => $this->toolTipsService['musican-contact-tab'],
          'name' => $this->l->t('Contact Information') ],
        [ 'id' => 'miscinfo',
          'tooltip' => $this->toolTipsService['musician-miscinfo-tab'],
          'name' => $this->l->t('Miscellaneous Data') ],
        [ 'id' => 'tab-all',
          'tooltip' => $this->toolTipsService['pme-showall-tab'],
          'name' => $this->l->t('Display all columns')
        ],
        ],
    ];

    // field definitions

    $opts['fdd']['id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => 'id',
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH', // new id, no sense to display
      'options'  => 'AVCPD',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',  // auto increment
      'sort'     => true,
    ];

    // must come after the key-def fdd
    $joinTables = $this->defineJoinStructure($opts);

    $bval = strval($this->l->t('Add to %s', [ $projectName ]));
    $tip  = strval($this->toolTipsService['register-musician']);
    if ($this->projectMode) {
      $opts['fdd']['add_musicians'] = [
        'tab' => [ 'id' => 'orchestra' ],
        'name' => $this->l->t('Add Musicians'),
        'css' => [ 'postfix' => ' register-musician' ],
        'select' => 'T',
        'options' => 'VCLR',
        'input' => 'V',
        'sql' => '$main_table.id',
        'php' => function($musicianId, $action, $k, $row, $recordId, $pme)
          use($bval, $tip) {
            return '<div class="register-musician">'
              .'  <input type="button"'
              .'         value="'.$bval.'"'
              .'         data-musician-id="'.$musicianId.'"'
              .'         title="'.$tip.'"'
              .'         name="registerMusician"'
              .'         class="register-musician" />'
              .'</div>';
        },
        'escape' => false,
        'nowrap' => true,
        'sort' =>false,
      ];
    }

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    $opts['fdd']['sur_name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Surname'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'input|LF' => 'H',
      // 'options'  => 'AVCPD',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['first_name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Forename'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'input|LF' => 'H',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['nick_name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Nickname'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'input|LF' => 'H',
      'sql|LFVD' => 'IF($column IS NULL OR $column = \'\', $table.first_name, $column)',
      'select'   => 'T',
      'maxlen'   => 380,
      'sort'     => true,
      'display|ACP' => [
        'attributes' => function($op, $row, $k, $pme) {
          $firstName = $row['qf'.($k-1)];
          return [
            'placeholder' => $firstName,
            'readonly' => empty($row['qf'.$k]),
          ];
        },
        'postfix' => function($op, $pos, $row, $k, $pme) {
          $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
          return '<input id="pme-musician-nickname"
  '.$checked.'
  type="checkbox"
  class="pme-input pme-input-lock-empty"/>
<label
   class="pme-input pme-input-lock-empty"
   title="'.$this->toolTipsService['pme-input-lock-empty'].'"
   for="pme-musician-nickname"></label>';
        },
      ],
    ];

    $opts['fdd']['display_name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Display-Name'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'sql|LFVD' => 'IF($column IS NULL OR $column = \'\',
  CONCAT(
    $table.sur_name,
    \', \',
    IF($table.nick_name IS NULL OR $table.nick_name = \'\',
      $table.first_name,
      $table.nick_name
    )
  ),
  $column)',
      'maxlen'   => 384,
      'sort'     => true,
      'select'   => 'T',
      'display|ACP' => [
        'attributes' => function($op, $row, $k, $pme) {
          $surName = $row['qf'.($k-3)];
          $firstName = $row['qf'.($k-2)];
          $nickName = $row['qf'.($k-1)];
          return [
            'placeholder' => $surName.', '.($nickName?:$firstName),
            'readonly' => empty($row['qf'.$k]),
          ];
        },
        'postfix' => function($op, $pos, $row, $k, $pme) {
          $checked = empty($row['qf'.$k]) ? '' : 'checked="checked" ';
          return '<input id="pme-musician-displayname"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock-empty"
/><label
    class="pme-input pme-input-lock-empty"
    title="'.$this->toolTipsService['pme-input-lock-empty'].'"
    for="pme-musician-displayname"></label>';
        },
      ],
    ];

    $opts['fdd']['user_id_slug'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('User Id'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'input|LF' => 'H',
      // 'options'  => 'AVCPD',
      'select'   => 'T',
      'maxlen'   => 256,
      'sort'     => true,
      'display|ACP' => [
        'attributes' => function($op, $row, $k, $pme) {
          $surName = $row['qf'.($k-4)];
          $firstName = $row['qf'.($k-3)];
          $nickName = $row['qf'.($k-2)];
          $placeHolder = $this->defaultUserIdSlug($surName, $firstName, $nickName);
          return [
            'placeholder' => $placeHolder,
            'readonly' => true,
          ];
        },
        'postfix' => function($op, $pos, $row, $k, $pme) {
          $checked = 'checked="checked" ';
          return '<input id="pme-musician-user-id-slug"
  type="checkbox"
  '.$checked.'
  class="pme-input pme-input-lock-unlock"
/><label
    class="pme-input pme-input-lock-unlock"
    title="'.$this->toolTipsService['pme-input-lock-unlock'].'"
    for="pme-musician-user-id-slug"></label>';
        },
      ],
    ];

    if ($this->showDisabled) {
      // soft-deletion
      $opts['fdd']['deleted'] = array_merge(
        $this->defaultFDD['deleted'], [
          'name' => $this->l->t('Deleted'),
          //'datemask' => 'd.m.Y H:i:s',
        ]
      );
    }

    $fdd = [
      'name'        => $this->l->t('Instruments'),
      'tab'         => ['id' => 'orchestra'],
      'css'         => ['postfix' => ' musician-instruments tooltip-top no-chosen selectize drag-drop'],
      'display|LVF' => ['popup' => 'data'],
      'sql'         => ($expertMode
                        ? 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'
                        : 'GROUP_CONCAT(DISTINCT IF('.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.deleted IS NULL, $join_col_fqn, NULL) ORDER BY '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.ranking ASC, $order_by)'),
      'select'      => 'M',
      'values' => [
        'table'       => self::INSTRUMENTS_TABLE,
        'column'      => 'id',
        'description' => 'name',
        'orderby'     => '$table.sort_order ASC',
        'join'        => '$join_col_fqn = '.$joinTables[self::MUSICIAN_INSTRUMENTS_TABLE].'.instrument_id'
      ],
      'values2' => $this->instrumentInfo['byId'],
      'valueGroups' => $this->instrumentInfo['idGroups'],
    ];
    $fdd['values|ACP'] = array_merge($fdd['values'], [ 'filters' => '$table.deleted IS NULL' ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'instrument_id', $fdd);
    $joinTables[self::INSTRUMENTS_TABLE] = 'PMEjoin'.(count($opts['fdd'])-1);

    $opts['fdd'][$this->joinTableFieldName(self::INSTRUMENTS_TABLE, 'sort_order')] = [
      'tab'         => [ 'id' => [ 'orchestra' ] ],
      'name'        => $this->l->t('Instrument Sort Order'),
      'sql|VCP'     => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by)',
      'input'       => 'HRS',
      'select'      => 'M',
      'sort'     => true,
      'values' => [
        'column' => 'sort_order',
        'orderby' => '$table.sort_order ASC',
        'join' => [ 'reference' => $joinTables[self::INSTRUMENTS_TABLE], ],
      ],
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::MUSICIAN_INSTRUMENTS_TABLE, 'deleted', [
        'name'    => $this->l->t('Disabled Instruments'),
        'tab'     => [ 'id' => [ 'musician', 'instrumentation' ] ],
        'sql'     => "GROUP_CONCAT(DISTINCT IF(\$join_col_fqn IS NULL, NULL, \$join_table.instrument_id))",
        'default' => false,
        'select'  => 'T',
        'input'   => ($expertMode ? 'S' : 'SH'),
        'tooltip' => $this->toolTipsService['musician-instruments-disabled'],
      ]);

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $opts['fdd']['member_status'] = [
      'name'    => strval($this->l->t('Member Status')),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => ['postfix' => ' memberstatus tooltip-wide'],
      'values2' => $this->memberStatusNames,
      'tooltip' => $this->toolTipsService['member-status'],
    ];

    $opts['fdd']['projects'] = [
      'tab' => ['id' => 'orchestra'],
      'input' => 'VR',
      'options' => 'LFVC',
      'select' => 'M',
      'name' => $this->l->t('Projects'),
      'sort' => true,
      'css'      => ['postfix' => ' projects tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'sql' => 'GROUP_CONCAT(DISTINCT $join_col_fqn ORDER BY $order_by SEPARATOR \',\')',
      //'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table' => self::PROJECTS_TABLE,
        'column' => 'name',
        'orderby' => '$table.year ASC, $table.name ASC',
        'groups' => 'year',
        'join' => '$join_table.id = '.$joinTables[self::PROJECT_PARTICIPANTS_TABLE].'.project_id'
      ],
    ];

    $opts['fdd']['mobile_phone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Mobile Phone'),
      'css'      => ['postfix' => ' phone-number'],
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      ];

    $opts['fdd']['fixed_line_phone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Fixed Line Phone'),
      'css'      => ['postfix' => ' phone-number'],
      'display'  => [
        'popup' => function($data) {
          return $this->phoneNumberService->metaData($data, null, '<br/>');
        }
      ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
    ];

    $opts['fdd']['email'] = $this->defaultFDD['email'];
    $opts['fdd']['email']['tab'] = ['id' => 'contact'];
    $opts['fdd']['email']['input'] .= 'M';

    $opts['fdd']['street'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Street'),
      'css'      => ['postfix' => ' musician-address street'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['postal_code'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Postal Code'),
      'css'      => ['postfix' => ' musician-address postal-code'],
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true,
    ];

    $opts['fdd']['city'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('City'),
      'css'      => ['postfix' => ' musician-address city'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $countries = $this->geoCodingService->countryNames();
    $countryGroups = $this->geoCodingService->countryContinents();

    $opts['fdd']['country'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Country'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => $this->getConfigValue('streetAddressCountry'),
      'values2'     => $countries,
      'valueGroups' => $countryGroups,
      'css'      => ['postfix' => ' musician-address country chosen-dropup'],
      'sort'     => true,
    ];

    $opts['fdd']['birthday'] = $this->defaultFDD['birthday'];
    $opts['fdd']['birthday']['tab'] = ['id' => 'miscinfo'];

    $opts['fdd']['remarks'] = [
      'tab'      => ['id' => 'orchestra'],
      'name'     => strval($this->l->t('Remarks')),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => ['postfix' => ' remarks tooltip-top'],
      'textarea' => ['css' => 'wysiwyg-editor',
                     'rows' => 5,
                     'cols' => 50],
      'display|LF' => ['popup' => 'data'],
      'escape' => false,
      'sort'     => true,
    ];

    $opts['fdd']['language'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => $this->l->t('Language'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values2'  => $this->findAvailableLanguages(),
    ];

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BANK_ACCOUNTS_TABLE, 'debit_mandate_reference', [
        'name' => $this->l->t('SEPA Debit Mandate Reference'),
        'input' => 'H',
        'tab' => [ 'id' => 'contact' ],
        'sql' => 'GROUP_CONCAT(DISTINCT CONCAT_WS(\':\', $join_table.sepa_id, $join_col_fqn) ORDER BY $order_by)',
        'filter' => 'having',
        'sort' => true,
        'select' => 'M',
        'values' => [
          'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
          // description needs to be there in order to trigger drop-down on change
          'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
          'grouped' => true,
          'orderby' => '$table.sepa_id ASC',
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BANK_ACCOUNTS_TABLE, 'debit_mandate_deleted', [
        'name' => $this->l->t('SEPA Debit Mandate Deleted'),
        'input' => 'H',
        'tab' => [ 'id' => 'contact' ],
        'sql' => 'GROUP_CONCAT(DISTINCT CONCAT_WS(\':\', $join_table.sepa_id, $join_col_fqn) ORDER BY $order_by)',
        'filter' => 'having',
        'sort' => true,
        'select' => 'M',
        'values' => [
          'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
          // description needs to be there in order to trigger drop-down on change
          'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
          'grouped' => true,
          'orderby' => '$table.sepa_id ASC',
        ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BANK_ACCOUNTS_TABLE, 'iban', [
        'name' => $this->l->t('SEPA Bank Accounts'),
        'input' => 'S',
        'input|ACP' => 'H',
        'tab' => [ 'id' => 'contact' ],
        'encryption' => [
          'encrypt' => function($value) {
            $values = Util::explode(',', $value);
            foreach ($values as &$value) {
              $value = $this->encrypt($value);
            }
            return implode(',', $values);
          },
          'decrypt' => function($value) {
            $values = Util::explode(',', $value);
            foreach ($values as &$value) {
              $value = $this->decrypt($value);
            }
            return implode(',', $values);
          },
        ],
        'encryption|ACP' => [
          'encrypt' => function($value) {
            $values = Util::explodeIndexed($value);
            foreach ($values as $key => $value) {
              $values[$key] = $key.self::JOIN_KEY_SEP.$this->encrypt($value);
            }
            return implode(',', $values);
          },
          'decrypt' => function($value) {
            $values = Util::explodeIndexed($value);
            foreach ($values as $key => $value) {
              $values[$key] = $key.self::JOIN_KEY_SEP.$this->decrypt($value);
            }
            return implode(',', $values);
          },
        ],
        'sql|ACP' => 'GROUP_CONCAT(DISTINCT CONCAT_WS(\':\', $join_table.sepa_id, $join_col_fqn) ORDER BY $order_by)',
        'filter' => 'having',
        'display|LFDV' => ['popup' => 'data'],
        'sort' => true,
        'select' => 'M',
        'values' => [
          'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
          // description needs to be there in order to trigger drop-down on change
          'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
          'grouped' => true,
          'orderby' => '$table.sepa_id ASC',
        ],
        'values2glue' => '<br/>',
        'css' => [ 'postfix' => ' hide-subsequent-lines' ],
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::SEPA_BANK_ACCOUNTS_TABLE, 'sepa_id', [
        'name' => $this->l->t('SEPA Bank Accounts'),
        'input' => 'VS',
        'input|LFDV' => 'VH',
        'select' => 'D',
        'css' => [ 'postfix' => ' sepa-bank-accounts' ],
        'values' => [
          'table' => self::SEPA_BANK_ACCOUNTS_TABLE,
          // description needs to be there in order to trigger drop-down on change
          'description' => PHPMyEdit::TRIVIAL_DESCRIPION,
          'grouped' => true,
        ],
        'php' => function($value, $op, $k, $row, $recordId, $pme) {
          $this->logInfo('VALUE '.$value.' ROW '.print_r($row, true));
          $valInfo = $pme->set_values($k-1);
          $this->logInfo('VALINFO '.print_r($valInfo, true));

          // more efficient would perhaps be JSON
          $sepaIds = Util::explode(',', $value);
          $ibans = Util::explodeIndexed($row['qf'.($k-1)]);
          $deleted = Util::explodeIndexed($row['qf'.($k-2)]);
          $references = Util::explodeIndexed($row['qf'.($k-3)]);
          $html = '<table class="row-count-'.count($ibans).'">
  <tbody>';
          foreach ($ibans as $sepaId => $iban) {
            $html .= '
    <tr class="bank-account-data" data-sepa-id="'.$sepaId.'">
      <td class="operations">
        <input
          class="operation delete-undelete"
          title="'.$this->toolTipsService['sepa-bank-account:delete-undelete'].'"
          type="button"/>
        <input
          class="operation info"
          title="'.$this->toolTipsService['sepa-bank-account:info'].'"
          type="button"/>
      </td>
      <td class="iban">
        <input
          class="bank-account-data"
          title="'.$this->toolTipsService['sepa-bank-account:info'].'"
          type="text"
          value="'.$iban.'"
          readonly
        />
      </td>
    </tr>';
          }
          $html .= '
    <tr class="placeholder">
      <td colspan="2" class="operation">
        <input
          class="operation add"
          title="'.$this->toolTipsService['sepa-bank-account:add'].'"
          type="button"
          value="'.$this->l->t('Add a new bank account').'"
        />
      </td>
    </tr>
  </tbody>
</table>
<div class="display-options">
  <div class="show-deleted">
    <input type="checkbox"
           name="show-deleted"
           class="show-deleted checkbox"
           value="show"
           id="sepa-bank-accounts-show-deleted"
           />
    <label class="show-deleted"
           for="sepa-bank-accounts-show-deleted"
           title="'.$this->toolTipsService['sepa-bank-acocunt:show-deleted'].'"
           >
    '.$this->l->t('Show deleted.').'
    </label>
  </div>
</div>';
          return $html;
        },
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::INSTRUMENT_INSURANCES_TABLE, 'insurance_amount', [
       'tab'      => ['id' => 'miscinfo'],
       'input' => 'V',
       'name' => $this->l->t('Instrument Insurance'),
       'select' => 'T',
       'options' => 'CDV',
       'sql' => 'SUM($join_col_fqn)',
       'escape' => false,
       'nowrap' => true,
       'sort' =>false,
       'php' => function($totalAmount, $action, $k, $row, $recordId, $pme) {
         $musicianId = $recordId['musician_id'];
         $annualFee = $this->insuranceService->insuranceFee($musicianId, null, true);
         $bval = $this->l->t(
           'Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;', [ $totalAmount, $annualFee ]);
         $tip = $this->toolTipsService['musician-instrument-insurance'];
         $button = "<div class=\"musician-instrument-insurance\">"
                 ."<input type=\"button\" "
                 ."value=\"$bval\" "
                 ."title=\"$tip\" "
                 ."name=\""
                 ."Template=instrument-insurance&amp;"
                 ."MusicianId=".$musicianId."\" "
                 ."class=\"musician-instrument-insurance\" />"
                 ."</div>";
         return $button;
       }
      ]);

    $this->makeJoinTableField(
      $opts['fdd'], self::PHOTO_JOIN_TABLE, 'image_id', [
      'tab'      => ['id' => 'miscinfo'],
      'input' => 'VRS',
      'name' => $this->l->t('Photo'),
      'select' => 'T',
      'options' => 'APVCD',
      'php' => function($imageId, $action, $k, $row, $recordId, $pme) {
        $musicianId = $recordId;
        return $this->photoImageLink($musicianId, $action, $imageId);
      },
      'css' => ['postfix' => ' photo'],
      'default' => '',
      'sort' => false
    ]);

//     ///////////////////// Test

    $opts['fdd']['vcard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '$main_table.id',
      'php' => function($musicianId, $action, $k, $row, $recordId, $pme) {
        // $this->logInfo('ROW: '.print_r($row, true));
        switch($action) {
        case 'change':
        case 'display':
          list('musician' => $musician, 'categories' => $categories) = $this->musicianFromRow($row, $pme);
          $vcard = $this->contactsService->export($musician);
          unset($vcard->PHOTO); // too much information
          $categories = array_merge($categories, $vcard->CATEGORIES->getParts());
          sort($categories);
          $vcard->CATEGORIES->setParts($categories);
          // $this->logInfo($vcard->serialize());
          return '<img height="231" width="231" src="'.(new QRCode)->render($vcard->serialize()).'"></img>';
        default:
          return '';
        }
      },
      'default' => '',
      'sort' => false
    ];

    //////////////////////////

    $opts['fdd']['uuid'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => 'UUID',
      //'options'  => 'AVCPDR',
      'input'    => 'R',
      'css'      => ['postfix' => ' musician-uuid'.' '.$addCSS],
      'sql'      => 'BIN2UUID($main_table.uuid)',
      'sqlw'     => 'UUID2BIN($val_qas)',
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => true,
    ];

    $opts['fdd']['updated'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          //"default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger.
        ]
      );

    $opts['fdd']['created'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Created"),
          //"default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger
        ]
      );

    if ($this->projectMode) {
      //$key = 'qf'.$projectsIdx;
      $projectsJoin = $joinTables[self::PROJECT_PARTICIPANTS_TABLE];
      $projectIds = "GROUP_CONCAT(DISTINCT {$projectsJoin}.project_id)";
      $opts['having']['AND'] = "($projectIds IS NULL OR NOT FIND_IN_SET('$projectId', $projectIds))";
      $opts['misc']['css']['major']   = 'bulkcommit';
      $opts['labels']['Misc'] = strval($this->l->t('Add all to %s', [$projectName]));
    }

    $opts['triggers']['update']['before'][] = [ $this, 'ensureUserIdSlug' ];
    $opts['triggers']['update']['before'][] = [ $this, 'extractInstrumentRanking' ];
    $opts['triggers']['update']['before'][] = [ $this, 'beforeUpdateDoUpdateAll' ];
    $opts['triggers']['update']['before'][] = [ $this, 'renameProjectParticipantFolders' ];

    $opts['triggers']['insert']['before'][] = [ $this, 'extractInstrumentRanking' ];
    $opts['triggers']['insert']['before'][] = [ $this, 'beforeInsertDoInsertAll' ];

    // $opts['triggers']['delete']['before'][]  = 'CAFEVDB\Musicians::beforeDeleteTrigger';

    $opts = $this->mergeDefaultOptions($opts);

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  /**
   * Geneate code for a HTML-link for an optional photo.
   */
  public function photoImageLink($musicianId, $action = 'display', $imageId)
  {
    if (empty($imageId)) {
      $imageId = ImagesController::IMAGE_ID_ANY;
    }
    switch ($action) {
    case 'add':
      return $this->l->t("Photos or Avatars can only be added to an existing musician's profile; please add the new musician without protrait image first.");
    case 'display':
      $url = $this->urlGenerator()->linkToRoute(
        'cafevdb.images.get',
        [ 'joinTable' => self::PHOTO_JOIN_TABLE,
          'ownerId' => $musicianId ]);
      $url .= '?timeStamp='.time();
      $url .= '&imageId='.$imageId;
      $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
      $div = ''
        .'<div class="photo image-wrapper single full"><img class="cafevdb_inline_image portrait zoomable" src="'.$url.'" '
        .'title="'.$this->l->t("Photo, if available").'" /></div>';
      return $div;
    case 'change':
      $imageInfo = json_encode([
        'ownerId' => $musicianId,
        'imageId' => $imageId,
        'joinTable' => self::PHOTO_JOIN_TABLE,
        'imageSize' => -1,
      ]);
      $photoarea = ''
        .'<div  data-image-info=\''.$imageInfo.'\' class="tip musician-portrait portrait propertycontainer tooltip-top cafevdb_inline_image_wrapper image-wrapper single full" title="'
      .$this->l->t("Drop photo to upload (max %s)", [ \OCP\Util::humanFileSize(Util::maxUploadSize()) ]).'"'
        .' data-element="PHOTO">
  <ul class="phototools" class="transparent hidden contacts_property">
    <li><a class="svg delete" title="'.$this->l->t("Delete current photo").'"></a></li>
    <li><a class="svg edit" title="'.$this->l->t("Edit current photo").'"></a></li>
    <li><a class="svg upload" title="'.$this->l->t("Upload new photo").'"></a></li>
    <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select photo from Cloud").'"></a></li>
  </ul>
</div> <!-- contact_photo -->
';

      return $photoarea;
    default:
      return $this->l->t("Internal error, don't know what to do concerning photos in the given context.");
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
