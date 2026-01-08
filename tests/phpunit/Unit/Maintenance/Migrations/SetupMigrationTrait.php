<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations;

use UnexpectedValueException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCP\IL10N;

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Common\ConsoleOutput;
use OCA\CAFEVDB\Database\Doctrine\Migrations;
use OCA\CAFEVDB\Database\Doctrine\Migrations\EnumMigrationDirection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Maintenance\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Service\DoctrineMigrationsService;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;

/** Setup code for testing migrations. */
trait SetupMigrationTrait
{
  private EntityManager $entityManager;

  private DoctrineMigrationsService $migrationsService;

  private IL10N $l;

  private MockProvider $mockProvider;

  private array $appliedVersions = [];

  /**
   * Setup the migrations service and apply all migrations up to the given
   * version. This is used as setup code in order to test a specific
   * migration.
   *
   * @param string $upToVersion
   *
   * @return void
   */
  public function setup(string $upToVersion): void
  {
    /** @var MockProvider $mockProvider */
    $this->mockProvider = $mockProvider = MockProvider::create($this);

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    $this->entityManager = $mockProvider->getEntityManager();

    $consoleLogger = new ConsoleLogger(
      consoleOutput: $this->createStub(ConsoleOutput::class),
      isCLI: false,
      logger: $mockProvider->getLoggerInterface(),
    );

    $appContainer = $mockProvider->getAppContainer();

    $this->migrationsService = new DoctrineMigrationsService(
      logger: $consoleLogger,
      entityManager: $this->entityManager,
      appContainer: $appContainer,
      l: $mockProvider->getL10N(),
    );

    $latest = null;
    $unapplied = $this->migrationsService->getUnapplied();
    foreach (array_keys($unapplied) as $version) {
      if ($upToVersion != 'latest' && (int)$version > (int)$upToVersion) {
        break;
      }
      $this->migrationsService->apply($version, EnumMigrationDirection::UP);
      $latest = $version;
      $this->appliedVersions[] = $version;
    }
    if ($upToVersion != 'latest' && $latest != $upToVersion) {
      throw new UnexpectedValueException("Migration '{$upToVersion}' does not seem to exist.");
    }

    // clear the cache in order not to have relicts of incomplete entities
    $this->entityManager->clear();
    $this->entityManager->getConfiguration()->getMetadataCache()->clear();
    $this->setDependencyInput(
      array_merge($this->dependencyInput(), [$this->migrationsService]),
    );
  }

  /**
   * Undo applying migrations, i.e. go down to an empty database.
   *
   * @return void
   */
  public function tearDown(): void
  {
    foreach (array_reverse($this->appliedVersions) as $version) {
      $this->migrationsService->apply($version, EnumMigrationDirection::DOWN);
    }
  }
}
