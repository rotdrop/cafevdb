<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCA\CAFEVDB\Database\Cloud\Entities\ProgressStatus;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class ProgressStatusMapper extends QBMapper
{
  public function __construct(IDBConnection $db, $appName) {
    parent::__construct($db, $appName.'_progress_status', ProgressStatus::class);
  }
}
