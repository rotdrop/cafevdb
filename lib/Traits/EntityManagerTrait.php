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
  protected $entityManager;

  /** @var string */
  protected $entityClassName;

  /** @var EntityRepository */
  protected $databaseRepository;

  /**
   * Set the given name as the current database target entity.
   *
   * @param string $entityClassName
   */
  protected function setDatabaseRepository(string $entityClassName):void
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
  protected function getDatabaseRepository($entityClassName = null):EntityRepository
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
  protected function createNamedQuery($name) {
    return $this->entityManager->createNamedQuery($name);
  }

  /**
   * Creates a new Query object.
   *
   * @param string $dql The DQL string.
   *
   * @return Query
   */
  protected function createQuery($dql) {
    return $this->entityManager->createQuery($dql);
  }

  /**
   * Create a QueryBuilder instance
   *
   * @return QueryBuilder
   */
  protected function queryBuilder() {
    return $this->entityManager->createQueryBuilder();
  }

  /**
   * Removes an entity instance.
   *
   * A removed entity will be removed from the database at or before transaction commit
   * or as a result of the flush operation.
   *
   * @param mixed $entity The entity instance to remove.
   *
   * @param bool $flush Initiate a flush if true.
   *
   * @return void
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  protected function remove($entity, bool $flush = false)
  {
    if (is_array($entity)) {
      $key = $entity;
      $entity = $this->entityManager->getReference($this->entityClassName, $key);
      $this->logDebug("Create reference from ".print_r($key, true).' for '.$this->entityClassName);
    }
    $this->entityManager->remove($entity);

    // This seems to be necessary, why?
    if ($flush) {
      $this->flush();
    }
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
  protected function persist($entity)
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
  protected function flush($entity = null)
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
  protected function findAll($entityClassName = null)
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
  protected function find($id, $lockMode = null, $lockVersion = null) {
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
  protected function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
  protected function findOneBy(array $criteria, array $orderBy = null)
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
  protected function matching(Criteria $criteria, $entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->matching($criteria);
  }

  protected static function criteria() {
    return new Criteria();
  }

  protected static function cExpr() {
    return Criteria::expr();
  }

  protected function expr() {
    return $this->queryBuilder()->expr();
  }

  /**
   * Counts entities by a set of criteria.
   *
   * @param array $criteria
   *
   * @param string $entityClassName The database entity to use.
   *
   * @return int The cardinality of the objects that match the given criteria.
   */
  protected function count(array $criteria, string $entityClassName = null):int
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
  protected function merge($entity) {
    $entity = $this->entityManager->merge($entity);
    $this->flush($entity);
    return $entity;
  }

  /**
   * Obtain the column names of the currently used database entity.
   *
   * @param string|null $entityClassName The database entity to use.
   *
   * @return array The column names.
   */
  protected function columnNames($entityClassName = null) {
    empty($entityClassName) && ($entityClassName = $this->entityClassName);
    if (empty($entityClassName)) {
      return [];
    }
    return $this->entityManager->getClassMetadata($entityClassName)->getColumnNames();
  }

  /**
   * Obtain the class meta-data for the given or most recently used
   * entity class.
   *
   * @param string|null $entityClassName The database entity to use.
   *
   * @return array The class meta-data
   */
  protected function classMetadata($entityClassName = null) {
    empty($entityClassName) && ($entityClassName = $this->entityClassName);
    if (empty($entityClassName)) {
      return null;
    }
    return $this->entityManager->getClassMetadata($entityClassName);
  }

  /**
   * @see \OCA\CAFEVDB\Database\EntityManager::property
   */
  protected function property($columnName)
  {
    return $this->entityManager->property($columnName);
  }

  /**
   * @see \OCA\CAFEVDB\Database\EntityManager::extractkeyvalues
   */
  public function extractKeyValues($meta, array $columnValues):array
  {
    return $this->entityManager->extractKeyValues($meta, $columnValues);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
