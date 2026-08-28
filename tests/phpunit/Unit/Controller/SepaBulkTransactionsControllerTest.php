<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use DateTimeInterface;
use DateTimeImmutable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IDateTimeFormatter;
use OCP\IRequest;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Database\Legacy\PME\GetPMEStubTrait;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
use OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the SepaBulkTransactionsController. */
#[Attributes\CoversClass(Controller\SepaBulkTransactionsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\DoNothingProgressStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\PHPMailer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageDirEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\EncryptedFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\EncryptedFileData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\File::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\FileData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDatum::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectPayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBulkTransaction::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\AssociationSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\InvoiceNumberHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable\Encryption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerClosedEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\UndoableRunQueueException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\CalendarObjectCreatedEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\CalendarObjectDeletedEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\CalendarObjectUpdatedEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\DatabaseStorageFileEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEventEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantFieldDataOptionEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantFieldEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\SepaBulkTransactionEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260206193722::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260207000624::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819094146::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819094422::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819105948::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\PMETableViewBase::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Util\Navigation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\AbstractReceivablesGenerator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\AqBankingBulkTransactionExporter::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\FinanceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\GnuCashBulkTransactionBalancingItemsExporter::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\GnuCashConnectorService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MailingListsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\OrganizationalRolesService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\BankTransactionsStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\Storage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\UuidType::class)]
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
class SepaBulkTransactionsControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use GetPMEStubTrait;
  use MockUserStorageTrait;
  use SetupCalendarBackendTrait;
  use SetupMigrationTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\SepaBulkTransactionsController::class;
  private const EXPECTED_ROUTES = [
    'serviceswitch',
  ];

  private const CONFIG_MOCK = [
    ConfigConstants::BANK_ACCOUNT_OWNER => 'Örchester e.V.',
    ConfigConstants::BANK_ACCOUNT_IBAN => 'DE07123412341234123412',
    ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER => 'DEPPZZZ0NNNNNNNNNN',
    ConfigConstants::BANK_ACCOUNT_BIC => 'MARKDEFFXXX',
  ];

  private MockProvider $mockProvider;

  private Controller\SepaBulkTransactionsController $controller;

  private DateTimeInterface $now;

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  private static int $fieldId;

  private array $postData = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    \OCA\CAFEVDB\Wrapped\Doctrine\Deprecations\Deprecation::enableWithTriggerError();
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->generateCalendarBackend();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->getUserStorageStub();
    $this->mockProvider->registerClassInstance(UserStorage::class, $this->userStorage, global: true);

    $this->now = DateTimeImmutable::createFromFormat('Y-m-d h:i:s', '2099-01-01 12:00:00');
    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $this->generateProjectParticipant(persist: true, now: $this->now, delete: false);
      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();
      $datum = $this->generateReceivable(persist: true, generatorClass: DoNothingReceivablesGenerator::class);
      self::$fieldId = $datum->getField()->getId();

      self::$migrationsApplied = true;
    }

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();
    $this->musician = $this->entityManager->find(Entities\Musician::class, self::$musicianId);
    $this->project = $this->entityManager->find(Entities\Project::class, self::$projectId);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );

    $this->getPHPMyEditStub();

    $encryptionService = $this->mockProvider->getEncryptionService();
    foreach (self::CONFIG_MOCK as $key => $value) {
      $encryptionService->setAppValue($key, $value);
    }

    $appContainer = $this->mockProvider->getAppContainer();

    $organizationalRolesService = $this->createStub(OrganizationalRolesService::class);
    $organizationalRolesService->method('treasurerContact')
      ->willReturnCallback(
        function() use ($organizationalRolesService) {
          return $organizationalRolesService->dedicatedBoardMemberContact(
            OrganizationalRolesService::TREASURER_ROLE,
          );
        },
      );
    $organizationalRolesService->method('dedicatedBoardMemberContact')
      ->willReturnCallback(
        function(string $role, int $musicianId = 0) {
          return [
            'email' => 'role@orchestra.org',
            'name' => 'Board Member ' . ucfirst($role),
            'firstName' => 'Board Member',
            'surName' => ucfirst($role),
            'street' => 'Some Street',
            'streetNumber' => 17,
            'streetAndNumber' => 'Some Street 17',
            'postalCode' => 'Z-12345',
            'city' => 'Unknown City',
            'phone' => '1234567',
            'mobile' => '1234567',
          ];
        },
      );
    $this->mockProvider->registerClassInstance(OrganizationalRolesService::class, $organizationalRolesService, global: true);

    $this->controller = new Controller\SepaBulkTransactionsController(
      appName: $this->mockProvider->appName,
      request: $request,
      bulkTransactionService: $appContainer->get(SepaBulkTransactionService::class),
      configService: $this->mockProvider->getConfigService(),
      dateTimeFormatter: $appContainer->get(IDateTimeFormatter::class),
      entityManager: $this->entityManager,
      financeService: $appContainer->get(FinanceService::class),
      pme: $this->pme,
      projectService: $this->createStub(ProjectService::class),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testRoutesAreDefined')]
  #[Attributes\Depends('testGenerateBulkTransactions')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  /** @return void */
  public function testGenerateBulkTransactions(): void
  {
    // PME_sys_mrecs[] = "{\"musician_id\":\"1\",\"sequence\":\"1\"}"
    $this->postData[$this->pme->cgiSysName(PHPMyEdit::MRECS_KEY)] = [
      json_encode([
        'musician_id' => self::$musicianId,
        'sequence' => 1,
      ]),
    ];

    $this->controller->serviceSwitch(
      bulkTransactionId: 0, // only needed for export
      projectId: self::$projectId,
      sepaBulkTransactions: [ self::$fieldId ],
      sepaDueDeadline: null,
      topic: Controller\EnumSepaBulkTransactionsTopic::CREATE,
    );
  }
}
