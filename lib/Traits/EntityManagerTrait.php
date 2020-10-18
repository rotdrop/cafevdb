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
use Doctrine\ORM\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;

/**Database EntityManager short-cuts.
 *
 */
trait EntityManagerTrait {

  /** @var EntityManager */
  private $entityManager;

  /** @var string */
  private $entityClassName;

  /** @var EntityRepository */
  private $databaseRepository;

  /**
   * Set the given name as the current database target entity.
   *
   * @param string $entityClassName
   */
  private function setDatabaseRepository(string $entityClassName):void
  {
    $this->entityClassName = $entityClassName;
    $this->databaseRepository = null;
  }

  /**
   * Set and get the given name as the current database target entity.
   *
   * @param string|null $entityClassName
   *
   * @return EntityRepository The repository for the current target
   * database entity.
   */
  private function getDatabaseRepository($entityClassName = null):EntityRepository
  {
    if (!empty($entityClassName) && $entityClassName !== $this->entityClassName) {
      $this->setDatabaseRepository($entityClassName);
    }
    if (empty($this->databaseRepository)) {
      $this->databaseRepository = $this->entityManager->getRepository($this->entityClassName);
    }
    return $this->databaseRepository;
  }

  /**
   * Creates a Query from a named query.
   *
   * @param string $name
   *
   * @return Query
   */
  private function createNamedQuery($name) {
    return $this->entityManager->createNamedQuery($name);
  }

  /**
   * Creates a new Query object.
   *
   * @param string $dql The DQL string.
   *
   * @return Query
   */
  private function createQuery($dql) {
    return $this->entityManager->createQuery($dql);
  }

  /**
   * Create a QueryBuilder instance
   *
   * @return QueryBuilder
   */
  private function queryBuilder() {
    return $this->entityManager->createQueryBuilder();
  }

  /**
   * Removes an entity instance.
   *
   * A removed entity will be removed from the database at or before transaction commit
   * or as a result of the flush operation.
   *
   * @param object $entity The entity instance to remove.
   *
   * @return void
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  private function remove($entity)
  {
    if (is_array($entity)) {
      $entity = $this->entityManager->getReference($this->entityClassName, $entity);
    }
    return $this->entityManager->remove($entity);
  }

  /**
   * Tells the EntityManager to make an instance managed and persistent.
   *
   * The entity will be entered into the database at or before transaction
   * commit or as a result of the flush operation.
   *
   * NOTE: The persist operation always considers entities that are not yet known to
   * this EntityManager as NEW. Do not pass detached entities to the persist operation.
   *
   * @param object $entity The instance to make managed and persistent.
   *
   * @return void
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  private function persist($entity)
  {
    return $this->entityManager->persist($entity);
  }

  /**
   * Flushes all changes to objects that have been queued up to now to the database.
   * This effectively synchronizes the in-memory state of managed objects with the
   * database.
   *
   * If an entity is explicitly passed to this method only this entity and
   * the cascade-persist semantics + scheduled inserts/removals are synchronized.
   *
   * @param null|object|array $entity
   *
   * @return void
   *
   * @throws \Doctrine\ORM\OptimisticLockException If a version check on an entity that
   *         makes use of optimistic locking fails.
   * @throws ORMException
   */
  private function flush($entity = null)
  {
    if (!empty($entity)) {
      $this->persist($entity);
    }
    return $this->entityManager->flush($entity);
  }

  /**
   * Finds all entities in the repository.
   *
   * @param string|null $entityClassName
   *
   * @return array The entities.
   */
  private function findAll($entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->findAll();
  }

  /**
   * Finds an entity by its primary key / identifier.
   *
   * @param mixed    $id          The identifier.
   * @param int|null $lockMode    One of the \Doctrine\DBAL\LockMode::* constants
   *                              or NULL if no specific lock mode should be used
   *                              during the search.
   * @param int|null $lockVersion The lock version.
   *
   * @return object|null The entity instance or NULL if the entity can not be found.
   */
  private function find($id, $lockMode = null, $lockVersion = null) {
    return $this->getDatabaseRepository()->find($id, $lockMode, $lockVersion);
  }

  /**
   * Finds entities by a set of criteria.
   *
   * @param array      $criteria
   * @param array|null $orderBy
   * @param int|null   $limit
   * @param int|null   $offset
   *
   * @return array The objects.
   */
  private function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
  {
    return $this->getDatabaseRepository()->findBy($criteria, $orderBy, $limit, $offset);
  }

  /**
   * Finds a single entity by a set of criteria.
   *
   * @param array      $criteria
   * @param array|null $orderBy
   *
   * @return object|null The entity instance or NULL if the entity can not be found.
   */
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
   * @param string|null $entityClassName
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

  private static function cExpr() {
    return Criteria::expr();
  }

  private function expr() {
    return $this->queryBuilder()->expr();
  }

  /**
   * Counts entities by a set of criteria.
   *
   * @todo Add this method to `ObjectRepository` interface in the next major release
   *
   * @param array $criteria
   *
   * @param string $entityClassName The database entity to use.
   *
   * @return int The cardinality of the objects that match the given criteria.
   */
  private function count(array $criteria, string $entityClassName = null):int
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
    $entity = $this->entityManager->merge($entity);
    $this->flush($entity);
    return $entity;
  }

  /**
   * Obtain the column name of the currently used database entity.
   *
   * @param string|null $entityClassName The database entity to use.
   *
   * @return array The column names.
   */
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
