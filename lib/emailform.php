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
  class EmailRecipientsFilter {
    const MAX_HISTORY_SIZE = 25; // the history is posted around, so ...

    private $projectId;   // Project id or NULL or -1 or ''
    private $projectNem;  // Project name of NULL or ''
    private $instrumentsFilter; // Current instrument filter
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
    private $memberStatusNames;

    private $cgiData;   // copy of cgi-data
    private $submitted; // form has been submitted
    private $reload;    // form must be reloaded
    
    private $jsonFlags; 
    static private $historyKeys = array('BasicRecipientsSet',
                                        'MemberStatusFilter',
                                        'InstrumentsFilter',
                                        'SelectedRecipients');
    private $filterHistory;
    private $historyPosition;
    private $historySize;    

    /* 
     * constructor
     */
    public function __construct()
    {
      $this->jsonFlags = JSON_FORCE_OBJECT|JSON_HEX_QUOT|JSON_HEX_APOS;
      if (Util::debugMode('request')) {
        echo '<PRE>';
        print_r($_POST);
        echo '</PRE>';
      }

      Config::init();
      $this->opts = Config::$pmeopts;

      // Fetch all data submitted by form
      $this->cgiData = Util::cgiValue('emailRecipients', array());

      // Quirk: the usual checkbox issue
      $this->cgiData['BasicRecipientsSet']['FromProject'] =
        isset($this->cgiData['BasicRecipientsSet']['FromProject']);
      $this->cgiData['BasicRecipientsSet']['ExceptProject'] =
        isset($this->cgiData['BasicRecipientsSet']['ExceptProject']);

      $this->projectId = Util::cgiValue('ProjectId', -1);
      $this->projectName   = Util::cgiValue('Project', '');

      // See wether we were passed specific variables ...
      $pmepfx          = $this->opts['cgi']['prefix']['sys'];
      $this->emailKey  = $pmepfx.'mrecs';
      $this->mtabKey   = $pmepfx.'mtable';

      $this->execute();
    }

    /**Parse the CGI stuff and compute the list of selected musicians,
     * either for the initial form setup as during the interaction
     * with the user.
     */
    private function execute() 
    {
      // Maybe should also check something else. If submitted is true,
      // then we use the form data, otherwise the defaults.
      $this->submitted = $this->cgiValue('FormStatus', '') == 'submitted';

      // "sane" default setttings
      $this->EmailRecs = Util::cgiValue($this->emailKey, array());
      $this->reload = false;

      if ($this->submitted) {
        if ($this->cgiValue('ResetInstrumentsFilter', false) !== false) {
          $this->EmailRecs = $this->cgiValue($this->emailKey, array());
          $this->submitted = false; // fall back to defaults for everything
          $this->cgiData = array();
          $this->reload = true;
        } else if ($this->cgiValue('UndoInstrumentsFilter', false) !== false) {
          $this->applyHistory(1); // the current state
          $this->reload = true;
        } else if ($this->cgiValue('RedoInstrumentsFilter', false) !== false) {
          $this->applyHistory(-1);
          $this->reload = true;
        }
      }

      $dbh = mySQL::connect($this->opts);
      
      $this->remapEmailRecords($dbh);
      $this->getMemberStatusNames($dbh);
      $this->initMemberStatusFilter();
      $this->getUserBase();
      $this->getInstrumentsFromDB($dbh);
      $this->fetchInstrumentsFilter();
      $this->getMusiciansFromDB($dbh);

      mySQL::close($dbh);

      if (!$this->submitted) {
        // Do this at end in order to have any tweaks around
        $this->setDefaultHistory();
      } else if (!$this->reload) {
        // add the current selection to the history if it is different
        // from the previous filter selection (i.e.: no-ops like
        // hitten apply over and over again or multiple double-clicks
        // will not alter the history.
        $this->pushHistory();
      }
    }

    private function cgiValue($key, $default = null)
    {
      if (isset($this->cgiData[$key])) {
        return $this->cgiData[$key];
      } else {
        return $default;
      } 
    }
    
    /**Compose a default history record for the initial state */
    private function setDefaultHistory()
    {
      $this->historyPosition = 0;
      $this->historySize = 1;


      $filter = array(
        'BasicRecipientsSet' => $this->defaultUserBase(),
        'MemberStatusFilter' => $this->defaultByStatus(),
        'InstrumentsFilter' => array(),
        'SelectedRecipients' => array_intersect($this->EmailRecs,
                                                array_keys($this->EMailsDpy)));

      // tweak: sort the selected recipients by key
      sort($filter['SelectedRecipients']);

      $this->filterHistory = array($filter);
    }

    private function compressHistory()
    {
      return base64_encode(gzencode(json_encode($this->filterHistory, $this->jsonFlags)));
    }

    private function decompressHistory($data)
    {
      $decompressedHistory = json_decode(gzdecode(base64_decode($data)), true);
      if ($decompressedHistory === false) {
        return false;
      }
      $this->filterHistory = $decompressedHistory;
      return true;
    }

    /**Decode he current history, filter values and history position
     * in order to keep track of undo/redo requests. Rather lengthy,
     * but will return some history record and throw useful errors
     * when debug is enabled.
     */
    private function fetchHistory()
    {
      if (!$this->submitted) {
        $this->setDefaultHistory();
        return;
      }

      $jsonHistory = $this->cgiValue('FilterHistory', false);
      if ($jsonHistory === false) {
        if (Util::debugMode('emailform')) {
          throw new \UnexpectedValueException(L::t('No filter-history available'));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }
      
      $history = json_decode($jsonHistory, true);
      if ($history === false) {
        if (Util::debugMode('emailform')) {
          throw new \InvalidArgumentException(L::t('Unable to decode history from JSON: %s.',
                                                   array($jsonHistory)));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }

      if (!isset($history['historyPosition']) ||
          !isset($history['historySize']) ||
          !isset($history['historyData'])) {
        if (Util::debugMode('emailform')) {
          throw new \UnexpectedValueException(L::t('Incomplete history data: %s (JSON: %s)',
                                                   array(print_r($history, true), $jsonHistory)));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }


      if (!$this->decompressHistory($history['historyData'])) {
        if (Util::debugMode('emailform')) {
          throw new \InvalidArgumentException(L::t('Unable to decompress history from JSON: %s.',
                                                   array($jsonHistory)));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }

      $this->historyPosition = $history['historyPosition'];
      $this->historySize = $history['historySize'];
      if ($this->historySize != count($this->filterHistory)) {
        if (Util::debugMode('emailform')) {
          throw new \OutOfBoundsException(L::t('Submitted history size %d != actual history size %d',
                                              array($this->historySize, count($this->filterHistory))));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }
      if ($this->historyPosition < 0 || $this->historyPosition > $this->historySize) {
        if (Util::debugMode('emailform')) {
          throw new \OutOfBoundsException(L::t('Submitted history position %d outside history size %d.',
                                               array($this->historyPosition, $this->historySize)));
        } else {
          $this->setDefaultHistory();
          return;
        }
      }
    }
    
    /**Push the current filter selection onto the undo-history
     * stack. Do nothing for dummy commits, i.e. only a changed filter
     * will be pushed onto the stack.
     */
    private function pushHistory()
    {
      $filter = array();
      foreach (self::$historyKeys as $key) {
        $filter[$key] = $this->cgiValue($key, array());
      }

      // exclude musicians deselected by the filter from the set of
      // selected recipients before recording the the history
      $filter['SelectedRecipients'] =
        array_intersect($filter['SelectedRecipients'],
                        array_keys($this->EMailsDpy));

      // tweak: sort the selected recipients by key
      sort($filter['SelectedRecipients']);

      $this->fetchHistory();

      /* Avoid pushing duplicate history entries. If the new
       * filter-record matches the current one, then simply discard
       * the new filter. This is in order to avoid bloating the
       * history records by repeated user submits of the same filter
       * or duplicated double-clicks.
       */
      $historyFilter = $this->filterHistory[$this->historyPosition];
      if (!$this->filterEqual($filter, $historyFilter)) {
        // Pushing a new record removes the history up to the current
        // position, i.e. redos are not longer possible then. This
        // seems to be common behaviour as "re-doing" is no longer
        // well defined in this case.
        array_splice($this->filterHistory, 0, $this->historyPosition);
        array_unshift($this->filterHistory, $filter);
        $this->historyPosition = 0;
        $this->historySize = count($this->filterHistory);
        while ($this->historySize > self::MAX_HISTORY_SIZE) {
          array_pop($this->filterHistory);
          --$this->historySize;
        }
      }
    }

    /**Relative move inside the history. The function will throw an
     * exception if emailform-debuggin is enabled and the requested
     * action would leave the history stack.
     */
    private function applyHistory($offset)
    {
      $this->fetchHistory();

      $newPosition = $this->historyPosition + $offset;
      
      // Check for valid position.
      if ($newPosition >= $this->historySize || $newPosition < 0) {
        if (Util::debugMode('emailform')) {
          throw new \OutOfBoundsException(
            L::t('Invalid history position %d request, history size is %d',
                 array($newPosition, $this->historySize)));
        }
        return;
      }

      // Move past the respective history position.
      $this->historyPosition = $newPosition;
      $filter = $this->filterHistory[$newPosition];
      foreach (self::$historyKeys as $key) {
        $this->cgiData[$key] = $filter[$key];
      }
    }

    /**Return true if we consider both filters to be equal
     */
    private function filterEqual($filter1, $filter2)
    {
      return json_encode($filter1, $this->jsonFlags) == json_encode($filter2, $this->jsonFlags);
    }
    

    /**This function is called at the very start. If in project-mode
     * ids from other tables are remapped to the ids for the
     * respective project view.
     */
    private function remapEmailRecords($dbh)
    {
      $table = Util::cgiValue($this->mtabKey,'');

      if ($this->projectId >= 0 &&
          ($table == 'Besetzungen' || $table == 'SepaDebitMandates')) {

        /*
         * This means we have been called from the "brief"
         * instrumentation view. The other possibility is to be called
         * from the detailed view, or the total view of all musicians.
         *
         * If called from the "Besetzungen" table, we remap the Id's
         * to the project view table and continue with that.
         */
        $oldTable = $table;
        $table = $this->projectName.'View';
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
      }
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
        ';
      if (count($this->instrumentsFilter) == 0) {
        $query .= '1
        ';
      } else {
        $query .= '(';
        foreach ($this->instrumentsFilter as $value) {
          $query .= "`".$restrict."` LIKE '%".$value."%' OR\n";
        }
        $query .= ' 0)
        ';
      }

      /* Don't bother any conductor etc. with mass-email. */
      $query .= $this->memberStatusSQLFilter();

      if (false) {
        echo '<PRE>';
        echo $query;
        echo '</PRE>';
      }
      $_POST['QUERY'] = $query;

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

    /* Fetch the list of musicians for the given context (project/global)
     */
    private function getMusiciansFromDB($dbh)
    {
      $this->NoMail = array();
      $this->EMails = array();
      $this->EMailsDpy = array(); // display records

      if ($this->projectId < 0) {        
        self::fetchMusicians($dbh, 'Musiker', 'Id', 'Instrumente', -1, true);
      } else {
        // Possibly add musicians from the project
        if ($this->userBase['FromProject']) {
          self::fetchMusicians($dbh,
                               '`'.$this->projectName.'View'.'`', 'MusikerId', 'Instrument',
                               $this->projectId, true);
        }

        // and/or not from the project
        if ($this->userBase['ExceptProject']) {
          $table =
            '(SELECT a.* FROM Musiker as a
    LEFT JOIN `'.$this->projectName.'View'.'` as b
      ON a.Id = b.MusikerId 
      WHERE b.MusikerId IS NULL) as c';
          self::fetchMusicians($dbh, $table, 'Id', 'Instrumente', -1, true);
        }

        // And otherwise leave it empty ;)
      }

      // Finally sort the display array
      asort($this->EMailsDpy);
    }

    /* Fetch the list of instruments (either only for project or all)
     *
     * Also: construct the filter by instrument.
     */
    private function getInstrumentsFromDb($dbh)
    {
      // Get the current list of instruments for the filter
      if ($this->projectId >= 0 && !$this->userBase['ExceptProject']) {
        $this->instruments = Instruments::fetchProjectMusiciansInstruments($this->projectId, $dbh);
      } else {
        $this->instruments = Instruments::fetch($dbh);
      }
      array_unshift($this->instruments, '*');
    }

    private function fetchInstrumentsFilter()
    {
      /* Remove instruments from the filter which are not known by the
       * current list of musicians.
       */
      $filterInstruments = $this->cgiValue('InstrumentsFilter', array());
      array_intersect($filterInstruments, $this->instruments);
      
      $this->instrumentsFilter = array();
      foreach ($filterInstruments as $value) {
        $this->instrumentsFilter[] = $value;
      }
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

    private function getMemberStatusNames($dbh)
    {
      $memberStatus = mySQL::multiKeys('Musiker', 'MemberStatus', $dbh);
      $this->memberStatusNames = array();
      foreach ($memberStatus as $tag) {
        $this->memberStatusNames[$tag] = strval(L::t($tag));
      }
    }  

    /*Get the current filter. Default value, after form submission,
     * initial setting otherwise.
     */
    private function initMemberStatusFilter() 
    {
      if ($this->submitted) {
        $this->memberFilter = $this->cgiValue('MemberStatusFilter', array());
      } else {
        $this->memberFilter = $this->defaultByStatus();
      }  
    }
    

    /**Form a SQL filter expression for the memeber status. */
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

    /**The default user base. Simple, but just keep the scheme in sync
     * with the other two filters and provide a default....()
     * function.
     */
    private function defaultUserBase()
    {
      return array('FromProject' => $this->projectId >= 0,
                   'ExceptProject' => false);
    }

    /**Decode the check-boxes which select the set of users we
     * consider basically.
     */
    private function getUserBase()
    {
      if (!$this->submitted) {
        $this->userBase = $this->defaultUserBase();
      } else {
        $this->userBase = $this->cgiValue('BasicRecipientsSet', false);
        if ($this->userBase === false) {
          $this->userBase = $this->defaultUserBase();
        }
      }
    }

    /**Return an array of values we want to maintain on form-submit,
     * intentionally for wrapping into hidden input fields.
     */
    public function formData()
    {
      return array($this->emailKey => $this->EmailRecs,
                   'FormStatus' => 'submitted');
    }

    /**Return the current filter history and the filter position as
     * JSON encoded string.
     */
    public function filterHistory()
    {
      $history = array('historyPosition' => $this->historyPosition,
                       'historySize' => count($this->filterHistory),
                       'historyData' => $this->compressHistory());
      return json_encode($history, $this->jsonFlags);
    }

    /**Return the current value of the member status filter or its
     * initial value.
     */
    public function memberStatusFilter()
    {
      $memberStatus = $this->cgiValue('MemberStatusFilter',
                                      $this->submitted ? '' : $this->defaultByStatus());
      $memberStatus = array_flip($memberStatus);
      $result = array();
      foreach($this->memberStatusNames as $tag => $name) {
        $result[] =  array('value' => $tag,
                           'name' => $name,
                           'flags' => isset($memberStatus[$tag]) ? Navigation::SELECTED : 0);
      }
      return $result;
    }

    /**Return the user basic set for the email form template
     */
    public function basicRecipientsSet()
    {
      return array('FromProject' => $this->userBase['FromProject'] ? 1 : 0,
                   'ExceptProject' => $this->userBase['ExceptProject'] ? 1 : 0);
    }

    /**Return the values for the instruments filter.
     *
     * TODO: group by instrument kind (strings, wind etc.)
     */
    public function instrumentsFilter()
    {
      $filterInstruments = $this->cgiValue('InstrumentsFilter', array('*'));
      $filterInstruments = array_flip(array_intersect($filterInstruments, $this->instruments));
      $result = array();
      foreach($this->instruments as $instrument) {
        $name = $instrument;
        $value = $instrument == '*'? '' : $instrument;
        $result[] = array('value' => $value,
                          'name' => $name,
                          'flags' => isset($filterInstruments[$instrument]) ? Navigation::SELECTED : 0);
      }
      return $result;
    }
    
    /**Return the values for the recipient select box */
    public function emailRecipientsChoices()
    {
      if ($this->submitted) {
        $selectedRecipients = $this->cgiValue('SelectedRecipients', array());
      } else {
        $selectedRecipients = $this->EmailRecs;
      }
      $selectedRecipients = array_flip($selectedRecipients);
      $result = array();
      foreach($this->EMailsDpy as $key => $email) {
        $result[] = array('value' => $key,
                          'name' => $email,
                          'flags' => isset($selectedRecipients[$key]) ? Navigation::SELECTED : 0);
      }
      return $result;
    }
    
    /**Return a list of musicians without email address, if any. */
    public function missingEmailAddresses()
    {
      $result = array();
      foreach ($this->NoMail as $person) {
        $result[] = $person['name'];
      }
      sort($result);
      return $result;
    }

    /**Return true if in initial state */
    public function initialState() {
      return !$this->submitted;
    }

    /**Return true if in reload state */
    public function reloadState() {
      return $this->reload;
    }
    
    /**Return the list of selected recipients. To have this method is
     * in principle the goal of all the mess above ...
     */
    public function selectedRecipients()
    {
      if ($this->submitted) {
        $selectedRecipients = $this->cgiValue('SelectedRecipients', array());
      } else {
        $selectedRecipients = $this->EmailRecs;
      }
      $selectedRecipients = array_unique($selectedRecipients);
      $EMails = array();
      foreach ($selectedRecipients as $key) {
        if (isset($this->EMails[$key])) {
          $EMails[] = $this->EMails[$key];
        }
      }
      return $EMails;
    }
    
  };

} // CAFEVDB

?>
