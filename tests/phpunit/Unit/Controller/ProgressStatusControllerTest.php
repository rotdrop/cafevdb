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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use ReflectionClass;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test the EntityRepositoryController. */
#[Attributes\CoversClass(Common\PlainFileProgressStatus::class)]
#[Attributes\CoversClass(Controller\DTO\MessagesResponse::class)]
#[Attributes\CoversClass(Controller\DTO\ProgressResponse::class)]
#[Attributes\CoversClass(Controller\ProgressStatusController::class)]
#[Attributes\CoversClass(Service\ProgressStatusService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Storage\AppStorage::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
class ProgressStatusControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\ProgressStatusController::class;
  private const EXPECTED_ROUTES = ['get', 'action'];

  private const START = 17;
  private const STOP = 117;
  private const DATA = [ 'key' => 'value' ];

  private Controller\ProgressStatusController $controller;

  private Service\ProgressStatusService $progressStatusService;

  private MockProvider $mockProvider;

  private array $postData = [];

  private static mixed $progressId;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $mockProvider = $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->progressStatusService = new Service\ProgressStatusService(
      appName: $mockProvider->appName,
      appContainer: $mockProvider->getAppContainer(),
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );

    $request = $mockProvider->getRequest();
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      }
    );

    $this->controller = new Controller\ProgressStatusController(
      appName: $mockProvider->appName,
      request: $request,
      progressStatusService: $this->progressStatusService,
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /** @return void */
  public function testCreate(): void
  {
    $this->postData = [
      'current' => self::START,
      'target' => self::STOP,
      'data' => self::DATA,
    ];
    $response = $this->controller->action(Controller\EnumProgressStatusOperation::CREATE->value);
    $this->assertTrue(($response instanceof Http\DataResponse) || ($response instanceof Http\JSONResponse));
    $data = $response->getData();
    $this->assertInstanceOf(Controller\DTO\ProgressResponse::class, $data);
    /** @var Controller\DTO\ProgressResponse $data */
    $this->assertEquals($this->postData['current'], $data->current);
    $this->assertEquals($this->postData['target'], $data->target);
    $this->assertEqualsCanonicalizing($this->postData['data'], $data->data);
    self::$progressId = $data->id;
  }

  /** @return void */
  #[Attributes\Depends('testCreate')]
  public function testUpdate(): void
  {
    $this->postData = [
      'id' => self::$progressId,
      'current' => self::START + 1,
      'target' => self::STOP + 1,
      'data' => [ 'key' => 'updated' ],
    ];
    $response = $this->controller->action(Controller\EnumProgressStatusOperation::UPDATE->value);
    $this->assertTrue(($response instanceof Http\DataResponse) || ($response instanceof Http\JSONResponse));
    $data = $response->getData();
    $this->assertInstanceOf(Controller\DTO\ProgressResponse::class, $data);
    /** @var Controller\DTO\ProgressResponse $data */
    $this->assertEquals($this->postData['current'], $data->current);
    $this->assertEquals($this->postData['target'], $data->target);
    $this->assertEqualsCanonicalizing($this->postData['data'], $data->data);
  }

  /** @return void */
  #[Attributes\Depends('testUpdate')]
  public function testGet(): void
  {
    $progressStatus = $this->progressStatusService->get(self::$progressId);
    $this->assertInstanceOf(Common\IProgressStatus::class, $progressStatus);
    $progressStatus->update(current: 190);
    $response = $this->controller->get(self::$progressId);
    $this->assertTrue(($response instanceof Http\DataResponse) || ($response instanceof Http\JSONResponse));
    $data = $response->getData();
    $this->assertInstanceOf(Controller\DTO\ProgressResponse::class, $data);
    /** @var Controller\DTO\ProgressResponse $data */
    $this->assertEquals(190, $data->current);
  }

  /** @return void */
  #[Attributes\Depends('testGet')]
  public function testDelete(): void
  {
    $this->postData = [
      'id' => self::$progressId,
    ];
    $response = $this->controller->action(Controller\EnumProgressStatusOperation::DELETE->value);
    $this->assertTrue(($response instanceof Http\DataResponse) || ($response instanceof Http\JSONResponse));
    $data = $response->getData();
    $this->assertInstanceOf(Controller\DTO\MessagesResponse::class, $data);
    try {
      $this->progressStatusService->get(self::$progressId);
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\ProgressStatusNotFoundException::class, $t);
    }
  }
}
