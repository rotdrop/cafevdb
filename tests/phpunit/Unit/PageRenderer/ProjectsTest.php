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

namespace OCA\CAFEVDB\Tests\Unit\PageRenderer;

use DOMDocument;
use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCP\IRequest;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Folder;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Listener;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

#[Attributes\CoversClass(PageRenderer\Projects::class)]
/** Test aspects of the AllMusicians page renderer. */
#[Attributes\CoversClass(Entities\Project::class)]
#[Attributes\CoversClass(Entities\ProjectEvent::class)]
#[Attributes\CoversClass(Entities\ProjectWebPage::class)]
#[Attributes\CoversClass(Events\AfterProjectDeletedEvent::class)]
#[Attributes\CoversClass(Events\ProjectEvent::class)]
#[Attributes\CoversClass(Events\ProjectUpdatedEvent::class)]
#[Attributes\CoversClass(Listener\CalendarObjectCreatedEventListener::class)]
#[Attributes\CoversClass(Listener\ProjectEventEntityListener::class)]
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\CoversClass(ProjectService::class)]
#[Attributes\CoversClass(Repositories\ProjectWebPagesRepository::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\ProjectModeNavigationItemTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrumentationNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\InvoiceNumberHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerClosedEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\UndoableRunQueueException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\CalendarObjectUpdatedEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CloudUserConnectorService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MailingListsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\UserStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ProjectsTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;

  private PageRenderer\Projects $renderer;

  private PHPMyEdit $phpMyEdit;

  private IRequest $request;

  private array $postData = [];

  private static bool $migrationsApplied = false;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateCalendarBackend();

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      self::$migrationsApplied = true;
    }

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->mockProvider->getUserSession()->method('isLoggedIn')->willReturn(true);

    $this->entityManager = $this->entityManager ?? $mockProvider->getEntityManager();

    $appContainer = $mockProvider->getAppContainer();

    $this->request = $mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      }
    );

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    // what a mess ...

    /** @var ConfigService $configService */
    $configService = $mockProvider->getConfigService();

    $mailingListsService = $this->createStub(MailingListsService::class);

    $projectParticipantFieldsService = new ProjectParticipantFieldsService(
      configService: $configService,
      entityManager: $this->entityManager,
    );
    $mockProvider->registerClassInstance(
      ProjectParticipantFieldsService::class,
      $projectParticipantFieldsService,
    );

    $musicianService = new MusicianService(
      configService: $configService,
      entityManager: $this->entityManager,
      listsService: $mailingListsService,
    );
    $mockProvider->registerClassInstance(
      MusicianService::class,
      $musicianService,
    );

    $service = \OCA\Redaxo\Service\RPC::class;
    $cmsRpc = $this->createStub($service);
    $mockProvider->registerClassInstance($service, $cmsRpc, global: false);
    $cmsRpc->method('addArticle')->willReturnCallback(
      function($pageName, $category, $pageTemplate) use (&$id) {
        return [
          [
            'articleId' => $id++,
            'articleName' => $pageName,
            'categoryId' => $category,
            'priority' => 13,
          ],
        ];
      }
    );
    $cmsRpc->method('articlesByName')->willReturn([]);

    $service = \OCA\DokuWiki\Service\AuthDokuWiki::class;
    $wikiRpc = $this->createStub($service);
    $mockProvider->registerClassInstance($service, $wikiRpc, global: false);
    $wikiRpc->method('getPage')->willReturn('some string');

    $id = 13;
    foreach (ConfigConstants::CMS_CATEGORIES as $cmsCategory) {
      $ucfSlug = ucfirst($cmsCategory);
      $configService->setConfigValue(ConfigConstants::CMS_PREFIX . $ucfSlug, $id++);
      $configService->setConfigValue(ConfigConstants::CMS_PREFIX . $ucfSlug . 'Module', $id++);
    }
    $configService->setConfigValue(ConfigConstants::CMS_PREFIX . 'SubPageTemplate', 13);
    $configService->setConfigValue(ConfigConstants::CMS_PREFIX . 'ConcertModule', 13);

    $configService->setConfigValue(ConfigConstants::SHARED_FOLDER, 'orchestra');
    $configService->setConfigValue(ConfigConstants::PROJECTS_FOLDER, 'projects');

    $projectService = new ProjectService(
      configService: $configService,
      entityManager: $this->entityManager,
      userStorage: $this->createStub(UserStorage::class),
      participantFieldsService: $projectParticipantFieldsService,
      musicianService: $musicianService,
      eventDispatcher: $mockProvider->getEventDispatcher(),
    );
    $mockProvider->registerClassInstance(
      ProjectService::class,
      $projectService,
      global: true,
    );

    $userFolder = $this->createStub(Folder::class);
    $userFolder->method('get')->willReturnCallback(function(string $path) use ($configService) {
      $node = $this->createStub(Node::class);
      $node->method('getPath')->willReturn($path);
      return $node;
    });

    $rootFolder = $this->getMockBuilder(IRootFolder::class)
      ->disableOriginalConstructor()
      ->getMock();
    $rootFolder->expects($this->atLeastOnce())
      ->method('getUserFolder')
      ->with(MockProvider::EXECUTIVE_BOARD_UID)
      ->willReturn($userFolder);

    $userStorage = new UserStorage(
      userSession: $mockProvider->getUserSession(),
      appContainer: $mockProvider->getAppContainer(),
      rootFolder: $rootFolder,
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );

    $this->renderer = new PageRenderer\Projects(
      configService: $configService,
      entityManager: $this->entityManager,
      request: $this->request,
      phpMyEdit: $this->phpMyEdit,
      pageNavigation: $appContainer->get(PageRenderer\Util\Navigation::class),
      toolTipsService: $appContainer->get(ToolTipsService::class),
      //
      listsService: $mailingListsService,
      projectService: $projectService,
      userStorage: $userStorage,
    );
  }

  /** {@inheritdoc} */
  public function testApplyMigrations(): void
  {
    $this->assertNotNull($this->renderer->shortTitle());
    $this->assertNotEmpty($this->renderer->navigationItems());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testDelete')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  // BEFORE INSERT TRIGGER NEWVALS

  private const BEFORE_INSERT_TRIGGER_NEWVALS = [
    'ProjectInstrumentationNumbers__master_key_' => '',
    'Instruments__master_key_' => '',
    'ProjectParticipantFields__master_key_' => '',
    'year' => '2099',
    'name' => 'Test2099',
    'type' => 'temporary',
    'ProjectInstrumentationNumbers:instrument_id' => '15,14,16,6',
    'ProjectInstrumentationNumbers:voice' => '',
    'ProjectInstrumentationNumbers:quantity' => '0',
    'mailing_list_id' => 'create',
    'registration_start_date' => '31.01.2099',
    'registration_deadline' => '28.02.2099',
  ];

  // BEFORE INSERT TRIGGER CHANGED

  private const BEFORE_INSERT_TRIGGER_CHANGED = [
    'ProjectInstrumentationNumbers__master_key_',
    'Instruments__master_key_',
    'ProjectParticipantFields__master_key_',
    'year',
    'name',
    'type',
    'ProjectInstrumentationNumbers:instrument_id',
    'ProjectInstrumentationNumbers:voice',
    'ProjectInstrumentationNumbers:quantity',
    'mailing_list_id',
    'registration_start_date',
    'registration_deadline',
  ];

  // BEFORE INSERT ALL NEWVALSD

  // BEFORE NEWVALS Array ( [ProjectInstrumentationNumbers__master_key_] => [Instruments__master_key_] => [ProjectParticipantFields__master_key_] => [year] => 2099 [name] => Test2099 [type] => temporary [ProjectInstrumentationNumbers:instrument_id] => 15,14,16,6 [ProjectInstrumentationNumbers:voice] => 6:0,14:0,15:0,16:0 [ProjectInstrumentationNumbers:quantity] => 0 [mailing_list_id] => create )

  // BEFORE INSERT ASLL CHANGFED

  // BEFORE CHANGED Array ( [0] => ProjectInstrumentationNumbers__master_key_ [1] => Instruments__master_key_ [2] => ProjectParticipantFields__master_key_ [3] => year [4] => name [5] => type [8] => ProjectInstrumentationNumbers:quantity [9] => mailing_list_id [10] => ProjectInstrumentationNumbers:instrument_id [11] => ProjectInstrumentationNumbers:voice )

  // BLAH

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const DO_ADD_PROJECT_FORM_DATA = 'projectName=&recordsPerPage=40&template=projects&table=Projects&templateRenderer=template%3Aprojects&projectId=&PME_sys_mtable=Projects&PME_sys_mkey%5Bid%5D=int&PME_sys_qf4=2025&PME_sys_qf4_comp=%3E%3D&PME_sys_qf6_comp=%3D&PME_sys_qf19_comp=%3D&PME_sys_qf4=2025&PME_sys_qf4_comp=%3E%3D&PME_sys_cur_tab=&PME_sys_qfn=%26PME_sys_qf4%3D2025%26PME_sys_qf4_comp%3D%253E%253D&PME_sys_rec=&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=0&PME_sys_op_name=add&PME_data_id=&PME_data_year=2099&PME_data_name=Test2099&PME_data_deleted=&PME_data_type=temporary&PME_data_ProjectInstrumentationNumbers%3Ainstrument_id%5B%5D=15&PME_data_ProjectInstrumentationNumbers%3Ainstrument_id%5B%5D=14&PME_data_ProjectInstrumentationNumbers%3Ainstrument_id%5B%5D=16&PME_data_ProjectInstrumentationNumbers%3Ainstrument_id%5B%5D=6&instrumentVoiceRequest%5B43%5D=&instrumentVoiceRequest%5B15%5D=&instrumentVoiceRequest%5B14%5D=&instrumentVoiceRequest%5B16%5D=&instrumentVoiceRequest%5B6%5D=&instrumentVoiceRequest%5B3%5D=&instrumentVoiceRequest%5B20%5D=&instrumentVoiceRequest%5B7%5D=&instrumentVoiceRequest%5B1%5D=&instrumentVoiceRequest%5B5%5D=&instrumentVoiceRequest%5B30%5D=&instrumentVoiceRequest%5B2%5D=&instrumentVoiceRequest%5B21%5D=&instrumentVoiceRequest%5B17%5D=&instrumentVoiceRequest%5B12%5D=&instrumentVoiceRequest%5B9%5D=&instrumentVoiceRequest%5B22%5D=&instrumentVoiceRequest%5B13%5D=&instrumentVoiceRequest%5B23%5D=&instrumentVoiceRequest%5B4%5D=&instrumentVoiceRequest%5B8%5D=&instrumentVoiceRequest%5B35%5D=&instrumentVoiceRequest%5B31%5D=&instrumentVoiceRequest%5B29%5D=&instrumentVoiceRequest%5B11%5D=&instrumentVoiceRequest%5B32%5D=&instrumentVoiceRequest%5B33%5D=&instrumentVoiceRequest%5B34%5D=&instrumentVoiceRequest%5B18%5D=&instrumentVoiceRequest%5B26%5D=&instrumentVoiceRequest%5B27%5D=&instrumentVoiceRequest%5B39%5D=&instrumentVoiceRequest%5B40%5D=&instrumentVoiceRequest%5B28%5D=&instrumentVoiceRequest%5B24%5D=&instrumentVoiceRequest%5B37%5D=&instrumentVoiceRequest%5B10%5D=&instrumentVoiceRequest%5B19%5D=&instrumentVoiceRequest%5B25%5D=&instrumentVoiceRequest%5B38%5D=&instrumentVoiceRequest%5B36%5D=&instrumentVoiceRequest%5B46%5D=&instrumentVoiceRequest%5B47%5D=&PME_data_ProjectInstrumentationNumbers%3Aquantity=0&PME_data_registration_start_date=&PME_data_mailing_list_id%5B%5D=create&PME_data_updated=&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aprojects&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neues+Projekt&reloadName=PME_sys_applyadd&reloadValue=Anwenden&modalDialog=true&modified=true&PME_sys_applyadd=Anwenden';
  // phpcs:enable Generic.Files.LineLength.TooLong

  /** @return void */
  #[Attributes\Depends('testApplyMigrations')]
  public function testInsert(): void
  {
    $this->assertTrue(self::$migrationsApplied);

    // @todo: mock the EventsService to just accept everything.

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Projects::TABLE;

    $oldValues = [];
    $newValues = self::BEFORE_INSERT_TRIGGER_NEWVALS;
    $changed = self::BEFORE_INSERT_TRIGGER_CHANGED;

    $this->entityManager->beginTransaction();
    try {
      $result = $this->renderer->beforeInsertTrigger(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );

      $this->assertEquals(true, $result);

      $result = $this->renderer->beforeInsertDoInsertAll(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );

      $this->assertEquals(true, $result);

      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollBack();
      }
      throw $t;
    }

    $this->assertEmpty($changed);

    $allProjects = $this->entityManager->getRepository(Entities\Project::class)->findAll();
    $this->assertEquals(1, count($allProjects));
    /** @var Entities\Project $project */
    $project = array_pop($allProjects);
    $instrumentIds = explode(PageRenderer\DataConstants::VALUES_SEP, self::BEFORE_INSERT_TRIGGER_NEWVALS['ProjectInstrumentationNumbers:instrument_id']);
    $instruments = $project->getInstrumentationNumbers();
    $this->assertEquals(count($instrumentIds), $instruments->count());
    /** @var Entities\ProjectInstrumentationNumber $number */
    foreach ($instruments as $number) {
      $this->assertInstanceOf(Entities\ProjectInstrumentationNumber::class, $number);
      $this->assertTrue(in_array($number->getInstrument()->getId(), $instrumentIds));
    }

    // The project registration event.
    $this->assertEquals(1, $project->getCalendarEvents()->count());

    $this->entityManager->beginTransaction();
    try {
      // Hard to test:
      // - no wiki
      // - no redaxo
      // - no file system
      //
      $this->renderer->afterInsertTrigger(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollBack();
      }
      throw $t;
    }
  }

  private const BEFORE_CHANGED_TRIGGER_DATA = [
    'changed' => [
      'ProjectInstrumentationNumbers__master_key_',
      'Instruments__master_key_',
      'ProjectParticipantFields__master_key_',
      'name',
    ],
    'oldValues' => self::BEFORE_INSERT_TRIGGER_NEWVALS,
    'newValues' => [ 'name' => 'TestChanged' . self::BEFORE_INSERT_TRIGGER_NEWVALS['year'] ],
  ];

  /** @return void */
  #[Attributes\Depends('testInsert')]
  public function testChange(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Project::class);
    /** @var Entities\Entity $entity */
    $entity = $repository->findOneBy(['name' => self::BEFORE_INSERT_TRIGGER_NEWVALS['name']]);
    $entityId = $entity->getId();

    $oldValues = self::BEFORE_CHANGED_TRIGGER_DATA['oldValues'];
    $newValues = array_merge($oldValues, self::BEFORE_CHANGED_TRIGGER_DATA['newValues']);
    $changed = self::BEFORE_CHANGED_TRIGGER_DATA['changed'];

    $oldValues['id'] =
      $newValues['id'] = $entityId;

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Projects::TABLE;

    $this->entityManager->beginTransaction();
    try {

      $result = $this->renderer->beforeUpdateTrigger(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );

      $this->assertEquals(true, $result);

      $result = $this->renderer->beforeUpdateDoUpdateAll(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );

      $this->assertEquals(true, $result);
      $this->assertEmpty($changed);

      // pme would recompute the change-set ...
      $changed = ['name'];

      $this->renderer->afterUpdateTrigger(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );

      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollBack();
      }
      throw $t;
    }

    $this->entityManager->clear();
    $entity = $repository->find(['id' => $entityId]);
    $this->assertNotNull($entity);
    $this->assertEquals(self::BEFORE_CHANGED_TRIGGER_DATA['newValues']['name'], $entity->getName());
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const DELETE_ENTITY_FORM_DATA = 'projectId=@ENTITY_ID@&projectName=&recordsPerPage=40&template=projects&table=Projects&templateRenderer=template%3Aprojects&PME_sys_mtable=Projects&PME_sys_mkey%5Bid%5D=int&PME_sys_qf4=2025&PME_sys_qf4_comp=%3E%3D&PME_sys_qf6_comp=%3D&PME_sys_qf19_comp=%3D&PME_sys_qf4=2025&PME_sys_qf4_comp=%3E%3D&PME_sys_cur_tab=&PME_sys_qfn=%26PME_sys_qf4%3D2025%26PME_sys_qf4_comp%3D%253E%253D&PME_sys_rec%5Bid%5D=@ENTITY_ID@&PME_sys_groupby_rec%5Bid%5D=@ENTITY_ID@&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=0&PME_sys_op_name=delete&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aprojects&initialViewOperation=true&initialName=PME_sys_operation&initialValue=Anzeigen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&reloadName=PME_sys_operation&reloadValue=L%C3%B6schen&modalDialog=true&modified=true&PME_sys_savedelete=L%C3%B6schen&PME_sys_savedelete=L%C3%B6schen&PME_sys_operation=Null';
  // phpcs:enable Generic.Files.LineLength.TooLong

  /** @return void */
  #[Attributes\Depends('testChange')]
  #[Attributes\Depends('testRenderAdd')]
  #[Attributes\Depends('testRenderChange')]
  #[Attributes\Depends('testRenderView')]
  #[Attributes\Depends('testRenderDelete')]
  #[Attributes\Depends('testRenderList')]
  public function testDelete(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Project::class);
    /** @var Entities\Project $entity */
    $entity = $repository->findOneBy(['name' => self::BEFORE_CHANGED_TRIGGER_DATA['newValues']['name']]);
    $this->assertNotNull($entity);
    $entityId = $entity->getId();
    $this->assertTrue($entityId > 0);

    $postString = str_replace('@ENTITY_ID@', $entityId, self::INDIVIDUAL_ENTITY_FORM_DATA);
    parse_str($postString, $this->postData);

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Projects::TABLE;

    [ 'rec' => $this->phpMyEdit->rec ] = $this->phpMyEdit->recordIdFromRequest();
    $oldValues = $newValues = []; // should not matter
    $changed = ['something'];

    $this->entityManager->beginTransaction();
    try {
      $this->renderer->deleteTrigger(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollBack();
      }
      throw $t;
    }
    $this->assertEmpty($changed);

    $this->entityManager->clear();
    $entity = $repository->find(['id' => $entityId]);
    $this->assertEquals(null, $entity);
  }

  /** @return void */
  #[Attributes\Depends('testChange')]
  public function testRenderList(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\Projects::TEMPLATE;
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      ob_end_clean();
      throw $t;
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const ADD_PROJECT_FORM_DATA = 'PME_sys_1navfmup=0&PME_sys_navnpup=40&projectName=&recordsPerPage=40&template=projects&table=Projects&templateRenderer=template%3Aprojects&projectId=&PME_sys_qfyear=2025&PME_sys_qfyear_comp=%3E%3D&PME_sys_fl=0&PME_sys_qfn=%26PME_sys_qfyear%3D2025%26PME_sys_qfyear_comp%3D%253E%253D&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=&PME_sys_qf4_comp=%3E%3D&PME_sys_qf4=2025&PME_sys_qf6_comp=%3D&PME_sys_qf6=&PME_sys_qf13=&PME_sys_qf14=&PME_sys_qf19_comp=%3D&PME_sys_qf19=&PME_sys_1navfmdown=0&PME_sys_navnpdown=40&PME_sys_mtable=Projects&PME_sys_mkey%5Bid%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=Neues+Projekt&PME_sys_cur_tab=all&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aprojects&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neues+Projekt&reloadName=PME_sys_operation&reloadValue=Neues+Projekt&modalDialog=true&modified=false&PME_sys_operation=Neues+Projekt';
  // phpcs:enable Generic.Files.LineLength.TooLong

  /** @return void */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderAdd(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\AllMusicians::TEMPLATE;
    parse_str(self::ADD_PROJECT_FORM_DATA, $this->postData);
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      ob_end_clean();
      throw $t;
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }

// phpcs:disable Generic.Files.LineLength.TooLong
  private const INDIVIDUAL_ENTITY_FORM_DATA = 'PME_sys_1navfmup=0&PME_sys_navnpup=40&projectName=&recordsPerPage=40&template=projects&table=Projects&templateRenderer=template%3Aprojects&projectId=&PME_sys_qf4=2025&PME_sys_qf4_comp=%3E%3D&PME_sys_fl=0&PME_sys_qfn=%26PME_sys_qf4%3D2025%26PME_sys_qf4_comp%3D%253E%253D&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=&PME_sys_qf4_comp=%3E%3D&PME_sys_qf4=2025&PME_sys_qf6_comp=%3D&PME_sys_qf6=&PME_sys_qf13=&PME_sys_qf14=&PME_sys_qf19_comp=%3D&PME_sys_qf19=&PME_sys_1navfmdown=0&PME_sys_navnpdown=40&PME_sys_mtable=Projects&PME_sys_mkey%5Bid%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=Anzeigen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aprojects&initialViewOperation=true&initialName=PME_sys_operation&initialValue=Anzeigen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&reloadName=PME_sys_operation&reloadValue=Anzeigen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&modalDialog=true&modified=false&PME_sys_operation=Anzeigen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@';
  // phpcs:enable Generic.Files.LineLength.TooLong

  /** @return void */
  #[Attributes\Depends('testChange')]
  public function testRenderView(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Project::class);
    /** @var Entities\Project $entity */
    $entity = $repository->findOneBy(['name' => self::BEFORE_CHANGED_TRIGGER_DATA['newValues']['name']]);
    $this->assertNotNull($entity);
    $entityId = $entity->getId();
    $postString = str_replace('@ENTITY_ID@', $entityId, self::INDIVIDUAL_ENTITY_FORM_DATA);
    parse_str($postString, $this->postData);
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      ob_end_clean();
      throw $t;
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }

  /** @return void */
  #[Attributes\Depends('testChange')]
  public function testRenderDelete(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Project::class);
    /** @var Entities\Project $entity */
    $entity = $repository->findOneBy(['name' => self::BEFORE_CHANGED_TRIGGER_DATA['newValues']['name']]);
    $this->assertNotNull($entity);
    $entityId = $entity->getId();
    $postString = str_replace('@ENTITY_ID@', $entityId, self::INDIVIDUAL_ENTITY_FORM_DATA);
    $postString = str_replace('Anzeigen', 'Löschen', $postString);
    parse_str($postString, $this->postData);
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      ob_end_clean();
      throw $t;
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }

  /** @return void */
  #[Attributes\Depends('testChange')]
  public function testRenderChange(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Project::class);
    /** @var Entities\Project $entity */
    $entity = $repository->findOneBy(['name' => self::BEFORE_CHANGED_TRIGGER_DATA['newValues']['name']]);
    $this->assertNotNull($entity);
    $entityId = $entity->getId();
    $postString = str_replace('@ENTITY_ID@', $entityId, self::INDIVIDUAL_ENTITY_FORM_DATA);
    $postString = str_replace('Anzeigen', 'Ändern', $postString);
    parse_str($postString, $this->postData);
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      ob_end_clean();
      throw $t;
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }
}
