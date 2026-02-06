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

namespace OCA\CAFEVDB\Tests\Unit\PageRenderer;

use DateTimeImmutable;
use DOMDocument;
use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

// https://symfony.com/doc/current/components/dom_crawler.html#forms
use Symfony\Component\DomCrawler;

use OCP\IRequest;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

/** Test aspects of the AllMusicians page renderer. */
#[Attributes\CoversClass(PHPMyEdit::class)]
#[Attributes\CoversClass(PageRenderer\AllMusicians::class)]
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\Musicians::class)]
#[Attributes\CoversClass(PageRenderer\PMETableViewBase::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\ProjectParticipants::class)]
#[Attributes\CoversClass(PageRenderer\Projects::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\CoversClass(PageRenderer\Util\Navigation::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\FinanceModeNavigationItemTrait::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\ProjectModeNavigationItemTrait::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\AbstractEntityManager::class)]
#[Attributes\UsesFunction(\OCA\CAFEVDB\Common\Functions\strcat::class)]
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
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ProjectParticipantsTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;

  private PageRenderer\ProjectParticipants $renderer;

  private PHPMyEdit $phpMyEdit;

  private IRequest $request;

  private array $postData = [];

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateCalendarBackend();

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $now = DateTimeImmutable::createFromFormat('Y-m-d h:i:s', '2099-01-01 12:00:00');
      $this->generateProjectParticipant(persist: true, now: $now);
      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();
      $this->generateInstruments(persist: true);
      self::$migrationsApplied = true;
    }

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->request = $this->mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(function (string $key, mixed $default = null) {
      return $this->postData[$key] ?? $default;
    });

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    $appContainer = $this->mockProvider->getAppContainer();
    $configService = $this->mockProvider->getConfigService();
    $participantFieldsService = $appContainer->get(ProjectParticipantFieldsService::class);

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    $userStorage = $this->createStub(UserStorage::class);

    $projectService = new ProjectService(
      configService: $configService,
      entityManager: $this->entityManager,
      userStorage: $userStorage,
      participantFieldsService: $participantFieldsService,
      musicianService: $appContainer->get(MusicianService::class),
      eventDispatcher: $this->mockProvider->getEventDispatcher(),
    );
    $this->mockProvider->registerClassInstance(ProjectService::class, $projectService, global: true);

    $this->postData[PersistentCGIKeys::PROJECT_ID] = self::$projectId;

    // what a mess ...
    $this->renderer = new PageRenderer\ProjectParticipants(
      configService: $configService,
      entityManager: $this->entityManager,
      request: $this->request,
      phpMyEdit: $this->phpMyEdit,
      pageNavigation: $appContainer->get(PageRenderer\Util\Navigation::class),
      toolTipsService: $appContainer->get(ToolTipsService::class),
      //
      contactsService: $appContainer->get(ContactsService::class),
      financeService: $appContainer->get(FinanceService::class),
      geoCodingService: $appContainer->get(GeoCodingService::class),
      insuranceService: $appContainer->get(InstrumentInsuranceService::class),
      phoneNumberService: $appContainer->get(PhoneNumberService::class),
      participantFieldsService: $participantFieldsService,
      projectService: $projectService,
      userStorage: $userStorage,
    );
  }

  /**
   * This is quas a setupBeforeClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  public function testApplyMigrations(): void
  {
    $this->assertNotNull($this->renderer->shortTitle());
    $this->assertNotEmpty($this->renderer->navigationItems());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderList(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\ProjectParticipants::TEMPLATE;
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

  // phpcs:disable
  private const VIEW_FORM_DATA = 'export=&PME_sys_1navfmup=0&PME_sys_navnpup=-1&projectId=@PROJECT_ID@&projectName=Test2026&recordsPerPage=40&template=project-participants&table=ProjectParticipants&templateRenderer=template%3Aproject-participants&dataPrefix%5Bmusicians%5D=Musicians%3A&participationStatusFddIndex=68&instrumentsFddIndex=61&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=-1&PME_sys_cur_tab=0&PME_sys_qf0_comp=%3D&PME_sys_qf0=&PME_sys_qf1_comp=%3D&PME_sys_qf1=&PME_sys_qf56=&PME_sys_qf57_comp=%3D&PME_sys_qf57=&PME_sys_qf74_comp=%3D&export=&PME_sys_1navfmdown=0&PME_sys_navnpdown=-1&PME_sys_mtable=ProjectParticipants&PME_sys_mkey%5Bproject_id%5D=int&PME_sys_mkey%5Bmusician_id%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=@OPERATION@%3FPME_sys_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%257D%26PME_sys_groupby_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%252C%2522participation_status%2522%253A%2522regular%2522%252C%2522ProjectInstruments__master_key_%2522%253Anull%257D&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aproject-participants&initialViewOperation=true&initialName=PME_sys_operation&initialValue=@OPERATION@%3FPME_sys_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%257D%26PME_sys_groupby_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%252C%2522participation_status%2522%253A%2522regular%2522%252C%2522ProjectInstruments__master_key_%2522%253Anull%257D&reloadName=PME_sys_operation&reloadValue=@OPERATION@%3FPME_sys_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%257D%26PME_sys_groupby_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%252C%2522participation_status%2522%253A%2522regular%2522%252C%2522ProjectInstruments__master_key_%2522%253Anull%257D&modalDialog=true&modified=false&PME_sys_operation=@OPERATION@%3FPME_sys_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%257D%26PME_sys_groupby_rec%3D%257B%2522project_id%2522%253A%2522@PROJECT_ID@%2522%252C%2522musician_id%2522%253A%2522@MUSICIAN_ID@%2522%252C%2522participation_status%2522%253A%2522regular%2522%252C%2522ProjectInstruments__master_key_%2522%253Anull%257D';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderView(): void
  {
    $this->assertNotNull($this->entityManager->find(Entities\Project::class, self::$projectId));
    /** @var Entities\Project $project */
    $project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    // testing the test-suite
    $this->assertEquals(1, $project->getParticipants()->count());
    $this->assertNotNull($this->entityManager->find(Entities\Musician::class, self::$musicianId));
    //
    parse_str(
      str_replace(['@PROJECT_ID@', '@MUSICIAN_ID@', '@OPERATION@'], [self::$projectId, self::$musicianId, 'Anzeigen'], self::VIEW_FORM_DATA),
      $this->postData,
    );
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

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderDelete(): void
  {
    $this->assertNotNull($this->entityManager->find(Entities\Project::class, self::$projectId));
    /** @var Entities\Project $project */
    $project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    // testing the test-suite
    $this->assertEquals(1, $project->getParticipants()->count());
    $this->assertNotNull($this->entityManager->find(Entities\Musician::class, self::$musicianId));
    //
    parse_str(
      str_replace(['@PROJECT_ID@', '@MUSICIAN_ID@', '@OPERATION@'], [self::$projectId, self::$musicianId, 'Löschen'], self::VIEW_FORM_DATA),
      $this->postData,
    );
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

  private const RENDER_UPDATE_FORM_VALUES = [
    'projectId' => '1',
    'projectName' => 'TestProject2099',
    'recordsPerPage' => '20',
    'template' => 'project-participants',
    'table' => 'ProjectParticipants',
    'templateRenderer' => 'template:project-participants',
    'dataPrefix[musicians]' => 'Musicians:',
    'participationStatusFddIndex' => '34',
    'instrumentsFddIndex' => '27',
    'PME_sys_mtable' => 'ProjectParticipants',
    'PME_sys_mkey[project_id]' => 'int',
    'PME_sys_mkey[musician_id]' => 'int',
    'PME_sys_qf0_comp' => '=',
    'PME_sys_qf1_comp' => '=',
    'PME_sys_qf57_comp' => '=',
    'PME_sys_cur_tab' => '0',
    'PME_sys_qfn' => '',
    'PME_sys_rec[project_id]' => '1',
    'PME_sys_rec[musician_id]' => '1',
    'PME_sys_groupby_rec[project_id]' => '1',
    'PME_sys_groupby_rec[musician_id]' => '1',
    'PME_sys_groupby_rec[participation_status]' => 'regular',
    'PME_sys_groupby_rec[ProjectInstruments__master_key_]' => '',
    'PME_sys_fm' => '0',
    'PME_sys_np' => '-1',
    'PME_sys_fl' => '0',
    'PME_sys_op_name' => 'change',
    'PME_data_project_id' => '1',
    'PME_data_musician_id' => '1',
    'PME_data_Musicians:organization' => '',
    'PME_data_Musicians:job_title' => '',
    'PME_data_Musicians:sur_name' => 'Musterperson',
    'PME_data_Musicians:first_name' => 'Max',
    'PME_data_Musicians:nick_name' => '',
    'PME_data_Musicians:display_name' => '',
    'PME_data_deleted' => '',
    'PME_data_Musicians:display_name_personal' => 'Max Musterperson',
    'PME_data_Musicians:gender' => '',
    'PME_data_Musicians:user_id_slug' => 'lieschen.mueller',
    'PME_data_ProjectInstruments:instrument_id' => ['1'],
    'PME_data_Instruments:sort_order[0]' => '1',
    'instrumentVoiceRequest[1]' => '',
    'PME_data_registration[0]' => '0',
    'PME_data_MusicianInstruments:instrument_id' => ['1', '4'],
    'PME_data_participation_status' => 'regular',
    'PME_data_Musicians:default_participation_status' => 'regular',
    'PME_data_Musicians:deleted' => '2099-01-01 12:00:00.000000',
    'PME_data_Musicians:cloud_account_disabled[0]' => '1',
    'PME_data_MusicianEmailAddresses@all:address' => ['john.doe@nowhere.tld'],
    'PME_data_Musicians:email' => 'john.doe@nowhere.tld',
    'PME_data_Musicians:mobile_phone' => '0815',
    'PME_data_Musicians:fixed_line_phone' => '4711',
    'PME_data_Musicians:address_supplement' => 'Igloo 13',
    'PME_data_Musicians:street' => 'Unauffindbarweg',
    'PME_data_Musicians:street_number' => '42',
    'PME_data_Musicians:po_box' => '',
    'PME_data_Musicians:postal_code' => 'Z-7',
    'PME_data_Musicians:city' => 'Nirgends',
    'PME_data_Musicians:country' => 'AQ',
    'PME_data_Musicians:birthday' => '01.01.2099',
    'PME_data_Musicians:remarks' => '',
    'PME_data_Musicians:language' => '',
    'PME_data_Musicians:address_book_uri' => '',
    'PME_data_Musicians:uuid' => '00000000-0000-0000-0000-000000000000',
    'PME_data_Musicians:updated' => '01.01.2099, 13:00:00',
    'PME_data_Musicians:created' => '01.01.2099, 13:00:00',
    'PME_sys_reloadOuterForm' => '',
  ];

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderUpdate(): void
  {
    $this->assertNotNull($this->entityManager->find(Entities\Project::class, self::$projectId));
    /** @var Entities\Project $project */
    $project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    // testing the test-suite
    $this->assertEquals(1, $project->getParticipants()->count());
    $this->assertNotNull($this->entityManager->find(Entities\Musician::class, self::$musicianId));
    //
    parse_str(
      str_replace(['@PROJECT_ID@', '@MUSICIAN_ID@', '@OPERATION@'], [self::$projectId, self::$musicianId, 'Ändern'], self::VIEW_FORM_DATA),
      $this->postData,
    );
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
    $crawler = new DomCrawler\Crawler($html, uri: 'https://localhost/cafevdb', baseHref: 'https://localhost');
    $form = $crawler->filter('form.pme-form')->form();
    // var_export($form->getValues());
    $this->assertEqualsCanonicalizing(self::RENDER_UPDATE_FORM_VALUES, $form->getValues());
    // ok. we can then examine the data further and modify and submit it ...
  }

  // TODO: test update instruments with various choices, including voices

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testRenderDelete')]
  #[Attributes\Depends('testRenderList')]
  #[Attributes\Depends('testRenderUpdate')]
  #[Attributes\Depends('testRenderView')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }
}
