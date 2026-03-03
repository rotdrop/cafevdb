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
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\ProjectParticipantFields\IManager as ProjectParticipantFieldsManager;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\ProjectParticipantFields as Renderer;
use OCA\CAFEVDB\Service\Finance\IRecurringReceivablesGenerator as ReceivablesGenerator;
use OCA\CAFEVDB\Service\Finance\ManuallyGeneratedReceivablesGenerator;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ProjectParticipantFieldsController. */
#[Attributes\CoversClass(Controller\ProjectParticipantFieldsController::class)]
#[Attributes\CoversClass(DTO\ParticipantFieldPropertyGetDefaultValue::class)]
#[Attributes\CoversClass(DTO\ParticipantFieldPropertyGetResponse::class)]
#[Attributes\CoversClass(DTO\ReceivablesStatistics::class)]
#[Attributes\CoversClass(ManuallyGeneratedReceivablesGenerator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\PlainFileProgressStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\MessagesResponse::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDatum::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantFieldDataOptionEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectParticipantFieldEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260303085014::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260206193722::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260207000624::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\AbstractReceivablesGenerator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\ReceivablesGeneratorFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProgressStatusService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
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
class ProjectParticipantFieldsControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use SetupMigrationTrait;
  use TestRoutesAreDefinedTrait;
  use SetupCalendarBackendTrait;

  private const CONTROLLER_CLASS = Controller\ProjectParticipantFieldsController::class;
  private const EXPECTED_ROUTES = [
    'serviceswitch',
  ];

  private const RECEIVABLES_GENERATOR = ManuallyGeneratedReceivablesGenerator::class;

  private MockProvider $mockProvider;

  private Controller\ProjectParticipantFieldsController $controller;

  private TimeFactory $timeFactory;

  private Entities\ProjectParticipantField $field;

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  private static int $fieldId;

  private DateTimeImmutable $now;

  private array $postData = [];

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
      $datum = $this->generateReceivable(persist: true, generatorClass: self::RECEIVABLES_GENERATOR);

      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();
      self::$fieldId = $datum->getField()->getId();

      self::$migrationsApplied = true;
    }

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();
    $this->musician = $this->entityManager->find(Entities\Musician::class, self::$musicianId);
    $this->project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    $this->field = $this->entityManager->find(Entities\ProjectParticipantField::class, self::$fieldId);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );

    // For real tests we will need to mock some methods.
    $fuzzyInputService = $this->createStub(FuzzyInputService::class);
    $participantFieldsService = $this->createStub(ProjectParticipantFieldsService::class);
    $phpMyEdit = $this->createStub(PHPMyEdit::class);
    $renderer = $this->createStub(Renderer::class);

    $this->controller = new Controller\ProjectParticipantFieldsController(
      appName: $this->mockProvider->appName,
      request: $request,
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      fuzzyInput: $fuzzyInputService,
      participantFieldsService: $participantFieldsService,
      pme: $phpMyEdit,
      renderer: $renderer,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testPropertyGetDefaultValue(): void
  {
    $this->postData['fieldId'] = self::$fieldId;
    $this->postData['property'] = Controller\EnumParticipantFieldPropertyGet::DEFAULT_VALUE->value;
    $response = $this->controller->serviceSwitch(
      Controller\EnumParticipantFieldRequestTopic::PROPERTY->value,
      Controller\EnumParticipantFieldRequestSubTopic::GET->value,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\ParticipantFieldPropertyGetResponse::class, $data);
    /** @var DTO\ParticipantFieldPropertyGetResponse $data */
    $this->assertEquals($this->postData['fieldId'], $data->fieldId);
    $this->assertEquals(Controller\EnumParticipantFieldPropertyGet::get($this->postData['property']), $data->property);
    $value = $data->value;
    $this->assertInstanceOf(DTO\ParticipantFieldPropertyGetDefaultValue::class, $value);
    /** @var DTO\ParticipantFieldPropertyGetDefaultValue $value */
    $default = $this->field->getDefaultValue();
    $this->assertEquals((string)$default->getKey(), $value->key);
    $this->assertEquals($default->getData(), $value->data);
  }

  /** @return void */
  public function testPropertyGetDefaultDeposit(): void
  {
    $this->postData['fieldId'] = self::$fieldId;
    $this->postData['property'] = Controller\EnumParticipantFieldPropertyGet::DEFAULT_DEPOSIT->value;
    $response = $this->controller->serviceSwitch(
      Controller\EnumParticipantFieldRequestTopic::PROPERTY->value,
      Controller\EnumParticipantFieldRequestSubTopic::GET->value,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\ParticipantFieldPropertyGetResponse::class, $data);
    /** @var DTO\ParticipantFieldPropertyGetResponse $data */
    $this->assertEquals($this->postData['fieldId'], $data->fieldId);
    $this->assertEquals(Controller\EnumParticipantFieldPropertyGet::get($this->postData['property']), $data->property);
    $value = $data->value;
    $this->assertEquals($this->field->getDefaultValue()->getDeposit(), $value);
  }

  /** @return void */
  public function testOptionRegenerate(): void
  {
    $data = [
      'fieldId' => self::$fieldId,
      'updateStrategy' => ReceivablesGenerator::UPDATE_STRATEGY_REPLACE,
      // 'progressToken' => XYZ
    ];
    try {
      $this->controller->serviceSwitch(
        Controller\EnumParticipantFieldRequestTopic::OPTION->value,
        Controller\EnumParticipantFieldRequestSubTopic::REGENERATE->value,
        data: $data,
      );
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }

    $data['musicianId'] = self::$musicianId;
    $response = $this->controller->serviceSwitch(
      Controller\EnumParticipantFieldRequestTopic::OPTION->value,
      Controller\EnumParticipantFieldRequestSubTopic::REGENERATE->value,
      data: $data,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\ReceivablesStatistics::class, $data);
    /** @var DTO\ReceivablesStatistics $data */
    $this->assertNotEmpty($data->messages);
    $this->assertEquals(1, $data->added);
    $this->assertEquals(0, $data->removed);
    $this->assertEquals(0, $data->changed);
    $this->assertEquals(0, $data->skipped);
    $this->assertEquals([$this->musician->getPublicName(firstNameFirst: true)], array_values($data->musicians));
    $this->assertEquals(1, count($data->receivables));
  }

  /** @return void */
  public function testGeneratorBogusSubtopic(): void
  {
    try {
      $this->controller->serviceSwitch(
        Controller\EnumParticipantFieldRequestTopic::GENERATOR->value,
        Controller\EnumParticipantFieldRequestSubTopic::REGENERATE->value, // illegal sub-topic
      );
    } catch (Throwable $t) {
      if (!($t instanceof Exceptions\EnduserNotificationException)) {
        echo 'Unexpected exception ' . get_class($t) . ': ' . $t->getMessage() . PHP_EOL;
      }
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }
  }

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testGeneratorBogusSubtopic')]
  #[Attributes\Depends('testOptionRegenerate')]
  #[Attributes\Depends('testPropertyGetDefaultDeposit')]
  #[Attributes\Depends('testPropertyGetDefaultValue')]
  #[Attributes\Depends('testRoutesAreDefined')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }
}
