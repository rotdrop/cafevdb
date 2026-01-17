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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use InvalidArgumentException;
use ReflectionClass;

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

/** Test aspects of the DoctrineMigrationsService class. */
#[Attributes\CoversClass(DoctrineMigrationsListener::class)]
#[Attributes\CoversClass(DoctrineMigrationsService::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000001::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000002::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000003::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260108084800::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260108115432::class)]
#[Attributes\CoversClass(Migrations\AbstractMigration::class)]
#[Attributes\CoversClass(Migrations\DependencyFactory::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class DoctrineMigrationsServiceTest extends TestCase
{
  private EntityManager $entityManager;

  private DoctrineMigrationsService $migrationsService;

  private IL10N $l;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

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

    $this->l = $mockProvider->getL10N();

    $this->migrationsService = new DoctrineMigrationsService(
      logger: $consoleLogger,
      entityManager: $this->entityManager,
      appContainer: $appContainer,
      l: $this->l,
    );
  }

  /** @return void */
  public function testGetDependencyFactory(): void
  {
    $factory = $this->migrationsService->getDependencyFactory();
    $this->assertInstanceOf(DependencyFactory::class, $factory);
  }

  private const UNAPPLIED = [
    '19700101000001' => 'Initial database setup.',
  ];

  /** @return void */
  public function testGetUnapplied(): void
  {
    $result = $this->migrationsService->getUnapplied();
    $this->assertArrayHasKey(array_keys(self::UNAPPLIED)[0], $result);
    $first = reset($result);
    $expected = array_values(self::UNAPPLIED)[0];
    $expected = [$expected, $this->l->t($expected)];
    $this->assertTrue(in_array($first, $expected));
  }

  /** @return void */
  public function testApplyInvalidArgument(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->migrationsService->apply('HutzliPutzli');
  }

  /** @return void */
  public function testApply(): void
  {
    $unapplied = $this->migrationsService->getUnapplied();
    foreach (array_keys($unapplied) as $version) {
      $this->migrationsService->apply($version, EnumMigrationDirection::UP);
    }
    $result = $this->migrationsService->getUnapplied();
    $this->assertEquals([], $result);
    $result = $this->migrationsService->getApplied();
    $this->assertEquals(count($unapplied), count($result));
    foreach (array_reverse(array_keys($unapplied)) as $version) {
      $this->migrationsService->apply($version, EnumMigrationDirection::DOWN);
    }
    $result = $this->migrationsService->getUnapplied();
    $this->assertEquals(count($unapplied), count($result));
    $result = $this->migrationsService->getApplied();
    $this->assertEquals(0, count($result));
  }
}
