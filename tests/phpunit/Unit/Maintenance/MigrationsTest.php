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

namespace OCA\CAFEVDB\Tests\Unit\Maintenance;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

/** Test aspects of all migrations. */
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000001::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000002::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version19700101000003::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260108084800::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260108115432::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260130130553::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260131090857::class)]
#[Attributes\CoversClass(MigrationsNamespace\Version20260206193722::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class MigrationsTest extends TestCase
{
  use SetupMigrationTrait;

  /** @return void */
  public function testVersion19700101000001(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion19700101000001')]
  public function testVersion19700101000002(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testVersion19700101000002')]
  public function testInstruments(): void
  {
    $this->getEntityManager();
    $instruments = $this->entityManager->getRepository(Entities\Instrument::class)->findAll();
    $this->assertEquals(
      count(MigrationsNamespace\Version19700101000002::INSTRUMENTS)
      +
      count(Entities\ProjectInstrument::NON_INSTRUMENTS),
      count($instruments),
    );
    foreach (MigrationsNamespace\Version19700101000002::INSTRUMENTS as $instrumentName => $instrumentInfo) {
      $instrument = $this->entityManager->getRepository(Entities\Instrument::class)->findOneBy(['name' => $instrumentName]);
      $this->assertInstanceOf(Entities\Instrument::class, $instrument);
      foreach ($instrumentInfo['families'] as $familyName) {
        $family = $instrument->getFamilies()->get($familyName);
        $this->assertInstanceOf(Entities\InstrumentFamily::class, $family);
        $this->assertEquals($instrument, $family->getInstruments()->get($instrument->getUntranslatedName()));
      }
    }
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testVersion19700101000002')]
  public function testFamilies(): void
  {
    $familyInstruments = [];
    foreach (MigrationsNamespace\Version19700101000002::INSTRUMENTS as $instrumentName => $instrumentInfo) {
      foreach ($instrumentInfo['families'] as $familyName) {
        $familyInstruments[$familyName][] = $instrumentName;
      }
    }
    $this->getEntityManager();
    foreach (MigrationsNamespace\Version19700101000002::INSTRUMENT_FAMILY_NAMES as $familyName) {
      $family = $this->entityManager->getRepository(Entities\InstrumentFamily::class)->findOneBy(['family' => $familyName]);
      $this->assertInstanceOf(Entities\InstrumentFamily::class, $family);
      $instrumentNames = array_unique($familyInstruments[$familyName] ?? []);
      foreach ($instrumentNames as $instrumentName) {
        $instrument = $family->getInstruments()->get($instrumentName);
        $this->assertInstanceOf(Entities\Instrument::class, $instrument);
        $this->assertEquals($family, $instrument->getFamilies()->get($family->getUntranslatedFamily()));
      }
    }
  }

  /** @return void */
  #[Attributes\Depends('testVersion19700101000002')]
  public function testVersion19700101000003(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion19700101000003')]
  public function testUuidFunctions(): void
  {
    $uuid = Uuid::create();
    $this->getEntityManager();
    $connection = $this->entityManager->getConnection();

    $sql = 'SELECT BIN2UUID(UUID2BIN("' . (string)$uuid . '")) AS uuid, UUID2BIN("' . (string)$uuid . '") AS binUuid';
    $stmt = $connection->executeQuery($sql);
    $result = $stmt->fetchAssociative();
    $stmt->free();
    $this->assertEquals((string)$uuid, $result['uuid']);
    $this->assertEquals($uuid->getBytes(), $result['binUuid']);

    $sql = 'SELECT BIN_TO_UUID(UUID_TO_BIN("' . (string)$uuid . '", 1), 1) AS uuid';
    $stmt = $connection->executeQuery($sql);
    $result = $stmt->fetchAssociative();
    $stmt->free();
    $this->assertEquals((string)$uuid, $result['uuid']);
  }

  /** @return void */
  #[Attributes\Depends('testVersion19700101000003')]
  public function testDatabaseFunctionExplode(): void
  {
    $data = ['a', 'b', 'c'];
    $delim = ',';
    $string = implode($delim, $data);

    $connection = $this->getEntityManager()->getConnection();
    $sql = [];
    foreach ($data as $index => $item) {
      ++$index; // index starts at 1
      $sql[] = "EXPLODE('{$delim}', '{$string}', {$index}) AS {$item}";
    }
    $sql = 'SELECT ' . implode(',', $sql);
    $stmt = $connection->executeQuery($sql);
    $result = $stmt->fetchAssociative();
    $this->assertEquals(array_combine($data, $data), $result);
    $stmt->free();
  }

  /** @return void */
  #[Attributes\Depends('testVersion19700101000003')]
  public function testVersion20260108084800(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260108084800')]
  public function testVersion20260108115432(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260108115432')]
  public function testVersion20260130130553(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260130130553')]
  public function testVersion20260131090857(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260131090857')]
  public function testVersion20260206193722(): void
  {
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations($migration);
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260206193722')]
  public function testUpToLatest(): void
  {
    $this->applyMigrations('latest');
  }

  /** @return void */
  #[Attributes\Depends('testUpToLatest')]
  public function testUnapply(): void
  {
    $this->unapplyMigrations();
  }
}
