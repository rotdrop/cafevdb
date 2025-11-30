<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\DAV\CalDAV\CalDavBackend;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType as VCalendarType;
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
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository;

/** Test the CSV export for AqBanking. */
#[Attributes\CoversClass(CalDavService::class)]
#[Attributes\CoversClass(EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractEnumType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class EventsServiceTest extends TestCase
{
  use EntityGeneratorTrait {
    EntityGeneratorTrait::setup as entitySetup;
  }

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

  private $defaultCalendars = [];

  private IAppContainer $appContainer;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->entitySetup(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = \OCP\Server::get(MockProvider::class);

    $this->appContainer = $mockProvider->getAppContainer();

    $l = $mockProvider->getL10N();

    $cloudConfig = $mockProvider->getCloudConfig();

    /** @var ConfigService $configService */
    $configService = $mockProvider->getConfigService();
    $calendarId = 1;
    foreach (array_keys(ConfigConstants::CALENDARS) as $uri) {
      $configService->setConfigValue($uri . ConfigConstants::CALENDAR_KEY_POSTFIX, $l->t('uri'));
      $configService->setConfigValue($uri . ConfigConstants::CALENDAR_ID_KEY_POSTFIX, $calendarId);
      $this->defaultCalendars[$uri] = $calendarId;
      ++$calendarId;
    }

    $projectEventId = 1;
    $this->calendarData = array_values(CalendarObjects::getData($this->project->getName(), $this->defaultCalendars, $l));
    foreach ($this->calendarData as $index => $row) {
      $this->calendarObjects[$row['calendarid']][$row['uri']] = $index;
    }
    self::addProjectEvents($this->project, $this->defaultCalendars);

    /** @var CalDavBackend $calDavBackend */
    $calDavBackend = $this->getMockBuilder(CalDavBackend::class)
      ->disableOriginalConstructor()
      ->getMock();
    $calDavBackend->method('getCalendarObject')
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
          echo 'NOT FOUND ' . $calendarId . ' ' . $objectUri . PHP_EOL;
          print_r($this->calendarObjects);
          return null;
        },
      );
    $calDavBackend->method('getCalendarById')
      ->willReturnCallback(
        function(int $calendarId): ?array {
          if ($calendarId < 1 || $calendareId > count($this->defaultCalendars)) {
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
    $calendarManager = $this->getMockBuilder(CalendarManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $calendarManager->method('getCalendars')
      ->willReturnCallback(
        function() use($l) : array {
          $calendars = [];
          foreach ($this->defaultCalendars as $uri => $id) {
            $calendar = $this->getMockBuilder(ICalendar::class)
              ->disableOriginalConstructor()
              ->getMock();
            $calendar->method('getKey')->willReturn((string)$id);
            $calendar->method('getdisplayName')->willReturn($l->t(ucfirst($uri) . ' (calendar.owner)'));
            $calendars[] = $calendar;
          }
          return $calendars;
        },
      );

    $calDavService = new CalDavService(
      configService: $configService,
      calendarManager: $calendarManager,
      calDavBackend: $calDavBackend,
    );

    /** @var EntityManager $entityManager */
    $entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entityManager->method('getRepository')->willReturnCallback(
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
            return $repository;
        }
        return null;
      },
    );

    /** @var ProjectService $projectService */
    $projectService = $this->getMockBuilder(ProjectService::class)
      ->disableOriginalConstructor()
      ->getMock();

    $vCalendarService = new VCalendarService(
      configService: $mockProvider->getConfigService(),
      legacyCalendarObject: new OC_Calendar_Object(
        userSession: $mockProvider->getUserSession(),
        l: $mockProvider->getL10N(),
      ),
    );

    $this->eventsService = new EventsService(
      userSession: $mockProvider->getUserSession(),
      configService: $mockProvider->getConfigService(),
      entityManager: $entityManager,
      projectService: $projectService,
      calDavService: $calDavService,
      vCalendarService: $vCalendarService,
      dateTimeFormatter: \OCP\Server::get(IDateTimeFormatter::class),
    );
  }

  /** @return void */
  public function testSetup(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testEvents(): void
  {
    // @todo insert fake events, until then this must be empty.
    $events = $this->eventsService->events($this->project->getId());
    $this->assertEquals($this->project->getCalendarEvents()->count(), count($events));
  }

  /** @return void */
  public function testDefaultCalendars(): void
  {
    $calendars = $this->eventsService->defaultCalendars();
    $this->assertEquals($this->defaultCalendars, $calendars);
  }

  /** @return void */
  public function testEventMatrix(): void
  {
    $events = $this->eventsService->events($this->project->getId());
    $calendars = $this->eventsService->defaultCalendars();
    $matrix = $this->eventsService->eventMatrix($events, $calendars);
    foreach ($matrix as $rowIndex => $matrixRow) {
      if ($rowIndex == -1) {
        $this->assertEquals([], $matrixRow['events']);
        continue;
      }
      $uri = $matrixRow['uri'];
      $numEvents = $this->project->getCalendarEvents()->filter(
        fn(Entities\ProjectEvent $projectEvent) => $projectEvent->getCalendarUri() == $uri,
      )->count();
      $this->assertEquals($numEvents, count($matrixRow['events']));
      $this->assertEquals($this->defaultCalendars[$uri], $rowIndex);
      $this->assertEquals($this->defaultCalendars[$uri], $matrixRow['calendarId']);
      $this->assertEquals(
        '/remote.php/dav/calendars/' . MockProvider::EXECUTIVE_BOARD_UID . '/' . $uri .  '_shared_by_calendar.owner/',
        $matrixRow['urlPath'],
      );
    }
  }

  /** @return void */
  public function testCategories(): void
  {
    $recordAbsence = $this->eventsService->getRecordAbsenceCategory(translate: false);
    $this->assertEquals($recordAbsence, EventsService::RECORD_ABSENCE_CATEGORY);
    $registrationCategory = $this->eventsService->getProjectRegistrationCategory(translate: false);
    $this->assertEquals($registrationCategory, EventsService::PROJECT_REGISTRATION_CATEGORY);
  }

  /** @return void */
  public function testL10NCategories(): void
  {
    $appL10N = $this->appContainer->get(\OCA\CAFEVDB\Service\Registration::APP_L10N);
    $recordAbsence = $this->eventsService->getRecordAbsenceCategory(translate: true);
    $this->assertEquals($recordAbsence, $appL10N->t(EventsService::RECORD_ABSENCE_CATEGORY));
    $registrationCategory = $this->eventsService->getProjectRegistrationCategory(translate: true);
    $this->assertEquals($registrationCategory, $appL10N->t(EventsService::PROJECT_REGISTRATION_CATEGORY));
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
}
