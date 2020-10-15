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

use Doctrine\Common\Collections\Criteria;
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

  private function getDatabaseRepository($entityClassName = null)
  {
    if ($entityClassName !== $this->entityClassName) {
      $this->setDatabaseRepository($entityClassName);
    }
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository;
  }

  private function createNamedQuery($name) {
    return $this->entityManager->createNamedQuery($name);
  }

  private function createQuery($dql) {
    return $this->entityManager->createQuery($dql);
  }

  private function queryBuilder() {
    return $this->entityManager->createQueryBuilder();
  }

  private function remove($entity)
  {
    if (is_array($entity)) {
      $entity = $this->entityManager->getReference($this->entityClassName, $entity);
    }
    return $this->entityManager->remove($entity);
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

  private function findAll($entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->findAll();
  }

  private function find($id, $lockMode = null, $lockVersion = null) {
    return $this->getDatabaseRepository()->find($id, $lockMode, $lockVersion);
  }

  private function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
  {
    return $this->getDatabaseRepository()->findBy($criteria, $orderBy, $limit, $offset);
  }

  private function findOneBy(array $criteria, array $orderBy = null)
  {
    return $this->getDatabaseRepository()->findOneBy($criteria, $orderBy);
  }

  /**
   * Select all elements from a selectable that match the expression and
   * return a new collection containing these elements.
   *
   * @param \Doctrine\Common\Collections\Criteria $criteria
   *
   * @return \Doctrine\Common\Collections\Collection
   */
  private function matching(Criteria $criteria, $entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->matching($criteria);
  }

  private static function criteria() {
    return new Criteria();
  }

  private static function expr() {
    return Criteria::expr();
  }

  private function count(array $criteria, $entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->count($criteria);
  }

  /**
   * Merges the state of a detached entity into the persistence context
   * of this EntityManager and returns the managed copy of the entity.
   * The entity passed to merge will not become associated/managed with this EntityManager.
   *
   * @param object $entity The detached entity to merge into the persistence context.
   *
   * @return object The managed copy of the entity.
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  private function merge($entity) {
    return $this->entityManager->merge($entity);
  }

  private function columnNames($entityClassName = null) {
    empty($entityClassName) && ($entityClassName = $this->entityClassName);
    if (empty($entityClassName)) {
      return [];
    }
    return $this->entityManager->getClassMetadata($entityClassName)->getColumnNames();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
