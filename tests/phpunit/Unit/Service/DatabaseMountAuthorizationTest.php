<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MountAuthenticationService;
use OCA\CAFEVDB\Storage\Database\Factory;
use OCA\CAFEVDB\Storage\Database\MountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MountProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class DatabaseMountAuthorizationTest extends TestCase
{
  public function testAuthorizationPrecedesCredentialRecovery(): void
  {
    $user = $this->createStub(IUser::class);
    $user->method('getUID')->willReturn('alice');
    $config = $this->createStub(ConfigService::class);
    $config->method('getL10n')->willReturn($this->createStub(IL10N::class));
    $config->method('logger')->willReturn($this->createStub(LoggerInterface::class));
    $authorization = $this->createMock(AuthorizationService::class);
    $authorization->expects($this->once())->method('authorized')
      ->with('alice', AuthorizationService::PERMISSION_FILESYSTEM)->willReturn(false);
    $authentication = $this->createMock(MountAuthenticationService::class);
    $authentication->expects($this->never())->method('authenticate');
    $entityManager = $this->createMock(EntityManager::class);
    $entityManager->expects($this->never())->method('connected');
    $provider = new MountProvider(
      $config,
      $entityManager,
      $authorization,
      $this->createStub(Factory::class),
      $authentication,
    );
    self::assertSame([], $provider->getMountsForUser($user, $this->createStub(IStorageFactory::class)));
  }
}
