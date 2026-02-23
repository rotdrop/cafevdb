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
use OCP\AppFramework\Http;

use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Service\Finance\GnuCashConnectorService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the AccountingControllerController. */
#[Attributes\CoversClass(Controller\AccountingController::class)]
#[Attributes\CoversClass(Controller\DTO\AutocompleteGnuCashAccountsResponse::class)]
#[Attributes\CoversClass(Controller\DTO\GnuCashAccountsAutocompleteData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
class AccountingControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\AccountingController::class;
  private const EXPECTED_ROUTES = [
    'autocompletegnucashaccounts',
  ];

  private MockProvider $mockProvider;

  private Controller\AccountingController $controller;

  private GnuCashConnectorService $gnuCashConnectorService;

  private array $postData = [];

  private const PROJECT_ID = 13;

  private const FAKE_DATA = [
    'projectName' => 'a string',
    'accounts' => [
      GnuCashConnectorService::GNU_CASH_EXPENSE_KEY => ['a', 'b', 'c'],
      GnuCashConnectorService::GNU_CASH_INCOME_KEY => ['d', 'e', 'f'],
    ],
  ];

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  private function generateController(): void
  {
    // We probably want to mock it in order to control the available toolkits.
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );

    $this->gnuCashConnectorService = $this->getMockBuilder(GnuCashConnectorService::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->controller = new Controller\AccountingController(
      appName: $this->mockProvider->appName,
      request: $request,
      gnuCashConnectorService: $this->gnuCashConnectorService,
      logger: $this->mockProvider->getLoggerInterface(),
    );
  }

  /** @return void */
  public function testAutocompleteGnuCashAccounts(): void
  {
    $this->generateController();
    $this->gnuCashConnectorService
      ->expects($this->exactly(1))
      ->method('getAccountsAutocompleteData')
      ->with(self::PROJECT_ID)
      ->willReturn(self::FAKE_DATA);
    $result = $this->controller->autocompleteGnuCashAccounts(self::PROJECT_ID);
    $this->assertInstanceOf(Http\JSONResponse::class, $result);
    /** @var Http\JSONResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $data = $result->getData();
    $this->assertInstanceOf(DTO\AutocompleteGnuCashAccountsResponse::class, $data);
    /** @var DTO\AutocompleteGnuCashAccountsResponse $data */
    $this->assertEquals(self::FAKE_DATA, json_decode(json_encode($data), true));
  }
}
