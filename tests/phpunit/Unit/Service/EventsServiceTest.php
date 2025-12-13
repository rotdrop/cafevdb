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

/** Test the EventsService class. */
#[Attributes\CoversClass(CalDavService::class)]
#[Attributes\CoversClass(EventsService::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractEnumType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
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
  use SetupEventsServiceTrait;

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
    '22.02.2024, 09:10',
    '20.04.2024, 16:15',
    '16.02.2025',
    '16.02.2025, 17:56',
    '04.03.2025, 09:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '06.03.2025 - 07.03.2025',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '20.03.2025 - 21.03.2025',
    '21.04.2025, 08:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '26.04.2025 - 27.04.2025',
    '01.08.2025 - 31.12.2025',
  ];

  private const LONG_EVENT_DATES = [
    '22.02.2024, 09:10 - 10:10',
    '20.04.2024, 16:15 - 19:15',
    '16.02.2025',
    '16.02.2025, 17:56 - 17:56',
    '04.03.2025, 09:00 - 23:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '06.03.2025, 23:00  -  07.03.2025, 16:00',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '20.03.2025, 23:00  -  21.03.2025, 16:00',
    '21.04.2025, 08:00 - 22:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '26.04.2025, 22:00  -  27.04.2025, 09:00',
    '01.08.2025  -  31.12.2025',
  ];

  private const MATRIX_BRIEF_EVENT_DATES = [
    '20.04.2024, 16:15',
    '22.02.2024, 09:10',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '20.03.2025 - 21.03.2025',
    '16.02.2025',
    '21.04.2025, 08:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '26.04.2025 - 27.04.2025',
    '01.08.2025 - 31.12.2025',
    '16.02.2025, 17:56',
    '04.03.2025, 09:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '06.03.2025 - 07.03.2025',
  ];

  private const MATRIX_LONG_EVENT_DATES = [
    '20.04.2024, 16:15 - 19:15',
    '22.02.2024, 09:10 - 10:10',
    '18.03.2025',
    '19.03.2025',
    '20.03.2025',
    '20.03.2025, 23:00  -  21.03.2025, 16:00',
    '16.02.2025',
    '21.04.2025, 08:00 - 22:00',
    '22.04.2025',
    '23.04.2025',
    '24.04.2025',
    '25.04.2025',
    '26.04.2025',
    '26.04.2025, 22:00  -  27.04.2025, 09:00',
    '01.08.2025  -  31.12.2025',
    '16.02.2025, 17:56 - 17:56',
    '04.03.2025, 09:00 - 23:00',
    '05.03.2025',
    '06.03.2025',
    '06.03.2025',
    '06.03.2025, 23:00  -  07.03.2025, 16:00',
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
  public function testBriefEventDateFromEventMatrix(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->atLeastOnce())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');

    $events = $this->eventsService->events($this->project->getId());
    $calendars = $this->eventsService->defaultCalendars();
    $matrix = $this->eventsService->eventMatrix($events, $calendars);
    $matrixEvents = [];
    foreach ($matrix as $rowIndex => $matrixRow) {
      $matrixEvents = array_merge($matrixEvents, $matrixRow->events);
    }
    foreach ($matrixEvents as $index => $matrixEvent) {
      $this->assertEquals(self::MATRIX_BRIEF_EVENT_DATES[$index], $this->eventsService->briefEventDate($matrixEvent));
      $this->assertEquals(self::MATRIX_LONG_EVENT_DATES[$index], $this->eventsService->longEventDate($matrixEvent));
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
}
