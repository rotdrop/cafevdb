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

use OCA\RotDrop\Tests\DatabaseProvider;

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Database\Doctrine\Migrations;
use OCA\CAFEVDB\Database\Doctrine\Migrations\EnumMigrationDirection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Maintenance\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Service\DoctrineMigrationsService;
use OCA\CAFEVDB\Service\MigrationsServiceInterface;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit\Console\ConsoleOutput;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;

/** Setup code for testing migrations. */
trait SetupMigrationTrait
{
  public const LATEST_VERSION = 'latest';
  public const FIRST_VERSION = 'first';

  private EntityManager $entityManager;

  private static DoctrineMigrationsService $migrationsService;

  private MockProvider $mockProvider;

  private DatabaseProvider $databaseProvider;

  private static array $appliedVersions = [];

  /**
   * Setup the migrations service and apply all migrations up to the given
   * version. This is used as setup code in order to test a specific
   * migration.
   *
   * @param string $upToVersion
   *
   * @return void
   */
  public function applyMigrations(string $upToVersion): void
  {
    /** @var MockProvider $mockProvider */
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    if (!$this->mockProvider->isServiceMocked(UserStorage::class)) {
      $userStorage = $this->createStub(UserStorage::class);
      $this->mockProvider->registerClassInstance(UserStorage::class, $userStorage, global: true);
    }

    $this->databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$this->databaseProvider->getDatabaseConfig()) {
      // echo 'STARTING DB SERVER' . PHP_EOL;
      $this->databaseProvider->startServer();
    }
    // print_r($this->databaseProvider->getDatabaseConfig());

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    $consoleLogger = new ConsoleLogger(
      consoleOutput: $this->createStub(ConsoleOutput::class),
      isCLI: false,
      logger: $this->mockProvider->getLoggerInterface(),
    );

    $appContainer = $this->mockProvider->getAppContainer();

    self::$migrationsService = new DoctrineMigrationsService(
      logger: $consoleLogger,
      entityManager: $this->entityManager,
      appContainer: $appContainer,
      l: $this->mockProvider->getL10N(),
    );

    $applied = self::$migrationsService->getApplied();
    $this->assertEqualsCanonicalizing(self::$appliedVersions, array_keys($applied));

    $latest = null;
    $unapplied = self::$migrationsService->getUnapplied();
    foreach (array_keys($unapplied) as $version) {
      if ($upToVersion != self::LATEST_VERSION && (int)$version > (int)$upToVersion) {
        break;
      }
      // echo 'APPLY ' . $version . PHP_EOL;
      self::$migrationsService->apply($version, EnumMigrationDirection::UP);
      $latest = $version;
      self::$appliedVersions[] = $version;
    }
    if ($upToVersion != self::LATEST_VERSION && $latest != $upToVersion) {
      throw new UnexpectedValueException("Migration '{$upToVersion}' does not seem to exist.");
    }

    // clear the cache in order not to have relicts of incomplete entities
    $this->entityManager->clear();
    $this->entityManager->getConfiguration()->getMetadataCache()->clear();
    $this->setDependencyInput(
      array_merge($this->dependencyInput(), [self::$migrationsService]),
    );
  }

  /** @return EntityManager */
  public function getEntityManager(): EntityManager
  {
    /** @var MockProvider $mockProvider */
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    return $this->entityManager;
  }

  /**
   * Undo applying migrations, i.e. go down to an empty database.
   *
   * @return void
   */
  public function unapplyMigrations(string $downBelow = self::FIRST_VERSION): void
  {
    $this->getEntityManager();

    foreach (array_reverse(self::$appliedVersions) as $version) {
      if ($downBelow != self::FIRST_VERSION && (int)$version < (int)$downBelow) {
        break;
      }
      self::$migrationsService->apply($version, EnumMigrationDirection::DOWN);
      array_pop(self::$appliedVersions);
      // echo 'UNAPPLY ' . $version . PHP_EOL;
    }

    if ($downBelow == self::FIRST_VERSION) {
      // The migrations table has to be dropped manually
      $sql = 'DROP TABLE IF EXISTS DoctrineMigrationsVersions';
      $connection = $this->entityManager->getConnection();
      $connection->prepare($sql)->executeQuery();

      // self::$migrationsService->clearCache();
      $this->assertEquals([], self::$migrationsService->getApplied());
      self::$appliedVersions = [];
    }

    // $service = $this->mockProvider->getAppContainer()->get(MigrationsServiceInterface::class);
    // echo get_class($service) . PHP_EOL;
  }
}
