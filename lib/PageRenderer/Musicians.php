<?php // Hey, Emacs, we are -*- php -*- mode!
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
use OCA\CAFEVDB\Service\ChangeLogService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\ContactsService;

use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;

/**Table generator for Musicians table. */
class Musicians extends PMETableViewBase
{
  const CSS_CLASS = 'musicians';
  const TABLE = 'Musicians';
  const INSTRUMENTS_JOIN = 'MusicianInstrument';
  const PHOTO_JOIN = 'MusicianPhoto';

  /** @var GeoCodingService */
  private $geoCodingService;

  private $projectMode;

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
  ) {
    parent::__construct($configService, $requestParameters, $entityManager, $phpMyEdit, $changeLogService, $toolTipsService, $pageNavigation);
    $this->geoCodingService = $geoCodingService;
    $this->contactsService = $contactsService;
    $this->projectMode = false;
  }

  public function cssClass() { return self::CSS_CLASS; }

  public function enableProjectMode()
  {
    $this->projectMode = true;
  }

  public function disableProjectMode()
  {
    $this->projectMode = false;
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
      return $this->l->t("Add musicians to the project `%s'", array($this->projectName));
    }
  }

  /** Header text informations. */
  public function headerText() {
    $header = $this->shortTitle();
    if ($this->projectMode) {
      $header .= "
<p>".$this->l->t("This page is the only way to add musicians to projects in order to
make sure that the musicians are also automatically added to the
`global' musicians data-base (and not only to the project).");
    }

    return '<div class="'.$this->cssPrefix().'-header-text">'.$header.'</div>';
  }

  /** Show the underlying table. */
  public function render(bool $execute = true)
  {
    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->pmeOptions;

    $expertMode = $this->getUserValue('expertmode');

    $opts['tb'] = self::TABLE;

    $opts['css']['postfix'] = ' show-hide-disabled';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    // Install values for after form-submit, e.g. $this->template ATM
    // is just the request parameter, while Template below will define
    // the value of $this->template after form submit.
    $template = $this->projectMode ? 'add-musicians' : 'all-musicians';
    $opts['cgi']['persist'] = [
      'template' => $template,
      'table' => $opts['tb'],
      'templateRenderer' => 'template:'.$template,
    ];

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = ['Instrumente','Name','Vorname','Id'];

    // GROUP BY clause, if needed.
    $opts['groupby_fields'] = 'Id';

    $opts['filters'] = "PMEtable0.Disabled <= ".intval($this->showDisabled);

    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    $opts['options'] = 'ACPVDFM';

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
    $opts['display'] =  Util::arrayMergeRecursive(
      $opts['display'],
      [
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
      ],
    );

    // field definitions

    $opts['fdd']['Id'] = [
      'tab'      => [ 'id' => 'miscinfo' ],
      'name'     => 'Id',
      'select'   => 'T',
      'input'    => 'R',
      'input|AP' => 'RH', // new id, no sense to display
      'options'  => 'AVCPD',
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',  // auto increment
      'sort'     => true
    ];

    $bval = strval($this->l->t('Add to %s', array($projectName)));
    $tip  = strval($this->toolTipsService['register-musician']);
    if ($this->projectMode) {
      $opts['fdd']['AddMusicians'] = [
        'tab' => [ 'id' => 'orchestra' ],
        'name' => $this->l->t('Add Musicians'),
        'select' => 'T',
        'options' => 'VLR',
        'input' => 'V',
        'sql' => "REPLACE('"
."<div class=\"register-musician\">"
."<input type=\"button\" "
."value=\"$bval\" "
."data-musician-id=\"@@key@@\" "
."title=\"$tip\" "
."name=\"registerMusician\" "
."class=\"register-musician\" />"
."</div>'"
.",'@@key@@',`PMEtable0`.`Id`)",
        'escape' => false,
        'nowrap' => true,
        'sort' =>false,
        //'php' => "AddMusician.php"
        ];
    }

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    $opts['fdd']['Name'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Surname'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['Vorname'] = [
      'tab'      => [ 'id' => 'tab-all' ],
      'name'     => $this->l->t('Forename'),
      'css'      => [ 'postfix' => ' musician-name'.' '.$addCSS ],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    if ($this->showDisabled) {
      $opts['fdd']['Disabled'] = [
        'name'     => $this->l->t('Disabled'),
        'options' => $expertMode ? 'LAVCPDF' : 'LVCPDF',
        'input'    => $expertMode ? '' : 'R',
        'select'   => 'C',
        'maxlen'   => 1,
        'sort'     => true,
        'escape'   => false,
        'sqlw'     => 'IF($val_qas = "", 0, 1)',
        'values2|CAP' => [ 1 => '' ],
        'values2|LVFD' => [ $this->l->t('false'), $this->l->t('true') ],
        'tooltip'  => $this->toolTipsService['musician-disabled'],
        'css'      => [ 'postfix' => ' musician-disabled' ],
      ];
    }

    $musInstIdx = count($opts['fdd']);
    $opts['fdd']['MusicianInstrumentJoin'] = [
      'name'   => $this->l->t('Instrument Join Pseudo Field'),
      'sql'    => 'GROUP_CONCAT(DISTINCT PMEjoin'.$musInstIdx.'.instrument_id ORDER BY PMEjoin'.$musInstIdx.'.instrument_id ASC)',
      'input'  => 'VRH',
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => self::INSTRUMENTS_JOIN,
        'column'      => 'instrument_id',
        'description' => ['columns' => 'instrument_id'],
        'join'        => '$join_table.musician_id = $main_table.Id',
      ]
    ];

    $opts['fdd']['InstrumentKey'] = [
      'name'  => $this->l->t('Instrument Key'),
      'sql'   => 'GROUP_CONCAT(DISTINCT PMEjoin'.$musInstIdx.'.id ORDER BY PMEjoin'.$musInstIdx.'.instrument_id ASC)',
      'input' => 'SRH',
      'filter' => 'having', // need "HAVING" for group by stuff
    ];

    $instIdx = count($opts['fdd']);
    $opts['fdd']['Instruments'] = [
      'tab'         => ['id' => 'orchestra'],
      'name'        => $this->l->t('Instruments'),
      'css'         => ['postfix' => ' musician-instruments tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'input'       => 'S', // skip
      'sort'        => true,
      'sql'         => 'GROUP_CONCAT(DISTINCT PMEjoin'.$instIdx.'.Id ORDER BY PMEjoin'.$instIdx.'.Id ASC)',
      //'input' => 'V', not virtual, tweaked by triggers
      'select'      => 'M',
      'filter'      => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table'       => 'Instruments',
        'column'      => 'Id',
        'description' => 'Instrument',
        'orderby'     => 'Sortierung',
        //        'groups'      => 'Familie',
        'join'        => '$join_table.Id = PMEjoin'.$musInstIdx.'.instrument_id'
      ],
    ];

    $opts['fdd']['Instruments']['values|ACP'] = array_merge(
      $opts['fdd']['Instruments']['values'],
      [ 'filters' => '$table.Disabled = 0' ]);

    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $opts['fdd']['MemberStatus'] = [
      'name'    => strval($this->l->t('Member Status')),
      'select'  => 'D',
      'maxlen'  => 128,
      'sort'    => true,
      'css'     => ['postfix' => ' memberstatus tooltip-wide'],
      'values2' => $this->memberStatusNames,
      'tooltip' => $this->toolTipsService['member-status'],
    ];

    // fetch the list of all projects in order to provide a somewhat
    // cooked filter list
    $projects =
      $this->getDatabaseRepository(Entities\Project::class)->shortDescription();
    $allProjects = $projects['projects'];
    $groupedProjects = $projects['yearByName'];
    $projects = $projects['nameByName'];

    // Dummy field in order to get the Besetzungen table for the Projects field
    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['MusikerId'] = [
      'input' => 'VH',
      'sql' => '`'.$join_table.'`.`MusikerId`',
//    'sqlw' => '`'.$join_table.'`.`MusikerId`',
      'options' => '',
      'values' => [
        'table' => 'Besetzungen',
        'column' => 'MusikerId',
        'description' => 'MusikerId',
        'join' => '$main_table.`Id` = $join_table.`MusikerId`'
      ],
    ];

    $projectsIdx = count($opts['fdd']);
    $idx = count($opts['fdd']);
    $join_table = 'PMEjoin'.$idx;
    $opts['fdd']['Projects'] = [
      'tab' => ['id' => 'orchestra'],
      'input' => 'VR',
      'options' => 'LFV',
      'select' => 'M',
      'name' => $this->l->t('Projects'),
      'sort' => true,
      'css'      => ['postfix' => ' projects tooltip-top'],
      'display|LVF' => ['popup' => 'data'],
      'sql' => "GROUP_CONCAT(DISTINCT `".$join_table."`.`Name` ORDER BY `".$join_table."`.`Name` ASC SEPARATOR ',')",
      'filter' => 'having', // need "HAVING" for group by stuff
      'values' => [
        'table' => 'Projects',
        'column' => 'Name',
        'description' => 'Name',
        'join' => '`PMEjoin'.($idx-1).'`.`ProjektId` = $join_table.`Id`',
      ],
      'values2' => $projects,
      'valueGroups' => $groupedProjects
    ];

    $opts['fdd']['MobilePhone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Mobile Phone'),
      'css'      => ['postfix' => ' phone-number'],
      'display'  => ['popup' => function($data) {
        return null;
        // if (PhoneNumbers::validate($data)) {
        //   return nl2br(PhoneNumbers::metaData());
        // } else {
        //   return null;
        // }
        }],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      ];

    $opts['fdd']['FixedLinePhone'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Fixed Line Phone'),
      'css'      => ['postfix' => ' phone-number'],
      'display'  => ['popup' => function($data) {
        return null;
        // if (PhoneNumbers::validate($data)) {
        //   return nl2br(PhoneNumbers::metaData());
        // } else {
        //     return null;
        // }
      }],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
    ];

     $opts['fdd']['Email'] = $this->defaultFDD['email'];
     $opts['fdd']['Email']['tab'] = ['id' => 'contact'];

    $opts['fdd']['Strasse'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Street'),
      'css'      => ['postfix' => ' musician-address street'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

    $opts['fdd']['Postleitzahl'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('Postal Code'),
      'css'      => ['postfix' => ' musician-address postal-code'],
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true,
    ];

    $opts['fdd']['Stadt'] = [
      'tab'      => ['id' => 'contact'],
      'name'     => $this->l->t('City'),
      'css'      => ['postfix' => ' musician-address city'],
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true,
    ];

     $countries = $this->geoCodingService->countryNames();
     $countryGroups = $this->geoCodingService->countryContinents();

    $opts['fdd']['Land'] = [
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

    $opts['fdd']['Geburtstag'] = $this->defaultFDD['birthday'];
    $opts['fdd']['Geburtstag']['tab'] = ['id' => 'miscinfo'];

    $opts['fdd']['Remarks'] = [
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

    $opts['fdd']['Sprachpräferenz'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => $this->l->t('Language'),
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values2'  => $this->findAvailableLanguages(),
    ];

//     $opts['fdd']['Insurance'] = [
//       'tab'      => ['id' => 'miscinfo'],
//       'input' => 'V',
//       'name' => $this->l->t('Instrument Insurance'),
//       'select' => 'T',
//       'options' => 'CDV',
//       'sql' => "`PMEtable0`.`Id`",
//       'escape' => false,
//       'nowrap' => true,
//       'sort' =>false,
//       'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
//         return self::instrumentInsurance($musicianId);
//       }
//       );

    $opts['fdd']['Photo'] = [
      'tab'      => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => $this->l->t('Photo'),
      'select' => 'T',
      'options' => 'APVCD',
      'sql' => '`PMEtable0`.`Id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        $stampIdx = array_search('Aktualisiert', $fds);
        $stamp = strtotime($row['qf'.$stampIdx]);
        return $this->photoImageLink($musicianId, $action, $stamp);
      },
      'css' => ['postfix' => ' photo'],
      'default' => '',
      'sort' => false
    ];

//     ///////////////////// Test

    $opts['fdd']['VCard'] = [
      'tab' => ['id' => 'miscinfo'],
      'input' => 'V',
      'name' => 'VCard',
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => '`PMEtable0`.`Id`',
      'php' => function($musicianId, $action, $k, $fds, $fdd, $row, $recordId) {
        switch($action) {
        case 'change':
        case 'display':
          $data = [];
          foreach($fds as $idx => $label) {
            $data[$label] = $row['qf'.$idx];
          }
          $this->logInfo(print_r($data, true));
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

    //////////////////////////

    $opts['fdd']['UUID'] = [
      'tab'      => ['id' => 'miscinfo'],
      'name'     => 'UUID',
      'options'  => 'AVCPDR', // auto increment
      'css'      => ['postfix' => ' musician-uuid'.' '.$addCSS],
      'sql'      => 'BIN2UUID(`PMEtable0`.`UUID`)',
      'sqlw'     => 'UUID2BIN($val_qas)',
      'select'   => 'T',
      'maxlen'   => 32,
      'sort'     => false,
    ];

    $opts['fdd']['Aktualisiert'] =
      array_merge(
        $this->defaultFDD['datetime'],
        [
          'tab' => ['id' => 'miscinfo'],
          "name" => $this->l->t("Last Updated"),
          "default" => date($this->defaultFDD['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger.
        ]
      );

    if ($this->projectMode) {
      //$key = 'qf'.$projectsIdx;
      $projects = "GROUP_CONCAT(DISTINCT `PMEjoin{$projectsIdx}`.`Name`)";
      $opts['having']['AND'] = "($projects IS NULL OR NOT FIND_IN_SET('$projectName', $projects))";
      $opts['misc']['css']['major']   = 'bulkcommit';
      $opts['labels']['Misc'] = strval($this->l->t('Add all to %s', [$projectName]));
    }

    // @@TODO oops. This will have to get marrried with interleaved ORM stuff
    $opts['triggers']['update']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['update']['before'][]  = [ $this, 'addOrChangeInstruments' ];
//     $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
//     $opts['triggers']['update']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'beforeAnythingTrimAnything' ];
    $opts['triggers']['insert']['before'][]  = [ __CLASS__, 'addUUIDTrigger' ];
//     $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';
    $opts['triggers']['insert']['after'][]  = [ $this, 'addOrChangeInstruments' ];

//     $opts['triggers']['delete']['before'][]  = 'CAFEVDB\Musicians::beforeDeleteTrigger';

    if ($this->pmeBare) {
      // disable all navigation buttons, probably for html export
      $opts['navigation'] = 'N'; // no navigation
      $opts['options'] = '';
      // Don't display special page elements
      $opts['display'] =  array_merge(
        $opts['display'],
        [
          'form'  => false,
          'query' => false,
          'sort'  => false,
          'time'  => false,
          'tabs'  => false
        ]);
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }

    if ($execute) {
      $this->execute($opts);
    } else {
      $this->pme->setOptions($opts);
    }
  }

  public function photoImageLink($musicianId, $action = 'display', $timeStamp = '')
  {
    switch ($action) {
    case 'add':
      return $this->l->t("Photos or Avatars can only be added to an existing musician's profile; please add the new musician without protrait image first.");
    case 'display':
      $url = $this->urlGenerator()->linkToRoute(
        'cafevdb.images.get',
        [ 'joinTable' => self::PHOTO_JOIN,
          'ownerId' => $musicianId ]);
      $url .= '?imageSize=1200&timoeStamp='.$timeStamp;
      $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
      $div = ''
        .'<div class="photo"><img class="cafevdb_inline_image portrait zoomable tooltip-top" src="'.$url.'" '
        .'title="'.$this->l->t("Photo, if available").'" /></div>';
      return $div;
    case 'change':
      $photoarea = ''
        .'<div id="contact_photo_upload">
  <div class="tip portrait propertycontainer tooltip-top" id="cafevdb_inline_image_wrapper" title="'
      .$this->l->t("Drop photo to upload (max %s)", [ \OCP\Util::humanFileSize(Util::maxUploadSize()) ]).'"'
        .' data-element="PHOTO">
    <ul id="phototools" class="transparent hidden contacts_property">
      <li><a class="svg delete" title="'.$this->l->t("Delete current photo").'"></a></li>
      <li><a class="svg edit" title="'.$this->l->t("Edit current photo").'"></a></li>
      <li><a class="svg upload" title="'.$this->l->t("Upload new photo").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select photo from ownCloud").'"></a></li>
    </ul>
  </div>
</div> <!-- contact_photo -->
';

      return $photoarea;
    default:
      return $this->l->t("Internal error, don't know what to do concerning photos in the given context.");
    }
  }

  /**
   * Instruments are stored in a separate pivot-table, hence we have
   * to take care of them from outside PME or use a view.
   *
   * @copydoc beforeTriggerSetTimestamp
   *
   * @todo Find out about transactions to be able to do a roll-back on
   * error.
   */
  public function addOrChangeInstruments($pme, $op, $step, &$oldValues, &$changed, &$newValues)
  {
    $this->logInfo("OLD: ".print_r($oldValues, true));
    $this->logInfo("CHANGED: ".print_r($changed, true));
    $this->logInfo("NEW: ".print_r($newValues, true));
    $this->logInfo("REQ: ".$pme->rec);
    $field = 'Instruments';
    $keyField = 'InstrumentKey';
    $key = array_search($field, $changed);
    if ($key !== false) {
      $table      = self::INSTRUMENTS_JOIN;
      $musicianId = $pme->rec;
      $oldIds     = Util::explode(',', $oldValues[$field]);
      $newIds     = Util::explode(',', $newValues[$field]);
      $oldKeys    = Util::explode(',', $oldValues[$keyField]);
      $oldRecords = array_combine($oldIds, $oldKeys);

      // we have to delete any removed instruments and to add any new instruments
      $repository = $this->getDatabaseRepository(Entities\MusicianInstrument::class);
      try {
        foreach(array_diff($oldIds, $newIds) as $id) {
          $this->remove([ 'id' => $oldRecords[$id] ]);
          $this->changeLogService->logDelete($table, 'id', [
            'id' => $oldRecords[$id],
            'musician_id' => $musicianId,
            'instrument_id' => $id
          ]);
        }
        $this->flush();
        $musician = $this->entityManager->getReference(Entities\Musician::class, [ 'id' => $musicianId ]);
        foreach(array_diff($newIds, $oldIds) as $instrumentId) {
          $instrument = $this->entityManager->getReference(Entities\Instrument::class, [ 'id' => $instrumentId ]);
          $musicianInstrument = Entities\MusicianInstrument::create()
                              ->setMusician($musician)
                              ->setInstrument($instrument);
          // @todo ranking by ordering in select
          $this->persist($musicianInstrument);
          $this->flush();
          $rec = $musicianInstrument->getId();
          if (!empty($rec)) {
            $this->changeLogService->logInsert($table, $rec, [
              'musician_id' => $musicianId,
              'instrument_id' => $instrumentId,
            ]);
          }
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        // @todo Do we want to bailout here?
        // return false;
      }
      $this->flush(); // sync with DB

      /**
       * @note Unset in particular the $changed records. Note that
       * phpMyEdit will generate a new change-set after its operations
       * have completed, so the change-log entries for the original
       * table will also be present.
       */
      unset($changed[$key]);
      unset($newValues[$field]);
      unset($newValues[$keyField]);
    }
    return true;
  }

  public static function addUUIDTrigger($pme, $op, $step, $oldvalues, &$changed, &$newvals)
  {
    $uuid = Uuid::uuid4();

    $key = 'UUID';
    $changed[] = $key;
    $newvals[$key] = $uuid;

    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
