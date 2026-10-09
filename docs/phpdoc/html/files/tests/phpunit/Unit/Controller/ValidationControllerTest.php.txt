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

use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\Validation\IManager as ValidationManager;

use OCA\CAFEVDB\Toolkit\Common\RationalNumber;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Controller\ValidationController as TestedController;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ValidationController. */
#[Attributes\CoversClass(TestedController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\AmountResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\FuzzyInputService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
class ValidationControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\ValidationController::class;
  private const EXPECTED_ROUTES = [
    'serviceswitch',
  ];

  private MockProvider $mockProvider;

  private TestedController $controller;

  private array $postData = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );

    // For real tests we will need to mock some methods.
    $fuzzyInput = $this->mockProvider->getAppContainer()->get(FuzzyInputService::class);

    $this->controller = new TestedController(
      appName: $this->mockProvider->appName,
      request: $request,
      configService: $this->mockProvider->getConfigService(),
      fuzzyInput: $fuzzyInput,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  private const MONETARY_INPUT_VALUES = [
    '13', '0,3335', '1,3', '1,205',
  ];

  /** @return void */
  public function testValidateMonetaryValue(): void
  {
    foreach (self::MONETARY_INPUT_VALUES as $inputValue) {
      $response = $this->controller->serviceSwitch(
        topic: TestedController::TOPIC_MONETARY_VALUE,
        value: $inputValue,
      );
      $this->assertInstanceOf(Http\JSONResponse::class, $response);
      /** @var Http\JSONResponse $response */
      $this->assertEquals(Http::STATUS_OK, $response->getStatus());
      $data = $response->getData();
      $this->assertInstanceOf(DTO\AmountResponse::class, $data);
      $rational = RationalNumber::create(str_replace(',', '.', $inputValue))->round(2);
      $this->assertEquals(true, $rational->equals($data->amount));
      $jsonData = json_decode(json_encode($data), true);
      $this->assertEquals(true, $rational->equals(RationalNumber::create($jsonData['amount'])));
    }
  }
}
