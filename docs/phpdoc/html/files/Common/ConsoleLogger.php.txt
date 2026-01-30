<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console;

use OCP\ILogger;

/** A PSR logger which logs to the console if in CLI mode. */
class ConsoleLogger extends AbstractLogger
{
  /** {@inheritdoc} */
  public function __construct(
    ConsoleOutput $consoleOutput,
    bool $isCLI,
    protected LoggerInterface $logger,
  ) {
    if ($isCLI) {
      $this->logger = new Console\Logger\ConsoleLogger($consoleOutput);
    }
  }

  /** {@inheritdoc} */
  public function log($level, string|\Stringable $message, array $context = []): void
  {
    switch ($level) {
      case ILogger::DEBUG:
        $level = LogLevel::DEBUG;
        break;
      case ILogger::INFO:
        $level = LogLevel::INFO;
        break;
      case ILogger::WARN:
        $level = LogLevel::WARNING;
        break;
      case ILogger::ERROR:
        $level = LogLevel::ERROR;
        break;
      case ILogger::FATAL:
        $level = LogLevel::EMERGENCY;
        break;
      default:
        // pass
        break;
    }
    $this->logger->log($level, $message, $context);
  }
}
