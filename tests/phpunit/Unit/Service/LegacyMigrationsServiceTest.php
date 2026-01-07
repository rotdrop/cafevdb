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

use ReflectionClass;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Common\ConsoleOutput;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Maintenance\Migrations\Legacy as LegacyMigrations;
use OCA\CAFEVDB\Service\DoctrineMigrationsService;
use OCA\CAFEVDB\Service\LegacyMigrationsService;
use OCA\CAFEVDB\Service\MigrationsServiceInterface;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\EventSubscriber;
use OCA\CAFEVDB\Wrapped\Gedmo\Loggable\LoggableListener;

/** Test aspects of the LegacyMigrationsService class. */
#[Attributes\CoversClass(LegacyMigrationsService::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Migration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerClosedEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddBalancingAccountToProjectParticipantFieldEntities::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddDonationFlagToProjectPayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddGenderToMusician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddMoreAddressFieldsToMusician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddParticipationContextToProjectParticipantFields::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddProjectRegistrationDeadline::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddPurposeFieldToInvoices::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\AddRunCountToMigrationRecords::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CorrectLanguageField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateExplodeFunction::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateGnuCashTables::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableDoctrineMigrationsVersions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableDonationReceipts::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableInvoices::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableInvoicesV2::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableLegalPersons::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableProjectApplications::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTableTaxationStatutorySources::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateTaxExemptionNotices::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\CreateWebBrowserHistoryTables::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\DBALFour::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\DecryptShareOwner::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\DisableCloudAccountsByDefault::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\DropTableLegalPersons::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\Dummy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\EnsureNotAnInstrumentBusinessRelation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\FixInsuranceRates::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\FixTaxExemptionNotices::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\GeoPostalCodesIndices::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\GroupSharedOrchestraFolder::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\InitialDatabaseSetup::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\MakeEmailDraftSubjectOptional::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\MakeProjectPaymentsSubjectNullable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\NativeEnums::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\ParticipantDropBankAccountColumns::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\ParticipantFieldAccessEnum::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\ParticipantFieldDropUnusedColumns::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\ParticipantFieldsAddLiabilities::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\ProjectEventsAddAbsenceFields::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\RenameInvoicesNotificationEmailColumn::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\RenameMemberStatusParticipationStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\SanitizeDatabaseStorageIdentifiers::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\SanitizeMailmanTemplateFiles::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\SanitizeSentEmailAssociations::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\SepaBulkTransactionsBalancingData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\UpdateTableTaxationStatutorySources::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Legacy\UseDecimalForExactFractions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260106233236::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class LegacyMigrationsServiceTest extends TestCase
{
  private EntityManager $entityManager;

  private LegacyMigrationsService $migrationsService;

  private MockProvider $mockProvider;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $this->mockProvider = $mockProvider = MockProvider::create($this);

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    $this->entityManager = $mockProvider->getEntityManager();

    $this->migrationsService = new LegacyMigrationsService(
      entityManager: $this->entityManager,
      logger: $mockProvider->getLoggerInterface(),
      appContainer: $mockProvider->getAppContainer(),
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    $this->entityManager->close();
    // stop the server?
  }

  /** @return void */
  public function testGetAll(): void
  {
    $result = $this->migrationsService->getAll();
    $this->assertEquals('00000000000000', array_keys($result)[0]);
  }

  /** @return void */
  public function testGetMigrationServiceInterface(): void
  {
    $service = $this->mockProvider->getAppContainer()->get(MigrationsServiceInterface::class);
    $this->assertInstanceOf(LegacyMigrationsService::class, $service);
  }

  /** @return void */
  public function testApplyFinal(): void
  {
    $result = $this->migrationsService->getAll();
    $finalVersion = array_key_last($result);
    $className = LegacyMigrations::class . '\\Version' . $finalVersion;
    $finalInstance = $this->mockProvider->getAppContainer()->get($className);
    $this->assertInstanceOf(LegacyMigrations\CreateTableDoctrineMigrationsVersions::class, $finalInstance);

    $createTables = [];
    $createTables[] = <<<'SQL'
CREATE TABLE ExtLogEntries (
  id INT AUTO_INCREMENT NOT NULL,
  action VARCHAR(8) NOT NULL,
  logged_at DATETIME(6) NOT NULL,
  object_class VARCHAR(191) NOT NULL,
  version INT NOT NULL,
  data LONGTEXT DEFAULT NULL,
  username VARCHAR(191) DEFAULT NULL,
  remote_address VARCHAR(45) DEFAULT NULL,
  object_id VARCHAR(573) DEFAULT NULL,
  INDEX log_class_lookup_idx (object_class),
  INDEX log_date_lookup_idx (logged_at),
  INDEX log_user_lookup_idx (username),
  INDEX log_version_lookup_idx (object_id, object_class, version),
  INDEX log_action_lookup_idx (action, object_class),
  INDEX log_action_class_lookup_idx (action),
  PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 ROW_FORMAT = DYNAMIC
SQL;
    $createTables[] = <<<'SQL'
CREATE TABLE Migrations (
  version CHAR(14) NOT NULL COLLATE `ascii_general_ci`,
  migration_class_name VARCHAR(512) NOT NULL,
  run_count INT DEFAULT 1 NOT NULL,
  created DATETIME(6) DEFAULT NULL,
  updated DATETIME(6) DEFAULT NULL,
  PRIMARY KEY (version)
) DEFAULT CHARACTER SET utf8mb4
SQL;
    $connection = $this->entityManager->getConnection();
    foreach ($createTables as $sql) {
      $connection->prepare($sql)->executeQuery();
    }
    $this->migrationsService->apply($finalVersion);

    $consoleLogger = new ConsoleLogger(
      consoleOutput: $this->createStub(ConsoleOutput::class),
      isCLI: false,
      logger: $this->mockProvider->getLoggerInterface(),
    );

    $doctrineMigrationsService = new DoctrineMigrationsService(
      logger: $consoleLogger,
      entityManager: $this->entityManager,
      appContainer: $this->mockProvider->getAppContainer(),
      l: $this->mockProvider->getL10N(),
    );

    $applied = $doctrineMigrationsService->getApplied();
    $this->assertEquals(1, count($applied));

    $service = $this->mockProvider->getAppContainer()->get(MigrationsServiceInterface::class);
    $this->assertInstanceOf(DoctrineMigrationsService::class, $service);

    foreach (['Migrations', 'ExtLogEntries', 'DoctrineMigrationsVersions'] as $table) {
      $connection->prepare('DROP TABLE ' . $table)->executeQuery();
    }


  }
}
