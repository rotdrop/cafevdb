<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\EmailForm;

use \OutOfBoundsException;
use \RuntimeException;

use OCP\ISession;
use OCP\IL10N;

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

  public const MUSICIANS_FROM_PROJECT_PRELIMINARY = (1 << 0);
  public const MUSICIANS_FROM_PROJECT_CONFIRMED = (1 << 1);
  public const MUSICIANS_FROM_PROJECT = self::MUSICIANS_FROM_PROJECT_CONFIRMED|self::MUSICIANS_FROM_PROJECT_PRELIMINARY;
  public const MUSICIANS_EXCEPT_PROJECT = (1 << 2);
  public const ANNOUNCEMENTS_MAILING_LIST = (1 << 3);
  public const PROJECT_MAILING_LIST = (1 << 4);
  public const MAILING_LIST = self::ANNOUNCEMENTS_MAILING_LIST|self::PROJECT_MAILING_LIST;
  public const UNDETERMINED_MUSICIANS = 0;
  public const ALL_MUSICIANS = self::MUSICIANS_FROM_PROJECT | self::MUSICIANS_EXCEPT_PROJECT;
  public const DATABASE_MUSICIANS = self::ALL_MUSICIANS;

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

  private $brokenEMails;     // List of people without email
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

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    ISession $session,
    RequestParameterService $parameterService,
    EntityManager $entityManager,
    PHPMyEdit $pme,
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
   *
   * @return void
   */
  public function bind(RequestParameterService $parameterService):void
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
   *
   * @return void
   */
  private function execute():void
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
      } elseif ($this->cgiValue('undoInstrumentsFilter', false) !== false) {
        $this->applyHistory(1); // the current state
        $this->reload = true;
      } elseif ($this->cgiValue('redoInstrumentsFilter', false) !== false) {
        $this->applyHistory(-1);
        $this->reload = true;
      } elseif ($this->cgiValue('historySnapshot', false) !== false) {
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
    $this->determineUserBase();
    $this->getMemberStatusNames();
    $this->initMemberStatusFilter();
    $this->getInstrumentsFromDB();
    $this->fetchInstrumentsFilter();
    $this->getMusiciansFromDB();

    if (!$this->submitted) {
      // Do this at end in order to have any tweaks around
      $this->setDefaultHistory();
    } elseif (!$this->reload) {
      $previousRecipientSet = $this->filterHistory[$this->historyPosition]['data'][self::BASIC_RECIPIENTS_SET_KEY] ?? [];
      // add the current selection to the history if it is different
      // from the previous filter selection (i.e.: no-ops like
      // hitten apply over and over again or multiple double-clicks
      // will not alter the history.
      $this->pushHistory();
      if (!empty($this->project)
          && !$this->announcementsMailingList() && !$this->projectMailingList()
          && ($previousRecipientSet[self::EXCEPT_PROJECT_KEY] != $this->recipientsExceptProject())) {
        // if in project mode and not using a mailing list and posting to/not
        // to the non-participants has changed then reset the member-status
        // filter.
        $this->memberFilter = $this->defaultByStatus();
      }
    }

    $this->storeHistory();
  }

  /**
   * Fetch a CGI-variable out of the form-select name-space.
   *
   * @param string $key The pareter key to query.
   *
   * @param mixed $default The default value.
   *
   * @return mixed
   */
  private function cgiValue(string $key, mixed $default = null):mixed
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

  /**
   * Compose a default history record for the initial state
   *
   * @return void
   */
  private function setDefaultHistory():void
  {
    $this->historyPosition = 0;
    $this->historySize = 1;

    $filter = [
      self::BASIC_RECIPIENTS_SET_KEY => $this->basicRecipientsSet(),
      'memberStatusFilter' => $this->defaultByStatus(),
      'instrumentsFilter' => [],
      'selectedRecipients' => array_intersect(
        $this->emailRecs,
        array_keys($this->eMailsDpy??[])
      )
    ];

    // tweak: sort the selected recipients by key
    sort($filter['selectedRecipients']);

    $md5 = md5(serialize($filter));
    $data = $filter;
    $this->filterHistory = [ [ 'md5' => $md5, 'data' => $data ], ];
  }

  /**
   * Store the history to somewhere, probably the session-data.
   *
   * @return void
   */
  private function storeHistory():void
  {
    $storageValue = [
      'size' => $this->historySize,
      'position' => $this->historyPosition,
      'records' => $this->filterHistory,
    ];
    $this->sessionStoreValue(self::SESSION_HISTORY_KEY, $storageValue);
  }

  /**
   * Load the history from the session data.
   *
   * @return bool Execution status.
   */
  private function loadHistory():bool
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

  /**
   * Validate the given history records, return false on error.
   *
   * @param bool|array $history The history to validate.
   *
   * @return bool Validation result.
   */
  private function validateHistory(mixed $history):bool
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

  /**
   * Validate one history entry
   *
   * @param mixed $record The history record. Intentionally an array.
   *
   * @return bool Validation result.
   */
  private function validateHistoryRecord(mixed $record):bool
  {
    if (!is_array($record)) {
      return false;
    }
    if (!isset($record['md5']) || !isset($record['data'])) {
      return false;
    }
    $md5 = md5(serialize($record['data']));
    if ($record['md5'] != $md5) {
      return false;
    }
    return true;
  }

  /**
   * Validate all history records.
   *
   * @param array $history The filter history.
   *
   * @return bool Validation result.
   */
  private function validateHistoryRecords(array $history):bool
  {
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
   *
   * @param bool $fastMode If true omit normalization attempts.
   *
   * @return void
   */
  private function pushHistory(bool $fastMode = false):void
  {
    $filter = [];
    foreach (self::HISTORY_KEYS as $key) {
      $filter[$key] = $this->cgiValue($key, []);
    }

    if (!$fastMode) {
      // exclude musicians deselected by the filter from the set of
      // selected recipients before recording the history
      $filter['selectedRecipients'] =
        array_intersect(
          $filter['selectedRecipients'],
          array_keys($this->eMailsDpy)
        );

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
   * @param int $offset Relative move inside the history. The function will
   * throw an exception if emailform-debuggin is enabled and the requested
   * action would leave the history stack.
   *
   * @return void
   */
  private function applyHistory(int $offset):void
  {
    $newPosition = $this->historyPosition + $offset;

    // Check for valid position.
    if ($newPosition >= $this->historySize || $newPosition < 0) {
      if ($this->shouldDebug(ConfigService::DEBUG_EMAILFORM)) {
        throw new OutOfBoundsException(
          $this->l->t(
            'Invalid history position %d request, history size is %d',
            [ $newPosition, $this->historySize ])
        );
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
   *
   * @return void
   */
  private function remapEmailRecords():void
  {
    if (!empty($this->bulkTransaction)) {

      $payments = $this->bulkTransaction->getPayments();
      if (empty($payments)) {
        throw new RuntimeException(
          $this->l->t('No payments for bulk-transaction id %d.', [ $this->bulkTransactionId ])
        );
      }
      $this->emailRecs = [];
      foreach ($payments as $payment) {
        $this->emailRecs[] = $payment->getMusician()->getId();
      }

      $this->frozen = true; // restrict to initial set of recipients

      return;
    } elseif (!empty($this->emailTable)) {
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
   * Form an associative array with the keys
   * - name (full name)
   * - email
   * - status (MemberStatus)
   * - dbdata (data as returned from the DB for variable substitution)
   * The function fills $this->eMails, $this->eMailsDpy
   *
   * @param array $criteria Filter criteria for the list of recipients.
   *
   * @return void
   */
  private function fetchMusicians(array $criteria):void
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
    // addresses to the "brokenEMails" list.
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
            if (isset($this->brokenEMails[$rec])) {
              $this->brokenEMails[$rec]['label'] .= ', '.$bad;
            } else {
              $this->brokenEMails[$rec] = [
                'participant' => $this->projectId > 0 && $musician->isMemberOf($this->projectId),
                'label' => $bad,
              ];

            }
          } else {
            $isParticipant = $this->projectId > 0 && $musician->isMemberOf($this->projectId);
            $isNonParticipant = $this->projectId > 0 && !$isParticipant;
            $userBase = 0;
            if ($isParticipant) {
              $userBase |= self::MUSICIANS_FROM_PROJECT;
            } elseif ($isNonParticipant) {
              $userBase |= self::MUSICIANS_EXCEPT_PROJECT;
            }
            $emailRecord = [
              'email'   => $emailVal,
              'name'    => $displayName,
              'status'  => $musician['memberStatus'],
              'project' => $this->projectId ?? 0,
              'participant' => $isParticipant,
              'userBase' => $userBase,
              'dbdata'  => $musician,
            ];
            $this->eMails[$rec] = $emailRecord;
            $this->eMailsDpy[$rec] = htmlspecialchars($displayName.' <'.$emailVal.'>');
          }
        }
      } else {
        $this->brokenEMails[$rec] = [
          'participant' => $this->projectId > 0 && $musician->isMemberOf($this->projectId),
          'label' => htmlspecialchars($displayName),
        ];
      }
    }
  }

  /**
   * Fetch the list of musicians for the given context (project/global).
   *
   * @return void
   */
  private function getMusiciansFromDB():void
  {
    $this->brokenEMails = [];
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
      } elseif ($userBase & self::MUSICIANS_EXCEPT_PROJECT) {
        $criteria[] = [
          "projectParticipation.project@GROUP_CONCAT(IF(IDENTITY(%s) = {$this->projectId}, 1, NULL))" => null
        ];
      } elseif ($userBase & self::MUSICIANS_FROM_PROJECT) {
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
   *
   * @return void
   */
  private function getInstrumentsFromDb():void
  {
    $instrumentInfo =
      $this->getDatabaseRepository(Entities\Instrument::class)->describeALL();
    // Get the current list of instruments for the filter
    if ($this->projectId > 0 && !($this->userBase & self::MUSICIANS_EXCEPT_PROJECT)) {
      $this->instruments = [];
      // @todo Perhaps write a special repository-method
      $projectInstruments = $this->project->getParticipantInstruments()->toArray();
      usort($projectInstruments, fn(Entities\ProjectInstrument $a, Entities\ProjectInstrument $b) => $a->getInstrument()->getSortOrder() - $b->getInstrument()->getSortOrder());
      foreach ($projectInstruments as $projectInstrument) {
        $instrument = $projectInstrument['instrument'];
        $this->instruments[$instrument['id']] = $instrument['name'];
      }
    } else {
      $this->instruments = $instrumentInfo['byId'];
    }
    $this->instrumentGroups = $instrumentInfo['idGroups']??[];
  }

  /**
   * Fetch the instruments filter from the CGI parameters.
   *
   * @return void
   */
  private function fetchInstrumentsFilter():void
  {
    /* If in project mode: remove instruments which are not played by the
     * project participants.
     */
    $filterInstruments = array_flip($this->cgiValue('instrumentsFilter', []));
    array_intersect_key($filterInstruments, $this->instruments);

    $this->instrumentsFilter = array_keys($filterInstruments);
  }

  /**
   * @return array The default by-member-status filter as positive list.
   */
  private function defaultByStatus():array
  {
    if ($this->frozen) {
      if (!$this->memberStatusNames) {
        $this->memberStatusNames = [];
      }
      return array_keys($this->memberStatusNames);
    }
    $byStatusDefault = [ 'regular' ];
    if ($this->projectId > 0 && !$this->recipientsExceptProject()) {
      $byStatusDefault[] = DBTypes\EnumMemberStatus::PASSIVE;
      $byStatusDefault[] = DBTypes\EnumMemberStatus::TEMPORARY;
    }
    return $byStatusDefault;
  }

  /**
   * Fill $this->memberStatusNames with values.
   *
   * @return void
   */
  private function getMemberStatusNames():void
  {
    $memberStatus = DBTypes\EnumMemberStatus::toArray();
    foreach ($memberStatus as $tag) {
      if (!isset($this->memberStatusNames[$tag])) {
        $this->memberStatusNames[$tag] = $this->l->t('member status '.$tag);
      }
    }
  }

  /**
   * Get the current filter. Default value, after form submission, initial
   * setting otherwise.
   *
   * @return void
   */
  private function initMemberStatusFilter():void
  {
    $this->memberFilter = $this->cgiValue(
      'memberStatusFilter',
      $this->submitted ? [] : $this->defaultByStatus());
  }

  /** @return array Form a SQL filter expression for the member status. */
  private function memberStatusBlackList():array
  {
    $allStatusFlags = array_keys($this->memberStatusNames);
    $statusBlackList = array_diff($allStatusFlags, $this->memberFilter);
    return $statusBlackList;
  }

  /**
   * The default user base. Simple, but just keep the scheme in sync
   * with the other two filters and provide a default....()
   * function.
   *
   * @return int Or-combined flag values.
   */
  private function defaultUserBase():int
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
   * Programmatically set the user base, e.g. to force sending to the mailing
   * list.
   *
   * @param int $userBase User-base flags.
   *
   * @return void
   */
  public function setUserBase(int $userBase):void
  {
    $userBaseData = [];
    if ($userBase & self::MUSICIANS_FROM_PROJECT_PRELIMINARY) {
      $userBaseData[self::FROM_PROJECT_PRELIMINARY_KEY] = true;
    }
    if ($userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) {
      $userBaseData[self::FROM_PROJECT_CONFIRMED_KEY] = true;
    }
    if ($userBase & self::MUSICIANS_EXCEPT_PROJECT) {
      $userBaseData[self::EXCEPT_PROJECT_KEY] = true;
    }
    if ($userBase & self::ANNOUNCEMENTS_MAILING_LIST) {
      $userBaseData[self::ANNOUNCEMENTS_MAILING_LIST_KEY] = true;
    }
    if ($userBase & self::PROJECT_MAILING_LIST) {
      $userBaseData[self::PROJECT_MAILING_LIST_KEY] = true;
    }
    $this->cgiData[self::BASIC_RECIPIENTS_SET_KEY] = $userBaseData;
    $this->determineUserBase();
  }

  /**
   * Decode the check-boxes which select the set of users we
   * consider basically.
   *
   * @return void
   */
  private function determineUserBase():void
  {
    if (!$this->submitted) {
      $this->userBase = $this->defaultUserBase();
    } else {
      $userBase = $this->cgiValue(self::BASIC_RECIPIENTS_SET_KEY, false);
      if ($userBase === false) {
        $this->userBase = $this->defaultUserBase();
      } else {
        $this->userBase = self::UNDETERMINED_MUSICIANS;
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

  /**
   * @param IL10N $l
   *
   * @return array Translated descriptions of the user base options.
   */
  public static function getUserBaseDescriptions(IL10N $l):array
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
   * @return array An array of values we want to maintain on form-submit,
   * intentionally for wrapping into hidden input fields.
   */
  public function formData():array
  {
    return [
      $this->mtabKey => $this->emailTable,
      $this->emailKey => $this->emailRecs,
      'frozenRecipients' => $this->frozen,
      'formStatus' => 'submitted',
    ];
  }

  /**
   * @return array The current filter history and the filter position as JSON
   * encoded string.
   */
  public function filterHistory():array
  {
    return [
      'historyPosition' => $this->historyPosition,
      'historySize' => count($this->filterHistory??[]),
    ];
  }

  /**
   * @return The current value of the member status filter or its initial
   * value.
   */
  public function memberStatusFilter():array
  {
    $memberStatus = $this->memberFilter;
    $memberStatus = array_flip($memberStatus);
    $result = [];
    foreach ($this->memberStatusNames as $tag => $name) {
      $result[] =  [
        'value' => $tag,
        'name' => $name,
        'flags' => isset($memberStatus[$tag]) ? PageNavigation::SELECTED : 0,
      ];
    }
    return $result;
  }

  /**
   * @return int The currently set user-base.
   *
   * @see basicRecipientsSet()
   */
  public function getUserBase():int
  {
    return $this->userBase ?? self::UNDETERMINED_MUSICIANS;
  }

  /** @return array The user basic set for the email form template */
  public function basicRecipientsSet():array
  {
    return [
      self::FROM_PROJECT_PRELIMINARY_KEY => ($this->userBase & self::MUSICIANS_FROM_PROJECT_PRELIMINARY) != 0,
      self::FROM_PROJECT_CONFIRMED_KEY => ($this->userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) != 0,
      self::EXCEPT_PROJECT_KEY => ($this->userBase & self::MUSICIANS_EXCEPT_PROJECT) != 0,
      self::ANNOUNCEMENTS_MAILING_LIST_KEY => ($this->userBase & self::ANNOUNCEMENTS_MAILING_LIST) != 0,
      self::PROJECT_MAILING_LIST_KEY => ($this->userBase & self::PROJECT_MAILING_LIST) != 0,
    ];
  }

  /** @return Whether recipients from the project are selected. */
  public function recipientsFromProject():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT) != 0;
  }

  /** @return Whether all recipients from the project are selected. */
  public function recipientsFromProjectAll():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT) == self::MUSICIANS_FROM_PROJECT;
  }

  /** @return Whether only to select from the preliminary participants. */
  public function recipientsFromProjectPreliminary():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT_PRELIMINARY) != 0;
  }

  /** @return Whether only to select from the confirmed participants. */
  public function recipientsFromProjectConfirmed():bool
  {
    return ($this->userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED) != 0;
  }

  /** @return Whether only to select from non-participants. */
  public function recipientsExceptProject():bool
  {
    return ($this->userBase & self::MUSICIANS_EXCEPT_PROJECT) != 0;
  }

  /** @return Whether only to use the announcements mailing list. */
  public function announcementsMailingList():bool
  {
    return ($this->userBase & self::ANNOUNCEMENTS_MAILING_LIST) != 0;
  }

  /** @return Whether only to use the respective project mailing list. */
  public function projectMailingList():bool
  {
    return ($this->userBase & self::PROJECT_MAILING_LIST) != 0;
  }

  /**
   * @return array The values for the instruments filter.
   */
  public function instrumentsFilter():array
  {
    $filterInstruments = array_flip($this->cgiValue('instrumentsFilter', [ '*' ]));
    $result = [];
    foreach ($this->instruments as $instrumentId => $instrumentName) {
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

  /** @return array The option descriptions for the recipient select box. */
  public function emailRecipientsChoices():array
  {
    if ($this->submitted) {
      $selectedRecipients = $this->cgiValue('selectedRecipients', []);
    } else {
      $selectedRecipients = $this->emailRecs;
    }
    $selectedRecipients = array_flip($selectedRecipients);

    $result = [];
    foreach ($this->eMailsDpy as $key => $email) {
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

  /** @return array A list of musicians without email address, if any. */
  public function missingEmailAddresses():array
  {
    $result = array_filter($this->brokenEMails, function($key) {
      return !$this->frozen || array_search($key, $this->emailRecs) !== false;
    }, ARRAY_FILTER_USE_KEY);

    asort($result);

    return $result;
  }

  /** @return bool Return true if in initial state. */
  public function initialState():bool
  {
    return !$this->submitted;
  }

  /** @return bool Return true if in reload state */
  public function reloadState():bool
  {
    return $this->reload;
  }

  /** @return \true when doing a mere history snapshot. */
  public function snapshotState():bool
  {
    return $this->snapshot;
  }

  /**
   * @param null|int $userBase Optional different user base. If unset
   * $this->userBase is used.
   *
   * @return The list of selected recipients. To have this method is
   * in principle the goal of all the mess above ...
   */
  public function selectedRecipients(?int $userBase = null):array
  {
    $userBase = $userBase ?? $this->userBase;
    if ($userBase & self::ANNOUNCEMENTS_MAILING_LIST) {
      $listInfo = $this->getMailingListInfo(self::ANNOUNCEMENTS_MAILING_LIST_KEY);
      return [
        [
          'email' => $listInfo[MailingListsService::LIST_INFO_FQDN_LISTNAME] ?? '',
          'name' =>  $listInfo[MailingListsService::LIST_INFO_DISPLAY_NAME] ?? '',
          'userBase' => self::ANNOUNCEMENTS_MAILING_LIST,
          'status' => self::MEMBER_STATUS_OPEN,
          'project' => $this->projectId ?? 0,
          'dbdata' => null,
        ],
      ];
    } elseif ($userBase & self::PROJECT_MAILING_LIST) {
      $listInfo = $this->getMailingListInfo(self::PROJECT_MAILING_LIST_KEY);
      return [
        [
          'email' => $listInfo[MailingListsService::LIST_INFO_FQDN_LISTNAME] ?? '',
          'name' =>  $listInfo[MailingListsService::LIST_INFO_DISPLAY_NAME] ?? '',
          'userBase' => self::PROJECT_MAILING_LIST,
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

  /**
   * Set the given array of musician ids as selected recipients.
   *
   * @param array $recipients Replacement recipients.
   *
   * @return void
   */
  public function setSelectedRecipients(array $recipients):void
  {
    $this->emailRecs = $recipients;
    $this->cgiData['selectedRecipients'] = $recipients;
  }

  /**
   * @return bool \true if the list of recipients is frozen,
   * i.e. restricted to the pre-selected recipients.
   */
  public function frozenRecipients():bool
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
   * Substitute the recipients by the announcements mailing list if
   * possible. The composer does this if the email does not contain personal
   * substitutions.
   *
   * @param array $selectedRecipients Recipients-set in the form returned by
   * RecipientsFilter::selectedRecipients().
   *
   * @return bool \true if the recipients list has been altered, \false if it
   * has been left as is.
   */
  public function substituteAnnouncementsMailingList(array &$selectedRecipients):bool
  {
    if ($this->announcementsMailingList()) {
      return true;
    }
    if (!empty($this->project) && ($this->userBase & self::DATABASE_MUSICIANS) != self::ALL_MUSICIANS) {
      return false;
    }
    if (!empty($this->instrumentsFilter)) {
      return false;
    }
    $defaultByStatus = $this->defaultByStatus();
    $memberFilter = $this->memberFilter ?? [];
    if (array_diff($defaultByStatus, $memberFilter) !== array_diff($memberFilter, $defaultByStatus)) {
      return false;
    }

    $numberOfPossibleRecipients = count($this->emailRecipientsChoices());
    $numberOfSelectedRecipients = count($selectedRecipients);

    if ($numberOfPossibleRecipients != $numberOfSelectedRecipients) {
      return false;
    }

    if (empty($this->getMailingListInfo(self::ANNOUNCEMENTS_MAILING_LIST_KEY))) {
      return false;
    }

    $selectedRecipients = $this->selectedRecipients(self::ANNOUNCEMENTS_MAILING_LIST);

    return true;
  }

  /**
   * Possibly replace part of the given recipients set by the project-mailing list:
   * - if the list contains recipients NOT in the given set, then DON'T
   * - if the given set covers the mailing list, then DO
   * - in any case keep the recipients not contained in the mailing list
   * - only recipients with active delivery are considered
   *
   * @param array $selectedRecipients Recipients-set in the form returned by
   * RecipientsFilter::selectedRecipients().
   *
   * @return bool \true If the recipients list has been tweaked, \false if it
   * has not been altered.
   */
  public function substituteProjectMailingList(array &$selectedRecipients):bool
  {
    if (empty($this->project)) {
      // this is not a project email
      $this->logInfo('NO PROJECT');
      return false;
    }

    if (!($this->userBase & self::MUSICIANS_FROM_PROJECT_CONFIRMED)) {
      // intentionally not for the list which should contain all confirmed
      // participants + further people.
      $this->logInfo('NOT FOR THE LIST');
      return false;
    }

    $defaultByStatus = $this->defaultByStatus();
    $memberFilter = $this->memberFilter ?? [];
    if (array_intersect($memberFilter, $defaultByStatus) !== $defaultByStatus) {
      // intentionally not for the list as less than the default status type
      // were addressed
      $this->logInfo('MEMBER FILTER INCOMPATIBLE');
      return false;
    }

    if (!empty($this->instrumentsFilter)) {
      // intentionally not for the list, which is also archived
      $this->logInfo('INSTRUMENTS FILTER NOT EMPTY');
      return false;
    }

    $numberOfPossibleRecipients = count($this->emailRecipientsChoices());
    $numberOfSelectedRecipients = count($selectedRecipients);
    if ($numberOfPossibleRecipients != $numberOfSelectedRecipients) {
      // although the recipient options intentionally include the list members
      // there is a particular choice of selected recipients, so this should
      // not go to the list.
      $this->logInfo('SPECIFIC CHOICE OF RECIPIENTS');
      return false;
    }

    // keep this check last as it implies a REST call which is time-consuming.
    $listInfo = $this->getMailingListInfo();
    if (empty($listInfo)) {
      // well, there is not list ...
      return false;
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
    if (!empty($remainingRecipients)) {
      $this->logInfo('NOT ON THE LIST ' . print_r(array_keys($remainingRecipients), true));
    }

    $remainingListMembers = array_diff($listMembers, array_keys($recipientsByEmail));
    if (!empty($remainingListMembers)) {
      $this->logInfo('EXCESS MEMBERS ' . print_r($remainingListMembers, true));
      // It is ok if there are more members on the list, as intentionally this
      // should be mailing list traffic.  return false;
    }

    // throw away all recipients which are also reached by posting to the list
    // and add the list address as additional recipient.
    $selectedRecipients = array_merge(
      $this->selectedRecipients(self::PROJECT_MAILING_LIST),
      array_values($remainingRecipients),
    );
    return true;
  }
}
