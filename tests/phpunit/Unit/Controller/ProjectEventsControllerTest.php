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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IRequest;

use OCA\CAFEVDB\Controller\ProjectEventsController;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Tests\Unit\Service\SetupEventsServiceTrait;

/** Test aspects of the ProjectEventsController. */
#[Attributes\CoversClass(ProjectEventsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
class ProjectEventsControllerTest extends TestCase
{
  use SetupEventsServiceTrait
  {
    SetupEventsServiceTrait::setup as setupEventsService;
  }

  private ProjectEventsController $projectEventsController;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->setupEventsService();
    $this->mockProvider->registerClassInstance(EventsService::class, $this->eventsService);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $this->mockProvider->registerClassInstance(IRequest::class, $request);

    $this->projectEventsController = new ProjectEventsController(
      appName: $this->mockProvider->appName,
      request: $request,
      configService: $this->configService,
      eventsService: $this->eventsService,
    );
  }

  /** {@inheritdoc} */
  public function testConstruction(): void
  {
    $this->calDavBackend->expects($this->never())->method('getCalendarObject');
    $this->calendarManager->expects($this->never())->method('getCalendars');
    $this->entityManager->expects($this->never())->method('getRepository');
  }

  private const INPUT_VALUE_RESULTS = [
    [
      'calendarId' => 1,
      'uri' => '6DDC62D6-32E1-11EE-87EF-3D2D258F32B7.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2024-04-20","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9jb25jZXJ0c19zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvNkREQzYyRDYtMzJFMS0xMUVFLTg3RUYtM0QyRDI1OEYzMkI3Lmljcw==","recurrenceId":0}',
    ], [
      'calendarId' => 2,
      'uri' => '74DF0E58-32E1-11EE-B087-4BC75144380D.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2024-02-22","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9yZWhlYXJzYWxzX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci83NERGMEU1OC0zMkUxLTExRUUtQjA4Ny00QkM3NTE0NDM4MEQuaWNz","recurrenceId":0}',
    ], [
      'calendarId' => 2,
      'uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrenceId' => 1742256000,
      'calendarObject' => '{"timeRange":"2025-03-18","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9yZWhlYXJzYWxzX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci8zQzAyRjQ2NC03MEI0LTQ3NzItOTlFMy1CNkE0MUJGRURBMzEuaWNz","recurrenceId":1742256000}',
    ], [
      'calendarId' => 2,
      'uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrenceId' => 1742342400,
      'calendarObject' => '{"timeRange":"2025-03-19","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9yZWhlYXJzYWxzX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci8zQzAyRjQ2NC03MEI0LTQ3NzItOTlFMy1CNkE0MUJGRURBMzEuaWNz","recurrenceId":1742342400}',
    ], [
      'calendarId' => 2,
      'uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'recurrenceId' => 1742428800,
      'calendarObject' => '{"timeRange":"2025-03-20","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9yZWhlYXJzYWxzX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci8zQzAyRjQ2NC03MEI0LTQ3NzItOTlFMy1CNkE0MUJGRURBMzEuaWNz","recurrenceId":1742428800}',
    ], [
      'calendarId' => 2,
      'uri' => '89D17B5A-FB28-11EF-9900-4D17E2143159.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-03-21","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9yZWhlYXJzYWxzX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci84OUQxN0I1QS1GQjI4LTExRUYtOTkwMC00RDE3RTIxNDMxNTkuaWNz","recurrenceId":0}',
    ], [
      'calendarId' => 3,
      'uri' => '863FA79C-E984-11EF-803F-D77076E61BF8.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-02-16","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvODYzRkE3OUMtRTk4NC0xMUVGLTgwM0YtRDc3MDc2RTYxQkY4Lmljcw==","recurrenceId":0}',
    ], [
      'calendarId' => 3,
      'uri' => '5CE69A6C-1EE6-11F0-ADAE-9F26E247CE42.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-04-21","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvNUNFNjlBNkMtMUVFNi0xMUYwLUFEQUUtOUYyNkUyNDdDRTQyLmljcw==","recurrenceId":0}',
    ], [
      'calendarId' => 3,
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrenceId' => 1745280000,
      'calendarObject' => '{"timeRange":"2025-04-22","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvRDkyN0IzQzMtOTBGNS00QTFELThCNTItNTg5QTYzQTNCMTM4Lmljcw==","recurrenceId":1745280000}',
    ], [
      'calendarId' => 3,
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrenceId' => 1745366400,
      'calendarObject' => '{"timeRange":"2025-04-23","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvRDkyN0IzQzMtOTBGNS00QTFELThCNTItNTg5QTYzQTNCMTM4Lmljcw==","recurrenceId":1745366400}',
    ], [
      'calendarId' => 3,
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrenceId' => 1745452800,
      'calendarObject' => '{"timeRange":"2025-04-24","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvRDkyN0IzQzMtOTBGNS00QTFELThCNTItNTg5QTYzQTNCMTM4Lmljcw==","recurrenceId":1745452800}',
    ], [
      'calendarId' => 3,
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrenceId' => 1745539200,
      'calendarObject' => '{"timeRange":"2025-04-25","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvRDkyN0IzQzMtOTBGNS00QTFELThCNTItNTg5QTYzQTNCMTM4Lmljcw==","recurrenceId":1745539200}',
    ], [
      'calendarId' => 3,
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'recurrenceId' => 1745625600,
      'calendarObject' => '{"timeRange":"2025-04-26","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvRDkyN0IzQzMtOTBGNS00QTFELThCNTItNTg5QTYzQTNCMTM4Lmljcw==","recurrenceId":1745625600}',
    ], [
      'calendarId' => 3,
      'uri' => '5CE8528A-1EE6-11F0-8CB2-C77D3281A19A.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-04-27","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvNUNFODUyOEEtMUVFNi0xMUYwLThDQjItQzc3RDMyODFBMTlBLmljcw==","recurrenceId":0}',
    ], [
      'calendarId' => 3,
      'uri' => '5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-08-01","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9vdGhlcl9zaGFyZWRfYnlfY2FsZW5kYXIub3duZXIvNUU2MTg1OEEtMzJFMC0xMUVFLThCNzktRjc0NUIzQ0NGMkE2Lmljcw==","recurrenceId":0}',
    ], [
      'calendarId' => 4,
      'uri' => '521BA9B5-18A2-42D5-8E70-7BAD13376E9E.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-02-16","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9tYW5hZ2VtZW50X3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci81MjFCQTlCNS0xOEEyLTQyRDUtOEU3MC03QkFEMTMzNzZFOUUuaWNz","recurrenceId":0}',
    ], [
      'calendarId' => 5,
      'uri' => 'C9D7987C-F8D7-11EF-B5E3-8FE1B384B391.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-03-04","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9maW5hbmNlX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci9DOUQ3OTg3Qy1GOEQ3LTExRUYtQjVFMy04RkUxQjM4NEIzOTEuaWNz","recurrenceId":0}',
    ], [
      'calendarId' => 5,
      'uri' => '952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics',
      'recurrenceId' => 1741132800,
      'calendarObject' => '{"timeRange":"2025-03-05","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9maW5hbmNlX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci85NTJDMUYxNC1FNzg2LTQ4QTItQTNFQi0xM0Y4QUUyQzRFRTkuaWNz","recurrenceId":1741132800}',
    ], [
      'calendarId' => 5,
      'uri' => '30734E6C-487E-4059-9C4E-BFACC83F82AB.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-03-06","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9maW5hbmNlX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci8zMDczNEU2Qy00ODdFLTQwNTktOUM0RS1CRkFDQzgzRjgyQUIuaWNz","recurrenceId":0}',
    ], [
      'calendarId' => 5,
      'uri' => '952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics',
      'recurrenceId' => 1741219200,
      'calendarObject' => '{"timeRange":"2025-03-06","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9maW5hbmNlX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci85NTJDMUYxNC1FNzg2LTQ4QTItQTNFQi0xM0Y4QUUyQzRFRTkuaWNz","recurrenceId":1741219200}',
    ], [
      'calendarId' => 5,
      'uri' => 'C9D92912-F8D7-11EF-892C-3F353C6D504D.ics',
      'recurrenceId' => 0,
      'calendarObject' => '{"timeRange":"2025-03-07","objectId":"L3JlbW90ZS5waHAvZGF2L2NhbGVuZGFycy9qb2huLmRvZS9maW5hbmNlX3NoYXJlZF9ieV9jYWxlbmRhci5vd25lci9DOUQ5MjkxMi1GOEQ3LTExRUYtODkyQy0zRjM1M0M2RDUwNEQuaWNz","recurrenceId":0}',
    ],
  ];

  /** {@inheritdoc} */
  public function testMakeInputValue(): void
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
      $data = $this->projectEventsController->makeInputValue($matrixEvent);
      $this->assertEqualsCanonicalizing(self::INPUT_VALUE_RESULTS[$index], $data);
    }
  }
}
