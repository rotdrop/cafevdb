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

use DOMDocument;
use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

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
#[Attributes\CoversClass(PageRenderer\ProjectParticipants::class)]
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

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateCalendarBackend();
    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      $this->generateProjectParticipant(persist: true);
      self::$projectId = $this->project->getId();
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
    $this->mockProvider->registerClassInstance(
      ProjectService::class,
      $projectService,
      global: true,
    );

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

  /**
   * This is quas a tearDownAfterClass() but we need some mocked / stubbed
   * classes for the entity-manager.
   *
   * @return void
   */
  #[Attributes\Depends('testApplyMigrations')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }
}
