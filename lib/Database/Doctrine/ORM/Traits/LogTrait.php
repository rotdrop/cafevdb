<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCP\ILogger;

trait LogTrait {

  public static function log($message, int $level = ILogger::INFO)
  {
    $trace = debug_backtrace();
    $caller = array_shift($trace);
    $file = $caller['file'];
    $line = $caller['line'];
    $class = $caller['class'];
    $method = $caller['function'];

    $prefix = $file.':'.$line.': '.$class.'::'.$method.': ';
    \OC::$server->query(ILogger::class)->log($level, $prefix.$message);
  }
}
