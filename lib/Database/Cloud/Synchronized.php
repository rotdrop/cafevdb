<?php

namespace OCA\CAFEVDB\Database\Cloud;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db;
use OCP\ILogger;

/**
 * A synchronized is a tuple of an entity and a mapper which writes
 * all data straight through to the database.
 */
abstract class Synchronized extends Db\QBMapper
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  /** @var bool */
  protected $attached;

  /** @var Entity */
  protected $entity;

  public function __construct(IDBConnection $db, string $appName, int $id = null, string $entityClass = null, string $tableName = null)
  {
    if ($entityClass === null) {
      $entityClass = $this->makeEntityClass();
    }
    if ($tableName === null) {
      $tableName = $this->makeTableName($appName, $entityClass);
    }

    parent::__construct($db, $tableName, $entityClass);

    $this->entity = new $entityClass();
    $this->entity->setId($id);

    $this->attach();
  }

  public function isAttached()
  {
    return $this->attached;
  }

  public function entity()
  {
    return $this->entity;
  }

  /**
   * Obtain object from database if it is "clean", if it is dirty or
   * new (no id) then create or update the database entry.
   */
  public function attach()
  {
    if ($this->entity->getId() !== null) {
      try {
        $this->find($this->entity->getId()); // will throw if not found
        $this->entity = $this->update($this->entity);
      } catch (DoesNotExistException $e) {
        $this->entity = $this->insert($this->entity);
      }
    } else {
      $this->entity = $this->insert($this->entity);
    }
    $this->attached = true;
  }

  /** Remove the wrapped entity from the database. */
  public function detach()
  {
    $this->delete($this->entity);
    $this->entity->setId(null);
    $this->entity->resetUpdatedFields();
    $this->attached = false;
  }

  /**
   * Update multiple properties and write them through to the database if we
   * are in $attached state. If called with no arguments read the entity back
   * from the database, possibly attaching it first.
   */
  public function merge(array $params = [])
  {
    if (!empty($params)) { // write
      foreach ($params as $key => $value) {
        $method = 'set'.(ucfirst($key));
        $this->entity->$method($value);
      }
      if ($this->attached) {
        $this->entity = $this->update($this->entity);
      }
    } else {
      $this->entity->resetUpdatedFields();
      $this->attach();
    }
  }

  public function __call($methodName, $args) {
    if (strpos($methodName, 'set') === 0) {
      try {
        $this->entity->$methodName($args);
        if ($this->attached) {
          $this->entity = $this->update($this->entity);
        }
      } catch (\Throwable $t) {
        throw $t;
      }
    } elseif (strpos($methodName, 'get') === 0) {
      if ($this->attached) {
        $this->entity = $this->find($this->entity->getId());
      }
      return $this->entity->$methodName();
    } else {
      throw new \BadFunctionCallException($methodName . ' does not exist');
    }
  }

  /**
   * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
   * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
   */
  public function find(int $id) {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
       ->from($this->tableName)
       ->where(
         $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
       );

    return $this->findEntity($qb);
  }

}
