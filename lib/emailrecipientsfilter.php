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

  /**Wrap the email filter form into a class to make things a little
   * less crowded. This is actually not to filter emails, but rather to
   * select specific groups of musicians (depending on instrument and
   * project).
   */
  class EmailRecipientsFilter {
    const MAX_HISTORY_SIZE = 100; // the history is posted around, so ...
    const SESSION_HISTORY_KEY = 'FilterHistory';
    private $session;

    private $projectId;   // Project id or NULL or -1 or ''
    private $projectNem;  // Project name of NULL or ''
    private $instrumentsFilter; // Current instrument filter
    private $userBase;    // Select from either project members and/or
    // all musicians w/o project-members
    private $memberFilter;// passive, regular, soloist, conductor, temporary
    private $EmailsRecs;  // Copy of email records from CGI env
    private $emailKey;    // Key for EmailsRecs into _POST or _GET

    private $instruments; // List of instruments for filtering
    private $instrumentGroups; // mapping of instruments to groups.
  
    private $opts;        // Copy of global options

    private $brokenEMail;     // List of people without email
    private $EMails;      // List of people with email  
    private $EMailsDpy;   // Display list with namee an email
    private $frozen;      // Only allow the preselected recipients (i.e. for debit notes)

    // Form elements
    private $memberStatusNames;

    private $cgiData;   // copy of cgi-data
    private $submitted; // form has been submitted
    private $reload;    // form must be reloaded
    private $snapshot;  // lean history snapshot, only the history data is valid
    
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

      $this->session = new Session();

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
      $this->projectName   = Util::cgiValue('ProjectName',
                                            Util::cgiValue('ProjectName', ''));

      // See wether we were passed specific variables ...
      $pmepfx          = $this->opts['cgi']['prefix']['sys'];
      $this->emailKey  = $pmepfx.'mrecs';
      $this->mtabKey   = $pmepfx.'mtable';

      $this->frozen = $this->cgiValue('FrozenRecipients', false);

      $this->execute();
    }

    /**Store the history records to the session data. */
    public function __destruct()
    {
      $this->storeHistory();
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
      $this->snapshot = false;

      if ($this->submitted) {
        $this->loadHistory(); // Fetch the filter-history from the session, if any.
        $this->EmailRecs = $this->cgiValue($this->emailKey, array());
        if ($this->cgiValue('ResetInstrumentsFilter', false) !== false) {
          $this->submitted = false; // fall back to defaults for everything
          $this->cgiData = array();
          $this->reload = true;
        } else if ($this->cgiValue('UndoInstrumentsFilter', false) !== false) {
          $this->applyHistory(1); // the current state
          $this->reload = true;
        } else if ($this->cgiValue('RedoInstrumentsFilter', false) !== false) {
          $this->applyHistory(-1);
          $this->reload = true;
        } else if ($this->cgiValue('HistorySnapshot', false) !== false) {
          // fast mode, only CGI data, no DB access. Only the
          // $this->filterHistory() should be queried afterwards,
          // everything else is undefined.
          $this->snapshot = true;
          $this->pushHistory(true);
          return;
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

    /**Fetch a CGI-variable out of the form-select name-space */
    private function cgiValue($key, $default = null)
    {
      if (isset($this->cgiData[$key])) {
        $value = $this->cgiData[$key];
        if (is_string($value)) {
          $value = trim($value);
        }
        return $value;
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

      $md5 = md5(serialize($filter));
      $data = $filter;
      $this->filterHistory = array(array('md5' => $md5,
                                         'data' => $data));
    }

    /**Store the history to somewhere, probably the session-data. */
    private function storeHistory()
    {
      $storageValue = array('size' => $this->historySize,
                            'position' => $this->historyPosition,
                            'records' => $this->filterHistory);
      //throw new \Exception(print_r($storageValue, true));
      $this->session->storeValue(self::SESSION_HISTORY_KEY, $storageValue);
    }

    /**Load the history from the session data. */
    private function loadHistory()
    {
      $loadHistory = $this->session->retrieveValue(self::SESSION_HISTORY_KEY);
      if (!$this->validateHistory($loadHistory)) {
        $this->setDefaultHistory();
        return false;
      }
      $this->historySize = $loadHistory['size'];
      $this->historyPosition = $loadHistory['position'];
      $this->filterHistory = $loadHistory['records'];
      return true;
    }

    /**Validate the given history records, return false on error.
     */
    private function validateHistory($history)
    {
      if ($history === false ||
          !isset($history['size']) ||
          !isset($history['position']) ||
          !isset($history['records']) ||
          !$this->validateHistoryRecords($history)) {
        return false;
      }
      return true;
    }
    
    /**Validate one history entry */
    private function validateHistoryRecord($record) {
      if (!is_array($record)) {
        return false;
      }
      $md5 = '';
      if (!isset($record['md5']) ||
          !isset($record['data']) ||
          $record['md5'] != ($md5 = md5(serialize($record['data'])))) {
        return false;
      }
      return true;
    }
    
    /**Validate all history records. */
    private function validateHistoryRecords($history) {
      foreach($history['records'] as $record) {
        if (!$this->validateHistoryRecord($record)) {
          return false;
        }
      }
      return true;
    }    

    /**Push the current filter selection onto the undo-history
     * stack. Do nothing for dummy commits, i.e. only a changed filter
     * will be pushed onto the stack.
     */
    private function pushHistory($fastMode = false)
    {
      $filter = array();
      foreach (self::$historyKeys as $key) {
        $filter[$key] = $this->cgiValue($key, array());
      }

      if (!$fastMode) {
        // exclude musicians deselected by the filter from the set of
        // selected recipients before recording the history
        $filter['SelectedRecipients'] =
          array_intersect($filter['SelectedRecipients'],
                          array_keys($this->EMailsDpy));
        
        // tweak: sort the selected recipients by key
      }

      sort($filter['SelectedRecipients']);
      $md5 = md5(serialize($filter));

      /* Avoid pushing duplicate history entries. If the new
       * filter-record matches the current one, then simply discard
       * the new filter. This is in order to avoid bloating the
       * history records by repeated user submits of the same filter
       * or duplicated double-clicks.
       */
      $historyFilter = $this->filterHistory[$this->historyPosition];
      if ($historyFilter['md5'] != $md5) {
        // Pushing a new record removes the history up to the current
        // position, i.e. redos are not longer possible then. This
        // seems to be common behaviour as "re-doing" is no longer
        // well defined in this case.
        array_splice($this->filterHistory, 0, $this->historyPosition);
        array_unshift($this->filterHistory, array('md5' => $md5,
                                                  'data' => $filter));
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

      // Move to the new history position.
      $this->historyPosition = $newPosition;

      $filter = $this->filterHistory[$newPosition]['data'];
      foreach (self::$historyKeys as $key) {
        $this->cgiData[$key] = $filter[$key];
      }
    }

    /**This function is called at the very start. If in project-mode
     * ids from other tables are remapped to the ids for the
     * respective project vie.w
     */
    private function remapEmailRecords($dbh)
    {
      $oldTable = Util::cgiValue($this->mtabKey,'');
      $remap = false;
      
      if ($oldTable == 'InstrumentInsurance') {
        $this->projectName = Config::getValue('memberTable');
        $this->projectId = Config::getValue('memberTableId');
        
        $this->frozen = true; // restrict to initial set of recipients

        $table = $this->projectName.'View';

                // Remap all email records to the ids from the project view.
        $query = 'SELECT `'.$oldTable.'`.`Id` AS \'OrigId\',
  `'.$table.'`.`Id` AS \'BesetzungsId\'
  FROM `'.$table.'` RIGHT JOIN `'.$oldTable.'`
  ON (
       `'.$oldTable.'`.`BillToParty` <= 0
       AND
       `'.$table.'`.`MusikerId` = `'.$oldTable.'`.`MusicianId`
    ) OR (
       `'.$oldTable.'`.`BillToParty` > 0
       AND
       `'.$table.'`.`MusikerId` = `'.$oldTable.'`.`BillToParty`
    )
    WHERE 1';

        $remap = true;
      }
      
      if ($this->projectId >= 0 && $oldTable == 'SepaDebitMandates') {
        $this->frozen = true; // restrict to initial set of recipients
        
        $table = $this->projectName.'View';

        // Remap all email records to the ids from the project view.
        $query = 'SELECT `'.$oldTable.'`.`id` AS \'OrigId\',
  `'.$table.'`.`Id` AS \'BesetzungsId\'
  FROM `'.$table.'` LEFT JOIN `'.$oldTable.'`
  ON `'.$table.'`.`MusikerId` = `'.$oldTable.'`.`musicianId`
  WHERE (`'.$oldTable.'`.`projectId` = '.$this->projectId.
          ' OR '.
          '`'.$oldTable.'`.`projectId` = '.Config::getValue('memberTableId').
          ')';

        // $_POST['QUERY'] = $query;
        $remap = true;
      }

      if ($remap) {
        // Fetch the result (or die) and remap the Ids
        $result = mySQL::query($query, $dbh);
        $map = array();
        while ($line = mysql_fetch_assoc($result)) {
          $map[$line['OrigId']] = $line['BesetzungsId'];
        }
        $newEmailRecs = array();
        foreach ($this->EmailRecs as $key) {
          if (!isset($map[$key])) {
            if (false) {
              // can happen after deleting records
              // TODO: sanitize this.
              Util::error(L::t('Musician %d in Table, but has no Id as Musician. '.
                               'POST: %s'.
                               'Map: %s'.
                               'SQL-Query: %s',
                               array($key, print_r($_POST, true), print_r($map, true), $query)));
            }
            continue;
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
                           'MobilePhone',
                           'FixedLinePhone',
                           'Strasse',
                           'Postleitzahl',
                           'Stadt',
                           'Land',
                           'Geburtstag',
                           'MemberStatus');
      $btk = '`';
      $comma = ',';
      $sep = $btk.$comma.$btk;
      $dot = '.';
      $origId = $btk.'MainTable'.$btk.$dot.$btk.'Id'.$btk.' AS '.$btk.'OrigId'.$btk;
      $fields =
        $origId.$comma.$btk.$id.$btk.' AS '.$btk.'musicianId'.$btk.$comma.
        $btk.implode($sep, $columnNames).$btk;

      $table .= ' AS MainTable';
      if ($projectId > 0) { // Add the project fee
        $fields .= ',`Unkostenbeitrag`,`mandateReference`';
        // join table with the SEPA mandate reference table
        $memberTableId = Config::getValue('memberTableId');
        $joinCond =
          '('.
          'projectId = '.$projectId.
          ' OR '.
          'projectId = '.$memberTableId.
          ')'.
          ' AND musicianId = MusikerId';

        $table .= " LEFT JOIN `SepaDebitMandates` ON "
          ."( ".$joinCond." ) ";
      }

      $query = "SELECT $fields FROM ($table) WHERE
        ";
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
      //$_POST['QUERY'] = $query;

      // use the mailer-class we are using later anyway in order to
      // validate email addresses syntactically now. Add broken
      // addresses to the "brokenEMail" list.
      $mailer = new \PHPMailer(true);

      // Fetch the result or die
      $result = mySQL::query($query, $dbh, true); // here we want to bail out on error

      /* Stuff all emails into one array for later usage, remember the Id in
       * order to combine any selection from the new "multi-select"
       * check-boxes.
       */
      while ($line = mysql_fetch_assoc($result)) {
        $name = $line['Vorname'].' '.$line['Name'];
        $rec = $line['OrigId'];
        if ($line['Email'] != '') {
          // We allow comma separated multiple addresses
          $musmail = explode(',',$line['Email']);
          if ($projectId < 0) {
            $line['Unkostenbeitrag'] = '';
            $line['mandateReference'] = '';
          }
          $line['insuranceFee'] = '0,00';
          foreach ($musmail as $emailval) {
            if (!$mailer->validateAddress($emailval)) {
              $bad = htmlspecialchars($name.' <'.$emailval.'>');
              if (isset($this->brokenEMail[$rec])) {
                $this->brokenEMail[$rec] .= ', '.$bad;
              } else {
                $this->brokenEMail[$rec] = $bad;
              }
            } else {
              $this->EMails[$rec] =
                array('email'   => $emailval,
                      'name'    => $name,
                      'status'  => $line['MemberStatus'],
                      'project' => $projectId,
                      'dbdata'  => $line);
              $this->EMailsDpy[$rec] =
                htmlspecialchars($name.' <'.$emailval.'>');
            }
          }
        } else {
          $this->brokenEMail[$rec] = htmlspecialchars($name);
        }
      }
      foreach($this->EMails as $key => $record) {
        $dbdata = $record['dbdata'];
        setlocale(LC_MONETARY, Util::getLocale());
        $fee = money_format('%n', InstrumentInsurance::annualFee($dbdata['musicianId'], $dbh));
        $dbdata['insuranceFee'] = $fee;
        $fee = money_format('%n', intval($dbdata['Unkostenbeitrag']));
        $dbdata['Unkostenbeitrag'] = $fee;
        $this->EMails[$key]['dbdata'] = $dbdata;
      }
    }

    /* Fetch the list of musicians for the given context (project/global)
     */
    private function getMusiciansFromDB($dbh)
    {
      $this->brokenEMail = array();
      $this->EMails = array();
      $this->EMailsDpy = array(); // display records

      if ($this->projectId < 0) {        
        self::fetchMusicians($dbh, 'Musiker', 'Id', 'Instrumente', -1, true);
      } else {
        // Possibly add musicians from the project
        if ($this->userBase['FromProject']) {
          self::fetchMusicians($dbh,
                               $this->projectName.'View', 'MusikerId', 'Instrument',
                               $this->projectId, true);
        }

        // and/or not from the project
        if ($this->userBase['ExceptProject']) {
          $table =
            '(SELECT a.* FROM Musiker as a
    LEFT JOIN `'.$this->projectName.'View'.'` as b
      ON a.Id = b.MusikerId 
      WHERE b.MusikerId IS NULL)';
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
      $this->instrumentGroups = Instruments::fetchGrouped($dbh);

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
      if ($this->frozen) {
        if (!$this->memberStatusNames) {
          $this->memberStatusNames = array();
        }
        return array_keys($this->memberStatusNames);
      }
      $byStatusDefault = array('regular');
      if ($this->projectId > 0) {
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
      $this->memberFilter = $this->cgiValue('MemberStatusFilter',
                                            $this->defaultByStatus());
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
                   'FrozenRecipients' => $this->frozen,
                   'FormStatus' => 'submitted');
    }

    /**Return the current filter history and the filter position as
     * JSON encoded string.
     */
    public function filterHistory()
    {
      return array('historyPosition' => $this->historyPosition,
                   'historySize' => count($this->filterHistory));
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
        if ($instrument == '*') {
          $value = '';
          $group = '';
        } else {
          $value = $instrument;
          $group = $this->instrumentGroups[$value];
        }
        
        $result[] = array('value' => $value,
                          'name' => $name,
                          'group' => $group,
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
        if ($this->frozen && array_search($key, $this->EmailRecs) === false) {
          continue;
        }
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
      foreach ($this->brokenEMail as $key => $problem) {
        if ($this->frozen && array_search($key, $this->EmailRecs) === false) {
          continue;
        }
        $result[$key] = $problem;
      }
      asort($result);

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

    /**Return true when doing a mere history snapshot. */
    public function snapshotState() {
      return $this->snapshot;
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
      //$_POST['blah'] = print_r($this->EMails, true);
      $EMails = array();
      foreach ($selectedRecipients as $key) {
        if (isset($this->EMails[$key])) {
          $EMails[] = $this->EMails[$key];
        }
      }
      return $EMails;
    }

    /**Return true if the list of recipients is frozen,
     * i.e. restricted to the pre-selected recipients.
     */
    public function frozenRecipients()
    {
      return $this->frozen;
    }
    
  };

} // CAFEVDB

?>
