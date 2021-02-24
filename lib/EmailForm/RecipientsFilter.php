<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\EmailForm;

use OCP\ISession;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * Wrap the email filter form into a class to make things a little
 * less crowded. This is actually not to filter emails, but rather to
 * select specific groups of musicians (depending on instrument and
 * project).
 */
class EmailRecipientsFilter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const POST_TAG = 'emailRecipients';
  private const MAX_HISTORY_SIZE = 100; // the history is posted around, so ...
  private const SESSION_HISTORY_KEY = 'FilterHistory';
  private const HISTORY_KEYS = [
    'BasicRecipientsSet',
    'MemberStatusFilter',
    'InstrumentsFilter',
    'SelectedRecipients'
  ];

  /** @var null|Entities\Project */
  private $project;
  private $projectId;   // Project id or NULL or -1 or ''
  private $projectName; // Project name of NULL or ''
  private $instrumentsFilter; // Current instrument filter
  private $userBase;    // Select from either project members and/or
  // all musicians w/o project-members
  private $memberFilter;// passive, regular, soloist, conductor, temporary
  private $EmailRecs;   // Copy of email records from CGI env
  private $emailKey;    // Key for EmailsRecs into _POST or _GET

  /** @var Entities\SepaDebitNote */
  private $debitNote;
  private $debitNoteId;

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
  private $filterHistory;
  private $historyPosition;
  private $historySize;

  /** @var ISession */
  private $session;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var PHPMyEdit */
  protected $pme;

  /*
   * constructor
   */
  public function __construct(
    ConfigService $configService
    , ISession $session
    , RequestParameterService $parameterService
    , EntityManager $entityManager
    , PHPMyEdit $pme
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->session = $session;
    $this->parameterService = $parameterService;
    $this->entityManager = $entityManager;
    $this->pme = $pme;

    $this->jsonFlags = JSON_FORCE_OBJECT|JSON_HEX_QUOT|JSON_HEX_APOS;

    // Fetch all data submitted by form
    $this->cgiData = $this->parameterService->getPrefixParams(self::POST_TAG);

    // Quirk: the usual checkbox issue
    $this->cgiData['BasicRecipientsSet']['FromProject'] =
      isset($this->cgiData['BasicRecipientsSet']['FromProject']);
    $this->cgiData['BasicRecipientsSet']['ExceptProject'] =
      isset($this->cgiData['BasicRecipientsSet']['ExceptProject']);

    $this->projectId = $this->parameterService->getParam('projectId', -1);
    $this->projectName = $this->parameterService->getParam('projectName', '');
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)
                            ->find($this->projectId);
    }

    $this->debitNoteId = $this->parameterService->getParam('DebitNoteId', -1);
    if ($this->debitNoteId > 0) {
      $this->debitNote = $this->getDatabaseRepository(Entities\SepaDebitNote::class)
                              ->find($this->debitNoteId);
    }

    // See wether we were passed specific variables ...
    $this->emailKey  = $this->pme->cgiSysName('mrecs');
    $this->mtabKey   = $this->pme->cgiSysName('mtable');
    $this->EmailRecs = []; // avoid null

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
    $this->submitted = $this->cgiValue('FormStatus', '') === 'submitted';

    // "sane" default setttings
    $this->EmailRecs = $this->parameterService->getParam($this->emailKey, []);
    $this->reload = false;
    $this->snapshot = false;

    if ($this->submitted) {
      $this->loadHistory(); // Fetch the filter-history from the session, if any.
      $this->EmailRecs = $this->cgiValue($this->emailKey, []);
      if ($this->cgiValue('ResetInstrumentsFilter', false) !== false) {
        $this->submitted = false; // fall back to defaults for everything
        $this->cgiData = [];
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

    $this->remapEmailRecords($dbh);
    $this->getMemberStatusNames();
    $this->initMemberStatusFilter();
    $this->getUserBase();
    $this->getInstrumentsFromDB();
    $this->fetchInstrumentsFilter();
    $this->getMusiciansFromDB($dbh);

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

  /** Fetch a CGI-variable out of the form-select name-space. */
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

  /** Compose a default history record for the initial state */
  private function setDefaultHistory()
  {
    $this->historyPosition = 0;
    $this->historySize = 1;

    $filter = [
      'BasicRecipientsSet' => $this->defaultUserBase(),
      'MemberStatusFilter' => $this->defaultByStatus(),
      'InstrumentsFilter' => [],
      'SelectedRecipients' => array_intersect($this->EmailRecs,
                                              array_keys($this->EMailsDpy))
    ];

    // tweak: sort the selected recipients by key
    sort($filter['SelectedRecipients']);

    $md5 = md5(serialize($filter));
    $data = $filter;
    $this->filterHistory = [ [ 'md5' => $md5, 'data' => $data ], ];
  }

  /**Store the history to somewhere, probably the session-data. */
  private function storeHistory()
  {
    $storageValue = [
      'size' => $this->historySize,
      'position' => $this->historyPosition,
      'records' => $this->filterHistory,
    ];
    //throw new \Exception(print_r($storageValue, true));
    $this->sessionStoreValue(self::SESSION_HISTORY_KEY, $storageValue);
  }

  /**Load the history from the session data. */
  private function loadHistory()
  {
    $loadHistory = $this->sessionRetrieveValue(self::SESSION_HISTORY_KEY);
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
    foreach ($history['records'] as $record) {
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
    $filter = [];
    foreach (self::HISTORY_KEYS as $key) {
      $filter[$key] = $this->cgiValue($key, []);
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
      array_unshift($this->filterHistory, [ 'md5' => $md5, 'data' => $filter ]);
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
          $this->l->t('Invalid history position %d request, history size is %d',
                      [ $newPosition, $this->historySize ]));
      }
      return;
    }

    // Move to the new history position.
    $this->historyPosition = $newPosition;

    $filter = $this->filterHistory[$newPosition]['data'];
    foreach (self::HISTORY_KEYS as $key) {
      $this->cgiData[$key] = $filter[$key];
    }
  }

  /**
   * This function is called at the very start. For special purpose
   * emails this function forms the list of recipients.
   */
  private function remapEmailRecords($dbh)
  {
    if ($this->debitNoteId > 0) {
      $debitNoteId = $this->debitNoteId;
      $debitNote = DebitNotes::debitNote($debitNoteId, $dbh);
      if ($debitNote === false) {
        throw new \RuntimeException($this->l->t('Unable to fetch debit note for id %d.',
                                                $debitNoteId));
      }
      if ($this->projectId > 0 && $debitNote['ProjectId'] !== $this->projectId) {
        throw new \Exception(print_r($debitNote, true).' '.$this->projectId);
        throw new \InvalidArgumentException($this->l->t('Debit note does not belong to the given project (%d <-> %d)',
                                                        [ $this->projectId, $debitNote['ProjectId'] ]));
      }
      $this->projectId = $debitNote['ProjectId'];
      if (empty($this->projectName)) {
        $this->projectName = Projects::fetchName($this->projectId, $dbh);
      }

      $payments = ProjectPayments::debitNotePayments($debitNoteId, $dbh);
      if ($payments === false) {
        throw new \RuntimeException(
          $this->l->t('Unable to fetch payments for debit-note id %d.', $debitNoteId));
      }
      if (empty($payments)) {
        throw new \RuntimeException(
          $this->l->t('No payments for debit-note id %d.', [ $debitNoteId ]));
      }
      $this->EmailRecs = [];
      foreach($payments as $payment) {
        $this->EmailRecs[] = $payment['InstrumentationId'];
      }

      $this->frozen = true; // restrict to initial set of recipients

      return;
    }
  }

  /**
   * Fetch musicians from either the "Musicians" table or a project
   * view. Depending on the table in use, $restrict is either
   * 'Instrumente' or 'Instrument' (normally). Also, fetch all data
   * needed to do any per-recipient substitution later.
   *
   * @param $dbh Data-base handle.
   *
   * @param $table The table to use, either 'Musiker' or a project view.
   *
   * @param $id The name of the column holding the musicians
   *                global id, this is either 'Id' (Musiker-table) or
   *                'MusikerId' (project view).
   *
   * @param $restrict The filter restriction, either 'Instrument'
   *                (German singular) or 'Instrumente' (German
   *                plural).
   *
   * @param $projectId Either a valid project-id, or -1 if not in
   *                "project-mode".
   *
   * @return Associative array with the keys
   * - name (full name)
   * - email
   * - status (MemberStatus)
   * - dbdata (data as returned from the DB for variable substitution)
   */
  private function fetchMusicians($table, $id, $projectId)
  {
    $columnNames = [
      'Vorname',
      'Name',
      'Email',
      'MobilePhone',
      'FixedLinePhone',
      'Strasse',
      'Postleitzahl',
      'Stadt',
      'Land',
      'Geburtstag',
      'MemberStatus',
    ];
    $comma = ',';
    $sep = $comma;
    $dot = '.';
    $origId = 'MainTable'.$dot.'Id'.' AS '.'OrigId';
    $realId = 'MainTable'.$dot.$id.' AS '.'musicianId';
    $fields =
            $origId.$comma.
            $realId.$comma.
            implode($sep, $columnNames);

    $table .= ' MainTable';

    $instrument = $projectId > 0 ? 'ProjectInstrument' : 'Instruments';
    $instrumentFilter = [];
    foreach ($this->instrumentsFilter as $value) {
      $instrumentFilter[] = $instrument." LIKE '%".$value."%'";
    }
    $instrumentFilter = implode("\n  OR\n", $instrumentFilter);
    $where = '1 ';

    if ($projectId > 0) { // Add the project fee

      if (!empty($instrumentFilter)) {
        $where .= "
  AND (".$instrumentFilter.")";
      }
      $having = '';

      // Add the relevant payment information (except the global
      // information attached to the debit note)
      if ($this->debitNoteId > 0) {
        $fields .=
                ',p.`Id` AS `PaymentId`'.
                ',p.`Amount` AS `DebitNoteAmount`'.
                ',p.`Subject` AS `DebitNotePurpose`'.
                ',p.`MandateReference` AS `DebitNoteMandateReference`';
        $joinCond =
                  'p.InstrumentationId = MainTable.Id'.
                  ' AND '.
                  'p.DebitNoteId = '.$this->debitNoteId;

        $table .= " LEFT JOIN `ProjectPayments` p ON "
               ."( ".$joinCond." )";
      }

      $fields .= ''
              .',`Unkostenbeitrag`'
              .',`Anzahlung`'
              .',`AmountPaid`'
              .',`PaidCurrentYear`'
              .',m.`mandateReference` AS `ProjectMandateReference`'
              .',m.`IBAN` AS `MandateIBAN`'
              .',m.`BIC` AS `MandateBIC`'
              .',m.`bankAccountOwner` AS `MandateAccountOwner`';
      // join table with the SEPA mandate reference table
      $memberTableId = Config::getValue('memberTableId');
      $joinCond =
                '('.
                'm.projectId = '.$projectId.
                ' OR '.
                'm.projectId = '.$memberTableId.
                ')'.
                ' AND m.musicianId = MusikerId'.
                ' AND m.active = 1';

      // if debit-note payment is given use its payment information.
      if ($this->debitNoteId > 0) {
        $joinCond .= ' AND m.mandateReference = p.MandateReference';
      }

      $table .= " LEFT JOIN `SepaDebitMandates` m ON "
             ."( ".$joinCond." ) ";

      // Add also any extra charges
      $monetary = ProjectExtra::monetaryFields($projectId, $dbh);

      foreach(array_keys($monetary) AS $extraLabel) {
        $fields .= ', `'.$extraLabel.'`';
      }
    } else { // $projectId > 0
      $fields .= ',
  GROUP_CONCAT(i.Instrument) AS Instruments';
      $table .= "
  LEFT JOIN `MusicianInstruments` mi
    ON mi.MusicianId = MainTable.Id
  LEFT JOIN `Instrumente` i
    ON i.Id = mi.InstrumentId
";
      $having = empty($instrumentFilter) ? '' : 'HAVING '.$instrumentFilter;
    }

    $query = "SELECT $fields
FROM  $table
WHERE";
    $query .= "
  $where";

    if ($this->frozen && $projectId > 0) {
      $query .= "
  AND MainTable.Id IN (".implode(',', $this->EmailRecs).") ";
    }

    /* Don't bother any conductor etc. with mass-email. */
    $query .= "
  ".$this->memberStatusSQLFilter();

    $query .= "
  AND MainTable.Disabled = 0";

    if (false) {
      echo '<PRE>';
      echo $query;
      echo '</PRE>';
    }
    //$_POST['QUERY'] = $query;

    $query .= "
  GROUP BY MainTable.Id";
    $query .= "
  $having";

    // use the mailer-class we are using later anyway in order to
    // validate email addresses syntactically now. Add broken
    // addresses to the "brokenEMail" list.
    $mailer = new \PHPMailer(true);

    // Fetch the result or die
    $result = mySQL::query($query, $dbh, true); // here we want to bail out on error

    /* Stuff all emails into one array for later usage, remember the
     * Id in order to combine any selection from the new
     * "multi-select" check-boxes. Data is only needed for persons
     * with emails
     */
    while ($line = mySQL::fetch($result)) {
      $name = $line['Vorname'].' '.$line['Name'];
      $rec = $line['OrigId'];
      if ($line['Email'] != '') {
        // We allow comma separated multiple addresses
        $musmail = explode(',',$line['Email']);
        if ($this->debitNoteId <= 0) {
          $line['PaymentId'] = '';
          $line['DebitNoteAmount'] = '';
          $line['DebitNotePurpose'] = '';
        }
        if ($projectId <= 0) {
          $line['Unkostenbeitrag'] = '';
          $line['Anzahlung'] = '';
          $line['SurchargeFees'] = '';
          $line['Extras'] = '';
          $line['TotalFees'] = '';
          $line['MandateReference'] = '';
          $line['AmountPaid'] = '';
          $line['AmountMissing'] = '';
        } else {
          $line['MandateReference'] = $line['ProjectMandateReference'];
          unset($line['ProjectMandateReference']);
          if ($this->debitNoteId > 0 &&
              $line['MandateReference'] !== $line['DebitNoteMandateReference']) {
            throw new \RuntimeException($this->l->t('Inconsistent debit-note mandates: "%s" vs. "%s"',
                                             array($line['MandateReference'],
                                                   $line['DebitNoteMandateReference'])));
          }
          $line['Extras'] = [];
          setlocale(LC_MONETARY, Util::getLocale());
          $extra = 0.0;
          foreach($monetary as $label => $fieldInfo) {
            $value = $line[$label];
            unset($line[$label]);
            if (empty($value)) {
              continue;
            }
            $allowed   = $fieldInfo['AllowedValues'];
            $type      = $fieldInfo['Type']['Multiplicity'];
            $surcharge = DetailedInstrumentation::extraFieldSurcharge($value, $allowed, $type);
            $extra    += $surcharge;
            $surcharge = money_format('%n', floatval($surcharge));
            $line['Extras'][] = array('label' => $label, 'surcharge' => $surcharge);
          }
          $line['SurchargeFees'] = $extra;
          $line['TotalFees'] = $extra + $line['Unkostenbeitrag'];
          if ($this->debitNoteId > 0) {
            $line['AmountPaid'] -=  $line['DebitNoteAmount']; // compensate for current payment
          }
          $line['AmountMissing'] = $line['TotalFees'] - $line['AmountPaid'];
        }
        $line['InsuranceFee'] = InstrumentInsurance::annualFee($line['musicianId'], $dbh);
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

    $moneyKeys = [
      'InsuranceFee',
      'SurchargeFees',
      'TotalFees',
      'Anzahlung',
      'Unkostenbeitrag',
      'AmountPaid',
      'AmountMissing',
      'DebitNoteAmount'
    ];

    // do this later when constructing the message
    foreach($this->EMails as $key => $record) {
      $dbdata = $record['dbdata'];
      setlocale(LC_MONETARY, Util::getLocale());
      foreach($moneyKeys as $moneyKey) {
        $fee = money_format('%n', floatval($dbdata[$moneyKey]));
        $dbdata[$moneyKey] = $fee;
      }
      $this->EMails[$key]['dbdata'] = $dbdata;
    }
  }

  /* Fetch the list of musicians for the given context (project/global)
   */
  private function getMusiciansFromDB($dbh)
  {
    $this->brokenEMail = [];
    $this->EMails = [];
    $this->EMailsDpy = []; // display records

    if ($this->projectId <= 0) {
      self::fetchMusicians($dbh, 'Musiker', 'Id', -1);
    } else {
      // Possibly add musicians from the project
      if ($this->userBase['FromProject']) {
        self::fetchMusicians($dbh,
                             $this->projectName.'View', 'MusikerId', $this->projectId);
      }

      // and/or not from the project
      if ($this->userBase['ExceptProject']) {
        $table =
               '(SELECT a.* FROM Musiker a
    LEFT JOIN `'.$this->projectName.'View'.'` b
      ON a.Id = b.MusikerId
      WHERE b.MusikerId IS NULL)';
        self::fetchMusicians($dbh, $table, 'Id', -1);
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
  private function getInstrumentsFromDb()
  {
    // Get the current list of instruments for the filter
    $instrumentInfo =
      $this->getDatabaseRepository(Entities\Instrument::class)->describeALL();
    if ($this->projectId > 0 && !$this->userBase['ExceptProject']) {
      $this->instruments = [];
      // @todo Perhaps write a special repository-method
      foreach ($this->project['participantInstruments'] as $projectInstrument)  {
        $instrument = $projectInstrument['instrument'];
        $this->instruments[$instrument['id']] = $instrument['name'];
      }
    } else {
      $this->instruments = $instrumentInfo['byId'];
    }
    $this->instrumentGroups = $instrumentInfo['nameGroups'];

    array_unshift($this->instruments, '*');
  }

  private function fetchInstrumentsFilter()
  {
    /* Remove instruments from the filter which are not known by the
     * current list of musicians.
     */
    $filterInstruments = $this->cgiValue('InstrumentsFilter', []);
    array_intersect($filterInstruments, $this->instruments);

    $this->instrumentsFilter = [];
    foreach ($filterInstruments as $value) {
      $this->instrumentsFilter[] = $value;
    }
  }

  private function defaultByStatus()
  {
    if ($this->frozen) {
      if (!$this->memberStatusNames) {
        $this->memberStatusNames = [];
      }
      return array_keys($this->memberStatusNames);
    }
    $byStatusDefault = [ 'regular' ];
    if ($this->projectId > 0) {
      $byStatusDefault[] = 'passive';
      $byStatusDefault[] = 'temporary';
    }
    return $byStatusDefault;
  }

  private function getMemberStatusNames()
  {
    $memberStatus = DBTypes\EnumMemberStatus::toArray();
    foreach ($memberStatus as $key => $tag) {
      if (!isset($this->memberStatusNames[$tag])) {
        $this->memberStatusNames[$tag] = $this->l->t('member status '.$tag);
      }
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
    return [
      'FromProject' => $this->projectId >= 0,
      'ExceptProject' => false,
    ];
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
    return [
      $this->emailKey => $this->EmailRecs,
      'FrozenRecipients' => $this->frozen,
      'FormStatus' => 'submitted',
    ];
  }

  /**Return the current filter history and the filter position as
   * JSON encoded string.
   */
  public function filterHistory()
  {
    return [
      'historyPosition' => $this->historyPosition,
      'historySize' => count($this->filterHistory),
    ];
  }

  /**Return the current value of the member status filter or its
   * initial value.
   */
  public function memberStatusFilter()
  {
    $memberStatus = $this->cgiValue('MemberStatusFilter',
                                    $this->submitted ? '' : $this->defaultByStatus());
    $memberStatus = array_flip($memberStatus);
    $result = [];
    foreach($this->memberStatusNames as $tag => $name) {
      $result[] =  [
        'value' => $tag,
        'name' => $name,
        'flags' => isset($memberStatus[$tag]) ? Navigation::SELECTED : 0,
      ];
    }
    return $result;
  }

  /**Return the user basic set for the email form template
   */
  public function basicRecipientsSet()
  {
    return [
      'FromProject' => $this->userBase['FromProject'] ? 1 : 0,
      'ExceptProject' => $this->userBase['ExceptProject'] ? 1 : 0,
      ];
  }

  /**Return the values for the instruments filter.
   *
   * TODO: group by instrument kind (strings, wind etc.)
   */
  public function instrumentsFilter()
  {
    $filterInstruments = $this->cgiValue('InstrumentsFilter', [ '*' ]);
    $filterInstruments = array_flip(array_intersect($filterInstruments, $this->instruments));
    $result = [];
    foreach($this->instruments as $instrument) {
      $name = $instrument;
      if ($instrument == '*') {
        $value = '';
        $group = '';
      } else {
        $value = $instrument;
        $group = $this->instrumentGroups[$value];
      }

      $result[] = [
        'value' => $value,
        'name' => $name,
        'group' => $group,
        'flags' => isset($filterInstruments[$instrument]) ? Navigation::SELECTED : 0,
      ];
    }
    return $result;
  }

  /**Return the values for the recipient select box */
  public function emailRecipientsChoices()
  {
    if ($this->submitted) {
      $selectedRecipients = $this->cgiValue('SelectedRecipients', []);
    } else {
      $selectedRecipients = $this->EmailRecs;
    }
    $selectedRecipients = array_flip($selectedRecipients);

    $result = [];
    foreach($this->EMailsDpy as $key => $email) {
      if ($this->frozen && array_search($key, $this->EmailRecs) === false) {
        continue;
      }
      $result[] = [
        'value' => $key,
        'name' => $email,
        'flags' => isset($selectedRecipients[$key]) ? Navigation::SELECTED : 0,
      ];
    }

    return $result;
  }

  /**Return a list of musicians without email address, if any. */
  public function missingEmailAddresses()
  {
    $result = [];
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
      $selectedRecipients = $this->cgiValue('SelectedRecipients', []);
    } else {
      $selectedRecipients = $this->EmailRecs;
    }
    $selectedRecipients = array_unique($selectedRecipients);
    //$_POST['blah'] = print_r($this->EMails, true);
    $EMails = [];
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
}
