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

use InvalidArgumentException;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OC\AppFramework\Http\Request;

use OCP\AppFramework\Http;
use OCP\Constants as CloudConstants;
use OCP\Files\File;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\Util as NcUtil;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit\Exceptions;

/** Test aspects of the UploadsController. */
#[Attributes\CoversClass(Controller\UploadsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\FileUploadMoveResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\MessagesResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\UploadFileData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\UploadModeTest::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageDirEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\EncryptedFile::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819094146::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819094422::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260819105948::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20261009150233::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\UserStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\AbstractEntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\EnumOrderByOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Exceptions\EnduserNotificationException::class)]
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
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\UserRootFolderTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class UploadsControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\EnableDeprecationsTrait;
  use \OCA\CAFEVDB\Tests\Unit\Toolkit\Traits\CheckGrumbleTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\GetAppStorageTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\RootFolderStubTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;

  private const CONTROLLER_CLASS = Controller\UploadsController::class;
  private const EXPECTED_ROUTES = [
    'move',
    'stash',
  ];

  private const BASE_NAME = 'a-file.md';

  private const FILE_CONTENT = '# Caption';

  private const MOVE_DESTINATION = '/a-folder/' . self::BASE_NAME;

  private const ORIGINAL_FILE_NAME = '/another-folder/' . self::BASE_NAME;

  private string $fileMimeType;

  private MockProvider $mockProvider;

  private Controller\UploadsController $controller;

  private ITempManager $tempManager;

  private IURLGenerator $urlGenerator;

  private array $postData = [];

  private ?array $files = null;

  private array $server = [];

  private IL10N $l;

  protected $appName;

  /** {@inheritdoc} */
  public function setup(): void
  {
    self::enableDeprecations('grumble');

    $this->generateCalendarBackend();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $userStorage = $this->getUserStorage();
    $appStorage = $this->getAppStorage();

    /** @var IRequest $request */
    $request = $this->createStub(Request::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );
    $request->method('__get')->willReturnCallback(
      function (string $key): mixed {
        switch ($key) {
          case 'files':
            return $this->files;
          case 'server':
            return $this->server;
          default:
            return null;
        }
      }
    );

    $appContainer = $this->mockProvider->getAppContainer();

    $this->appName = $appContainer->get('appName');
    $this->l = $this->mockProvider->getL10N();
    $this->tempManager = $appContainer->get(ITempManager::class);
    $this->urlGenerator = $appContainer->get(IURLGenerator::class);

    $this->fileMimeType = $this->mimeTypeDetector->detectString(self::FILE_CONTENT);

    $this->controller = new Controller\UploadsController(
      appName: $this->mockProvider->appName,
      appStorage: $appStorage,
      l: $this->l,
      logger: $this->mockProvider->getLoggerInterface(),
      request: $request,
      userStorage: $userStorage,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testStashDefault(): void
  {
    $response = $this->controller->stash();
    $this->ensureGrumbleResponse($response);
  }

  /** @return void */
  public function testObtainCloudFilesStashModes(): void
  {
    /* $file = */$this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::TEST,
    );
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertTrue(array_is_list($data));
    $this->assertEquals(count($cloudPaths), count($data));
    /** @var Controller\DTO\UploadModeTest $dataItem */
    foreach ($data as $dataItem) {
      $this->assertInstanceOf(Controller\DTO\UploadModeTest::class, $dataItem);
      $this->assertEqualsCanonicalizing(
        [
          Controller\EnumFileUploadMode::COPY,
          Controller\EnumFileUploadMode::MOVE
        ],
        $dataItem->availableUploadModes,
      );
    }

    // generate an artifical database backed file in order to also  have mode 'LINK'.
    $dbDirEntry = new Entities\DatabaseStorageFile()
      ->setName(self::BASE_NAME)
      ->setParent(
        new Entities\DatabaseStorageFolder()
          ->setName(dirname(self::ORIGINAL_FILE_NAME))
          ->setParent(new Entities\DatabaseStorageFolder),
      )
      ->setFile(
        new Entities\EncryptedFile(self::BASE_NAME, self::FILE_CONTENT, $this->fileMimeType),
      );
    $this->setDatabaseDirEntry($dbDirEntry);
    $this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::TEST,
    );
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertTrue(array_is_list($data));
    $this->assertEquals(count($cloudPaths), count($data));
    /** @var Controller\DTO\UploadModeTest $dataItem */
    foreach ($data as $dataItem) {
      $this->assertInstanceOf(Controller\DTO\UploadModeTest::class, $dataItem);
      $this->assertEqualsCanonicalizing(
        [
          Controller\EnumFileUploadMode::COPY,
          Controller\EnumFileUploadMode::LINK,
          Controller\EnumFileUploadMode::MOVE,
        ],
        $dataItem->availableUploadModes,
      );
    }
  }

  /** @return void */
  public function testStashCloudFilesWithoutFiles(): void
  {
    $uploadModes = Controller\EnumFileUploadMode::cases();
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $oldLine = -1;
    foreach ($uploadModes as $uploadMode) {
      $response = $this->controller->stash(
        cloudPaths: $cloudPaths,
        uploadMode: $uploadMode,
      );
      $this->ensureGrumbleResponse($response);
      if ($oldLine > 0) {
        $this->assertEquals($oldLine, $response->getData()['line']);
      }
      $oldLine = $response->getData()['line'];
    }
    $first = true;
    // $this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    // generate a directory instead of a file
    $this->userStorage->ensureFolder(self::ORIGINAL_FILE_NAME);
    foreach ($uploadModes as $uploadMode) {
      $response = $this->controller->stash(
        cloudPaths: $cloudPaths,
        uploadMode: $uploadMode,
      );
      $this->ensureGrumbleResponse($response);
      if ($first) {
        $this->assertNotEquals($oldLine, $response->getData()['line']);
      } else {
        $this->assertEquals($oldLine, $response->getData()['line']);
      }
      $oldLine = $response->getData()['line'];
      $first = false;
    }
  }

  /** @return void */
  public function testStashCloudFilesAsCopy(): void
  {
    /* $file = */$this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::COPY,
    );
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertTrue(array_is_list($data));
    $this->assertEquals(count($cloudPaths), count($data));
    /** @var Controller\DTO\UploadFileData $dataItem */
    foreach ($data as $dataItem) {
      $this->assertInstanceOf(Controller\DTO\UploadFileData::class, $dataItem);
      $this->assertEquals(self::BASE_NAME, $dataItem->name);
      $this->assertEquals(self::BASE_NAME, $dataItem->original_name);
      $this->assertEquals(strlen(self::FILE_CONTENT), $dataItem->size);
      $this->assertEquals(0, $dataItem->error);
      $this->assertEquals(Util::fileUploadError($dataItem->error, $this->l), $dataItem->str_error);
      $this->assertEquals(Controller\EnumFileUploadMode::COPY, $dataItem->upload_mode);
      $this->assertEquals($dataItem->tmp_name, (string)Uuid::asUuid($dataItem->tmp_name));
      $stashedFile = $this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $dataItem->tmp_name);
      $this->assertInstanceOf(ISimpleFile::class, $stashedFile);
      $this->assertEquals(self::FILE_CONTENT, $stashedFile->getContent());
    }
  }

  /** @return void */
  public function testStashCloudFilesAsMove(): void
  {
    $file = $this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::MOVE,
    );
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertTrue(array_is_list($data));
    $this->assertEquals(count($cloudPaths), count($data));
    /** @var Controller\DTO\UploadFileData $dataItem */
    foreach ($data as $dataItem) {
      $this->assertInstanceOf(Controller\DTO\UploadFileData::class, $dataItem);
      $this->assertEquals(self::BASE_NAME, $dataItem->name);
      $this->assertEquals(self::ORIGINAL_FILE_NAME, $dataItem->original_name);
      $this->assertEquals(strlen(self::FILE_CONTENT), $dataItem->size);
      $this->assertEquals(0, $dataItem->error);
      $this->assertEquals(Util::fileUploadError($dataItem->error, $this->l), $dataItem->str_error);
      $this->assertEquals(Controller\EnumFileUploadMode::MOVE, $dataItem->upload_mode);
      $this->assertEquals($dataItem->tmp_name, (string)Uuid::asUuid($dataItem->tmp_name));
      $stashedFile = $this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $dataItem->tmp_name);
      $this->assertInstanceOf(ISimpleFile::class, $stashedFile);
      $this->assertEquals(self::FILE_CONTENT, $stashedFile->getContent());
    }

    // test the permission check,  move must fail if PERMISSION_DELETE is not set
    $this->setNodePermissions($file, $file->getPermissions() & ~CloudConstants::PERMISSION_DELETE);
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::MOVE,
    );
    $this->ensureGrumbleResponse($response);
  }

  /** @return void */
  public function testStashCloudFilesAsLink(): void
  {
    /* $file = */$this->userStorage->putContent(self::ORIGINAL_FILE_NAME, self::FILE_CONTENT);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $response = $this->controller->stash(
      cloudPaths: $cloudPaths,
      uploadMode: Controller\EnumFileUploadMode::LINK,
    );
    $this->ensureGrumbleResponse($response);
    // print_r($response->getData());
    // $oldLine = $response->getData()['line'];
  }

  /** @return void */
  public function testStashDbFiles(): void
  {
    // generate an artifical database backed file in order to also  have mode 'LINK'.
    $dbDirEntry = new Entities\DatabaseStorageFile()
      ->setName(self::BASE_NAME)
      ->setParent(
        new Entities\DatabaseStorageFolder()
          ->setName(dirname(self::ORIGINAL_FILE_NAME))
          ->setParent(new Entities\DatabaseStorageFolder),
      )
      ->setFile(
        new Entities\EncryptedFile(self::BASE_NAME, self::FILE_CONTENT, $this->fileMimeType)
          ->setId(13),
      );
    $this->setDatabaseDirEntry($dbDirEntry);
    $cloudPaths = [ self::ORIGINAL_FILE_NAME ];
    $uploadModes = [
      Controller\EnumFileUploadMode::COPY,
      Controller\EnumFileUploadMode::LINK,
      Controller\EnumFileUploadMode::MOVE,
    ];
    foreach ($uploadModes as $uploadMode) {
      $response = $this->controller->stash(
        cloudPaths: $cloudPaths,
        uploadMode: $uploadMode,
      );
      $this->assertInstanceOf(Http\DataResponse::class, $response);
      $this->assertEquals(Http::STATUS_OK, $response->getStatus());
      $data = $response->getData();
      $this->assertIsArray($data);
      $this->assertTrue(array_is_list($data));
      $this->assertEquals(count($cloudPaths), count($data));
      /** @var Controller\DTO\UploadFileData $dataItem */
      foreach ($data as $dataItem) {
        $this->assertInstanceOf(Controller\DTO\UploadFileData::class, $dataItem);
        $this->assertEquals(self::BASE_NAME, $dataItem->name);
        switch ($uploadMode) {
          case Controller\EnumFileUploadMode::LINK:
            $expected = $dbDirEntry->getFile()->getId();
            break;
          case Controller\EnumFileUploadMode::COPY:
            $expected = self::BASE_NAME;
            break;
          case Controller\EnumFileUploadMode::MOVE:
            $expected = self::ORIGINAL_FILE_NAME;
            break;
        }
        $this->assertEquals($expected, $dataItem->original_name);
        $this->assertEquals(strlen(self::FILE_CONTENT), $dataItem->size);
        $this->assertEquals(0, $dataItem->error);
        $this->assertEquals(Util::fileUploadError($dataItem->error, $this->l), $dataItem->str_error);
        $this->assertEquals($uploadMode, $dataItem->upload_mode);
        if ($uploadMode == Controller\EnumFileUploadMode::LINK) {
          $this->assertNull($dataItem->tmp_name);
        } else {
          $this->assertEquals($dataItem->tmp_name, (string)Uuid::asUuid($dataItem->tmp_name));
          $stashedFile = $this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $dataItem->tmp_name);
          $this->assertInstanceOf(ISimpleFile::class, $stashedFile);
          $this->assertEquals(self::FILE_CONTENT, $stashedFile->getContent());
        }
      }
    }

    // generate an artifical database backed file in order to also  have mode 'LINK'.
    $dbDirEntry = new Entities\DatabaseStorageFolder()
      ->setName(self::BASE_NAME)
      ->setParent(
        new Entities\DatabaseStorageFolder()
          ->setName(dirname(self::ORIGINAL_FILE_NAME))
          ->setParent(new Entities\DatabaseStorageFolder),
      );
    $this->setDatabaseDirEntry($dbDirEntry);
    $uploadModes = Controller\EnumFileUploadMode::cases();
    $oldLine = -1;
    foreach ($uploadModes as $uploadMode) {
      $response = $this->controller->stash(
        cloudPaths: $cloudPaths,
        uploadMode: $uploadMode,
      );
      $this->ensureGrumbleResponse($response);
      if ($oldLine > 0) {
        $this->assertEquals($oldLine, $response->getData()['line']);
      }
      $oldLine = $response->getData()['line'];
    }
  }

  /** @return void */
  public function testStashDeviceFilesNoFiles(): void
  {
    $response = $this->controller->stash(
      cloudPaths: [],
      uploadMode: Controller\EnumFileUploadMode::COPY,
    );
    $this->ensureGrumbleResponse($response);
    $oldMessage = $response->getData()['messages'][0];
    $this->files = [];
    $this->server['CONTENT_LENGTH'] = NcUtil::uploadLimit() + 1;
    $response = $this->controller->stash(
      cloudPaths: [],
      uploadMode: Controller\EnumFileUploadMode::COPY,
    );
    $this->ensureGrumbleResponse($response);
    $this->assertNotEquals($oldMessage, $response->getData()['messages'][0]);
  }

  /** @return void */
  public function testStashDeviceFiles(): void
  {
    $tmpFileName = $this->tempManager->getTemporaryFile();
    file_put_contents($tmpFileName, self::FILE_CONTENT);
    $this->files[Controller\UploadsController::UPLOAD_KEY] = [
      'name' => self::BASE_NAME,
      'type' => $this->fileMimeType,
      'size' => strlen(self::FILE_CONTENT),
      'tmp_name' => $tmpFileName,
      'error' => 0,
      'full_path' => 'this/value/cannot/be/trusted/as/documented',
    ];
    /** @var Http\DataResponse $response */
    $response = $this->controller->stash(
      cloudPaths: [],
      uploadMode: Controller\EnumFileUploadMode::COPY,
    );
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertEquals(1, count($data));
    $this->assertTrue(array_is_list($data));
    /** @var Controller\DTO\UploadFileData $dataItem */
    foreach ($data as $dataItem) {
      $this->assertInstanceOf(Controller\DTO\UploadFileData::class, $dataItem);
      $this->assertEquals($dataItem->tmp_name, $dataItem->name);
      $this->assertEquals(self::BASE_NAME, $dataItem->original_name);
      $this->assertEquals(strlen(self::FILE_CONTENT), $dataItem->size);
      $this->assertEquals(UPLOAD_ERR_OK, $dataItem->error);
      $this->assertEquals(Util::fileUploadError($dataItem->error, $this->l), $dataItem->str_error);
      $this->assertEquals(Controller\EnumFileUploadMode::COPY, $dataItem->upload_mode);
      $this->assertEquals($dataItem->tmp_name, (string)Uuid::asUuid($dataItem->tmp_name));
      $stashedFile = $this->appStorage->getFile(AppStorage::UPLOAD_FOLDER, $dataItem->tmp_name);
      $this->assertInstanceOf(ISimpleFile::class, $stashedFile);
      $this->assertEquals(self::FILE_CONTENT, $stashedFile->getContent());
    }
  }

  /** @return void */
  public function testStashDeviceFilesAsMoveOrLink(): void
  {
    $response = $this->controller->stash(
      cloudPaths: [],
      uploadMode: Controller\EnumFileUploadMode::MOVE,
    );
    $this->ensureGrumbleResponse($response);

    $response = $this->controller->stash(
      cloudPaths: [],
      uploadMode: Controller\EnumFileUploadMode::LINK,
    );
    $this->ensureGrumbleResponse($response);
  }

  /** @return void */
  public function testMoveStashedFileToCloud(): void
  {
    $stashedFile = $this->appStorage->newTemporaryFile(AppStorage::UPLOAD_FOLDER);
    $stashedFileName = $stashedFile->getName();
    $stashedFile->putContent(self::FILE_CONTENT);
    $response = $this->controller->move(
      stashedFile: $stashedFileName,
      destinationPath: self::MOVE_DESTINATION,
    );
    $this->assertInstanceOf(Http\JsonResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    /** @var Controller\DTO\FileUploadMoveResponse $data */
    $data = $response->getData();
    $this->assertInstanceOf(Controller\DTO\FileUploadMoveResponse::class, $data);
    $this->assertEquals(self::BASE_NAME, $data->fileName);
    $this->assertIsString($data->downloadLink);
    $cloudFile = $this->userStorage->get(self::MOVE_DESTINATION);
    $this->assertInstanceOf(File::class, $cloudFile);
    $this->assertEquals($cloudFile->getId(), $data->fileId);
    $this->assertEquals($this->fileMimeType, $cloudFile->getMimetype());
    $this->assertTrue(array_is_list($data->messages));
    $this->assertEquals(1, count($data->messages));
    $this->assertStringContainsString(self::MOVE_DESTINATION, $data->messages[0]);
    $this->assertStringContainsString($stashedFileName, $data->messages[0]);
    $this->assertFalse($this->appStorage->fileExists(AppStorage::UPLOAD_FOLDER, $stashedFileName));
    // print_r($data);
  }
}
