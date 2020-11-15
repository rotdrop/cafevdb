<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCP\ILogger;

trait LogTrait {

  public static function log($message, int $level = ILogger::INFO, $shift = 1)
  {
    $trace = debug_backtrace();
    $caller = $trace[$shift];
    $file = $caller['file'];
    $line = $caller['line'];
    $caller = $trace[$shift+1];
    $class = $caller['class'];
    $method = $caller['function'];

    $prefix = $file.':'.$line.': '.$class.'::'.$method.': ';
    \OC::$server->query(ILogger::class)->log($level, $prefix.$message);
  }
}
