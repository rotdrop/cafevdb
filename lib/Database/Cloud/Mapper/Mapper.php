<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

abstract class Mapper extends QBMapper
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  public function __construct(IDBConnection $db, $appName, string $entityClass = null, string $tableName = null)
  {
    if ($entityClass === null) {
      $entityClass = $this->makeEntityClass();
    }
    if ($tableName === null) {
      $tableName = $this->makeTableName($appName, $entityClass);
    }
    parent::__construct($db, $tableName, $entityClass);
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

  public function findAll($limit=null, $offset=null) {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
       ->from($tableName)
       ->setMaxResults($limit)
       ->setFirstResult($offset);

    return $this->findEntities($sql);
  }
}
