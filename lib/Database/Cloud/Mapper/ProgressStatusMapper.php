<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCA\CAFEVDB\Config\ConfigService;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class ProgressStatusMapper extends QBMapper
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(IDBConnection $db, ConfigService $configService) {
    $this->configService = $configService;
    parent::__construct($db, $this->appName().'_progress_status');
  }
}
