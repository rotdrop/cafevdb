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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\LegacyMigrationsService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\DatabaseProvider;

/** Test aspects of the LegacyMigrationsService class. */
#[Attributes\CoversClass(LegacyMigrationsService::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class LegacyMigrationsServiceTest extends TestCase
{
  private EntityManager $entityManager;

  private LegacyMigrationsService $migrationsService;

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
    // stop the server?
  }

  /** @return void */
  public function testGetAll(): void
  {
    $result = $this->migrationsService->getAll();
    $this->assertEquals('00000000000000', array_keys($result)[0]);
  }
}
