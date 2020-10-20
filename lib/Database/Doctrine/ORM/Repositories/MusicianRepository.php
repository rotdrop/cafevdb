<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class MusicianRepository extends EntityRepository
{
  public function __construct


  public function findByName(string $firstName, string $surName)
  {
    return $this->findBy(
      [ 'vorname' => $firstName, 'name' => $surName ],
      [ 'name' => 'ASC', 'vorname' => 'ASC' ]
    );
  }

//   /**Fetch all known data from the Musiker table for the respective musician.  */
//   public static function fetchMusicianById($musicianId, $handle = false)
//   {
//     $ownConnection = $handle === false;

//     if ($ownConnection) {
//       Config::init();
//       $handle = mySQL::connect(Config::$pmeopts);
//     }

//     $query = "SELECT
//  m.*,
//  GROUP_CONCAT(DISTINCT mi.`InstrumentId` ORDER BY i.`Sortierung`) AS InstrumentIds,
//  GROUP_CONCAT(DISTINCT i.`Instrument` ORDER BY i.`Sortierung`) AS Instruments
// FROM `".self::TABLE."` AS m
// LEFT JOIN `MusicianInstruments` mi
//   ON m.`Id` = mi.`MusicianId`
// LEFT JOIN Instrumente i
//   ON i.`Id` = mi.`InstrumentId`
// WHERE m.`Id` = $musicianId
// GROUP BY m.`Id`
// ";

//     //throw new \Exception($query);

//     $result = mySQL::query($query, $handle);
//     if ($result !== false && mySQL::numRows($result) == 1) {
//       $row = mySQL::fetch($result);
//     } else {
//       $row = false;
//     }

//     if ($ownConnection) {
//       mySQL::close($handle);
//     }

//     return $row;
//   }

//   /**Fetch all known data from the Musiker table for the respective musician.  */
//   public static function fetchMusicianByUUID($musicianUUID, $handle = false)
//   {
//     $ownConnection = $handle === false;

//     if ($ownConnection) {
//       Config::init();
//       $handle = mySQL::connect(Config::$pmeopts);
//     }

//     $query = "SELECT
//  GROUP_CONCAT(DISTINCT mi.`InstrumentId` ORDER BY i.`Sortierung`) AS InstrumentIds,
//  GROUP_CONCAT(DISTINCT i.`Instrument` ORDER BY i.`Sortierung`) AS Instruments
// FROM `".self::TABLE."` AS m
// LEFT JOIN `MusicianInstruments` mi
//   ON m.`Id` = mi.`MusicianId`
// LEFT JOIN Instrumente i
//   ON i.`Id` = mi.`InstrumentId`
// WHERE `UUID` = '$musicianUUID'
// GROUP BY m.`Id`
// ";

//     $result = mySQL::query($query, $handle);
//     if ($result !== false && mySQL::numRows($result) == 1) {
//       $row = mySQL::fetch($result);
//     } else {
//       $row = false;
//     }

//     if ($ownConnection) {
//       mySQL::close($handle);
//     }

//     return $row;
//   }

//   /**Fetch the street address of the respected musician. Needed in
//    * order to generate automated snail-mails.
//    *
//    * Return value is a flat array:
//    *
//    * array('firstName' => ...,
//    *       'surName' => ...,
//    *       'street' => ...,
//    *       'city' => ...,
//    *       'ZIP' => ...);
//    */
//   public static function fetchStreetAddress($musicianId, $handle = false)
//   {
//     $ownConnection = $handle === false;
//     if ($ownConnection) {
//       Config::init();
//       $handle = mySQL::connect(Config::$pmeopts);
//     }

//     $query =
//       'SELECT '.
//       '`Name` AS `surName`'.
//       ', '.
//       '`Vorname` AS `firstName`'.
//       ', '.
//       '`Strasse` AS `street`'.
//       ', '.
//       '`Stadt` AS `city`'.
//       ', '.
//       '`Postleitzahl` AS `ZIP`'.
//       ', '.
//       '`FixedLinePhone` AS `phone`'.
//       ', '.
//       '`MobilePhone` AS `cellphone`';
//     $query .= ' FROM `'.self::TABLE.'` WHERE `Id` = '.$musicianId;

//     \OCP\Util::writeLog(Config::APP_NAME,
//                         __METHOD__.' Query: '.$query,
//                         \OCP\Util::DEBUG);

//       $result = mySQL::query($query, $handle);

//     $row = false;
//     if ($result !== false && mySQL::numRows($result) == 1) {
//       $row = mySQL::fetch($result);
//     }

//     if ($ownConnection) {
//       mySQL::close($handle);
//     }

//     return $row;
//   }

//   /** Fetch the musician-name name corresponding to $musicianId.
//    */
//   public static function fetchName($musicianId, $handle = false)
//   {
//     $ownConnection = $handle === false;
//     if ($ownConnection) {
//       Config::init();
//       $handle = mySQL::connect(Config::$pmeopts);
//     }

//     $query = 'SELECT `Name`,`Vorname`,`Email` FROM `'.self::TABLE.'` WHERE `Id` = '.$musicianId;
//     $result = mySQL::query($query, $handle);

//     $row = false;
//     if ($result !== false && mySQL::numRows($result) == 1) {
//       $row = mySQL::fetch($result);
//     }

//     if ($ownConnection) {
//       mySQL::close($handle);
//     }

//     return array('firstName' => (isset($row['Vorname']) && $row['Vorname'] != '') ? $row['Vorname'] : 'X',
//                  'lastName' => (isset($row['Name']) && $row['Name'] != '') ? $row['Name'] : 'X',
//                  'email' => (isset($row['Email']) && $row['Email'] != '') ? $row['Email'] : 'X');
//   }

  //  /**Add missing UUID field */

  // Rather a migration / import thing. UUIDs could be generated by
  // listening to events on entity creation.

 //  public static function ensureUUIDs($handle = false)
 //  {
 //    $ownConnection = $handle === false;
 //    if ($ownConnection) {
 //      Config::init();
 //      $handle = mySQL::connect(Config::$pmeopts);
 //    }

 //    $query = "SELECT `Id` FROM `".self::TABLE."` WHERE `UUID` IS NULL";
 //    $result = mySQL::query($query, $handle);

 //    $changed = 0;
 //    while ($row = mySQL::fetch($result)) {
 //      $query = "UPDATE `".self::TABLE."`
 // SET `UUID` = '".Util::generateUUID()."'
 // WHERE `Id` = ".$row['Id'];
 //      if (mySQL::query($query, $handle)) {
 //        ++$changed;
 //      }
 //    }

 //    if ($ownConnection) {
 //      mySQL::close($handle);
 //    }

 //    return $changed;
 //  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
