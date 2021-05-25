<?php

namespace OCA\CAFEVDB\Database\Cloud\Synchronized;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\ILogger;

use OCA\CAFEVDB\Database\Cloud\Synchronized;

class SynchronizedProgressStatus extends Synchronized
{
  public function __construct(IDBConnection $db, $appName, $id = null) {
    parent::__construct($db, $appName, $id);
  }
}
