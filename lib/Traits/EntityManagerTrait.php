<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use Throwable;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository as BaseEntityRepository;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator as ClassMetadata;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/**
 * Database EntityManager short-cuts.
 */
trait EntityManagerTrait
{
  /** @var EntityManager */
  protected EntityManager $entityManager;

  /** @var string */
  protected $entityClassName;

  /** @var BaseEntityRepository */
  protected $databaseRepository;

  /**
   * Clear the cache.
   *
   * @return void
   */
  protected function clearDatabaseRepository():void
  {
    $this->entityClassName = null;
    $this->databaseRepository = null;
  }

  /**
   * Set the given name as the current database target entity.
   *
   * @param string $entityClassName
   *
   * @return void
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
   * @return BaseEntityRepository The repository for the current target
   * database entity.
   */
  protected function getDatabaseRepository($entityClassName = null):BaseEntityRepository
  {
    if (!empty($entityClassName) && $entityClassName !== $this->entityClassName) {
      $this->setDatabaseRepository($entityClassName);
    }
    if (empty($this->databaseRepository)
        || ($this->databaseRepository instanceof EntityRepository
            && $this->databaseRepository->getEntityManager() != $this->entityManager->getWrappedObject())) {
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
  protected function createNamedQuery(string $name)
  {
    return $this->entityManager->createNamedQuery($name);
  }

  /**
   * Creates a new Query object.
   *
   * @param string $dql The DQL string.
   *
   * @return Query
   */
  protected function createQuery(string $dql)
  {
    return $this->entityManager->createQuery($dql);
  }

  /**
   * Create a QueryBuilder instance
   *
   * @return QueryBuilder
   */
  protected function queryBuilder()
  {
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
   * @param bool $hard Issue a double remove: if $entity is managed by
   * Gedmo\SoftDeleteable then this will "hard" (read: finally) delete
   * the entity unless it is tagged as "in use" by other means.
   *
   * @param bool $soft For entities managed by Gedmo\SoftDeleteable
   * first check if this entity is already tagged as "soft deleted"
   * and if so do nothing.
   *
   * @param bool $useTransaction Wrap the remove operation into a transaction
   * or not. This parameter is ignored if $flush is false.
   *
   * @return void
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  protected function remove(
    mixed $entity,
    bool $flush = false,
    bool $hard = false,
    bool $soft = false,
    bool $useTransaction = false,
  ):void {
    if (filter_var($entity, FILTER_VALIDATE_INT, ['min_range' => 1])) {
      $entity = [ 'id' => $entity ];
    }
    if (is_array($entity)) {
      $key = $entity;
      // getReference() will not always work, and in particular not with
      // foreign keys, so just fetch the entity first.
      //
      // $entity = $this->entityManager->getReference($this->entityClassName, $key);
      $entity = $this->entityManager->find($this->entityClassName, $key);
    }
    if ($soft && !$hard && method_exists($entity, 'isDeleted') && $entity->isDeleted()) {
      return;
    }
    try {
      if ($useTransaction && $flush) {
        $this->entityManager->beginTransaction();
      }
      $this->entityManager->remove($entity);
      if ($hard && (!method_exists($entity, 'isDeleted') || !$entity->isDeleted())) {
        $this->flush();
        $this->entityManager->remove($entity);
      }

      if ($flush) {
        $this->flush();
        if ($useTransaction) {
          $this->entityManager->commit();
        }
      }
    } catch (Throwable $t) {
      if ($useTransaction) {
        $this->entityManager->rollback();
      }
      throw $t;
    }
  }

  /**
   * Gets a reference to the entity identified by the given type and identifier
   * without actually loading it, if the entity is not yet loaded.
   *
   * @param string $entityClassName The name of the entity type.
   *
   * @param mixed $key The entity reference.
   *
   * @return mixed
   *
   * @throws ORMException
   */
  protected function getReference(string $entityClassName, mixed $key)
  {
    return $this->entityManager->getReference($entityClassName, $key);
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
   * @param mixed $entity The instance to make managed and persistent.
   *
   * @return mixed
   *
   * @throws ORMInvalidArgumentException
   * @throws ORMException
   */
  protected function persist(mixed $entity)
  {
    return $this->entityManager->persist($entity);
  }

  /**
   * Flushes all changes to objects that have been queued up to now to the database.
   * This effectively synchronizes the in-memory state of managed objects with the
   * database.
   *
   * If an entity is explicitly passed then it is persisted before the actual
   * flush is called.
   *
   * @param null|object|array $entity
   *
   * @param bool $useTransaction Wrap the flush operation into a transaction or not.
   *
   * @return void
   *
   * @throws \OCA\CAFEVDB\Wrapped\Doctrine\ORM\OptimisticLockException If a version check on an entity that
   *         makes use of optimistic locking fails.
   * @throws ORMException
   */
  protected function flush($entity = null, bool $useTransaction = false):void
  {
    if (!empty($entity)) {
      $this->entityManager->persist($entity);
    }
    if ($useTransaction) {
      $this->entityManager->beginTransaction();
      try {
        $this->entityManager->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        $this->entityManager->rollback();
      }
    } else {
      $this->entityManager->flush();
    }
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
   *
   * @param int|null $lockMode    One of the \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\LockMode::* constants
   *                              or NULL if no specific lock mode should be used
   *                              during the search.
   * @param int|null $lockVersion The lock version.
   *
   * @return object|null The entity instance or NULL if the entity can not be found.
   */
  protected function find(mixed $id, ?int $lockMode = null, ?int $lockVersion = null)
  {
    return $this->getDatabaseRepository()->find($id, $lockMode, $lockVersion);
  }

  /**
   * Finds an entity by its primary key / identifier.
   *
   * @param string   $entityClassName
   *
   * @param mixed    $id          The identifier.
   *
   * @param int|null $lockMode    One of the \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\LockMode::* constants
   *                              or NULL if no specific lock mode should be used
   *                              during the search.
   * @param int|null $lockVersion The lock version.
   *
   * @return object|null The entity instance or NULL if the entity can not be found.
   */
  protected function findEntity(string $entityClassName, mixed $id, ?int $lockMode = null, ?int $lockVersion = null)
  {
    return $this->entityManager->find($entityClassName, $id, $lockMode, $lockVersion);
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
   * Forward to EntityManager::contains().
   *
   * @param mixed $entity
   *
   * @return bool
   */
  protected function containsEntity(mixed $entity):bool
  {
    return $this->entityManager->contains($entity);
  }

  /**
   * Forward to EntityManager::refresh(), return the refreshed entity in order
   * to allow for "->" chaining.
   *
   * @param mixed $entity
   *
   * @return mixed $entity
   */
  protected function refreshEntity(mixed $entity)
  {
    $this->entityManager->refresh($entity);
    return $entity;
  }

  /**
   * Enable the given filter.
   *
   * @param string $filterName
   *
   * @param bool $state
   *
   * @return \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\Filter\SQLFilter The enabled filter.
   */
  protected function enableFilter(string $filterName, bool $state = true)
  {
    if ($state) {
      return $this->entityManager->getFilters()->enable($filterName);
    }
  }

  /**
   * Disable the given filter. In contrast to the upstream-method does
   * not throw an exception if the filter is not enabled.
   *
   * @param string $filterName
   *
   * @return bool The previous isEnabled() state of the filter.
   */
  protected function disableFilter(string $filterName):bool
  {
    if ($this->entityManager->getFilters()->isEnabled($filterName)) {
      $this->entityManager->getFilters()->disable($filterName);
      return true;
    }
    return false;
  }

  /**
   * Select all elements from a selectable that match the expression and
   * return a new collection containing these elements.
   *
   * @param Collections\Criteria $criteria
   *
   * @param string|null $entityClassName
   *
   * @return Collections\Collection
   */
  protected function matching(Collections\Criteria $criteria, ?string $entityClassName = null)
  {
    return $this->getDatabaseRepository($entityClassName)->matching($criteria);
  }

  /**
   * Convenience function to generate Collections\Criteria
   *
   * @return Collections\Criteria
   */
  protected static function criteria():Collections\Criteria
  {
    return DBUtil::criteria();
  }

  /**
   * Convenience function to generate Collections\ExpressionBuilder
   *
   * @return Collections\ExpressionBuilder
   */
  protected static function criteriaExpr():Collections\ExpressionBuilder
  {
    return DBUtil::criteriaExpr();
  }

  /**
   * @param array $arrayCriteria
   *
   * @return Collections\Criteria
   *
   * @see DBUtil::criteriaWhere()
   */
  protected static function criteriaWhere(array $arrayCriteria)
  {
    return DBUtil::criteriaWhere($arrayCriteria);
  }

  /** @return mixed */
  protected function expr()
  {
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
   * Obtain the column names of the currently used database entity.
   *
   * @param string|null $entityClassName The database entity to use.
   *
   * @return array The column names.
   */
  protected function columnNames($entityClassName = null)
  {
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
  protected function classMetadata(?string $entityClassName = null):ClassMetadata
  {
    empty($entityClassName) && ($entityClassName = $this->entityClassName);
    if (empty($entityClassName)) {
      return null;
    }
    return $this->entityManager->getClassMetadata($entityClassName);
  }

  /**
   * @param string $columnName
   *
   * @return string
   *
   * @see \OCA\CAFEVDB\Database\EntityManager::property
   */
  protected function property(string $columnName):string
  {
    return $this->entityManager->property($columnName);
  }
}
