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

use OCP\IDateTimeFormatter;
use OCP\IRequest;

use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\EmailAddressService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\Settings\Personal;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo\Service\RPC as WebPagesRPC;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the PersonalSettingsController. */
#[Attributes\CoversClass(Controller\PersonalSettingsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class PersonalSettingsControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\PersonalSettingsController::class;
  private const EXPECTED_ROUTES = [
    'form',
    'get',
    'getapp',
    'set',
    'setapp',
  ];

  private MockProvider $mockProvider;

  private Controller\PersonalSettingsController $controller;

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

    $appContainer = $this->mockProvider->getAppContainer();

    $this->controller = new Controller\PersonalSettingsController(
      appName: $this->mockProvider->appName,
      request: $request,
      configService: $this->mockProvider->getConfigService(),
      appContainer: $appContainer,
      calDavService: $this->createStub(CalDavService::class),
      configCheckService: $this->createStub(ConfigCheckService::class),
      emailAddressService: $this->createStub(EmailAddressService::class),
      financeService: $this->createStub(FinanceService::class),
      fuzzyInputService: $this->createStub(FuzzyInputService::class),
      personalSettings: $this->createStub(Personal::class),
      phoneNumberService: $this->createStub(PhoneNumberService::class),
      projectService: $this->createStub(ProjectService::class),
      translationService: $this->createStub(TranslationService::class),
      userStorage: $this->createStub(UserStorage::class),
      webPagesRPC: $this->createStub(WebPagesRPC::class),
      wikiRPC: $this->createStub(WikiRPC::class),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }
}
