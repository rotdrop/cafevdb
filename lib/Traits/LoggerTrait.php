<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Traits;

use \OCP\ILogger;
use OCP\IL10N;

trait LoggerTrait
{
  /** @var ILogger */
  protected $logger;

  /** @var IL10N */
  protected $l;

  public function log(int $level, string $message, array $context = []) {
    return $this->logger->log($level, $message, $context);
  }

  public function logException($exception, $message = null) {
    //empty($message) && ($message = $this->l->t("Caught an Exception"));
    empty($message) && ($message = "Caught an Exception");
    $this->logger->logException($exception, [ 'message' => $message ]);
  }

  public function logError(string $message, array $context = []) {
    return $this->log(ILogger::ERROR, $message, $context);
  }

  public function logDebug(string $message, array $context = []) {
    return $this->log(ILogger::DEBUG, $message, $context);
  }

  public function logInfo(string $message, array $context = []) {
    return $this->log(ILogger::INFO, $message, $context);
  }

  public function logWarn(string $message, array $context = []) {
    return $this->log(ILogger::WARN, $message, $context);
  }

  public function logFatal(string $message, array $context = []) {
    return $this->log(ILogger::FATAL, $message, $context);
  }

}
