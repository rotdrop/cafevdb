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

use OC\CapabilitiesManager;

use OCP\App\IAppManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\IShare;

use OCA\CAFEVDB\Database\Cloud\Entities\TOSException;
use OCA\CAFEVDB\Database\Cloud\Mapper\TOSExceptionMapper;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ByPassToSService . */
#[Attributes\CoversClass(Service\ByPassToSService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Cloud\Entities\TOSException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DomainNameService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class ByPassToSServiceTest extends TestCase
{
  private const SHARE_TOKEN = 'Pj9B3pKjNHyYpRW';

  private const ALLOWED_IPS = [
    '127.0.0.1',
    '123.123.123.123/16',
    'fe80::feb0:deff:fe6e:71/64',
  ];

  private MockProvider $mockProvider;

  private Service\ByPassToSService $service;

  private ISession $phpSession;

  private IRequest $request;

  private IAppManager $appManager;

  private TOSExceptionMapper $mapper;

  private array $exceptions = [];

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $appContainer = $this->mockProvider->getAppContainer();
    $this->appManager = $this->getMockBuilder(IAppManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    // $appManager->method('isEnabledForAnyone')->with('terms_of_service')->willReturn(true);

    $this->request = $this->getMockBuilder(IRequest::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->phpSession = $this->mockProvider->getSession();

    $exception = new TOSException();
    $exception->setIpRanges(implode(',', self::ALLOWED_IPS));
    $exception->setShareToken(self::SHARE_TOKEN);
    $this->mapper = $this->getMockBuilder(TOSExceptionMapper::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->mapper->method('getToSExceptions')->willReturnCallback(
      fn(string $token) => $token == self::SHARE_TOKEN ? [$exception] : [],
    );
    $this->mapper->method('addToSException')->willReturnCallback(
      function(string $token, array $ips, bool $exclusive) {
        $this->exceptions[] = compact('token', 'ips', 'exclusive');
        return new TOSException(); // but do not care
      }
    );

    $this->service = new Service\ByPassToSService(
      domainNameService: $appContainer->get(Service\DomainNameService::class),
      appManager: $this->appManager,
      request: $this->request,
      phpSession: $this->mockProvider->getSession(),
      mapper: $this->mapper,
      logger: $this->mockProvider->getLoggerInterface(),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testSetup(): void
  {
    $this->mapper->expects($this->never())->method('getToSExceptions');
    $this->appManager->expects($this->never())->method('isEnabledForAnyOne');
    $this->request->expects($this->never())->method('getRemoteAddress');
    // $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testAddExceptionForHostname(): void
  {
    $this->appManager->expects($this->never())->method('isEnabledForAnyOne');
    $this->request->expects($this->never())->method('getRemoteAddress');
    $this->mapper->expects($this->once())->method('addToSException');
    // just use localhost, we could also mock the DomainNameService, or course
    $share = $this->getMockBuilder(IShare::class)
      ->disableOriginalConstructor()
      ->getMock();
    $share->expects($this->once())->method('getToken')->willReturn(self::SHARE_TOKEN);
    $this->service->addExceptionForHostname($share, 'localhost', exclusive: false);
    $this->assertEquals(['127.0.0.1', '::1'], $this->exceptions[0]['ips']);
  }

  /**
   * A test which just should take the "allowed" path and finally place the
   * term_uuid in the PHP session.
   *
   * @return void
   */
  public function testCheckForTosByPass(): void
  {
    $this->request->expects($this->atLeastOnce())->method('getRemoteAddress')->willReturn('127.0.0.1');
    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->atLeastOnce())->method('isEnabledForAnyOne')
      ->willReturn(true);
    $this->mapper->expects($this->atLeastOnce())->method('getToSExceptions');

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([
      'terms_of_service' => [
        'term_uuid' => 'UUID',
      ],
    ]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertEquals('UUID', $sessionValue);
  }

  /**
   * A test which just should take the "allowed" path and finally place the
   * term_uuid in the PHP session.
   *
   * @return void
   */
  public function testCheckForTosByPassSubnetV4Match(): void
  {
    $this->request->expects($this->atLeastOnce())->method('getRemoteAddress')->willReturn('123.123.0.1');
    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->atLeastOnce())->method('isEnabledForAnyOne')
      ->willReturn(true);
    $this->mapper->expects($this->atLeastOnce())->method('getToSExceptions');

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([
      'terms_of_service' => [
        'term_uuid' => 'UUID',
      ],
    ]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertEquals('UUID', $sessionValue);
  }

  /**
   * A test which just should take the "allowed" path and finally place the
   * term_uuid in the PHP session.
   *
   * @return void
   */
  public function testCheckForTosByPassSubnetV6Match(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->atLeastOnce())->method('getRemoteAddress')->willReturn('fe80::1');
    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->atLeastOnce())->method('isEnabledForAnyOne')
      ->willReturn(true);
    $this->mapper->expects($this->atLeastOnce())->method('getToSExceptions');

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([
      'terms_of_service' => [
        'term_uuid' => 'UUID',
      ],
    ]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertEquals('UUID', $sessionValue);
  }

  /**
   * @return void
   */
  public function testCheckForTosByPassIllegalScript(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->never())->method('getRemoteAddress');

    $this->request->expects($this->once())->method('getScriptName')->willReturn('BLAH');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->never())->method('getMethod');

    $this->appManager->expects($this->never())->method('isEnabledForAnyOne');
    $this->mapper->expects($this->never())->method('getToSExceptions');

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * @return void
   */
  public function testCheckForTosByPassIllegalPath(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->never())->method('getRemoteAddress');

    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('BLAH');
    $this->request->expects($this->never())->method('getMethod');

    $this->appManager->expects($this->never())->method('isEnabledForAnyOne');
    $this->mapper->expects($this->never())->method('getToSExceptions');

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * @return void
   */
  public function testCheckForTosByPassIllegalMethod(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->never())->method('getRemoteAddress');

    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('HUTZLI');

    $this->appManager->expects($this->never())->method('isEnabledForAnyOne');
    $this->mapper->expects($this->never())->method('getToSExceptions');

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * @return void
   */
  public function testCheckForTosByPassTosAppDisabled(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->never())->method('getRemoteAddress');

    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->once())->method('isEnabledForAnyOne')
      ->willReturn(false);

    $this->mapper->expects($this->never())->method('getToSExceptions');

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * @return void
   */
  public function testCheckForTosByPassNoTermUuidCapability(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->never())->method('getRemoteAddress');

    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->once())->method('isEnabledForAnyOne')
      ->willReturn(true);

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->mapper->expects($this->never())->method('getToSExceptions');

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * A test which just should take the "allowed" path and finally place the
   * term_uuid in the PHP session.
   *
   * @return void
   */
  public function testCheckForTosByPassV4SubnetMismatch(): void
  {
    $this->request->expects($this->atLeastOnce())->method('getRemoteAddress')->willReturn('123.122.0.1');
    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->atLeastOnce())->method('isEnabledForAnyOne')
      ->willReturn(true);
    $this->mapper->expects($this->atLeastOnce())->method('getToSExceptions');

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([
      'terms_of_service' => [
        'term_uuid' => 'UUID',
      ],
    ]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }

  /**
   * A test which just should take the "allowed" path and finally place the
   * term_uuid in the PHP session.
   *
   * @return void
   */
  public function testCheckForTosByPassV6SubnetMismatch(): void
  {
    // 'fe80::feb0:deff:fe6e:71/64',
    $this->request->expects($this->atLeastOnce())->method('getRemoteAddress')->willReturn('fe80:1::1');
    $this->request->expects($this->once())->method('getScriptName')->willReturn('/public.php');
    $this->request->expects($this->once())->method('getPathInfo')->willReturn('/dav/files/' . self::SHARE_TOKEN . '/TheRestDoesNotMatter');
    $this->request->expects($this->once())->method('getMethod')->willReturn('GET');

    $this->appManager->expects($this->atLeastOnce())->method('isEnabledForAnyOne')
      ->willReturn(true);
    $this->mapper->expects($this->atLeastOnce())->method('getToSExceptions');

    $capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $capabilitiesManager->expects($this->once())->method('getCapabilities')->with(true)->willReturn([
      'terms_of_service' => [
        'term_uuid' => 'UUID',
      ],
    ]);
    $this->mockProvider->registerClassInstance(CapabilitiesManager::class, $capabilitiesManager, global: true);

    $this->service->checkForToSByPass();

    // and the outcome should be that the session value is defined.
    $sessionValue = $this->phpSession->get('term_uuid');
    $this->assertNull($sessionValue);
  }
}
