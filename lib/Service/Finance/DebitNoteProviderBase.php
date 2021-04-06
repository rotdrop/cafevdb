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

use \DateTimeImmutable as DateTime;

/**
 * Base class for debit-note export.
 */
abstract class DebitNoteProviderBase implements IDebitNoteProvider
{
//     /**Fetch all relevant finance information from the given project
//      * in order to initiate a debit note. Only musician with active
//      * debit note mandate are taken into account.
//      */
//     static protected function projectFinanceExport($projectId, $projectName = null, $handle = false)
//     {
//       $ownConnection = $handle === false;

//       if ($ownConnection) {
//         Config::init();
//         $handle = mySQL::connect(Config::$pmeopts);
//       }

//       $memberProjectId = Config::getValue('memberTableId');
//       empty($projectName) && $projectName = Projects::fetchName($projectId, $handle);
//       $monetary = ProjectParticipant::monetaryFields($projectId, $handle);

//       $projectTable = $projectName.'View';
//       $mandateTable = 'SepaDebitMandates';

//       //build a query with all relevant finance fields
//       $query = "SELECT ";
//       $query .= $projectId.' AS ProjectId'
//         .', p.Id AS InstrumentationId'
//         .', MusikerId AS MusicianId'
//         .", '".$projectName."' AS ProjectName"
//         .', Name AS SurName'
//         .', Vorname AS FirstName'
//         .', UnkostenBeitrag AS RegularFee'
//         .', Anzahlung AS Deposit'
//         .', AmountPaid AS AmountPaid'
//         .', PaidCurrentYear AS PaidCurrentYear'
//         .', LastSchrift AS DebitNote';

//       foreach(array_keys($monetary) AS $extraLabel) {
//         $query .= ', `'.$extraLabel.'`';
//       }
//       $query .= ',
//   `m`.*';

//       // cope with last used dates stored in mandate table
//       // vs. determined from payments table
//       $query .= ",
//   GREATEST(
//     COALESCE(MAX(pp.`DateOfReceipt`), ''),
//     COALESCE(m.`lastUsedDate`, '')
//   ) AS RecordedLastUse";

//       // build FROM and JOIN
//       $query .= ' FROM '.$projectTable.' p'."\n";

//       // if we have a mandate for the project and a mandate as club
//       // member, the project mandate takes precedence. This covers
//       // non-frequent cases, but it can happen ...
//       if ($projectId === $memberProjectId) {
//         $projectSelector = 'm.projectId  = '.$projectId;
//       } else {
//         $fct = self::PREFER_PROJECT_MANDATE ? 'MAX' : 'MIN';
//         $subQuery = "SELECT m2.musicianId, ".$fct."(m2.projectId) AS ProjectId
//   FROM `".self::MEMBER_TABLE."` m2
//   WHERE
//     (m2.projectId = ".$projectId." OR m2.projectId = ".$memberProjectId.")
//     AND
//     m2.active
//   GROUP BY m2.musicianId";
//         $query .= "LEFT JOIN (".$subQuery.") MandateSelector
//   ON MandateSelector.musicianId = p.MusikerId
// ";
//         $projectSelector = "m.projectId = MandateSelector.Projectid";
//       }

//       $query .= 'LEFT JOIN `'.self::MEMBER_TABLE.'` m
//   ON m.musicianId = p.MusikerId
//      AND '.$projectSelector.'
//      AND m.active = 1
// LEFT JOIN `ProjectPayments` pp
//   ON m.`mandateReference` = pp.`MandateReference`
// WHERE
//     p.Lastschrift = 1
//     AND
//     p.Anmeldung = 1
//     AND
//     p.Disabled = 0
//     AND
//     m.mandateReference IS NOT NULL
// ';

//       $query .= "GROUP BY p.Id";
//       error_log("@@@@ query: ".$query);
//       $result = mySQL::query($query, $handle);
//       $table = array();

//       if ($result === false) {
//         throw new \Exception(mySQL::error($handle).' '.$query);
//       }

//       while ($row = mySQL::fetch($result)) {
//         if (Finance::mandateIsExpired($row['mandateReference'], $handle)) {
//           error_log("@@@@ expired for ".$row['MusicianId']);
//           continue;
//         }
//         // use max of explicit last-use and value deduced from
//         // ProjectPayments table.
//         $row['lastUsedDate'] = $row['RecordedLastUse'];
//         unset($row['RecordedLastUse']);
//         $amount = 0.0;
//         foreach($monetary as $label => $fieldInfo) {
//           $value = $row[$label];
//           unset($row[$label]);
//           if (empty($value)) {
//             continue;
//           }
//           $allowed  = $fieldInfo['DataOptions'];
//           $type     = $fieldInfo['Type']['Multiplicity'];
//           $amount  += DetailedInstrumentation::participantFieldSurcharge($value, $allowed, $type);
//         }
//         $row['SurchargeFees'] = $amount;
//         $table[] = $row;
//       }

//       if ($ownConnection) {
//         mySQL::close($handle);
//       }

//       return $table;
//     }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
