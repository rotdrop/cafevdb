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

    /**Add an event to the finance calendar, possibly including a
     * reminder.
     *
     * @param title
     * @param description (may be empty)
     * @param projectName (may be empty)
     * @param timeStamp
     * @param alarm (maybe <= 0 for no alarm)
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
     * @param title
     * @param description (may be empty)
     * @param projectName (may be empty)
     * @param timeStamp
     * @param alarm (maybe <= 0 for no alarm)
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
      $oldLocale = setlocale(LC_ALL, 'de_DE.UTF8');
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
     * XXXX-YYYY-IN-PROJ-YEAR
     *
     * where XXXX is the project Id, YYYY the musician ID, NAME and PROJ
     * each are the first four letters of the respective name in order
     * to make the reference a little bit more readable. YEAR is only
     * added if the project name carries a year.
     */
    public static function generateSepaMandateReference($projectId, $musicianId, $handle = false) 
    {
      $musicianName = Musicians::fetchName($musicianId, $handle);
      $projectName = Projects::fetchName($projectId, $handle);    

      $musicianName['firstName'] .= 'X';
      $musicianName['lastName'] .= 'X';
      $initials = $musicianName['firstName'][0].$musicianName['lastName'][0];
      $prjId = substr("0000".$projectId, -4);
      $musId = substr("0000".$musicianId, -4);

      $ref = $prjId.'-'.$musId.'-'.$initials.'-';

      $year = substr($projectName, -4);
      if (is_numeric($year)) {
        $projectName = substr($projectName, 0, -4);
        $ref = substr($ref.$projectName, 0, 30).$year;
      } else {
        $ref = substr($ref.$projectName, 0, 34);
      }

      $ref = preg_replace('/\s+/', 'X', $ref); // replace space by X

      return strtoupper($ref);
    }

    /**Fetch an exisiting reference given project and musician. This
     * fetch the entire db-row, i.e. everything known about the mandate.
     */
    public static function fetchSepaMandate($projectId, $musicianId, $handle = false)
    {
      $mandate = false;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "SELECT * FROM `".self::$dataBaseInfo['table']."` WHERE ".
        "`projectId` = $projectId AND `musicianId` = $musicianId";
      $result = mySQL::query($query, $handle);
      if ($result !== false && mysql_num_rows($result) == 1) {
        $row = mySQL::fetch($result);
        if ($row['mandateReference']) {
          $mandate = $row;
        }      
      }

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

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $mandate;
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

    /**Update the last used time-stamp for the given array if ids
     *
     * @param mixed $ids Either one specific id, or an array of ids or
     * an array of debit-notees as returned by
     * SepaDebitMandates::insuranceTableExport() or
     * SepaDebitMandates::projectTableExport()
     *
     * @param int $timeStamp Unix time stamp. Default to now + 14 days if unset.
     *
     * @param $handle Data-base handle. Will be aquired if unset.
     */
    public static function stampSepaMandates($ids, $timeStamp = false, $handle = false)
    {
      // convert to flat id array for all supported cases
      if (is_int($ids)) {
        $ids = array($ids);
      } else if (is_array($ids)) {
        if (count($ids) == 0) {
          return true;
        }
        if (is_array($ids[0])) {
          $notes = $ids;
          $ids = array();
          foreach ($notes as $debitNote) {
            $ids[] = $debitNote['id'];
          }
        }
      }

      if ($timeStamp === false) {
        date_default_timezone_set(Util::getTimezone());
        $timeStamp = strtotime('+ 14 days');
      }

      $dateIssued = date('Y-m-d', $timeStamp);
      
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $table = Finance::$dataBaseInfo['table'];
      $idSet = '('.implode(',', $ids).')';
      $query = "UPDATE `".$table."` SET `lastUsedDate` = '".$dateIssued."' WHERE `id` IN ".$idSet;
      
      $result = mySQL::query($query, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      if (!$result) {
        throw new \RuntimeException(
          "\n".
          L::t('Unable to update the last-used date to %s', array($dateIssued)).
          "\n".
          L::t('Data-base query:').
          "\n".
          $query);
      }

      return $result;
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
        if (isset($mandate[$date]) && $mandate[$date] != '') {
          $stamp = strtotime($mandate[$date]);
          $value = date('Y-m-d', $stamp);
          if ($stamp != strtotime($value)) {
            return false;
          }
          $mandate[$date] = $value;
        }
      }

      if (self::$useEncryption) {
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

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $table = self::$dataBaseInfo['table'];

      $oldMandate = self::fetchSepaMandate($prj, $mus, $handle);
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
        $query = "UPDATE `".$table."` SET ";
        $setter = array();
        foreach ($mandate as $key => $value) {
          if ($key == 'lastUsedDate' || $value != '') {
            // only store non-empty fields, with the exception of
            // last-used which we may want to reset in case we did not
            // really submit the debit note.
            $setter[] = "`".$key."`='".$value."'";
          }
        }
        $query .= implode(", ", $setter);
        $query .= " WHERE `mandateReference` = '".$ref."'";
      } else {
        // insert query
        $query = "INSERT INTO `".$table."` ";
        $query .= "(`".implode("`,`",array_keys($mandate))."`) ";
        $query .= " VALUES ";
        $query .= "('".implode("','",array_values($mandate))."') ";
      }

      $result = mySQL::query($query, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    /**Erase a SEPA-mandate. */
    public static function deleteSepaMandate($projectId, $musicianId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "DELETE FROM `SepaDebitMandates` WHERE `projectId` = $projectId AND `musicianId` = $musicianId";
      mySQL::query($query, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true; // hopefully
    }

    /**Verify the given mandate, throw an InvalidArgumentException */
    public static function validateSepaMandate($mandate)
    {
      $nl = "\n";
      $keys = array('mandateReference',
                    'mandateDate',
                    'lastUsedDate',
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
        if ($key == 'lastUsedDate') {
          continue;
        }
        if ((string)$mandate[$key] == '') {
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
            L::t('Full data record: %s').
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
  
  };

} // namespace CAFEVDB

?>
