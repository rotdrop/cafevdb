<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use Psr\Log\LogLevel;

use OCA\CAFEVDB\Common\ConsoleLogger;

/**
 * Doctrine\Deprecations\Deprecation always logs with level 'notice'. We wrap
 * therefore another Psr-logger to just map all levels to the wanted one.
 */
class DeprecationLogger extends ConsoleLogger
{
  /** @var mixed $logLevel */
  protected mixed $logLevel = null;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private ConsoleLogger $actualLogger,
    private bool $isCLI,
  ) {
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
    $level = $this->isCLI ? LogLevel::WARNING : $this->logLevel;
    $this->actualLogger->log($level, $message, $context);
  }
}
