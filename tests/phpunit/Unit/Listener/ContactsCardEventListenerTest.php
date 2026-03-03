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

namespace OCA\CAFEVDB\Tests\Unit\Listener;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use Sabre\VObject;

use Psr\Container\ContainerInterface;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\Image;

use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardMovedEvent;
use OCA\DAV\Events\CardUpdatedEvent;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Listener\ContactsCardEventListener as TestedClass;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\L10N\AppL10N;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ContactsCardEventListener class. */
#[Attributes\CoversClass(ContactsService::class)]
#[Attributes\CoversClass(ProjectService::class)]
#[Attributes\CoversClass(TestedClass::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableFolderCreate::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrumentationNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianInstrumentEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectInstrumentEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260206193722::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260207000624::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
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
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ContactsCardEventListenerTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;

  private const ADDRESS_BOOK_URI = 'addressbook';
  private const SHARED_ADDRESS_BOOK_URI = 'addressbook_shared_by_blahblah';

  private TestedClass $instance;

  private MockProvider $mockProvider;

  private ContainerInterface $appContainer;

  private AppL10N $appL10n;

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  private array $addressBooks = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');
    \OCA\CAFEVDB\Wrapped\Doctrine\Deprecations\Deprecation::enableWithTriggerError();

    $this->generateCalendarBackend();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $this->generateProjectParticipant(persist: true, now: $this->now, delete: false);
      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();
      $this->generateInstruments(persist: true);
      self::$migrationsApplied = true;
    } else {
      $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

      $this->project = $this->entityManager->find(Entities\Project::class, self::$projectId);
      $this->musician = $this->entityManager->find(Entities\Musician::class, self::$musicianId);
      $participant = $this->musician->getProjectParticipantOf($this->project);
      if ($participant) {
        $this->participant = $participant;
      }
      $this->assertNotNull($this->project);
      $this->assertNotNull($this->musician);
    }

    $avatarManager = $this->createStub(IAvatarManager::class);
    $image = $this->createStub(Image::class);
    $image->method('mimeType')->willReturn('a string');
    $image->method('data')->willReturn('a data string');
    $avatar = $this->createStub(IAvatar::class);
    $avatar->method('get')->willReturn($image);
    $avatarManager->method('getAvatar')->willReturn($avatar);
    $this->mockProvider->registerClassInstance(IAvatarManager::class, $avatarManager, global: true);

    $contactsManager = $this->createStub(IContactsManager::class);
    $this->addressBooks[self::ADDRESS_BOOK_URI] = $this->createStub(IAddressBook::class);
    $this->addressBooks[self::SHARED_ADDRESS_BOOK_URI] = $this->createStub(IAddressBook::class);
    foreach ($this->addressBooks as $key => $addressBook) {
      $addressBook->method('getUri')->willReturn($key);
    }
    $contactsManager->method('getUserAddressBooks')->willReturnCallback(fn() => $this->addressBooks);
    $this->mockProvider->registerClassInstance(IContactsManager::class, $contactsManager, global: true);

    $this->appContainer = $this->mockProvider->getAppContainer();
    $configService = $this->mockProvider->getConfigService();
    $participantFieldsService = $this->appContainer->get(ProjectParticipantFieldsService::class);

    $this->getUserStorageStub();
    $this->mockProvider->registerClassInstance(UserStorage::class, $this->userStorage, global: true);

    $projectService = new ProjectService(
      configService: $configService,
      entityManager: $this->entityManager,
      userStorage: $this->userStorage,
      participantFieldsService: $participantFieldsService,
      musicianService: $this->appContainer->get(MusicianService::class),
      eventDispatcher: $this->mockProvider->getEventDispatcher(),
    );
    $this->mockProvider->registerClassInstance(ProjectService::class, $projectService, global: true);

    $this->instance = new TestedClass(
      appContainer: $this->mockProvider->getAppContainer(),
      isCLI: false,
    );
    // need to register the instance as involved classes use the app-container for auto-load.
    $this->mockProvider->registerClassInstance(TestedClass::class, $this->instance, global: true);

    $this->appL10n = $this->appContainer->get(\OCA\CAFEVDB\Service\Registration::APP_L10N);
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /**
   * This is quas a setupBeforeClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  public function testApplyMigrations(): void
  {
  }

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testApplyMigrations')]
  #[Attributes\Depends('testHandleCardUpdatedNewMusician')]
  #[Attributes\Depends('testHandleCardUpdatedExistingMusician')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  private const CARD_UID = 'a073db1b-fe3f-40aa-ad53-9c82a309351f';

  private const CARD_DB_ROW = [
    'id' => 1,
    'addressbookid' => 1,
    'uri' => 'uri.vcf',
    'lastmodified' => 1234,
    'etag' => null,
    'size' => 1234,
    'uid' => self::CARD_UID,
    'carddata' => 'BEGIN:VCARD
VERSION:3.0
N:Doe;Jane;;;
FN:Jane Doe
ORG:Example.com Inc.;
UID:' . self::CARD_UID . '
TITLE:Imaginary test person
EMAIL;type=INTERNET;type=WORK;type=pref:janeDoe@example.org
TEL;type=WORK;type=pref:+1 617 555 1212
TEL;type=WORK:+1 (617) 555-1234
TEL;type=CELL:+1 781 555 1212
TEL;type=HOME:+1 202 555 1212
item1.ADR;type=WORK:;;2 Enterprise Avenue;Worktown;NY;01111;USA
item1.X-ABADR:us
item2.ADR;type=HOME;type=pref:;;3 Acacia Avenue;Hoemtown;MA;02222;USA
item2.X-ABADR:us
NOTE:Jane Doe has a long and varied history\, being documented on more police files that anyone else. Reports of his death are alas numerous.
item3.URL;type=pref:http\://www.example/com/doe
item3.X-ABLabel:_$!<HomePage>!$_
item4.URL:http\://www.example.com/Joe/foaf.df
item4.X-ABLabel:FOAF
item5.X-ABRELATEDNAMES;type=pref:Jane Doe
item5.X-ABLabel:_$!<Friend>!$_
CATEGORIES:Work,Test group
X-ABUID:5AD380FD-B2DE-4261-BA99-DE1D1DB52FBE\:ABPerson
END:VCARD
',
    ];

  /** @return void */
  #[Attributes\Depends('testApplyMigrations')]
  public function testHandleCardUpdatedNewMusician(): void
  {
    $projectName = $this->project->getName();
    $cardData = self::CARD_DB_ROW;
    $cardData['carddata'] = str_replace(
      'CATEGORIES:Work,Test group',
      'CATEGORIES:Work,Test group,' . $this->mockProvider->appName . ',' . $projectName,
      $cardData['carddata'],
    );
    $orchestraGroup = MockProvider::USER_GROUP_VALUE;
    $etag = md5('12345');
    $event = new CardUpdatedEvent(
      addressBookId: 1,
      addressBookData: [
        'uri' => self::ADDRESS_BOOK_URI,
        'principaluri' =>  'blah/blah/blahblah'
      ],
      shares: [
        [
          'href' => 'principal:principals/groups/' . $orchestraGroup,
          'status' => 1,
          'readOnly' => false,
          '{http://owncloud.org/ns}principal' => 'principals/groups/' . $orchestraGroup,
          '{http://owncloud.org/ns}group-share' => true,
        ],
      ],
      cardData: $cardData,
      etag: $etag,
    );

    $updateCardData = null;
    $this->addressBooks[self::SHARED_ADDRESS_BOOK_URI]
      ->method('createOrUpdate')
      ->willReturnCallback(
        function(array $cardData) use (&$updateCardData) {
          $updateCardData = $cardData;
        });

    $vCard = VObject\Reader::read($cardData['carddata']);
    $contactsService = $this->mockProvider->getAppContainer()->get(ContactsService::class);
    $flatCardData = $contactsService->flattenVCard('card.vcf', $vCard);
    $this->addressBooks[self::SHARED_ADDRESS_BOOK_URI]
      ->method('search')
      ->willReturnCallback(
        function(string $pattern, array $searchProperties, array $options) use ($flatCardData) {
          return [$flatCardData];
        });

    $this->instance->handle($event);

    $this->assertNull($event->getEtag());

    $this->assertNotNull($updateCardData);
    $categories = explode(',', $updateCardData['CATEGORIES'] ?? '');
    $expectedCategories = [
      $this->mockProvider->appName,
      $projectName . $this->appL10n->t(ContactsService::ASSOCIATES_SUFFIX),
      $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_ASSOCIATE),
      $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_BUSINESS_PARTNER),
    ];
    $this->assertEqualsCanonicalizing($expectedCategories, array_intersect($categories, $expectedCategories));

    $this->assertEquals(2, $this->project->getParticipants()->count());
    $this->assertEquals(1, $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->count());
    $newParticipant = $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->first();
    $newMusician = $newParticipant->getMusician();
    $this->assertEquals(self::CARD_UID, (string)$newMusician->getUuid());
  }

  /** @return void */
  #[Attributes\Depends('testApplyMigrations')]
  #[Attributes\Depends('testHandleCardUpdatedNewMusician')]
  public function testHandleCardUpdatedExistingMusician(): void
  {
    $projectName = $this->project->getName();
    $cardData = self::CARD_DB_ROW;
    $cardData['carddata'] = str_replace(
      'CATEGORIES:Work,Test group',
      'CATEGORIES:Work,Test group,Viola'
      . ',' . $this->mockProvider->appName
      . ',' . $projectName
      . ',' . $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_ASSOCIATE)
      . ',' . $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_BUSINESS_PARTNER),
      $cardData['carddata'],
    );
    $orchestraGroup = MockProvider::USER_GROUP_VALUE;
    $etag = md5('12345');
    $event = new CardUpdatedEvent(
      addressBookId: 1,
      addressBookData: [
        'uri' => self::ADDRESS_BOOK_URI,
        'principaluri' =>  'blah/blah/blahblah'
      ],
      shares: [
        [
          'href' => 'principal:principals/groups/' . $orchestraGroup,
          'status' => 1,
          'readOnly' => false,
          '{http://owncloud.org/ns}principal' => 'principals/groups/' . $orchestraGroup,
          '{http://owncloud.org/ns}group-share' => true,
        ],
      ],
      cardData: $cardData,
      etag: $etag,
    );

    $updateCardData = null;
    $this->addressBooks[self::SHARED_ADDRESS_BOOK_URI]
      ->method('createOrUpdate')
      ->willReturnCallback(
        function(array $cardData) use (&$updateCardData) {
          $updateCardData = $cardData;
        });

    $vCard = VObject\Reader::read($cardData['carddata']);
    $contactsService = $this->mockProvider->getAppContainer()->get(ContactsService::class);
    $flatCardData = $contactsService->flattenVCard('card.vcf', $vCard);
    $this->addressBooks[self::SHARED_ADDRESS_BOOK_URI]
      ->method('search')
      ->willReturnCallback(
        function(string $pattern, array $searchProperties, array $options) use ($flatCardData) {
          return [$flatCardData];
        });

    $this->instance->handle($event);

    $this->assertNull($event->getEtag());

    $this->assertEquals(1, $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->count());
    $newParticipant = $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->first();
    $newMusician = $newParticipant->getMusician();
    $this->assertEquals(self::CARD_UID, (string)$newMusician->getUuid());
    $this->assertTrue(
      $newMusician->getInstruments()->exists(fn($key, $value) => $value->getInstrument()->getName() == 'Viola'),
    );

    $this->assertNotNull($updateCardData);
    $categories = explode(',', $updateCardData['CATEGORIES'] ?? '');
    $expectedCategories = [
      $this->mockProvider->appName,
      $projectName . $this->appL10n->t(ContactsService::ASSOCIATES_SUFFIX),
      $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_ASSOCIATE),
      $this->appL10n->t(Entities\ProjectInstrument::NON_INSTRUMENT_BUSINESS_PARTNER),
    ];
    $this->assertEqualsCanonicalizing($expectedCategories, array_intersect($categories, $expectedCategories));

    $this->assertEquals(2, $this->project->getParticipants()->count());
    $this->assertEquals(1, $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->count());
    $newParticipant = $this->project->getParticipants(EnumParticipationContext::ASSOCIATES)->first();

    $this->assertEquals(self::CARD_UID, (string)$newParticipant->getMusician()->getUuid());
  }
}
