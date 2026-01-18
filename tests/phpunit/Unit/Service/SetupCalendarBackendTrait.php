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

use ReflectionMethod;
use UnexpectedValueException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;

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
 * Mock the CalDavBackend and generate instances of our CalDavService and
 * VCalendarService.
 */
trait SetupCalendarBackendTrait
{
  /**
   * @var array<string, array>
   *
   * Faked NC oc_calendarobjects rows, indexed by calendar id and VObject URI.
   */
  private array $calendarObjects = [];

  private $defaultCalendars = [];

  private ConfigService $configService;

  private MockProvider $mockProvider;

  private CalDavBackend $calDavBackend;

  private VCalendarService $vCalendarService;

  private CalDavService $calDavService;

  private CalendarManager $calendarManager;

  private array $sharedCalendarRows = [];

  /**
   * Setup the OCA\DAV\CalDAV\CalDavBackend, the CalendarManager and our
   * VCalendarService and CalDavService.
   *
   * @return void
   */
  public function generateCalendarBackend(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

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
    $this->sharedCalendarRows = CalendarObjects::getCalendarRows($this->defaultCalendars, $l);

    $calDavBackendMethods = [
      'getFederatedCalendarsForUser' => fn(string $principalUri): array => [],
      'getSubscriptionsForUser' => fn(string $principalUri): array => [],
      'getCalendarsForUser' => function(
        string $principalUri,
      ) {
        $calendars = [];
        $readOnlyPropertyName = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}read-only';
        foreach ($this->sharedCalendarRows as $row) {
          $uri = $row['uri'] . '_shared_by_' . CalendarObjects::CALENDAR_OWNER;
          $row['displayname'] = $row['displayname'] . ' (' . CalendarObjects::CALENDAR_OWNER . ')';
          $components = [];
          if ($row['components']) {
            $components = explode(',', $row['components']);
          }
          $calendar = [
            'id' => $row['id'],
            'uri' => $uri,
            'principaluri' => $principalUri,
            '{' . \OCA\DAV\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ?: '0'),
            '{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
            '{' . \OCA\DAV\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
            '{' . \OCA\DAV\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp('transparent'),
            '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $row['principaluri'],
            $readOnlyPropertyName => false,
          ];

          $rowToCalendar = new ReflectionMethod(CalDavBackend::class, 'rowToCalendar');
          $calendar = $rowToCalendar->invoke($this->calDavBackend, $row, $calendar);
          $calendars[] = $calendar;
        }
        return array_values($calendars);
      },
      'createCalendarObject' => function(
        $calendarId,
        $objectUri,
        $calendarData,
        $calendarType = CalDavBackend::CALENDAR_TYPE_CALENDAR
      ): ?string {
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
        $this->calendarObjects["{$calendarId}-{$objectUri}"] = $rowData;
        return null; // empty etag
      },
      'getCalendarById' => function(int $calendarId): ?array {
        $row = $this->sharedCalendarRows[$calendarId] ?? null;
        if ($row === null) {
          return null;
        }
        $components = [];
        if ($row['components']) {
          $components = explode(',', $row['components']);
        }

        $calendar = [
          'id' => $row['id'],
          'uri' => $row['uri'],
          'principaluri' => $row['principaluri'],
          '{' . \OCA\DAV\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ?: '0'),
          '{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?? 0,
          '{' . \OCA\DAV\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
          '{' . \OCA\DAV\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
        ];

        $rowToCalendar = new ReflectionMethod(CalDavBackend::class, 'rowToCalendar');
        $calendar = $rowToCalendar->invoke($this->calDavBackend, $row, $calendar);

        return $calendar;
      },
      'getCalendarObject' => function(
        $calendarId,
        $objectUri,
        int $calendarType = CalDavBackend::CALENDAR_TYPE_CALENDAR,
      ): ?array {
        $key = "{$calendarId}-{$objectUri}";
        if (!empty($this->calendarObjects[$key])) {
          return new ReflectionMethod(CalDavBackend::class, 'rowToCalendarObject')
            ->invoke($this->calDavBackend, $this->calendarObjects[$key]);
        }
        echo 'NOT FOUND ' . $calendarId . ' ' . $objectUri . PHP_EOL;
        print_r(array_keys($this->calendarObjects));
        return null;
      },
      'unshare' => null,
      'updateCalendarObject' => function(
        $calendarId,
        $objectUri,
        $calendarData,
        $calendarType = self::CALENDAR_TYPE_CALENDAR,
      ) {
        $extraData = $this->calDavBackend->getDenormalizedData($calendarData);
        if (isset($this->calendarObjects[$calendarId][$objectUri])) {
          $data = 'calendarObjects';
        } elseif (isset($this->createdCalendarData[$calendarId][$objectUri])) {
          $data = 'createdCalendarData';
        }
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
        $this->{$data} = $rowData;
        return null;
      },
    ];
    // print_r(array_keys($calDavBackendMethods));

    /** @var CalDavBackend $this->calDavBackend */
    $this->calDavBackend = $this->getMockBuilder(CalDavBackend::class)
      ->onlyMethods(array_keys($calDavBackendMethods))
      ->disableOriginalConstructor()
      ->getMock();
    foreach (array_filter($calDavBackendMethods) as $method => $implementation) {
      $this->calDavBackend
        ->method($method)
        ->willReturnCallback($implementation);
    }
    $this->calDavBackend->expects($this->never())->method('unshare');
    $this->mockProvider->registerClassInstance(CalDavBackend::class, $this->calDavBackend, global: true);

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
            $calendar->method('getdisplayName')->willReturn($l->t(ucfirst($uri) . ' (' . CalendarObjects::CALENDAR_OWNER . ')'));
            $calendars[] = $calendar;
          }
          return $calendars;
        },
      );
    // $this->calendarManager->method('searchForPrincipal')

    $this->calendarManager->expects($this->never())->method('clear');
    $this->mockProvider->registerClassInstance(CalendarManager::class, $this->calendarManager);

    $this->calDavService = new CalDavService(
      configService: $this->configService,
      calendarManager: $this->calendarManager,
      calDavBackend: $this->calDavBackend,
    );
    $this->mockProvider->registerClassInstance(CalDavService::class, $this->calDavService);

    $this->vCalendarService = new VCalendarService(
      configService: $mockProvider->getConfigService(),
      legacyCalendarObject: new OC_Calendar_Object(
        userSession: $mockProvider->getUserSession(),
        l: $mockProvider->getL10N(),
        dateTimeZone: $this->createStub(IDateTimeZone::class),
      ),
    );
    $this->mockProvider->registerClassInstance(VCalendarService::class, $this->vCalendarService);
  }
}
