<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

use Throwable;
use OCP\ILogger;

/**
 * Utility trait providing functions for logging messages the cloud
 * log-file.
 */
trait LoggerTrait
{
  /** @var ILogger */
  protected $logger;

  /** @return ILogger The stored logger class. */
  public function logger():ILogger
  {
    return $this->logger;
  }

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param int $level
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function log(int $level, string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $trace = debug_backtrace();
    if (count($trace) <= $shift) {
      $shift = count($trace) - 1;
    }
    $prefix = '';
    $shift = min($shift, count($trace));

    do {
      $caller = $trace[$shift];
      $file = $caller['file']??'unknown';
      $line = $caller['line']??'unknown';

      $caller = $trace[$shift+1]??'unknown';
      $class = $caller['class']??'unknown';
      $method = $caller['function']??'unknown';

      $prefix .= $file.':'.$line.': '.$class.'::'.$method.'(): ';
    } while ($showTrace && --$shift > 0);
    $this->logger()->log($level, $prefix.$message, $context);
  }

   /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param Throwable $exception
   *
   * @param null|string|array $message Message or log-context.
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logException(Throwable $exception, mixed $message = null, int $shift = 0, bool $showTrace = false):void
  {
    $trace = debug_backtrace();
    $caller = $trace[$shift];
    $file = $caller['file']??'unknown';
    $line = $caller['line']??0;
    $caller = $trace[$shift + 1];
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

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logError(string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $this->log(ILogger::ERROR, $message, $context, $shift, $showTrace);
  }

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logDebug(string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $this->log(ILogger::DEBUG, $message, $context, $shift + 1, $showTrace);
  }

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logInfo(string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $this->log(ILogger::INFO, $message, $context, $shift + 1, $showTrace);
  }

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logWarn(string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $this->log(ILogger::WARN, $message, $context, $shift + 1, $showTrace);
  }

  /**
   * Log the given message at the given level. The function optionally
   * provides a backtrace functionality and determines the file, line, class
   * and method of the calling context.
   *
   * @param string $message
   *
   * @param array $context
   *
   * @param int $shift Additional shift for determining the calling
   * context. Can be set to something larger than 0 if this method is called
   * from another logging method in order get the backtrace right.
   *
   * @param bool $showTrace
   *
   * @return void
   *
   * @see \OCP\ILogger
   */
  public function logFatal(string $message, array $context = [], int $shift = 0, bool $showTrace = false):void
  {
    $this->log(ILogger::FATAL, $message, $context, $shift + 1, $showTrace);
  }
}
