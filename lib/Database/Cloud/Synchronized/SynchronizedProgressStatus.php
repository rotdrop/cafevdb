<?php

namespace OCA\CAFEVDB\Database\Cloud\Synchronized;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\ILogger;

use OCA\CAFEVDB\Database\Cloud\Synchronized;

class SynchronizedProgressStatus extends Synchronized
{
  /** @var stingt */
  private $userId;

  public function __construct(IDBConnection $db, $appName, $userId, $id = null) {
    $this->userId = $userId;
    parent::__construct($db, $appName.'_progress_status', $id);
  }

  public function insert(Entity $entity): Entity
  {
    $entity->setUserId($this->userId); // will also update the timestamp
    return parent::insert($entity);
  }

}
