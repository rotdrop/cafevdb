<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Display all or selected musicians.
 */
class Musicians
  extends Instrumentation
{
  const CSS_PREFIX = 'cafevdb-page';
  const CSS_CLASS = 'musicians';
  private $projectMode;

  function __construct($mode = false, $execute = true) {
    parent::__construct($execute);
    $this->projectMode = $mode;
    //$this->recordsPerPage = 25;
  }

  public function shortTitle()
  {
    if ($this->deleteOperation()) {
      return L::t('Remove all data of the displayed musician?');
    } else if ($this->copyOperation()) {
      return L::t('Copy the displayed musician?');
    } else if ($this->viewOperation()) {
      return L::t('Display of all stored personal data for the shown musician.');
    } else if ($this->changeOperation()) {
      return L::t('Edit the personal data of the displayed musician.');
    } else if ($this->addOperation()) {
      return L::t('Add a new musician to the data-base.');
    } else if (!$this->projectMode) {
      return L::t('Overview over all registered musicians');
    } else {
      return L::t("Add musicians to the project `%s'", array($this->projectName));
    }
  }

  public function headerText()
  {
    $header = $this->shortTitle();
    if ($this->projectMode) {
      $header .= "
<p>
This page is the only way to add musicians to projects in order to
make sure that the musicians are also automatically added to the
`global' musicians data-base (and not only to the project).";
    }

    return '<div class="'.self::CSS_PREFIX.'-header-text">'.$header.'</div>';
  }

  /**Display the list of all musicians. If $projectMode == true,
   * filter out all musicians present in $projectId and add a
   * hyperlink which will add the Musician to the respective project.
   */
  public function display()
  {
    global $debug_query;
    $debug_query = Util::debugMode('query');

    $template        = $this->template;
    $projectName     = $this->projectName;
    $projectId       = $this->projectId;
    $recordsPerPage  = $this->recordsPerPage;
    $opts            = $this->opts;

    /*
     * IMPORTANT NOTE: This generated file contains only a subset of huge amount
     * of options that can be used with phpMyEdit. To get information about all
     * features offered by phpMyEdit, check official documentation. It is available
     * online and also for download on phpMyEdit project management page:
     *
     * http://platon.sk/projects/main_page.php?project_id=5
     *
     * This file was generated by:
     *
     *                    phpMyEdit version: 5.7.1
     *       phpMyEdit.class.php core class: 1.204
     *            phpMyEditSetup.php script: 1.50
     *              generating setup script: 1.50
     */

    $opts['tb'] = 'Musiker';

    // Number of records to display on the screen
    // Value of -1 lists all records in a table
    $opts['inc'] = $recordsPerPage;

    $opts['cgi']['persist'] = array(
      'ProjectName' => $projectName,
      'ProjectId' => $projectId,
      'Template' => $this->projectMode
      ? 'add-musicians' : 'all-musicians',
      'Table' => $opts['tb'],
      'DisplayClass' => 'Musicians',
      'ClassArguments' => array($this->projectMode));

    if ($this->projectMode) {
      $opts['cgi']['append'][Config::$pmeopts['cgi']['prefix']['sys'].'fl'] = 1;
      $opts['cgi']['overwrite'][Config::$pmeopts['cgi']['prefix']['sys'].'fl'] = 1;
    }

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('Instrumente','Name','Vorname','Id');

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
      $export = Navigation::tableExportButton();
      $opts['buttons'] = Navigation::prependTableButton($export, true);
    }

    // Display special page elements
    $opts['display'] =  array_merge(
      $opts['display'],
      array(
        'form'  => true,
        'query' => true,
        'sort'  => true,
        'time'  => true,
        'tabs'  => array(
          array('id' => 'orchestra',
                'default' => true,
                'tooltip' => Config::toolTips('musician-orchestra-tab'),
                'name' => L::t('Instruments and Status')),
          array('id' => 'contact',
                'tooltip' => Config::toolTips('musican-contact-tab'),
                'name' => L::t('Contact Information')),
          array('id' => 'miscinfo',
                'tooltip' => Config::toolTips('musician-miscinfo-tab'),
                'name' => L::t('Miscellaneous Data')),
          array('id' => 'tab-all',
                'tooltip' => Config::toolTips('pme-showall-tab'),
                'name' => L::t('Display all columns'))
          )
        )
      );

    // Set default prefixes for variables
    $opts['js']['prefix']               = 'PME_js_';
    $opts['dhtml']['prefix']            = 'PME_dhtml_';
    $opts['cgi']['prefix']['operation'] = 'PME_op_';
    $opts['cgi']['prefix']['sys']       = 'PME_sys_';
    $opts['cgi']['prefix']['data']      = 'PME_data_';

    /* Get the user's default language and use it if possible or you can
       specify particular one you want to use. Refer to official documentation
       for list of available languages. */
    //  $opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';

    /* Table-level filter capability. If set, it is included in the WHERE clause
       of any generated SELECT statement in SQL query. This gives you ability to
       work only with subset of data from table.

       $opts['filters'] = "column1 like '%11%' AND column2<17";
       $opts['filters'] = "section_id = 9";
       $opts['filters'] = "PMEtable0.sessions_count > 200";
    */

    if ($this->projectMode) {
//      $opts['filters'] = "(SELECT COUNT(*) FROM `Besetzungen` WHERE MusikerId = PMEtable0.Id AND ProjektId = $projectId) = 0";
      $opts['misc']['css']['major']   = 'bulkcommit';
      $opts['labels']['Misc'] = strval(L::t('Add all to %s', array($projectName)));
    }

    /* Field definitions

       Fields will be displayed left to right on the screen in the order in which they
       appear in generated list. Here are some most used field options documented.

       ['name'] is the title used for column headings, etc.;
       ['maxlen'] maximum length to display add/edit/search input boxes
       ['trimlen'] maximum length of string content to display in row listing
       ['width'] is an optional display width specification for the column
       e.g.  ['width'] = '100px';
       ['mask'] a string that is used by sprintf() to format field output
       ['sort'] true or false; means the users may sort the display on this column
       ['strip_tags'] true or false; whether to strip tags from content
       ['nowrap'] true or false; whether this field should get a NOWRAP
       ['select'] T - text, N - numeric, D - drop-down, M - multiple selection
       ['options'] optional parameter to control whether a field is displayed
       L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
       Another flags are:
       R - indicates that a field is read only
       W - indicates that a field is a password field
       H - indicates that a field is to be hidden and marked as hidden
       ['URL'] is used to make a field 'clickable' in the display
       e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
       ['URLtarget']  HTML target link specification (for example: _blank)
       ['textarea']['rows'] and/or ['textarea']['cols']
       specifies a textarea is to be used to give multi-line input
       e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
       ['values'] restricts user input to the specified constants,
       e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
       ['values']['table'] and ['values']['column'] restricts user input
       to the values found in the specified column of another table
       ['values']['description'] = 'desc_column'
       The optional ['values']['description'] field allows the value(s) displayed
       to the user to be different to those in the ['values']['column'] field.
       This is useful for giving more meaning to column values. Multiple
       descriptions fields are also possible. Check documentation for this.
    */

    $opts['fdd']['Id'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'name'     => 'Id',
      'select'   => 'T',
      'options'  => 'AVCPDR', // auto increment
      'maxlen'   => 5,
      'align'    => 'right',
      'default'  => '0',
      'sort'     => true
      );

    $bval = strval(L::t('Add to %s', array($projectName)));
    $tip  = strval(Config::toolTips('register-musician'));
    if ($this->projectMode) {
      $opts['fdd']['AddMusicians'] = array(
        'tab' => array('id' => 'orchestra'),
        'name' => L::t('Add Musicians'),
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
        );
    }

    if ($this->addOperation()) {
      $addCSS = 'add-musician';
    } else {
      $addCSS = '';
    }

    $opts['fdd']['Name'] = array(
      'tab'      => array('id' => 'tab-all'),
      'name'     => L::t('Surname'),
      'css'      => array('postfix' => ' musician-name'.' '.$addCSS),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Vorname'] = array(
      'tab'      => array('id' => 'tab-all'),
      'name'     => L::t('Forename'),
      'css'      => array('postfix' => ' musician-name'.' '.$addCSS),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Instrumente'] = array(
      'tab'         => array('id' => 'orchestra'),
      'name'        => L::t('Instruments'),
      'css'         => array('postfix' => ' musician-instruments tipsy-se'),
      'display|LF'  => array('popup' => 'data'),
      'select'      => 'M',
      'maxlen'      => 137,
      'sort'        => true,
      'values'      => $this->instruments,
      'valueGroups' => $this->groupedInstruments,
      );
    /* Make "Status" a set, 'soloist','conductor','noemail', where in
     * general the first two imply the last.
     */
    $opts['fdd']['MemberStatus'] = array('name'    => strval(L::t('Member Status')),
                                         'select'  => 'D',
                                         'maxlen'  => 128,
                                         'sort'    => true,
                                         'css'     => array('postfix' => ' memberstatus'),
                                         'values2' => $this->memberStatusNames,
                                         'tooltip' => config::toolTips('member-status'));

    // fetch the list of all projects in order to provide a somewhat
    // cooked filter list
    $allProjects = Projects::fetchProjects(false /* no db handle */, true /* include years */);
    $projectQueryValues = array('*' => '*'); // catch-all filter
    $projectQueryValues[''] = L::t('no projects yet');
    foreach ($allProjects as $proj) {
      $projectQueryValues[$proj['Name']] = $proj['Jahr'].': '.$proj['Name'];
    }

    $derivedtable =<<<__EOT__
SELECT MusikerId,GROUP_CONCAT(DISTINCT Projekte.Name ORDER BY Projekte.Name ASC SEPARATOR ', ') AS Projekte FROM
Besetzungen
LEFT JOIN Projekte ON Projekte.Id = Besetzungen.ProjektId
GROUP BY MusikerId
__EOT__;

    $projectsIdx = count($opts['fdd']);
    if ($this->projectMode) {
      $opts['cgi']['persist']['ProjectMode'] = true;
      if (!Util::cgiValue('ProjectMode', false)) {
        // start initially filtered, but let the user choose other things.
        $pfx = Config::$pmeopts['cgi']['prefix']['sys'];
        $key = 'qf'.$projectsIdx;
        $opts['cgi']['append'][$pfx.$key.'_id'] = array($projectName);
        $opts['cgi']['append'][$pfx.$key.'_comp'] = array('not');
      }
    }

    $opts['fdd']['Projekte'] =
      array(
        'tab'      => array('id' => 'orchestra'),
        'input' => 'VR', // virtual, read perm
        'options' => 'LFV', // List View and Filter
        'select' => 'M',
        'name' => L::t('Projects'),
        'sort' => true,
        'sql' => 'PMEjoin'.$projectsIdx.'.Projekte',
        'sqlw' => 'PMEjoin'.$projectsIdx.'.Projekte',
        'css'      => array('postfix' => ' projects tipsy-se'),
        'display|LVF' => array('popup' => 'data'),
        'values' => array( //API for currently making a join in PME.
          'table' =>
          array('sql' => $derivedtable,
                'kind' => 'derived'),
          'column' => 'MusikerId',
          'join' => '$main_table.Id = $join_table.MusikerId',
          'description' => 'Projekte',
          'queryvalues' => $projectQueryValues
          ),
        );

    $opts['fdd']['MobilePhone'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Mobile Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['FixedLinePhone'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => L::t('Fixed Line Phone'),
      'css'      => array('postfix' => ' phone-number'),
      'display'  => array('popup' => function($data) {
          if (PhoneNumbers::validate($data)) {
            return nl2br(PhoneNumbers::metaData());
          } else {
            return null;
          }
        }),
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Email'] = Config::$opts['email'];
    $opts['fdd']['Email']['tab'] = array('id' => 'contact');

    $opts['fdd']['Strasse'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => 'Strasse',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Postleitzahl'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => 'Postleitzahl',
      'select'   => 'T',
      'maxlen'   => 11,
      'sort'     => true
      );

    $opts['fdd']['Stadt'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => 'Stadt',
      'select'   => 'T',
      'maxlen'   => 128,
      'sort'     => true
      );

    $opts['fdd']['Land'] = array(
      'tab'      => array('id' => 'contact'),
      'name'     => 'Land',
      'select'   => 'T',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true);

    $opts['fdd']['Geburtstag'] = Config::$opts['birthday'];
    $opts['fdd']['Geburtstag']['tab'] = array('id' => 'miscinfo');

    $opts['fdd']['Remarks'] = array(
      'tab'      => array('id' => 'orchestra'),
      'name'     => strval(L::t('Remarks')),
      'select'   => 'T',
      'maxlen'   => 65535,
      'css'      => array('postfix' => ' remarks tipsy-se'),
      'textarea' => array('css' => 'wysiwygeditor',
                          'rows' => 5,
                          'cols' => 50),
      'display|LF' => array('popup' => 'data'),
      'escape' => false,
      'sort'     => true);

    $opts['fdd']['Sprachpräferenz'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'name'     => 'Spachpräferenz',
      'select'   => 'D',
      'maxlen'   => 128,
      'default'  => 'Deutschland',
      'sort'     => true,
      'values2'   => Config::$opts['languages']);

    $opts['fdd']['Insurance'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'input' => 'V',
      'name' => L::t('Instrument Insurance'),
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => "Id",
      'escape' => false,
      'nowrap' => true,
      'sort' =>false,
      'php' => array(
        'type' => 'function',
        'function' => 'CAFEVDB\Musicians::instrumentInsurancePME',
        'parameters' => array()
        )
      );

    $opts['fdd']['Portrait'] = array(
      'tab'      => array('id' => 'miscinfo'),
      'input' => 'V',
      'name' => L::t('Photo'),
      'select' => 'T',
      'options' => 'ACPDV',
      'sql' => 'Id',
      'php' => array(
        'type' => 'function',
        'function' => 'CAFEVDB\Musicians::portraitImageLinkPME',
        'parameters' => array()
        ),
      'css' => array('postfix' => ' photo'),
      'default' => '',
      'sort' => false);

    $opts['fdd']['Aktualisiert'] =
      array_merge(
        Config::$opts['datetime'],
        array(
          'tab' => array('id' => 'miscinfo'),
          "name" => L::t("Last Updated"),
          "default" => date(Config::$opts['datetime']['datemask']),
          "nowrap" => true,
          "options" => 'LFAVCPDR' // Set by update trigger.
          )
        );

    $opts['triggers']['update']['before'] = array();
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Util::beforeUpdateRemoveUnchanged';
    $opts['triggers']['update']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    $opts['triggers']['insert']['before'] = array();
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Util::beforeAnythingTrimAnything';
    $opts['triggers']['insert']['before'][]  = 'CAFEVDB\Musicians::beforeTriggerSetTimestamp';

    if ($this->pme_bare) {
      // disable all navigation buttons, probably for html export
      $opts['navigation'] = 'N'; // no navigation
      $opts['options'] = '';
      // Don't display special page elements
      $opts['display'] =  array_merge($opts['display'],
                                      array(
                                        'form'  => false,
                                        'query' => false,
                                        'sort'  => false,
                                        'time'  => false,
                                        'tabs'  => false
                                        ));
      // Disable sorting buttons
      foreach ($opts['fdd'] as $key => $value) {
        $opts['fdd'][$key]['sort'] = false;
      }
    }

    $opts['execute'] = $this->execute;

    $this->pme = new \phpMyEdit($opts);

    if ($this->execute) {
      // Photo upload support:
      echo '
<form class="float"
      id="file_upload_form"
      action="'.\OCP\Util::linkTo('cafevdb', 'ajax/inlineimage/uploadimage.php').'"
      method="post"
      enctype="multipart/form-data"
      target="file_upload_target">
  <input type="hidden" name="requesttoken" value="'.\OCP\Util::callRegister().'">
  <input type="hidden" name="RecordId" value="'.Util::getCGIRecordId().'">
  <input type="hidden" name="ImagePHPClass" value="CAFEVDB\Musicians">
  <input type="hidden" name="ImageSize" value="1200">
  <input type="hidden" name="MAX_FILE_SIZE" value="'.Util::maxUploadSize().'" id="max_upload">
  <input type="hidden" class="max_human_file_size" value="max '.\OCP\Util::humanFileSize(Util::maxUploadSize()).'">
  <input id="file_upload_start" type="file" accept="image/*" name="imagefile" />
</form>

<div id="edit_photo_dialog" title="Edit photo">
		<div id="edit_photo_dialog_img"></div>
</div>
';
    }

    if (Util::debugMode('request')) {
      echo '<PRE>';
      print_r($_POST);
      echo '</PRE>';
    }

  } // display()

  /** phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeTriggerSetTimestamp($pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    if (count($changed) > 0) {
      $key = 'Aktualisiert';
      $changed[] = $key;
      $newvals[$key] = date(\CAFEVDB\mySQL::DATEMASK);
    }
    echo '<!-- '.print_r($newvals, true).'-->';
    return true;
  }

  public static function instrumentInsurancePME($musicianId, $opts, $action, $k, $fds, $fdd, $row)
  {
    return self::instrumentInsurance($musicianId, $opts);
  }

  public static function instrumentInsurance($musicianId, $opts)
  {
    $amount = InstrumentInsurance::insuranceAmount($musicianId);
    $fee    = InstrumentInsurance::annualFee($musicianId);
    $bval = L::t('Total Amount %02.02f &euro;, Annual Fee %02.02f &euro;',
                 array($amount, $fee));
    $tip = strval(Config::toolTips('musician-instrument-insurance'));
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

  public static function portraitImageLinkPME($musicianId, $opts, $action, $k, $fds, $fdd, $row)
  {
    return self::portraitImageLink($musicianId, $action);
  }

  public static function portraitImageLink($musicianId, $action = 'display')
  {
    switch ($action) {
    case 'add':
      return L::t("Portraits or Avatars can only be added to an existing musician's profile; please add the new musician without protrait image first.");
    case 'display':
      $div = ''
        .'<div class="photo"><img class="cafevdb_inline_image portrait zoomable" src="'
        .\OCP\UTIL::linkTo('cafevdb', 'inlineimage.php').'?RecordId='.$musicianId.'&ImagePHPClass=CAFEVDB\Musicians&ImageSize=1200'
        .'" '
        .'title="Photo, if available" /></div>';
      return $div;
    case 'change':
      $photoarea = ''
        .'<div id="contact_photo">

  <iframe name="file_upload_target" id=\'file_upload_target\' src=""></iframe>
  <div class="tip portrait propertycontainer" id="cafevdb_inline_image_wrapper" title="'
      .L::t("Drop photo to upload (max %s)", array(\OCP\Util::humanFileSize(Util::maxUploadSize()))).'"'
        .' data-element="PHOTO">
    <ul id="phototools" class="transparent hidden contacts_property">
      <li><a class="svg delete" title="'.L::t("Delete current photo").'"></a></li>
      <li><a class="svg edit" title="'.L::t("Edit current photo").'"></a></li>
      <li><a class="svg upload" title="'.L::t("Upload new photo").'"></a></li>
      <li><a class="svg cloud icon-cloud" title="'.L::t("Select photo from ownCloud").'"></a></li>
    </ul>
  </div>
</div> <!-- contact_photo -->
';

      return $photoarea;
    default:
      return L::t("Internal error, don't know what to do concerning portrait images in the given context.");
    }
  }

  public static function imagePlaceHolder()
  {
    // could probably also check for browser support for svg here
    return 'person_large.png';
  }


  public static function fetchImage($musicianId, $handle = false)
  {
    $photo = '';

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT `PhotoData` FROM `MemberPortraits` WHERE `MemberId` = ".$musicianId;

    $result = mySQL::query($query, $handle);

    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
      if (isset($row['PhotoData'])) {
        $photo = $row['PhotoData'];
      }
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $photo;
  }

  /**Take a BASE64 encoded photo and store it in the DB.
   */
  public static function storeImage($musicianId, $photo, $handle = false)
  {
    if (!isset($photo) || $photo == '') {
      return;
    }

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "INSERT INTO `MemberPortraits`
  (`MemberId`,`PhotoData`) VALUES (".$musicianId.",'".$photo."')
  ON DUPLICATE KEY UPDATE `PhotoData` = '".$photo."';";

    $result = mySQL::query($query, $handle) && self::storeModified($musicianId, $handle);

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $result;
  }

  public static function deleteImage($musicianId, $handle = false)
  {
    $photo = '';

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "DELETE IGNORE FROM `MemberPortraits` WHERE `MemberId` = ".$musicianId;

    $result = mySQL::query($query, $handle) && self::storeModified($musicianId, $handle);

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $result;
  }

  public static function storeModified($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "UPDATE IGNORE `Musiker`
    SET `Aktualisiert` = '".date(mySQL::DATEMASK)."'
    WHERE `Id` = ".$musicianId;

    $result = mySQL::query($query, $handle);

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $result;
  }

  public static function fetchModified($musicianId, $handle = false)
  {
    $modified = 0;

    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT `Aktualisiert` FROM `Musiker` WHERE `Id` = ".$musicianId.";";

    $result = mySQL::query($query, $handle);
    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
      if (isset($row['Aktualisiert'])) {
        $modified = strtotime($row['Aktualisiert']);
      }
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $modified;
  }

  /**Fetch all known data from the Musiker table for the respective musician.  */
  public static function fetchMusicianPersonalData($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT * FROM `Musiker` WHERE `Id` = $musicianId";

    $result = mySQL::query($query, $handle);
    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
    } else {
      $row = false;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /**In principle a musician can have multiple entries per
   * project. Unique is only the combination
   * project-musician-instrument-position. In principle, if a musician
   * plays more than one instrument in different pieces in a project,
   * he or she could be listed twice.
   */
  public static function fetchMusicianProjectData($musicianId, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = " SELECT *
 FROM `Besetzungen`
     WHERE `Besetzungen`.`MusikerId` = $musicianId
       AND `Besetzungen`.`ProjektId` = $projectId";

    $result = mySQL::query($query, $handle);
    if ($result !== false) {
      $rows = array();
      while ($row = mySQL::fetch($result)) {
        $rows[] = $row;
      }
    } else {
      $rows = false;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $rows;
  }

  /**Fetch the street address of the respected musician. Needed in
   * order to generate automated snail-mails.
   *
   * Return value is a flat array:
   *
   * array('firstName' => ...,
   *       'surName' => ...,
   *       'street' => ...,
   *       'city' => ...,
   *       'ZIP' => ...);
   */
  public static function fetchStreetAddress($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query =
      'SELECT '.
      '`Name` AS `surName`'.
      ', '.
      '`Vorname` AS `firstName`'.
      ', '.
      '`Strasse` AS `street`'.
      ', '.
      '`Stadt` AS `city`'.
      ', '.
      '`Postleitzahl` AS `ZIP`'.
      ', '.
      '`Telefon` AS `phone`';
    $query .= ' FROM `Musiker` WHERE `Id` = '.$musicianId;
    $result = mySQL::query($query, $handle);

    $row = false;
    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /** Fetch the musician-name name corresponding to $musicianId.
   */
  public static function fetchName($musicianId, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = 'SELECT `Name`,`Vorname`,`Email` FROM `Musiker` WHERE `Id` = '.$musicianId;
    $result = mySQL::query($query, $handle);

    $row = false;
    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return array('firstName' => (isset($row['Vorname']) && $row['Vorname'] != '') ? $row['Vorname'] : 'X',
                 'lastName' => (isset($row['Name']) && $row['Name'] != '') ? $row['Name'] : 'X',
                 'email' => (isset($row['Email']) && $row['Email'] != '') ? $row['Email'] : 'X');
  }

  /** Fetch the entire mess of duplicate musicians by name.
   */
  public static function musiciansByName($firstName, $surName, $handle = false)
  {
    $ownConnection = $handle === false;
    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = "SELECT * FROM `Musiker` WHERE `Name` = '".$surName."' AND `Vorname` = '".$firstName."'";
    $result = mySQL::query($query, $handle);

    $musicians = array();
    while ($row = mySQL::fetch($result)) {
      $musicians[] = $row;
    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $musicians;
  }

};

} // namespace CAFEVDB

?>
