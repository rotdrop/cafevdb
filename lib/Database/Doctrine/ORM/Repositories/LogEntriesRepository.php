<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Wrapped\Gedmo\Loggable;

class LogEntriesRepository extends Loggable\Entity\Repository\LogEntryRepository
{
  /**
   * Return the most recent modification time. Theoretically this should
   * coincide with the highest id ...
   *
   * @param null|string $entityClass Restrict the query to the given entity class.
   */
  public function modificationTime(?string $entityClass = null):?\DateTimeInterface
  {
    $qb = $this->createQueryBuilder('l')
      ->select('MAX(l.loggedAt) as modificationTime');
    if (!empty($entityClass)) {
      $qb->where('l.objectClass = :objectClass')
        ->setParameter('objectClass', $entityClass);
    }
    $result = $qb->getQuery()->getSingleScalarResult();

    // Because of the aggregate MAX() this is now a string
    return new \DateTimeImmutable($result, new \DateTimeZone('UTC'));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
