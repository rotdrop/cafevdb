<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @method Entities\SepaBulkTransaction find($id)
 */
class SepaBulkTransactionsRepository extends EntityRepository
{
  public function sepaTransactionDataModificationTime($year = null):\DateTimeInterface
  {
    $qb = $this->createQueryBuilder('t')
      ->select('MAX(t.sepaTransactionDataChanged) as modificiationTime');
    if (!empty($year)) {
      $qb->where('YEAR(t.created) = :year')
        ->setParameter('year', $year);
    }
    $result = $qb->getQuery()->getSingleScalarResult();

    // Because of the aggregate MAX() result is a string
    return new \DateTimeImmutable($result, new \DateTimeZone('UTC'));
  }

  public function findByCreationYear($year)
  {
    return $this->createQueryBuilder('t')
      ->where('YEAR(t.created) = :year')
      ->setParameter('year', $year)
      ->getQuery()
      ->getResult();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
