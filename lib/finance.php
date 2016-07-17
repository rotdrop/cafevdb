<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /**Finance and bank related stuff. */
  class Finance
  {
    private static $useEncryption = true;
    public static $dataBaseInfo =
      array('table' => 'SepaDebitMandates',
            'key' => 'id',
            'encryptedColumns' => array('IBAN', 'BIC', 'BLZ', 'bankAccountOwner'));
    public static $sepaCharset = "a-zA-Z0-9 \/?:().,'+-";
    public static $sepaPurposeLength = 35;
    public static $sepaMandateLength = 35; ///< Maximum length of mandate reference.
    const SEPA_MANDATE_EXPIRE_MONTHS = 36; ///< Unused mandates expire after this time.

    /**Add an event to the finance calendar, possibly including a
     * reminder.
     *
     * @param $title
     * @param $description (may be empty)
     * @param $projectName (may be empty)
     * @param $timeStamp
     * @param $alarm (maybe <= 0 for no alarm)
     */
    static public function financeEvent($title, $description, $projectName, $timeStamp, $alarm = false)
    {
      $eventKind = 'finance';
      $categories = '';
      if ($projectName) {
        // This triggers adding the event to the respective project when added
        $categories .= $projectName.',';
      }
      $categories .= L::t('finance');
      $calKey       = $eventKind.'calendar';
      $calendarName = Config::getSetting($calKey, L::t($eventKind));
      $calendarId   = Config::getSetting($calKey.'id', false);

      $eventData = array('title' => $title,
                         'from' => date('d-m-Y', $timeStamp),
                         'to' => date('d-m-Y', $timeStamp),
                         'allday' => 'on',
                         'location' => 'Cyber-Space',
                         'categories' => $categories,
                         'description' => $description,
                         'repeat' => 'doesnotrepeat',
                         'calendar' => $calendarId,
                         'alarm' => $alarm);

      return Events::newEvent($eventData);
    }

    /**Add a task to the finance calendar, possibly including a
     * reminder.
     *
     * @param $title
     * @param $description (may be empty)
     * @param $projectName (may be empty)
     * @param $timeStamp
     * @param $alarm (maybe <= 0 for no alarm)
     */
    static public function financeTask($title, $description, $projectName, $timeStamp, $alarm = false)
    {
      $taskKind = 'finance';
      $categories = '';
      if ($projectName) {
        // This triggers adding the task to the respective project when added
        $categories .= $projectName.',';
      }
      $categories .= L::t('finance');
      $calKey       = $taskKind.'calendar';
      $calendarName = Config::getSetting($calKey, L::t($taskKind));
      $calendarId   = Config::getSetting($calKey.'id', false);

      $taskData = array('title' => $title,
                         'due' => date('d-m-Y', $timeStamp),
                         'start' => date('d-m-Y'),
                         'location' => 'Cyber-Space',
                         'categories' => $categories,
                         'description' => $description,
                         'calendar' => $calendarId,
                         'priority' => 99, // will get a star if != 0
                         'alarm' => $alarm);

      return Events::newTask($taskData);
    }

    /**Convert an UTF-8 encoded string to the brain-damaged SEPA
     * requirements. Thank you so much, you idiots. Banks.
     */
    public static function sepaTranslit($string)
    {
      $oldLocale = setlocale(LC_ALL, '0');
      setlocale(LC_ALL, 'de_DE.UTF8');
      $result = iconv("utf-8","ascii//TRANSLIT", $string);
      setlocale(LC_ALL, $oldLocale);
      return $result;
    }

    /**Validate whether the given string conforms to the brain-damaged
     * SEPA requirements. Thank you so much, you idiots. Banks.
     */
    public static function validateSepaString($string)
    {
      return !preg_match('@[^'.self::$sepaCharset.']@', $string);
    }


    /**The "SEPA mandat reference" must be unique per mandat, consist
     * more or less of alpha-numeric characters and has a maximum length
     * of 35 characters. We choose the format
     *
     * XXXX-YYYY-IN-PROJECTYEAR
     *
     * where XXXX is the project Id, YYYY the musician ID, PROJECT and
     * YEAR are the project name and the year. The project name will
     * be shortened s.t. the entire reference fits into 35 characters.
     *
     * Mandates expired after 36 months if not used, and if the bank
     * account information changes then we also need a new mandate
     * reference. If this should happen for the same project (i.e. the
     * club-member pseudo-project) then we attach a sequence number at
     * the end. If necessary, the project name will shortened further
     * for this purpose. Sequence numbers are only present if necessary:
     *
     * XXXX-YYYY-IN-PROJECTYEAR+SEQ
     */
    public static function generateSepaMandateReference($projectId,
                                                        $musicianId,
                                                        $sequence = false,
                                                        $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // fetch latest relevant mandate and possibly increase the sequence.
      $query = "SELECT mandateReference FROM `SepaDebitMandates`
  WHERE `projectId` = $projectId AND `musicianId` = $musicianId
  ORDER BY id DESC
  LIMIT 0,1";
      $result = mySQL::query($query, $handle);
      if ($result !== false && mySQL::numRows($result) === 1) {
        $row = mySQL::fetch($result);
        if ($row['mandateReference']) {
          $seq = substr($row['mandateReference'], -3);
          if ($seq[0] === '+') {
            $sequence = intval($seq)+1;
          } else {
            $sequence = 1;
          }
        }
      }

      $musicianName = Musicians::fetchName($musicianId, $handle);
      $projectName = Projects::fetchName($projectId, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      $projectName = self::sepaTranslit($projectName);
      $firstName = self::sepaTranslit($musicianName['firstName']);
      $lastName = self::sepaTranslit($musicianName['lastName']);

      $firstName .= 'X';
      $lastName .= 'X';
      $initials = $firstName[0].$lastName[0];

      $prjId = substr("0000".$projectId, -4);
      $musId = substr("0000".$musicianId, -4);

      $ref = $prjId.'-'.$musId.'-'.$initials.'-';

      if (is_numeric($sequence) && $sequence > 0) {
        $tail = '+'.sprintf("%02d", intval($sequence));
      } else {
        $tail = '';
      }

      $year = substr($projectName, -4);
      if (is_numeric($year)) {
        $projectName = substr($projectName, 0, -4);
        $tail = $year.$tail;
      }
      $tailLength = strlen($tail);
      $trimLength = self::$sepaMandateLength - strlen($tail);
      $ref = substr($ref.$projectName, 0, $trimLength).$tail;

      $ref = preg_replace('/\s+/', 'X', $ref); // replace space by X

      return strtoupper($ref);
    }

    /**Fetch an exisiting reference given project and musician. This
     * fetch the entire db-row, i.e. everything known about the
     * mandate. Expired and inactive mandates are ignored, i.e. false
     * is returned in this case.
     */
    public static function fetchSepaMandate($projectId, $musicianId, $handle = false, $cooked = true, $expired = false)
    {
      $mandate = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT * FROM `".self::$dataBaseInfo['table']."`
WHERE
  `projectId` = $projectId
  AND
  `musicianId` = $musicianId
  AND
  `active` = 1";

      $result = mySQL::query($query, $handle);
      if ($result !== false && mySQL::numRows($result) == 1) {
        $row = mySQL::fetch($result);
        if ($row['mandateReference']) {
          $mandate = $row;
        }
      }

      if (!$expired && self::mandateIsExpired($mandate['mandateReference'], $handle)) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
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

        if (!self::decryptSepaMandate($mandate)) {
          $mandate = false;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $mandate;
    }

    /**Set the sequence type based on the last-used date and the
     * recurring/non-recurring status.
     */
    public static function sepaMandateSequenceType($mandate)
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
    public static function encryptSepaMandate(&$mandate)
    {
      if (is_array($mandate) && self::$useEncryption) {
        $enckey = Config::getEncryptionKey();
        foreach (self::$dataBaseInfo['encryptedColumns'] as $column) {
          if (isset($mandate[$column])) {
            $value = Config::encrypt($mandate[$column], $enckey);
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
    public static function decryptSepaMandate(&$mandate)
    {
      if (is_array($mandate) && self::$useEncryption) {
        $enckey = Config::getEncryptionKey();
        foreach (self::$dataBaseInfo['encryptedColumns'] as $column) {
          $value = Config::decrypt($mandate[$column], $enckey);
          if ($value === false) {
            return false;
          }
          $mandate[$column] = $value;
        }
      }
      return true;
    }

    /**Store a SEPA-mandate, possibly with only partial
     * information. mandateReference, musicianId and projectId are
     * required.
     */
    public static function storeSepaMandate($mandate, $handle = false)
    {
      $result = false;

      if (!is_array($mandate) ||
          !isset($mandate['mandateReference']) ||
          !isset($mandate['musicianId']) ||
          !isset($mandate['projectId'])) {
        return false;
      }

      if (isset($mandate['sequenceType'])) {
        $sequenceType = $mandate['sequenceType'];
        $mandate['nonrecurring'] = $sequenceType == 'once';
        unset($mandate['sequenceType']);
      }

      $ref = $mandate['mandateReference'];
      $mus = $mandate['musicianId'];
      $prj = $mandate['projectId'];

      // Convert to a date format understood by mySQL.
      $dateFields = array('lastUsedDate', 'mandateDate');
      foreach ($dateFields as $date) {
        if (!empty($mandate[$date])) {
          $stamp = strtotime($mandate[$date]);
          $value = date('Y-m-d', $stamp);
          if ($stamp != strtotime($value)) {
            return false;
          }
          $mandate[$date] = $value;
        } else {
          unset($mandate[$date]);
        }
      }

      self::encryptSepaMandate($mandate);

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $table = self::$dataBaseInfo['table'];

      // fetch the old mandate, but keep the old values encrypted
      $oldMandate = self::fetchSepaMandate($prj, $mus, $handle, false);
      if ($oldMandate) {
        // Sanity checks
        if (!is_array($oldMandate) ||
            !isset($oldMandate['mandateReference']) ||
            !isset($oldMandate['musicianId']) ||
            !isset($oldMandate['projectId']) ||
            $oldMandate['mandateReference'] != $ref ||
            $oldMandate['musicianId'] != $mus ||
            $oldMandate['projectId'] != $prj) {
          return false;
        }
        // passed: issue an update query
        foreach ($mandate as $key => $value) {
          if (empty($value) && $key != 'lastUsedDate') {
            unset($mandate[$key]);
          }
        }
        $where = "`mandateReference` = '".$ref."'";
        $result = mySQL::update($table, $where, $mandate, $handle);
        if ($result !== false) {
          mySQL::logUpdate($table, 'id', $oldMandate, $mandate, $handle);
        }
      } else {
        // insert query
        $result = mySQL::insert($table, $mandate, $handle);
        if ($result !== false) {
          mySQL::logInsert($table, mySQL::newestIndex($handle), $mandate, $handle);
        }

      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Compute usage data for the given mandate reference*/
    static public function mandateReferenceUsage($reference, $brief = false, $handle = false)
    {
      $result = false;

      $ownConnection = $handle === false;

      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT
  m.mandateReference AS MandateReference,
  m.Active,
  GREATEST(COALESCE(MAX(d.DueDate), ''), COALESCE(m.lastUsedDate, '')) AS LastUsed,
  m.mandateDate AS MandateIssued";
      if ($brief !== false) {
        $query .= ",
  m.lastUsedDate AS MandateLastUsed,
  MAX(d.DateIssued) AS DebitNoteLastIssued,
  MAX(d.SubmitDate) AS DebitNoteLastSubmitted,
  MAX(d.DueDate) AS DebitNoteLastDue,
  IF(p.DateOfReceipt = MAX(d.DueDate), p.DebitMessageId, NULL) AS DebitNoteLastNotified";
      }
      $query .= "
FROM SepaDebitMandates m
LEFT JOIN ProjectPayments p
  ON p.MandateReference = m.mandateReference
LEFT JOIN DebitNotes d
  ON d.Id = p.DebitNoteId";
      $query .= "
WHERE m.mandateReference = '".$reference."'
GROUP BY m.mandateReference";

      $result = mySQL::query($query, $handle);
      if ($result === false || mySQL::numRows($result) !== 1) {
        return false;
      }
      $result = mySQL::fetch($result);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Determine if the given mandate is expired, in which case we
     * would need a new mandate.
     *
     * @param[in] mixed $usageInfo Either a mandate-reference or a
     * previously fetched result from self::mandateReferenceUsage()
     *
     * @return @c true iff the mandate is expired, @c false otherwise.
     */
    public static function mandateIsExpired($usageInfo, $handle = false)
    {
      $mandate = $usageInfo;
      if (empty($usageInfo['LastUsed'])) {
        $usageInfo = self::mandateReferenceUsage($usageInfo, true, $handle);
      }
      if (empty($usageInfo['LastUsed'])) {
        $usageInfo['LastUsed'] = $usageInfo['MandateIssued'];
      }
      $oldLocale = setlocale(LC_TIME, '0');
      setlocale(LC_TIME, Util::getLocale());

      $oldTZ = date_default_timezone_get();
      $tz = Util::getTimezone();
      date_default_timezone_set($tz);

      $nowDate  = new \DateTime(strftime('%Y-%m-%d'));
      $usedDate = new \DateTime($usageInfo['LastUsed']);

      $diff = $usedDate->diff($nowDate);
      $months = $diff->format('%y') * 12 + $diff->format('%m');

      date_default_timezone_set($oldTZ);
      setlocale(LC_TIME, $oldLocale);

      return $months >= self::SEPA_MANDATE_EXPIRE_MONTHS;
    }

    /**Deactivate a SEPA-mandate (timeout, withdrawn, erroneous data
     * etc.). This flags the mandate as deleted, but we have to keep
     * the data for the book-keeping.
     *
     * @param[in] string $mandateReference The mandate reference string.
     *
     * @param[in] mixed $handle Optional data-base handle.
     *
     */
    public static function deactivateSepaMandate($mandateReference, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $table = 'SepaDebitMandates';
      $where = "`mandateReference` = '$mandateReference'";
      $oldValues = mySQL::fetchRows($table, $where, null, $handle);

      $newValues = array('active' => 0);
      $result = mySQL::update($table, $where, $newValues, $handle);

      if ($result !== false) {
        mySQL::logUpdate($table, 'mandateReference',
                         array('mandateReference' => $mandateReference),
                         $newValues, $handle);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true; // hopefully
    }

    /**Erase a SEPA-mandate. This is important data, so we require the
     * project and musician as well as the mandate reference.
     *
     * @param[in] string $mandateReference The mandate reference string.
     *
     * @param[in] mixed $handle Optional data-base handle.
     *
     */
    public static function deleteSepaMandate($mandateReference, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $usage = self::mandateReferenceUsage($mandateReference, true, $handle);

      if (!empty($usage['LastUsed'])) {
        $result = self::deactivateSepaMandate($mandateReference, $handle);
      } else {

        $table = 'SepaDebitMandates';
        $where = "`mandateReference` = '$mandateReference'";
        $oldValues = mySQL::fetchRows($table, $where, null, $handle);

        $query = "DELETE FROM `".$table."` WHERE ".$where;
        $result = mySQL::query($query, $handle);

        if ($result !== false && count($oldValues) > 0) {
          mySQL::logDelete($table, 'id', $oldValues[0], $handle);
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result !== false; // hopefully
    }

    /**Verify the given mandate, throw an
     * InvalidArgumentException. The sequence type may or may not
     * already have been converted to the actual type based on the
     * lastUsedDate.
     */
    public static function validateSepaMandate($mandate)
    {
      $nl = "\n";
      $keys = array('mandateReference',
                    'mandateDate',
                    /*'lastUsedDate', may be null */
                    'musicianId',
                    'projectId',
                    'sequenceType',
                    'IBAN',
                    'BLZ',
                    'bankAccountOwner');
      $names = array('mandateReference' => L::t('mandate reference'),
                     'mandateDate' => L::t('date of issue'),
                     'lastUsedDate' => L::t('date of last usage'),
                     'musicianId' => L::t('musician id'),
                     'projectId' => L::t('project id'),
                     'sequenceType' => L::t('sequence type'),
                     'IBAN' => 'IBAN',
                     'BLZ' => L::t('bank code'),
                     'bankAccountOwner' => L::t('bank account owner'));

      ////////////////////////////////////////////////////////////////
      //
      // Compat hack: we store "nonrecurring", but later want to have
      // "sequenceType". Also, the sequence type still may be simply
      // 'permanent'.
      if (!isset($mandate['sequenceType'])) {
        $mandate['sequenceType'] = self::sepaMandateSequenceType($mandate);
      }
      //
      ////////////////////////////////////////////////////////////////

      foreach($keys as $key) {
        if (!isset($mandate[$key])) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Missing fields in debit mandate: %s (%s).', array($key, $names[$key])).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));
        }
        if ((string)$mandate[$key] == '') {
          if ($key == 'lastUsedDate') {
            continue;
          }
          throw new \InvalidArgumentException(
            $nl.
            L::t('Empty fields in debit mandate: %s (%s).', array($key, $names[$key])).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));

        }
      }

      // Verify that bankAccountOwner conforms to the brain-damaged
      // SEPA charset. Thank you so much. Banks.
      if (!self::validateSepaString($mandate['bankAccountOwner'])) {
        throw new \InvalidArgumentException(
          $nl.
          L::t('Illegal characters in bank account owner field').
          $nl.
          L::t('Full data record:').
          $nl.
          print_r($mandate, true));
      }

      // Verify that the dates are not in the future, and that the
      // mandateDate is set (last used maybe 0)
      //
      // lastUsedDate should be the date of the actual debit, so it
      // can very well refer to a transaction in the future.
      foreach(array('mandateDate'/*, 'lastUsedDate'*/) as $dateKey) {
        $date = $mandate[$dateKey];
        if ($date == '0000-00-00' || $date == '1970-01-01') {
          continue;
        }
        $stamp = strtotime($date);
        $now = time();
        if ($now < $stamp) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Mandate issued in the future: %s????', array($date)).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));
        }
      }
      $dateIssued = $mandate['mandateDate'];
      if ($dateIssued == '0000-00-00') {
        throw new \InvalidArgumentException(
          $nl.
          L::t('Missing mandate date').
          $nl.
          $nl.
          L::t('Full data record:').
          $nl.
          print_r($mandate, true));
      }

      $sequenceType = self::sepaMandateSequenceType($mandate);
      $allowedTypes = array('once', 'permanent', 'first', 'following');
      if (array_search($sequenceType, $allowedTypes) === false) {
        throw new \InvalidArgumentException(
          $nl.
          L::t("Invalid sequence type `%s', should be one of %s",
               array($sequenceType, implode(',', $allowedTypes))).
          $nl.
          $nl.
          L::t('Full data record:').
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
          L::t('Invalid IBAN: %s', array($IBAN)).
          $nl.
          L::t('Full data record:').
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
            L::t('BLZ and IBAN do not match: %s != %s', array($BLZ, $ibanBLZ)).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));
        }

        $bav = new \malkusch\bav\BAV;

        if (!$bav->isValidBank($ibanBLZ)) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Invalid German BLZ: %s.', array($BLZ)).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));
        }

        if (!$bav->isValidAccount($ibanKTO)) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Invalid German bank account: %s @ %s.', array($ibanKTO, $BLZ)).
            $nl.
            L::t('Full data record:').
            $nl.
            print_r($mandate, true));
        }

        $blzBIC = $bav->getMainAgency($ibanBLZ)->getBIC();
        if ($blzBIC != $BIC) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Probably invalid BIC: %s. Computed: %s. ', array($BIC, $blzBIC)).
            $nl.
            L::t('Full data record:').
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
    public static function testIBAN( $iban ) {
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

    public static function testCI($ci)
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
      return self::testIBAN($fakeIBAN);
    }

    /********************************************************
     * Funktion zur Erstellung einer IBAN aus BLZ+Kontonr
     * Gilt nur fuer deutsche Konten
     ********************************************************/
    public static function makeIBAN($blz, $kontonr) {
      $blz8 = str_pad ( $blz, 8, "0", STR_PAD_RIGHT);
      $kontonr10 = str_pad ( $kontonr, 10, "0", STR_PAD_LEFT);
      $bban = $blz8 . $kontonr10;
      $pruefsumme = $bban . "131400";
      $modulo = (bcmod($pruefsumme,"97"));
      $pruefziffer =str_pad ( 98 - $modulo, 2, "0",STR_PAD_LEFT);
      $iban = "DE" . $pruefziffer . $bban;
      return $iban;
    }

    public static function validateSWIFT($swift)
    {
      return preg_match('/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/i', $swift);
    }

    /**Take a locale-formatted string and parse it into a float.
     */
    public static function parseCurrency($value, $noThrow = false)
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
          L::t("Cannot parse `%s' Only plain number, € and \$ currencies are supported.",
               array((string)$value)));
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
          $value = (float)($matches[2].str_replace(array(',', '.'), '', $matches[3]).'.'.$fraction);
        } else if ($noThrow) {
          return false;
        } else {
          throw new \InvalidArgumentException(L::t("Cannot parse number string `%s'", array((string)$value)));
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
          $value = (float)($matches[1].str_replace(array(',', '.'), '', $matches[2]).'.'.$fraction);
        } else if ($noThrow) {
          return false;
        } else {
          throw new \InvalidArgumentException(L::t("Cannot parse number string `%s'", array((string)$value)));
        }
        break;
      }
      return array('amount' => $value,
                   'currency' => $currencyCode);
    }

  };

} // namespace CAFEVDB

?>
