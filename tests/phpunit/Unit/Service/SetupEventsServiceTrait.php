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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use UnexpectedValueException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\IAppContainer;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager as CalendarManager;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IDateTimeZone;

use OCA\DAV\CalDAV\CalDavBackend;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType as VCalendarType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/**
 * Mock around s.t. the EventsService class can be instantiated and used.
 *
 * @todo Currently this covers only the generation of the ProjectEvent
 * matrix. It should be extended to also cover the various events emitted by
 * the dav app.
 */
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
trait SetupEventsServiceTrait
{
  use SetupCalendarBackendTrait;
  use EntityGeneratorTrait;

  private EventsService $eventsService;

  private IAppContainer $appContainer;

  private EntityManager $entityManager;

  private MockProvider $mockProvider;

  private array $entityRepositories = [];

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function generateEventsService(): void
  {
    $this->generateProjectParticipant(persist: false);
    $this->generateCalendarBackend();

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->appContainer = $mockProvider->getAppContainer();

    $l = $mockProvider->getL10N();

    $mockProvider->getCloudConfig();

    $calendarData = array_values(CalendarObjects::getData($this->project->getName(), $this->defaultCalendars, $l));
    foreach ($calendarData as $row) {
      self::$calendarObjects[$row['calendarid'] . '-' . $row['uri']] = $row;
    }
    self::addProjectEvents($this->project, $this->defaultCalendars);

    $this->entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityManager->method('getWrappedObject')->willReturn($this->entityManager);
    $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        $repository = $this->entityRepositories[$className] ?? $this->getMockBuilder(EntityRepository::class)
          ->disableOriginalConstructor()
          ->getMock();

        $repository->method('getEntityManager')->willReturn($this->entityManager);
        $expects = $repository->expects($this->never())?->method('createQueryBuilder');
        return $repository;
      },
    );
    $this->mockProvider->registerClassInstance(EntityManager::class, $this->entityManager, global: true);

    // Entities\ProjectEvent
    $repository = $this->getMockBuilder(Repositories\ProjectEventsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('findBy')->willReturnCallback(
      function(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array {
        if (!isset($criteria['project']) || ($criteria['type'] ?? VCalendarType::VEVENT) != VCalendarType::VEVENT) {
          throw new UnexpectedValueException('Can only fake search for VEVENT given a project or project-id.');
        }
        $projectOrId = $criteria['project'];
        if ($projectOrId !== $this->project && $projectOrId != $this->project->getId()) {
          return [];
        }
        return $this->project->getCalendarEvents()->toArray();
      },
    );
    $repository->method('getEntityManager')->willReturn($this->entityManager);
    $repository->expects($this->never())->method('createQueryBuilder');
    $this->entityRepositories[Entities\ProjectEvent::class] = $repository;

    // Entities\Project
    $repository = $this->getMockBuilder(Repositories\ProjectsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('find')->willReturnCallback(
      fn(int $projectId) => $this->project->getId() == $projectId ? $this->project : null,
    );
    $repository->method('getEntityManager')->willReturn($this->entityManager);
    $repository->expects($this->never())->method('createQueryBuilder');
    $this->entityRepositories[Entities\Project::class] = $repository;

    // Entities\Invoice
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findLike')->willReturn([]);
    $this->entityRepositories[Entities\Invoice::class] = $repository;

    /** @var ProjectService $projectService */
    $projectService = $this->createStub(ProjectService::class);
    $this->mockProvider->registerClassInstance(ProjectService::class, $projectService, global: true);

    $this->eventsService = new EventsService(
      userSession: $mockProvider->getUserSession(),
      configService: $this->configService,
      entityManager: $this->entityManager,
      projectService: $projectService,
      calDavService: $this->calDavService,
      vCalendarService: $this->vCalendarService,
      dateTimeFormatter: \OCP\Server::get(IDateTimeFormatter::class),
    );
  }

  /**
   * Perform basic tests with the event-matrix output.
   *
   * @param array $matrix
   *
   * @return void
   */
  private function eventMatrixTest(array $matrix): void
  {
    foreach ($matrix as $rowIndex => $matrixRow) {
      if ($rowIndex == -1) {
        $this->assertEquals([], $matrixRow->events ?? []);
        continue;
      }
      $uri = $matrixRow->uri;
      if ($uri === null) {
        print_r($matrixRow);
        throw new UnexpectedValueException('URI is null');
      }
      $numEvents = $this->project->getCalendarEvents()->filter(
        fn(Entities\ProjectEvent $projectEvent) => $projectEvent->getCalendarUri() == $uri,
      )->count();
      $this->assertEquals($numEvents, count($matrixRow->events ?? []));
      $this->assertEquals($this->defaultCalendars[$uri], $rowIndex);
      $this->assertEquals($this->defaultCalendars[$uri], $matrixRow->calendarId);
      $this->assertEquals(
        '/remote.php/dav/calendars/' . MockProvider::EXECUTIVE_BOARD_UID . '/' . $uri .  '_shared_by_calendar.owner/',
        $matrixRow->urlPath,
      );
    }
  }
}
