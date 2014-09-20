<?php
/**Orchestra member, musician and project management application.
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
/**Wrap the email filter form into a class to make things a little
 * less crowded. This is actually not to filter emails, but rather to
 * select specific groups of musicians (depending on instrument and
 * project).
 */
  class EmailRecipientFilter {

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
      if (Util::debugMode('request')) {
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
      if ($this->projectId >= 0 &&
          ($table == 'Besetzungen' || $table == 'SepaDebitMandates')) {

        $dbh = mySQL::connect($this->opts);

        /*
         * This means we have been called from the "brief"
         * instrumentation view. The other possibility is to be called
         * from the detailed view, or the total view of all musicians.
         *
         * If called from the "Besetzungen" table, we remap the Id's
         * to the project view table and continue with that.
         */
        $oldTable = $table;
        $table = $this->project.'View';
        switch ($oldTable) {
        case 'Besetzungen':
          $query = 'SELECT `'.$oldTable.'`.`Id` AS \'OrigId\',
  `'.$table.'`.`MusikerId` AS \'MusikerId\'
  FROM `'.$table.'` LEFT JOIN `'.$oldTable.'`
  ON `'.$table.'`.`MusikerId` = `'.$oldTable.'`.`MusikerId`
  WHERE `'.$oldTable.'`.`ProjektId` = '.$this->projectId;
          break;
        case 'SepaDebitMandates':
          $query = 'SELECT `'.$oldTable.'`.`id` AS \'OrigId\',
  `'.$table.'`.`MusikerId` AS \'MusikerId\'
  FROM `'.$table.'` LEFT JOIN `'.$oldTable.'`
  ON `'.$table.'`.`MusikerId` = `'.$oldTable.'`.`musicianId`
  WHERE `'.$oldTable.'`.`projectId` = '.$this->projectId;
          break;
        }

        // Fetch the result (or die) and remap the Ids
        $result = mySQL::query($query, $dbh);
        $map = array();
        while ($line = mysql_fetch_assoc($result)) {
          $map[$line['OrigId']] = $line['MusikerId'];
        }
        $newEmailRecs = array();
        foreach ($this->EmailRecs as $key) {
          if (!isset($map[$key])) {
            Util::error('Musician in Table, but has no Id as Musician');
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
     * 'Instrumente' or 'Instrument' (normally). Also, fetch all data
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
                           'Geburtstag',
                           'MemberStatus');
      $sep = '`,`';
      $fields = '`'.$id.$sep.implode($sep, $columnNames).'`';

      if ($projectId > 0) { // Add the project fee
        $fields .= ',`Unkostenbeitrag`,`mandateReference`';
        // join table with the SEPA mandate reference table
        $table .= "LEFT JOIN `SepaDebitMandates` ON "
          ."( `MusikerId` = `musicianId` AND `projectId` = ".$projectId." ) ";
      }

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
          if ($projectId < 0) {
            $line['Unkostenbeitrag'] = '';
            $line['mandateReference'] = '';
          }
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
          array('id' => 'selectedUserGroup-fromProject',
                'value' => true,
                'class' => 'selectfromproject',
                'title' => 'Auswahl aus den registrierten Musikern für das Projekt.'));
        $check->setContent('<span class="selectfromproject">&isin; '.$this->project.'</span>');
        //$check->setAttribute('checked');
        
        $check = $group->addElement(
          'checkbox', 'selectedUserGroup[exceptProject]',
          array('id' => 'selectedUserGroup-exceptProject',
                'value' => true,
                'class' => 'selectexceptproject',
                'title' => 'Auswahl aus allen Musikern, die nicht für das Projekt registriert sind.'));
        $check->setContent('<span class="selectexceptproject">&notin; '.$this->project.'</span>');
      }

      // Optionally also include recipients which are normally disabled.
      $this->byStatusSelect = $this->baseGroupFieldSet->addElement(
        'select', 'memberStatusFilter',
        array('id' => 'memberStatusFilter',
              'multiple' => 'multiple',
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
        array('size' => 18, 'class' => 'dualselect', 'id' => 'DualSelectMusicians'),
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
        array('id' => 'InstrumentenFilter',
              'multiple' => 'multiple',
              'size' => 18,
              'class' => 'filter'),
        array('label' => L::t('Instrument-Filter'),
              'options' => array('*' => '*')));

      /******** Submit buttons follow *******/

      $this->submitFieldSet = $outerFS->addElement(
        'fieldset', NULL, array('class' => 'submit'));

      $this->freezeFieldSet = $this->submitFieldSet->addElement(
        'fieldset', NULL, array('class' => 'freeze'));

      $this->freezeButton = $this->freezeFieldSet->addElement(
        'submit', 'writeMail',
        array('id' => 'writeMail',
              'value' => L::t('Compose Em@il'),
              'title' => 'Beendet die Musiker-Auswahl
und aktiviert den Editor'));

      $this->submitFilterFieldSet = $this->submitFieldSet->addElement(
        'fieldset', NULL, array('class' => 'filtersubmit'));

      $this->filterApplyButton = $this->submitFilterFieldSet->addElement(
        'submit', 'filterApply',
        array('id' => 'filterApply',
              'value' => L::t('Apply Filter'),
              'class' => 'apply',
              'title' => 'Instrumenten- und Musiker-Fundus-Filter anwenden.'));

      $this->filterResetButton = $this->submitFilterFieldSet->addElement(
        'submit', 'filterReset',
        array('id' => 'filterReset',
              'value' => L::t('Reset Filter'),
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
         * We implement two further POST actions for communication with
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

          $this->memberFilter = $this->defaultByStatus();
          /* Install default "no-email" stuff */
          $this->byStatusSelect->setvalue($this->defaultByStatus());

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

        } elseif (!empty($value['writeMail']) ||
                  Util::cgiValue('saveEmailTemplate') ||
                  Util::cgiValue('emailTemplateSelector') ||
                  Util::cgiValue('sendEmail') ||
                  Util::cgiValue('deleteAttachment')) {
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
      // Nope: DO NOT EMIT INLINE SCRIPTS, instead, include the two needed
      // libraries directly from the top-level index.php
      //$renderer->getJavascriptBuilder()->getLibraries(true, true);
      echo $renderer;
    }

  };


} // CAFEVDB

?>
