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

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IRequest;

use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ToolTipsController. */
#[Attributes\CoversClass(Controller\ToolTipsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ToolTipsControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\ToolTipsController::class;
  private const EXPECTED_ROUTES = [
    'get',
    'getmultiple',
  ];

  private MockProvider $mockProvider;

  private Controller\ToolTipsController $controller;

  private array $postData = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    // We probably want to mock it in order to control the available toolkits.
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );

    // For real tests we will need to mock some methods.
    $toolTipsService = $this->mockProvider->getAppContainer()->get(ToolTipsService::class);

    $this->controller = new Controller\ToolTipsController(
      appName: $this->mockProvider->appName,
      request: $request,
      logger: $this->mockProvider->getLoggerInterface(),
      toolTipsService: $toolTipsService,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }
}
