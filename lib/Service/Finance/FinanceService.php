<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

use \DateTimeImmutable AS DateTime;
use Cmixin\BusinessDay;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EventsService;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Documents\PDFFormFiller;

/** Finance and bank related stuff. */
class FinanceService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\EnsureEntityTrait;
  use \OCA\CAFEVDB\Traits\SloppyTrait;

  const ENTITY = Entities\SepaDebitMandate::class;
  const SEPA_CHARSET = "a-zA-Z0-9 \/?:().,'+-";
  const SEPA_PURPOSE_LENGTH = 4*35;
  const SEPA_MANDATE_LENGTH = 35; ///< Maximum length of mandate reference.
  const SEPA_MANDATE_EXPIRE_MONTHS = 36; ///< Unused mandates expire after this time.
  const BANK_NAME_MAX = 32; // for pretty-printing

  const TARGET2_HOLIDAYS = [
    'new-year'                => '01-01',
    'easter-2'                => '= easter -2',
    'easter'                  => '= easter',
    'easter-p1'               => '= easter 1',
    'labor-day'               => '05-01',
    'christmas'               => '12-25',
    'christmas-next-day'      => '12-26',
  ];

  /** @var EventsService */
  private $eventsService;

  /** @var Repositories\SepaDebitMandatesRepository */
  private $mandatesRepository;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , EventsService $eventsService
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->eventsService = $eventsService;
    $this->l = $this->l10n();

    $this->mandatesRepository = $this->getDatabaseRepository(self::ENTITY);

    BusinessDay::enable([
      Carbon::class,
      CarbonImmutable::class,
    ]);
    CarbonImmutable::setHolidays('target2', $targetHolidays);
  }

  /**
   * Add the given offset of days to the given $fromDate. If
   * $calendarOffset is non-empty, then the resulting absolute
   * distance from $fromDate will be the maxium of both offsets. The
   * offsets must not have different signs.
   *
   * Business-days are relative to the SEPA TARGET2 calendar,
   * {@see https://en.wikipedia.org/wiki/TARGET2#Holidays}
   *
   * @param int $businessOffset Offset in TARGET2 business days.
   *
   * @param int|null $calendarOffset Offset in calendar days.
   *
   * @param \DateTimeInterface|null $fromDate The pivot-date.
   */
  public function targetDeadline(int $businessOffset, ?int $calendarOffset = null, ?\DateTimeInterface $fromDate = null):\DateTimeInterface
  {
    /** @var CarbonImmutable $fromDate */
    if (empty($fromDate)) {
      $fromDate = new CarbonImmutable('now', $this->getDateTimeZone());
    } else {
      $fromDate = (new CarbonImmutable)
                ->setTimezone($fromDate->getTimezone())
                ->setTimestamp($fromDate->getTimestamp());
    }

    if (!empty($calendarOffset) && $businessOffset*$calendarOffset < 0) {
      throw new \RuntimeException(
        $this->l->t('The business-day and calendar-day offset must have the same sign (%d / %d)', [
          $businessOffset, $calendarOffset
        ]));
    }

    CarbonImmutable::setHolidaysRegion('target2');
    $businessDeadline = $fromDate->addBusinessDays($businessOffset);
    if (!empty($calendarOffset)) {
      $calendarDeadline = $fromDate->addDays($calendarOffset);

      $businessInterval = $fromDate->diff($businessDeadline, true);
      $calendarInterval = $fromDate->diff($calendarDeadline, true);

      if ($calendarInterval->days > $businessInterval->days) {
        return $calendarDeadline;
      }
    }

    return $businessDeadline;
  }

  public function isClubMembersProject($projectOrId):bool
  {
    if (empty($projectOrId)) {
      return false;
    }
    $projectId = ($projectOrId instanceof Entities\Project)
               ? $projectOrId->getId()
               : $projectOrId;
    return (int)$projectId === $this->getClubMembersProjectId();
  }

  public function isClubMember($musicianOrId):bool
  {
    $musician = $this->ensureMusician($musicianOrId);
    if (empty($musician)) {
      return false;
    }
    return $musician->isMemberOf($this->getClubMembersProjectId());
  }

  /**
   *
   */
  public function preFilledDebitMandateForm($accountSequenceOrAccount, $projectOrId, $musicianOrId = null)
  {
    /** @var Entities\Musician $musician */
    if (!($accountSequenceOrAccount instanceof Entities\SepaBankAccount)) {
      $musician = $this->ensureMusician($musicianOrId);
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $this->getDatabaseRepository(Entities\SepaBankAccount::class)
                          ->find([ 'musician' => $musician, 'sequence' => $accountSequenceOrAccount ]);
    } else {
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $accountSequenceOrAccount;

      $musician = $bankAccount->getMusician();
      if (!empty($musicianOrId)
          && $musician->getId() != $this->ensureMusician($musicianOrId)->getId()) {
        throw new \InvalidArgumentException(
          $this->l->t('Bankaccount belongs to musician "%s", but specified musician is "%s".',
                      [ $musician->getPublicName(), $this->ensureMusician($musicianOrId)->getPublicName() ]));
      }
    }

    if (empty($musician)) {
      return [];
    }

    if (empty($projectOrId) && $this->isClubMember($musician)) {
      // assume a general debit mandate
      $project = $this->ensureProject($this->getClubMembersProjectId());
    } else {
      $project = $this->ensureProject($projectOrId);
    }

    if (empty($project)) {
      return [];
    }

    if ($this->isClubMembersProject($project)) {
      $formFileName = $this->getConfigValue('generalDebitNoteMandateForm');
    } else {
      $formFileName = $this->getConfigValue('projectDebitNoteMandateForm');
    }

    if (empty($formFileName)) {
      return [];
    }

    $templatesFolder = $this->getDocumentTemplatesPath();
    if (empty($templatesFolder)) {
      return  [];
    }
    $formFileName = UserStorage::pathCat($templatesFolder, $formFileName);

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    $formFile = $userStorage->getFile($formFileName);
    if (empty($formFile)) {
      return [];
    }

    if (empty(strrchr($formFile->getMimeType(), 'pdf'))) {
      return [ $formFile->getContent(), $formFile->getMimeType(), $formFile->getName() ];
    }

    $formData = [
      'projectName' => $project->getName(),
      'bankAccountOwner' => $musician->getPublicName(),
      'projectParticipant' => $musician->getPublicName(),
    ];

    if (!empty($bankAccount)) {
      $info = $this->getIbanInfo($bankAccount->getIban());
      $bank = $this->ellipsizeFirst($info['bank'], $info['city'], self::BANK_NAME_MAX);

      $formData = array_merge($formData, [
        'bankAccountOwner' => $bankAccount->getBankAccountOwner(),
        'bankAccountIBAN' => $bankAccount->getIban(),
        'bankAccountBIC' => $bankAccount->getBic(),
        'bank' => $bank,
      ]);
    }

    $formFiller = (new PDFFormFiller($formFile))->fill($formData);

    $fileParts = [
      $this->timeStamp('Ymd'),
      basename($formFile->getName()),
      str_replace('.', '-', $musician->getUserIdSlug()),
    ];
    $fileName = implode('-', $fileParts) . '.pdf';

    return [ $formFiller->getContent(), 'application/pdf', $fileName ];
  }

  /**
   * Add an event to the finance calendar, possibly including a
   * reminder.
   *
   * @param string $title
   * @param string $description (may be empty)
   * @param null|Entities\Project $projectName (may be empty)
   * @param \DateTimeInterface $timeStamp
   * @param int $alarm (maybe <= 0 for no alarm)
   *
   * @return null|string new event uri
   */
  public function financeEvent($title, $description, ?Entities\Project $project, \DateTimeInterface $time, int $alarm = 0): ?string
  {
    $eventKind = 'finance';
    $categories = '';
    if ($projectName) {
      // This triggers adding the event to the respective project when added
      $categories .= $project->getName().',';
    }
    $categories .= $this->l->t('finance');
    $calKey       = $eventKind.'calendar';
    $calendarName = $this->getConfigValue($calKey, $this->l->t($eventKind));
    $calendarId   = $this->getConfigValue($calKey.'id', false);

    $eventData = [
      'summary' => $title,
      'from' => $time->format('d-m-Y'),
      'to' => $time->format('d-m-Y'),
      'allday' => 'on',
      'location' => 'Cyber-Space',
      'categories' => $categories,
      'description' => $description,
      'repeat' => 'doesnotrepeat',
      'calendar' => $calendarId,
      'alarm' => $alarm,
    ];

    return $this->eventsService->newEvent($eventData);
  }

  /**
   * Add a task to the finance calendar, possibly including a
   * reminder.
   *
   * @param string $title
   * @param string $description (may be empty)
   * @param null|Entities\Project $project (may be empty)
   * @param \DateTimeInterface $time
   * @param int $alarm (maybe <= 0 for no alarm)
   *
   * @return null|string new event uri
   */
  public function financeTask($title, $description, ?Entities\Project $project, \DateTimeInterface $time, int $alarm = 0): ?string
  {
    $taskKind = 'finance';
    $categories = '';
    if ($projectName) {
      // This triggers adding the task to the respective project when added
      $categories .= $project->getName().',';
    }
    $categories .= $this->l->t('finance');
    $calKey       = $taskKind.'calendar';
    $calendarName = $this->getConfigValue($calKey, $this->l->t($taskKind));
    $calendarId   = $this->getConfigValue($calKey.'id', false);

    $taskData = [
      'summary' => $title,
      'due' => $time->format('d-m-Y'),
      'start' => $time->format('d-m-Y'),
      'location' => 'Cyber-Space',
      'categories' => $categories,
      'description' => $description,
      'calendar' => $calendarId,
      'priority' => 99, // will get a star if != 0
      'alarm' => $alarm,
    ];

    return $this->eventsService->newTask($taskData);
  }

  /**
   * Delete an entry from the finance calendar.
   */
  public function deleteFinanceCalendarEntry($localUri)
  {
    $taskKind = 'finance';
    $calKey = $taskKind.'calendar';
    $calendarId = $this->getConfigValue($calKey.'id', false);
    $this->eventsService->deleteCalendarEntry($calendarId, $localUri);
  }

  /**
   * Convert an UTF-8 encoded string to something simpler, with
   * transliteration of special characters. I may not be necesary any
   * more, but at least at the beginning of the SEPA affair at least
   * some banks were at least very restrictive concerning the allowed
   * characters.
   */
  public function sepaTranslit($string, $language = null)
  {
    if (!empty($language)) {
      $locale = strtolower($language).'_'.strtoupper($language).'UTF-8';
    }
    return $this->transliterate($string, $locale);
  }

  /**
   * Validate whether the given string conforms to a very restricted
   * character set. I may not be necesary any more, but at least at
   * the beginning of the SEPA affair at least some banks were at
   * least very restrictive concerning the allowed characters.
   */
  public function validateSepaString($string)
  {
    return !preg_match('@[^'.self::SEPA_CHARSET.']@', $string);
  }

  /**
   * The "SEPA mandat reference" must be unique per mandate, consist
   * more or less of alpha-numeric characters and has a maximum length
   * of 35 characters. We choose the format
   *
   * XXXX-YYYY-IN-PROJECTYEAR
   *
   * where XXXX is the project Id, YYYY the musician ID, PROJECT and
   * YEAR are the project name and the year. The project name will be
   * shortened s.t. the entire reference fits into 35 characters.  IN
   * are the initials of the musican (first character of first first
   * name, first character of first surname).
   *
   * Mandates expired after 36 months if not used, and if the bank
   * account information changes then we also need a new mandate
   * reference. If this should happen for the same project (i.e. the
   * club-member pseudo-project) then we attach a sequence number at
   * the end. If necessary, the project name will shortened further
   * for this purpose. Sequence numbers are only present if necessary:
   *
   * XXXX-YYYY-IN-PROJECTYEAR+SEQ
   *
   * @param Entities\SepaDebitMandate $mandate The mandate the
   * generate the reference for.
   *
   * @return string New mandate reference.
   *
   */
  public function generateSepaMandateReference(Entities\SepaDebitMandate $mandate):string
  {
    $project = $this->ensureProject($mandate->getProject());
    if (empty($project)) {
      throw new \InvalidArgumentException($this->l->t('The given mandate does not contain a valid project-id.'));
    }
    $mandate->setProject($project);

    $projectId = $project['id'];
    $projectName = $this->sepaTranslit($project['name']);

    $projectYear = substr($projectName, -4);
    if (is_numeric($year)) {
      $projectName = substr($projectName, 0, -4);
    } else {
      $projectYear = null;
    }

    $musician = $this->ensureMusician($mandate->getMusician());
    if (empty($musician)) {
      throw new \InvalidArgumentException($this->l->t('The given mandate does not contain a valid musician-id.'));
    }
    $mandate->setMusician($musician);

    $sequence = $mandate->getSequence();

    $musicianId = $musician['id'];
    $firstName = $this->sepaTranslit($musician['firstName']);
    $surName = $this->sepaTranslit($musician['surName']);

    $format = empty($projectYear)
            ? '%04d-%04d-%\'X1.1s%\'X1.1s-%-\'X19.19s%.0s+%02d'
            : '%04d-%04d-%\'X1.1s%\'X1.1s-%-\'X15.15s%04d+%02d';

    $ref = sprintf($format,
                   $projectId, $musicianId,
                   $firstName, $surName,
                   $projectName, $projectYear,
                   (int)$sequence);

    $ref = strtoupper(Util::normalizeSpaces($ref, 'X'));

    if (strlen($ref) != self::SEPA_MANDATE_LENGTH) {
      throw new \RuntimeException(
        $this->l->t('SEPA mandate-reference "%s" is too long (%d > %d).',
                    [ $ref, strlen($ref), self::SEPA_MANDATE_LENGTH]));
    }

    return $ref;
  }

  /**
   * Fetch an exisiting reference given project and musician. This
   * fetch the entire db-row, i.e. everything known about the
   * mandate. Expired and inactive mandates are ignored, i.e. false
   * is returned in this case.
   */
  public function fetchSepaMandate($projectId, $musicianId, $expired = false)
  {
    $mandate = null;

    $mandate = $this->mandatesRepository->findNewest($projectId, $musicianId);

    if (!empty($mandate) && !$expired
        && $this->mandateIsExpired($mandate['mandateReference'])) {
      return null;
    }

    return $mandate;
  }

  /**
   * Set the sequence type based on the last-used date and the
   * recurring/non-recurring status.
   *
   * @param array|SepaDebitMandate $mandate Either a plain array were
   * the keys are the actual names of the database columns, or the
   * database entity from the model.
   */
  public function sepaMandateSequenceType($mandate)
  {
    if ($mandate['nonRecurring']) {
      return 'once';
    } else if (empty($mandate['lastUsedDate']) || $mandate['lastUsedDate'] == '0000-00-00') {
      return 'first';
    } else {
      return 'following';
    }
    return false;
  }

  /**
   * Store a SEPA-mandate, possibly with only partial
   * information. mandateReference, musicianId and projectId are
   * required.
   *
   * @todo Switch to \Doctrine\ORM entities, i.e. $mandate should at
   * least optionally be an Entities\SepaDebitMandate.
   */
  public function storeSepaMandate($newMandate)
  {
    if (!is_array($newMandate) ||
        !isset($newMandate['mandateReference']) ||
        !isset($newMandate['musicianId']) ||
        !isset($newMandate['projectId'])) {
      return null;
    }

    if (isset($newMandate['sequenceType'])) {
      $sequenceType = $newMandate['sequenceType'];
      $newMandate['nonRecurring'] = $sequenceType == 'once';
      unset($newMandate['sequenceType']);
    }

    $ref = $newMandate['mandateReference'];
    $mus = $newMandate['musicianId'];
    $prj = $newMandate['projectId'];

    // Convert to a date format understood by mySQL.
    // @todo use \DateTime objects.
    $dateFields = [ 'lastUsedDate', 'mandateDate', ];
    foreach ($dateFields as $date) {
      if (!empty($newMandate[$date])) {
        $stamp = strtotime($newMandate[$date]);
        $value = date('Y-m-d', $stamp);
        if ($stamp != strtotime($value)) {
          return null;
        }
        $newMandate[$date] = $value;
      } else {
        unset($newMandate[$date]);
      }
    }

    $table = $this->DATA_BASE_INFO['table'];

    // fetch the old mandate, but keep the old values encrypted
    $mandate = $this->fetchSepaMandate($prj, $mus, false);
    if (!empty($mandate)) {
      // Sanity checks
      if (!isset($mandate['mandateReference']) ||
          !isset($mandate['musician']) ||
          !isset($mandate['project']) ||
          $mandate['mandateReference'] != $ref ||
          $mandate['musician']['id'] != $mus ||
          $mandate['project']['id'] != $prj) {
        return null;
      }
      // passed: issue an update query
      foreach ($newMandate as $key => $value) {
        switch ($key) {
        case 'musicianId':
          $targetKey = 'musician';
          break;
        case 'projectId':
          $targetKey = 'project';
          break;
        default:
          $targetKey = $key;
          break;
        }
        if (empty($value) && $key != 'lastUsedDate') {
          unset($newMandate[$key]);
          $value = null;
        }
        $mandate[$targetKey] = $value; // @todo check date and time-stamps.
      }
    } else {
      $mandate = Entities\SepaDebitMandate::create();
      foreach ($newMandate as $key => $value) {
        switch ($key) {
        case 'musicianId':
          $targetKey = 'musician';
          break;
        case 'projectId':
          $targetKey = 'project';
          break;
        default:
          $targetKey = $key;
          break;
        }
        $mandate[$targetKey] = $value; // @todo check date and time-stamps.
      }
      $this->persist($mandate);
    }
    $this->flush($mandate); // persist

    return $mandate;
  }

  /**Compute usage data for the given mandate reference*/
  public function mandateReferenceUsage($reference, $brief = false)
  {
    return $this->mandatesRepository->usage($reference, $brief);
  }

  /**
   * Determine if the given mandate is expired, in which case we
   * would need a new mandate.
   *
   * @param mixed $usageInfo Either a mandate-reference or a
   * previously fetched result from $this->mandateReferenceUsage()
   *
   * @return bool @c true iff the mandate is expired, @c false otherwise.
   */
  public function mandateIsExpired($usageInfo)
  {
    $mandate = $usageInfo;
    if (empty($usageInfo['lastUsed'])) {
      $usageInfo = $this->mandateReferenceUsage($usageInfo, true);
    }
    if (empty($usageInfo['lastUsed'])) {
      $usageInfo['lastUsed'] = $usageInfo['mandateIssued'];
    }
    $oldLocale = setlocale(LC_TIME, '0');
    setlocale(LC_TIME, $this->getLocale());

    $oldTZ = date_default_timezone_get();
    $tz = $this->getTimezone();
    date_default_timezone_set($tz);

    $nowDate  = Util::dateTime(strftime('%Y-%m-%d'));
    $usedDate = Util::dateTime($usageInfo['lastUsed']); // lastUsed may already be a DateTime object

    $diff = $usedDate->diff($nowDate);
    $months = $diff->format('%y') * 12 + $diff->format('%m');

    date_default_timezone_set($oldTZ);
    setlocale(LC_TIME, $oldLocale);

    return $months >= self::SEPA_MANDATE_EXPIRE_MONTHS;
  }

  /**
   * Deactivate a SEPA-mandate (timeout, withdrawn, erroneous data
   * etc.). This flags the mandate as deleted, but we have to keep
   * the data for the book-keeping.
   *
   * @param string $mandateReference The mandate reference string.
   *
   * @return ?Entities\SepaDebitMandate
   */
  public function deactivateSepaMandate($mandateReference):?Entities\SepaDebitMandate
  {
    return !empty($this->mandatesRepository->ban($mandateReference));
  }

  /**
   * Erase a SEPA-mandate. This is important data, so we require the
   * project and musician as well as the mandate reference.
   *
   * @param string $mandateReference The mandate reference string.
   *
   * @return bool True on success.
   */
  public function deleteSepaMandate($mandateReference)
  {
    return !empty($this->mandatesRepository->remove($mandateReference));
  }


  /**
   * Decode the information of an IBAN in to an array.
   *
   * @param string $iban
   *
   * @return array
   * ```
   * [
   *   'iban' => $iban,
   *   'bic' => BIC,
   *   'blz' => BLZ,
   *   'account' => BANK_ACCOUNT_NR,
   *   'bank' => NAME_OF_BANK,
   * ]
   * ```
   */
  public function getIbanInfo(string $iban)
  {
    $result = [ 'iban' => $iban ];

    $iban = new \PHP_IBAN\IBAN($iban);
    if (!$iban->Verify()) {
      return null;
    }

    $result['country'] = $iban->Country();

    if ($iban->Country() == 'DE') {
      // otherwise: not implemented yet
      $ibanBLZ = $iban->Bank();
      $ibanKTO = $iban->Account();

      $bav = new \malkusch\bav\BAV;

      if (!$bav->isValidBank($ibanBLZ)) {
        return null;
      }

      if (!$bav->isValidAccount($ibanKTO)) {
        return null;
      }

      $agency = $bav->getMainAgency($ibanBLZ);
      $blzBIC = $agency->getBIC();
      $bankName = $agency->getName();
      $bankCity = $agency->getCity();
      $result = array_merge($result, [
        'bic' => $blzBIC,
        'blz' => $ibanBLZ,
        'account' => $ibanKTO,
        'bank' => $bankName,
        'city' => $bankCity,
      ]);
    }
    return $result;
  }

  /**
   * Validate the given SEPA bank account. It is assumed that
   * transliteration etc. already has been performed during the
   * validation of user input, so this need not be very user-friendly.
   *
   * @param Entities\SepaBankAccount $account
   * @throws \InvalidArgumentException
   */
  public function validateSepaAccount(Entities\SepaBankAccount $account)
  {

    // Verify that bankAccountOwner conforms to the brain-damaged
    // SEPA charset. Thank you so much. Banks.
    if (!$this->validateSepaString($account->getBankAccountOwner())) {
      throw new \InvalidArgumentException($this->l->t('Illegal characters in bank account owner field.'));
    }

    // Check IBAN and BIC: extract the bank and bank account id,
    // check both with BAV, regenerate the BIC
    $IBAN = $account->getIban();
    $BLZ  = $account->getBlz();
    $BIC  = $account->getBic();

    $iban = new \PHP_IBAN\IBAN($IBAN);
    if (!$iban->Verify()) {
      throw new \InvalidArgumentException($this->l->t('Invalid IBAN: %s', $IBAN));
    }

    if ($iban->Country() == 'DE') {
      // otherwise: not implemented yet
      $ibanBLZ = $iban->Bank();
      $ibanKTO = $iban->Account();

      if ($BLZ != $ibanBLZ) {
        throw new \InvalidArgumentException($this->l->t('BLZ and IBAN do not match: %s != %s', [ $BLZ, $ibanBLZ, ]));
      }

      $bav = new \malkusch\bav\BAV;

      if (!$bav->isValidBank($ibanBLZ)) {
        throw new \InvalidArgumentException($this->l->t('Invalid German BLZ: %s.', $BLZ));
      }

      if (!$bav->isValidAccount($ibanKTO)) {
        throw new \InvalidArgumentException($this->l->t('Invalid German bank account: %s @ %s.', [ $ibanKTO, $BLZ, ]));
      }

      $blzBIC = $bav->getMainAgency($ibanBLZ)->getBIC();
      if ($blzBIC != $BIC) {
        throw new \InvalidArgumentException($this->l->t('Probably invalid BIC: %s. Computed: %s. ', [ $BIC, $blzBIC, ]));
      }
    }

    return true;
  }

  /********************************************************
   * Funktionen fuer die Umwandlung und Verifizierung von IBAN/BIC
   * Fragen/Kommentare bitte auf http://donauweb.at/ebusiness-blog/2013/07/25/iban-und-bic-statt-konto-und-blz/
   ********************************************************/

  /********************************************************
   * BLZ und BIC in AT: http://www.conserio.at/bankleitzahl/
   * BLZ und BIC in DE: http://www.bundesbank.de/Redaktion/DE/Standardartikel/Kerngeschaeftsfelder/Unbarer_Zahlungsverkehr/bankleitzahlen_download.html
   ********************************************************/

  /********************************************************
   * Funktion zur Plausibilitaetspruefung einer IBAN-Nummer, gilt fuer alle Laender
   * Das Ganze ist deswegen etwas spannend, weil eine Modulo-Rechnung, also eine Ganzzahl-Division mit einer
   * bis zu 38-stelligen Ganzzahl durchgefuehrt werden muss. Wegen der meist nur zur Verfuegung stehenden
   * 32-Bit-CPUs koennen mit PHP aber nur maximal 9 Stellen mit allen Ziffern genutzt werden.
   * Deshalb muss die Modulo-Rechnung in mehere Teilschritte zerlegt werden.
   * http://www.michael-schummel.de/2007/10/05/iban-prufung-mit-php
   ********************************************************/
  public function testIBAN( $iban ) {
    $iban = str_replace( ' ', '', $iban );
    $iban1 = substr( $iban,4 )
           . strval( ord( $iban[0] )-55 )
           . strval( ord( $iban[1] )-55 )
           . substr( $iban, 2, 2 );

    for( $i = 0; $i < strlen($iban1); $i++) {
      if(ord( $iban1[$i] )>64 && ord( $iban1[$i] )<91) {
        $iban1 = substr($iban1,0,$i) . strval( ord( $iban1[$i] )-55 ) . substr($iban1,$i+1);
      }
    }
    $rest=0;
    for ( $pos=0; $pos<strlen($iban1); $pos+=7 ) {
      $part = strval($rest) . substr($iban1,$pos,7);
      $rest = intval($part) % 97;
    }
    $pz = sprintf("%02d", 98-$rest);

    if ( substr($iban,2,2)=='00')
      return substr_replace( $iban, $pz, 2, 2 );
    else
      return ($rest==1) ? true : false;
  }

  public function testCI($ci)
  {
    $ci = preg_replace('/\s+/', '', $ci); // eliminate space
    $country      = substr($ci, 0, 2);
    $checksum     = substr($ci, 2, 2);
    $businesscode = substr($ci, 4, 3);
    $id           = substr($ci, 7);
    if ($country == 'DE' && strlen($ci) != 18) {
      return false;
    } else if (strlen($ci) > 35) {
      return false;
    }
    $fakeIBAN = $country . $checksum . $id;
    return $this->testIBAN($fakeIBAN);
  }

  /********************************************************
   * Funktion zur Erstellung einer IBAN aus BLZ+Kontonr
   * Gilt nur fuer deutsche Konten
   ********************************************************/
  public function makeIBAN($blz, $kontonr) {
    $blz8 = str_pad ( $blz, 8, "0", STR_PAD_RIGHT);
    $kontonr10 = str_pad ( $kontonr, 10, "0", STR_PAD_LEFT);
    $bban = $blz8 . $kontonr10;
    $pruefsumme = $bban . "131400";
    $modulo = (bcmod($pruefsumme,"97"));
    $pruefziffer =str_pad ( 98 - $modulo, 2, "0",STR_PAD_LEFT);
    $iban = "DE" . $pruefziffer . $bban;
    return $iban;
  }

  public function validateSWIFT($swift)
  {
    return preg_match('/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/i', $swift);
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
