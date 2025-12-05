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
