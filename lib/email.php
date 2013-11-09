<?php

namespace
{

$incPath = __DIR__.'/../3rdparty';
set_include_path($incPath . PATH_SEPARATOR . get_include_path());
$incPath = __DIR__.'/../3rdparty/QuickForm2';
set_include_path($incPath . PATH_SEPARATOR . get_include_path());
$incPath = __DIR__.'/../3rdparty/pear/php';
set_include_path($incPath . PATH_SEPARATOR . get_include_path());

require_once('QuickForm2/DualSelect.php');
//require_once("PHPMailer/class.phpmailer.php");
require_once("PHPMailer/PHPMailerAutoload.php");
require_once("Net/IMAP.php");

}

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{
/**Wrap the email filter form into a class to make things a little
 * less crowded. This is actually not to filter emails, but rather to
 * select specific groups of musicians (depending on instrument and
 * project).
 */
class EmailFilter {

  private $projectId;   // Project id or NULL or -1 or ''
  private $project;     // Project name of NULL or ''
  private $filter;      // Current instrument filter
  private $userBase;    // Select from either project members and/or
                        // all musicians w/o project-members
  private $memberFilter;// passive, regular, soloist, conductor, temporary
  private $EmailsRecs;  // Copy of email records from CGI env
  private $emailKey;    // Key for EmailsRecs into _POST or _GET

  private $instruments; // List of instruments for filtering
  
  private $opts;        // Copy of global options

  private $NoMail;      // List of people without email
  private $EMails;      // List of people with email  
  private $EMailsDpy;   // Display list with namee an email

  // Form elements
  private $form;           // QuickForm2 form
  private $baseGroupFieldSet;
  private $userGroupSelect;
  private $memberStatusNames;
  private $byStatusSelect;
  private $filterFieldSet; // Field-set for the filter
  private $selectFieldSet; // Field-set for adressee selection
  private $dualSelect;     // QF2 dual-select
  private $filterSelect;   // Filter by instrument.

  private $submitFieldSet; // Field-set for freeze and reset buttons
  private $submitFilterFieldSet; // Field-set for freeze and reset buttons
  private $freezeFieldSet;   // Display emails as list.
  private $freezeButton;   // Display emails as list.
  private $filterResetButton;    // Reset to default state
  private $filterApplyButton;   // Guess what.
  private $nopeFieldSet;   // No email.
  private $nopeStatic;     // Actual static form

  private $frozen;         // Whether or not we are active.

  /* 
   * constructor
   */
  public function __construct(&$_opts, $action = NULL)
  {
    if (false) {
      echo '<PRE>';
      print_r($_POST);
      echo '</PRE>';
    }

    $this->frozen = false;

    $this->opts = $_opts;

    $this->projectId = Util::cgiValue('ProjectId',-1);
    $this->project   = Util::cgiValue('Project','');

    // See wether we were passed specific variables ...
    $pmepfx          = $this->opts['cgi']['prefix']['sys'];
    $this->emailKey  = $pmepfx.'mrecs';
    $this->mtabKey   = $pmepfx.'mtable';
    $this->EmailRecs = Util::cgiValue($this->emailKey, array());
    $this->userBase  = array('fromProject' => $this->projectId >= 0,
                             'exceptProject' => false);

    $table = Util::cgiValue($this->mtabKey,'');
    $this->remapEmailRecords($table);
    $this->getMemberStatusNames();

    /* At this point we have the Email-List, either in global or project
     * context, and possibly a selection of Musicians by Id from the
     * script which called us. We build a "dual-select" box where the Ids
     * in the MiscRecs field are remembered.
     */
    $this->createForm();

    if ($action) {
      $this->form->setAttribute('action', $action);
    }
  }

  /* 
   * Include hidden fields to remember stuff across submit.
   */
  public function addPersistentCGI($key, $value, &$form = NULL) {
    if (!$form) {
      $form = $this->form;
    }

    if (is_array($value)) {
      // One could recurse ...
      foreach($value as $idx => $val) {
        $this->addPersistentCGI($key.'['.$idx.']', $val, $form);
      }
    } else {
      $form->addElement('hidden', $key)->setValue($value);
    }
  }

  /* 
   * Inject sufficient "hidden" form fields to make data persistent
   * across reloads
   */
  public function emitPersistent(&$form = NULL) {
    
    $this->addPersistentCGI('ProjectId', $this->projectId, $form);
    $this->addPersistentCGI('Project', $this->project, $form);
    $this->addPersistentCGI('Template', 'email', $form);
    $this->addPersistentCGI($this->emailKey, $this->EmailRecs, $form);
    $this->addPersistentCGI('EventSelect',
                            Util::cgiValue('EventSelect', array()), $form);
    $this->addPersistentCGI('headervisibility',
                            Util::cgiValue('headervisibility', 'expanded'), $form);
  }

  /*
   * Return a string with our needed data.
   */
  public function getPersistent($moreStuff = array())
  {
    $form = new \HTML_QuickForm2('emailrecipients');
    
    $this->addPersistentCGI('ProjectId', $this->projectId, $form);
    $this->addPersistentCGI('Project', $this->project, $form);
    $this->addPersistentCGI('Template', 'email', $form);
    $this->addPersistentCGI($this->emailKey, $this->EmailRecs, $form);
    $this->addPersistentCGI('EventSelect',
                            Util::cgiValue('EventSelect', array()), $form);
    $this->addPersistentCGI('headervisibility',
                            Util::cgiValue('headervisibility', 'expanded'), $form);

    $value = $this->form->getValue();


    if (false) {
      echo '<PRE>getPersistent:';
      print_r($value);
      echo '</PRE>';
    }

    foreach (array('SelectedMusicians',
                   'InstrumentenFilter',
                   'memberStatusFilter',
                   'baseGroup') as $key) {
      if (isset($value[$key])) {
        $this->addPersistentCGI($key, $value[$key], $form);
      }
    }
    
    foreach ($moreStuff as $key => $value) {
      $this->addPersistentCGI($key, $value, $form);
    }

    $out = '';
    foreach ($form as $element) {
      $out .= $element."\n";
    }

    return $out;
  }
  
  /*
   * Fetch the list of instruments (either only for project or all)
   *
   * Also: construct the filter by instrument.
   */
  private function getInstrumentsFromDb($dbh)
  {
    // Get the current list of instruments for the filter
    if ($this->projectId >= 0 && !$this->userBase['exceptProject']) {
      $this->instruments = Instruments::fetchProjectMusiciansInstruments($this->projectId, $dbh);
    } else {
      $this->instruments = Instruments::fetch($dbh);
    }
    array_unshift($this->instruments, '*');

    /* Install the stuff into the form */
    $this->filterSelect->loadOptions(array_combine($this->instruments,
                                                   $this->instruments));        
    $value = $this->form->getValue();
    $filterInstruments =
      isset($value['InstrumentenFilter'])
      ? $value['InstrumentenFilter']
      : array('*');
    $this->filterSelect->setValue(
      array_intersect($filterInstruments, $this->instruments));
  }
    
  private function fetchInstrumentsFilter()
  {
    /* Remove instruments from the filter which are not known by the
     * current list of musicians.
     */
    $value = $this->form->getValue();
    $filterInstruments = $value['InstrumentenFilter'];

    $this->filter = array();
    foreach ($filterInstruments as $value) {
      $this->filter[] = $value;
    }
  }  

  private function getMemberStatusNames()
  {
    $dbh = mySQL::connect($this->opts);

    $memberStatus = mySQL::multiKeys('Musiker', 'MemberStatus', $dbh);
    $this->memberStatusNames = array();
    foreach ($memberStatus as $tag) {
      $this->memberStatusNames[$tag] = strval(L::t($tag));
    }
    mySQL::close($dbh);
  }
  

  private function remapEmailRecords($table)
  {
    if ($this->projectId >= 0 && $table == 'Besetzungen') {

      $dbh = mySQL::connect($this->opts);

      /*
       * This means we have been called from the "brief"
       * instrumentation view. The other possibility is to be called
       * from the detailed view, or the total view of all musicians.
       *
       * If called from the "Besetzungen" table, we remap the Id's
       * to the project view table and continue with that.
       */
      $table = $this->project.'View';
      $query = 'SELECT `Besetzungen`.`Id` AS \'BesetzungsId\',
  `'.$table.'`.`MusikerId` AS \'MusikerId\'
  FROM `'.$table.'` LEFT JOIN `Besetzungen`
  ON `'.$table.'`.`MusikerId` = `Besetzungen`.`MusikerId`
  WHERE `Besetzungen`.`ProjektId` = '.$this->projectId;

      // Fetch the result (or die) and remap the Ids
      $result = mySQL::query($query, $dbh);
      $map = array();
      while ($line = mysql_fetch_assoc($result)) {
        $map[$line['BesetzungsId']] = $line['MusikerId'];
      }
      $newEmailRecs = array();
      foreach ($this->EmailRecs as $key) {
        if (!isset($map[$key])) {
          Util::error('Musician in Besetzungen, but has no Id as Musician');
        }
        $newEmailRecs[] = $map[$key];
      }
      $this->EmailRecs = $newEmailRecs;

      mySQL::close($dbh);
    }
  }

  private function memberStatusSQLFilter()
  {
    $allStatusFlags = array_keys($this->memberStatusNames);
    $statusBlackList = array_diff($allStatusFlags, $this->memberFilter);

    // Explicitly include NULL MemberStatus (which in principle should not happen
    $filter = "AND ( `MemberStatus` IS NULL OR (1 ";
    foreach ($statusBlackList as $badStatus) {
      $filter .= " AND `MemberStatus` NOT LIKE '".$badStatus."'";
    }
    $filter .= "))";

    return $filter;
  }

  /**Fetch musicians from either the "Musiker" table or a project
   * view. Depending on the table in use, $restrict is either
   * 'Intrumente' or 'Instrument' (normally). Also, fetch all data
   * needed to do any per-recipient substitution later.
   *
   * @param[in] $dbh Data-base handle.
   *
   * @param[in] $table The table to use, either 'Musiker' or a project view.
   *
   * @param[in] $id The name of the column holding the musicians
   *                global id, this is either 'Id' (Musiker-table) or
   *                'MusikerId' (project view).
   *
   * @param[in] $restrict The filter restriction, either 'Instrument'
   *                (German singular) or 'Instrumente' (German
   *                plural).
   *
   * @param[in] $projectId Either a valid project-id, or -1 if not in
   *                "project-mode".
   *
   * @return Associative array with the keys
   * - name (full name)
   * - email 
   * - status (MemberStatus)
   * - dbdata (data as returned from the DB for variable substitution)
   */
  private function fetchMusicians($dbh, $table, $id, $restrict, $projectId)
  {
    $columnNames = array('Vorname',
                         'Name',
                         'Email',
                         'Telefon',
                         'Telefon2',
                         'Strasse',
                         'Postleitzahl',
                         'Stadt',
                         'Land',
                         'MemberStatus');
    $sep = '`,`';
    $fields = '`'.$id.$sep.implode($sep, $columnNames).'`';
    $query = 'SELECT '.$fields.' FROM ('.$table.') WHERE
       ( ';
    foreach ($this->filter as $value) {
      if ($value == '*') {
        $query .= "1 OR\n";
      } else {
        $query .= "`".$restrict."` LIKE '%".$value."%' OR\n";
      }
    }
    $query .= "0 )";

    /* Don't bother any conductor etc. with mass-email. */
    $query .= $this->memberStatusSQLFilter();

    if (false) {
      echo '<PRE>';
      echo $query;
      echo '</PRE>';
    }

    // Fetch the result or die
    $result = mySQL::query($query, $dbh, true); // here we want to bail out on error

    /* Stuff all emails into one array for later usage, remember the Id in
     * order to combine any selection from the new "multi-select"
     * check-boxes.
     */
    while ($line = mysql_fetch_assoc($result)) {
      $name = $line['Vorname'].' '.$line['Name'];
      if ($line['Email'] != '') {
        // We allow comma separated multiple addresses
        $musmail = explode(',',$line['Email']);
        foreach ($musmail as $emailval) {
          $this->EMails[$line[$id]] =
            array('email'   => $emailval,
                  'name'    => $name,
                  'status'  => $line['MemberStatus'],
                  'project' => $projectId,
                  'dbdata'  => $line);
          $this->EMailsDpy[$line[$id]] =
            htmlspecialchars($name.' <'.$emailval.'>');
        }
      } else {
        $this->NoMail[$line[$id]] = array('name' => $name);
      }
    }
  }

  /*
   * Fetch the list of musicians for the given context (project/global)
   */
  private function getMusiciansFromDB($dbh)
  {
    $this->NoMail = array();
    $this->EMails = array();
    $this->EMailsDpy = array();

    if ($this->projectId < 0) {        
      self::fetchMusicians($dbh, 'Musiker', 'Id', 'Instrumente', -1, true);
    } else {
      // Possibly add musicians from the project
      if ($this->userBase['fromProject']) {
        self::fetchMusicians($dbh,
                             '`'.$this->project.'View'.'`', 'MusikerId', 'Instrument',
                             $this->projectId, true);
      }

      // and/or not from the project
      if ($this->userBase['exceptProject']) {
        $table =
          '(SELECT a.* FROM Musiker as a
    LEFT JOIN `'.$this->project.'View'.'` as b
      ON a.Id = b.MusikerId 
      WHERE b.MusikerId IS NULL) as c';
        self::fetchMusicians($dbh, $table, 'Id', 'Instrumente', -1, true);
      }

      // And otherwise leave it empty ;)
    }

    // Finally sort the display array
    asort($this->EMailsDpy);
  }

  private function defaultByStatus()
  {
    $byStatusDefault = array('regular');
    if ($this->projectId >= 0) {
      $byStatusDefault[] = 'passive';
      $byStatusDefault[] = 'temporary';
    }
    return $byStatusDefault;
  }  

  /*
   * Generate a QF2 form
   */
  private function createForm()
  {
    $this->form = new \HTML_QuickForm2('emailrecipients');

    $byStatusDefault = $this->defaultByStatus();

    /* Add any variables we want to keep
     */
    /* Selected recipient */
    $this->form->addDataSource(
      new \HTML_QuickForm2_DataSource_Array(
        array('SelectedMusicians' => $this->EmailRecs,
              'InstrumentenFilter' => array('*'),
              'memberStatusFilter' => $byStatusDefault,
              'baseGroup' => array(
                'selectedUserGroup' => $this->userBase))));

    $this->form->setAttribute('class', 'cafevdb-email-filter');

    /* Groups can only render field-sets well, so make things more
     * complicated than necessary
     */

    // Outer field-set with border
    $outerFS = $this->form->addElement(
      'fieldset', NULL, array('class' => 'border', 'id' => 'emailRecipientBlock'));
    $outerFS->setLabel(L::t('Select Em@il Recipients'));

    $this->baseGroupFieldSet = $outerFS->addElement('fieldset', NULL,
                                                      array('class' => 'basegroup'));

    if ($this->projectId >= 0) {
      $group = $this->userGroupSelect =
        $this->baseGroupFieldSet->addElement('group', 'baseGroup');
      $group->setLabel(L::t('Principal Address Collection'));
      $check = $group->addElement(
        'checkbox', 'selectedUserGroup[fromProject]',
        array('value' => true,
              'class' => 'selectfromproject',
              'title' => 'Auswahl aus den registrierten Musikern für das Projekt.'));
      $check->setContent('<span class="selectfromproject">&isin; '.$this->project.'</span>');
      //$check->setAttribute('checked');
        
      $check = $group->addElement(
        'checkbox', 'selectedUserGroup[exceptProject]',
        array('value' => true,
              'class' => 'selectexceptproject',
              'title' => 'Auswahl aus allen Musikern, die nicht für das Projekt registriert sind.'));
      $check->setContent('<span class="selectexceptproject">&notin; '.$this->project.'</span>');
    }

    // Optionally also include recipients which are normally disabled.
    $this->byStatusSelect = $this->baseGroupFieldSet->addElement(
      'select', 'memberStatusFilter',
      array('multiple' => 'multiple',
            'size' => 5,
            'class' => 'member-status-filter chosen-rtl',
            'title' => L::t('Select recipients by member status.'),
            'data-placeholder' => L::t('Select Members by Status')),
      array('label' => L::t('Member-Status'),
            'options' => $this->memberStatusNames));
    //$this->byStatusSelect->setValue($byStatusDefault);

    $this->selectFieldSet = $outerFS->addElement('fieldset', NULL, array());
    $this->selectFieldSet->setAttribute('class', 'select');

    $fromToImg = \OCP\Util::imagePath('core', 'actions/play-next.svg');
    $toFromImg = \OCP\Util::imagePath('core', 'actions/play-previous.svg');
      
    $this->dualSelect = $this->selectFieldSet->addElement(
      'dualselect', 'SelectedMusicians',
      array('size' => 18, 'class' => 'dualselect'),
      array('options'    => $this->EMailsDpy,
            'keepSorted' => true,
            'from_to'    => array(
              //'content' => ' &gt;&gt; ',
              'content' => '<img class="svg" src="'.$fromToImg.'" alt=" &lt&lt; " />', 
              'attributes' => array('class' => 'transfer')),
            'to_from'    => array(
              //'content' => ' &lt&lt; ',
              'content' => '<img class="svg" src="'.$toFromImg.'" alt=" &lt&lt; " />', 
              'attributes' => array('class' => 'transfer'))
        )
      );

    $this->dualSelect->setLabel(
      array(
        L::t('Email Recipients'),
        L::t('Remaining Recipients'),
        L::t('Selected Recipients')));

    if (false) {
      $this->dualSelect->addRule(
        'required', 'Bitte wenigstens einen Adressaten wählen', 1,
        \HTML_QuickForm2_Rule::ONBLUR_CLIENT_SERVER);
    }

    $this->filterFieldSet = $outerFS->addElement('fieldset', NULL,
                                                 array('class' => 'filter'));

    $this->filterSelect = $this->filterFieldSet->addElement(
      'select', 'InstrumentenFilter',
      array('multiple' => 'multiple', 'size' => 18, 'class' => 'filter'),
      array('label' => L::t('Instrument-Filter'),
            'options' => array('*' => '*')));

    /******** Submit buttons follow *******/

    $this->submitFieldSet = $outerFS->addElement(
      'fieldset', NULL, array('class' => 'submit'));

    $this->freezeFieldSet = $this->submitFieldSet->addElement(
      'fieldset', NULL, array('class' => 'freeze'));

    $this->freezeButton = $this->freezeFieldSet->addElement(
      'submit', 'writeMail',
      array('value' => L::t('Compose Em@il'),
            'title' => 'Beendet die Musiker-Auswahl
und aktiviert den Editor'));

    $this->submitFilterFieldSet = $this->submitFieldSet->addElement(
      'fieldset', NULL, array('class' => 'filtersubmit'));

    $this->filterApplyButton = $this->submitFilterFieldSet->addElement(
      'submit', 'filterApply',
      array('value' => L::t('Apply Filter'),
            'class' => 'apply',
            'title' => 'Instrumenten- und Musiker-Fundus-Filter anwenden.'));

    $this->filterResetButton = $this->submitFilterFieldSet->addElement(
      'submit', 'filterReset',
      array('value' => L::t('Reset Filter'),
            'class' => 'reset',
            'title' => 'Von vorne mit den Anfangswerten.'));

    /********** Add a pseudo-form for people without email *************/

    $this->nopeFieldSet =
      $this->form->addElement(
        'fieldset', 'NoEm@il', array('class' => 'border'));
    $this->nopeFieldSet->setLabel(L::t('Musicians without Em@il'));
    $this->nopeStatic = $this->nopeFieldSet->addElement('static', 'NoEm@il', NULL,
                                                        array('tagName' => 'div'));
  }

  /* Add a static "dummy"-form or people without email */
  private function updateNoEmailForm() 
  {    

    if (count($this->NoMail) > 0) {
      $data = '<PRE>';
      $data .= L::t("Count: ").count($this->NoMail)."\n";
      foreach($this->NoMail as $value) {
        $data .= htmlspecialchars($value['name'])."\n";
      }
      $data.= '</PRE>';
    
      if (!$this->form->getElementById($this->nopeFieldSet->getId())) {
        $this->form->appendChild($this->nopeFieldSet);
      }
      $this->nopeStatic->setContent($data);
    } elseif ($this->form->getElementById($this->nopeFieldSet->getId())) {
      $this->form->removeChild($this->nopeFieldSet);
    }
  }
  
  /*
   * Are we frozen, i.e. ready to send?
   */
  public function isFrozen() 
  {
    return $this->frozen;
  }

  /*
   * Return an array with the filtered Email addresses
   *
   * value['SelectedMusicians'] contains the form-data, this->EMails
   * the stuff from the data-base
   */
  public function getEmails()
  {
    $EMails = array();
    $values = $this->form->getValue();
    if (isset($values['SelectedMusicians'])) {
      foreach ($values['SelectedMusicians'] as $key) {
        $EMails[] = $this->EMails[$key];
      }
    }
    return $EMails;
  }

  /**Decode the check-boxes which select the set of users we
   * consider basically.
   */
  private function getUserBase()
  {
    $this->userBase['fromProject']   = false;
    $this->userBase['exceptProject'] = false;
    $values = $this->form->getValue();
    if (isset($values['baseGroup'])) {
      if (isset($values['baseGroup']['selectedUserGroup']['fromProject'])) {
        $this->userBase['fromProject'] = true;
      }
      if (isset($values['baseGroup']['selectedUserGroup']['exceptProject'])) {
        $this->userBase['exceptProject'] = true;
      }
    }
  }

  /**Decode the member-status filter
   */
  private function getMemberStatusFilter()
  {
    $values = $this->form->getValue();
    if (isset($values['memberStatusFilter'])) {
      $this->memberFilter = $values['memberStatusFilter'];
    } else {
      $this->memberFilter = array();
    }
  }

  /*
   * Let it run; 
   */
  public function execute()
  {
    if (true) {

      $this->getUserBase();
      $this->getMemberStatusFilter();

      $dbh = mySQL::connect($this->opts);

      $this->getInstrumentsFromDB($dbh);
      $this->fetchInstrumentsFilter();
      $this->getMusiciansFromDB($dbh);

      mySQL::close($dbh);

      /* Now we need to reinstall the musicians into dualSelect */
      $this->dualSelect->loadOptions($this->EMailsDpy);
        
      /* Also update the "no email" notice. */
      $this->updateNoEmailForm();
    }

    $value = $this->form->getValue();

    if (false) {
      echo '<PRE>';
      print_r($value);
      print_r($_POST);
      echo '</PRE>';
    }

    // outputting form values
    if ($this->form->validate()) {

      /*
       * We implement two further POST action for communication with
       * other forms:
       *
       * eraseAll -> if set, restart with default
       *
       * modifyAdresses -> set, then read the list of musicians by
       * hand and initialize the quick-form class accordingly
       * 
       */
      if (Util::cgiValue('modifyAddresses','') != '') {

        /* Nothing to do. */
          
      } elseif (Util::cgiValue('eraseAll','') != '' || !empty($value['filterReset'])) {
        $this->dualSelect->toggleFrozen(false);
        $this->frozen = false;

        /* Reset the musician filter */
        $this->userBase['fromProject']   = $this->projectId >= 0;
        $this->userBase['exceptProject'] = false;
        if ($this->projectId >= 0) {
          $this->userGroupSelect->setValue(
            array('selectedBaseGroup' => $this->userBase));
        }

        /* Ok, this means we must re-fetch some stuff from the DB */
        $dbh = mySQL::connect($this->opts);

        $this->getInstrumentsFromDB($dbh);
        $this->filterSelect->setValue(array('*'));
        $this->fetchInstrumentsFilter();
        $this->getMusiciansFromDB($dbh);

        mySQL::close($dbh);

        /* Now we need to reinstall the musicians into dualSelect */
        $this->dualSelect->loadOptions($this->EMailsDpy);
        $this->dualSelect->setValue($this->EmailRecs);

        /* Also update the "no email" notice. */
        $this->updateNoEmailForm();

        /* Install default "no-email" stuff */
        $this->byStatusSelect->setvalue($this->defaultByStatus());

      } elseif (!empty($value['writeMail']) ||
                Util::cgiValue('sendEmail') ||
                Util::cgiValue('deleteAttachment')) {
        $this->frozen = true;
        $this->dualSelect->toggleFrozen(true);
        $this->filterFieldSet->toggleFrozen(true);
        if ($this->projectId >= 0) {
          $this->baseGroupFieldSet->toggleFrozen(true);
        }
            
        $this->freezeFieldSet->removeChild($this->freezeButton);
        $this->submitFilterFieldSet->removeChild($this->filterResetButton);
        $this->submitFilterFieldSet->removeChild($this->filterApplyButton);
        if (false && $this->form->getElementById($this->nopeFieldSet->getId())) {
          $this->form->removeChild($this->nopeFieldSet);
        }
          
      }
    }

    // Emit persistent values possibly only after updating the form
    $this->emitPersistent();   
  }
  

  public function render() 
  {
    $renderer = \HTML_QuickForm2_Renderer::factory('default');
    
    $this->form->render($renderer);
    echo $renderer->getJavascriptBuilder()->getLibraries(true, true);
    echo $renderer;
  }

};

/**One further class which actually only acts as kind of a namespace
 * and contains one monolythic static function display().
 */
class Email
{
  const CSS_PREFIX         = 'cafevdb-email';
  const DEFAULT_TEMPLATE = 'Liebe Musiker,
<p>
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
<p>
Mit den besten Grüßen,
<p>
Euer Camerata Vorstand (${GLOBAL::ORGANIZER})
<p>
P.s.:
Sie erhalten diese Email, weil Sie schon einmal mit dem Orchester
Camerata Academica Freiburg musiziert haben. Wenn wir Sie aus unserer Datenbank
löschen sollen, teilen Sie uns das bitte kurz mit, indem Sie entsprechend
auf diese Email antworten. Wir entschuldigen uns in diesem Fall für die
Störung.';
  const MEMBERVARIABLES = '
VORNAME
NAME
EMAIL
TELEFON_1
TELEFON_2
STRASSE
PLZ
STADT
LAND
';
  const MEMBERCOLUMNS = '
Vorname
Name
Email
Telefon
Telefon2
Strasse
Postleitzahl
Stadt
Land
';

  private static $constructionMode = true;

  private $initialTemplate;
  private $catchAllEmail;
  private $catchAllName;
  private $projectId;
  private $opts;
  private $user;
  private $vorstand;

  // Message specific stuff
  private $sender;
  private $senderEmail;
  private $mailTag;
  private $subject;
  private $CC;
  private $BCC;
  private $message;
  private $fileAttach;
  private $deleteAttachment;

  function __construct($user, $templateText = NULL) {
    $this->user = $user;

    if (!is_null($templateText) && $templateText != '') {
      $this->initialTemplate = $templateText;
    } else {
      $this->initialTemplate = self::DEFAULT_TEMPLATE;
    }

    Config::init();

    self::$constructionMode = Config::$opts['emailtestmode'] != 'off';

    if (self::$constructionMode) {      
      $this->catchAllEmail = Config::getValue('emailtestaddress');
      $this->catchAllName  = 'Bilbo Baggins';
    } else {
      $this->catchAllEmail = Config::getValue('emailfromaddress');
      $this->catchAllName  = Config::getValue('emailfromname');
    }    

    $this->projectId = Util::cgiValue('ProjectId', -1);
    $this->project   = Util::cgiValue('Project','');
  }

  /**Return an associative array with keys and column names for the
   * values (Name, Stadt etc.) for substituting per-member data.
   */
  private function emailMemberVariables()
  {
    $vars   = preg_split('/\s+/', trim(self::MEMBERVARIABLES));
    $values = preg_split('/\s+/', trim(self::MEMBERCOLUMNS));
    return array_combine($vars, $values);
  }

  /**Compose an associative array with keys and values for global
   * variables which do not depend on the specific recipient.
   */
  private function emailGlobalVariables()
  {
    $globalVars = array('ORGANIZER' => $this->fetchVorstand());

    return $globalVars;
  }

  /**Fetch the pre-names of the members of the organizing committee in
   * order to construct an up-to-date greeting.
   */
  private function fetchVorstand()
  {
    $handle = mySQL::connect($this->opts);

    $query = "SELECT `Vorname` FROM `VorstandView` ORDER BY `Reihung`,`Stimmführer`,`Vorname`";

    $result = mySQL::query($query, $handle);
    
    $vorstand = array();
    while ($line = mysql_fetch_assoc($result)) {
      $vorstand[] = $line['Vorname'];
    }

    mySQL::close($handle);

    $cnt = count($vorstand);
    $text = $vorstand[0];
    for ($i = 1; $i < $cnt-1; $i++) {
      $text .= ', '.$vorstand[$i];
    }
    $text .= ' '.L::t('and').' '.$vorstand[$cnt-1];

    return $text;
  }
  

  public function headerText()
  {
    $string = '';
    if (self::$constructionMode) {
      $string .=<<<__EOT__
        <H1>Testbetrieb. Email geht nur an mich.</H1>
        <H4>Ausgenommen Cc:. Bitte um Testmail von eurer Seite.</H4>
__EOT__;
    }
    $string .= '<H2>Email export and simple mass-mail web-form ';
    if ($this->project != '') {
      $string .= 'for project '.$this->project.'</H2>';
    } else {
      $string .= 'for all musicians</H2>';
    }
    $string .=<<< __EOT__
<H4>
Der Editor und die Adress-Auswahl sind wechselseitig ausgeschaltet.
Um Adressen oder Text- nachzubearbeiten, auf den entsprechenden
Button klicken; man kann mehrfach hin- und herwechseln, ohne dass
die jeweiligen Eingaben verloren gehen. Der Instrumentenfilter ist
"destruktiv": das Abwählen des Filters restauriert nicht die
vorherige Adressenauswahl. Der "Abbrechen"-Button unter dem Editor
setzt alles wieder auf die Default-Einstellungen.
</H4>
<P>
ReturnTo: und Sender: sind
<TT>@OUREMAIL@</TT>. Die Adresse
<TT>@OUREMAIL@</TT> erhälte eine Kopie um Missbrauch durch "Einbrecher" 
abzufangen. Außerdem werden die Emails in der Datenbank gespeichert.
__EOT__;

    // replace some tokens.
    $string = str_replace('@OUREMAIL@', $this->catchAllEmail, $string);

    return $string;
  }

  static private function textMessage($htmlMessage)
  {
    $h2t = new \html2text($htmlMessage);
    $h2t->set_encoding('utf-8');
    return $h2t->get_text();
  }

  /**Delete all temorary files not found in $fileAttach. If the file
   * is successfully removed, then it is also remove from the
   * config-space.
   *
   * @param[in] $fileAttach List of files @b not to be removed.
   *
   * @return Undefined.
   */
  private static function cleanTemporaries($fileAttach = array())
  {
    // Fetch the files from the config-space
    $tmpFiles = self::fetchTemporaries();

    $toKeep = array();
    foreach ($fileAttach as $files) {
      $tmp = $files['tmp_name'];
      if (is_file($tmp)) {
        $toKeep[] = $tmp;
      }
    }
      
    foreach ($tmpFiles as $key => $tmpFile) {
      if (array_search($tmpFile, $toKeep) !== false) {
        continue;
      }
      @unlink($tmpFile);
      if (!@is_file($tmpFile)) {
        unset($tmpFiles[$key]);
      }
    }
    self::storeTemporaries($tmpFiles);
  }
    
  /**Fetch the list of temporaries from the config-space.
   */
  private static function fetchTemporaries()
  {
    // Remember the file in the data-base for cleaning up later
    $tmpFiles = Config::getUserValue('attachments','');
    $tmpFiles = preg_split('@,@', $tmpFiles, NULL, PREG_SPLIT_NO_EMPTY);
      
    return $tmpFiles;
  }

  /**Store a list of temporaries in the config-space.*/
  private static function storeTemporaries($tmpFiles)
  {
    // Remember the file in the data-base for cleaning up later
    Config::setUserValue('attachments',implode(',',$tmpFiles));
  }
    
  /**Handle file uploads. In order for upload to survive we have to
   * move them to an alternate location. And clean up afterwards, of
   * course. We store the generated temporaries in the user
   * config-space in order to (latest) remove them on logout/login.
   *
   * @param[in,out] $file Typically $_FILES['fileAttach'], but maybe
   * any file record.
   *
   * @return Copy of $file with changed temporary file which
   * survives script-reload, or @c false on error.
   */
  public static function saveAttachment($fileRecord, $local = false)
  {
    if ($fileRecord['name'] != '') {
      $tmpdir = ini_get('upload_tmp_dir');
      if ($tmpdir == '') {
        $tmpdir = sys_get_temp_dir();
      }
      $tmpFile = tempnam($tmpdir, Config::APP_NAME);
      if ($tmpFile === false) {
        return false;
      }

      // Remember the file in the data-base for cleaning up later
      $tmpFiles = self::fetchTemporaries();
      $tmpFiles[] = $tmpFile;
      self::storeTemporaries($tmpFiles);
        
      if ($local) {
        // Move the uploaded file
        if (move_uploaded_file($fileRecord['tmp_name'], $tmpFile)) {
          // Sanitize permissions
          chmod($tmpFile, 0600);
          
          // Remember the uploaded file.
          $fileRecord['tmp_name'] = $tmpFile;
          
          return $fileRecord;
        }
      } else {
        // Make a copy
        if (copy($fileRecord['tmp_name'], $tmpFile)) {
          // Sanitize permissions
          chmod($tmpFile, 0600);
          
          // Remember the uploaded file.
          $fileRecord['tmp_name'] = $tmpFile;
          
          return $fileRecord;
        }
      }

      // Clean up.
      unlink($tmpFile);
      $tmpFiles = self::fetchTemporaries();
      if (($key = array_search($tmpFile, $tmpFiles)) !== false) {
        unset($tmpFiles[$key]);
        self::storeTemporaries($tmpFiles);
      }
      return false;
    }
    return false;
  }

  /*
   *
   *
   *******************************************************************************/

  /**Display the email-form
   *
   * @bug Most of this stuff should be moved to the templates folder.
   */
  public function display()
  {
    $this->opts = Config::$pmeopts;

    Util::disableEnterSubmit(); // ? needed ???

    // Display a filter dialog
    $filter = new EmailFilter($this->opts, $this->opts['page_name']);

    $filter->execute();

    /************************************************************************
     *
     * Initialize some global stuff for the email form. Need to do this
     * before rendering the address selection stuff.
     *
     */

    if ($this->projectId < 0 || $this->project == '') {
      $this->mailTag = '[CAF-Musiker]';
    } else {
      $this->mailTag = '[CAF-'.$this->project.']';
    }

    $emailPosts = array(
      'txtSubject' => '',
      'txtCC' => '',
      'txtBCC' => '',
      'txtFromName' => $this->catchAllName,
      'txtDescription' => $this->initialTemplate);

    $doResetAll = Util::cgiValue('eraseAll', false);
      
    if ($doResetAll !== false) {
      /* Take everything to its defaults */
      foreach ($emailPosts as $key => $value) {
        $_POST[$key] = $value; // cheat
      }
      if (isset($_FILES['fileAttach'])) {
        unset($_FILES['fileAttach']);
      }
      if (isset($_POST['fileAttach'])) {
        unset($_POST['fileAttach']);
      }
      self::cleanTemporaries();
    }

    // Perhaps this should go to the constructor. ;)
    $this->subject          = Util::cgiValue('txtSubject', $emailPosts['txtSubject']);
    $this->message          = Util::cgiValue('txtDescription', $emailPosts['txtDescription']);
    $this->sender           = Util::cgiValue('txtFromName', $emailPosts['txtFromName']);
    $this->CC               = Util::cgiValue('txtCC', $emailPosts['txtCC']);
    $this->BCC              = Util::cgiValue('txtBCC', $emailPosts['txtBCC']);
    $this->senderEmail      = $this->catchAllEmail; // always
    $this->fileAttach       = Util::cgiValue('fileAttach', array());
    $this->deleteAttachment = Util::cgiValue('deleteAttachment', array());

    /* Determine whether we have to deal with template email, i.e.:
     * variable substitutions. Substitutions are something like
     * ${VARNAME}.
     */
    $templateMail     = false;
    $templateLeftOver = array();
    if (preg_match('![$]{MEMBER::[^{]+}!', $this->message)) {
      // Fine, we have substitutions. We should now verify that we
      // only have _legal_ substitutions. There are probably more
      // clever ways to do this, but at this point we simply
      // substitute any legal variable by DUMMY and check that no
      // unknown ${...} substitution tag remains. Mmmh.

      $dummy = $this->message;
      $variables = $this->emailMemberVariables();
      foreach ($variables as $placeholder => $column) {
        $dummy = preg_replace('/[$]{MEMBER::'.$placeholder.'}/', $column, $dummy);
      }

      if (preg_match('![$]{MEMBER::[^{]+}!', $dummy, $templateLeftOver)) {
        $templateMail = 'error';
      } else {
        $templateMail = true;
      }
    }

    if (isset($this->fileAttach[-1])) {
      $newRecord = $this->fileAttach[-1];
      unset($this->fileAttach[-1]);
      $this->fileAttach[] = $newRecord;
    }

    foreach ($this->deleteAttachment as $tmpName) {
      foreach ($this->fileAttach as $key => $fileRecord) {
        if ($fileRecord['tmp_name'] == $tmpName) {
          unset($this->fileAttach[$key]);
        } 
      }
    }

    self::cleanTemporaries($this->fileAttach);

    if (false) {
      echo '<pre>';
      print_r($this->fileAttach);
      print_r($_FILES);        
      echo '</pre>';
    }

    /*
     *
     *
     ************************************************************************/

    /*************************************************************************
     *
     * Display the address selection from if it is not frozen, other after the email editor.
     *
     */

    if (!$filter->isFrozen()) {
      /* Add all of the above to the form, if it is active */

      $filter->addPersistentCGI('txtSubject', $this->subject);
      $filter->addPersistentCGI('txtDescription', $this->message);
      $filter->addPersistentCGI('txtFromName', $this->sender);
      $filter->addPersistentCGI('txtCC', $this->CC);
      $filter->addPersistentCGI('txtBCC', $this->BCC);
      $filter->addPersistentCGI('fileAttach', $this->fileAttach);

      $filter->render(); // else render below the Email editor
    }

    /*
     *
     *
     *******************************************************************************************/

    /******************************************************************************************
     *
     * If sending requested and basic stuff is missing, display the
     * corresponding error messages here; because at the bottom of the
     * page they may be outside of the viewable region.
     *
     */

    else if (Util::cgiValue('sendEmail', false) !== false) {
      echo '<div class="cafevdb-error" id="cafevdb-email-error"></div>';
    }

    /*
     *
     *
     ************************************************************************/

    /*************************************************************************
     * Now define one huge form. This is really somewhat ugly.
     * Also: we cheat: pretend we are quick-form to simplify the look
     *
     */

    /**** start quick-form cheat ****/
    echo '<div class="quickform">';
    /*******************************/
    echo '
<FORM METHOD="post" ACTION="'.$this->opts['page_name'].'" NAME="Email" enctype="multipart/form-data" class="cafevdb-email-form">';
    /**** start quick-form cheat ****/

    /* Remember address filter for later */
    echo $filter->getPersistent(array('fileAttach' => $this->fileAttach));

    $eventAttachButton = '';
    $attachedEvents = '';
    if ($this->projectId >= 0) {
      $EventSelect = Util::cgiValue('EventSelect', array());
      $eventAttachButton = Projects::eventButton(
        $this->projectId, $this->project, L::t('Events'), $EventSelect);
      if (!empty($EventSelect)) {
        $attachedEvents = ''
          .'<tr class="eventattachments"><td>'.L::t('Attached Events').'</td>'
          .'<td colspan="2"><span id="eventattachments">';
        $locale = Util::getLocale();
        foreach ($EventSelect as $id) {
          $event = Events::fetchEvent($id);
          $brief =
            $event['summary'].', '.
            Events::briefEventDate($event, $locale);

          $attachedEvents .= ''
            .'<button '
            .'type="button" '
            .'title="'.L::t('Edit Event %s',array($brief)).'" '
            .'class="eventattachments edit" '
            .'id="eventattachment-'.$id.'" '
            .'name="eventattachment[]" '
            .'value="'.$id.'" '
            .'>'
            .'<img '
            .'alt="'.$id.'" '
            .'src="'.\OCP\Util::imagePath('calendar', 'icon.svg').'" '
            .'class="svg events small" '
            .'/>'
            .'</button>';
        }
        $attachedEvents .= '</span></td></tr>';
      }
    }

    echo sprintf('
  <fieldset %s id="cafevdb-email-form" class="border"><legend id="cafevdb-email-form-legend">Em@il Verfassen</legend>',$filter->isFrozen() ? '' : 'disabled');
      /*******************************/
      echo '
  <TABLE class="cafevdb-email-form">
  <tr>
     <td>'.L::t('Recipients').'</td>
     <td colspan="2">'.L::t('Determined automatically from data-base, see below the email form.').'</td>
  </tr>
  <tr>
     <td>Carbon Copy</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($this->CC).'" name="txtCC" type="text" id="txtCC"></td>
  </tr>
  <tr>
     <td>Blind CC</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($this->BCC).'" name="txtBCC" type="text" id="txtBCC"></td>
  </tr>
  <tr>
     <td>'.L::t('Subject').'</td>
     <td colspan="2" class="subject">'.
      '<span class="subject tag">'.htmlspecialchars($this->mailTag).'</span>'.
        '<input value="'.$this->subject.'" size="40" name="txtSubject" type="text" id="txtSubject"></td>
  </tr>
  <tr>
    <td class="body">'.L::t('Message-Body').'</td>
    <td colspan="2"><textarea name="txtDescription" cols="20" rows="4" id="txtDescription">'.$this->message.'</textarea></td>
  </tr>
  <tr>
    <td>'.L::t('Sender-Name').'</td>
    <td colspan="2"><input value="'.$this->sender.'" size="40" value="CAFEV" name="txtFromName" type="text"></td>
  </tr>
  <tr>
  <tr>
    <td>'.L::t('Sender-Email').'</td>
    <td colspan="2">'.L::t('Tied to').' "'.$this->catchAllEmail.'"</td>
  </tr>
  <tr class="attachments">
    <td class="attachments">'.L::t('Add Attachment').'</td>
    <td class="attachments" colspan="2">
      '.$eventAttachButton.'
      <button type="button" '
      .'class="attachment upload" '
         .'title="'.Config::toolTips('upload-attachment').'" '
         .'value="'.L::t('Upload new File').'">
        <img src="'.\OCP\Util::imagePath('core', 'actions/upload.svg').'" alt="'.L::t('Upload new File').'"/>
      </button>
      <button type="button" '
      .'class="attachment owncloud" '
         .'title="'.Config::toolTips('owncloud-attachment').'" '
         .'value="'.L::t('Select from Owncloud').'">
        <img src="'.\OCP\Util::imagePath('core', 'places/file.svg').'" alt="'.L::t('Select from Owncloud').'"/>
      </button>
    </td>
  </tr>'
         .$attachedEvents;
      foreach ($this->fileAttach as $attachment) {
        $tmpName = $attachment['tmp_name'];
        $name    = $attachment['name'];
        $size    = $attachment['size'];
        $size    = \OC_Helper::humanFileSize($size);
        echo ''
          .'  <tr>'
          .'    <td><button type="submit" name="deleteAttachment[]" value="'.$tmpName.'" >'.L::t('Remove').'</button></td>'
          .'    <td colspan="2"><span class="attachmentName">'.$name.' ('.$size.')</span></td>'
          .'  </tr>';
      }
      $submitString = '
  <tr class="submit">
    <td class="send">
      <input %1$s title="Vorsicht!"
      type="submit" name="sendEmail" value="'.L::t('Send Em@il').'"/></td>
    <td class="addresses">
      <input %1$s title="Der Nachrichten-Inhalt
bleibt erhalten." type="submit" name="modifyAddresses" value="'.L::t('Edit recipients').'"/>
   </td>
   <td class="reset">
       <input %1$s title="Abbrechen und von
vorne Anfangen, bereits
veränderter Text geht
verloren." type="submit" name="eraseAll" value="'.L::t('Cancel').'" />
     </td>
  </tr>';
      echo sprintf($submitString, $filter->isFrozen() ? '' : 'disabled');
      echo '
  </table>';
      echo '</fieldset></FORM></div>
';

      /*
       *
       *
       ***********************************************************************/

      /************************************************************************
       *
       * If the filter is frozen, display it now, otherwise the editor
       * window would be at the bottom of some large address list.
       *
       */

      if ($filter->isFrozen()) {
        $filter->render(); // else render below the Email editor
      }

      /************************************************************************
       *
       * Now maybe send an email, if requested ...
       *
       */

      // Now: do we want to send an Email?
      if (Util::cgiValue('sendEmail',false) === false) {
        // not yet.
        return true;
      }

      // So place the spam ...

      date_default_timezone_set(@date_default_timezone_get());

      // See what we finally have ...
      $EMails = $filter->getEmails();

      // For archieving
      $MailAddrStr = '';
      foreach ($EMails as $value) {
        $MailAddrStr .=
          ''
          .htmlspecialchars($value['name'])
          .'&lt;'
          .htmlspecialchars($value['email'])
          .'&gt;'
          .'<BR/>';
      }

      // Perform sanity checks before spamming ...
      $DataValid = true;
      if ($templateMail === 'error') {
        Util::alert(L::t('Invalid template mail, first unknown left-over parameter is %s',
                         array($templateLeftOver[0])),
                    L::t('Unknown Variable'),
                    'cafevdb-emai-error');
        $DataValid = false;
      }

      if (empty($EMails)) {
        Util::alert(L::t('No recipients specified, you possibly forgot to shift items from the left select box (potential recipients) to the right select box (actual recipients). Pleas click on the `modify recipients\' button and specify some recipients.'),
                    L::t('No recipients'),
                    'cafevdb-email-error');
        $DataValid = false;
      }

      if ($this->subject == '') {
        Util::alert(L::t('The subject must not consist of `%s\' as only part.<br/>'.
                         'Please correct that and then hit the `Send\'-button again.',
                         array($this->mailTag)),
                    L::t('Incomplete Subject'),
                    'cafevdb-email-error');
        $DataValid = false;
      }
      if ($this->sender == '') {
        Util::alert(L::t('The sender-name should not be empty.<br/>'.
                         'Please correct that and then hit the `Send\'-button again.'),
                    L::t('Descriptive Sender Name'),
                    'cafevdb-email-error');
        $DataValid = false;
      }

      if (!$DataValid) {
        return false;
      }

      $strMessage = nl2br($this->message);
      
      if (preg_match('![$]{GLOBAL::[^{]+}!', $this->message)) {
        $vars = $this->emailGlobalVariables();
        
        // TODO: one call to preg_replace would be enough, but does
        // not really matter as long as there is only one global
        // variable.
        foreach ($vars as $key => $value) {
          $strMessage = preg_replace('/[$]{GLOBAL::'.$key.'}/', $value, $strMessage);
        }
      }

      if ($templateMail === true) {
        // Template emails are emails with per-member variable
        // substitutions. This means that we cannot send one email to
        // all recipients, but have to send different emails one by
        // one. This has some implicatios:
        //
        // - extra recipients added through the Cc: and Bcc: fields
        //   and the catch-all address is not added to each
        //   email. Instead, we send out the template without
        //   substitutions, and also only copy this template to the
        //   "Sent"-Folder on the imap server.
        //
        // - still each single email is logged to the DB in order to
        //  catch duplicates.
        //
        // - after variable substitution we need to reencode some
        // - special characters.
        $templateMessage = $strMessage;
        $variables = $this->emailMemberVariables();
        

        foreach ($EMails as $recipient) {
          $dbdata = $recipient['dbdata'];
          $strMessage = $templateMessage;
          foreach ($variables as $placeholder => $column) {
            $strMessage = preg_replace('/[$]{MEMBER::'.$placeholder.'}/',
                                       htmlspecialchars($dbdata[$column]),
                                       $strMessage);
          }
          $this->composeAndSend($strMessage, array($recipient), false);
        }
        // Finally send one message without template substitution (as
        // this makes no sense) to all Cc:, Bcc: recipients and the
        // catch-all. This Message also gets copied to the Sent-folder
        // on the imap server.
        $msg = $this->composeAndSend($templateMessage, array(), true);
        if ($msg !== false) {
          copyToSentFolder($msg);
        }
      } else {
        $msg = $this->composeAndSend($strMessage, $EMails);
        if ($msg !== false) {
          copyToSentFolder($msg);
        }
      }
  }

  /**Compose and send one message. If $EMails only contains one
   * address, then the emails goes out using To: and Cc: fields,
   * otherwise Bcc: is used, unless sending to the recipients of a
   * project. All emails are logged with an MD5-sum to the DB in order
   * to prevent duplicate mass-emails. If a duplicate is detected the
   * message is not sent out. A duplicate is something with the same
   * message text and the same recipient list.
   *
   * @param[in] $strMessage The message to send.
   *
   * @param[in] $EMails The recipient list
   *
   * @param[in] $addCC If @c false, then additional CC and BCC recipients will
   *                   not be added.
   * 
   * @return The sent Mime-message which then may be stored in the
   * Sent-Folder on the imap server (for example).
   */
  private function composeAndSend($strMessage, $EMails, $addCC = true)
  {
    // If we are sending to a single address (i.e. if $strMessage has
    // been constructed with per-member variable substitution), then
    // we do not need to send via BCC.
    $singleAddress = count($EMails) == 1;

    // One big try-catch block. Using exceptions we do not need to
    // keep track of all return values, which is quite beneficial
    // here. Some of the stuff below clearly cannot throw, but then
    // it doesn't hurt to keep it in the try-block. All data is
    // added in the try block. There is another try-catch-construct
    // surrounding the actual sending of the message.
    try {

      $mail = new \PHPMailer(true);
      $mail->CharSet = 'utf-8';
      $mail->SingleTo = false;

      // Setup the mail server for testing
      // $mail->IsSMTP();
      //$mail->IsMail();
      $mail->IsSMTP();
      if (true) {
        $mail->Host = Config::getValue('smtpserver');
        $mail->Port = Config::getValue('smtpport');
        switch (Config::getValue('smtpsecure')) {
        case 'insecure': $mail->SMTPSecure = ''; break;
        case 'starttls': $mail->SMTPSecure = 'tls'; break;
        case 'ssl':      $mail->SMTPSecure = 'ssl'; break;
        default:         $mail->SMTPSecure = ''; break;
        }
        $mail->SMTPAuth = true;
        $mail->Username = Config::getValue('emailuser');
        $mail->Password = Config::getValue('emailpassword');
      }
        
      $mail->Subject = $this->mailTag . ' ' . $this->subject;
      $mail->msgHTML($strMessage, true);

      $mail->AddReplyTo($this->senderEmail, $this->sender);
      $mail->SetFrom($this->senderEmail, $this->sender);

      if (!self::$constructionMode) {
        // Loop over all data-base records and add each recipient in turn
        foreach ($EMails as $recipient) {
          if ($singleAddress) {
            $mail->AddAddress($recipient['email'], $recipient['name']);
          } else if ($recipient['project'] < 0) {
            // blind copy, don't expose the victim to the others.
            $mail->AddBCC($recipient['email'], $recipient['name']);
          } else {
            // Well, people subscribing to one of our projects
            // simply must not complain, except soloist or
            // conductors which normally are not bothered with
            // mass-email at all, but if so, then they are added as Bcc
            if ($recipient['status'] == 'conductor' ||
                $recipient['status'] == 'soloist') {
              $mail->AddBCC($recipient['email'], $recipient['name']);
            } else {
              $mail->AddAddress($recipient['email'], $recipient['name']);
            }
          }
        }
      } else {
        // Construction mode: per force only send to the developer
        $mail->AddAddress($this->catchAllEmail, $this->catchAllName);
      }

      if ($addCC === true) {
        // Always drop a copy to the orchestra's email account for
        // archiving purposes and to catch illegal usage. It is legel
        // to modify $this->sender through the email-form.
        $mail->AddCC($this->catchAllEmail, $this->sender);
      }
        
      // If we have further Cc's, then add them also
      if ($addCC === true && $this->CC != '') {
        // Now comes some dirty work: we need to split the string in
        // names and email addresses. We re-construct $this->CC in this
        // context, to normalize it for storage in the email-log.
  
        $arrayCC = self::parseAddrListToArray($this->CC);
        if (Util::debugMode()) {
          echo "<PRE>\n";
          print_r($arrayCC);
          echo "</PRE>\n";
        }
  
        foreach ($arrayCC as $value) {
          $this->CC .= $value['name'].' <'.$value['email'].'>,';
          // PHP-Mailer adds " for itself as needed
          $value['name'] = trim($value['name'], '"');
          $mail->AddCC($value['email'], $value['name']);
        }
        $this->CC = trim($this->CC, ',');
      }

      // Do the same for Bcc
      if ($addCC === true && $this->BCC != '') {
        // Now comes some dirty work: we need to split the string in
        // names and email addresses.
  
        $arrayBCC = self::parseAddrListToArray($this->BCC);
        if (Util::debugMode()) {
          echo "<PRE>\n";
          print_r($arrayBCC);
          echo "</PRE>\n";
        }
  
        $this->BCC = '';
        foreach ($arrayBCC as $value) {
          $this->BCC .= $value['name'].' <'.$value['email'].'>,';
          // PHP-Mailer adds " for itself as needed
          $value['name'] = trim($value['name'], '"');
          $mail->AddBCC($value['email'], $value['name']);
        }
        $this->BCC = trim($this->BCC, ',');
      }

      // Add all registered attachments.
      foreach ($this->fileAttach as $attachment) {
        $mail->AddAttachment($attachment['tmp_name'],
                             $attachment['name'],
                             'base64',
                             $attachment['type']);
          
      }

      // Finally possibly to-be-attached events. This cannot throw,
      // but it does not hurt to keep it here. This way we are just
      // ready with adding data to the message inside the try-block.
      $EventSelect = Util::cgiValue('EventSelect', array());
      if ($this->projectId >= 0 && !empty($EventSelect)) {
        // Construct the calendar
        $calendar = Events::exportEvents($EventSelect, $this->project);
          
        // Encode it as attachment
        $mail->AddStringEmbeddedImage($calendar,
                                      md5($this->project.'.ics'),
                                      $this->project.'.ics',
                                      'quoted-printable',
                                      'text/calendar');
      }
    } catch (\Exception $exception) {
      // popup an alert and abort the form-processing
        
      $msg = $exception->getMessage();

      Util::alert(L::t('The email-backend throwed an exception stating:<br/>').
                  '"'.$msg.'"<br/>'.
                  L::t('Please correct the problem and then click on the `Send\'-button again.'),
                  L::t('Caught an exception'),
                  'cafevdb-email-error');

      return false;
    }

    // Construct the query to store the email in the data-base
    // log-table.

    // Construct one MD5 for recipients subject and html-text
    $bulkRecipients = '';
    foreach ($EMails as $pairs) {
      $bulkRecipients .= $pairs['name'].' <'.$pairs['email'].'>,';
    }
    // add CC and BCC
    if ($this->CC != '') {
      $bulkRecipients .= $this->CC.',';
    }
    if ($this->BCC != '') {
      $bulkRecipients .= $this->BCC.',';
    }
    $bulkRecipients = trim($bulkRecipients,',');
    $bulkMD5 = md5($bulkRecipients);
  
    $textforMD5 = $this->subject . $strMessage;
    $textMD5 = md5($textforMD5);
      
    // compute the MD5 stuff for the attachments
    $attachLog = array();
    foreach ($this->fileAttach as $attachment) {
      if($attachment['name'] != "") {
        $md5val = md5_file($attachment['tmp_name']);
        $attachLog[] = array('name' => $attachment['name'],
                             'md5' => $md5val);
      }
    }
      
    // Now insert the stuff into the SentEmail table  
    $handle = mySQL::connect($this->opts);

    // First make sure that we have enough columns to store the
    // attachments (better: only their checksums)

    foreach ($attachLog as $key => $value) {
      $logquery = sprintf(
        'ALTER TABLE `SentEmail`
  ADD `Attachment%02d` TEXT
  CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  ADD `MD5Attachment%02d` TEXT
  CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        $key, $key);

      // And execute. Just to make that all needed columns exist.
        
      $result = mySQL::query($logquery, $handle, false, true);
    }
      
    // Now construct the real query, but do not execute it until the
    // message has succesfully been sent.

    $logquery = "INSERT INTO `SentEmail`
(`user`,`host`,`BulkRecipients`,`MD5BulkRecipients`,`Cc`,`Bcc`,`Subject`,`HtmlBody`,`MD5Text`";
    $idx = 0;
    foreach ($attachLog as $pairs) {
      $logquery .=
        sprintf(",`Attachment%02d`,`MD5Attachment%02d`", $idx, $idx);
      $idx++;
    }

    $logquery .= ') VALUES (';
    $logquery .= "'".$this->user."','".$_SERVER['REMOTE_ADDR']."'";
    $logquery .= ",'".mysql_real_escape_string($bulkRecipients,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($bulkMD5,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($this->CC,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($this->BCC,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($this->subject,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($strMessage,$handle)."'";
    $logquery .= ",'".mysql_real_escape_string($textMD5,$handle)."'";
    foreach ($attachLog as $pairs) {
      $logquery .=
        ",'".mysql_real_escape_string($pairs['name'],$handle)."'".
        ",'".mysql_real_escape_string($pairs['md5'],$handle)."'";
    }
    $logquery .= ")";
  
    // Now logging is ready to execute. But first check for
    // duplicate sending attempts. This takes only the recipients,
    // the subject and the message body into account. Rationale: if
    // you want to send an updated attachment, then you really
    // should write a comment on that. Still the test is flaky
    // enough.

    // Check for duplicates
    $loggedquery = "SELECT * FROM `SentEmail` WHERE";
    $loggedquery .= " `MD5Text` LIKE '$textMD5'";
    $loggedquery .= " AND `MD5BulkRecipients` LIKE '$bulkMD5'";
    $result = mySQL::query($loggedquery, $handle);
  
    $cnt = 0;
    $loggedDates = '';
    if ($line = mySQL::fetch($result)) {
      $loggedDates .= ','.$line['Date'];
      ++$cnt;
    }
    $loggedDates = trim($loggedDates,',');  

    if ($loggedDates != '') {
      // Ok, we are really pissed of at this point. What the heck
      // does the user think it is :) Grin.

      Util::alert(L::t('A message with exactly the same text '.
                       'to exactly the same recipients has already '.
                       'been sent on the following date(s):<br/>'.
                       '%s<br/>'.
                       'Refusing to send duplicate bulk emails.',
                       $loggedDates),
                  L::t('Duplicate Email'), 'cafevdb-email-error');
      return false;
    }

    // Finally the point of no return. Send it out!!!
      
    try {
      if (!$mail->Send()) {
        Util::alert(L::t('Sending failed for an unknown reason. Sorry.'),
                    L::t('General Failure reading your hard-disk ... just kidding.'),
                    'cafevdb-email-error');
        return false;
      } else {
        Util::alert(L::t('Message has been sent, at least: no error from our side!'),
                    L::t('Message has been sent'),
                    'cafevdb-email-error');
        // Log the message to our data-base
        mySQL::query($logquery, $handle);  
      }
    } catch (\Exception $exception) {
      $msg = $exception->getMessage();
      Util::alert(
        L::t('During send, the email-backend throwed an exception stating:<br/>'
             .'`%s\'</br>'
             .'Please correct the problem and then click on the `Send\'-button again.',array($msg)),
        L::t('Caught an exception'),
        'cafevdb-email-error');
      return false;
    }

    mySQL::close($handle);

    return $mail->GetSentMIMEMessage();
  }

  /**Take the supplied message and copy it to the "Sent" folder.
   */
  private function copyToSentFolder($mimeMessage)
  {
    // PEAR IMAP works without the c-client library
    ini_set('error_reporting',ini_get('error_reporting') & ~E_STRICT);

    $imaphost   = Config::getValue('imapserver');
    $imapport   = Config::getValue('imapport');
    $imapsecure = Config::getValue('imapsecure');

    $imap = new \Net_IMAP($imaphost,
                          $imapport,
                          $imapsecure == 'starttls' ? true : false, 'UTF-8');
    if (($ret = $imap->login($mail->Username, $mail->Password)) !== true) {
      Util::alert(
        L::t('The IMAP backend returned the error `%s\'. Unfortunate Snafu.<br/>'.
             'I was trying to copy the message to our send-folder, but that failed.',
             array($ret->toString())),
        L::t('IMAP connection failed'),
        'cafevdb-email-error');
      $imap->disconnect();
      return false;
    }
    if (($ret = $imap->appendMessage($mail->GetSentMIMEMessage(), 'Sent')) !== true) {
      Util::alert(L::t('Could not copy the message to the "Sent"-folder.</br>'.
                       'Server returned the error: `%s\'',
                       array($ret->toString())),
                  L::t('Copying to `Sent\'-folder failed.'),
                  'cafevdb-email-error');
      $imap->disconnect();
      return false;
    }
    $imap->disconnect();
    
    if (true || Util::debugMode()) {
      // TODO: no need to copy the entire base64-encoded attachment
      // soup to the screen ...
      echo '<HR/><H4>Gesendete Email</H4>';
      echo "<PRE>\n";
      $msg = $mail->GetSentMIMEMessage();
      $msgArray = explode("\n", $msg);
      for ($i = 0; $i < min(64, count($msgArray)); $i++) {
        echo htmlspecialchars($msgArray[$i])."\n";
      }
      echo "</PRE><HR/>\n";
    }

    return true;
  }
  
  /** Split a comma separated address list into an array.
   */
  public static function parseAddrListToArray($list)
  {
    $t = str_getcsv($list);

    foreach($t as $k => $v) {
      if (strpos($v,',') !== false) {
        $t[$k] = '"'.str_replace(' <','" <',$v);
      }
    }

    foreach ($t as $addr) {
      if (strpos($addr, '<')) {
        preg_match('!(.*?)\s?<\s*(.*?)\s*>!', $addr, $matches);
        $emails[] = array(
          'email' => $matches[2],
          'name' => $matches[1]
          );
      } else {
        $emails[] = array(
          'email' => $addr,
          'name' => ''
          );
      }
    }

    return $emails;
  }

  /** Issue an error message.
   */
  public static function echoInvalid($kind, $email)
  {
    Util::alert(L::t('The %s address `%s\' seems to be invalid.<br/>'.
                     'Please correct that first and then click on the `Send\'-button again.<br/>'.
                     'Unfortunately, attachments (if any) have to be specified again.',
                     array($kind, $email)),
                L::t('Invalid Email Address'),
                'cafevdb-email-error');
    return false;
  }

  public static function addAddress($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddAddress($address, $name)) {
      self::echoInvalid(L::t('recipient'), $address);
      return false;
    }
    return true;
  }

  public static function addCC($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddCC($address, $name)) {
      self::echoInvalid('"Cc:"', $address);
      return false;
    }
    return true;
  }

  public static function addBCC($phpmailer, $address, $name = '')
  {
    if (!$phpmailer->AddBCC($address, $name)) {
      self::echoInvalid('"Bcc:"', $address);
      return false;
    }
    return true;
  }

  public static function setFrom($phpmailer, $address, $name = '')
  {
    if ($phpmailer->SetFrom($address, $name) != true) {
      self::echoInvalid('"From:"', $address);
      return false;
    }
    return true;
  }

  public static function addReplyTo($phpmailer, $address, $name = '')
  {
    if ($phpmailer->AddReplyTo($address, $name) != true) {
      self::echoInvalid('"ReplyTo:"', $address);
      return false;
    }
    return true;
  }

  public static function checkImapServer($host, $port, $secure, $user, $password)
  {
    $oldReporting = ini_get('error_reporting');
    ini_set('error_reporting', $oldReporting & ~E_STRICT);

    $imap = new \Net_IMAP($host, $port, $secure == 'starttls' ? true : false, 'UTF-8');
    $result = $imap->login($user, $password) === true;
    $imap->disconnect();

    ini_set('error_reporting', $oldReporting);
      
    return $result;
  }

  public static function checkSmtpServer($host, $port, $secure, $user, $password)
  {
    $result = true;

    $mail = new \PHPMailer(true);
    $mail->CharSet = 'utf-8';
    $mail->SingleTo = false;
    $mail->IsSMTP();

    $mail->Host = $host;
    $mail->Port = $port;
    switch ($secure) {
    case 'insecure': $mail->SMTPSecure = ''; break;
    case 'starttls': $mail->SMTPSecure = 'tls'; break;
    case 'ssl':      $mail->SMTPSecure = 'ssl'; break;
    default:         $mail->SMTPSecure = ''; break;
    }
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;
        
    try {
      $mail->SmtpConnect();
      $mail->SmtpClose();
    } catch (\Exception $exception) {
      $result = false;
    }    

    return $result;
  }
};

/**Display the email-history stored in the database.
 */
class EmailHistory
{
  const CSS_PREFIX = 'cafevdb-page';

  public static function headerText()
  {
    $result =<<<__EOT__
<h3>Massenmail-Historie.</h3>
<H4>Nat&uuml;rlich kann man einmal gesendete Email nicht mehr &auml;ndern, also kann man auch die Daten unten nicht editieren.</H4>

__EOT__;

    return $result;
  }

  /**Display the history records of all our junk-mail attempts.
   */
  public static function display()
  {
    Config::init();
    
    //$debug_query = true;

    if (isset($debug_query) && $debug_query) {
      echo "<PRE>\n";
      print_r($_POST);
      print_r($_GET);
      echo "</PRE>\n";
    }

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

    $opts = Config::$pmeopts;
    unset($opts['miscphp']);

    $opts['inc'] = 15;

    $opts['tb'] = 'SentEmail';

    $recordsPerPage = Util::cgiValue('RecordsPerPage',-1);

    $opts['cgi']['persist'] = array('Project' => $this->project,
                                    'ProjectId' => $this->projectId,
                                    'Table' => $opts['tb'],
                                    'Template' => 'email-history',
                                    'RecordsPerPage' => $recordsPerPage);

    // Name of field which is the unique key
    $opts['key'] = 'Id';

    // Type of key field (int/real/string/date etc.)
    $opts['key_type'] = 'int';

    // Sorting field(s)
    $opts['sort_field'] = array('-Date');
    
    // Options you wish to give the users
    // A - add,  C - change, P - copy, V - view, D - delete,
    // F - filter, I - initial sort suppressed
    // This is a view, undeletable.
    //  $opts['options'] = 'CPVFM';
    $opts['options'] = 'VFM';

    // Number of lines to display on multiple selection filters
    $opts['multiple'] = '6';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    //$opts['navigation'] = 'DB';

    // Display special page elements
    $opts['display'] = array(
      'form'  => true,
      'query' => true,
      'sort'  => true,
      'time'  => true,
      'tabs'  => true
      );

    // Set default prefixes for variables
    $opts['js']['prefix']               = 'PME_js_';
    $opts['dhtml']['prefix']            = 'PME_dhtml_';
    $opts['cgi']['prefix']['operation'] = 'PME_op_';
    $opts['cgi']['prefix']['sys']       = 'PME_sys_';
    $opts['cgi']['prefix']['data']      = 'PME_data_';
    
    //$opts['cgi']['append']['PME_sys_fl'] = 1;
    
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
      'name'     => 'Id',
      'select'   => 'T',
      'options'  => 'LAVCPDR', // auto increment
      'maxlen'   => 11,
      'default'  => '0',
      'sort'     => false
      );

    $opts['fdd']['Date'] = Config::$opts['datetime'];
    $opts['fdd']['Date']['name'] = 'Datum';
    $opts['fdd']['Date']['default'] = date(Config::$opts['datetime']['datemask']);
    $opts['fdd']['Date']['nowrap'] = true;
    $opts['fdd']['Date']['options'] = 'LAVCPDRF'; // Set by update trigger.
  
    $opts['fdd']['user'] = array('name'     => 'Absende-User',
                                 'select'   => 'T',
                                 'maxlen'   => 36,
                                 'sort'     => true );
    $opts['fdd']['Subject'] = array('name'     => 'Betreff',
                                    'select'   => 'T',
                                    'maxlen'   => 384,
                                    'sort'     => true );
    $opts['fdd']['BulkRecipients'] = array('name'     => 'Empf&auml;nger',
                                           'select'   => 'T',
                                           'maxlen'   => 40,
                                           'css'      => array('postfix' => 'rcpt'),
                                           'sort'     => true );
    $opts['fdd']['Cc'] = array('name'     => 'CarbonCopy',
                               'select'   => 'T',
                               'maxlen'   => 40,
                               'css'      => array('postfix' => 'cc'),
                               'sort'     => true );
    $opts['fdd']['Bcc'] = array('name'     => 'BlindCarbonCopy',
                                'select'   => 'T',
                                'maxlen'   => 40,
                                'css'      => array('postfix' => 'bcc'),
                                'sort'     => true );
    $opts['fdd']['HtmlBody'] = array('name'     => 'Inhalt',
                                     'select'   => 'T',
                                     'maxlen'   => 40,
                                     'escape'   => false,
                                     'css'      => array('postfix' => 'msg'),
                                     'sort'     => true );

    new \phpMyEdit($opts);
  }

};

} // namespace

?>