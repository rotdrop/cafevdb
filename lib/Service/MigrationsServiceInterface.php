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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\Doctrine\Migrations\EnumMigrationDirection;

/**
 * Interface for database migration services. The actual implementation
 * (e.g. Doctrine\Migrations) typically provides much more functionality
 * through a CLI interface. The non-CLI service class provides means to check
 * if migrations are pending and to execute "up"-migrations. Going backwards
 * in time is not intended here.
 */
interface MigrationsServiceInterface
{
  /**
   * @return array All unapplied migrations. The migration classes are
   * instatiated using depency injection with the app-container.
   */
  public function getUnapplied(): array;

  /**
   * Apply the migration with the given version.
   *
   * @param string $version
   *
   * @param EnumMigrationDirection $direction
   *
   * @return void
   *
   * @throws InvalidArgumentException If the given migration does not exist.
   */
  public function apply(string $version, EnumMigrationDirection $direction = EnumMigrationDirection::UP): void;
}
