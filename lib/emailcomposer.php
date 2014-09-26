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

  class EmailComposer {
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
GEBURTSTAG
UNKOSTENBEITRAG
SEPAMANDATSREFERENZ
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
Geburtstag
Unkostenbeitrag
mandateReference
';
    private $opts; ///< For db connection and stuff.
    private $dbh;  ///< Data-base handle.

    private $recipients; ///< The list of recipients.
    private $cgiData;
    private $submitted;    

    private $projectId;
    private $projectName;

    private $constructionMode;

    private $catchAllEmail; ///< The fixed From: email address.
    private $catchAllName;  ///< The default From: name.
    private $senderName;    ///< The modifiable From: name.

    private $initialTemplate;
    private $templateNames;
    private $templateName;
    
    private $messageTag;

    private $messageContents; // What we finally send out to the world

    private $executionStatus; // false on error
    private $diagnostics; // mixed, depends on operation

    private $fileAttach;

    /* 
     * constructor
     */
    public function __construct($recipients = array())
    {
      Config::init();
      $this->opts = Config::$pmeopts;
      $this->dbh = false;

      $this->recipients = $recipients;

      $this->constructionMode = Config::$opts['emailtestmode'] != 'off';
      $this->setCatchAll();

      $this->cgiData = Util::cgiValue('emailComposer', array());

      $this->projectId   = $this->cgiValue('ProjectId', Util::cgiValue('ProjectId', -1));
      $this->projectName = $this->cgiValue('ProjectName',
                                           Util::cgiValue('ProjectName',
                                                          Util::cgiValue('Project', '')));
      $this->setSubjectTag();

      // First initialize defaults, will be overriden based on
      // form-submit data in $this->execute()
      $this->setDefaultTemplate();

      $this->templateName = 'Default';
      $this->templateNames = $this->fetchTemplateNames(); // all from data-base

      $this->messageContents = $this->initialTemplate;

      $this->executionStatus = true;
      $this->execute();
    }

    /**Parse the submitted form data and act accordinly */
    private function execute()
    {
      // Maybe should also check something else. If submitted is true,
      // then we use the form data, otherwise the defaults.
      $this->submitted = $this->cgiValue('FormStatus', '') == 'submitted';

      if (!$this->submitted) {
        // Leave everything at default state
        return;
      }

      $this->messageContents = $this->cgiValue('MessageText', $this->initialTemplate);
      if (($value = $this->cgiValue('TemplateSelector', false))) {
        $this->templateName =$value;
        $this->messageContents = $this->fetchTemplate($this->templateName);
      } else if (($value = $this->cgiValue('DeleteTemplate', false))) {
        $this->deleteTemplate($this->cgiValue('TemplateName'));
        $this->setDefaultTemplate();
        $this->messageContents = $this->initialTemplate;
        $this->templateNames = $this->fetchTemplateNames(); // refresh
      } else if (($value = $this->cgiValue('SaveTemplate', false))) {
        $result = $this->validateTemplate($this->messageContents);
        if ($result === true) {
          $this->storeTemplate($this->cgiValue('TemplateName'), $this->messageContents);
          $this->templateNames = $this->fetchTemplateNames(); // refresh
        } else {
          $this->executionStatus = false;
          $this->diagnostics = $result;
        }
      } else if ($this->cgiValue('Cancel', false)) {
        // do some cleanup, i.e. remove temporay storage from file attachments
        self::cleanTemporaries();
      }
    }

    /**Fetch a CGI-variable out of the form-select name-space */
    private function cgiValue($key, $default = null)
    {
      if (isset($this->cgiData[$key])) {
        return $this->cgiData[$key];
      } else {
        return $default;
      } 
    }

    /**Compute the subject tag, depending on whether we are in project
     * mode or not.
     */
    private function setSubjectTag()
    {
      if ($this->projectId < 0 || $this->projectName == '') {
        $this->messageTag = '[CAF-Musiker]';
      } else {
        $this->messageTag = '[CAF-'.$this->projectName.']';
      }
    }

    /**Validates the given template, i.e. searches for unknown
     * substitutions. This function is invoked right before sending
     * stuff out and before storing drafts. In order to do so we
     * substitute each known variable by a dummy value and then make
     * sure that no variable tag ${...} remains.
     */
    private function validateTemplate($template)
    {
      $templateError = array();

      // Check for per-member stubstitutions
      $memberTemplateLeftOver = array();

      $dummy = $template;

      if (preg_match('![$]{MEMBER::[^}]+}!', $dummy)) {
        // Fine, we have substitutions. We should now verify that we
        // only have _legal_ substitutions. There are probably more
        // clever ways to do this, but at this point we simply
        // substitute any legal variable by DUMMY and check that no
        // unknown ${...} substitution tag remains. Mmmh.
        
        $variables = $this->emailMemberVariables();
        foreach ($variables as $placeholder => $column) {
          $dummy = preg_replace('/[$]{MEMBER::'.$placeholder.'}/', $column, $dummy);
        }
        
        if (preg_match('![$]{MEMBER::[^}]+}!', $dummy, $memberTemplateLeftOver)) {
          $templateError[] = 'member';
        }
        
        // Now remove all member variables, known or not
        $dummy = preg_replace('/[$]{MEMBER::[^}]*}/', '', $dummy);
      }

      // Now check for global substitutions
      $globalTemplateLeftOver = array();
      if (preg_match('![$]{GLOBAL::[^}]+}!', $dummy)) {
        $variables = $this->emailGlobalVariables();
        foreach ($variables as $key => $value) {
          $dummy = preg_replace('/[$]{GLOBAL::'.$key.'}/', $value, $dummy);
        }

        if (preg_match('![$]{GLOBAL::[^}]+}!', $dummy, $globalTemplateLeftOver)) {
          $templateError[] = 'global';
        }        

        // Now remove all global variables, known or not
        $dummy = preg_replace('/[$]{GLOBAL::[^}]*}/', '', $dummy);
      }

      $spuriousTemplateLeftOver = array();      
      // No substitutions should remain. Check for that.
      if (preg_match('![$]{[^}]+}!', $dummy, $spuriousTemplateLeftOver)) {
        $templateError[] = 'spurious';
      }
      
      if (count($templateError) == 0) {
        return true;  
      }
      
      // Otherwise construct a cooked status return
      return array('MemberErrors' => $memberTemplateLeftOver,
                   'GlobalErrors' => $globalTemplateLeftOver,
                   'SpuriousErrors' => $spuriousTemplateLeftOver);
    }

    private function setDefaultTemplate() 
    {
      // Make sure that at least the default template exists and install
      // that as default text
      $this->initialTemplate = self::DEFAULT_TEMPLATE;
      
      $dbTemplate = $this->fetchTemplate('Default');
      if ($dbTemplate === false) {
        $this->storeTemplate('Default', $this->initialTemplate);
      } else {
        $this->initialTemplate = $dbTemplate;
      }
    }

    private function setCatchAll() 
    {
      if ($this->constructionMode) {      
        $this->catchAllEmail = Config::getValue('emailtestaddress');
        $this->catchAllName  = 'Bilbo Baggins';
      } else {
        $this->catchAllEmail = Config::getValue('emailfromaddress');
        $this->catchAllName  = Config::getValue('emailfromname');
      }    
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
      $globalVars = array(
        'ORGANIZER' => $this->fetchExecutiveBoard(),
        'CREDITORIDENTIFIER' => Config::getValue('bankAccountCreditorIdentifier'),
        'ADDRESS' => $this->streetAddress(),
        'BANKACCOUNT' => $this->bankAccount(),
        );

      return $globalVars;
    }

    private function streetAddress()
    {
      return
        Config::getValue('streeAddressName01')."<br/>\n".
        Config::getValue('streeAddressName02')."<br/>\n".
        Config::getValue('streeAddressStreet')."&nbsp;".
        Config::getValue('streeAddressHouseNumber')."<br/>\n".
        Config::getValue('streeAddressZIP')."&nbsp;".
        Config::getValue('streeAddressCity');
    }

    private function bankAccount()
    {
      $iban = new \IBAN(Config::getValue('bankAccountIBAN'));
      return
        Config::getValue('bankAccountOwner')."<br/>\n".
        "IBAN ".$iban->HumanFormat()."<br/>\n".
        "BIC ".Config::getValue('bankAccountBIC');
    }

    /**Fetch the pre-names of the members of the organizing committee in
     * order to construct an up-to-date greeting.
     */
    private function fetchExecutiveBoard()
    {
      $executiveBoard = Config::getValue('executiveBoardTable');

      $handle = $this->dataBaseConnect();

      $query = "SELECT `Vorname` FROM `".$executiveBoard."View` ORDER BY `Reihung`,`Stimmführer`,`Vorname`";

      $result = mySQL::query($query, $handle);
    
      if ($result === false) {
        throw new \RuntimeException(L::t('Unable to fetch executive board contents from data-base.'));
      }

      $vorstand = array();
      while ($line = mysql_fetch_assoc($result)) {
        $vorstand[] = $line['Vorname'];
      }

      $cnt = count($vorstand);
      $text = $vorstand[0];
      for ($i = 1; $i < $cnt-1; $i++) {
        $text .= ', '.$vorstand[$i];
      }
      $text .= ' '.L::t('and').' '.$vorstand[$cnt-1];

      return $text;
    }
  
    private function dataBaseConnect()
    {
      if ($this->dbh === false) {
        $this->dbh = mySQL::connect($this->opts);
      }
      return $this->dbh;
    }

    private function dataBaseDisconnect()
    {
      if ($this->dbh !== false) {
        mySQL::close($this->dbh);
        $this->dbh = false;
      }
    }

    /**Take the text supplied by $contents and store it in the DB
     * EmailTemplates table with tag $templateName. An existing template with the
     * same tag will be replaced.
     */
    private function storeTemplate($templateName, $contents)
    {
      $handle = $this->dataBaseConnect();

      $contents = mysql_real_escape_string($contents, $handle);

      $query = "REPLACE INTO `EmailTemplates` (`Tag`,`Contents`)
  VALUES ('".$templateName."','".$contents."')";

      // Ignore the result at this point.o
      mySQL::query($query, $handle);
    }

    /**Delete the named email template.
     */
    private function deleteTemplate($templateName)
    {
      $handle = $this->dataBaseConnect();

      $query = "DELETE FROM `EmailTemplates` WHERE `Tag` LIKE '".$templateName."'";

      // Ignore the result at this point.o
      mySQL::query($query, $handle);
    }

    /**Fetch a specific template from the DB. Return false if that template is not found
     */
    private function fetchTemplate($templateName)
    {
      $handle = $this->dataBaseConnect();

      $query   = "SELECT * FROM `EmailTemplates` WHERE `Tag` LIKE '".$templateName."'";
      $result  = mySQL::query($query, $handle);
      $line    = mysql_fetch_assoc($result);
      $numrows = mysql_num_rows($result);

      return $numrows == 1 ? $line['Contents'] : false;
    }
  
    /**Return a flat array with all known template names.
     */
    private function fetchTemplateNames()
    {
      $handle = $this->dataBaseConnect();

      $query  = "SELECT `Tag` FROM `EmailTemplates` WHERE 1";
      $result = mySQL::query($query, $handle);
      $names  = array();
      while ($line = mysql_fetch_assoc($result)) {
        $names[] = $line['Tag'];
      }

      return $names;
    }


    /**** file temporary utilities ***/


    /**Delete all temorary files not found in $fileAttach. If the file
     * is successfully removed, then it is also removed from the
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

    /**** public methods exporting data needed by the web-page template ***/

    /**General form data for hidden input elements.*/
    public function formData() 
    {
      return array('FormStatus' => 'submitted');
    }

    /**Return the current catch-all email. */
    public function catchAllEmail()
    {
      return htmlspecialchars($this->catchAllName.' <'.$this->catchAllEmail.'>');
    }

    /**Compose one "readable", comma separated list of recipients,
     * meant only for display. The real recipients list is composed
     * somewhere else.
     */
    public function toString()
    {
      $toString = array();
      foreach($this->recipients as $recipient) {
        $name = trim($recipient['name']);
        $email = trim($recipient['email']);
        if ($name == '') {
          $toString[] = $email;
        } else {
          $toString[] = $name.' <'.$email.'>';
        }
      }
      return htmlspecialchars(implode(', ', $toString));
    }

    /**Export the array of email templates. */
    public function emailTemplates()
    {
      return $this->templateNames;
    }

    /**Export the currently selected template name. */
    public function currentEmailTemplate()
    {
      return $this->templateName;      
    }

    /**Export the subject tag depending on whether we ar in "project-mode" or not. */
    public function subjectTag()
    {
      return $this->messageTag;
    }

    /**Export the From: name. This is modifiable. The From: email
     * address, however, is fixed in order to prevent abuse.
     */
    public function fromName()
    {
      return $this->cgiValue('FromName', $this->catchAllName);
    }
    
    /**Return the current From: addres. This is fixed and cannot be changed. */
    public function fromAddress()
    {
      return htmlspecialchars($this->catchAllEmail);
    }

    /**In principle the most important stuff: the message text. */
    public function messageText()
    {
      return $this->messageContents;
    }
    
    /**Export BCC. */
    public function blindCarbonCopy()
    {
      return $this->cgiValue('BCC', '');
    }
    
    /**Export CC. */
    public function carbonCopy()
    {
      return $this->cgiValue('BCC', '');
    }

    /**Export Subject. */
    public function subject()
    {
      return $this->cgiValue('Subject', '');
    }

    /**Return the file attachment data. */
    public function fileAttachments()
    {
      // JSON encoded array
      $fileAttachJSON = $this->cgiValue('FileAttach', '{}');
      $fileAttach = json_decode($fileAttachJSON, true);
      $selectedAttachments = $this->cgiValue('AttachedFiles', array());
      $selectedAttachments = array_flip($selectedAttachments);
      $localFileAttach = array();
      $ocFileAttach = array();
      foreach($fileAttach as $attachment) {
        if ($attachment['status'] == 'new') {
          $attachment['status'] = 'selected';
        } else if (isset($selectedAttachments[$attachment['tmp_name']])) {
          $attachment['status'] = 'selected';
        } else {
          $attachment['status'] = 'inactive';
        }
        $attachment['name'] = basename($attachment['name']);
        if($attachment['origin'] == 'owncloud') {
          $ocFileAttach[] = $attachment;
        } else {
          $localFileAttach[] = $attachment;
        }
      }
      
      usort($ocFileAttach, function($a, $b) {
          return strcmp($a['name'], $b['name']);
        });
      usort($localFileAttach, function($a, $b) {
          return strcmp($a['name'], $b['name']);
        });

      return array_merge($localFileAttach, $ocFileAttach);
    }

    /**A helper function to generate suitable select options for
     * Navigation::selectOptions()
     */
    static public function fileAttachmentOptions($fileAttach)
    {
      $selectOptions = array();
      foreach($fileAttach as $attachment) {
        $value    = $attachment['tmp_name'];
        $size     = \OC_Helper::humanFileSize($attachment['size']);
        $name     = $attachment['name'].' ('.$size.')';
        $group    = $attachment['origin'] == 'owncloud' ? 'Owncloud' : L::t('Local Filesystem');
        $selected = $attachment['status'] == 'selected';
        $selectOptions[] = array('value' => $value,
                                 'name' => $name,
                                 'group' => $group,
                                 'flags' => $selected ? Navigation::SELECTED : 0);
      }
      return $selectOptions;
    }
    
    /**Return the file attachment data. This function checks for the
     * cgi-values of EventSelect or the "local" cgi values
     * emailComposer[AttachedEvents]. The "legacy" values take
     * precedence.
     */
    public function eventAttachments()
    {
      $attachedEvents = Util::cgiValue('EventSelect',
                                       $this->cgiValue('AttachedEvents', array()));
      return $attachedEvents;      
    }

    /**A helper function to generate suitable select options for
     * Navigation::selectOptions().
     *
     * @param $attachedEvents Flat array of attached events.
     */
    static public function eventAttachmentOptions($projectId, $attachedEvents)
    {
      if ($projectId <= 0) {
        return array();
      }

      // fetch all events for this project
      $events      = Events::events($projectId);
      $dfltIds     = Events::defaultCalendars();
      $eventMatrix = Events::eventMatrix($events, $dfltIds);

      // timezone, locale
      $locale = Util::getLocale();
      $zone = Util::getTimezone();

      // transpose for faster lookup
      $attachedEvents = array_flip($attachedEvents);

      // build the select option control array
      $selectOptions = array();
      foreach($eventMatrix as $eventGroup) {
        $group = $eventGroup['name'];
        foreach($eventGroup['events'] as $event) {
          $object = $event['object'];
          $datestring = Events::briefEventDate($object, $zone, $locale);
          $name = stripslashes($object['summary']).', '.$datestring;
          $value = $event['EventId'];
          $selectOptions[] = array('value' => $value,
                                   'name' => $name,
                                   'group' => $group,
                                   'flags' => isset($attachedEvents[$value]) ? Navigation::SELECTED : 0
            );
        }
      }
      return $selectOptions;
    }
    
    /**If a complete reload has to be done ... for now */
    public function reloadState() 
    {
      return true;
    }

    /**Return the dispatch status. */
    public function errorStatus()
    {
      return !$this->executionStatus;
    }

    /**Return possible diagnostics or not. Depending on operation. */
    public function errorDiagnostics()
    {
      return $this->diagnostics;
    }

  };

} // CAFEVDB

?>
