<?php

namespace OCA\CAFEVDB\Database\Cloud;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db;
use OCP\ILogger;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\CAFEVDB\Database\Cloud\Entities;
use OCA\CAFEVDB\Common\IProgressStatus;

class DatabaseProgressStatus extends Db\QBMapper implements IProgressStatus
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  const ENTITY_NAME = Entities\ProgressStatus::class;

  /** @var Entities\ProgressStatus */
  protected $progressEntity;

  /**
   * Find the given or create a new Entities\ProgressStatus entity.
   */
  public function __construct(IDBConnection $db, $appName, $id = null) {

    $tableName = $this->makeTableName($appName, $entityClass);
    parent::__construct($db, $tableName, self::ENTITY_NAME);

    if (!empty($id)) {
      try {
        $this->progressEntity = $this->find($id);
      } catch (DoesNotExistException $e) {
        $this->insert($this->progressEntity);
      }
    } else {
      $this->insert($this->progressEntity);
    }
  }

  /**
   * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
   * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
   */
  protected function find(int $id) {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
       ->from($this->tableName)
       ->where(
         $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
       );
    return $this->findEntity($qb);
  }

  public function getId()
  {
    return $this->progressEntity->getId();
  }

  public function update(int $current, ?int $target = null, ?array $data = null)
  {
    $this->progressEntity->setCurrent($current);
    if (!empty($target)) {
      $this->progressEntity->setTarget($target);
    }
    if (!empty($data)) {
      $this->progressEntity->setData(json_encode($data));
    }
    parent::update($this->progressEntity);
  }

  public function sync()
  {
    $this->progressEntity = $this->find($this->progressEntity->getId());
  }

  public function getCurrent():int
  {
    return $this->progressEntity->getCurrent();
  }

  public function getTarget()
  {
    return $this->progressEntity->getTarget();
  }

  public function getLastModified():\DateTimeinterface
  {
    return (new \DateTimeImmutable)->setTimestamp($this->progressEntity()->getLastModified());
  }

  public function getData():?array
  {
    $dbData = $this->progressEntity->getData();
    return empty($dbData) ? $dbData : json_decode($dbData, true);
  }

}
