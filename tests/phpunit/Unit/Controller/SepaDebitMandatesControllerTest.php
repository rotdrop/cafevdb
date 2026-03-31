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

use DateTimeImmutable;
use DOMDocument;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use Psr\Container\ContainerInterface;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Controller\EnumAddDocumentConflictAction;
use OCA\CAFEVDB\Controller\EnumFileUploadMode;
use OCA\CAFEVDB\Controller\EnumFileUploadOrigin;
use OCA\CAFEVDB\Controller\EnumSepaDebitMandateBinding;
use OCA\CAFEVDB\Controller\SepaDebitMandatesController;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\Database as DatabaseStorage;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Tests\Unit\PageRenderer\GetFormValuesTrait;
use OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
use OCA\CAFEVDB\Tests\Unit\Storage\GetAppStorageTrait;
use OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the SepaDebitMandatesController */
#[Attributes\CoversClass(DTO\SepaBankAccount::class)]
#[Attributes\CoversClass(DTO\SepaDebitMandate::class)]
#[Attributes\CoversClass(SepaDebitMandatesController::class)]
#[Attributes\CoversMethod(DatabaseStorage\ProjectParticipantsStorage::class, 'addDebitMandate')]
#[Attributes\CoversMethod(DatabaseStorage\ProjectParticipantsStorage::class, 'replaceDebitMandate')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractFileSystemUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableFileRemove::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableFileSystemNodeRemove::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\UploadFileData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\UploadFileMetaData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitMandate::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBankAccountsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaDebitMandatesRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerClosedEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\DatabaseStorageFileEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\FinanceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\Factory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\ProjectParticipantsStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\Database\Storage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\AbstractEntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UnusedTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EnsureEntityTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class SepaDebitMandatesControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use GetAppStorageTrait;
  use GetFormValuesTrait;
  use MockUserStorageTrait;
  use SetupCalendarBackendTrait;
  use SetupMigrationTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = SepaDebitMandatesController::class;
  private const EXPECTED_ROUTES = [
    'mandatevalidate',
    'mandateform',
    'mandatestore',
    'prefilledmandateform',
    'mandatedelete',
    'mandatedisable',
    'mandatereactivate',
    'mandatehardcopy',
    'accountdelete',
    'accountdisable',
    'accountreactivate',
  ];

  private SepaDebitMandatesController $controller;

  private MockProvider $mockProvider;

  private TimeFactory $timeFactory;

  private ContainerInterface $appContainer;

  private IDateTimeFormatter $dateTimeFormatter;

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  private DateTimeImmutable $now;

  /** {@inheritdoc} */
  public function setup(): void
  {
    error_reporting(E_ALL);
    \OCA\CAFEVDB\Wrapped\Doctrine\Deprecations\Deprecation::enableWithTriggerError();
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->generateCalendarBackend();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->now = DateTimeImmutable::createFromFormat('Y-m-d h:i:s', '2099-01-01 12:00:00');
    /** @var TimeFactory $timeFactory */
    $this->timeFactory = $this->getMockBuilder(TimeFactory::class)->getMock();
    $this->timeFactory->method('now')->willReturnCallback(fn() => $this->now);
    $this->timeFactory->method('sleepUntil')->willReturnCallback(function (float $timestamp) {
      $diff = $timestamp - $this->now->getTimeStamp();
      if ($diff > 0) {
        return time_sleep_until(time() + $diff);
      }
      return false;
    });

    $this->timeFactory->expects($this->never())->method('withTimeZone');
    $this->mockProvider->registerClassInstance(TimeFactory::class, $this->timeFactory, global: true);

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $this->generateProjectParticipant(persist: true, now: $this->now, delete: false);
      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();

      self::$migrationsApplied = true;
    }

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();
    $this->musician = $this->entityManager->find(Entities\Musician::class, self::$musicianId);
    $this->project = $this->entityManager->find(Entities\Project::class, self::$projectId);

    $this->getUserStorageStub();
    $this->mockProvider->registerClassInstance(UserStorage::class, $this->userStorage, global: true);

    $this->getAppStorage();
    $this->mockProvider->registerClassInstance(AppStorage::class, $this->appStorage, global: true);

    $this->appContainer = $this->mockProvider->getAppContainer();

    $this->dateTimeFormatter = $this->appContainer->get(IDateTimeFormatter::class);

    $this->controller = new SepaDebitMandatesController(
      appName: $this->mockProvider->appName,
      request: $this->mockProvider->getRequest(),
      bav: $this->mockProvider->getBankAccountValidator(),
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      financeService: $this->appContainer->get(FinanceService::class),
      fuzzyInputService: $this->appContainer->get(FuzzyInputService::class),
      projectService: $this->createStub(ProjectService::class),
      storageFactory: new StorageFactory($this->mockProvider->getConfigService()),
      timeFactory: $this->timeFactory,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  private const INITIAL_FORM_VALUES = [
    'mandateProjectName' => '',
    'projectId' => '1',
    'projectName' => 'TestProject2099',
    'musicianId' => '1',
    'musicianName' => 'Musterperson, Max',
    'bankAccountSequence' => '0',
    'mandateSequence' => '0',
    'mandateReference' => '',
    'mandateNonRecurring' => '0',
    'writtenMandateId' => '',
    'memberProjectId' => '0',
    'bankAccountOwner' => 'Max Musterperson',
    'bankAccountBLZ' => '',
    'bankAccountIBAN' => '',
    'bankAccountBIC' => '',
    'sepa-validation-toggle' => 'on',
    'mandateProjectId' => '1',
    'mandateDate' => '01.01.2099',
    'mandateLastUsedDate' => '',
    'uploadPlaceholder' => '',
    'writtenMandateFileUpload' => '',
  ];

  /** @return void */
  public function testMandateFormBlank(): void
  {
    $result = $this->controller->mandateForm(musicianId: $this->musician->getId(), projectId: self::$projectId, bankAccountSequence: 0, mandateSequence: 0);
    $this->assertTrue($result instanceof Http\JSONResponse || $result instanceof Http\DataResponse);
    $data = $result->getData();
    $this->assertInstanceOf(DTO\SepaDebitMandate::class, $data);
    /** @var DTO\SepaDebitMandate $data */
    $this->assertNotEmpty($data->contents);

    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertTrue($domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $formValues = $this->getFormValues($data->contents, '#sepa-debit-mandate-form');
    $this->assertEquals(self::INITIAL_FORM_VALUES, $formValues);
    $this->assertEquals($this->musician->getPublicName(), $formValues['musicianName']);
    $this->assertEquals($this->musician->getPublicName(firstNameFirst: true), $formValues['bankAccountOwner']);
    $this->assertEquals($this->musician->getId(), $formValues['musicianId']);
    $this->assertEquals($this->project->getId(), $formValues['projectId']);
    $this->assertEquals($this->project->getName(), $formValues['projectName']);
    $this->assertEquals($this->dateTimeFormatter->formatDate($this->now, 'medium'), $formValues['mandateDate']);
  }

  /**
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\Depends('testMandateFormBlank')]
  public function testMandateStore(): void
  {
    MockProvider::$ibanInfo[self::MUSICIAN_IBAN] = [
      'iban' => self::MUSICIAN_IBAN,
      'country' => 'Deutschland (DE)',
      'bic' => self::MUSICIAN_BIC,
      'blz' => self::MUSICIAN_BLZ,
      'account' => self::MUSICIAN_BANK_ACCOUNT,
      'bank' => 'Blah Bank',
      'city' => 'Neustadt',
    ];
    $numBankAccounts = count($this->entityManager->getRepository(Entities\SepaBankAccount::class)->findAll());
    $numDebitMandates = count($this->entityManager->getRepository(Entities\SepaDebitMandate::class)->findAll());
    $result = $this->controller->mandateStore(
      projectId: self::$projectId,
      // SEPA "id"
      musicianId: self::$musicianId,
      bankAccountSequence: 0,
      mandateSequence: 0,
      // Bank account data
      bankAccountIBAN: self::MUSICIAN_IBAN,
      bankAccountBIC: self::MUSICIAN_BIC,
      bankAccountBLZ: self::MUSICIAN_BLZ,
      bankAccountOwner: self::MUSICIAN_BANK_ACCOUNT_OWNER,
      // debit-mandate data
      mandateRegistration: 'on',
      mandateBinding: EnumSepaDebitMandateBinding::FOR_ALL_RECEIVABLES->value,
      mandateProjectId: self::$projectId,
      mandateNonRecurring: 0,
      mandateDate: $this->dateTimeFormatter->formatDate($this->now, 'medium'),
      mandateLastUsedDate: null,
      writtenMandateId: 0,
      writtenMandateFileUpload: '', // FILL THIS!
      mandateUploadLater: 'on',
      uploadPlaceholder: 'this does not matter',
    );

    $this->assertInstanceOf(Http\JSONResponse::class, $result);
    /** @var Http\JSONResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());

    $data = $result->getData();
    $this->assertInstanceOf(DTO\SepaDebitMandate::class, $data);

    $bankAccounts = $this->entityManager->getRepository(Entities\SepaBankAccount::class)->findAll();
    $debitMandates = $this->entityManager->getRepository(Entities\SepaDebitMandate::class)->findAll();
    $this->assertEquals($numBankAccounts + 1, count($bankAccounts));
    $this->assertEquals($numDebitMandates + 1, count($debitMandates));
  }

  private const FILE_NAME = 'file.txt';
  private const FILE_DATA = 'TEXT';

  /**
   * Simulate file upload with various sources.
   *
   * @param string $tempFileName Temporary filename of the simulated upload.
   *
   * @param EnumFileUploadOrigin $origin Upload origin.
   *
   * @param ?EnumFileUploadMode $uploadMode What to do with the original  file.
   *
   * @param int|string $originalName If linking to other DB files this must be the file-id.
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  private function doMandateStoreUpload(
    string $tempFileName,
    EnumFileUploadOrigin $origin,
    ?EnumFileUploadMode $uploadMode,
    int|string $originalName = self::FILE_NAME,
  ): void {
    MockProvider::$ibanInfo[self::MUSICIAN_IBAN] = [
      'iban' => self::MUSICIAN_IBAN,
      'country' => 'Deutschland (DE)',
      'bic' => self::MUSICIAN_BIC,
      'blz' => self::MUSICIAN_BLZ,
      'account' => self::MUSICIAN_BANK_ACCOUNT,
      'bank' => 'Blah Bank',
      'city' => 'Neustadt',
    ];

    // The expected uploads file data is a JSON encoded DTO\UploadFileData instance
    $uploadFileData = new DTO\UploadFileData(
      name: self::FILE_NAME,
      error: 0,
      str_error: null,
      message: null,
      tmp_name: $tempFileName,
      type: 'text/plain',
      size: strlen(self::FILE_DATA),
      original_name: $originalName,
      upload_max_file_size: 1 << 16,
      max_human_file_size: '64 kiB',
      meta: null,
      origin: $origin,
      upload_mode: $uploadMode,
    );

    $numBankAccounts = count($this->entityManager->getRepository(Entities\SepaBankAccount::class)->findAll());
    $debitMandates = $this->entityManager->getRepository(Entities\SepaDebitMandate::class)->findAll();
    $numDebitMandates = count($debitMandates);
    $this->assertTrue(count($debitMandates) > 0);

    $debitMandate = array_pop($debitMandates);
    $bankAccount = $debitMandate->getSepaBankAccount();

    $result = $this->controller->mandateStore(
      projectId: self::$projectId,
      // SEPA "id"
      musicianId: self::$musicianId,
      bankAccountSequence: $bankAccount->getSequence(),
      mandateSequence: $debitMandate->getSequence(),
      // Bank account data
      bankAccountIBAN: $bankAccount->getIban(),
      bankAccountBIC: $bankAccount->getBic(),
      bankAccountBLZ: $bankAccount->getBlz(),
      bankAccountOwner: $bankAccount->getBankAccountOwner(),
      // debit-mandate data
      mandateRegistration: 'on',
      mandateBinding: EnumSepaDebitMandateBinding::FOR_ALL_RECEIVABLES->value,
      mandateProjectId: $debitMandate->getProject()->getId(),
      mandateNonRecurring: $debitMandate->getNonRecurring(),
      mandateDate: $this->dateTimeFormatter->formatDate($debitMandate->getMandateDate(), 'medium'),
      mandateLastUsedDate: $debitMandate->getLastUsedDate(),
      writtenMandateId: 0,
      writtenMandateFileUpload: json_encode([$uploadFileData]),
      mandateUploadLater: null,
      uploadPlaceholder: 'this does not matter',
    );

    $this->assertInstanceOf(Http\JSONResponse::class, $result);
    /** @var Http\JSONResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());

    $data = $result->getData();
    $this->assertInstanceOf(DTO\SepaDebitMandate::class, $data);

    $bankAccounts = $this->entityManager->getRepository(Entities\SepaBankAccount::class)->findAll();
    $debitMandates = $this->entityManager->getRepository(Entities\SepaDebitMandate::class)->findAll();
    $this->assertEquals($numBankAccounts, count($bankAccounts));
    $this->assertEquals($numDebitMandates, count($debitMandates));
  }

  /**
   * Try to augment the mandate from the previous test with a file upload (in
   * real life an electronic copy of the signed debit mandate).
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\Depends('testMandateStore')]
  public function testMandateStoreWithUploadHardCopy(): void
  {
    // In this test we fake an upload with data from a temp-file on the local hard-disk.
    /** @var ITempManager $tempManager */
    $tempManager = $this->appContainer->get(ITempManager::class);
    $tmpFile = $tempManager->getTemporaryFile();
    file_put_contents($tmpFile, self::FILE_DATA);

    $this->doMandateStoreUpload($tmpFile, EnumFileUploadOrigin::UPLOAD, null);
  }

  /**
   * Try to augment the mandate from the previous test with a file upload (in
   * real life an electronic copy of the signed debit mandate).
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\Depends('testMandateStore')]
  public function testMandateStoreWithCloudCopyHardCopy(): void
  {
    $this->userStorage->putContent(self::FILE_NAME, self::FILE_DATA);
    $tempFile = $this->appStorage->newTemporaryFile(AppStorage::UPLOAD_FOLDER);
    $tempFile->putContent(self::FILE_DATA);
    $tempFileName = $tempFile->getName();

    $this->doMandateStoreUpload($tempFileName, EnumFileUploadOrigin::CLOUD, EnumFileUploadMode::COPY);

    // Temp-file should have gone away.
    $this->assertNull($this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $tempFileName, throw: false));
  }

  /**
   * Try to augment the mandate from the previous test with a file upload (in
   * real life an electronic copy of the signed debit mandate).
   *
   * This variant should delete the original file in cloud storage.
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\Depends('testMandateStore')]
  public function testMandateStoreWithCloudMoveHardCopy(): void
  {
    $this->userStorage->putContent(self::FILE_NAME, self::FILE_DATA);
    $tempFile = $this->appStorage->newTemporaryFile(AppStorage::UPLOAD_FOLDER);
    $tempFile->putContent(self::FILE_DATA);
    $tempFileName = $tempFile->getName();

    $this->doMandateStoreUpload($tempFileName, EnumFileUploadOrigin::CLOUD, EnumFileUploadMode::MOVE);

    // Temp-file should have gone away.
    $this->assertNull($this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $tempFileName, throw: false));

    // The original file should have been deleted.
    $originalFile = $this->userStorage->getFile(self::FILE_NAME);
    $this->assertNull($originalFile);
  }

  /**
   * Try to augment the mandate from the previous test with a file upload (in
   * real life an electronic copy of the signed debit mandate).
   *
   * This variant should just link an existing DB-storage file.
   *
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\Depends('testMandateStoreWithCloudMoveHardCopy')]
  public function testMandateStoreWithCloudLinkHardCopy(): void
  {
    $debitMandates = $this->entityManager->getRepository(Entities\SepaDebitMandate::class)->findAll();
    $this->assertTrue(count($debitMandates) > 0);

    // let's steal the existing document ...
    $debitMandate = array_pop($debitMandates);
    $writtenMandate = $debitMandate->getWrittenMandate();
    $this->assertNotNull($writtenMandate);
    $file = $writtenMandate->getFile();
    $folder = $writtenMandate->getParent();
    $this->entityManager->beginTransaction();
    try {
      $newDocument = $folder->addDocument(
        file: $file,
        fileName: 'SomethingWhichProbablyDoesNotYetExist.txt',
        conflictAction: EnumAddDocumentConflictAction::FAIL,
      );
      $debitMandate->setWrittenMandate(null); // should also trigger orphan removal
      $this->entityManager->flush();

      $this->assertEquals(1, $newDocument->getNumberOfLinks());
      $this->assertEquals(self::FILE_DATA, $newDocument->getData());

      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw $t;
    }

    $this->doMandateStoreUpload(
      tempFileName: 'does not matter',
      origin: EnumFileUploadOrigin::CLOUD,
      uploadMode: EnumFileUploadMode::LINK,
      originalName: $newDocument->getFile()->getId(),
    );

    $this->assertEquals(2, $newDocument->getNumberOfLinks());
  }

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testMandateFormBlank')]
  #[Attributes\Depends('testMandateStore')]
  #[Attributes\Depends('testMandateStoreWithHardCopy')]
  #[Attributes\Depends('testMandateStoreWithCloudCopyHardCopy')]
  #[Attributes\Depends('testMandateStoreWithCloudMoveHardCopy')]
  #[Attributes\Depends('testRoutesAreDefined')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }
}
