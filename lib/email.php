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
  require_once("PHPMailer/class.phpmailer.php");
  require_once("Net/IMAP.php");

}

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
    private $EmailsRecs;  // Copy of email records from CGI env
    private $emailKey;    // Key for EmailsRecs into _POST or _GET

    private $table;       // Table-name, either Musiker of some project view
    private $MId;         // Key for the musicians id, Id, or MusikerId
    private $restrict;    // Instrument filter keyword, Instrument or ...s
    private $instruments; // List of instruments for filtering
  
    private $opts;        // Copy of global options

    private $NoMail;      // List of people without email
    private $EMails;      // List of people with email  
    private $EMailsDpy;   // Display list with namee an email

    // Form elements
    private $form;           // QuickForm2 form
    private $baseGroupFieldSet;
    private $userGroupSelect;
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
      $this->EmailRecs = Util::cgiValue($this->emailKey,array());
      $this->table     = Util::cgiValue($this->mtabKey,'');
      $this->userBase  = array('fromProject' => $this->projectId >= 0,
                               'exceptProject' => false);

      $this->remapEmailRecords();

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
    public function getPersistent()
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

      if (isset($value['SelectedMusicians'])) {
        $this->addPersistentCGI('SelectedMusicians', $value['SelectedMusicians'], $form);
      }
      if (isset($value['InstrumentenFilter'])) {
        $this->addPersistentCGI('InstrumentenFilter', $value['InstrumentenFilter'], $form);
      }
      if (isset($value['baseGroup'])) {
        $this->addPersistentCGI('baseGroup', $value['baseGroup'], $form);
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
      $filterInstruments = $value['InstrumentenFilter'];
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

    private function remapEmailRecords($dbh = false)
    {
      if ($this->projectId >= 0 &&
          $this->table == 'Besetzungen') {

        $ownConnection = false;
        if ($dbh === false) {
          $dbh = mySQL::connect($this->opts);
          $ownConnection = true;  
        }

        /*
         * This means we have been called from the "brief"
         * instrumentation view. The other possibility is to be called
         * from the detailed view, or the total view of all musicians.
         *
         * If called from the "Besetzungen" table, we remap the Id's
         * to the project view table and continue with that.
         */
        $this->table = $this->project.'View';
        $query = 'SELECT `Besetzungen`.`Id` AS \'BesetzungsId\',
  `'.$this->table.'`.`MusikerId` AS \'MusikerId\'
  FROM `'.$this->table.'` LEFT JOIN `Besetzungen`
  ON `'.$this->table.'`.`MusikerId` = `Besetzungen`.`MusikerId`
  WHERE `Besetzungen`.`ProjektId` = '.$this->projectId;

        // Fetch the result or die and remap the Ids
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

        if ($ownConnection) {
          mySQL::close($dbh);
        }
      }
    }

    /*
     * Fetch the list of musicians for the given context (project/global)
     */
    private function getMusiciansFromDB($dbh)
    {
      /*** Now continue as if from 'ordinary' View-Table ****/

      if ($this->projectId >= 0) {
        if ($this->userBase['fromProject'] &&
            $this->userBase['exceptProject']) {
          $this->table    = 'Musiker';
          $this->MId      = 'Id';
          $this->Restrict = 'Instrumente';
        } else if ($this->userBase['fromProject']) {
          $this->table = $this->project.'View';
          $this->MId      = 'MusikerId';
          $this->Restrict = 'Instrument';
        } else if ($this->userBase['exceptProject']) {
          $this->table =
'(SELECT a.* FROM Musiker as a
    LEFT JOIN '.$this->project.'View'.' as b
      ON a.Id = b.MusikerId 
      WHERE b.MusikerId IS NULL) as c';
          $this->MId      = 'Id';
          $this->Restrict = 'Instrumente';
        } else {
          $this->table = '';
          $this->NoMail = array();
          $this->EMails = array();
          $this->EMailsDpy = array();
          return;
        }
      } else {
        $this->table    = 'Musiker';
        $this->MId      = 'Id';
        $this->Restrict = 'Instrumente';
      }

      $query = 'SELECT `'.$this->MId.'`,`Vorname`,`Name`,`Email` FROM '.$this->table.' WHERE
       ( ';
      foreach ($this->filter as $value) {
        if ($value == '*') {
          $query .= "1 OR\n";
        } else {
          $query .= "`".$this->Restrict."` LIKE '%".$value."%' OR\n";
        }
      }
      /* Don't bother any conductor with mass-email. */
      $query .= "0 ) AND NOT `".$this->Restrict."` LIKE '%Taktstock%'\n";

      // Fetch the result or die
      $result = mySQL::query($query, $dbh);

      /* Stuff all emails into one array for later usage, remember the Id in
       * order to combine any selection from the new "multi-select"
       * check-boxes.
       */
      $this->NoMail = array();
      $this->EMails = array();
      $this->EMailsDpy = array();
      while ($line = mysql_fetch_assoc($result)) {
        $name = $line['Vorname'].' '.$line['Name'];
        if ($line['Email'] != '') {
          // We allow comma separated multiple addresses
          $musmail = explode(',',$line['Email']);
          foreach ($musmail as $emailval) {
            $this->EMails[$line[$this->MId]] =
              array('email' => $emailval, 'name' => $name);
            $this->EMailsDpy[$line[$this->MId]] =
              htmlspecialchars($name.' <'.$emailval.'>');
          }
        } else {
          $this->NoMail[$line[$this->MId]] = array('name' => $name);
        }
      }
      /* Sort EMailsDpy according to its values */
      asort($this->EMailsDpy);
    }
  
    /*
     * Generate a QF2 form
     */
    private function createForm()
    {
      $this->form = new \HTML_QuickForm2('emailrecipients');

      /* Add any variables we want to keep
       */
      /* Selected recipient */
      $this->form->addDataSource(
        new \HTML_QuickForm2_DataSource_Array(
          array('SelectedMusicians' => $this->EmailRecs,
                'InstrumentenFilter' => array('*'),
                'baseGroup' => array(
                  'selectedUserGroup' => $this->userBase))));

      $this->form->setAttribute('class', 'cafevdb-email-filter');

      /* Groups can only render field-sets well, so make thing more
       * complicated than necessary
       */

      // Outer field-set with border
      $outerFS = $this->form->addElement('fieldset');      
      $outerFS->setLabel(L::t('Select Em@il Recipients'));

      if ($this->projectId >= 0) {
        $this->baseGroupFieldSet = $outerFS->addElement('fieldset', NULL,
                                                        array('class' => 'basegroup'));
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

      $this->filterFieldSet = $outerFS->addElement('fieldset', NULL, array());
      $this->filterFieldSet->setAttribute('class', 'filter');

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
        $this->form->addElement('fieldset', 'NoEm@il');
      $this->nopeFieldSet->setLabel(L::t('Musicians without Em@il'));
      $this->nopeStatic = $this->nopeFieldSet->addElement('static', 'NoEm@il', NULL,
                                                          array('tagName' => 'div'));
    }

    /* Add a static "dummy"-form or people without email */
    private function updateNoEmailForm() 
    {    

      if (count($this->NoMail) > 0) {
        $data = '<PRE>';
        $data .= "Count: ".count($this->NoMail)."\n";
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
      $values = $this->form->getValue();
      $EMails = array();
      foreach ($values['SelectedMusicians'] as $key) {
        $EMails[] = $this->EMails[$key];
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

    /*
     * Let it run; 
     */
    public function execute()
    {
      if (true) {

        $this->getUserBase();
        
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
          $this->userGroupSelect->setValue(
            array('selectedBaseGroup' => $this->userBase));

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
        } elseif (!empty($value['writeMail']) || Util::cgiValue('sendEmail')) {
          if (Util::cgiValue('sendEmail')) {
            // Re-install the filter from the form
            //$this->dualSelect->toggleFrozen(false);
            //$this->dualSelect->setValue(Util::cgiValue('SelectedMusicians'), array());
          }
          $this->frozen = true;
          $this->dualSelect->toggleFrozen(true);
          $this->filterFieldSet->toggleFrozen(true);
          $this->baseGroupFieldSet->toggleFrozen(true);
            
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
    const CONSTRUCTION_MODE  = true;
    const CONSTRUCTION_EMAIL = 'DEVELOPER@his.server.eu';
    const PRODUCTION_EMAIL   = 'orchestra@example.eu';

    public static function headerText()
    {
      $projectId = Util::cgiValue('ProjectId',-1);
      $project   = Util::cgiValue('Project','');

      $CAFEVCatchAllEmail =
        self::CONSTRUCTION_MODE
        ? self::CONSTRUCTION_EMAIL
        : self::PRODUCTION_EMAIL;

      $string = '';
      if (self::CONSTRUCTION_MODE) {
        $string .=<<<__EOT__
<H1>Testbetrieb. Email geht nur an mich.</H1>
<H4>Ausgenommen Cc:. Bitte um Testmail von eurer Seite.</H4>
__EOT__;
      }
      $string .= '<H2>Email export and simple mass-mail web-form ';
      if ($project != '') {
        $string .= 'for project '.$project.'</H2>';
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
      $string = str_replace('@OUREMAIL@', $CAFEVCatchAllEmail, $string);

      return $string;
    }

    public static function display($user)
    {
      Config::init();

      $opts = Config::$pmeopts;

      Util::disableEnterSubmit(); // ? needed ???

      $CAFEVCatchAllEmail =
        self::CONSTRUCTION_MODE
        ? self::CONSTRUCTION_EMAIL
        : self::PRODUCTION_EMAIL;

      // Display a filter dialog
      $filter = new EmailFilter($opts, $opts['page_name']);

      $filter->execute();

      /********************************************************************************************
       *
       * Initialize some global stuff for the email form. Need to do this
       * before rendering the address selection stuff.
       *
       */

      $projectId = Util::cgiValue('ProjectId',-1);
      $project   = Util::cgiValue('Project','');

      if ($projectId < 0 || $project == '') {
        $MailTag = '[CAF-Musiker]';
      } else {
        $MailTag = '[CAF-'.$project.']';
      }

      $emailPosts = array(
        'txtSubject' => '',
        'txtCC' => '',
        'txtBCC' => '',
        'txtFromName' => 'Our Ensemble e.V..',
        'txtDescription' =>
        'Liebe Musiker,
<p>
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
<p>
Mit den besten Grüßen,
<p>
Euer Camerata Vorstand (Katha, Georg, Martina, Lea, Luise und Claus)
<p>
P.s.:
Sie erhalten diese Email, weil Sie schon einmal mit dem Orchester
Camerata Academica Freiburg musiziert haben. Wenn wir Sie aus unserer Datenbank
löschen sollen, teilen Sie uns das bitte kurz mit, indem Sie entsprechend
auf diese Email antworten. Wir entschuldigen uns in diesem Fall für die
Störung.');  

      if (Util::cgiValue('eraseAll',false) !== false) {
        /* Take everything to its defaults */
        foreach ($emailPosts as $key => $value) {
          $_POST[$key] = $value; // cheat
        }
      }

      $strSubject = Util::cgiValue('txtSubject', $emailPosts['txtSubject']);
      $strMsg     = Util::cgiValue('txtDescription', $emailPosts['txtDescription']);
      $strSender  = Util::cgiValue('txtFromName', $emailPosts['txtFromName']);
      $strCC      = Util::cgiValue('txtCC', $emailPosts['txtCC']);
      $strBCC     = Util::cgiValue('txtBCC', $emailPosts['txtBCC']);
      $strSenderEmail = $CAFEVCatchAllEmail; // always
      $strFile1 = isset($_FILES['fileAttach1']['name']) ? $_FILES['fileAttach1']['name'] : '';
      $strFile2 = isset($_FILES['fileAttach2']['name']) ? $_FILES['fileAttach2']['name'] : '';
      $strFile3 = isset($_FILES['fileAttach3']['name']) ? $_FILES['fileAttach3']['name'] : '';
      $strFile4 = isset($_FILES['fileAttach4']['name']) ? $_FILES['fileAttach4']['name'] : '';

      /*
       *
       *
       *******************************************************************************************/

      /******************************************************************************************
       *
       * Display the address selection from if it is not frozen, other after the email editor.
       *
       */

      if (!$filter->isFrozen()) {
        /* Add all of the above to the form, if it is active */

        $filter->addPersistentCGI('txtSubject', $strSubject);
        $filter->addPersistentCGI('txtDescription', $strMsg);
        $filter->addPersistentCGI('txtFromName', $strSender);
        $filter->addPersistentCGI('txtCC', $strCC);
        $filter->addPersistentCGI('txtBCC', $strBCC);
        $filter->addPersistentCGI('fileAttach1', $strFile1);
        $filter->addPersistentCGI('fileAttach2', $strFile2);
        $filter->addPersistentCGI('fileAttach3', $strFile3);
        $filter->addPersistentCGI('fileAttach4', $strFile4);

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

        if ($strSubject == '') {
          echo '<div class="cafevdb-error">
<HR/><H4>Fehler: Die Betreffzeile sollte nicht nur aus "'.$MailTag.'" bestehen.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
        }
        if ($strSender == '') {
          echo '<div class="cafevdb-error">
<HR/><H4>Fehler: Der Absender-Name sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
        }
        if ($strSenderEmail == '') {
          echo '<div class="cafevdb-error">
<HR/><H4>Fehler: Die Email-Adresse des Absenders sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4><HR/>
</div>';
        }
      }

      /*
       *
       *
       *******************************************************************************************/

      /******************************************************************************************
       *
       * Now define one huge form. This is really somewhat ugly.
       * Also: we cheat: pretend we are quick-form to simplify the look
       *
       */

      /**** start quick-form cheat ****/
      echo '<div class="quickform">';
      /*******************************/
      echo '
<FORM METHOD="post" ACTION="'.$opts['page_name'].'" NAME="Email" enctype="multipart/form-data" class="cafevdb-mail-form">';
      /**** start quick-form cheat ****/

      /* Remember address filter for later */
      if (true || $filter->isFrozen()) {
        echo $filter->getPersistent();
      }

      if ($projectId >= 0) {
        $EventSelect = Util::cgiValue('EventSelect', array());
        $eventAttach = ''
.'  <tr>
      <td>'
.Projects::eventButton($projectId, $project, 'Attach Events', $EventSelect)
.'</td>
      <td colspan="2">'
.'<span id="eventattachments">'.implode(', ', $EventSelect).'</span></td>
   </tr>';
      } else {
        $eventAttach = '';
      }

      echo sprintf('
  <fieldset %s id="cafevdb-mail-form-0"><legend id="cafevdb-mail-form-0-legend">Em@il Verfassen</legend>',$filter->isFrozen() ? '' : 'disabled');
      /*******************************/
      echo '
  <TABLE class="cafevdb-email-form">
  <tr>
     <td>Adressat</td>
     <td colspan="2">Determined automatically from data-base, see below the email form.</td>
  </tr>
  <tr>
     <td>Carbon Copy</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($strCC).'" name="txtCC" type="text" id="txtCC"></td>
  </tr>
  <tr>
     <td>Blind CC</td>
     <td colspan="2"><input size="40" value="'.htmlspecialchars($strBCC).'" name="txtBCC" type="text" id="txtBCC"></td>
  </tr>
  <tr>
     <td>Betreff</td>
     <td class="subject">'.htmlspecialchars($MailTag).'&nbsp;</td>
     <td><input value="'.$strSubject.'" size="40" name="txtSubject" type="text" id="txtSubject"></td>
  </tr>
  <tr>
    <td>Nachricht</td>
    <td colspan="2"><textarea name="txtDescription" cols="20" rows="4" id="txtDescription">'.$strMsg.'</textarea></td>
  </tr>
  <tr>
    <td>Absende-Name</td>
    <td colspan="2"><input value="'.$strSender.'" size="40" value="CAFEV" name="txtFromName" type="text"></td>
  </tr>
  <tr>
  <tr>
    <td>Absende-Email</td>
    <td colspan="2">Tied to "'.$CAFEVCatchAllEmail.'"</td>
  </tr>
  '.$eventAttach.'
  <tr>
    <td>Attachment 1</td>
    <td colspan="2"><input name="fileAttach1" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 2</td>
    <td colspan="2"><input name="fileAttach2" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 3</td>
    <td colspan="2"><input name="fileAttach3" type="file"></td>
  </tr>
  <tr>
    <td>Attachment 4</td>
    <td colspan="2"><input name="fileAttach4" type="file"></td>
  </tr>';
      $submitString = '
  <tr class="submit">
    <td class="send">
      <input %1$s title="Vorsicht!"
      type="submit" name="sendEmail" value="Em@il Verschicken"/></td>
    <td class="addresses">
      <input %1$s title="Der Nachrichten-Inhalt
bleibt erhalten." type="submit" name="modifyAddresses" value="Adressen Bearbeiten"/>
   </td>
   <td class="reset">
       <input %1$s title="Abbrechen und von
vorne Anfangen, bereits
veränderter Text geht
verloren." type="submit" name="eraseAll" value="Abbrechen" />
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
       *******************************************************************************************/

      /******************************************************************************************
       *
       * If the filter is frozen, display it now, otherwise the editor
       * window would be at the bottom of some large address list.
       *
       */

      if ($filter->isFrozen()) {
        $filter->render(); // else render below the Email editor
      }

      /*
       *
       *
       *******************************************************************************************/

      /******************************************************************************************
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

      if ($strSubject == '') {
        echo '<HR/><H4>Die Betreffzeile sollte nicht nur aus "'.$MailTag.'" bestehen.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
        $DataValid = false;
      }
      if ($strSender == '') {
        echo '<HR/><H4>Der Absender-Name sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
        $DataValid = false;
      }
      if ($strSenderEmail == '') {
        echo '<HR/><H4>Die Email-Adresse des Absenders sollte nicht leer sein.
<p>
Bitte korrigieren und dann den "Send"-Button noch einmal anklicken
<P>
Leider m&uuml;ssen etwaige Attachments jetzt noch einmal angegeben werden.
</H4>';
        $DataValid = false;
      }

      $strMessage = nl2br($strMsg);
      $h2t = new \html2text($strMessage);
      $h2t->set_encoding('utf-8');
      $strTextMessage = $h2t->get_text();

      $mail = new \PHPMailer();
      $mail->CharSet = 'utf-8';
      $mail->SingleTo = false;

      // Setup the mail server for testing
      // $mail->IsSMTP();
      //$mail->IsMail();
      $mail->IsSMTP();
      if (true) {
        $mail->Host = 'server.example.eu';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->Username = 'wp1173590-cafev';
        $mail->Password = 'XXXXXXXX';
      }

      $mail->IsHTML();

      $DataValid = self::setFrom($mail,$strSenderEmail,$strSender)
        && $DataValid;
      $DataValid = self::addReplyTo($mail,$strSenderEmail,$strSender)
        && $DataValid;
      $mail->Subject = $MailTag . ' ' . $strSubject;
      $mail->Body = $strMessage;
      $mail->AltBody = $strTextMessage;

      if (!self::CONSTRUCTION_MODE) {
        // Loop over all data-base records and add each recipient in turn
        foreach ($EMails as $pairs) {
          // Better not use AddAddress: we should not expose the email
          // addresses to everybody. TODO: instead place the entire
          // message, including the Bcc's, either in the "sent" folder,
          // or save it somewhere else.
          if ($projectId < 0) {
            $DataValid = self::addBCC($mail, $pairs['email'], $pairs['name'])
              && $DataValid;
          } else {
            // Well, people subscribing to one of our projects simply must not complain.
            $DataValid = self::addAddress($mail, $pairs['email'], $pairs['name'])
              && $DataValid;
          }
        }
      } else {
        $DataValid = self::addAddress($mail, 'DEVELOPER@his.server.eu', 'Claus-Justus Heine')
          && $DataValid;
      }

      // Always drop a copy locally and to the mailing list, for
      // archiving purposes and to catch illegal usage.
      $DataValid = self::addAddress($mail, $CAFEVCatchAllEmail, $strSender)
        && $DataValid;

      // If we have further Cc's, then add them also
      if ($strCC != '') {
        // Now comes some dirty work: we need to split the string in
        // names and email addresses.
  
        $arrayCC = parseEmailListToArray($strCC);
        if (Util::debugMode()) {
          echo "<PRE>\n";
          print_r($arrayCC);
          echo "</PRE>\n";
        }
  
        foreach ($arrayCC as $value) {
          $strCC .= $value['name'].' <'.$value['email'].'>,';
          // PHP-Mailer adds " for itself as needed
          $value['name'] = trim($value['name'], '"');
          $DataValid = self::addCC($mail, $value['email'], $value['name'])
            && $DataValid;
        }
        $strCC = trim($strCC, ',');
      }

      // Do the same for Bcc
      if ($strBCC != '') {
        // Now comes some dirty work: we need to split the string in
        // names and email addresses.
  
        $arrayBCC = parseEmailListToArray($strBCC);
        if (Util::debugMode()) {
          echo "<PRE>\n";
          print_r($arrayBCC);
          echo "</PRE>\n";
        }
  
        $strBCC = '';
        foreach ($arrayBCC as $value) {
          $strBCC .= $value['name'].' <'.$value['email'].'>,';
          // PHP-Mailer adds " for itself as needed
          $value['name'] = trim($value['name'], '"');
          $DataValid = self::addBCC($mail, $value['email'], $value['name'])
            && $DataValid;
        }
        $strBCC = trim($strBCC, ',');
      }

      if ($DataValid) {
  
        foreach ($_FILES as $key => $value) {
          if (Util::debugMode()) {
            echo "<PRE>\n";
            print_r($value);
            echo "</PRE>\n";
          }
          if($value['name'] != "") {
            if ($value['type'] == 'application/x-download' &&
                strrchr($value['name'], '.') == '.pdf') {
              $value['type'] = 'application/pdf';
            }
            if (!$mail->AddAttachment($value['tmp_name'],$value['name'],
                                      'base64',$value['type'])) {
              $DataValid = false;
              echo '<HR/><H4>The attachment '.$value['name'].' seems to be invalid.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
            }
          }
        }

        // Construct one MD5 for recipients subject and html-text
        $bulkRecipients = '';
        foreach ($EMails as $pairs) {
          $bulkRecipients .= $pairs['name'].' <'.$pairs['email'].'>,';
        }
        // add CC and BCC
        if ($strCC != '') {
          $bulkRecipients .= $strCC.',';
        }
        if ($strBCC != '') {
          $bulkRecipients .= $strBCC.',';
        }
        $bulkRecipients = trim($bulkRecipients,',');
        $bulkMD5 = md5($bulkRecipients);
  
        $textforMD5 = $strSubject . $strMessage;
        $textMD5 = md5($textforMD5);
  
        // compute the MD5 stuff for the attachments
        $attachLog = array();
        foreach ($_FILES as $key => $value) {
          if($value['name'] != "") {
            $md5val = md5_file($value['tmp_name']);
            $attachLog[] = array('name' => $value['name'],
                                 'md5' => $md5val);
          }
        }
  
        // Now insert the stuff into the SentEmail table  
        $handle = mySQL::connect($opts);

        $logquery = "INSERT INTO `SentEmail`
(`user`,`host`,`BulkRecipients`,`MD5BulkRecipients`,`Cc`,`Bcc`,`Subject`,`HtmlBody`,`MD5Text`";
        $idx = 1;

        foreach ($attachLog as $pairs) {
          $logquery .= ",`Attachment".$idx."`,`MD5Attachment".$idx."`";
        }
        $logquery .= ') VALUES (';
        $logquery .= "'".$user."','".$_SERVER['REMOTE_ADDR']."'";
        $logquery .= ",'".mysql_real_escape_string($bulkRecipients,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($bulkMD5,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($strCC,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($strBCC,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($strSubject,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($strMessage,$handle)."'";
        $logquery .= ",'".mysql_real_escape_string($textMD5,$handle)."'";
        foreach ($attachLog as $pairs) {
          $logquery .=
            ",'".mysql_real_escape_string($pairs['name'],$handle)."'".
            ",'".mysql_real_escape_string($pairs['md5'],$handle)."'";
        }
        $logquery .= ")";
  
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
          echo '<HR/><H3>A message with exactly the same text to exactly the same
recipients has already been sent on the following date'.($cnt > 1 ? 's' : '').':
<p>
'.$loggedDates.'
<p>
Refusing to send duplicate bulk emails.
</H3><HR/>
';
          return false;
        }

        try {
          if (!$mail->Send()) {
            echo <<<__EOT__
              <p>Mail failed<p>
__EOT__;
          } else {
            // Log the message to our data-base
      
            mySQL::query($logquery, $handle);
      
          }
        } catch (Exception $e) {
          echo '<HR/><H4>Fehler:</H4>';
          echo "<PRE>\n";
          echo htmlspecialchars($e->getMessage())."\n";
          echo "</PRE><HR/>\n";
        }

        mySQL::close($handle);

        if (false) {
          // Now, this is really the fault of our provider. Sad
          // story. How to work-around? Well, we send the message as
          // Email-attachment to our own account. Whoops.
    
          // connect to your Inbox through port 143.  See imap_open()
          // function for more details
          $mbox = imap_open('{'.$mail->Host.':143/notls}INBOX',
                            $mail->Username,
                            $mail->Password);
          if ($mbox !== false) {
            // save the sent email to your Sent folder by just passing a
            // string composed of the entire message + headers.  See
            // imap_append() function for more details.  Notice the 'r'
            // format for the date function, which formats the date
            // correctly for messaging.

            imap_append($mbox, '{'.$mail->Host.':993/ssl}INBOX.Sent',
                        $mail->GetSentMIMEMessage());
            // close mail connection.
            imap_close($mbox);
          }
        } elseif (true) {
          // PEAR IMAP works without the c-client library

          ini_set('error_reporting',ini_get('error_reporting') & ~E_STRICT);

          $imap = new \Net_IMAP($mail->Host, 993, false, 'UTF-8');
          if (($ret = $imap->login($mail->Username, $mail->Password)) !== true) {
            CAFEVerror($ret->toString(), false);
            $imap->disconnect();
            die();
          }
          if (($ret = $imap->appendMessage($mail->GetSentMIMEMessage(), 'Sent')) !== true) {
            CAFEVerror($ret->toString(), false);
            $imap->disconnect();
            die();
          }
          $imap->disconnect();
        }

        if (true || Util::debugMode()) {
          echo '<HR/><H4>Gesendete Email</H4>';
          echo "<PRE>\n";
          echo htmlspecialchars($mail->GetSentMIMEMessage())."\n";
          echo "</PRE><HR/>\n";
        }
      }
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
      echo '<HR/><H4>The '.$kind.' address "'.$email.'" seems to be invalid.
<p>
Please correct that first and then click on the "Send"-button again.
<P>
Unfortunately, attachments (if any) have to be specified again.
</H4>';
    }

    public static function addAddress($phpmailer, $address, $name = '')
    {
      if (!$phpmailer->AddAddress($address, $name)) {
        EmailEchoInvalid('recipient', $address);
        return false;
      }
      return true;
    }

    public static function addCC($phpmailer, $address, $name = '')
    {
      if (!$phpmailer->AddCC($address, $name)) {
        EmailEchoInvalid('"Cc:"', $address);
        return false;
      }
      return true;
    }

    public static function addBCC($phpmailer, $address, $name = '')
    {
      if (!$phpmailer->AddBCC($address, $name)) {
        EmailEchoInvalid('"Bcc:"', $address);
        return false;
      }
      return true;
    }

    public static function setFrom($phpmailer, $address, $name = '')
    {
      if ($phpmailer->SetFrom($address, $name) != true) {
        EmailEchoInvalid('"From:"', $address);
        return false;
      }
      return true;
    }

    public static function addReplyTo($phpmailer, $address, $name = '')
    {
      if ($phpmailer->AddReplyTo($address, $name) != true) {
        EmailEchoInvalid('"ReplyTo:"', $address);
        return false;
      }
      return true;
    }

    // Maybe not needed.
    public static function callback($isSent, $to, $cc, $bcc, $subject, $body)
    {


    }

    /**Display the history records of all our junk-mail attempts.
     */
    public static function displayHistory()
    {
      Config::init();
    
      //$debug_query = true;

      if (isset($debug_query) && $debug_query) {
        echo "<PRE>\n";
        print_r($_POST);
        print_r($_GET);
        echo "</PRE>\n";
      }
      echo <<<__EOT__
<div class="cafevdb-pme-header-box">
  <div class="cafevdb-pme-header">
    <h3>Massenmail-Historie.</h3>
    <H4>Nat&uuml;rlich kann man einmal gesendete Email nicht mehr &auml;ndern, also kann man auch die Daten unten nicht editieren.</H4>
  </div>
</div>

__EOT__;
  
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

      $project = Util::cgiValue('Project','');
      $projectId = Util::cgiValue('ProjectId',-1);
      $recordsPerPage = Util::cgiValue('RecordsPerPage',-1);

      $opts['cgi']['persist'] = array('Project' => $project,
                                      'ProjectId' => $projectId,
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

}

?>
