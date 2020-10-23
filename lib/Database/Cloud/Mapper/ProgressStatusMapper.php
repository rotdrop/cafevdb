<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\IDBConnection;

class ProgressStatusMapper extends Mapper
{
  public function __construct(IDBConnection $db, $appName) {
    parent::__construct($db, $appName);
  }
}
