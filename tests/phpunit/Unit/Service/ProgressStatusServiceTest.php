<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use DateTimeImmutable;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\Files\IAppData;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test the EventsService class. */
#[Attributes\CoversClass(Common\DatabaseProgressStatus::class)]
#[Attributes\CoversClass(Common\PlainFileProgressStatus::class)]
#[Attributes\CoversClass(Service\ProgressStatusService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
class ProgressStatusServiceTest extends TestCase
{
  private const START = 13;
  private const STOP = 47;
  private const DATA = [
    'key' => 'value',
  ];

  private MockProvider $mockProvider;

  private Service\ProgressStatusService $service;

  static mixed $progressId;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->service = new Service\ProgressStatusService(
      appName: $mockProvider->appName,
      appContainer: $mockProvider->getAppContainer(),
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function testCreate(): void
  {
    $progressStatus = $this->service->create(
      start: self::START,
      stop: self::STOP,
      data: self::DATA,
    );
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $this->assertEquals(self::START, $progressStatus->getCurrent());
    $this->assertEquals(self::STOP, $progressStatus->getTarget());
    $this->assertEqualsCanonicalizing(self::DATA, $progressStatus->getData());
    $id = $progressStatus->getId();
    $progressStatus = $this->service->create(
      start: self::START + 1,
      stop: self::STOP + 1,
      data: [ 'key' => 'update' ],
      id: $id,
    );
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $this->assertEquals(self::START + 1, $progressStatus->getCurrent());
    $this->assertEquals(self::STOP + 1 , $progressStatus->getTarget());
    $this->assertEquals('update', $progressStatus->getData()['key']);
    $this->assertEquals($id, $progressStatus->getId());

    self::$progressId = $id;
  }

  /** @return void */
  #[Attributes\Depends('testCreate')]
  public function testGet(): void
  {
    $progressStatus = $this->service->get(self::$progressId);
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $this->assertEquals(self::START + 1, $progressStatus->getCurrent());
    $this->assertEquals(self::STOP + 1 , $progressStatus->getTarget());
    $this->assertEquals('update', $progressStatus->getData()['key']);
    $this->assertEquals(self::$progressId, $progressStatus->getId());
  }

  /** @return void */
  #[Attributes\Depends('testGet')]
  public function testOperate(): void
  {
    $progressStatus = $this->service->get(self::$progressId);
    $progressStatus->increment();
    $this->assertEquals(self::START + 2, $progressStatus->getCurrent());
    $progressStatus->increment(delta: 13);
    $this->assertEquals(self::START + 15, $progressStatus->getCurrent());
    $progressStatus->delete();
    try {
      $progressStatus = $this->service->get(self::$progressId);
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\ProgressStatusNotFoundException::class, $t);
    }
  }
}
