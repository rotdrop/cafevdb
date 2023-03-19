<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM;

/** Repository for ProjectPayment entities. */
class ProjectPaymentsRepository extends EntityRepository
{
  /**
   * Fetch all debit note payments and sum them up, grouped by musicianId and projectId.
   *
   * @param array $criteria
   *
   * @return null|array
   */
  public function findTotals(array $criteria):?array
  {
    $queryParts = $this->prepareFindBy($criteria, [
      'project.id' => 'ASC',
      'musician.id' => 'ASC',
    ]);

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [
      'SUM(mainTable.amount) AS totalAmountPaid',
      'project.id AS projectId',
      'musician.id AS musicianId',
    ]);

    $qb->groupBy('project.id')
       ->addGroupBy('musician.id');

    $qb = $this->generateFindByWhere($qb, $queryParts);

    return $qb->getQuery()->execute();

  //     $query = "SELECT p.InstrumentationId, SUM(p.Amount) AS TotalAmountPaid
  // FROM `".self::TABLE."` p
  // LEFT JOIN `".self::DEBIT_NOTES."` d
  //   ON p.DebitNoteId = d.Id
  // WHERE d.Job = '".$debitJob."'";

  //     if (!empty($startDate)) {
  //       $query .= " AND p.DateOfReceipt >= '".$startDate."'";
  //     }
  //     if (!empty($endDate)) {
  //       $query .= " AND p.DateOfReceipt <= '".$endDate."'";
  //     }

  //     $query .= " GROUP BY p.InstrumentationId";
  //     $query .= " ORDER BY p.InstrumentationId ASC";

  //     $result = false;
  //     $qResult = mySQL::query($query, $handle);
  //     if ($qResult !== false) {
  //       $result = array();
  //       while ($row = mySQL::fetch($qResult)) {
  //         $result[$row['InstrumentationId']] = $row;
  //       }
  //     }
  }
}
