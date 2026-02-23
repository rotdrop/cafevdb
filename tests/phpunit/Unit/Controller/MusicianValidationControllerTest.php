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
use OCA\CAFEVDB\Controller\MusicianValidationController as TestedController;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Service\EmailAddressService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the MusicianValidationController. */
#[Attributes\CoversClass(TestedController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\AutocompletePlaceResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\AutocompleteStreetResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\EmailValidationResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\MessagesResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\PhoneNumberValidationResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
class MusicianValidationControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Legacy\PME\GetPMEStubTrait;

  private const CONTROLLER_CLASS = TestedController::class;
  private const EXPECTED_ROUTES = [
    'validate',
  ];

  private MockProvider $mockProvider;

  private TestedController $controller;

  private GeoCodingService $geoCodingService;

  private array $postData = [];

  private const DATA_PREFIX = 'HutzliPutzli';

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

    $this->getPHPMyEditStub();

    $this->postData[PersistentCGIKeys::DATA_PREFIX] = [ 'musicians' => self::DATA_PREFIX ];

    $this->geoCodingService = $this->getMockBuilder(GeoCodingService::class)
      ->disableOriginalConstructor()
      ->getMock();
    // just work around PHPUnit't evolving strictness
    $this->geoCodingService->expects($this->never())->method('updateCountries');

    $this->controller = new TestedController(
      appName: $this->mockProvider->appName,
      request: $request,
      emailAddressService: $appContainer->get(EmailAddressService::class),
      geoCodingService: $this->geoCodingService,
      l: $this->mockProvider->getL10N(),
      logger: $this->mockProvider->getLoggerInterface(),
      phoneNumberService: $appContainer->get(PhoneNumberService::class),
      pme: $this->pme,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  private const TEST_EMAIL = [
    '' => false,
    'someone@domain.tld' => true,
    'John Doe <john@doe.tld>' => true,
    'john@doe.tld (John Doe)' => true,
    'balh@blah@blah@blah' => false,
  ];

  /** @return void */
  public function testValidateEmail(): void
  {
    foreach (self::TEST_EMAIL as $address => $expectOk) {
      $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'email')] = $address;
      foreach (TestedController::EMAIL_VALIDATION_ON_FAILURE as $onFailure) {
        $response = $this->controller->validate(
          topic: Controller\EnumMusicianValidationTopic::EMAIL,
          subTopic: null,
          failure: $onFailure,
        );
        $this->assertInstanceOf(Http\JSONResponse::class, $response);
        $expectedStatus = !$expectOk && $onFailure == TestedController::EMAIL_VALIDATION_ON_FAILURE_ERROR
          ? Http::STATUS_BAD_REQUEST
          : Http::STATUS_OK;
        if ($expectedStatus != $response->getStatus()) {
          echo 'HTTP status mismatch ' . $address . PHP_EOL;
          print_r($data->messages);
        }
        /** @var Http\JSONResponse $response */
        $this->assertEquals($expectedStatus, $response->getStatus());
        $data = $response->getData();
        $this->assertInstanceOf(DTO\EmailValidationResponse::class, $data);
        /** @var DTO\EmailValidationResponse $data */
      }
    }
  }

  private const INVALID_NUMBER = '12345';
  private const FIXED_LINE_NUMBER = [
    'input' => '0213456789',
    'output' => '+49 2134 56789',
  ];
  private const MOBILE_NUMBER = [
    'input' => '015160000000',
    'output' => '+49 1516 0000000',
  ];

  /** @return void */
  public function testValidatePhoneNumbersInvalid(): void
  {
    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'mobile_phone')] = self::INVALID_NUMBER;
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(1, count($data->messages));
    $this->assertEquals(self::INVALID_NUMBER, $data->mobilePhone);
    $this->assertEmpty($data->mobileMeta);
    $this->assertEmpty($data->fixedLinePhone);
    $this->assertEmpty($data->fixedLineMeta);

    unset($this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'mobile_phone')]);
    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'fixed_line_phone')] = self::INVALID_NUMBER;
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(1, count($data->messages));
    $this->assertEmpty($data->mobilePhone);
    $this->assertEmpty($data->mobileMeta);
    $this->assertEquals(self::INVALID_NUMBER, $data->fixedLinePhone);
    $this->assertEmpty($data->fixedLineMeta);

    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'mobile_phone')] = self::INVALID_NUMBER;
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(2, count($data->messages));
    $this->assertEquals(self::INVALID_NUMBER, $data->mobilePhone);
    $this->assertEmpty($data->mobileMeta);
    $this->assertEquals(self::INVALID_NUMBER, $data->fixedLinePhone);
    $this->assertEmpty($data->fixedLineMeta);
  }

  /** @return void */
  public function testValidatePhoneNumbersValidMobile(): void
  {
    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'mobile_phone')] = self::MOBILE_NUMBER['input'];
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(0, count($data->messages));
    $this->assertEquals(self::MOBILE_NUMBER['output'], $data->mobilePhone);
    $this->assertNotEmpty($data->mobileMeta);
    $this->assertEmpty($data->fixedLinePhone);
    $this->assertEmpty($data->fixedLineMeta);
  }

  /** @return void */
  public function testValidatePhoneNumbersValidFixedLine(): void
  {
    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'fixed_line_phone')] = self::FIXED_LINE_NUMBER['input'];
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(0, count($data->messages));
    $this->assertEquals(self::FIXED_LINE_NUMBER['output'], $data->fixedLinePhone);
    $this->assertNotEmpty($data->fixedLineMeta);
    $this->assertEmpty($data->mobilePhone);
    $this->assertEmpty($data->mobileMeta);
  }

  /** @return void */
  public function testValidatePhoneNumbersValidInterchanged(): void
  {
    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'fixed_line_phone')] = self::MOBILE_NUMBER['input'];
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(1, count($data->messages));
    $this->assertEmpty($data->fixedLinePhone);
    $this->assertEmpty($data->fixedLineMeta);
    $this->assertEquals(self::MOBILE_NUMBER['output'], $data->mobilePhone);
    $this->assertNotEmpty($data->mobileMeta);

    $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'mobile_phone')] = self::FIXED_LINE_NUMBER['input'];
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(1, count($data->messages));
    $this->assertEquals(self::FIXED_LINE_NUMBER['output'], $data->fixedLinePhone);
    $this->assertNotEmpty($data->fixedLineMeta);
    $this->assertEquals(self::MOBILE_NUMBER['output'], $data->mobilePhone);
    $this->assertNotEmpty($data->mobileMeta);

    unset($this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . 'fixed_line_phone')]);
    $response = $this->controller->validate(Controller\EnumMusicianValidationTopic::PHONE);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\PhoneNumberValidationResponse::class, $data);
    /** @var DTO\EmailValidationResponse $data */
    $this->assertEquals(1, count($data->messages));
    $this->assertEquals(self::FIXED_LINE_NUMBER['output'], $data->fixedLinePhone);
    $this->assertNotEmpty($data->fixedLineMeta);
    $this->assertEmpty($data->mobilePhone);
    $this->assertEmpty($data->mobileMeta);
  }

  private const AUTOCOMPLETE_DATA = [
    'country' => 'COUNTRY',
    'city' => 'CITY',
    'street' => 'STREET',
    'postal_code' => 'ZIP',
  ];

  private const AUTOCOMPLETED_STREETS = ['öäü', 'üblub', 'äblah'];

  /** @return void */
  public function testAutocompleteStreet(): void
  {
    $this->geoCodingService
      ->expects($this->once())
      ->method('autoCompleteStreet')
      ->willReturn(self::AUTOCOMPLETED_STREETS);
    foreach (self::AUTOCOMPLETE_DATA as $key => $value) {
      $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . $key)] = $value;
    }
    $response = $this->controller->validate(
      topic: Controller\EnumMusicianValidationTopic::AUTOCOMPLETE,
      subTopic: Controller\EnumMusicianValidationSubTopic::AUTOCOMPLETE_STREET,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\AutocompleteStreetResponse::class, $data);
    /** @var DTO\AutocompleteStreetResponse $data */
    $this->assertEqualsCanonicalizing(self::AUTOCOMPLETED_STREETS, $data->streets);
  }

  private const AUTOCOMPLETED_PLACES = [
    [
      'Name' => 'ÜNAME1',
      'PostalCode' => 'POSTALCODE1',
      'Country' => 'ÜCOUNTRY1',
    ],
    [
      'Name' => 'ÄNAME2',
      'PostalCode' => 'POSTALCODE2',
      'Country' => 'ÄCOUNTRY2',
    ],
  ];

  /** @return void */
  public function testAutocompletePlace(): void
  {
    // Just one brief test, this will not cover all code in the controller class ...
    $this->geoCodingService
      ->expects($this->atLeastOnce())
      ->method('cachedLocations')
      ->willReturn(self::AUTOCOMPLETED_PLACES);
    foreach (self::AUTOCOMPLETE_DATA as $key => $value) {
      $this->postData[$this->pme->cgiDataName(self::DATA_PREFIX . $key)] = $value;
    }
    $response = $this->controller->validate(
      topic: Controller\EnumMusicianValidationTopic::AUTOCOMPLETE,
      subTopic: Controller\EnumMusicianValidationSubTopic::AUTOCOMPLETE_PLACE,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    /** @var Http\JSONResponse $response */
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\AutocompletePlaceResponse::class, $data);
    /** @var DTO\AutocompletePlaceResponse $data */
    $this->assertEqualsCanonicalizing(array_map(fn($item) => $item['Name'], self::AUTOCOMPLETED_PLACES), $data->cities);
    $this->assertEqualsCanonicalizing(array_map(fn($item) => $item['Country'], self::AUTOCOMPLETED_PLACES), $data->countries);
    $this->assertEqualsCanonicalizing(array_map(fn($item) => $item['PostalCode'], self::AUTOCOMPLETED_PLACES), $data->postalCodes);
  }
}
