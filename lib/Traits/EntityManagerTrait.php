<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCA\CAFEVDB\Database\EntityManager;

/**Database EntityManager short-cuts.
 *
 */
trait EntityManagerTrait {

  /** @var EntityManager */
  private $entityManager;

  /** @var string */
  private $entityClassName;

  /** @var Repository */
  private $databaseRepository;

  private function setDatabaseRepository($entityClassName)
  {
    $this->entityClassName = $entityClassName;
    $this->databaseRepository = null;
  }

  private function queryBuilder() {
    return $this->entityManager->createQueryBuilder();
  }

  private function remove($entity)
  {
    if (is_array($entity)) {
      $entity = $this->entityManager->getReference($this->entityClassName, $entity);
    }
    $this->entityManager->remove($entity);
  }

  private function persist($entity)
  {
    return $this->entityManager->persist($entity);
  }

  private function flush($entity = null)
  {
    if (!empty($entity)) {
      $this->persist($entity);
    }
    return $this->entityManager->flush($entity);
  }

  private function findAll()
  {
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository->findAll();
  }

  private function find($id, $lockMode = null, $lockVersion = null) {
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository->find($id, $lockMode, $lockVersion);
  }

  private function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
  {
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository->findBy($criteria, $orderBy, $limit, $offset);
  }

  private function findOneBy(array $criteria, array $orderBy = null)
  {
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository->findOneBy($criteria, $orderBy);
  }

  private function count(array $criteria)
  {
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository->count($criteria);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
