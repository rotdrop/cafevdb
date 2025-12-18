<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Psr\Log\AbstractLogger;
use OCP\ILogger;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Doctrine\Deprecations\Deprecation always logs with level 'notice'. We wrap
 * therefore another Psr-logger to just map all levels to the wanted one.
 */
class DeprecationLogger extends AbstractLogger
{
  /** @var mixed $logLevel */
  protected mixed $logLevel = null;

  protected LoggerInterface $actualLogger;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private LoggerInterface $logger,
    private bool $isCLI,
  ) {
    if ($isCLI) {
      \OCP\Server::get(\Psr\Log\LoggerInterface::class)->info('BLAH', ['exception' => new \Exception('BLAH') ]);
      // ignore the provided logger and instead log to the console
      $this->actualLogger = new ConsoleLogger(new ConsoleOutput);
    } else {
      $this->actualLogger = $logger;
    }
  }
  // phpcs:enable

  /**
   * @param mixed $level
   *
   * @return self
   */
  public function setLogLevel(mixed $level): self
  {
    $this->logLevel = $level;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getLogLevel(): mixed
  {
    return $this->logLevel;
  }

  /** {@inheritdoc} */
  public function log($level, string|\Stringable $message, array $context = []): void
  {
    $level = $this->isCLI ? LogLevel::CRITICAL : $this->logLevel;
    $this->actualLogger->log($level, $message, $context);
  }
}
