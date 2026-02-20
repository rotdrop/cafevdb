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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Repositories;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository;

/** Test aspects of the ORM InstrumentsRepository. */
#[Attributes\CoversClass(InstrumentsRepository::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260206193722::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260207000624::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class InstrumentsRepositoryTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

  private InstrumentsRepository $repository;

  /** @return InstrumentsRepository */
  public function getRepository(): InstrumentsRepository
  {
    $this->repository = $this->repository ?? $this->getEntityManager()->getRepository(Entities\Instrument::class);
    return $this->repository;
  }

  private const EXPECTED_INFO_KEYS = [
    'families', 'byId', 'byName', 'idGroups', 'nameGroups',
  ];

  /** @return void */
  public function testDatabaseSetup(): void
  {
    $this->applyMigrations('latest');
    $this->assertEquals([], self::$migrationsService->getUnapplied());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testDatabaseSetup')]
  public function testDescribeAll(): void
  {
    $this->getRepository();
    $instrumentInfo = $this->repository->describeALL();
    $instruments = $this->repository->findAll();
    $this->assertEqualsCanonicalizing(self::EXPECTED_INFO_KEYS, array_keys($instrumentInfo));
    $allGroups = [];
    /** @var Entities\Instrument $instrument */
    foreach ($instruments as $instrument) {
      $name = $instrument->getName();
      $id = $instrument->getId();
      $this->assertNotEmpty($instrumentInfo['nameGroups'][$name]);
      $this->assertEquals($name, $instrumentInfo['byName'][$name]);
      $this->assertEquals($name, $instrumentInfo['byId'][$id]);
      $families = $instrument->getFamilies()->map(fn(Entities\InstrumentFamily $family) => $family->getFamily())->toArray();
      sort($families);
      $families = implode(',', $families);
      $this->assertEquals($families, $instrumentInfo['nameGroups'][$name]);
      $this->assertEquals($families, $instrumentInfo['idGroups'][$id]);
      $allGroups[] = $families;
    }
    $allGroups = array_unique($allGroups);
    $this->assertEqualsCanonicalizing($allGroups, $instrumentInfo['families']);
  }

  /** @return void */
  #[Attributes\Depends('testDatabaseSetup')]
  public function testFindNonInstruments(): void
  {
    $this->getRepository();
    $result = $this->repository->findNonInstruments();
    /** @var Entities\Instrument $nonInstrument */
    foreach ($result as $nonInstrument) {
      $this->assertEquals(
        Entities\ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY,
        $nonInstrument->getFamilies()->first()->getUntranslatedFamily(),
      );
    }
    $this->assertTrue(count($result) > 1);
    $only = reset($result)->getUntranslatedName();
    $onlyResult = $this->repository->findNonInstruments(only: [$only]);
    $this->assertEquals(1, count($onlyResult));
    $this->assertEquals($result[0], $onlyResult[0]);

    // should fail as translations have not yet been flushed.
    $only = reset($result)->getName();
    $onlyResult = $this->repository->findNonInstruments(only: [$only]);
    $this->assertEquals(0, count($onlyResult));

    // flush should "fix" this.
    $this->entityManager->flush();
    $onlyResult = $this->repository->findNonInstruments(only: [$only]);
    $this->assertEquals(1, count($onlyResult));
    $this->assertEquals($result[0], $onlyResult[0]);
  }

  /**
   * Use by the ContactsService class.
   *
   * @return void
   */
  #[Attributes\Depends('testDatabaseSetup')]
  public function testFindNames(): void
  {
    $this->getRepository();
    $all = $this->repository->findAll();
    $result = $this->repository->findNames();

    $this->assertEquals(count($all), count($result));
  }

  /** @return void */
  #[Attributes\Depends('testDescribeAll')]
  #[Attributes\Depends('testFindNonInstruments')]
  #[Attributes\Depends('testFindNames')]
  public function testDatabaseTeardown(): void
  {
    $this->unapplyMigrations();
  }
}
