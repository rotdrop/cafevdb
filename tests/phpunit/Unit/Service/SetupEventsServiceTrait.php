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
  use SetupCalendarBackendTrait;
  use EntityGeneratorTrait;

  private EventsService $eventsService;

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
    }
    self::addProjectEvents($this->project, $this->defaultCalendars);

    $this->entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityManager->method('getWrappedObject')->willReturn($this->entityManager);
    $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        $repository = $this->entityRepositories[$className] ?? null;
        if ($repository == null) {
          $repository = $this->getMockBuilder(EntityRepository::class)
          ->disableOriginalConstructor()
            ->getMock();
          $this->entityRepositories[$className] = $repository;
        }
        $repository->method('getEntityManager')->willReturn($this->entityManager);
        $repository->expects($this->never())?->method('createQueryBuilder');
        return $repository;
      },
    );
    $this->entityManager->method('persist')->willReturnCallback(
      function(mixed $entity) {
        if (!method_exists($entity, 'getId')) {
          // give up for now
          return;
        }
        $class = get_class($entity);
        if (!isset($this->entities[$class])) {
          $this->entities[$class] = new ArrayCollection;
        }
        $givenId = $entity->getId();
        if ($givenId !== null) {
          $oldEntity = $this->entities[$class]->get($givenId);
          if ($oldEntity) {
            $this->assertEquals($entity, $oldEntity);
            return;
          }
          $this->entities[$class]->set($givenId, $entity);
          return;
        }
        $newId = \max(0, 0, ...$this->entities[$class]->getKeys()) + 1;
        $this->entities[$class]->set($newId, $entity);
      },
    );
    $this->entityManager->method('flush')->willReturnCallback(function() {
      foreach ($this->entities as $entities) {
        foreach ($entities as $id => $entity) {
          $entity->setId($id);
        }
      }
    });
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
    $allMethods = array_map(
      fn(ReflectionMethod $method) => $method->getName(),
      new ReflectionClass(Repositories\ProjectsRepository::class)->getMethods(),
    );
    $wantedMethods = array_diff($allMethods, [
      'findByIdOrName',
      'findById',
      'ensureProject',
      'findOneBy',
      'findAll',
    ]);
    $repository = $this->getMockBuilder(Repositories\ProjectsRepository::class)
      ->disableOriginalConstructor()
      ->onlyMethods($wantedMethods)
      ->getMock();
    $repository->method('find')->willReturnCallback(function(mixed $id) {
      if (is_array($id)) {
        $id = $id['id'];
      }
      $projectId = (int)$id;
      if ($this->project->getId() == $projectId) {
        return $this->project;
      }
      if (isset($this->entities[Entities\Project::class])) {
        return $this->entities[Entities\Project::class]->get($projectId);
      }
      return null;
    });
    $repository->method('findBy')->willReturnCallback(
      function(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null) {
        foreach ($criteria as $criterium) {
          $this->assertTrue(is_array($criterium));
          $this->assertEquals(1, count($criterium));
          $this->assertTrue(ctype_alpha(array_keys($criterium)[0]));
          $field = array_keys($criterium)[0];
          $method = 'get' . ucfirst($field);
          $this->assertTrue(method_exists(Entities\Project::class, $method));
        }
        $allEntities = ($this->entities[Entities\Project::class] ?? null)?->toArray() ?? [];
        $allEntities[$this->project->getId()] = $this->project;
        $entities = array_filter(
          $allEntities,
          function(Entities\Project $entity) use ($criteria) {
            foreach ($criteria as $criterium) {
              $field = array_keys($criterium)[0];
              $value = array_values($criterium)[0];
              $method = 'get' . ucfirst($field);
              if ($entity->$method() != $value) {
                return false;
              }
            }
            return true;
          },
        );
        if (!empty($orderBy)) {
          usort(entities, function(Entities\Project $a, Entities\Project $b) use ($orderBy) {
            $result = 0;
            foreach ($orderBy as $field => $direction) {
              $method = 'get' . ucfirst($field);
              $result = $a->$method() <=> $b->$method();
              if ($direction == 'DESC') {
                $result = -$result;
              }
              if ($result) {
                break;
              }
            }
            return $result;
          });
        }
        return array_slice($entities, $offset ?? 0, $limit);
      },
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
