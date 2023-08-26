<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine;

use Psr\Log\LoggerInterface as CloudLogger;
use OCP\ILogger;
use OCA\CAFEVDB\Wrapped\Psr\Log\LogLevel;
use OCA\CAFEVDB\Wrapped\Psr\Log\AbstractLogger;

/**
 * Due to namespace wrapping we cannot pass the cloud-logger directly to the
 * wrapped doctrine framework.
 */
class CloudLoggerWrapper extends AbstractLogger
{
  /** @var CloudLogger */
  protected $cloudLogger;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(CloudLogger $cloudLogger)
  {
    $this->cloudLogger = $cloudLogger;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function log($level, string|\Stringable $message, array $context = []):void
  {
    $level = $this->mapLogLevels($level);
    $this->cloudLogger->log($level, $message, $context);
  }

  /**
   * Map PSR log-levels to ILogger log-levels as the PsrLoggerAdapter only
   * understands those.
   *
   * @param mixed $level
   *
   * @return mixed
   */
  protected function mapLogLevels(mixed $level):int
  {
    if (is_int($level) || is_numeric($level)) {
      return (int)$level;
    }
    switch ($level) {
      case LogLevel::EMERGENCY:
        return ILogger::FATAL;
      case LogLevel::ALERT:
        return ILogger::ERROR;
      case LogLevel::CRITICAL:
        return ILogger::ERROR;
      case LogLevel::ERROR:
        return ILogger::ERROR;
      case LogLevel::WARNING:
        return ILogger::WARN;
      case LogLevel::NOTICE:
        return ILogger::INFO;
      case LogLevel::INFO:
        return ILogger::INFO;
      case LogLevel::DEBUG:
        return ILogger::DEBUG;
      default:
        return ILogger::ERROR;
    }
  }
}
