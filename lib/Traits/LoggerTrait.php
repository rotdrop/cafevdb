<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Traits;

use \OCP\ILogger;

trait LoggerTrait
{
  /** @var ILogger */
  protected $logger;

  /** Return the stored logger class */
  public function logger():ILogger
  {
    return $this->logger;
  }

  public function log(int $level, string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    ++$shift; // relative to the caller of this function
    $trace = debug_backtrace();
    $prefix = '';
    $shift = min($shift, count($trace));

    do {
      $caller = $trace[$shift];
      $file = $caller['file']??'unknown';
      $line = $caller['line']??'unknown';
      $caller = $trace[$shift+1]??'unknown';
      $class = $caller['class']??'unknown';
      $method = $caller['function'];

      $prefix .= $file.':'.$line.': '.$class.'::'.$method.'(): ';
    } while ($showTrace && --$shift > 0);
    return $this->logger()->log($level, $prefix.$message, $context);
  }

  public function logException($exception, $message = null, $shift = 0, bool $showTrace = false) {
    $trace = debug_backtrace();
    $caller = $trace[$shift];
    $file = $caller['file']??'unknown';
    $line = $caller['line']??0;
    $caller = $trace[$shift+1];
    $class = $caller['class'];
    $method = $caller['function'];

    $prefix = $file.':'.$line.': '.$class.'::'.$method.': ';

    if (is_array($message)) {
      $context = $message;
      $message = null;
    } else {
      $context = [];
    }
    $message = $message ?? ($context['message'] ?? 'Caught an Exception');
    $context['message'] = $prefix.$message;
    $this->logger()->logException($exception, $context);
  }

  public function logError(string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    return $this->log(ILogger::ERROR, $message, $context, $shift, $showTrace);
  }

  public function logDebug(string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    return $this->log(ILogger::DEBUG, $message, $context, $shift, $showTrace);
  }

  public function logInfo(string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    return $this->log(ILogger::INFO, $message, $context, $shift, $showTrace);
  }

  public function logWarn(string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    return $this->log(ILogger::WARN, $message, $context, $shift, $showTrace);
  }

  public function logFatal(string $message, array $context = [], $shift = 0, bool $showTrace = false) {
    return $this->log(ILogger::FATAL, $message, $context, $shift, $showTrace);
  }

}
