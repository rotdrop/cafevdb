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
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the AllMusicians page renderer. */
#[Attributes\CoversClass(PHPMyEdit::class)]
#[Attributes\CoversClass(PageRenderer\AllMusicians::class)]
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\Musicians::class)]
#[Attributes\CoversClass(PageRenderer\PMETableViewBase::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\Projects::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\CoversClass(PageRenderer\Util\Navigation::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\FinanceModeNavigationItemTrait::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\ProjectModeNavigationItemTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianInstrumentEntityListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class AllMusiciansTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;

  private PageRenderer\AllMusicians $renderer;

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

    $this->request = $this->mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      }
    );

    $this->entityManager = $this->entityManager ?? $this->mockProvider->getEntityManager();

    $appContainer = $this->mockProvider->getAppContainer();

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    // what a mess ...
    $this->renderer = new PageRenderer\AllMusicians(
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      request: $this->request,
      phpMyEdit: $this->phpMyEdit,
      pageNavigation: $appContainer->get(PageRenderer\Util\Navigation::class),
      toolTipsService: $appContainer->get(ToolTipsService::class),
      //
      contactsService: $appContainer->get(ContactsService::class),
      geoCodingService: $appContainer->get(GeoCodingService::class),
      insuranceService: $appContainer->get(InstrumentInsuranceService::class),
      listsService: $appContainer->get(MailingListsService::class),
      musicianService: $appContainer->get(MusicianService::class),
      phoneNumberService: $appContainer->get(PhoneNumberService::class),
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
  #[Attributes\Depends('testDelete')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  // phpcs:disable
  private const ADD_MUSICIAN_SUBMIT_FORM_DATA = 'template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=&projectId=0&projectName=&recordsPerPage=40&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_qf14=max&PME_sys_qf18_comp=%3D&PME_sys_qf38_comp=%3D&PME_sys_qf46_comp=%3D&PME_sys_qf51_comp=%3D&PME_sys_qf52_comp=%3D&PME_sys_qf14=max&PME_sys_qf28=&PME_sys_cur_tab=all&PME_sys_qfn=%26PME_sys_qf14%3Dmax%26PME_sys_qf28%3D&PME_sys_rec=&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=1&PME_sys_op_name=add&PME_data_id=0&PME_data_organization=&PME_data_job_title=&PME_data_sur_name=Musterperson&PME_data_first_name=Max+Maria&PME_data_nick_name=Max&PME_data_display_name=&PME_data_display_name_personal=&PME_data_gender=&PME_data_user_id_slug=&PME_data_deleted=&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=6&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=3&PME_data_Instruments%3Asort_order=&PME_data_default_participation_status=&PME_data_cloud_account_disabled%5B%5D=1&PME_data_mobile_phone=&PME_data_fixed_line_phone=&PME_data_MusicianEmailAddresses%40all%3Aaddress%5B%5D=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_email=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_mailing_list=invite&PME_data_address_supplement=&PME_data_street=&PME_data_street_number=&PME_data_po_box=&PME_data_postal_code=&PME_data_city=&PME_data_country=DE&PME_data_birthday=&PME_data_remarks=&PME_data_language=&PME_data_SepaDebitMandates%3Amandate_reference=&PME_data_SepaDebitMandates%3Adeleted=&PME_data_SepaBankAccounts%3Aiban=&PME_data_SepaBankAccounts%3Adeleted=&PME_data_address_book_uri=&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neue+Person&reloadName=PME_sys_operation&reloadValue=Neue+Person&modalDialog=true&modified=true&PME_sys_applyadd=Anwenden&PME_sys_applyadd=Anwenden';
  // phpcs:enable

  private const BEFORE_INSERT_DO_INSERT_ALL_DATA = [
    'id' => null,
    'MusicianInstruments__master_key_' => '',
    'Instruments__master_key_' => '',
    'InstrumentInsurances__master_key_' => '',
    'ProjectParticipants@allProjects__master_key_' => '',
    'MusicianEmailAddresses__master_key_' => '',
    'MusicianEmailAddresses@all__master_key_' => '',
    'SepaBankAccounts__master_key_' => '',
    'SepaDebitMandates__master_key_' => '',
    'sur_name' => 'Musterperson',
    'first_name' => 'Max Maria',
    'nick_name' => 'Max',
    'MusicianInstruments:instrument_id' => '6,3',
    'MusicianInstruments:deleted' => '',
    'cloud_account_deactivated' => '',
    'cloud_account_disabled' => '1 ',
    'MusicianEmailAddresses@all:address' => 'max-maria.musterperson@non-existing.tld',
    'email' => 'max-maria.musterperson@non-existing.tld',
    'mailing_list' => 'invite',
    'country' => 'DE',
    'MusicianInstruments:ranking' => '6:1,3:2',
  ];

  // phpcs:disable
  private const UPDATE_MUSICIAN_FORM_DATA = 'template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=1200&projectId=0&projectName=&recordsPerPage=40&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_qf14=max&PME_sys_qf18_comp=%3D&PME_sys_qf38_comp=%3D&PME_sys_qf46_comp=%3D&PME_sys_qf51_comp=%3D&PME_sys_qf52_comp=%3D&PME_sys_qf14=max&PME_sys_cur_tab=all&PME_sys_qfn=%26PME_sys_qf14%3Dmax&PME_sys_rec%5Bid%5D=1200&PME_sys_groupby_rec%5Bid%5D=1200&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=1&PME_sys_op_name=change&PME_data_id=1200&PME_data_organization=&PME_data_job_title=&PME_data_sur_name=Musterperson&PME_data_first_name=Max+Maria&PME_data_nick_name=Maria&PME_data_display_name=&PME_data_display_name_personal=Max+Musterperson&PME_data_gender=&PME_data_user_id_slug=max.musterperson.2&PME_data_deleted=&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=6&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=3&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=10&PME_data_Instruments%3Asort_order%5B0%5D=5&PME_data_Instruments%3Asort_order%5B1%5D=10&PME_data_MusicianInstruments%3Adeleted%5B%5D=&PME_data_default_participation_status=regular&PME_data_cloud_account_disabled%5B%5D=1&PME_data_mobile_phone=&PME_data_fixed_line_phone=&PME_data_MusicianEmailAddresses%40all%3Aaddress%5B%5D=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_email=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_address_supplement=&PME_data_street=&PME_data_street_number=&PME_data_po_box=&PME_data_postal_code=&PME_data_city=&PME_data_country=DE&PME_data_birthday=&PME_data_remarks=&PME_data_language=&show-deleted=show&PME_data_address_book_uri=&PME_data_uuid=e8ab7bba-ec9c-11f0-a659-250679a0522d&PME_data_updated=09.01.2026%2C+09%3A54%3A09&PME_data_created=08.01.2026%2C+15%3A18%3A31&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Change%3FPME_sys_rec%3D%257B%2522id%2522%253A%25221200%2522%257D%26PME_sys_groupby_rec%3D%257B%2522id%2522%253A%25221200%2522%257D&reloadName=PME_sys_morechange&reloadValue=Anwenden&modalDialog=true&modified=true&PME_sys_morechange=Anwenden';
  // phpcs:enable

  private const BEFORE_UPDATE_DO_UPDATE_ALL_DATA = [
    'oldValues' => [
      'id' => '1200',
      'MusicianInstruments__master_key_' => '3,6',
      'Instruments__master_key_' => '3,6',
      'InstrumentInsurances__master_key_' => '',
      'ProjectParticipants@allProjects__master_key_' => '',
      'MusicianEmailAddresses__master_key_' => 'max-maria.musterperson@non-existing.tld',
      'MusicianEmailAddresses@all__master_key_' => 'max-maria.musterperson@non-existing.tld',
      'SepaBankAccounts__master_key_' => '',
      'SepaDebitMandates__master_key_' => '',
      'organization' => '',
      'job_title' => '',
      'sur_name' => 'Musterperson',
      'first_name' => 'Max Maria',
      'nick_name' => 'Max',
      'display_name' => '',
      'display_name_personal' => 'Max Musterperson',
      'gender' => '',
      'user_id_slug' => 'max.musterperson.2',
      'deleted' => '',
      'MusicianInstruments:instrument_id' => '6,3',
      'MusicianInstruments:deleted' => '',
      'default_participation_status' => 'regular',
      'cloud_account_deactivated' => '',
      'cloud_account_disabled' => '1',
      'mobile_phone' => '',
      'fixed_line_phone' => '',
      'MusicianEmailAddresses@all:address' => 'max-maria.musterperson@non-existing.tld',
      'email' => 'max-maria.musterperson@non-existing.tld',
      'address_supplement' => '',
      'street' => '',
      'street_number' => '',
      'po_box' => '',
      'postal_code' => '',
      'city' => '',
      'country' => 'DE',
      'birthday' => '',
      'remarks' => '',
      'language' => '',
      'SepaDebitMandates:mandate_reference' => '',
      'SepaDebitMandates:deleted' => '',
      'SepaBankAccounts:iban' => '',
      'SepaBankAccounts:deleted' => '',
      'address_book_uri' => '',
      'uuid' => 'e8ab7bba-ec9c-11f0-a659-250679a0522d',
      'updated' => '2026-01-09 08:54:09',
      'created' => '2026-01-08 14:18:31',
      'MusicianInstruments:ranking' => '6:1,3:2',
    ],

    'newValues' => [
      'id' => '1200',
      'MusicianInstruments__master_key_' => '',
      'Instruments__master_key_' => '',
      'InstrumentInsurances__master_key_' => '',
      'ProjectParticipants@allProjects__master_key_' => '',
      'MusicianEmailAddresses__master_key_' => '',
      'MusicianEmailAddresses@all__master_key_' => '',
      'SepaBankAccounts__master_key_' => '',
      'SepaDebitMandates__master_key_' => '',
      'organization' => '',
      'job_title' => '',
      'sur_name' => 'Musterperson',
      'first_name' => 'Max Maria',
      'nick_name' => 'Maria',
      'display_name' => '',
      'display_name_personal' => 'Max Musterperson',
      'gender' => '',
      'user_id_slug' => 'max.musterperson',
      'deleted' => '',
      'MusicianInstruments:instrument_id' => '6,3,10',
      'MusicianInstruments:deleted' => '',
      'default_participation_status' => 'regular',
      'cloud_account_deactivated' => '',
      'cloud_account_disabled' => '1',
      'mobile_phone' => '',
      'fixed_line_phone' => '',
      'MusicianEmailAddresses@all:address' => 'max-maria.musterperson@non-existing.tld',
      'email' => 'max-maria.musterperson@non-existing.tld',
      'address_supplement' => '',
      'street' => '',
      'street_number' => '',
      'po_box' => '',
      'postal_code' => '',
      'city' => '',
      'country' => 'DE',
      'birthday' => '',
      'remarks' => '',
      'language' => '',
      'SepaDebitMandates:mandate_reference' => '',
      'SepaDebitMandates:deleted' => '',
      'SepaBankAccounts:iban' => '',
      'SepaBankAccounts:deleted' => '',
      'address_book_uri' => '',
      'uuid' => 'e8ab7bba-ec9c-11f0-a659-250679a0522d',
      'updated' => '2026-01-09 08:54:09',
      'created' => '2026-01-08 14:18:31',
      'MusicianInstruments:ranking' => '6:1,3:2,10:3',
    ],

    'changed' => [
      'MusicianInstruments__master_key_',
      'Instruments__master_key_',
      'MusicianEmailAddresses__master_key_',
      'MusicianEmailAddresses@all__master_key_',
      'nick_name',
      'MusicianInstruments:instrument_id',
      'MusicianInstruments:ranking',
    ],
  ];

  // phpcs:disable
  private const DELETE_MUSICIAN_FORM_DATA = 'template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=1184&projectId=0&projectName=&recordsPerPage=30&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_qf14=max&PME_sys_qf18_comp=%3D&PME_sys_qf38_comp=%3D&PME_sys_qf46_comp=%3D&PME_sys_qf51_comp=%3D&PME_sys_qf52_comp=%3D&PME_sys_qf14=max&PME_sys_qf28=&PME_sys_cur_tab=all&PME_sys_qfn=%26PME_sys_qf14%3Dmax%26PME_sys_qf28%3D&PME_sys_rec%5Bid%5D=1184&PME_sys_groupby_rec%5Bid%5D=1184&PME_sys_fm=0&PME_sys_np=30&PME_sys_fl=1&PME_sys_op_name=delete&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=false&initialName=PME_sys_operation&initialValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D1184%26PME_sys_groupby_rec%5Bid%5D%3D1184%26PME_sys_mrec_rec%5Bid%5D%3D1184&reloadName=PME_sys_operation&reloadValue=L%C3%B6schen%3FPME_sys_rec%5Bid%5D%3D1184%26PME_sys_groupby_rec%5Bid%5D%3D1184%26PME_sys_mrec_rec%5Bid%5D%3D1184&modalDialog=true&modified=true&PME_sys_savedelete=L%C3%B6schen&PME_sys_savedelete=L%C3%B6schen&PME_sys_operation=Null';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testApplyMigrations')]
  public function testInsert(): void
  {
    $this->assertTrue(self::$migrationsApplied);

    $oldValues = [];
    $newValues = self::BEFORE_INSERT_DO_INSERT_ALL_DATA;
    $changed = array_keys(self::BEFORE_INSERT_DO_INSERT_ALL_DATA);

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\AllMusicians::TABLE;
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

    $this->assertEquals(1, count($this->entityManager->getRepository(Entities\Musician::class)->findAll()));

    /** @var Entities\Musician $musterPerson */
    $musterPerson = $this->entityManager->getRepository(Entities\Musician::class)->findOneBy(['surName' => 'Musterperson']);
    $this->assertInstanceOf(Entities\Musician::class, $musterPerson);
    $this->assertEquals(
      count(explode(PageRenderer\DataConstants::VALUES_SEP, self::BEFORE_INSERT_DO_INSERT_ALL_DATA['MusicianInstruments:instrument_id'])),
      $musterPerson->getInstruments()->count(),
    );
    $this->assertEquals($newValues['nick_name'], $musterPerson->getNickName());
    $this->assertEquals(1, $musterPerson->getEmailAddresses()->count());
    $this->assertEquals(self::BEFORE_INSERT_DO_INSERT_ALL_DATA['email'], $musterPerson->getEmail());
    $this->assertEquals($musterPerson->getEmail(), $musterPerson->getEmailAddresses()->first()->getAddress());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testInsert')]
  public function testUpdate(): void
  {
    /** @var Entities\Musician $musterPerson */
    $musterPerson = $this->entityManager->getRepository(Entities\Musician::class)->findOneBy(['surName' => 'Musterperson']);

    $oldValues = self::BEFORE_UPDATE_DO_UPDATE_ALL_DATA['oldValues'];
    $newValues = self::BEFORE_UPDATE_DO_UPDATE_ALL_DATA['newValues'];
    $changed = self::BEFORE_UPDATE_DO_UPDATE_ALL_DATA['changed'];

    $oldValues['id'] =
      $newValues['id'] = $musterPerson->getId();
    $oldValues['user_id_slug'] =
      $newValues['user_id_slug'] = $musterPerson->getUserIdSlug();
    $oldValues['updated'] =
      $newValues['updated'] = $musterPerson->getUpdated()->format('Y-m-d H:i:s');
    $oldValues['created'] =
      $newValues['created'] = $musterPerson->getUpdated()->format('Y-m-d H:i:s');
    $oldValues['uuid'] =
      $newValues['uuid'] = (string)$musterPerson->getUuid();

    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\AllMusicians::TABLE;

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

    $this->assertEquals(1, count($this->entityManager->getRepository(Entities\Musician::class)->findAll()));

    // The legacy code uses the ORM, so in principle a refresh should not be necessary ...
    // $this->entityManager->refresh($musterPerson);
    $this->assertEquals(
      count(explode(PageRenderer\DataConstants::VALUES_SEP, self::BEFORE_UPDATE_DO_UPDATE_ALL_DATA['newValues']['MusicianInstruments:instrument_id'])),
      $musterPerson->getInstruments()->count(),
    );
    $this->assertEquals($newValues['nick_name'], $musterPerson->getNickName());
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testRenderList')]
  #[Attributes\Depends('testRenderAdd')]
  #[Attributes\Depends('testRenderChange')]
  #[Attributes\Depends('testRenderView')]
  #[Attributes\Depends('testRenderDelete')]
  public function testDelete(): void
  {
    /** @var Entities\Musician $musterPerson */
    $musterPerson = $this->entityManager->getRepository(Entities\Musician::class)->findOneBy(['surName' => 'Musterperson']);
    $this->renderer->render(execute: false);
    $this->phpMyEdit->tb = PageRenderer\AllMusicians::TABLE;

    parse_str(self::DELETE_MUSICIAN_FORM_DATA, $this->postData);
    $this->postData['musicianId'] = $musterPerson->getId();
    $this->postData[$this->phpMyEdit->cgiSysName('rec')]['id'] =
      $this->postData[$this->phpMyEdit->cgiSysName('groupby_rec')]['id'] = $musterPerson->getId();
    [ 'rec' => $this->phpMyEdit->rec ] = $this->phpMyEdit->recordIdFromRequest();

    // $this->entityManager->clear();
    // $musterPerson = $this->entityManager->getRepository(Entities\Musician::class)->findOneBy(['surName' => 'Musterperson']);
    $this->entityManager->refresh($musterPerson);

    $oldValues = $newValues = []; // should not matter
    $changed = ['something'];
    $this->entityManager->beginTransaction();
    try {
      $this->renderer->beforeDeleteTrigger(
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

    $musterPerson = $this->entityManager->getRepository(Entities\Musician::class)->findOneBy(['surName' => 'Musterperson']);
    $this->assertEquals(null, $musterPerson);
  }

  /** {@inheritdoc} */
  #[Attributes\Depends('testUpdate')]
  public function testRenderList(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\AllMusicians::TEMPLATE;
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
  private const ADD_MUSICIAN_FORM_DATA = 'export=&PME_sys_navfmup=0&PME_sys_navnpup=40&template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=&projectId=&projectName=&recordsPerPage=40&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=0&PME_sys_qf9=&PME_sys_qf10=&PME_sys_qf14=&PME_sys_qf18_comp=%3D&PME_sys_qf18=&PME_sys_qf26=&PME_sys_qf27=&PME_sys_qf28=&PME_sys_qf29=&PME_sys_qf31=&PME_sys_qf32=&PME_sys_qf33=&PME_sys_qf34=&PME_sys_qf35=&PME_sys_qf36=&PME_sys_qf38_comp=%3D&PME_sys_qf38=&PME_sys_qf39=&PME_sys_qf46_comp=%3D&PME_sys_qf46=&PME_sys_qf47=&PME_sys_qf48=&PME_sys_qf50=&PME_sys_qf51_comp=%3D&PME_sys_qf51=&PME_sys_qf52_comp=%3D&PME_sys_qf52=&export=&PME_sys_navfmdown=0&PME_sys_navnpdown=40&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=Neue+Person&PME_sys_cur_tab=all&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neue+Person&reloadName=PME_sys_operation&reloadValue=Neue+Person&modalDialog=true&modified=false&PME_sys_operation=Neue+Person';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testUpdate')]
  public function testRenderAdd(): void
  {
    $this->postData[PersistentCGIKeys::TEMPLATE] = PageRenderer\AllMusicians::TEMPLATE;
    parse_str(self::ADD_MUSICIAN_FORM_DATA, $this->postData);
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
  private const VIEW_MUSICIAN_FORM_DATA = 'export=&PME_sys_navfmup=0&PME_sys_navnpup=40&template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=&projectId=&projectName=&recordsPerPage=40&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_fl=0&PME_sys_qfn=&PME_sys_fm=0&PME_sys_np=40&PME_sys_cur_tab=0&PME_sys_qf9=&PME_sys_qf10=&PME_sys_qf14=&PME_sys_qf18_comp=%3D&PME_sys_qf18=&PME_sys_qf26=&PME_sys_qf27=&PME_sys_qf28=&PME_sys_qf29=&PME_sys_qf31=&PME_sys_qf32=&PME_sys_qf33=&PME_sys_qf34=&PME_sys_qf35=&PME_sys_qf36=&PME_sys_qf38_comp=%3D&PME_sys_qf38=&PME_sys_qf39=&PME_sys_qf46_comp=%3D&PME_sys_qf46=&PME_sys_qf47=&PME_sys_qf48=&PME_sys_qf50=&PME_sys_qf51_comp=%3D&PME_sys_qf51=&PME_sys_qf52_comp=%3D&PME_sys_qf52=&export=&PME_sys_navfmdown=0&PME_sys_navnpdown=40&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_reloadOuterForm=&PME_sys_operation=Anzeigen%3FPME_sys_rec%5Bid%5D%3D1%26PME_sys_groupby_rec%5Bid%5D%3D1%26PME_sys_mrec_rec%5Bid%5D%3D1&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=true&initialName=PME_sys_operation&initialValue=Anzeigen%3FPME_sys_rec%5Bid%5D%3D1%26PME_sys_groupby_rec%5Bid%5D%3D1%26PME_sys_mrec_rec%5Bid%5D%3D1&reloadName=PME_sys_operation&reloadValue=Anzeigen%3FPME_sys_rec%5Bid%5D%3D1%26PME_sys_groupby_rec%5Bid%5D%3D1%26PME_sys_mrec_rec%5Bid%5D%3D1&modalDialog=true&modified=false&PME_sys_operation=Anzeigen%3FPME_sys_rec%5Bid%5D%3D1%26PME_sys_groupby_rec%5Bid%5D%3D1%26PME_sys_mrec_rec%5Bid%5D%3D1';
  // phpcs:enable

  /** {@inheritdoc} */
  #[Attributes\Depends('testUpdate')]
  public function testRenderView(): void
  {
    parse_str(self::VIEW_MUSICIAN_FORM_DATA, $this->postData);
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
  #[Attributes\Depends('testRenderView')]
  public function testRenderDelete(): void
  {
    $postString = str_replace('Anzeigen', 'Löschen', self::VIEW_MUSICIAN_FORM_DATA);
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
  #[Attributes\Depends('testUpdate')]
  public function testRenderChange(): void
  {
    $postString = str_replace('Anzeigen', 'Ändern', self::VIEW_MUSICIAN_FORM_DATA);
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
