<?php

set_include_path(dirname(__FILE__).'/QuickForm2' . PATH_SEPARATOR . get_include_path());

require_once('functions.php.inc');
require_once('Instruments.php');
require_once('QuickForm2/DualSelect.php');

/* Wrap the email form into a class to make things a little less crowded
 *
 */

class CAFEVmailFilter {

  private $ProjektId;   // Project id or NULL or -1 or ''
  private $Projekt;     // Project name of NULL or ''
  private $Filter;      // Current instrument filter
  private $EmailsRecs;  // Copy of email records from CGI env
  private $emailKey;    // Key for EmailsRecs into _POST or _GET

  private $Table;       // Table-name, either Musiker of some project view
  private $MId;         // Key for the musicians id, Id, or MusikerId
  private $Restrict;    // Instrument filter keyword, Instrument or ...s
  private $Instruments; // List of instruments for filtering
  
  private $opts;        // Copy of global options

  private $NoMail;      // List of people without email
  private $EMails;      // List of people with email  
  private $EMailsDpy;   // Display list with namee an email

  // Form elements
  private $form;           // QuickForm2 form
  private $selectFieldSet; // Field-set for the filter
  private $dualSelect;     // QF2 dual-select
  private $filterSelect;   // Filter by instrument.
  private $submitFieldSet; // Field-set for the select buttons
  private $resetButton;    // Reset to default state
  private $submitButton;   // Guess what.
  private $freezeButton;   // Display emails as list.
  private $nopeFieldSet;   // No email.
  private $nopeStatic;     // Actual static form

  private $frozen;         // Whether or not we are active.

  /* 
   * constructor
   */
  public function CAFEVmailFilter(&$_opts, $action = NULL)
  {
    $this->frozen = false;

    $this->opts = $_opts;

    $this->ProjektId = CAFEVcgiValue('ProjektId',-1);
    $this->Projekt   = CAFEVcgiValue('Projekt','');

    // See wether we were passed specific variables ...
    $pmepfx          = $this->opts['cgi']['prefix']['sys'];
    $this->emailKey  = $pmepfx.'mrecs';
    $this->mtabKey   = $pmepfx.'mtable';
    $this->EmailRecs = CAFEVcgiValue($this->emailKey,array());
    $this->Table     = CAFEVcgiValue($this->mtabKey,'');

    // Now connect to the data-base
    $dbh = CAFEVmyconnect($this->opts);

    $this->getInstrumentsFromDB($dbh);
    $this->getMusiciansFromDB($dbh);

    // Not needed anymore
    CAFEVmyclose($dbh);

    /* At this point we have the Email-List, either in global or project
     * context, and possibly a selection of Musicians by Id from the
     * script which called us. We build a "dual-select" box where the Ids
     * in the MiscRecs field are remembered.
     */
    $this->createForm();
    
    if ($action) {
      $this->form->setAttribute('action', $action);
    }

    $this->emitPersistent();   
  }

  static public function defaultStyle()
  {
    $style =<<<__EOT__
.cafev-mail-filter fieldset.filter {
    white-space:nowrap;
}
.cafev-mail-filter fieldset.filter div.row
{
    vertical-align:top;
    width:10em;
    display:inline-block;
    float:none;
}
.cafev-mail-filter fieldset.filter div.element
{
    display:inline-block;
    float:left;
}
.cafev-mail-filter fieldset.filter select {
     font-size:90%;
}
.cafev-mail-filter fieldset.filter label.element {
    float:left;
    width:100%;
    text-align:left;
    margin: 0.7em 0 0 0;
}
.cafev-mail-filter fieldset.filter select.filter {
    width:auto;
}
.cafev-mail-filter fieldset.filter select.dualselect {
    width:350px;
}
.cafev-mail-filter fieldset.filter p.label {
    float:left;
    width:100%;
    text-align:left;
    padding: 0;
    margin: 0.7em 0 0 0;
}

.cafev-mail-filter fieldset.submit {
    white-space:nowrap;
}
.cafev-mail-filter fieldset.submit div.row
{
    display:inline-block;
}

__EOT__;
    return $style;
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
        $form->addElement('hidden', $key.'['.$idx.']')->setValue($val);
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
    
    $this->addPersistentCGI('ProjektId', $this->ProjektId);
    $this->addPersistentCGI('Projekt', $this->Projekt);
    $this->addPersistentCGI($this->emailKey, $this->EmailRecs);
  }

  /*
   * Return a string with our needed data.
   */
  public function getPersistent()
  {
    $form = new HTML_QuickForm2('dummy');
    
    $this->addPersistentCGI('ProjektId', $this->ProjektId, $form);
    $this->addPersistentCGI('Projekt', $this->Projekt, $form);
    $this->addPersistentCGI($this->emailKey, $this->EmailRecs, $form);

    $value = $this->form->getValue();
    if (isset($value['SelectedMusicians'])) {
      $this->addPersistentCGI('SelectedMusicians', $value['SelectedMusicians'], $form);
    }
    if (isset($value['InstrumentenFilter'])) {
      $this->addPersistentCGI('InstrumentenFilter', $value['InstrumentenFilter'], $form);
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
  private function getInstrumentsFromDb($dbh, $filterNumbers = NULL)
  {
    // Get the current list of instruments for the filter
    if ($this->ProjektId >= 0) {
      $this->MId         = 'MusikerId';
      $this->Restrict    = 'Instrument';
      $this->Instruments = fetchProjectMusiciansInstruments($this->ProjektId, $dbh);
    } else {
      $this->MId         = 'Id';
      $this->Restrict    = 'Instrumente';
      $this->Instruments = fetchInstruments($dbh);
    }
    array_unshift($this->Instruments, '*');

    /* Construct the filter */
    if (!$filterNumbers) {
      $filterNumbers = CAFEVcgiValue('InstrumentenFilter',array('0'));
    }
    $this->Filter = array();
    foreach ($filterNumbers as $idx) {
      $this->Filter[] = $this->Instruments[$idx];
    }
  }  

  /*
   * Fetch the list of musicians for the given context (project/gloabl)
   */
  private function getMusiciansFromDB($dbh)
  {
    if ($this->Table == 'Besetzungen') {
      /*
       * Then we need to remap the stuff. To keep things clean we remap now
       */
      $this->Table = $this->Projekt.'View';
      $query = 'SELECT `Besetzungen`.`Id` AS \'BesetzungsId\',
  `'.$this->Table.'`.`'.$this->MId.'` AS \'MusikerId\'
  FROM `'.$this->Table.'` LEFT JOIN `Besetzungen`
  ON `'.$this->Table.'`.`'.$this->MId.'` = `Besetzungen`.`MusikerId` WHERE 1';

      // Fetch the result or die and remap the Ids
      $result = CAFEVmyquery($query, $dbh);
      $map = array();
      while ($line = mysql_fetch_assoc($result)) {
        $map[$line['BesetzungsId']] = $line['MusikerId'];
      }
      $newEmailRecs = array();
      foreach ($this->EmailRecs as $key) {
        if (!isset($map[$key])) {
          CAFEVerror('Musician in Besetzungen, but has no Id as Musician');
        }
        $newEmailRecs[] = $map[$key];
      }
      $this->EmailRecs = $newEmailRecs;
    }
    
    /*** Now continue as if from 'ordinary' View-Table ****/
    if ($this->ProjektId < 0) {
      $this->Table = 'Musiker';
    } else {
      $this->Table = $this->Projekt.'View';
    }

    $query = 'SELECT `'.$this->MId.'`,`Vorname`,`Name`,`Email` FROM '.$this->Table.' WHERE
       ( ';
    foreach ($this->Filter as $value) {
      if ($value == '*') {
        $query .= "1 OR\n";
      } else {
        $query .= "`".$this->Restrict."` LIKE '%".$value."%' OR\n";
      }
    }
    /* Don't bother any conductor with mass-email. */
    $query .= "0 ) AND NOT `".$this->Restrict."` LIKE '%Taktstock%'\n";

    // Fetch the result or die
    $result = CAFEVmyquery($query, $dbh);

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
    $this->form = new HTML_QuickForm2('dualselect');

    /* Add any variables we want to keep
     */
    /* Selected recipient */
    $this->form->addDataSource(
      (new HTML_QuickForm2_DataSource_Array(
        array('SelectedMusicians' => $this->EmailRecs))));

    $this->form->setAttribute('class', 'cafev-mail-filter');

    /* Groups can only render field-sets well, so make thing more
     * complicated than necessary
     */
    $fsdummystyle = 'border:none;margin:0px;padding:0px;clear:both';

    // Outer field-set with border
    $outerFS = $this->form->addElement('fieldset')->setLabel('Em@il Auswahl');

    $this->selectFieldSet = $outerFS->addElement('fieldset', NULL, array('style' => $fsdummystyle));
    $this->selectFieldSet->setAttribute('class','filter');

    $this->filterSelect = $this->selectFieldSet->addElement(
      'select', 'InstrumentenFilter',
      array('multiple' => 'multiple', 'size' => 15, 'class' => 'filter'),
      array('options' => $this->Instruments, 'label' => 'Instrumenten-Filter'));

    if (!CAFEVcgiValue('InstrumentenFilter',false)) {
      $this->filterSelect->setValue(array(0));
    }

    $this->dualSelect = $this->selectFieldSet->addElement(
      'dualselect', 'SelectedMusicians',
      array('size' => 15, 'class' => 'dualselect'),
      array('options'    => $this->EMailsDpy,
            'keepSorted' => true,
            'from_to'    => array(
              'content' => ' &gt;&gt; ',
              'attributes' => array('class' => 'transfer')),
            'to_from'    => array(
              'content' => ' &lt&lt; ',
              'attributes' => array('class' => 'transfer'))
        )
      )->setLabel(array(
                    'Email Adressaten',
                    'übrige',
                    'ausgewählte'
                    ));

    if (false) {
      $this->dualSelect->addRule(
        'required', 'Bitte wenigstens einen Adressaten wählen', 1,
        HTML_QuickForm2_Rule::ONBLUR_CLIENT_SERVER);
    }

    /******** Submit buttons follow *******/

    $this->submitFieldSet = $outerFS->addElement('fieldset', NULL, array('style' => $fsdummystyle));
    $this->submitFieldSet->setAttribute('class','submit');

    $this->freezeButton = $this->submitFieldSet->addElement(
      'submit', 'writeMail',
      array('value' => 'Email Verfassen',
            'title' => 'Beendet die Musiker-Auswahl
und aktiviert den Editor'));

    $this->submitButton = $this->submitFieldSet->addElement(
      'submit', 'filterSubmit',
      array('value' => 'Filter Anwenden',
      'title' => 'Instrumenten-Filter anwenden.'));

    $this->resetButton = $this->submitFieldSet->addElement(
      'submit', 'filterReset',
      array('value' => 'Filter Zurücksetzen',
            'title' => 'Von vorne mit den
Anfangswerten.'));

    /********** Add a pseudo-form for people without email *************/

    $this->nopeFieldSet =
      $this->form->addElement('fieldset', 'NoEm@il')->setLabel('Musiker ohne Em@il');
    $this->nopeStatic = $this->nopeFieldSet->addElement('static', 'NoEm@il', NULL,
                                                        array('tagName' => 'div'));

    $this->updateNoEmailForm();
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

  /*
   * Let it run; 
   */
  public function execute()
  {
    // outputting form values
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $value = $this->form->getValue();
      /* echo "<pre>\n"; */
      /* var_dump($value); */
      /* print_r($this->EMails); */
      /* echo "</pre>\n<hr />"; */
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
      if (CAFEVcgiValue('eraseAll','') != '') {
        /* actually, this simply means nothing to do */
      } elseif (CAFEVcgiValue('modifyAddresses','') != '') {
        /* we are already in our default state because the script was
         * called from another form. So install the previous
         * selection.
         */ 
        $this->dualSelect->toggleFrozen(false);
        $this->frozen = false;

        $this->dualSelect->setValue(CAFEVcgiValue('SelectedMusicians'), array());
        
      } elseif (!empty($value['filterReset'])) {
        $this->dualSelect->toggleFrozen(false);
        $this->frozen = false;

        /* Ok, this means we must re-fetch some stuff from the DB */
        $this->filterSelect->setValue(array(0));

        $dbh = CAFEVmyconnect($this->opts);
        $this->getInstrumentsFromDB($dbh, array(0));
        $this->getMusiciansFromDB($dbh);
        CAFEVmyclose($dbh);
        
        /* Now we need to reinstall the musicians into dualSelect */
        $this->dualSelect->loadOptions($this->EMailsDpy);
        $this->dualSelect->setValue($this->EmailRecs);

        /* Also update the "no email" notice. */
        $this->updateNoEmailForm();
      } elseif (!empty($value['writeMail']) || CAFEVcgiValue('sendEmail')) {
        if (CAFEVcgiValue('sendEmail')) {
          // Re-install the filter from the form
          $this->dualSelect->toggleFrozen(false);
          $this->dualSelect->setValue(CAFEVcgiValue('SelectedMusicians'), array());
        }
        $this->frozen = true;
        $this->dualSelect->toggleFrozen(true);
        $this->submitFieldSet->removeChild($this->submitButton);
        $this->submitFieldSet->removeChild($this->freezeButton);
        $this->submitFieldSet->removeChild($this->resetButton);
        $this->selectFieldSet->removeChild($this->filterSelect);
        if ($this->form->getElementById($this->nopeFieldSet->getId())) {
          $this->form->removeChild($this->nopeFieldSet);
        }
      }
    }
  }
  

  public function render() 
  {
    $renderer = HTML_QuickForm2_Renderer::factory('default');
    
    $this->form->render($renderer);
    echo $renderer->getJavascriptBuilder()->getLibraries(true, true);
    echo $renderer;
  }

};


?>