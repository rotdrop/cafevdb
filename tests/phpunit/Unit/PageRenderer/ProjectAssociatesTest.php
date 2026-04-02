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
use Exception;
use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCP\IRequest;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator;
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
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ProjectParticipants page renderer. */
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\ProjectAssociates::class)]
#[Attributes\CoversClass(PageRenderer\Projects::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\CoversClass(PageRenderer\Util\Navigation::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianInstrumentEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ProjectInstrumentEntityListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\DoNothingReceivablesGenerator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesFunction(\OCA\CAFEVDB\Common\Functions\strCmpEmptyLast::class)]
#[Attributes\UsesFunction(\OCA\CAFEVDB\Common\Functions\strcat::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\EncryptionContextTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\GetByUuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ProjectAssociatesTest extends TestCase
{
  use GetFormValuesTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;

  private PageRenderer\ProjectAssociates $renderer;

  private PHPMyEdit $phpMyEdit;

  private IRequest $request;

  private array $postData = [];

  private static bool $migrationsApplied = false;

  private static int $projectId;

  private static int $musicianId;

  private static int $projectInstrumentId;

  private array $nonInstrumentIds = [];

  private DateTimeImmutable $now;

  /** {@inheritdoc} */
  public function setup(): void
  {
    \OCA\CAFEVDB\Wrapped\Doctrine\Deprecations\Deprecation::enableWithTriggerError();
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $countInstruments = count(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::INSTRUMENTS);
    foreach (Entities\ProjectInstrument::NON_INSTRUMENTS as $nonInstrumentName) {
      $this->nonInstrumentIds[$nonInstrumentName] = ++$countInstruments; // DB ids start at 1.
    }

    $this->generateCalendarBackend();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->now = DateTimeImmutable::createFromFormat('Y-m-d h:i:s', '2099-01-01 12:00:00');
    /** @var TimeFactory $timeFactory */
    $timeFactory = $this->getMockBuilder(TimeFactory::class)
      ->getMock();
    $timeFactory->method('now')->willReturnCallback(fn() => $this->now);
    $timeFactory->expects($this->never())->method('withTimeZone');
    $this->mockProvider->registerClassInstance(TimeFactory::class, $timeFactory, global: true);

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $this->generateProjectParticipant(persist: true, now: $this->now, delete: false);
      self::$projectId = $this->project->getId();
      self::$musicianId = $this->musician->getId();
      $this->generateInstruments(
        persist: true,
        instrumentNames: Entities\ProjectInstrument::NON_INSTRUMENTS,
        voices: 0,
      );
      $this->generateReceivable(persist: true, generatorClass: DoNothingReceivablesGenerator::class);
      $projectInstrument = $this->participant->getProjectInstruments()->first();
      self::$projectInstrumentId = $projectInstrument->getInstrument()->getId();

      // Participation status must come last as long as we dot not set an instrument "NOT AN INSTRUMENT" as project-instrument.
      $this->participant->setParticipationStatus(EnumParticipationStatus::ASSOCIATED);
      $this->entityManager->beginTransaction();
      try {
        $this->entityManager->flush();
        $this->entityManager->commit();
      } catch (Throwable $t) {
        if ($this->entityManager->isTransactionActive()) {
          $this->entityManager->rollBack();
        }
        throw $t;
      }

      self::$migrationsApplied = true;
    }

    $this->request = $this->mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(function (string $key, mixed $default = null) {
      return $this->postData[$key] ?? $default;
    });

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    $appContainer = $this->mockProvider->getAppContainer();
    $configService = $this->mockProvider->getConfigService();
    $participantFieldsService = $appContainer->get(ProjectParticipantFieldsService::class);

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    $userStorage = $this->getUserStorageStub();

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
    $this->renderer = new PageRenderer\ProjectAssociates(
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
    // $blah = [];
    // parse_str(self::VIEW_FORM_DATA_ALT, $blah);
    // var_export($blah);
    $this->assertNotNull($this->renderer->shortTitle());
    $this->assertNotEmpty($this->renderer->navigationItems());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testRenderList(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\ProjectAssociates::TEMPLATE;
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($html, LIBXML_PEDANTIC);
  }

  // phpcs:disable
  private const VIEW_FORM_DATA = 'export=&PME_sys_1navfmup=0&PME_sys_navnpup=-1&projectId=@PROJECT_ID@&projectName=Test2026&recordsPerPage=40&template=project-associates&table=ProjectParticipants&templateRenderer=template%3Aproject-associates&dataPrefix%5Bmusicians%5D=Musicians%3A&participationStatusFddIndex=49&instrumentsFddIndex=45&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=-1&PME_sys_cur_tab=0&PME_sys_1navfmdown=0&PME_sys_navnpdown=-1&PME_sys_mtable=ProjectParticipants&PME_sys_mkey%5Bproject_id%5D=int&PME_sys_mkey%5Bmusician_id%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=@OPERATION@%3FPME_sys_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_groupby_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_groupby_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_mrec_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_mrec_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aproject-associates&initialViewOperation=true&initialName=PME_sys_operation&initialValue=@OPERATION@%3FPME_sys_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_groupby_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_groupby_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_mrec_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_mrec_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@&reloadName=PME_sys_operation&reloadValue=@OPERATION@%3FPME_sys_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_groupby_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_groupby_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_mrec_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_mrec_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@&modalDialog=true&modified=false&PME_sys_operation=@OPERATION@%3FPME_sys_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_groupby_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_groupby_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@%26PME_sys_mrec_rec%5Bproject_id%5D%3D@PROJECT_ID@%26PME_sys_mrec_rec%5Bmusician_id%5D%3D@MUSICIAN_ID@';
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
    $substitutions = [
      '@PROJECT_ID@' => self::$projectId,
      '@MUSICIAN_ID@' => self::$musicianId,
      '@OPERATION@' => 'Anzeigen',
    ];
    parse_str(
      str_replace(array_keys($substitutions), array_values($substitutions), self::VIEW_FORM_DATA),
      $this->postData,
    );
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $result = $domDoc->loadHTML($html, LIBXML_PEDANTIC);
    $this->assertTrue($result);
    // $this->assertStringContainsString('Violine 1', $html);
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
    $substitutions = [
      '@PROJECT_ID@' => self::$projectId,
      '@MUSICIAN_ID@' => self::$musicianId,
      '@OPERATION@' => 'Löschen',
    ];
    parse_str(
      str_replace(array_keys($substitutions), array_values($substitutions), self::VIEW_FORM_DATA),
      $this->postData,
    );
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $result = $domDoc->loadHTML($html, LIBXML_PEDANTIC);
    $this->assertTrue($result);
    // $this->assertStringContainsString('Violine 1', $html);
  }

  private const RENDER_UPDATE_FORM_VALUES = [
    'projectId' => '1',
    'projectName' => 'TestProject2099',
    'recordsPerPage' => '20',
    'template' => 'project-associates',
    'table' => 'ProjectParticipants',
    'templateRenderer' => 'template:project-associates',
    'dataPrefix' => [
      'musicians' => 'Musicians:',
    ],
    'participationStatusFddIndex' => '33',
    'instrumentsFddIndex' => '29',
    'PME_sys_mtable' => 'ProjectParticipants',
    'PME_sys_mkey' => [
      'project_id' => 'int',
      'musician_id' => 'int',
    ],
    'PME_sys_cur_tab' => '0',
    'PME_sys_qfn' => '',
    'PME_sys_rec' => [
      'project_id' => '1',
      'musician_id' => '1',
    ],
    'PME_sys_groupby_rec' => [
      'project_id' => '1',
      'musician_id' => '1',
    ],
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
    'PME_data_ProjectInstruments:instrument_id' => '31',
    'PME_data_MusicianInstruments:instrument_id' => ['31', '32'],
    'PME_data_MusicianInstruments:deleted' => [''],
    'PME_data_participation_status' => 'associated',
    'PME_data_Musicians:default_participation_status' => 'regular',
    'PME_data_Musicians:deleted' => '',
    'PME_data_Musicians:cloud_account_disabled' => ['1'],
    'PME_data_ProjectParticipantFieldsDataOptions@1:label' => [
      'ReNr RE25/01354 Aktenzeichen 25-01258 Ümläüteß',
      'Voreinstellung',
    ],
    'PME_data_ProjectParticipantFieldsData@1:option_value' => [
      '12.23',
      '1234',
    ],
    'PME_data_ProjectParticipantFieldsData@1:option_key' => [
      '0c1eca68-bc3b-4d65-b80e-588ffbe0ca86',
      '027db41d-4f5b-45be-a956-c70095dab48d',
    ],
    'recurringReceivablesUpdateStrategy' => [ 1 => 'exception' ],
    'PME_data_ProjectParticipantFieldsData@1:deleted' => '',
    'PME_data_ProjectParticipantFieldsData@1:supporting_document_id' => '027db41d-4f5b-45be-a956-c70095dab48d:,0c1eca68-bc3b-4d65-b80e-588ffbe0ca86:',
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
    'PME_data_SepaBankAccounts:iban' => [
      '1-1-0:DE02700100800030876808',
    ],
    'PME_data_SepaBankAccounts:deleted' => [
      '1-1-0',
    ],
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
    $substitutions = [
      '@PROJECT_ID@' => self::$projectId,
      '@MUSICIAN_ID@' => self::$musicianId,
      '@OPERATION@' => 'Ändern',
      '@PROJECT_INSTRUMENT@' => self::$projectInstrumentId,
    ];
    parse_str(
      str_replace(array_keys($substitutions), array_values($substitutions), self::VIEW_FORM_DATA),
      $this->postData,
    );
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $formValues = $this->getFormValues($html);
    // var_export($formValues);
    $this->assertEquals(self::RENDER_UPDATE_FORM_VALUES, $formValues);
    // ok. we can then examine the data further and modify and submit it ...
    $this->participant = $this->entityManager->find(
      Entities\ProjectParticipant::class,
      ['project' => self::$projectId, 'musician' => self::$musicianId],
    );
    $this->assertNotNull($this->participant);
    $this->assertEquals(1, $this->participant->getProjectInstruments()->count());
  }

  /**
   * Test clicking the apply button whithout acutally changing anything.
   *
   * @return void
   */
  #[Attributes\Depends('testRenderUpdate')]
  public function testRenderUpdateApply(): void
  {
    $this->assertNotNull($this->entityManager->find(Entities\Project::class, self::$projectId));
    /** @var Entities\Project $project */
    $project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    // testing the test-suite
    $this->assertEquals(1, $project->getParticipants()->count());
    $this->assertNotNull($this->entityManager->find(Entities\Musician::class, self::$musicianId));
    //
    $this->postData = self::RENDER_UPDATE_FORM_VALUES;
    $this->postData[$this->phpMyEdit->cgiSysName('morechange')] = 'Apply';
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $formValues = $this->getFormValues($html);
    $this->assertEquals(self::RENDER_UPDATE_FORM_VALUES, $formValues);
    $this->participant = $this->entityManager->find(
      Entities\ProjectParticipant::class,
      ['project' => self::$projectId, 'musician' => self::$musicianId],
    );
    $this->assertNotNull($this->participant);
    $this->assertEquals(1, $this->participant->getProjectInstruments()->count());
  }

  /**
   * Add an instrument
   *
   * @return void
   */
  #[Attributes\Depends('testRenderUpdateApply')]
  public function testRenderUpdateApplyAddInstrument(): void
  {
    $this->assertNotNull($this->entityManager->find(Entities\Project::class, self::$projectId));
    /** @var Entities\Project $project */
    $project = $this->entityManager->find(Entities\Project::class, self::$projectId);
    // testing the test-suite
    $this->assertEquals(1, $project->getParticipants()->count());
    $this->assertNotNull($this->entityManager->find(Entities\Musician::class, self::$musicianId));
    //
    $this->postData = self::RENDER_UPDATE_FORM_VALUES;
    $this->assertGreaterThanOrEqual(2, count(Entities\ProjectInstrument::NON_INSTRUMENTS));
    $newInstrumentId = $this->nonInstrumentIds[Entities\ProjectInstrument::NON_INSTRUMENTS[1]];
    $this->postData['PME_data_ProjectInstruments:instrument_id'] = [$newInstrumentId];
    $this->postData[$this->phpMyEdit->cgiSysName('morechange')] = 'Apply';
    ob_start();
    try {
      $this->renderer->render(execute: true);
      $html = ob_get_contents();
    } catch (Throwable $t) {
      throw new Exception($t->getMessage(), previous: $t);
    } finally {
      ob_end_clean();
    }
    $formValues = $this->getFormValues($html);
    $expectedFormValues = Util::arrayMergeRecursive(
      self::RENDER_UPDATE_FORM_VALUES,
      [
        // only one associate role, not many.
        'PME_data_ProjectInstruments:instrument_id' => $newInstrumentId,
      ],
    );
    $this->assertEquals($expectedFormValues, $formValues);
    $this->participant = $this->entityManager->find(
      Entities\ProjectParticipant::class,
      ['project' => self::$projectId, 'musician' => self::$musicianId],
    );
    $this->assertNotNull($this->participant);
    $this->assertEquals(1, $this->participant->getProjectInstruments()->count());
  }

  /**
   * Remove the instrument again.
   *
   * @return void
   */
  #[Attributes\Depends('testRenderUpdateApplyAddInstrument')]
  public function testRenderUpdateApplyRemoveInstrument(): void
  {
    $this->testRenderUpdateApply();
  }

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testRenderDelete')]
  #[Attributes\Depends('testRenderList')]
  #[Attributes\Depends('testRenderUpdate')]
  #[Attributes\Depends('testRenderUpdateApply')]
  #[Attributes\Depends('testRenderUpdateApplyAddInstrument')]
  #[Attributes\Depends('testRenderUpdateApplyAddVoice')]
  #[Attributes\Depends('testRenderUpdateApplyRemoveInstrument')]
  #[Attributes\Depends('testRenderView')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }
}
