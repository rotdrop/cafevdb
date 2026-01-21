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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Util;

use ReflectionClass;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the entity serializer with fake entities. */
#[Attributes\CoversClass(EntitySerializer::class)]
#[Attributes\CoversMethod(EntitySerializer::class, 'addEntity')]
#[Attributes\CoversMethod(EntitySerializer::class, 'export')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDatum::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectPayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReference::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReferenceCollection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailPersistanceListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\SanitizerRegistration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\AbstractSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\GoogleMailSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
class EntitySerializerTest extends TestCase
{
  use EntityGeneratorTrait;

  private EntityManager $entityManager;

  private EntitySerializer $entitySerializer;

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

    $this->generateProjectParticipant(persist: false);

    $this->entityManager = $mockProvider->getEntityManager();

    $this->generateCompositePayment();

    $this->entityManager->persist($this->musician);

    $this->entitySerializer = new EntitySerializer(
      entityManager: $this->entityManager,
      l: $mockProvider->getL10N(),
      logger: $mockProvider->getLoggerInterface(),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    $this->entityManager->detach($this->musician);
    foreach ($this->musician->getEmailAddresses() as $emailAddress) {
      $this->entityManager->detach($emailAddress);
    }
  }

  /** @return void */
  public function testAddEntity(): void
  {
    // $this->expectNotToPerformAssertions();
    $this->entitySerializer->addEntity($this->musician);
  }

  /** @return void */
  public function testExport(): void
  {
    $this->entitySerializer->addEntity($this->musician);
    $exportData = $this->entitySerializer->export();
    json_encode($exportData, JSON_PRETTY_PRINT);
    $this->assertInstanceOf(Util\EntityResponse::class, $exportData);
    $this->assertArrayHasKey(Entities\Musician::class, $exportData->entities);
    $this->assertArrayHasKey(Entities\Musician::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\SepaBankAccount::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\ProjectParticipant::class, $exportData->repositories);
  }

  /** @return void */
  public function testExportWithShortNames(): void
  {
    $this->entitySerializer->reset();
    $nameSpaceName = new ReflectionClass(Entities\Musician::class)->getNamespaceName();
    $this->entitySerializer->setCommonPrefix($nameSpaceName);
    $this->entitySerializer->addEntity($this->musician);
    $exportData = $this->entitySerializer->export();
    $jsonData = json_encode($exportData, JSON_PRETTY_PRINT);
    $this->assertGreaterThan(0, strlen($jsonData));
    $this->assertInstanceOf(Util\EntityResponse::class, $exportData);
    $this->assertArrayHasKey(new ReflectionClass(Entities\Musician::class)->getShortName(), $exportData->entities);
    $this->assertArrayHasKey(new ReflectionClass(Entities\Musician::class)->getShortName(), $exportData->repositories);
    $this->assertArrayHasKey(new ReflectionClass(Entities\SepaBankAccount::class)->getShortName(), $exportData->repositories);
    $this->assertArrayHasKey(new ReflectionClass(Entities\ProjectParticipant::class)->getShortName(), $exportData->repositories);

    // Test for index-by stringification of UUIDs
    foreach ($this->musician->getProjectParticipantFieldsData()->getKeys() as $key) {
      $uuidInstance = Uuid::fromBytes($key);
      $uuidString = (string)$uuidInstance;
      $this->assertArrayHasKey(
        $uuidString,
        $exportData->repositories['Musician'][$this->musician->getId()]['projectParticipantFieldsData']->entities,
      );
    }

    $data = json_decode(json_encode($exportData), true);
    array_walk_recursive(
      $data,
      function(mixed $value, mixed $key) use ($nameSpaceName) {
        if (is_string($value)) {
          $this->assertFalse(str_starts_with($value, $nameSpaceName), "Array value {$value} for key {$key} should not start with {$nameSpaceName}");
        }
        if (is_string($key)) {
          $this->assertFalse(str_starts_with($key, $nameSpaceName), "Array key {$key} with value {$value} should not start with {$nameSpaceName}");
        }
      },
    );
  }

  /** @return void */
  public function testDuplicateEntities(): void
  {
    $this->entitySerializer->addEntity($this->musician);
    $this->entitySerializer->addEntity($this->musician);
    $exportData = $this->entitySerializer->export();
    $this->assertEquals(1, count($exportData->entities[Entities\Musician::class]));
    $this->assertEquals(1, count($exportData->repositories[Entities\Musician::class]));
    $this->assertEquals(1, count($exportData->repositories[Entities\ProjectParticipant::class]));
    $this->assertEquals(1, count($exportData->repositories[Entities\SepaBankAccount::class]));
  }

  /** @return void */
  public function testDeepen(): void
  {
    $this->entitySerializer->addEntity($this->musician, depth: 1);
    $this->entitySerializer->addEntity($this->musician, depth: 2);
    $exportData = $this->entitySerializer->export();
    $this->assertEquals(1, count($exportData->entities[Entities\Musician::class]));
    $this->assertArrayHasKey(Entities\Musician::class, $exportData->entities);
    $this->assertArrayHasKey(Entities\Musician::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\Project::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\ProjectParticipant::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\SepaBankAccount::class, $exportData->repositories);
    $this->assertArrayHasKey(Entities\MusicianEmailAddress::class, $exportData->repositories);
    // $json = json_encode($exportData, JSON_PRETTY_PRINT);
  }
}
