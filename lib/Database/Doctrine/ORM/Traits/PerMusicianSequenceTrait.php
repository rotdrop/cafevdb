<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * A special trait for a repository for entities with per-musician
 * sequence which together with the musician form a composite key.
 */
trait PerMusicianSequenceTrait
{
  /**
   * Get the highest sequence number of the given musician
   */
  public function sequenceMax($musician)
  {
    return $this->createQueryBuilder('m')
                ->select('MAX(m.sequence) AS sequence')
                ->where('m.musician = :musician')
                ->setParameter('musician', $musician)
                ->getQuery()
                ->getSingleScalarResult();
  }

  /**
   * Persist the given entity with the next sequence by using a simple
   * brute-force method. The musician the entity belongs to needs to
   * be set in the entity. If the $entity already has a sequence
   * attached, then it is simply persisted.
   *
   * @throws Doctrine\DBAL\Exception\UniqueConstraintViolationException
   */
  protected function persistEntity($entity)
  {
    $entityManager = $this->getEntityManager();

    // in order not to have "other" exceptions in our try block
    $entityManager->flush();

    if ($entity->getSequence() == null) {
      $musician = $entity->getMusician();
      if (!($musician instanceof Entities\Musician)) {
        $musician = $entityManager->getReference(Entities\Musician::class, [ 'id' => $musician ]);
        $entity->setMusician($musician);
      }
      $nextSequence = 1 + $this->sequenceMax($musician);
      $entity->setSequence($nextSequence);
    }
    $entityManager->persist($entity);
    $entityManager->flush();
    return $entity;
  }

}
