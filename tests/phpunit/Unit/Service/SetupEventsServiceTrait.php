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
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
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
  use EntityGeneratorTrait;

  private EventsService $eventsService;

  /**
   * @var array<string, array>
   *
   * Some faked DB rows for generating calendar objects.
   */
  private array $calendarObjects = [];

  /**
   * Flat array of fake database rows of calendar objects.
   */
  private array $calendarData = [];

  private array $createdCalendarData = [];

  private $defaultCalendars = [];

  private IAppContainer $appContainer;

  private ConfigService $configService;

  private EntityManager $entityManager;

  private MockProvider $mockProvider;

  private CalDavBackend $calDavBackend;

  private CalendarManager $calendarManager;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function generateEventsService(): void
  {
    $this->generateProjectParticipant(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->appContainer = $mockProvider->getAppContainer();

    $l = $mockProvider->getL10N();

    $mockProvider->getCloudConfig();

    $this->configService = $mockProvider->getConfigService();
    $calendarId = 1;
    foreach (array_keys(ConfigConstants::CALENDARS) as $uri) {
      $this->configService->setConfigValue($uri . ConfigConstants::CALENDAR_KEY_POSTFIX, $l->t('uri'));
      $this->configService->setConfigValue($uri . ConfigConstants::CALENDAR_ID_KEY_POSTFIX, $calendarId);
      $this->defaultCalendars[$uri] = $calendarId;
      ++$calendarId;
    }

    $this->calendarData = array_values(CalendarObjects::getData($this->project->getName(), $this->defaultCalendars, $l));
    foreach ($this->calendarData as $index => $row) {
      $this->calendarObjects[$row['calendarid']][$row['uri']] = $index;
    }
    self::addProjectEvents($this->project, $this->defaultCalendars);

    /** @var CalDavBackend $this->calDavBackend */
    $this->calDavBackend = $this->getMockBuilder(CalDavBackend::class)
      ->onlyMethods(['getCalendarObject', 'createCalendarObject', 'getCalendarById', 'unshare'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->calDavBackend->method('createCalendarObject')
      ->willReturnCallback(
        // should return the etag
        function($calendarId, $objectUri, $calendarData, $calendarType = CalDavBackend::CALENDAR_TYPE_CALENDAR): ?string {
          $extraData = $this->calDavBackend->getDenormalizedData($calendarData);
          $rowData = [
            'calendarid' => $calendarId,
            'uri' => $objectUri,
            'calendardata' => $calendarData,
            'lastmodified' => time(),
            'etag' => $extraData['etag'],
            'size' => $extraData['size'],
            'componenttype' => $extraData['componentType'],
            'firstoccurence' => $extraData['firstOccurence'],
            'lastoccurence' => $extraData['lastOccurence'],
            'classification' => $extraData['classification'],
            'uid' => $extraData['uid'],
            'calendartype' => $calendarType,
          ];
          $this->createdCalendarData[$calendarId][$objectUri] = $rowData;
          return null;
        }
      );
    $this->calDavBackend->method('getCalendarObject')
      ->willReturnCallback(
        function(
          $calendarId,
          $objectUri,
          int $calendarType = CalDavBackend::CALENDAR_TYPE_CALENDAR,
        ): ?array {
          $rowIndex = $this->calendarObjects[$calendarId][$objectUri] ?? null;
          if ($rowIndex !== null) {
            return $this->rowToCalendarObject($this->calendarData[$rowIndex]);
          }
          if (!empty($this->createdCalendarData[$calendarId][$objectUri])) {
            return $this->rowToCalendarObject($this->createdCalendarData[$calendarId][$objectUri]);
          }
          echo 'NOT FOUND ' . $calendarId . ' ' . $objectUri . PHP_EOL;
          print_r($this->calendarObjects);
          return null;
        },
      );
    $this->calDavBackend->method('getCalendarById')
      ->willReturnCallback(
        function(int $calendarId): ?array {
          if ($calendarId < 1 || $calendarId > count($this->defaultCalendars)) {
            return null;
          }
          $calendars = array_flip($this->defaultCalendars);
          return [
            'principaluri' => 'principals/users/calendar.owner',
            'uri' => $calendars[$calendarId],
          ];
        },
      );

    /** @var CalendarManager $calendarManager */
    $this->calendarManager = $this->getMockBuilder(CalendarManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->calendarManager->method('getCalendars')
      ->willReturnCallback(
        function() use ($l) : array {
          $calendars = [];
          foreach ($this->defaultCalendars as $uri => $id) {
            $calendar = $this->createStub(ICalendar::class);
            // $calendar = $this->getMockBuilder(ICalendar::class)
            //   ->disableOriginalConstructor()
            //   ->getMock();
            $calendar->method('getKey')->willReturn((string)$id);
            $calendar->method('getdisplayName')->willReturn($l->t(ucfirst($uri) . ' (calendar.owner)'));
            $calendars[] = $calendar;
          }
          return $calendars;
        },
      );

    $calDavService = new CalDavService(
      configService: $this->configService,
      calendarManager: $this->calendarManager,
      calDavBackend: $this->calDavBackend,
    );

    $this->entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityManager->method('getWrappedObject')->willReturn($this->entityManager);
    $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        switch ($className) {
          case Entities\ProjectEvent::class:
            $repository = $this->getMockBuilder(EntityRepository::class)
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
            $repository->expects($this->never())->method('getEntityManager');
            return $repository;

          case Entities\Project::class:
            $repository = $this->getMockBuilder(EntityRepository::class)
              ->disableOriginalConstructor()
              ->getMock();
            $repository->method('find')->willReturnCallback(
              fn(int $projectId) => $this->project->getId() == $projectId ? $this->project : null,
            );
            $repository->expects($this->never())->method('getEntityManager')->willReturn($this->entityManager);
            return $repository;

          case Entities\Invoice::class:
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findLike')->willReturn([]);
            return $repository;

          default:
            return $this->createStub(EntityRepository::class);
        }
      },
    );

    /** @var ProjectService $projectService */
    $projectService = $this->createStub(ProjectService::class);

    $vCalendarService = new VCalendarService(
      configService: $mockProvider->getConfigService(),
      legacyCalendarObject: new OC_Calendar_Object(
        userSession: $mockProvider->getUserSession(),
        l: $mockProvider->getL10N(),
        dateTimeZone: $this->createStub(IDateTimeZone::class),
      ),
    );

    $this->calendarManager->expects($this->never())->method('clear');
    $this->calDavBackend->expects($this->never())->method('unshare');

    $this->eventsService = new EventsService(
      userSession: $mockProvider->getUserSession(),
      configService: $mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      projectService: $projectService,
      calDavService: $calDavService,
      vCalendarService: $vCalendarService,
      dateTimeFormatter: \OCP\Server::get(IDateTimeFormatter::class),
    );
  }

  /**
   * This has been copied from the original OCA\DAV\CalDAV\CalDavBackend.
   *
   * @param array $row Database row.
   *
   * @return array
   */
  private static function rowToCalendarObject(array $row): array
  {
    return [
      'id' => $row['id'],
      'uri' => $row['uri'],
      'uid' => $row['uid'],
      'lastmodified' => $row['lastmodified'],
      'etag' => '"' . $row['etag'] . '"',
      'calendarid' => $row['calendarid'],
      'size' => (int)$row['size'],
      'calendardata' => $row['calendardata'],
      'component' => strtolower($row['componenttype']),
      'classification' => (int)$row['classification'],
      '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_NEXTCLOUD . '}deleted-at' => $row['deleted_at'] === null ? $row['deleted_at'] : (int)$row['deleted_at'],
    ];
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
        exit(1);
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
