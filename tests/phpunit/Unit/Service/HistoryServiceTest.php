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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCP\IRequest;

use OCA\CAFEVDB\Service\HistoryService;
use OCA\RotDrop\Tests\DeprecationException;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the HistoryService class. */
#[Attributes\CoversClass(HistoryService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class HistoryServiceTest extends TestCase
{
  private HistoryService $service;

  private MockProvider $mockProvider;

  private IRequest $request;

  private array $postData = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->request = $this->mockProvider->getRequest();
    $this->request->method('getParam')->willReturnCallback(function (string $key, mixed $default = null) {
      return $this->postData[$key] ?? $default;
    });

    $this->request->method('getParams')->willReturnCallback(function (string $key, mixed $default = null) {
      return $this->postData;
    });

    $this->service = new HistoryService(
      appName: $this->mockProvider->appName,
      l: $this->mockProvider->getL10N(),
      logger: $this->mockProvider->getLoggerInterface(),
      request: $this->request,
      session: $this->mockProvider->getSession(),
    );
  }

  /** {@inheritdoc} */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testSetup(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testSetGetFilterOut(): void
  {
    $data = array_combine(HistoryService::EXCLUDED_KEYS, HistoryService::EXCLUDED_KEYS);
    $data['DataItem'] = 'DATA';
    $hash = $data[HistoryService::HASH_KEY];
    $this->service->set($hash, $data);
    $getData = $this->service->get($hash);
    $this->assertArrayHasKey('DataItem', $getData);
    $this->assertEquals('DATA', $getData['DataItem']);
  }

  /** @return void */
  public function testSetGetEmptyData(): void
  {
    $data = [];
    $hash = 'HASH';
    $this->service->set($hash, $data);
    $getData = $this->service->get($hash);
    $this->assertNotNull($getData);
    $this->assertEquals([], $getData);
  }

  /** @return void */
  public function testSetGetNoData(): void
  {
    $getData = $this->service->get('WHATEVER');
    $this->assertNull($getData);
  }

  /** @return void */
  public function testSetGetLastUrlPath(): void
  {
    $urlPath = 'URL PATH';
    $data = [HistoryService::FRONTEND_URL_PATH_KEY => $urlPath];
    $hash = 'HASH';
    $this->service->set($hash, $data);
    $getUrlPath = $this->service->getLastUrlPath();
    $this->assertEquals($urlPath, $getUrlPath);
  }

  /** @return void */
  public function testGetNoLastUrlPath(): void
  {
    $getUrlPath = $this->service->getLastUrlPath();
    $this->assertNull($getUrlPath);
  }

  /** @return void */
  public function testSaveGetCustomKey(): void
  {
    $customKey = 'CUSTOM KEY';
    $data = [
      HistoryService::HASH_KEY => 'IMPLIED KEY',
      'key' => 'value',
    ];
    $this->service->save(data: $data, key: $customKey);
    $getData = $this->service->get($customKey);
    $this->assertEquals(['key' => 'value'], $getData);
  }

  /** @return void */
  public function testSaveGetImpliedKey(): void
  {
    $impliedKey = 'IMPLIED KEY';
    $data = [
      HistoryService::HASH_KEY => $impliedKey,
      'key' => 'value',
    ];
    $this->service->save(data: $data);
    $getData = $this->service->get($impliedKey);
    $this->assertEquals(['key' => 'value'], $getData);
  }
}
