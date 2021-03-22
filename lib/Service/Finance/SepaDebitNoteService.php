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

class SepaDebitNoteService
{
  public function __construct(
    EntityManager
  ) {
  }

  // public static function removeDebitNote(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  // {
  //   if ($op !== 'delete') {
  //     return false;
  //   }

  //   if (empty($oldvals['Id'])) {
  //     return false;
  //   }

  //   if (!empty($oldvals['SubmitDate'])) {
  //     return false;
  //   }

  //   $debitNoteId = $oldvals['Id'];

  //   $result = true;

  //   // remove all associated payments
  //   $result = ProjectPayments::deleteDebitNotePayments($debitNoteId, $pme->dbh);

  //   // remove all the data (one item, probably)
  //   $result = self::deleteDebitNoteData($debitNoteId, $pme->dbh);

  //   try {
  //     // remove the associated OwnCloud events and task.
  //     $result = \OC_Calendar_Object::delete($oldvals['SubmissionEvent']);
  //   } catch (\Exception $e) {}

  //   try {
  //     $result = \OC_Calendar_Object::delete($oldvals['DueEvent']);
  //   } catch (\Exception $e) {}

  //   try {
  //     $result = Util::postToRoute('tasks.tasks.deleteTask',
  //                                 array('taskID' => $oldvals['SubmissionTask']));
  //   } catch (\Exception $e) {}

  //   return true;
  // }

  /** Delete the data associated to a given debit-note */
  private function deleteDebitNoteData($debitNoteId, $handle)
  {
    $rows = mySQL::fetchRows(
      self::DATA_TABLE, 'DebitNoteId = '.$debitNoteId, 'FileName ASC', $handle);
    $failed = 0;
    foreach($rows as $row) {
      // remove the associated data
      $query = "DELETE FROM `".self::DATA_TABLE."`
  WHERE `Id` = ".$row['Id']." AND `DebitNoteId` = ".$debitNoteId;
      if (mySQL::query($query, $handle) !== false) {
        mySQL::logDelete(self::DATA_TABLE, 'Id', $row, $handle);
      } else {
        ++$failed;
      }
    }
    return $failed === 0;
  }


  /** Fetch the debit-note data identified by the debit-note's id */
  public function debitNoteData($debitNoteId)
  {
    $rows = mySQL::fetchRows(
      self::DATA_TABLE, 'DebitNoteId = '.$debitNoteId, 'FileName ASC', $handle);

    $encKey = Config::getEncryptionKey();
    foreach($rows as &$row) {
      $row['Data'] = Config::decrypt($row['Data'], $encKey);
    }

    return $rows;
  }

  /**Return the name for the default email-template for the given job-type. */
  public static function emailTemplate($debitNoteJob)
  {
    switcH($debitNoteJob) {
      case 'remaining':
        return L::t('DebitNoteAnnouncementProjectRemaining');
        case 'amount':
          return L::t('DebitNoteAnnouncementProjectAmount');
          case 'deposit':
            return L::t('DebitNoteAnnouncementProjectDeposit');
            case 'insurance':
              return L::t('DebitNoteAnnouncementInsurance');
              case 'membership-fee':
                return L::t('DebitNoteAnnouncementMembershipFee');
                default:
                  return L::t('DebitNoteAnnouncementUnknown');
    }
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
