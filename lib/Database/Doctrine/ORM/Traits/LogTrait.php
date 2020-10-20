<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCP\ILogger;

trait LogTrait {

    public static function log($message, int $level = ILogger::INFO)
    {
        $logger = \OC::$server->query(ILogger::class);
        $logger->log($level, __CLASS__.'::'.$message);
    }

}
