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

use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260206193722;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\DriverException;

/** Test integer overflow for Unix epoche after 2028. */
#[Attributes\CoversClass(Version20260206193722::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Listener\CalendarObjectCreatedEventListener::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Listener\CalendarObjectUpdatedEventListener::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(Version19700101000001::class)]
#[Attributes\UsesClass(Version19700101000002::class)]
#[Attributes\UsesClass(Version19700101000003::class)]
#[Attributes\UsesClass(Version20260108084800::class)]
#[Attributes\UsesClass(Version20260108115432::class)]
#[Attributes\UsesClass(Version20260130130553::class)]
#[Attributes\UsesClass(Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\InvoiceNumberHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable\Encryption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerClosedEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEventEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\AbstractEntityManager::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\EncryptionContextTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UnusedTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class Version20260206193722Test extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use SetupMigrationTrait;

  /** @return void */
  public function testVersion20260206193722(): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->mockProvider->getUserSession()->method('isLoggedIn')->willReturn(true);
    $this->appContainer = $this->appContainer ?? $this->mockProvider->getAppContainer();
    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer->get(EventsService::class);
    $this->mockProvider->registerClassInstance(EventsService::class, $eventsService, global: true);

    // up to the previous
    $this->applyMigrations(upToVersion: '20260131090857');
    $this->generateCalendarBackend();

    // we actually here only need the project ...
    $this->generateProjectParticipant(persist: true);

    $initialNumberOfProjectEvents = $this->project->getCalendarEvents()->count();

    $eventData = [
      'summary' => 'Overflow Event',
      'from' => '20-01-2038',
      'to' => '27-01-2038', // should be converted to a repeating event
      'allDay' => true,
      'calendar' => 2,
      'categories' => $this->project->getName(),
    ];

    $result = $eventsService->newEvent($eventData);
    foreach (['uri', 'uid', 'event'] as $key) {
      $this->assertArrayHasKey($key, $result);
    }
    $exceptions = $this->entityManager->getTransactionExceptions();
    $this->assertEquals(1, count($exceptions));
    /** @var DriverException $exception */
    $exception = array_pop($exceptions);
    $this->assertInstanceOf(DriverException::class, $exception);
    $this->assertStringContainsString('Numeric value out of range', $exception->getMessage());

    // ok, it failed, should be cured by applying the migration in question ...
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations(upToVersion: $migration);

    // need to re-fetch as applyMigrations() empties the entity manager
    $this->project = $this->entityManager->find(Entities\Project::class, $this->project->getId());

    $result = $eventsService->newEvent($eventData);
    foreach (['uri', 'uid', 'event'] as $key) {
      $this->assertArrayHasKey($key, $result);
    }
    $this->assertEquals(8 + $initialNumberOfProjectEvents, $this->project->getCalendarEvents()->count());

    // unapply must now fail as the table contains values not representable with 32 bits
    $throwable = null;
    try {
      $this->unapplyMigrations(downBelow: $migration);
    } catch (Throwable $throwable) {
      // empty
    }
    $this->assertInstanceOf(DriverException::class, $throwable);
    $this->assertStringContainsString('Numeric value out of range', $throwable->getMessage());
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete(Entities\ProjectEvent::class, 'pe')->where($qb->expr()->gt('pe.recurrenceId', 0x7fffffff));
    $qb->getQuery()->execute();
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260206193722')]
  public function testUnapply(): void
  {
    $this->unapplyMigrations();
  }
}
