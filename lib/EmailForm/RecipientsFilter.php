<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types as DBTypes;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Common\PHPMailer;
use OCA\CAFEVDB\Common\Util;

/**
 * Wrap the email filter form into a class to make things a little
 * less crowded. This is actually not to filter emails, but rather to
 * select specific groups of musicians (depending on instrument and
 * project).
 */
class RecipientsFilter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const POST_TAG = 'emailRecipients';

  public const MEMBER_STATUS_OPEN = 'open';

  public const BASIC_RECIPIENTS_SET_KEY = 'basicRecipientsSet';
  public const FROM_PROJECT_CONFIRMED_KEY = 'fromProjectConfirmed';
  public const FROM_PROJECT_PRELIMINARY_KEY = 'fromProjectPreliminary';
  public const EXCEPT_PROJECT_KEY = 'exceptProject';
  public const ANNOUNCEMENTS_MAILING_LIST_KEY = ConfigService::ANNOUNCEMENTS_MAILING_LIST_FQDN_NAME;
  public const PROJECT_MAILING_LIST_KEY = 'projectMailingList';

  private const MUSICIANS_FROM_PROJECT_PRELIMINARY = (1 << 0);
  private const MUSICIANS_FROM_PROJECT_CONFIRMED = (1 << 1);
  private const MUSICIANS_FROM_PROJECT = self::MUSICIANS_FROM_PROJECT_CONFIRMED|self::MUSICIANS_FROM_PROJECT_PRELIMINARY;
  private const MUSICIANS_EXCEPT_PROJECT = (1 << 2);
  private const ANNOUNCEMENTS_MAILING_LIST = (1 << 3);
  private const PROJECT_MAILING_LIST = (1 << 4);
  private const MAILING_LIST = self::ANNOUNCEMENTS_MAILING_LIST|self::PROJECT_MAILING_LIST;
  private const NO_MUSICIANS = 0;
  private const ALL_MUSICIANS = self::MUSICIANS_FROM_PROJECT | self::MUSICIANS_EXCEPT_PROJECT;
  private const DATABASE_MUSICIANS = self::ALL_MUSICIANS;

  /** @var array recipient set keys by flag value */
  public const BASIC_RECIPIENTS_SET_KEYS = [
    self::MUSICIANS_FROM_PROJECT_PRELIMINARY => self::FROM_PROJECT_PRELIMINARY_KEY,
    self::MUSICIANS_FROM_PROJECT_CONFIRMED => self::FROM_PROJECT_CONFIRMED_KEY,
    self::MUSICIANS_EXCEPT_PROJECT => self::EXCEPT_PROJECT_KEY,
    self::ANNOUNCEMENTS_MAILING_LIST => self::ANNOUNCEMENTS_MAILING_LIST_KEY,
    self::PROJECT_MAILING_LIST => self::PROJECT_MAILING_LIST_KEY,
  ];

  /** @var array recipient set flag values by key */
  public const BASIC_RECIPIENT_SET_FLAGS = [
    self::FROM_PROJECT_PRELIMINARY_KEY => self::MUSICIANS_FROM_PROJECT_PRELIMINARY,
    self::FROM_PROJECT_CONFIRMED_KEY => self::MUSICIANS_FROM_PROJECT_CONFIRMED,
    self::EXCEPT_PROJECT_KEY => self::MUSICIANS_EXCEPT_PROJECT,
    self::ANNOUNCEMENTS_MAILING_LIST_KEY => self::ANNOUNCEMENTS_MAILING_LIST,
    self::PROJECT_MAILING_LIST_KEY => self::PROJECT_MAILING_LIST,
  ];

  private const MAX_HISTORY_SIZE = 100; // the history is posted around, so ...
  private const SESSION_HISTORY_KEY = 'filterHistory';
  private const HISTORY_KEYS = [
    self::BASIC_RECIPIENTS_SET_KEY,
    'memberStatusFilter',
    'instrumentsFilter',
    'selectedRecipients'
  ];

  // MUSICIAN_KEY[PME_sys_mtable]
  private const MUSICIAN_KEY = [
    'Musicians' => 'id',
    'ProjectParticipants' => 'musician_id',
    'InstrumentInsurances' => 'bill_to_party_id',
  ];

  /**
   * @var int
   * project-id, no project if <= 0
   */
  private $projectId;

  /**
   * @var Entities\Project
   * project corresponding to $projectId or null.
   */
  private $project;

  private $instrumentsFilter; // Current instrument filter
  private $userBase;    // Select from either project members and/or
  // all musicians w/o project-members
  private $memberFilter;// passive, regular, soloist, conductor, temporary
  private $emailRecs;   // Copy of email records from CGI env
  private $emailKey;    // Key for EmailsRecs into _POST or _GET

  /**
   * @var array
   *
   * The mailing-list information as returned from the lists-server, cached
   * for the running request.
   */
  private array $mailingListInfo = [];

  /** @var Entities\SepaBulkTransaction */
  private $bulkTransaction;

  /** @var int */
  private $bulkTransactionId;

  private $instruments; // List of instruments for filtering
  private $instrumentGroups; // mapping of instruments to groups.

  private $opts;        // Copy of global options

  private $brokenEMail;     // List of people without email
  private $eMails;      // List of people with email
  private $eMailsDpy;   // Display list with namee an email
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

  /** @var Repositories\MusiciansRepository */
  protected $musiciansRepository;

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
    $this->entityManager = $entityManager;
    $this->pme = $pme;

    $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    $this->jsonFlags = JSON_FORCE_OBJECT|JSON_HEX_QUOT|JSON_HEX_APOS;

    $this->bind($parameterService);
  }

  /**
   * @param RequestParameterService $parameterService Bind self to the
   * given request parameters.
   */
  public function bind(RequestParameterService $parameterService)
  {
    $this->parameterService = $parameterService;

    // Fetch all data submitted by form
    $this->cgiData = $this->parameterService->getParam(self::POST_TAG, []);

    // The web-form submits the basic recipient set as array
    $values = array_flip($this->cgiData[self::BASIC_RECIPIENTS_SET_KEY] ?? []);

    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY][self::FROM_PROJECT_CONFIRMED_KEY] =
      isset($values[self::FROM_PROJECT_CONFIRMED_KEY]);
    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY][self::FROM_PROJECT_PRELIMINARY_KEY] =
      isset($values[self::FROM_PROJECT_PRELIMINARY_KEY]);
    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY][self::EXCEPT_PROJECT_KEY] =
      isset($values[self::EXCEPT_PROJECT_KEY]);
    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY][self::ANNOUNCEMENTS_MAILING_LIST_KEY] =
      isset($values[self::ANNOUNCEMENTS_MAILING_LIST_KEY]);
    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY][self::PROJECT_MAILING_LIST_KEY] =
      isset($values[self::PROJECT_MAILING_LIST_KEY]);

    $this->projectId = $this->parameterService->getParam('projectId', -1);
    $this->projectName = $this->parameterService->getParam('projectName', '');
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)
        ->find($this->projectId);

    }

    $this->bulkTransactionId = $this->parameterService->getParam('bulkTransactionId', 0);
    if ($this->bulkTransactionId > 0) {
      $this->bulkTransaction = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class)
                                    ->find($this->bulkTransactionId);
    }

    // See wether we were passed specific variables ...
    $this->emailKey  = $this->pme->cgiSysName('mrecs');
    $this->mkeyKey   = $this->pme->cgiSysName('mkey');
    $this->mtabKey   = $this->pme->cgiSysName('mtable');
    $this->emailRecs = []; // avoid null

    $this->frozen = $this->cgiValue('frozenRecipients', false);

    $this->execute();
  }

  /**
   * Parse the CGI stuff and compute the list of selected musicians,
   * either for the initial form setup as during the interaction
   * with the user.
   */
  private function execute()
  {
    // Maybe should also check something else. If submitted is true,
    // then we use the form data, otherwise the defaults.
    $this->submitted = $this->cgiValue('formStatus', '') === 'submitted';

    // "sane" default setttings
    $this->emailRecs = $this->parameterService->getParam($this->emailKey, []);
    $this->emailTable = $this->parameterService->getParam($this->mtabKey, '');

    $this->reload = false;
    $this->snapshot = false;

    if ($this->submitted) {
      $this->loadHistory(); // Fetch the filter-history from the session, if any.
      $this->emailRecs = $this->cgiValue($this->emailKey, []);
      $this->emailTable = $this->cgiValue($this->mtabKey);
      if ($this->cgiValue('resetInstrumentsFilter', false) !== false) {
        $this->submitted = false; // fall back to defaults for everything
        $this->cgiData = [];
        $this->reload = true;
      } else if ($this->cgiValue('undoInstrumentsFilter', false) !== false) {
        $this->applyHistory(1); // the current state
        $this->reload = true;
      } else if ($this->cgiValue('redoInstrumentsFilter', false) !== false) {
        $this->applyHistory(-1);
        $this->reload = true;
      } else if ($this->cgiValue('historySnapshot', false) !== false) {
        // fast mode, only CGI data, no DB access. Only the
        // $this->filterHistory() should be queried afterwards,
        // everything else is undefined.
        $this->snapshot = true;
        $this->pushHistory(true);
        $this->storeHistory();
        return;
      }
    }

    $this->remapEmailRecords();
    $this->getMemberStatusNames();
    $this->initMemberStatusFilter();
    $this->getUserBase();
    $this->getInstrumentsFromDB();
    $this->fetchInstrumentsFilter();
    $this->getMusiciansFromDB();

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

    $this->storeHistory();
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
      self::BASIC_RECIPIENTS_SET_KEY => $this->basicRecipientsSet(),
      'memberStatusFilter' => $this->defaultByStatus(),
      'instrumentsFilter' => [],
      'selectedRecipients' => array_intersect($this->emailRecs,
                                              array_keys($this->eMailsDpy??[]))
    ];

    // tweak: sort the selected recipients by key
    sort($filter['selectedRecipients']);

    $md5 = md5(serialize($filter));
    $data = $filter;
    $this->filterHistory = [ [ 'md5' => $md5, 'data' => $data ], ];
  }

  /** Store the history to somewhere, probably the session-data. */
  private function storeHistory()
  {
    $storageValue = [
      'size' => $this->historySize,
      'position' => $this->historyPosition,
      'records' => $this->filterHistory,
    ];
    $this->sessionStoreValue(self::SESSION_HISTORY_KEY, $storageValue);
  }

  /** Load the history from the session data. */
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
    // $this->logInfo('HISTORY '.print_r($loadHistory, true));
    return true;
  }

  /** Validate the given history records, return false on error. */
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

  /** Validate all history records. */
  private function validateHistoryRecords($history) {
    foreach ($history['records'] as $record) {
      if (!$this->validateHistoryRecord($record)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Push the current filter selection onto the undo-history
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
      $filter['selectedRecipients'] =
        array_intersect($filter['selectedRecipients'],
                        array_keys($this->eMailsDpy));

      // tweak: sort the selected recipients by key
    }

    sort($filter['selectedRecipients']);
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

  /**
   * Relative move inside the history. The function will throw an
   * exception if emailform-debuggin is enabled and the requested
   * action would leave the history stack.
   */
  private function applyHistory($offset)
  {
    $newPosition = $this->historyPosition + $offset;

    // Check for valid position.
    if ($newPosition >= $this->historySize || $newPosition < 0) {
      if ($this->shouldDebug(ConfigService::DEBUG_EMAILFORM)) {
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
  private function remapEmailRecords()
  {
    if (!empty($this->bulkTransaction)) {

      $payments = $this->bulkTransaction->getPayments();
      if (empty($payments)) {
        throw new \RuntimeException(
          $this->l->t('No payments for bulk-transaction id %d.', [ $bulkTransactionId ]));
      }
      $this->emailRecs = [];
      foreach($payments as $payment) {
        $this->emailRecs[] = $payment->getMusician()->getId();
      }

      $this->frozen = true; // restrict to initial set of recipients

      return;
    } else if (!empty($this->emailTable)) {
      $musicianKey = self::MUSICIAN_KEY[$this->emailTable];
      $this->emailRecs = array_filter(
        array_map(
          function($keyRecord) use ($musicianKey) {
            // @todo Quite inefficient
            $musicianId = filter_var($keyRecord, FILTER_VALIDATE_INT);
            if ($musicianId !== false) {
              return $musicianId;
            }
            return json_decode($keyRecord, true)[$musicianKey];
          },
          $this->emailRecs));
    }
  }

  /**
   * @param array $criteria Filter criteria for the list of recipients.
   *
   * @return Associative array with the keys
   * - name (full name)
   * - email
   * - status (MemberStatus)
   * - dbdata (data as returned from the DB for variable substitution)
   */
  private function fetchMusicians(array $criteria)
  {
    // add the instruments filter
    if (!empty($this->instrumentsFilter)) {
      $criteria[] = [ 'instruments.instrument' => $this->instrumentsFilter ];
      if ($this->projectId > 0 && ($this->userBase & self::MUSICIANS_EXCEPT_PROJECT) == 0) {
        $criteria[] = [ 'projectInstruments.instrument' => $this->instrumentsFilter ];
        $criteria[] = [ 'projectInstruments.project' => $this->projectId ];
      }
    }
    if ($this->frozen && $this->projectId > 0) {
      $criteria[] = [ 'id' => $this->emailRecs ];
    }
    $criteria[] = [ '!memberStatus' => $this->memberStatusBlackList() ];

    $musicians = $this->musiciansRepository->findBy($criteria, [ 'id' => 'INDEX' ]);

    // use the mailer-class we are using later anyway in order to
    // validate email addresses syntactically now. Add broken
    // addresses to the "brokenEMail" list.
    $mailer = new PHPMailer(true);

    /** @var Entities\Musician $musician */
    foreach ($musicians as $rec => $musician) {

      $displayName = $musician->getPublicName(true);
      if (!empty($musician->getEmail())) {
        // We allow comma separated multiple addresses
        $musMail = explode(',', $musician->getEmail());
        foreach ($musMail as $emailVal) {
          if (!$mailer->validateAddress($emailVal)) {
            $bad = htmlspecialchars($displayName.' <'.$emailVal.'>');
            if (isset($this->brokenEMail[$rec])) {
              $this->brokenEMail[$rec]['label'] .= ', '.$bad;
            } else {
              $this->brokenEMail[$rec] = [
                'participant' => $this->projectId > 0 && $musician->isMemberOf($this->projectId),
                'label' => $bad,
              ];

            }
          } else {
            $this->eMails[$rec] = [
              'email'   => $emailVal,
              'name'    => $displayName,
              'status'  => $musician['memberStatus'],
              'project' => $this->projectId ?? 0,
              'participant' => $this->projectId > 0 && $musician->isMemberOf($this->projectId),
              'dbdata'  => $musician,
            ];
            $this->eMailsDpy[$rec] = htmlspecialchars($displayName.' <'.$emailVal.'>');
          }
        }
      } else {
        $this->brokenEMail[$rec] = [
          'participant' => $this->projectId > 0 && $musician->isMemberOf($this->projectId),
          'label' => htmlspecialchars($displayName),
        ];
      }
    }

    // $moneyKeys = [
    //   'InsuranceFee',
    //   'SurchargeFees',
    //   'TotalFees',
    //   'Anzahlung',
    //   'Unkostenbeitrag',
    //   'AmountPaid',
    //   'AmountMissing',
    //   'BulkTransactionAmount'
    // ];

    // // do this later when constructing the message
    // foreach($this->eMails as $key => $record) {
    //   $dbdata = $record['dbdata'];
    //   setlocale(LC_MONETARY, $this->getLocale());
    //   foreach($moneyKeys as $moneyKey) {
    //     $fee = money_format('%n', floatval($dbdata[$moneyKey]));
    //     $dbdata[$moneyKey] = $fee;
    //   }
    //   $this->eMails[$key]['dbdata'] = $dbdata;
    // }
  }

  /* Fetch the list of musicians for the given context (project/global) */
  private function getMusiciansFromDB()
  {
    $this->brokenEMail = [];
    $this->eMails = [];
    $this->eMailsDpy = []; // display records

    $userBase = $this->userBase & self::DATABASE_MUSICIANS;
    $criteria = [];
    if (!empty($this->project) && $userBase != self::ALL_MUSICIANS) {

      // OK, perhaps one should switch to sub-queries ...
      //
      // The problem is here that we need HAVING clauses in order to filter by
      // the project id. WHERE and HAVING are implicitly joined by an AND, so
      // we cannot first restrict to confirmed participation in the current
      // project by WHERE and later allow non-project-musicians by HAVING.

      if (($userBase & self::MUSICIANS_EXCEPT_PROJECT) && ($userBase & self::MUSICIANS_FROM_PROJECT)) {
        $criteria[] = [
          "(|projectParticipation.project@GROUP_CONCAT(IF(IDENTITY(%s) = {$this->projectId}, 1, NULL))" => null
          // "(|projectParticipation.project@GROUP_CONCAT(CASE WHEN IDENTITY(%s) = {$this->projectId} THEN 1 ELSE NULL END))" => null
        ];
        if ($userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) {
          $criteria[] = [
            "projectParticipation.project@GROUP_CONCAT(IF(IDENTITY(%s) = {$this->projectId}, projectParticipation.registration, NULL))" => 1
          ];
        } else {
          $criteria[] = [
            "projectParticipation.project@GROUP_CONCAT(IF(IDENTITY(%s) = {$this->projectId}, NULLIF(projectParticipation.registration, 0), NULL))" => null
          ];
        }
        $criteria[] = [ ')@' => true ];
      } else if ($userBase & self::MUSICIANS_EXCEPT_PROJECT) {
        $criteria[] = [
          "projectParticipation.project@GROUP_CONCAT(IF(IDENTITY(%s) = {$this->projectId}, 1, NULL))" => null
        ];
      } else if ($userBase & self::MUSICIANS_FROM_PROJECT) {
        $criteria[] = [ '(&projectParticipation.project' => $this->projectId ];
        switch ($userBase & self::MUSICIANS_FROM_PROJECT) {
          case self::MUSICIANS_FROM_PROJECT:
            break; // just all participants
          case self::MUSICIANS_FROM_PROJECT_CONFIRMED:
            $criteria[] = [ 'projectParticipation.registration' => 1 ];
            break;
          case self::MUSICIANS_FROM_PROJECT_PRELIMINARY:
            $criteria[] = [ '(|projectParticipation.registration' => 0 ];
            $criteria[] = [ 'projectParticipation.registration' => null ];
            $criteria[] = [ ')' => true ];
            break;
        }
        $criteria[] = [ ')' => true ];
      }
      if (empty($criteria)) {
        return;
      }
    }
    $this->fetchMusicians($criteria);

    // Finally sort the display array
    asort($this->eMailsDpy);
  }

  /**
   * Fetch the list of instruments (either only for project or all)
   *
   * Also: construct the filter by instrument.
   */
  private function getInstrumentsFromDb()
  {
    $instrumentInfo =
      $this->getDatabaseRepository(Entities\Instrument::class)->describeALL();
    // Get the current list of instruments for the filter
    if ($this->projectId > 0 && !($this->userBase & self::MUSICIANS_EXCEPT_PROJECT)) {
      $this->instruments = [];
      // @todo Perhaps write a special repository-method
      foreach ($this->project['participantInstruments'] as $projectInstrument)  {
        $instrument = $projectInstrument['instrument'];
        $this->instruments[$instrument['id']] = $instrument['name'];
      }
    } else {
      $this->instruments = $instrumentInfo['byId'];
    }
    $this->instrumentGroups = $instrumentInfo['idGroups']??[];

    $this->instruments[0] = '*';
  }

  private function fetchInstrumentsFilter()
  {
    /* If in project mode: remove instruments which are not played by the
     * project participants.
     */
    $filterInstruments = array_flip($this->cgiValue('instrumentsFilter', []));
    array_intersect_key($filterInstruments, $this->instruments);

    $this->instrumentsFilter = array_keys($filterInstruments);
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
      $byStatusDefault[] = DBTypes\EnumMemberStatus::PASSIVE;
      $byStatusDefault[] = DBTypes\EnumMemberStatus::TEMPORARY;
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

  /**
   * Get the current filter. Default value, after form submission, initial
   * setting otherwise.
   */
  private function initMemberStatusFilter()
  {
    $this->memberFilter = $this->cgiValue(
      'memberStatusFilter',
      $this->submitted ? [] : $this->defaultByStatus());
  }


  /** Form a SQL filter expression for the member status. */
  private function memberStatusBlackList()
  {
    $allStatusFlags = array_keys($this->memberStatusNames);
    $statusBlackList = array_diff($allStatusFlags, $this->memberFilter);
    return $statusBlackList;
  }

  /**
   * The default user base. Simple, but just keep the scheme in sync
   * with the other two filters and provide a default....()
   * function.
   */
  private function defaultUserBase()
  {
    if ($this->projectId > 0) {
      return self::MUSICIANS_FROM_PROJECT;
    } else {
      if ($this->frozenRecipients()) {
        return 0;
      }
      if (!empty($this->emailRecs)) {
        return 0;
      }
      if (empty($this->getMailingListInfo(self::ANNOUNCEMENTS_MAILING_LIST_KEY))) {
        return 0;
      } else {
        return self::ANNOUNCEMENTS_MAILING_LIST;
      }
    }
  }

  /**
   * Decode the check-boxes which select the set of users we
   * consider basically.
   */
  private function getUserBase()
  {
    if (!$this->submitted) {
      $this->userBase = $this->defaultUserBase();
    } else {
      $userBase = $this->cgiValue(self::BASIC_RECIPIENTS_SET_KEY, false);
      if ($userBase === false) {
        $this->userBase = $this->defaultUserBase();
      } else {
        $this->userBase = self::NO_MUSICIANS;
        if (!empty($userBase[self::FROM_PROJECT_PRELIMINARY_KEY])) {
          $this->userBase |= self::MUSICIANS_FROM_PROJECT_PRELIMINARY;
        }
        if (!empty($userBase[self::FROM_PROJECT_CONFIRMED_KEY])) {
          $this->userBase |= self::MUSICIANS_FROM_PROJECT_CONFIRMED;
        }
        if (!empty($userBase[self::EXCEPT_PROJECT_KEY])) {
          $this->userBase |= self::MUSICIANS_EXCEPT_PROJECT;
        }
        if (!empty($userBase[self::ANNOUNCEMENTS_MAILING_LIST_KEY])) {
          $this->userBase |= self::ANNOUNCEMENTS_MAILING_LIST;
        }
        if (!empty($userBase[self::PROJECT_MAILING_LIST_KEY])) {
          $this->userBase |= self::PROJECT_MAILING_LIST;
        }
      }
    }
  }

  static public function getUserBaseDescriptions(\OCP\IL10N $l)
  {
    $descriptions = [
      [
        'text' => $l->t('project mailing list'),
        'conditions' => [ 'only-project-mode', self::PROJECT_MAILING_LIST_KEY ],
      ],
      [
        'text' => $l->t('announcements mailing list'),
        'conditions' => [ self::ANNOUNCEMENTS_MAILING_LIST_KEY, ],
      ],
      [
        'text' => $l->t('musician database'),
        'conditions' => [ 'not-project-mode', ],
      ],
      [
        'text' => $l->t('all known musicians'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_CONFIRMED_KEY, self::FROM_PROJECT_PRELIMINARY_KEY, self::EXCEPT_PROJECT_KEY, ],
      ],
      [
        'text' => $l->t('all musicians NOT participating'),
        'conditions' => [ 'only-project-mode', self::EXCEPT_PROJECT_KEY, ],
      ],
      [
        'text' => $l->t('participants (confirmed)'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_CONFIRMED_KEY, ],
      ],
      [
        'text' => $l->t('participants (preliminary)'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_PRELIMINARY_KEY, ],
      ],
      [
        'text' => $l->t('participants (preliminary and confirmed)'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_PRELIMINARY_KEY, self::FROM_PROJECT_CONFIRMED_KEY, ],
      ],
      [
        'text' => $l->t('all musicians except confirmed participants'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_PRELIMINARY_KEY, self::EXCEPT_PROJECT_KEY, ],
      ],
      [
        'text' => $l->t('all musicians except preliminary participants'),
        'conditions' => [ 'only-project-mode', self::FROM_PROJECT_CONFIRMED_KEY, self::EXCEPT_PROJECT_KEY, ],
      ],
      [
        'text' => $l->t('no one'),
        'conditions' => [ 'only-project-mode', ],
      ],
    ];
    return $descriptions;
  }

  /**
   * Return an array of values we want to maintain on form-submit,
   * intentionally for wrapping into hidden input fields.
   */
  public function formData()
  {
    return [
      $this->mtabKey => $this->emailTable,
      $this->emailKey => $this->emailRecs,
      'frozenRecipients' => $this->frozen,
      'formStatus' => 'submitted',
    ];
  }

  /**
   * Return the current filter history and the filter position as
   * JSON encoded string.
   */
  public function filterHistory()
  {
    return [
      'historyPosition' => $this->historyPosition,
      'historySize' => count($this->filterHistory??[]),
    ];
  }

  /**
   * Return the current value of the member status filter or its
   * initial value.
   */
  public function memberStatusFilter()
  {
    $memberStatus = $this->cgiValue('memberStatusFilter',
                                    $this->submitted ? [] : $this->defaultByStatus());
    $memberStatus = array_flip($memberStatus);
    $result = [];
    foreach($this->memberStatusNames as $tag => $name) {
      $result[] =  [
        'value' => $tag,
        'name' => $name,
        'flags' => isset($memberStatus[$tag]) ? PageNavigation::SELECTED : 0,
      ];
    }
    return $result;
  }

  /** Return the user basic set for the email form template */
  public function basicRecipientsSet()
  {
    return [
      self::FROM_PROJECT_PRELIMINARY_KEY => ($this->userBase & self::MUSICIANS_FROM_PROJECT_PRELIMINARY) != 0,
      self::FROM_PROJECT_CONFIRMED_KEY => ($this->userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) != 0,
      self::EXCEPT_PROJECT_KEY => ($this->userBase & self::MUSICIANS_EXCEPT_PROJECT) != 0,
      self::ANNOUNCEMENTS_MAILING_LIST_KEY => ($this->userBase & self::ANNOUNCEMENTS_MAILING_LIST) != 0,
      self::PROJECT_MAILING_LIST_KEY => ($this->userBase & self::PROJECT_MAILING_LIST) != 0,
    ];
  }

  public function recipientsFromProject():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT) != 0;
  }

  public function recipientsFromProjectAll():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT) == self::MUSICIANS_FROM_PROJECT;
  }

  public function recipientsFromProjectPreliminary():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT_PRELIMINARY) != 0;
  }

  public function recipientsFromProjectConfirmed():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) != 0;
  }

  public function recipientsExceptProject():bool
  {
    return ($this->userBase & self::MUSICIANS_EXCEPT_PROJECT) != 0;
  }

  public function announcementsMailingList():bool
  {
    return ($this->userBase & self::ANNOUNCEMENTS_MAILING_LIST) != 0;
  }

  public function projectMailingList():bool
  {
    return ($this->userBase & self::PROJECT_MAILING_LIST) != 0;
  }

  /**
   * Return the values for the instruments filter.
   */
  public function instrumentsFilter()
  {
    $filterInstruments = array_flip($this->cgiValue('instrumentsFilter', [ '*' ]));
    $result = [];
    foreach($this->instruments as $instrumentId => $instrumentName) {
      if ($instrumentName == '*') {
        $value = '';
        $group = '';
      } else {
        $value = $instrumentId;
        $group = $this->instrumentGroups[$value];
      }

      $result[] = [
        'value' => $value,
        'name' => $instrumentName,
        'group' => $group,
        'flags' => isset($filterInstruments[$instrumentId]) ? PageNavigation::SELECTED : 0,
      ];
    }
    return $result;
  }

  /** Return the values for the recipient select box */
  public function emailRecipientsChoices()
  {
    if ($this->submitted) {
      $selectedRecipients = $this->cgiValue('selectedRecipients', []);
    } else {
      $selectedRecipients = $this->emailRecs;
    }
    $selectedRecipients = array_flip($selectedRecipients);

    $result = [];
    foreach($this->eMailsDpy as $key => $email) {
      if ($this->frozen && array_search($key, $this->emailRecs) === false) {
        continue;
      }
      $result[] = [
        'value' => $key,
        'name' => $email,
        'flags' => isset($selectedRecipients[$key]) ? PageNavigation::SELECTED : 0,
      ];
    }

    return $result;
  }

  /** Return a list of musicians without email address, if any. */
  public function missingEmailAddresses()
  {
    $result = array_filter($this->brokenEMail, function($key) {
      return !$this->frozen || array_search($key, $this->emailRecs) !== false;
    }, ARRAY_FILTER_USE_KEY);

    asort($result);

    return $result;
  }

  /** Return true if in initial state */
  public function initialState() {
    return !$this->submitted;
  }

  /** Return true if in reload state */
  public function reloadState() {
    return $this->reload;
  }

  /** Return true when doing a mere history snapshot. */
  public function snapshotState() {
    return $this->snapshot;
  }

  /**
   * Return the list of selected recipients. To have this method is
   * in principle the goal of all the mess above ...
   */
  public function selectedRecipients()
  {
    if ($this->userBase & self::ANNOUNCEMENTS_MAILING_LIST) {
      $listInfo = $this->getMailingListInfo(self::ANNOUNCEMENTS_MAILING_LIST_KEY);
      return [
        [
          'email' => $listInfo[MailingListsService::LIST_INFO_FQDN_LISTNAME] ?? '',
          'name' =>  $listInfo[MailingListsService::LIST_INFO_DISPLAY_NAME] ?? '',
          'status' => self::MEMBER_STATUS_OPEN,
          'project' => $this->projectId ?? 0,
          'dbdata' => null,
        ],
      ];
    } else if ($this->userBase & self::PROJECT_MAILING_LIST) {
      $listInfo = $this->getMailingListInfo(self::PROJECT_MAILING_LIST_KEY);
      return [
        [
          'email' => $listInfo[MailingListsService::LIST_INFO_FQDN_LISTNAME] ?? '',
          'name' =>  $listInfo[MailingListsService::LIST_INFO_DISPLAY_NAME] ?? '',
          'status' => self::MEMBER_STATUS_OPEN,
          'project' => $this->projectId ?? 0,
          'dbdata' => null,
        ],
      ];
    }

    if ($this->submitted) {
      $selectedRecipients = $this->cgiValue('selectedRecipients', []);
    } else {
      $selectedRecipients = $this->emailRecs;
    }
    $selectedRecipients = array_unique($selectedRecipients);
    $eMails = [];
    foreach ($selectedRecipients as $key) {
      if (isset($this->eMails[$key])) {
        $eMails[] = $this->eMails[$key];
      }
    }
    return $eMails;
  }

  /** Set the given array of musician ids as selected recipients */
  public function setSelectedRecipients(array $recipients)
  {
    $this->emailRecs = $recipients;
    $this->cgiData['selectedRecipients'] = $recipients;
  }

  /**
   * Return true if the list of recipients is frozen,
   * i.e. restricted to the pre-selected recipients.
   */
  public function frozenRecipients()
  {
    return $this->frozen;
  }

  /** @return null|Entities\Project Return the associated project entity if in project mode */
  public function getProject():?Entities\Project
  {
    return $this->project;
  }

  /**
   * Return the list information by asking the REST-server. The result is
   * cached for the time of the running request.
   *
   * @param string $which One of the known list type, currently
   * RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY or
   * RecipientsFilter::PROJECT_MAILING_LIST_KEY.
   *
   * @return null|array The list-information as returned by the mailing-lists
   * server, see MailingListsService::LIST_INFO_KEYS.
   */
  public function getMailingListInfo(string $which = self::PROJECT_MAILING_LIST_KEY)
  {
    if (empty($this->mailingListInfo[$which])) {
      $listId = null;
      /** @var MailingListsService $listsService */
      $listsService = $this->di(MailingListsService::class);
      if (!$listsService->isConfigured()) {
        return null;
      }
      switch ($which) {
        case self::PROJECT_MAILING_LIST_KEY:
          if (empty($this->project)) {
            return null;
          }
          $listId = $this->project->getMailingListId();
          break;
        case self::ANNOUNCEMENTS_MAILING_LIST_KEY:
          $listId = $this->getConfigValue(ConfigService::ANNOUNCEMENTS_MAILING_LIST_FQDN_NAME);
          break;
      }
      if (!empty($listId)) {
        try {
          $this->mailingListInfo[$which] = $listsService->getListInfo($listId);
        } catch (\Throwable $t) {
          $this->logException($t, 'Unable to contact REST interface of mailing list service');
        }
      }
    }
    return $this->mailingListInfo[$which] ?? null;
  }

  /**
   * Possibly replace part of the given recipients set by the project-mailing list:
   * - if the list contains recipients NOT in the given set, then DON'T
   * - if the given set covers the mailing list, then DO
   * - in any case keep the recipients not contained in the mailing list
   * - only recipients with active delivery are considered
   *
   * @param array $selectedRecipients Recipients-set in the form returned by
   * RecipientsFilter::selectedRecipients()
   *
   *
   */
  public function substituteProjectMailingList(array $selectedRecipients)
  {
    if (count($selectedRecipients) == 1) {
      // never send single-address email to the list
      return $selectedRecipients;
    }

    $listInfo = $this->getMailingListInfo();
    if (empty($listInfo)) {
      return $selectedRecipients;
    }
    $listId = $listInfo['list_id'];
    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);
    $listMembers = $listsService->findMembers($listId, flat: true, criteria: [
      MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_ENABLED,
      MailingListsService::MEMBER_DELIVERY_MODE => MailingListsService::DELIVERY_MODE_REGULAR,
    ]);
    $bulkEmailFromAddress = $this->getConfigValue('emailfromaddress');
    Util::unsetValue($listMembers, $bulkEmailFromAddress);
    $recipientsByEmail = [];
    foreach ($selectedRecipients as $recipient) {
      $recipientsByEmail[$recipient['email']] = $recipient;
    }
    $remainingRecipients = array_diff_key($recipientsByEmail, array_flip($listMembers));
    $remainingListMembers = array_diff($listMembers, array_keys($recipientsByEmail));

    if (!empty($remainingListMembers)) {
      // list contains members not present in the recipients-set: bail out
      $this->logInfo('Excess members ' . print_r($remainingListMembers, true));
      return $selectedRecipients;
    }

    // throw away all recipients which are also reached by posting to the list
    // and add the list address as additional recipient.
    $selectedRecipients = array_values($remainingRecipients);
    array_unshift($selectedRecipients, [
      'email' => $listInfo[MailingListsService::LIST_INFO_FQDN_LISTNAME] ?? '',
      'name' =>  $listInfo[MailingListsService::LIST_INFO_DISPLAY_NAME] ?? '',
      'status' => self::MEMBER_STATUS_OPEN,
      'project' => $this->projectId ?? 0,
      'dbdata' => null,
    ]);
    return $selectedRecipients;
  }
}
