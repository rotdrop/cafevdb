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

/**
 * Doctrine\Deprecations\Deprecation always logs with level 'notice'. We wrap
 * therefore another Psr-logger to just map all levels to the wanted one.
 */
class DeprecationLogger extends CloudLoggerWrapper
{
  /** @var null|int $logLevel */
  protected $logLevel = null;

  /**
   * @param null|int $level
   *
   * @return void
   */
  public function setLogLevel(null|int $level)
  {
    $this->logLevel = $level;
  }

  /**
   * @return null|int
   */
  public function getLogLevel():?int
  {
    return $this->logLevel;
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
    if ($this->logLevel !== null) {
      return $this->logLevel;
    }
    return parent::mapLogLevels($level);
  }
}
