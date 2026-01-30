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

use Throwable;
use UnexpectedValueException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use ReflectionProperty;
use Sabre\VObject;

use OCP\AppFramework\IAppContainer;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager as CalendarManager;
use OCP\IDateTimeFormatter;
use OCP\IL10N;

use OCA\DAV\CalDAV\CalDavBackend;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType as VCalendarType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
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

/** Test the EventsService class. */
#[Attributes\CoversClass(CalDavService::class)]
#[Attributes\CoversClass(EventsService::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Listener\CalendarObjectCreatedEventListener::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Listener\CalendarObjectUpdatedEventListener::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class EventsServiceTest extends TestCase
{
  use SetupEventsServiceTrait;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateEventsService();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->mockProvider->getUserSession()->method('isLoggedIn')->willReturn(true);

    // $this->entityManager->expects($this->never())->method('getRepository');
  }

  /** @return void */
  public function testEvents(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');
    $events = $this->eventsService->events($this->project->getId());
    $this->assertEquals($this->project->getCalendarEvents()->count(), count($events));
  }

  private const BRIEF_EVENT_DATES = [
    '22.02.2024, 10:10',
    '20.04.2024, 18:15',
    '16.02.2025',
    '16.02.2025, 18:56',
    '04.03.2025, 10:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '07.03.2025, bis 17:00',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '21.03.2025, bis 17:00',
    '21.04.2025, 10:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '27.04.2025, bis 11:00',
    '01.08.2025 - 31.12.2025',
  ];

  private const LONG_EVENT_DATES = [
    '22.02.2024, 10:10 - 11:10',
    '20.04.2024, 18:15 - 21:15',
    '16.02.2025',
    '16.02.2025, 18:56 - 18:56',
    '04.03.2025, 10:00 - 24:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '07.03.2025, 00:00 - 17:00',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '21.03.2025, 00:00 - 17:00',
    '21.04.2025, 10:00 - 24:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '27.04.2025, 00:00 - 11:00',
    '01.08.2025  -  31.12.2025',
  ];

  private const MATRIX_BRIEF_EVENT_DATES = [
    '20.04.2024, 18:15',
    '22.02.2024, 10:10',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '21.03.2025, bis 17:00',
    '16.02.2025',
    '21.04.2025, 10:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '27.04.2025, bis 11:00',
    '01.08.2025 - 31.12.2025',
    '16.02.2025, 18:56',
    '04.03.2025, 10:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '07.03.2025, bis 17:00',
  ];

  private const MATRIX_LONG_EVENT_DATES = [
    '20.04.2024, 18:15 - 21:15',
    '22.02.2024, 10:10 - 11:10',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '21.03.2025, 00:00 - 17:00',
    '16.02.2025',
    '21.04.2025, 10:00 - 24:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '27.04.2025, 00:00 - 11:00',
    '01.08.2025  -  31.12.2025',
    '16.02.2025, 18:56 - 18:56',
    '04.03.2025, 10:00 - 24:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '07.03.2025, 00:00 - 17:00',
    ];

  /** @return void */
  public function testEventDateFromEventData(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');
    $events = $this->eventsService->events($this->project->getId());

    foreach ($events as $index => $event) {
      $this->assertEquals(self::BRIEF_EVENT_DATES[$index], $this->eventsService->briefEventDate($event));
      $this->assertEquals(self::LONG_EVENT_DATES[$index], $this->eventsService->longEventDate($event));
    }
  }

  /** @return void */
  public function testEventDateFromEventMatrix(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->atLeastOnce())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');

    $events = $this->eventsService->events($this->project->getId());
    $calendars = $this->eventsService->defaultCalendars();
    $matrix = $this->eventsService->eventMatrix($events, $calendars);
    $matrixEvents = [];
    foreach ($matrix as $matrixRow) {
      $matrixEvents = array_merge($matrixEvents, $matrixRow->events);
    }
    foreach ($matrixEvents as $index => $matrixEvent) {
      $this->assertEquals(self::MATRIX_BRIEF_EVENT_DATES[$index], $this->eventsService->briefEventDate($matrixEvent));
      $this->assertEquals(self::MATRIX_LONG_EVENT_DATES[$index], $this->eventsService->longEventDate($matrixEvent));
    }
  }

  private const FLAT_IDENTIFIERS_FROM_EVENT_DATA = [
    '2:74DF0E58-32E1-11EE-B087-4BC75144380D.ics:0',
    '1:6DDC62D6-32E1-11EE-87EF-3D2D258F32B7.ics:0',
    '3:863FA79C-E984-11EF-803F-D77076E61BF8.ics:0',
    '4:521BA9B5-18A2-42D5-8E70-7BAD13376E9E.ics:0',
    '5:C9D7987C-F8D7-11EF-B5E3-8FE1B384B391.ics:0',
    '5:952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics:1741132800',
    '5:30734E6C-487E-4059-9C4E-BFACC83F82AB.ics:0',
    '5:952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics:1741219200',
    '5:C9D92912-F8D7-11EF-892C-3F353C6D504D.ics:0',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742256000',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742342400',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742428800',
    '2:89D17B5A-FB28-11EF-9900-4D17E2143159.ics:0',
    '3:5CE69A6C-1EE6-11F0-ADAE-9F26E247CE42.ics:0',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745280000',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745366400',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745452800',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745539200',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745625600',
    '3:5CE8528A-1EE6-11F0-8CB2-C77D3281A19A.ics:0',
    '3:5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics:0',
  ];

  /** @return void */
  public function testMakeFlatIdentifierFromEventData(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');
    $events = $this->eventsService->events($this->project->getId());
    foreach ($events as $index => $event) {
      $this->assertEquals(
        self::FLAT_IDENTIFIERS_FROM_EVENT_DATA[$index],
        $this->eventsService->makeFlatIdentifier($event),
      );
    }
  }

  private const FLAT_IDENTIFIERS_FROM_EVENT_MATRIX = [

    '1:6DDC62D6-32E1-11EE-87EF-3D2D258F32B7.ics:0',
    '2:74DF0E58-32E1-11EE-B087-4BC75144380D.ics:0',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742256000',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742342400',
    '2:3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics:1742428800',
    '2:89D17B5A-FB28-11EF-9900-4D17E2143159.ics:0',
    '3:863FA79C-E984-11EF-803F-D77076E61BF8.ics:0',
    '3:5CE69A6C-1EE6-11F0-ADAE-9F26E247CE42.ics:0',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745280000',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745366400',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745452800',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745539200',
    '3:D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics:1745625600',
    '3:5CE8528A-1EE6-11F0-8CB2-C77D3281A19A.ics:0',
    '3:5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics:0',
    '4:521BA9B5-18A2-42D5-8E70-7BAD13376E9E.ics:0',
    '5:C9D7987C-F8D7-11EF-B5E3-8FE1B384B391.ics:0',
    '5:952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics:1741132800',
    '5:30734E6C-487E-4059-9C4E-BFACC83F82AB.ics:0',
    '5:952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics:1741219200',
    '5:C9D92912-F8D7-11EF-892C-3F353C6D504D.ics:0',
  ];

  /** @return void */
  public function testMakeFlatIdentifierFromEventMatrix(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->atLeastOnce())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');

    $events = $this->eventsService->events($this->project->getId());
    $calendars = $this->eventsService->defaultCalendars();
    $matrix = $this->eventsService->eventMatrix($events, $calendars);
    $matrixEvents = [];
    foreach ($matrix as $matrixRow) {
      $matrixEvents = array_merge($matrixEvents, $matrixRow->events);
    }
    foreach ($matrixEvents as $index => $matrixEvent) {
      $this->assertEquals(
        self::FLAT_IDENTIFIERS_FROM_EVENT_MATRIX[$index],
        $this->eventsService->makeFlatIdentifier($matrixEvent),
      );
    }
  }

  /** @return void */
  public function testDefaultCalendars(): void
  {
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->never())->method('getCalendarObject');

    $calendars = $this->eventsService->defaultCalendars();
    $this->assertEquals($this->defaultCalendars, $calendars);
  }

  /** @return void */
  public function testEventMatrix(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->atLeastOnce())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');

    $events = $this->eventsService->events($this->project->getId());
    $calendars = $this->eventsService->defaultCalendars();
    $matrix = $this->eventsService->eventMatrix($events, $calendars);
    $this->eventMatrixTest($matrix);
  }

  /** @return void */
  public function testCategories(): void
  {
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->never())->method('getCalendarObject');

    $recordAbsence = $this->eventsService->getRecordAbsenceCategory(translate: false);
    $this->assertEquals($recordAbsence, EventsService::RECORD_ABSENCE_CATEGORY);
    $registrationCategory = $this->eventsService->getProjectRegistrationCategory(translate: false);
    $this->assertEquals($registrationCategory, EventsService::PROJECT_REGISTRATION_CATEGORY);
  }

  /** @return void */
  public function testL10NCategories(): void
  {
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->never())->method('getCalendarObject');

    $appL10N = $this->appContainer->get(\OCA\CAFEVDB\Service\Registration::APP_L10N);
    $recordAbsence = $this->eventsService->getRecordAbsenceCategory(translate: true);
    $this->assertEquals($recordAbsence, $appL10N->t(EventsService::RECORD_ABSENCE_CATEGORY));
    $registrationCategory = $this->eventsService->getProjectRegistrationCategory(translate: true);
    $this->assertEquals($registrationCategory, $appL10N->t(EventsService::PROJECT_REGISTRATION_CATEGORY));
  }

  /** @return void */
  public function testEnsureProjectRegistrationEvent(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->exactly(2))->method('getCalendarObject');
    $this->calDavBackend->expects($this->exactly(1))->method('createCalendarObject');
    $this->calDavBackend->expects($this->exactly(1))->method('deleteCalendarObject');

    $this->project->setRegistrationStartDate(null);
    $result = $this->eventsService->ensureProjectRegistrationEvent($this->project);
    $this->assertFalse($result);

    $this->project->setRegistrationStartDate('2099-01-01');
    try {
      $this->eventsService->ensureProjectRegistrationEvent($this->project);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
    $this->project->setRegistrationDeadline('2099-12-31');
    $this->projectService->expects($this->atLeastOnce())
      ->method('getProjectRegistrationDeadline')
      ->willReturn($this->project->getRegistrationDeadline());
    $this->projectService->expects($this->atLeastOnce())
      ->method('fetchAll')
      ->willReturn($this->entityManager->getRepository(Entities\Project::class)->findAll());

    $projectEventsCount = $this->project->getCalendarEvents()->count();
    $result = $this->eventsService->ensureProjectRegistrationEvent($this->project);
    $this->assertTrue($result);
    $this->assertEquals($projectEventsCount + 1, $this->project->getCalendarEvents()->count());
  }

  /** @return void */
  #[Attributes\Depends('testEnsureProjectRegistrationEvent')]
  public function testFindProjectRegistrationEvent(): void
  {
    $this->entityManager->expects($this->exactly(0))->method('getRepository');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->calDavBackend->expects($this->exactly(2))->method('getCalendarObject');
    $this->calDavBackend->expects($this->exactly(0))->method('createCalendarObject');
    $this->calDavBackend->expects($this->exactly(0))->method('deleteCalendarObject');
    $this->projectService->expects($this->exactly(0))->method('getProjectRegistrationDeadline');

    $this->project->setRegistrationStartDate('2099-01-01');

    try {
      $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\CalendarException::class, $t);
    }

    $this->assertArrayHasKey('3-5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics', self::$calendarObjects);
    unset(self::$calendarObjects['3-5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics']);

    $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
    $this->assertNotEmpty($registrationEvent);
    $this->assertArrayHasKey('calendardata', $registrationEvent);
    $this->assertInstanceOf(VObject\Component\VCalendar::class, $registrationEvent['calendardata']);
    $registrationVEvent = VCalendarService::getVObject($registrationEvent['calendardata']);
    $this->assertInstanceOf(VObject\Component\VEvent::class, $registrationVEvent);
    $this->assertStringContainsString($this->project->getName(), $registrationVEvent->DESCRIPTION);

    $this->calDavService->clearCalendarObjectCache();
    // Should not recurse to the calDavService
    $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
  }

  /** @return void */
  #[Attributes\Depends('testFindProjectRegistrationEvent')]
  public function testSanitizeProjectRegistrationEvent(): void
  {
    $this->entityManager->expects($this->exactly(2))->method('getRepository');
    $this->calDavBackend->expects($this->exactly(3))->method('getCalendarObject');
    $this->calDavBackend->expects($this->exactly(1))->method('updateCalendarObject');

    $this->assertArrayHasKey('3-5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics', self::$calendarObjects);
    unset(self::$calendarObjects['3-5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics']);
    $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
    $this->assertNotEmpty($registrationEvent);

    $storedEvent = self::$calendarObjects["{$registrationEvent['calendarid']}-{$registrationEvent['uri']}"];
    $this->assertNotNull($storedEvent);
    $storedVObject = VCalendarService::getVCalendar($storedEvent);
    $storedVEvent = VCalendarService::getVObject($storedVObject);
    $oldDescription = (string)$storedVEvent->DESCRIPTION;
    $storedVEvent->DESCRIPTION = str_replace($this->project->getName(), 'SomeOtherName2099', $oldDescription);
    $storedEvent['calendardata'] = $storedVObject->serialize();
    self::$calendarObjects["{$registrationEvent['calendarid']}-{$registrationEvent['uri']}"] = $storedEvent;

    new ReflectionProperty($this->eventsService, 'projectRegistrationEvents')->setValue($this->eventsService, []);
    $this->calDavService->clearCalendarObjectCache();

    $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
    $this->assertNotEmpty($registrationEvent);
    $this->assertArrayHasKey('calendardata', $registrationEvent);
    $this->assertInstanceOf(VObject\Component\VCalendar::class, $registrationEvent['calendardata']);
    $registrationVEvent = VCalendarService::getVObject($registrationEvent['calendardata']);
    $this->assertInstanceOf(VObject\Component\VEvent::class, $registrationVEvent);
    $this->assertStringNotContainsString($this->project->getName(), $registrationVEvent->DESCRIPTION);

    $this->project->setRegistrationStartDate('2099-01-01');
    $this->project->setRegistrationDeadline('2099-12-31');
    $this->projectService->expects($this->atLeastOnce())
      ->method('getProjectRegistrationDeadline')
      ->willReturn($this->project->getRegistrationDeadline());
    $this->projectService->expects($this->exactly(1))
      ->method('fetchAll')
      ->willReturn($this->entityManager->getRepository(Entities\Project::class)->findAll());

    $result = $this->eventsService->ensureProjectRegistrationEvent($this->project);
    $this->assertTrue($result);

    new ReflectionProperty($this->eventsService, 'projectRegistrationEvents')->setValue($this->eventsService, []);
    $this->calDavService->clearCalendarObjectCache();

    $registrationEvent = $this->eventsService->findProjectRegistrationEvent($this->project);
    $registrationVEvent = VCalendarService::getVObject($registrationEvent['calendardata']);
    $this->assertEquals($oldDescription, (string)$registrationVEvent->DESCRIPTION);
  }
}
