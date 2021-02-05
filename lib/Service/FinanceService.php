<?php
/* Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

/**Finance and bank related stuff. */
class FinanceService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private $useEncryption = true;

  const ENTITY = Entities\SepaDebitMandate::class;
  const DATA_BASE_INFO = [
    'table' => 'SepaDebitMandates',
    'key' => 'id',
    'encryptedColumns' => [
      'IBAN', 'BIC', 'BLZ', 'bankAccountOwner',
    ],
  ];
  const SEPA_CHARSET = "a-zA-Z0-9 \/?:().,'+-";
  const SEPA_PURPOSE_LENGTH = 35;
  const SEPA_MANDATE_LENGTH = 35; ///< Maximum length of mandate reference.
  const SEPA_MANDATE_EXPIRE_MONTHS = 36; ///< Unused mandates expire after this time.

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
  }

  /**Add an event to the finance calendar, possibly including a
   * reminder.
   *
   * @param $title
   * @param $description (may be empty)
   * @param $projectName (may be empty)
   * @param $timeStamp
   * @param $alarm (maybe <= 0 for no alarm)
   *
   * @return null|string new event uri
   */
  public function financeEvent($title, $description, $projectName, $timeStamp, $alarm = false): ?string
  {
    $eventKind = 'finance';
    $categories = '';
    if ($projectName) {
      // This triggers adding the event to the respective project when added
      $categories .= $projectName.',';
    }
    $categories .= $this->l->t('finance');
    $calKey       = $eventKind.'calendar';
    $calendarName = $this->getConfigValue($calKey, $this->l->t($eventKind));
    $calendarId   = $this->getConfigValue($calKey.'id', false);

    $eventData = [
      'summary' => $title,
      'from' => date('d-m-Y', $timeStamp),
      'to' => date('d-m-Y', $timeStamp),
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

  /**Add a task to the finance calendar, possibly including a
   * reminder.
   *
   * @param $title
   * @param $description (may be empty)
   * @param $projectName (may be empty)
   * @param $timeStamp
   * @param $alarm (maybe <= 0 for no alarm)
   *
   * @return null|string new event uri
   */
  public function financeTask($title, $description, $projectName, $timeStamp, $alarm = false): ?string
  {
    $taskKind = 'finance';
    $categories = '';
    if ($projectName) {
      // This triggers adding the task to the respective project when added
      $categories .= $projectName.',';
    }
    $categories .= $this->l->t('finance');
    $calKey       = $taskKind.'calendar';
    $calendarName = $this->getConfigValue($calKey, $this->l->t($taskKind));
    $calendarId   = $this->getConfigValue($calKey.'id', false);

    $taskData = [
      'summary' => $title,
      'due' => date('d-m-Y', $timeStamp),
      'start' => date('d-m-Y'),
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
   * Convert an UTF-8 encoded string to something simpler, with
   * transliteration of special characters. I may not be necesary any
   * more, but at least at the beginning of the SEPA affair at least
   * some banks were at least very restrictive concerning the allowed
   * characters.
   */
  public function sepaTranslit($string)
  {
    $oldLocale = setlocale(LC_ALL, '0');
    setlocale(LC_ALL, 'de_DE.UTF8'); // after all, this is just for German banks.
    $result = iconv("utf-8","ascii//TRANSLIT", $string);
    setlocale(LC_ALL, $oldLocale);
    return $result;
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
   * @param Entities\Project $project
   *
   * @param Entities\Musician $musician
   *
   * @param int $sequence
   *
   * @return string New mandate id
   *
   */
  public function generateSepaMandateReference(Entities\Project $project,
                                               Entities\Musician $musician,
                                               int $sequence = 1):string
  {
    $projectName = $this->sepaTranslit($project['name']);
    $firstName = $this->sepaTranslit($musician['firstName']);
    $lastName = $this->sepaTranslit($musician['lastName']);

    $firstName .= 'X';
    $lastName .= 'X';
    $initials = $firstName[0].$lastName[0];

    $prjId = substr("0000".$projectId, -4);
    $musId = substr("0000".$musicianId, -4);

    $ref = $prjId.'-'.$musId.'-'.$initials.'-';

    $tail = '+'.sprintf("%02d", intval($sequence));

    $year = substr($projectName, -4);
    if (is_numeric($year)) {
      $projectName = substr($projectName, 0, -4);
      $tail = $year.$tail;
    }
    $tailLength = strlen($tail);
    $trimLength = $this->SEPA_MANDATE_LENGTH - strlen($tail);
    $ref = substr($ref.$projectName, 0, $trimLength).$tail;

    $ref = preg_replace('/\s+/', 'X', $ref); // replace space by X

    return strtoupper($ref);
  }

  /**Fetch an exisiting reference given project and musician. This
   * fetch the entire db-row, i.e. everything known about the
   * mandate. Expired and inactive mandates are ignored, i.e. false
   * is returned in this case.
   */
  public function fetchSepaMandate($projectId, $musicianId, $cooked = true, $expired = false)
  {
    $mandate = null;

    $mandate = $this->mandateRepository->findNewest($projectId, $musicianId);

    if (!$expired && $this->mandateIsExpired($mandate['mandateReference'])) {
      return false;
    }

    if ($cooked) {
      if ($mandate && !isset($mandate['sequenceType'])) {
        if ($mandate['nonrecurring']) {
          $mandate['sequenceType'] = 'once';
        } else {
          $mandate['sequenceType'] = 'permanent';
        }
        unset($mandate['nonrecurring']);
      }

      if (!$this->decryptSepaMandate($mandate)) {
        $mandate = false;
      }
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
    if (isset($mandate['nonrecurring'])) {
      if ($mandate['nonrecurring']) {
        return 'once';
      } else if (empty($mandate['lastUsedDate']) || $mandate['lastUsedDate'] == '0000-00-00') {
        return 'first';
      } else {
        return 'following';
      }
      unset($mandate['nonrecurring']);
      $mandate['sequenceType'] = $sequenceType;
    } else if (isset($mandate['sequenceType'])) {
      if ($mandate['sequenceType'] == 'permanent') {
        return (empty($mandate['lastUsedDate']) || $mandate['lastUsedDate'] == '0000-00-00') ? 'first' : 'following';
      } else {
        return $mandate['sequenceType'];
      }
    }

    // error: cannot compute sequenceType.
    return false;
  }

  /**Given a mandate with plain text columns, encrypt them. */
  public function encryptSepaMandate(&$mandate)
  {
    if (is_array($mandate) && $this->$useEncryption) {
      $enckey = $this->getAppEncryptionKey();
      foreach ($this->DATA_BASE_INFO['encryptedColumns'] as $column) {
        if (isset($mandate[$column])) {
          $value = $this->encrypt($mandate[$column], $enckey);
          if ($value === false) {
            return false;
          }
          $mandate[$column] = $value;
        }
      }
    }
    return true;
  }

  /**Given a mandate with encrypted columns, decrypt them. */
  public function decryptSepaMandate(&$mandate)
  {
    if (is_array($mandate) && $this->$useEncryption) {
      $enckey = $this->getAppEncryptionKey();
      foreach ($this->DATA_BASE_INFO['encryptedColumns'] as $column) {
        $value = $this->decrypt($mandate[$column], $enckey);
        if ($value === false) {
          return false;
        }
        $mandate[$column] = $value;
      }
    }
    return true;
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
    $result = false;

    if (!is_array($newMandate) ||
        !isset($newMandate['mandateReference']) ||
        !isset($newMandate['musicianId']) ||
        !isset($newMandate['projectId'])) {
      return false;
    }

    if (isset($newMandate['sequenceType'])) {
      $sequenceType = $newMandate['sequenceType'];
      $newMandate['nonrecurring'] = $sequenceType == 'once';
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
          return false;
        }
        $newMandate[$date] = $value;
      } else {
        unset($newMandate[$date]);
      }
    }

    $this->encryptSepaMandate($newMandate);

    $table = $this->DATA_BASE_INFO['table'];

    // fetch the old mandate, but keep the old values encrypted
    $mandate = $this->fetchSepaMandate($prj, $mus, false);
    if (!empty($mandate)) {
      // Sanity checks
      if (!isset($mandate['mandateReference']) ||
          !isset($mandate['musicianId']) ||
          !isset($mandate['projectId']) ||
          $mandate['mandateReference'] != $ref ||
          $mandate['musicianId'] != $mus ||
          $mandate['projectId'] != $prj) {
        return false;
      }
      // passed: issue an update query
      foreach ($newMandate as $key => $value) {
        if (empty($value) && $key != 'lastUsedDate') {
          unset($newMandate[$key]);
          $value = null;
        }
        $mandate[$key] = $value; // @todo check date and time-stamps.
      }
    } else {
      $mandate = Entities\SepaDebitMandate::create();
      foreach ($newMandate as $key => $value) {
        $mandate[$key] = $value; // @todo check date and time-stamps.
      }
      $this->persist($mandate);
    }
    $this->flush($mandate); // persist
  }

  /**Compute usage data for the given mandate reference*/
  public function mandateReferenceUsage($reference, $brief = false)
  {
    return $this->mandateRepository->usage($reference, $brief);
  }

  /**
   * Determine if the given mandate is expired, in which case we
   * would need a new mandate.
   *
   * @param mixed $usageInfo Either a mandate-reference or a
   * previously fetched result from $this->mandateReferenceUsage()
   *
   * @return @c true iff the mandate is expired, @c false otherwise.
   */
  public function mandateIsExpired($usageInfo)
  {
    $mandate = $usageInfo;
    if (empty($usageInfo['lastUsed'])) {
      $usageInfo = $this->mandateReferenceUsage($usageInfo, true);
    }
    if (empty($usageInfo['lastUsed'])) {
      $usageInfo['LastUsed'] = $usageInfo['mandateIssued'];
    }
    $oldLocale = setlocale(LC_TIME, '0');
    setlocale(LC_TIME, Util::getLocale());

    $oldTZ = date_default_timezone_get();
    $tz = Util::getTimezone();
    date_default_timezone_set($tz);

    $nowDate  = new \DateTime(strftime('%Y-%m-%d'));
    $usedDate = new \DateTime($usageInfo['lastUsed']); // lastUsed may already be a DateTime object

    $diff = $usedDate->diff($nowDate);
    $months = $diff->format('%y') * 12 + $diff->format('%m');

    date_default_timezone_set($oldTZ);
    setlocale(LC_TIME, $oldLocale);

    return $months >= $this->SEPA_MANDATE_EXPIRE_MONTHS;
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

  /**Erase a SEPA-mandate. This is important data, so we require the
   * project and musician as well as the mandate reference.
   *
   * @param string $mandateReference The mandate reference string.
   */
  public function deleteSepaMandate($mandateReference)
  {
    return !empty($this->mandatesRepository->remove($mandateReference));
  }

  /**Verify the given mandate, throw an
   * InvalidArgumentException. The sequence type may or may not
   * already have been converted to the actual type based on the
   * lastUsedDate.
   */
  public function validateSepaMandate($mandate)
  {
    $nl = "\n";
    $keys = [
      'mandateReference',
      'mandateDate',
      /*'lastUsedDate', may be null */
                  'musicianId',
      'projectId',
      'sequenceType',
      'IBAN',
      'BLZ',
      'bankAccountOwner',
    ];
    $names = [
      'mandateReference' => $this->l->t('mandate reference'),
      'mandateDate' => $this->l->t('date of issue'),
      'lastUsedDate' => $this->l->t('date of last usage'),
      'musicianId' => $this->l->t('musician id'),
      'projectId' => $this->l->t('project id'),
      'sequenceType' => $this->l->t('sequence type'),
      'IBAN' => 'IBAN',
      'BLZ' => $this->l->t('bank code'),
      'bankAccountOwner' => $this->l->t('bank account owner'),
    ];

    ////////////////////////////////////////////////////////////////
    //
    // Compat hack: we store "nonrecurring", but later want to have
    // "sequenceType". Also, the sequence type still may be simply
    // 'permanent'.
    if (!isset($mandate['sequenceType'])) {
      $mandate['sequenceType'] = $this->sepaMandateSequenceType($mandate);
    }
    //
    ////////////////////////////////////////////////////////////////

    foreach($keys as $key) {
      if (!isset($mandate[$key])) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Missing fields in debit mandate: %s (%s).', [ $key, $names[$key], ]).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
      }
      if ((string)$mandate[$key] == '') {
        if ($key == 'lastUsedDate') {
          continue;
        }
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Empty fields in debit mandate: %s (%s).', [ $key, $names[$key], ]).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));

      }
    }

    // Verify that bankAccountOwner conforms to the brain-damaged
    // SEPA charset. Thank you so much. Banks.
    if (!$this->validateSepaString($mandate['bankAccountOwner'])) {
      throw new \InvalidArgumentException(
        $nl.
        $this->l->t('Illegal characters in bank account owner field').
        $nl.
        $this->l->t('Full data record:').
        $nl.
        print_r($mandate, true));
    }

    // Verify that the dates are not in the future, and that the
    // mandateDate is set (last used maybe 0)
    //
    // lastUsedDate should be the date of the actual debit, so it
    // can very well refer to a transaction in the future.
    foreach([ 'mandateDate'/*, 'lastUsedDate',*/ ] as $dateKey) {
      $date = $mandate[$dateKey];
      if ($date == '0000-00-00' || $date == '1970-01-01') {
        continue;
      }
      $stamp = strtotime($date);
      $now = time();
      if ($now < $stamp) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Mandate issued in the future: %s????', $date).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
      }
    }
    $dateIssued = $mandate['mandateDate'];
    if ($dateIssued == '0000-00-00') {
      throw new \InvalidArgumentException(
        $nl.
        $this->l->t('Missing mandate date').
        $nl.
        $nl.
        $this->l->t('Full data record:').
        $nl.
        print_r($mandate, true));
    }

    $sequenceType = $this->sepaMandateSequenceType($mandate);
    $allowedTypes = [ 'once', 'permanent', 'first', 'following', ];
    if (array_search($sequenceType, $allowedTypes) === false) {
      throw new \InvalidArgumentException(
        $nl.
        $this->l->t("Invalid sequence type `%s', should be one of %s",
                    [ $sequenceType, implode(',', $allowedTypes), ]).
        $nl.
        $nl.
        $this->l->t('Full data record:').
        $nl.
        print_r($mandate, true));
    }

    // Check IBAN and BIC: extract the bank and bank account id,
    // check both with BAV, regenerate the BIC
    $IBAN = $mandate['IBAN'];
    $BLZ  = $mandate['BLZ'];
    $BIC  = $mandate['BIC'];

    $iban = new \IBAN($IBAN);
    if (!$iban->Verify()) {
      throw new \InvalidArgumentException(
        $nl.
        $this->l->t('Invalid IBAN: %s', $IBAN).
        $nl.
        $this->l->t('Full data record:').
        $nl.
        print_r($mandate, true));
    }

    if ($iban->Country() == 'DE') {
      // otherwise: not implemented yet
      $ibanBLZ = $iban->Bank();
      $ibanKTO = $iban->Account();

      if ($BLZ != $ibanBLZ) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('BLZ and IBAN do not match: %s != %s', [ $BLZ, $ibanBLZ, ]).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
      }

      $bav = new \malkusch\bav\BAV;

      if (!$bav->isValidBank($ibanBLZ)) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Invalid German BLZ: %s.', $BLZ).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
      }

      if (!$bav->isValidAccount($ibanKTO)) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Invalid German bank account: %s @ %s.', [ $ibanKTO, $BLZ, ]).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
      }

      $blzBIC = $bav->getMainAgency($ibanBLZ)->getBIC();
      if ($blzBIC != $BIC) {
        throw new \InvalidArgumentException(
          $nl.
          $this->l->t('Probably invalid BIC: %s. Computed: %s. ', [ $BIC, $blzBIC, ]).
          $nl.
          $this->l->t('Full data record:').
          $nl.
          print_r($mandate, true));
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
           . strval( ord( $iban{0} )-55 )
           . strval( ord( $iban{1} )-55 )
           . substr( $iban, 2, 2 );

    for( $i = 0; $i < strlen($iban1); $i++) {
      if(ord( $iban1{$i} )>64 && ord( $iban1{$i} )<91) {
        $iban1 = substr($iban1,0,$i) . strval( ord( $iban1{$i} )-55 ) . substr($iban1,$i+1);
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

  /**Take a locale-formatted string and parse it into a float.
   */
  public function parseCurrency($value, $noThrow = false)
  {
    $value = trim($value);
    if (strstr($value, '€') !== false) {
      $currencyCode = '€';
    } else if (strstr($value, '$') !== false) {
      $currencyCode = '$';
    } else if (is_numeric($value)) {
      // just return e.g. if C-locale is active
      $currencyCode = '';
    } else if ($noThrow) {
      return false;
    } else {
      throw new \InvalidArgumentException(
        $this->l->t("Cannot parse `%s' Only plain number, € and \$ currencies are supported.",
                    (string)$value));
    }

    switch ($currencyCode) {
    case '€':
      // Allow either comma or decimal point as separator
      if (preg_match('/^(€)? *([+-]?) *(\d{1,3}(\.\d{3})*|(\d+))(\,(\d{2}))? *(€)?$/',
                     $value, $matches)
          ||
          preg_match('/^(€)? *([+-]?) *(\d{1,3}(\,\d{3})*|(\d+))(\.(\d{2}))? *(€)?$/',
                     $value, $matches)) {
        //print_r($matches);
        // Convert value to number
        //
        // $matches[2] optional sign
        // $matches[3] integral part
        // $matches[7] fractional part
        $fraction = isset($matches[7]) ? $matches[7] : '00';
        $value = (float)($matches[2].str_replace([',', '.', ], '', $matches[3]).'.'.$fraction);
      } else if ($noThrow) {
        return false;
      } else {
        throw new \InvalidArgumentException($this->l->t("Cannot parse number string `%s'", (string)$value));
      }
      break;
    case '$':
      if (preg_match('/^\$? *([+-])? *(\d{1,3}(\,\d{3})*|(\d+))(\.(\d{2}))? *\$?$/', $value, $matches)) {
        // Convert value to number
        //
        // $matches[1] optional sign
        // $matches[2] integral part
        // $matches[6] fractional part
        $fraction = isset($matches[6]) ? $matches[6] : '00';
        $value = (float)($matches[1].str_replace([ ',', '.', ], '', $matches[2]).'.'.$fraction);
      } else if ($noThrow) {
        return false;
      } else {
        throw new \InvalidArgumentException($this->l->t("Cannot parse number string `%s'", (string)$value));
      }
      break;
    }
    return [
      'amount' => $value,
      'currency' => $currencyCode,
    ];
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
