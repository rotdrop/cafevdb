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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Services\IInitialState;
use OCP\IInitialStateService;
use OCP\IRequest;

use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Controller\VueAppController;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;

#[Attributes\CoversClass(VueAppController::class)]
/**
 * Test aspects of the VueAppController. The tests trigger at least the
 * instantiation of each PageRenderer instance, so we claim to also cover
 * those classes. Additionally, the ToolTipsGeneration is explicitly looked
 * at.
 *
 * @todo The main entry point is still not tested.
 */
#[Attributes\CoversClass(DTO\NavigationItemsResponse::class)]
#[Attributes\CoversClass(DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\AbstractPageRenderer::class)]
#[Attributes\CoversClass(PageRenderer\AddMusicians::class)]
#[Attributes\CoversClass(PageRenderer\AllMusicians::class)]
#[Attributes\CoversClass(PageRenderer\Blog::class)]
#[Attributes\CoversClass(PageRenderer\DTO\SidebarNavigationItem::class)]
#[Attributes\CoversClass(PageRenderer\DonationReceipts::class)]
#[Attributes\CoversClass(PageRenderer\InstrumentFamilies::class)]
#[Attributes\CoversClass(PageRenderer\InstrumentInsurances::class)]
#[Attributes\CoversClass(PageRenderer\Instruments::class)]
#[Attributes\CoversClass(PageRenderer\InsuranceBrokers::class)]
#[Attributes\CoversClass(PageRenderer\InsuranceRates::class)]
#[Attributes\CoversClass(PageRenderer\Invoices::class)]
#[Attributes\CoversClass(PageRenderer\Musicians::class)]
#[Attributes\CoversClass(PageRenderer\PMETableViewBase::class)]
#[Attributes\CoversClass(PageRenderer\PME\Config::class)]
#[Attributes\CoversClass(PageRenderer\ProjectInstrumentationNumbers::class)]
#[Attributes\CoversClass(PageRenderer\ProjectParticipantFields::class)]
#[Attributes\CoversClass(PageRenderer\ProjectParticipants::class)]
#[Attributes\CoversClass(PageRenderer\ProjectPayments::class)]
#[Attributes\CoversClass(PageRenderer\Projects::class)]
#[Attributes\CoversClass(PageRenderer\Registration::class)]
#[Attributes\CoversClass(PageRenderer\SepaBankAccounts::class)]
#[Attributes\CoversClass(PageRenderer\SepaBulkTransactions::class)]
#[Attributes\CoversClass(PageRenderer\TaxExemptionNotices::class)]
#[Attributes\CoversClass(PageRenderer\TaxationStatutorySources::class)]
#[Attributes\CoversClass(Service\ToolTipsDataService::class)]
#[Attributes\CoversClass(Service\ToolTipsService::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\CryptoTrait::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\FinanceModeNavigationItemTrait::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\ProjectEntityTrait::class)]
#[Attributes\CoversTrait(PageRenderer\FieldTraits\ProjectModeNavigationItemTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Cloud\Mapper\Mapper::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Documents\OpenDocumentFiller::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Documents\TemplateService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\ConfigCheck::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Util\Navigation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ContactsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\FilesCacheService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\FinanceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\FuzzyInputService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\GeoCodingService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ImagesService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MailingListsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MusicianService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\OrganizationalRolesService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\DatabaseStorageUtil::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\AnyToPdf::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\AppStorageDisclosure::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class VueAppControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use MockUserStorageTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = VueAppController::class;
  private const EXPECTED_ROUTES = [
    'index',
    'indexfront',
    'navigation',
  ];

  private VueAppController $controller;

  private Service\ToolTipsService $toolTipsService;

  private array $postData = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->userStorage = $this->getUserStorageStub();
    $mockProvider->registerClassInstance(UserStorage::class, $this->userStorage, global: true);

    $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        $repository = $this->createStub(EntityRepository::class);
        switch ($className) {
          case Entities\Project::class:
            // echo 'REPO ' . $className . (new \Exception('')->getTraceAsString()) .  PHP_EOL;
            $repository->method('find')->willReturnCallback(
              function(mixed $identifier) {
                return (($identifier['id'] ?? $identifier ?? null) === $this->project->getId()) ? $this->project : null;
              },
            );
            $repository->method('findOneBy')->willReturnCallback(
              function(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null) {
                if ($this->project->getName() == ($criteria['name'] ?? null) || $this->project->getId() == ($criteria['id'] ?? null)) {
                  return $this->project;
                }
                return null;
              },
            );
            break;
          default:
            break;
        }
        return $repository;
      }
    );
    $mockProvider->registerClassInstance(EntityManager::class, $this->entityManager);

    $request = $mockProvider->getRequest();
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      }
    );
    $this->postData[PageRenderer\PersistentCGIKeys::PROJECT_ID] = $this->project->getId();
    $this->postData[PageRenderer\PersistentCGIKeys::PROJECT_NAME] = $this->project->getName();

    $appContainer = $mockProvider->getAppContainer();

    $this->toolTipsService = $appContainer->get(Service\ToolTipsService::class);

    $this->controller = new VueAppController(
      appName: $mockProvider->appName,
      request: $request,
      // userId: $appContainer->get('userId'),
      userId: $mockProvider->getUserSession()->getUser()->getUID(),
      assetService: $this->createStub(Service\AssetService::class),
      configService: $mockProvider->getConfigService(),
      historyService: $this->createStub(Service\HistoryService::class),
      authorizationService: $mockProvider->getAuthorizationService(),
      toolTipsService: $this->toolTipsService,
      initialState: $this->createStub(IInitialState::class),
      initialStateService: $this->createStub(IInitialStateService::class),
      appContainer: $appContainer,
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
  }

  /** @return void */
  public function testGetNavigation(): void
  {
    foreach (array_keys(PageRenderer\Registration::LEGACY_TEMPLATES) as $template) {
      $result = $this->controller->navigation(
        template: $template,
        projectId: $this->project->getId(),
        projectName: $this->project->getName(),
      );
      $data = $result->getData();
      $data->jsonSerialize();
      $this->assertTrue(is_array($data->navigation));
      if ($template == PageRenderer\ConfigCheck::TEMPLATE) {
        continue;
      }
      $this->assertTrue(0 < count($data->navigation));
      foreach ($data->navigation as $item) {
        $this->assertInstanceOf(DTO\SidebarNavigationItem::class, $item);
        /** @var DTO\SidebarNavigationItem $item */
        $this->assertEquals($this->toolTipsService[$item->nameKey], $item->name);
      }
    }
  }
}
