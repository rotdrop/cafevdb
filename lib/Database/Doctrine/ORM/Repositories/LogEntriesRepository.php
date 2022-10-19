<?php
/* Orchestra member, musician and project management application.
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
use OCA\CAFEVDB\Wrapped\Gedmo\Loggable;

class LogEntriesRepository extends Loggable\Entity\Repository\LogEntryRepository
{
  const ACTION_CREATE = Loggable\LoggableListener::ACTION_CREATE;
  const ACTION_UPDATE = Loggable\LoggableListener::ACTION_UPDATE;
  const ACTION_REMOVE = Loggable\LoggableListener::ACTION_REMOVE;

  /**
   * Return the most recent modification time. Theoretically this should
   * coincide with the highest id ...
   *
   * @param null|string $entityClass Restrict the query to the given entity class.
   */
  public function modificationTime(?string $entityClass = null):?\DateTimeInterface
  {
    return $this->getModificationTime($entityClass, null);
  }

  /**
   * Return the most recent modification time. Theoretically this should
   * coincide with the highest id ...
   *
   * @param null|string $entityClass Restrict the query to the given entity class.
   *
   * @param null|string $action Restrict the query to the given action.
   */
  public function getModificationTime(?string $entityClass = null, ?string $action = null):?\DateTimeInterface
  {
    $qb = $this->createQueryBuilder('l')
      ->select('l.loggedAt as modificationTime')
      ->orderBy('l.id', 'DESC')
      ->setMaxResults(1);
    if (!empty($entityClass)) {
      $qb->andWhere('l.objectClass = :objectClass')
        ->setParameter('objectClass', $entityClass);
    }
    if (!empty($action)) {
      $qb->andWhere('l.action = :action')
        ->setParameter('action', $action);
    }
    return $qb->getQuery()->getOneOrNullResult();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
