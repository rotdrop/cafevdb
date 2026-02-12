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
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test the EventsService class. */
#[Attributes\CoversClass(Common\DatabaseProgressStatus::class)]
#[Attributes\CoversClass(Common\PlainFileProgressStatus::class)]
#[Attributes\CoversClass(Service\ProgressStatusService::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
class ProgressStatusServiceTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Storage\GetAppStorageTrait;

  private const START = 13;
  private const STOP = 47;
  private const DATA = [
    'key' => 'value',
  ];

  private MockProvider $mockProvider;

  private Service\ProgressStatusService $service;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->getAppStorage();
    $this->mockProvider->registerClassInstance(AppStorage::class, $this->appStorage, global: true);
    $this->mockProvider->registerClassInstance(IAppData::class, $this->appData, global: true);

    $this->service = new Service\ProgressStatusService(
      appName: $mockProvider->appName,
      appContainer: $mockProvider->getAppContainer(),
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return string */
  private function generateProgressStatus(): string
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

    return $progressStatus->getId();
  }

  /** @return void */
  public function testCreate(): void
  {
    $id = $this->generateProgressStatus();
    $progressStatus = $this->service->create(
      start: self::START + 1,
      stop: self::STOP + 1,
      data: [ 'key' => 'update' ],
      id: $id,
    );
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $this->assertEquals(self::START + 1, $progressStatus->getCurrent());
    $this->assertEquals(self::STOP + 1, $progressStatus->getTarget());
    $this->assertEquals('update', $progressStatus->getData()['key']);
    $this->assertEquals($id, $progressStatus->getId());
  }

  /** @return void */
  public function testGet(): void
  {
    $id = $this->generateProgressStatus();

    $progressStatus = $this->service->get($id);
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $this->assertEquals(self::START, $progressStatus->getCurrent());
    $this->assertEquals(self::STOP, $progressStatus->getTarget());
    $this->assertEqualsCanonicalizing(self::DATA, $progressStatus->getData());
    $this->assertEquals($id, $progressStatus->getId());
  }

  /** @return void */
  public function testOperate(): void
  {
    $id = $this->generateProgressStatus();

    $progressStatus = $this->service->get($id);
    $progressStatus->increment();
    $this->assertEquals(self::START + 1, $progressStatus->getCurrent());
    $progressStatus->increment(delta: 13);
    $this->assertEquals(self::START + 14, $progressStatus->getCurrent());
    $progressStatus->delete();
    try {
      $progressStatus = $this->service->get($id);
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\ProgressStatusNotFoundException::class, $t);
    }
  }
}
