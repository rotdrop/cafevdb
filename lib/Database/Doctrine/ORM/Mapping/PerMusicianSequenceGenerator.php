<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Mapping;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Id\AbstractIdGenerator;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Per-musician sequence generator. This is by no means fool-proof and in
 * principle would have to use either locking or would have to be wrapped
 * into a loop.
 */
class PerMusicianSequenceGenerator extends AbstractIdGenerator
{
  /** {@inheritdoc} */
  public function generate(EntityManager $em, $entity)
  {
    if ($entity->getSequence() === null) {
      $musician = $entity->getMusician();
      if (!($musician instanceof Entities\Musician)) {
        $musician = $entityManager->getReference(Entities\Musician::class, [ 'id' => $musician ]);
        $entity->setMusician($musician);
      }
      $nextSequence = 1 + self::sequenceMax($em, $musician);
      $entity->setSequence($nextSequence);
    }
  }

  /**
   * Get the highest sequence number of the given musician
   */
  static private function sequenceMax(EntityManager $em, Entities\Musician $musician)
  {
    $em->createQueryBuilder()
      ->from(Entities\Musician::class, 'm')
      ->select('MAX(m.sequence) AS sequence')
      ->where('m.musician = :musician')
      ->setParameter('musician', $musician)
      ->getQuery()
      ->getSingleScalarResult();
  }
}
