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

use ReflectionClass;
use ReflectionMethod;
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

use OCA\CAFEVDB\Common\TimeFactory;
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
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockProjectsRepositoryTrait;
use OCA\CAFEVDB\Tests\Unit\Database\MockEntityManagerTrait;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

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
  use EntityGeneratorTrait;
  use MockEntityManagerTrait;
  use MockProjectsRepositoryTrait;
  use SetupCalendarBackendTrait;

  private EventsService $eventsService;

  private ProjectService $projectService;

  private IAppContainer $appContainer;

  private EntityManager $entityManager;

  private MockProvider $mockProvider;

  private array $entityRepositories = [];

  private array $entities = [];

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
      self::$calendarObjectId = max(self::$calendarObjectId, $row['id']);
    }
    self::addProjectEvents($this->project, $this->defaultCalendars);

    $this->getEntityManagerMock();

    // Entities\ProjectEvent
    $repository = $this->getMockBuilder(Repositories\ProjectEventsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('findBy')->willReturnCallback(
      function(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array {
        if (array_is_list($criteria)) {
          // foreach ($criteria as $criterium) {
          // ain't perfect, but ... for now just return an empty array
          return [];
          // }
        } else {
          if (!empty($criteria['eventUri'])) {
            $eventUri = $criteria['eventUri'];
            return array_values(
              array_filter(
                $this->project->getCalendarEvents()->toArray(),
                fn(Entities\ProjectEvent $projectEvent) => $projectEvent->getEventUri() == $eventUri,
              ),
            );
          } elseif (!isset($criteria['project']) || ($criteria['type'] ?? VCalendarType::VEVENT) != VCalendarType::VEVENT) {
            throw new UnexpectedValueException('Can only fake search for VEVENT given a project or project-id: ' . print_r($criteria, true));
          }
          $projectOrId = $criteria['project'];
          if ($projectOrId !== $this->project && $projectOrId != $this->project->getId()) {
            return [];
          }
          return $this->project->getCalendarEvents()->toArray();
        }
      },
    );
    $repository->method('getEntityManager')->willReturn($this->entityManager);
    $repository->expects($this->never())->method('createQueryBuilder');
    $this->entityRepositories[Entities\ProjectEvent::class] = $repository;

    $this->getProjectsRepositoryMock();

    // Entities\Invoice
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findLike')->willReturn([]);
    $this->entityRepositories[Entities\Invoice::class] = $repository;

    /** @var ProjectService $projectService */
    $this->projectService = $this->getMockBuilder(ProjectService::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->mockProvider->registerClassInstance(ProjectService::class, $this->projectService, global: true);
    $this->projectService->expects($this->never())->method(ProjectService::UNUSED_METHOD_NOT_TO_BE_CALLED_NAME);

    $this->eventsService = new EventsService(
      userSession: $mockProvider->getUserSession(),
      configService: $this->configService,
      entityManager: $this->entityManager,
      projectService: $this->projectService,
      calDavService: $this->calDavService,
      vCalendarService: $this->vCalendarService,
      dateTimeFormatter: \OCP\Server::get(IDateTimeFormatter::class),
      timeFactory: $this->appContainer->get(TimeFactory::class),
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
      $uri = $matrixRow->uri->value;
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
