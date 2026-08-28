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

use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the AllMusicians page renderer. */
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\Instruments::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\Projects::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Util\Navigation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MailingListsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UnusedTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class InstrumentsTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

  private PageRenderer\Instruments $renderer;

  private PHPMyEdit $phpMyEdit;

  private IRequest $request;

  private array $postData = [];

  private static bool $migrationsApplied = false;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      self::$migrationsApplied = true;
    }

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    $appContainer = $this->mockProvider->getAppContainer();

    $this->request = $this->mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      }
    );

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    // what a mess ...
    $this->renderer = new PageRenderer\Instruments(
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      request: $this->request,
      phpMyEdit: $this->phpMyEdit,
      pageNavigation: $appContainer->get(PageRenderer\Util\Navigation::class),
      toolTipsService: $appContainer->get(ToolTipsService::class),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** {@inheritdoc} */
  public function testApplyMigrations(): void
  {
    $this->assertNotNull($this->renderer->shortTitle());
    $this->assertNotEmpty($this->renderer->navigationItems());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testAdd')]
  #[Attributes\Depends('testApplyMigrations')]
  #[Attributes\Depends('testChange')]
  #[Attributes\Depends('testDelete')]
  #[Attributes\Depends('testRenderChange')]
  #[Attributes\Depends('testRenderDelete')]
  #[Attributes\Depends('testRenderList')]
  #[Attributes\Depends('testRenderView')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  #[Attributes\Depends('testChange')]
  public function testRenderList(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\Instruments::TEMPLATE;
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
  private const ADD_INSTRUMENT_FORM_DATA = 'template=instruments&musicianId=0&projectId=0&projectName=&recordsPerPage=40&table=Instruments&templateRenderer=template%3Ainstruments&PME_sys_qf8_comp=%3D&PME_sys_qf10_comp=%3D&PME_sys_qf11_comp=%3D&PME_sys_cur_tab=&PME_sys_qfn=&PME_sys_rec=&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=0&PME_sys_op_name=add&PME_data_name=TestInstrument&PME_data_sort_order=17&PME_data_InstrumentFamilies%3Aid%5B%5D=4&PME_data_InstrumentFamilies%3Aid%5B%5D=6&PME_data_InstrumentFamilies%3Aid%5B%5D=5&PME_data_deleted=&PME_data_usage=&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Ainstruments&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neuer+Datensatz&reloadName=PME_sys_operation&reloadValue=Neuer+Datensatz&modalDialog=true&modified=true&PME_sys_applyadd=Anwenden&PME_sys_applyadd=Anwenden';
  // phpcs:enable

  private const ADD_INSTRUMENT_NEWVALS = [
    'TableFieldTranslations__master_key_' => '',
    'instrument_instrument_family__master_key_' => '',
    'InstrumentFamilies__master_key_' => '',
    'MusicianInstruments__master_key_' => '',
    'ProjectInstruments__master_key_' => '',
    'ProjectInstrumentationNumbers__master_key_' => '',
    'name' => 'TestInstrument',
    'sort_order' => 17,
    'InstrumentFamilies:id' => '4,6,5',
  ];

   /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testAdd(): void
  {
    $this->assertTrue(self::$migrationsApplied);

    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    $oldInstruments = $repository->findAll();

    $oldValues = [];
    $newValues = self::ADD_INSTRUMENT_NEWVALS;
    $changed = array_keys(self::ADD_INSTRUMENT_NEWVALS);

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Instruments::TABLE;
    // the code needs an active transaction, so supply one, no need to catch
    // exceptions.
    $this->entityManager->beginTransaction();
    try {
      $this->renderer->beforeInsertDoInsertAll(
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

    $newInstruments = $repository->findAll();

    $this->assertEquals(count($oldInstruments) + 1, count($newInstruments));
    $newInstrument = $repository->findOneBy(['name' => self::ADD_INSTRUMENT_NEWVALS['name']]);
    $this->assertInstanceOf(Entities\Instrument::class, $newInstrument);
    $this->assertEquals(self::ADD_INSTRUMENT_NEWVALS['sort_order'], $newInstrument->getSortOrder());
    $familyIds = array_values($newInstrument->getFamilies()->map(fn(Entities\InstrumentFamily $family) => $family->getId())->toArray());
    $this->assertEqualsCanonicalizing(explode(PageRenderer\DataConstants::VALUES_SEP, self::ADD_INSTRUMENT_NEWVALS['InstrumentFamilies:id']), $familyIds);
    $this->assertEquals(0, $newInstrument->usage());
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const CHANGE_INSTRUMENT_FORM_DATA = 'template=instruments&musicianId=0&projectId=0&projectName=&recordsPerPage=40&table=Instruments&templateRenderer=template%3Ainstruments&PME_sys_qf8_comp=%3D&PME_sys_qf10_comp=%3D&PME_sys_qf11_comp=%3D&PME_sys_cur_tab=&PME_sys_qfn=&PME_sys_rec%5Bid%5D=48&PME_sys_groupby_rec%5Bid%5D=48&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=0&PME_sys_op_name=change&PME_data_id=48&PME_data_name=TestInstrumentChanged&PME_data_sort_order=18&PME_data_InstrumentFamilies%3Aid%5B%5D=4&PME_data_InstrumentFamilies%3Aid%5B%5D=6&PME_data_InstrumentFamilies%3Aid%5B%5D=7&PME_data_deleted=&PME_data_usage=0&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Ainstruments&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Change%3FPME_sys_rec%3D%257B%2522id%2522%253A%252248%2522%257D%26PME_sys_groupby_rec%3D%257B%2522id%2522%253A%252248%2522%257D&reloadName=PME_sys_operation&reloadValue=Change%3FPME_sys_rec%3D%257B%2522id%2522%253A%252248%2522%257D%26PME_sys_groupby_rec%3D%257B%2522id%2522%253A%252248%2522%257D&modalDialog=true&modified=true&PME_sys_morechange=Anwenden&PME_sys_morechange=Anwenden';
  // phpcs:enable

  private const CHANGE_INSTRUMENT_DATA = [
    'newValues' => [
      'id' => '48',
      'TableFieldTranslations__master_key_' => '@',
      'instrument_instrument_family__master_key_' => '@',
      'InstrumentFamilies__master_key_' => '@',
      'MusicianInstruments__master_key_' => '@',
      'ProjectInstruments__master_key_' => '@',
      'ProjectInstrumentationNumbers__master_key_' => '@',
      'name' => 'TestInstrumentChanged',
      'sort_order' => '18',
      'InstrumentFamilies:id' => '4,6,7',
      'deleted' => '',
    ],
    'oldValues' => [
      'id' => '48',
      'TableFieldTranslations__master_key_' => 'TestInstrument',
      'instrument_instrument_family__master_key_' => '48',
      'InstrumentFamilies__master_key_' => '4,5,6',
      'MusicianInstruments__master_key_' => '@',
      'ProjectInstruments__master_key_' => '@',
      'ProjectInstrumentationNumbers__master_key_' => '@',
      'name' => 'TestInstrument',
      'sort_order' => '17',
      'InstrumentFamilies:id' => '4,6,5',
      'deleted' => ''
    ],
    'changed' => [
        'TableFieldTranslations__master_key_',
        'instrument_instrument_family__master_key_',
        'InstrumentFamilies__master_key_',
        'name',
        'sort_order',
        'InstrumentFamilies:id',
    ],
  ];

  /** {@inheritdoc} */
  #[Attributes\Depends('testAdd')]
  public function testChange(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    /** @var Entities\Instrument $newInstrument */
    $newInstrument = $repository->findOneBy(['name' => self::ADD_INSTRUMENT_NEWVALS['name']]);

    $oldValues = self::CHANGE_INSTRUMENT_DATA['oldValues'];
    $newValues = self::CHANGE_INSTRUMENT_DATA['newValues'];
    $changed = self::CHANGE_INSTRUMENT_DATA['changed'];

    $oldValues['id'] =
      $newValues['id'] = $newInstrument->getId();

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Instruments::TABLE;

    // the code needs an active transaction, so supply one, no need to catch
    // exceptions.
    $this->entityManager->beginTransaction();
    try {
      $this->renderer->beforeUpdateDoUpdateAll(
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

    $this->assertInstanceOf(Entities\Instrument::class, $newInstrument);
    $this->assertEquals(self::CHANGE_INSTRUMENT_DATA['newValues']['sort_order'], $newInstrument->getSortOrder());
    $familyIds = array_values($newInstrument->getFamilies()->map(fn(Entities\InstrumentFamily $family) => $family->getId())->toArray());
    $this->assertEqualsCanonicalizing(explode(PageRenderer\DataConstants::VALUES_SEP, self::CHANGE_INSTRUMENT_DATA['newValues']['InstrumentFamilies:id']), $familyIds);
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const DELETE_INSTRUMENT_FORM_DATA = 'template=instruments&musicianId=0&projectId=0&projectName=&recordsPerPage=40&table=Instruments&templateRenderer=template%3Ainstruments&PME_sys_qf8_comp=%3D&PME_sys_qf10_comp=%3D&PME_sys_qf11_comp=%3D&PME_sys_cur_tab=&PME_sys_qfn=&PME_sys_rec%5Bid%5D=48&PME_sys_groupby_rec%5Bid%5D=48&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=0&PME_sys_op_name=delete&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Ainstruments&initialViewOperation=false&initialName=PME_sys_operation&initialValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D48%26PME_sys_groupby_rec%5Bid%5D%3D48%26PME_sys_mrec_rec%5Bid%5D%3D48&reloadName=PME_sys_operation&reloadValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D48%26PME_sys_groupby_rec%5Bid%5D%3D48%26PME_sys_mrec_rec%5Bid%5D%3D48&modalDialog=true&modified=true&PME_sys_savedelete=L%C3%B6schen&PME_sys_savedelete=L%C3%B6schen&PME_sys_operation=Null';
  // phpcs:enable

  #[Attributes\Depends('testRenderAdd')]
  #[Attributes\Depends('testRenderChange')]
  #[Attributes\Depends('testRenderView')]
  #[Attributes\Depends('testRenderDelete')]
  /** {@inheritdoc} */
  #[Attributes\Depends('testRenderList')]
  public function testDelete(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    /** @var Entities\Instrument $newInstrument */
    $newInstrument = $repository->findOneBy(['name' => self::CHANGE_INSTRUMENT_DATA['newValues']['name']]);
    $newInstrumentId = $newInstrument->getId();
    $this->assertTrue($newInstrumentId > 0);

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\Instruments::TABLE;

    parse_str(self::DELETE_INSTRUMENT_FORM_DATA, $this->postData);
    $this->postData[$this->phpMyEdit->cgiSysName('rec')]['id'] =
      $this->postData[$this->phpMyEdit->cgiSysName('groupby_rec')]['id'] = $newInstrument->getId();
    [ 'rec' => $this->phpMyEdit->rec ] = $this->phpMyEdit->recordIdFromRequest();

    $oldValues = $newValues = []; // should not matter
    $changed = ['something'];
    $this->entityManager->beginTransaction();
    try {
      $this->renderer->beforeDeleteSimplyDoDelete(
        pme: $this->phpMyEdit,
        op: 'do not care',
        step: 'do not care',
        oldValues: $oldValues,
        changed: $changed,
        newValues: $newValues,
      );
      $this->entityManager->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollBack();
      }
      throw $t;
    }
    $this->assertEmpty($changed);

    $this->entityManager->clear();
    $newInstrument = $repository->find(['id' => $newInstrumentId]);
    $this->assertEquals(null, $newInstrument);
  }

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const INDIVIDUAL_ENTITY_FORM_DATA = 'PME_sys_navfmup=0&PME_sys_navnpup=40&template=instruments&musicianId=&projectId=&projectName=&recordsPerPage=40&table=Instruments&templateRenderer=template%3Ainstruments&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=&PME_sys_qf7=&PME_sys_qf8_comp=%3D&PME_sys_qf8=&PME_sys_qf10_comp=%3D&PME_sys_qf10=&PME_sys_qf11_comp=%3D&PME_sys_qf11=&PME_sys_qf12=&PME_sys_navfmdown=0&PME_sys_navnpdown=40&PME_sys_reloadOuterForm=&PME_sys_operation=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Ainstruments&initialViewOperation=false&initialName=PME_sys_operation&initialValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&reloadName=PME_sys_operation&reloadValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@&modalDialog=true&modified=false&PME_sys_operation=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_groupby_rec%5Bid%5D%3D@ENTITY_ID@%26PME_sys_mrec_rec%5Bid%5D%3D@ENTITY_ID@';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testChange')]
  public function testRenderView(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    /** @var Entities\Instrument $newInstrument */
    $newInstrument = $repository->findOneBy(['name' => self::CHANGE_INSTRUMENT_DATA['newValues']['name']]);
    $newInstrumentId = $newInstrument->getId();
    $postString = str_replace('@ENTITY_ID@', $newInstrumentId, self::INDIVIDUAL_ENTITY_FORM_DATA);
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

  /** {@inheritdoc} */
  #[Attributes\Depends('testChange')]
  public function testRenderDelete(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    /** @var Entities\Instrument $newInstrument */
    $newInstrument = $repository->findOneBy(['name' => self::CHANGE_INSTRUMENT_DATA['newValues']['name']]);
    $newInstrumentId = $newInstrument->getId();
    $postString = str_replace('@ENTITY_ID@', $newInstrumentId, self::INDIVIDUAL_ENTITY_FORM_DATA);
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

  /** {@inheritdoc} */
  #[Attributes\Depends('testChange')]
  public function testRenderChange(): void
  {
    $repository = $this->entityManager->getRepository(Entities\Instrument::class);
    /** @var Entities\Instrument $newInstrument */
    $newInstrument = $repository->findOneBy(['name' => self::CHANGE_INSTRUMENT_DATA['newValues']['name']]);
    $newInstrumentId = $newInstrument->getId();
    $postString = str_replace('@ENTITY_ID@', $newInstrumentId, self::INDIVIDUAL_ENTITY_FORM_DATA);
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

  // phpcs:disable Generic.Files.LineLength.TooLong
  private const RENDER_ADD_INSTRUMENT_FORM_DATA = 'PME_sys_navfmup=0&PME_sys_navnpup=40&template=instruments&musicianId=&projectId=&projectName=&recordsPerPage=40&table=Instruments&templateRenderer=template%3Ainstruments&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=&PME_sys_qf7=&PME_sys_qf8_comp=%3D&PME_sys_qf8=&PME_sys_qf10_comp=%3D&PME_sys_qf10=&PME_sys_qf11_comp=%3D&PME_sys_qf11=&PME_sys_qf12=&PME_sys_navfmdown=0&PME_sys_navnpdown=40&PME_sys_reloadOuterForm=&PME_sys_operation=Neuer+Datensatz&PME_sys_cur_tab=all&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Ainstruments&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neuer+Datensatz&reloadName=PME_sys_operation&reloadValue=Neuer+Datensatz&modalDialog=true&modified=false&PME_sys_operation=Neuer+Datensatz';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testChange')]
  public function testRenderAdd(): void
  {
    $postString = self::RENDER_ADD_INSTRUMENT_FORM_DATA;
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
