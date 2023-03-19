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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager as DecoratedEntityManager;

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
   *
   * @param Entities\Musician $musician
   *
   * @return null|int
   */
  public function sequenceMax(Entities\Musician $musician):?int
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
   * @param int|Entities\Musician $entity Entity or entit id.
   *
   * @return mixed
   *
   * @throws Doctrine\DBAL\Exception\UniqueConstraintViolationException
   */
  protected function persistEntity(mixed $entity):mixed
  {
    $entityManager = $this->getEntityManager();

    if ($entity->getSequence() !== null) {
      $entityManager->persist($entity);
      return $entity;
    }

    $filters = $entityManager->getFilters();
    $softDeleteable = $filters->isEnabled(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    if ($softDeleteable) {
      $filters->disable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    $musician = $entity->getMusician();
    if (!($musician instanceof Entities\Musician)) {
      $musician = $entityManager->getReference(Entities\Musician::class, [ 'id' => $musician ]);
      $entity->setMusician($musician);
    }
    $nextSequence = 1 + $this->sequenceMax($musician);
    $entity->setSequence($nextSequence);
    $entityManager->persist($entity);
    $entityManager->flush();

    if ($softDeleteable) {
      $filters->enable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    return $entity;
  }
}
