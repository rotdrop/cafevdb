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

namespace OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations;

use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000001;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000003;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260108084800;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260108115432;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260130130553;
use OCA\CAFEVDB\Maintenance\Migrations\Version20260131090857;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\DriverException;

/** Test integer overflow for Unix epoche after 2028. */
#[Attributes\CoversClass(Version20260206193722::class)]
#[Attributes\UsesClass(Version19700101000001::class)]
#[Attributes\UsesClass(Version19700101000002::class)]
#[Attributes\UsesClass(Version19700101000003::class)]
#[Attributes\UsesClass(Version20260108084800::class)]
#[Attributes\UsesClass(Version20260108115432::class)]
#[Attributes\UsesClass(Version20260130130553::class)]
#[Attributes\UsesClass(Version20260131090857::class)]
class Version20260206193722Test extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use SetupMigrationTrait;

  private const Y2038_EVENT = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
PRODID:-//IDN nextcloud.com//Calendar app 6.2.0-rc.1//EN
BEGIN:VEVENT
CREATED:20260206T195449Z
DTSTAMP:20260206T195532Z
LAST-MODIFIED:20260206T195532Z
SEQUENCE:3
UID:cd08ed81-8382-4494-bde3-102c9d8444cc
DTSTART;VALUE=DATE:20380120
DTEND;VALUE=DATE:20380120
STATUS:CONFIRMED
SUMMARY:Test
CATEGORIES:Test2099
END:VEVENT
END:VCALENDAR';

  /** @return void */
  public function testVersion20260206193722(): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->mockProvider->getUserSession()->method('isLoggedIn')->willReturn(true);
    $this->appContainer = $this->appContainer ?? $this->mockProvider->getAppContainer();
    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer->get(EventsService::class);
    $this->mockProvider->registerClassInstance(EventsService::class, $eventsService, global: true);

    // up to the previous
    $this->applyMigrations(upToVersion: '20260131090857');
    $this->generateCalendarBackend();

    // we actually here only need the project ...
    $this->generateProjectParticipant(persist: true);

    $initialNumberOfProjectEvents = $this->project->getCalendarEvents()->count();

    $eventData = [
      'summary' => 'Overflow Event',
      'from' => '20-01-2038',
      'to' => '27-01-2038', // should be converted to a repeating event
      'allDay' => true,
      'calendar' => 2,
      'categories' => $this->project->getName(),
    ];

    $result = $eventsService->newEvent($eventData);
    foreach (['uri', 'uid', 'event'] as $key) {
      $this->assertArrayHasKey($key, $result);
    }
    $exceptions = $this->entityManager->getTransactionExceptions();
    $this->assertEquals(1, count($exceptions));
    /** @var DriverException $exception */
    $exception = array_pop($exceptions);
    $this->assertInstanceOf(DriverException::class, $exception);
    $this->assertStringContainsString('Numeric value out of range', $exception->getMessage());

    // ok, it failed, should be cured by applying the migration in question ...
    $migration = substr(__METHOD__, -14);
    $this->applyMigrations(upToVersion: $migration);

    // need to re-fetch as applyMigrations() empties the entity manager
    $this->project = $this->entityManager->find(Entities\Project::class, $this->project->getId());

    $result = $eventsService->newEvent($eventData);
    foreach (['uri', 'uid', 'event'] as $key) {
      $this->assertArrayHasKey($key, $result);
    }
    $this->assertEquals(8 + $initialNumberOfProjectEvents, $this->project->getCalendarEvents()->count());

    // unapply must now fail as the table contains values not representable with 32 bits
    $throwable = null;
    try {
      $this->unapplyMigrations(downBelow: $migration);
    } catch (Throwable $throwabled) {
      // empty
    }
    $this->assertInstanceOf(DriverException::class, $throwable);
    $this->assertStringContainsString('Numeric value out of range', $throwable->getMessage());
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete(Entities\ProjectEvent::class, 'pe')->where($qb->expr()->gt('pe.recurrenceId', 0x7fffffff));
    $qb->getQuery()->execute();
  }

  /** @return void */
  #[Attributes\Depends('testVersion20260206193722')]
  public function testUnapply(): void
  {
    $this->unapplyMigrations();
  }
}
