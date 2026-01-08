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

use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ToolTipsService;

/** Test aspects of the AllMusicians page renderer. */
#[Attributes\CoversClass(PageRenderer\AllMusicians::class)]
#[Attributes\CoversClass(PageRenderer\Musicians::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\InvoiceNumberHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEditTimer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\ContactsCardEventListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianInstrumentEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\PME\Config::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Projects::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Util\Navigation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DoctrineMigrationsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\PageRenderer\FieldTraits\FinanceModeNavigationItemTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\PageRenderer\FieldTraits\ProjectModeNavigationItemTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class AllMusiciansTest extends TestCase
{
  use SetupMigrationTrait {
    SetupMigrationTrait::setup as migrationSetup;
    SetupMigrationTrait::tearDown as migrationTearDown;
  }

  private PageRenderer\AllMusicians $renderer;

  private PHPMyEdit $phpMyEdit;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->migrationSetup('latest');

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $appContainer = $this->mockProvider->getAppContainer();

    $this->phpMyEdit = $appContainer->get(PHPMyEdit::class);

    // what a mess ...
    $this->renderer = new PageRenderer\AllMusicians(
      configService: $mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      request: $mockProvider->getRequest(),
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

  /** {@inheritdoc} */
  public function tearDown(): void
  {
    $this->migrationTearDown();
  }

  /** {@inheritdoc} */
  public function testSetup(): void
  {
    $this->assertNotNull($this->renderer->shortTitle());
    $this->assertNotEmpty($this->renderer->navigationItems());
  }

  // phpcs:disable
  private const ADD_MUSICIAN_FORM_DATA = 'template=all-musicians&table=Musicians&templateRenderer=template%3Aall-musicians&musicianId=&projectId=0&projectName=&recordsPerPage=40&participationStatusFddIndex=22&instrummentsFddIndex=19&PME_sys_mtable=Musicians&PME_sys_mkey%5Bid%5D=int&PME_sys_qf14=max&PME_sys_qf18_comp=%3D&PME_sys_qf38_comp=%3D&PME_sys_qf46_comp=%3D&PME_sys_qf51_comp=%3D&PME_sys_qf52_comp=%3D&PME_sys_qf14=max&PME_sys_qf28=&PME_sys_cur_tab=all&PME_sys_qfn=%26PME_sys_qf14%3Dmax%26PME_sys_qf28%3D&PME_sys_rec=&PME_sys_fm=0&PME_sys_np=40&PME_sys_fl=1&PME_sys_op_name=add&PME_data_id=0&PME_data_organization=&PME_data_job_title=&PME_data_sur_name=Musterperson&PME_data_first_name=Max+Maria&PME_data_nick_name=Max&PME_data_display_name=&PME_data_display_name_personal=&PME_data_gender=&PME_data_user_id_slug=&PME_data_deleted=&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=6&PME_data_MusicianInstruments%3Ainstrument_id%5B%5D=3&PME_data_Instruments%3Asort_order=&PME_data_default_participation_status=&PME_data_cloud_account_disabled%5B%5D=1&PME_data_mobile_phone=&PME_data_fixed_line_phone=&PME_data_MusicianEmailAddresses%40all%3Aaddress%5B%5D=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_email=himself%2Bmax-maria-musterperson%40claus-justus-heine.de&PME_data_mailing_list=invite&PME_data_address_supplement=&PME_data_street=&PME_data_street_number=&PME_data_po_box=&PME_data_postal_code=&PME_data_city=&PME_data_country=DE&PME_data_birthday=&PME_data_remarks=&PME_data_language=&PME_data_SepaDebitMandates%3Amandate_reference=&PME_data_SepaDebitMandates%3Adeleted=&PME_data_SepaBankAccounts%3Aiban=&PME_data_SepaBankAccounts%3Adeleted=&PME_data_address_book_uri=&PME_sys_reloadOuterForm=&ambientContainerSelector=%23cafevdb-page-body&dialogHolderCSSId=pme-table-dialog&templateRenderer=template%3Aall-musicians&initialViewOperation=false&initialName=PME_sys_operation&initialValue=Neue+Person&reloadName=PME_sys_operation&reloadValue=Neue+Person&modalDialog=true&modified=true&PME_sys_applyadd=Anwenden&PME_sys_applyadd=Anwenden';
  // phpcs:enable

  private const BEFORE_INSERT_DO_INSERT_ALL_DATA = [
    'id' => '0',
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
    'MusicianEmailAddresses@all:address' => 'himself+max-maria-musterperson@claus-justus-heine.de',
    'email' => 'himself+max-maria-musterperson@claus-justus-heine.de',
    'mailing_list' => 'invite',
    'country' => 'DE',
    'MusicianInstruments:ranking' => '6:1,3:2',
  ];

  /** {@inheritdoc} */
  public function testBeforeInsertDoInsertAll(): void
  {
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
  }
}
