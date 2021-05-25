<?php

namespace OCA\CAFEVDB\Common;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\CAFEVDB\Database\Cloud\Mapper;
use OCA\CAFEVDB\Database\Cloud\Entities;
use OCA\CAFEVDB\Common\IProgressStatus;

class DatabaseProgressStatus implements IProgressStatus
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const ENTITY_NAME = Entities\ProgressStatus::class;

  /** @var Mapper\ProgressStatusMapper */
  protected $mapper;

  /** @var Entities\ProgressStatus */
  protected $entity;

  /**
   * Find the given or create a new Entities\ProgressStatus entity.
   */
  public function __construct(
    $appName
    , ILogger $logger
    , IL10N $l10n
    , IDBConnection $db
  ) {

    $this->logger = $logger;
    $this->l = $l10n;
    $this->mapper = new Mapper\ProgressStatusMapper($db, $appName);
  }

  public function bind($id)
  {
    if (!empty($this->entity) && $this->entity->getId() != $id) {
      try {
        $this->mapper->delete($this->entity);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $this->entity = null;
    }

    if (!empty($id)) {
      try {
        $this->entity = $this->mapper->find($id);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
    if (empty($this->entity)) {
      $this->entity = new Entities\ProgressStatus;
      $this->entity->setCurrent(0);
      $this->entity->setTarget(0);
      $this->entity->setData(null);
      $this->mapper->insert($this->entity);
    }
  }

  public function getId()
  {
    return $this->entity->getId();
  }

  public function update(int $current, ?int $target = null, ?array $data = null)
  {
    $this->entity->setCurrent($current);
    if (!empty($target)) {
      $this->entity->setTarget($target);
    }
    if (!empty($data)) {
      $this->entity->setData(json_encode($data));
    }
    $this->mapper->update($this->entity);
  }

  public function sync()
  {
    $this->entity = $this->mapper->find($this->entity->getId());
  }

  public function getCurrent():int
  {
    return $this->entity->getCurrent();
  }

  public function getTarget():int
  {
    return $this->entity->getTarget();
  }

  public function getLastModified():\DateTimeinterface
  {
    return (new \DateTimeImmutable)->setTimestamp($this->entity()->getLastModified());
  }

  public function getData():?array
  {
    $dbData = $this->entity->getData();
    return empty($dbData) ? $dbData : json_decode($dbData, true);
  }

}
