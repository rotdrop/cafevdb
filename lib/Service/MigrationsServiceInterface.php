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
   * Check whether there need any migrations to be applied.
   *
   * @return bool
   */
  public function needsMigration(): bool;

  /**
   * Apply all found migrations, stop when one is failing.
   *
   * @return void
   */
  public function applyAll(): void;

  /**
   * @return array All unapplied migrations. The migration classes are
   * instatiated using depency injection with the app-container.
   */
  public function getUnapplied(): array;

  /**
   * @return array All applied migrations. The migration classes are
   * instatiated using depency injection with the app-container.
   */
  public function getApplied(): array;

  /**
   * @return array Get all migration classes, instantiate all of them via
   * dependency injection with the app-container.
   */
  public function getAll(): array;

  /** @return string The latest applied migration, if any. */
  public function getLatest(): ?string;

  /**
   * Apply the migration with the given version.
   *
   * @param string $version
   *
   * @return void
   *
   * @throws InvalidArgumentException
   */
  public function apply(string $version): void;

  /**
   * Get the description of the migration with the given version.
   *
   * @param string $version
   *
   * @return null|string The description or null if the migration could not be found.
   */
  public function description(string $version): string;
}
