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

use OC\Authentication\Token\IProvider;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\MountAuthenticationService;
use OCA\Richdocuments\Db\WopiMapper;
use OCA\Richdocuments\Middleware\WOPIMiddleware;
use OCP\Authentication\Token\IToken;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MountTestWopi
{
  public const TOKEN_TYPE_USER = 0;

  public function __construct(
    private int $type = self::TOKEN_TYPE_USER,
    private string $editor = 'alice',
    private string $fileUser = 'alice',
  ) {
  }

  public function getTokenType(): int { return $this->type; }
  public function getEditorUid(): string { return $this->editor; }
  public function getUserForFileAccess(): string { return $this->fileUser; }
}

class MountTestWopiMapper
{
  public function getWopiForToken(string $token): MountTestWopi
  {
    return new MountTestWopi();
  }
}

class MountTestWopiMiddleware
{
  public function isWOPIRequest(): bool { return true; }
}

#[CoversClass(MountAuthenticationService::class)]
#[AllowMockObjectsWithoutExpectations]
class MountAuthenticationServiceTest extends TestCase
{
  private IUserSession $session;
  private IRequest $request;
  private EncryptionService $encryption;
  private IProvider $tokens;
  private ContainerInterface $container;
  private LoggerInterface $logger;
  private IUser $user;
  private MountTestWopiMapper $mapper;
  private MountTestWopiMiddleware $middleware;
  private MountAuthenticationService $service;

  protected function setUp(): void
  {
    $this->session = $this->createMock(IUserSession::class);
    $this->request = $this->createMock(IRequest::class);
    $this->encryption = $this->createMock(EncryptionService::class);
    $this->tokens = $this->createMock(IProvider::class);
    $this->container = $this->createMock(ContainerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->user = $this->createMock(IUser::class);
    $this->user->method('getUID')->willReturn('alice');
    $this->mapper = $this->createMock(MountTestWopiMapper::class);
    $this->middleware = $this->createMock(MountTestWopiMiddleware::class);
    $this->container->method('get')->willReturnCallback(fn(string $id) => match ($id) {
      WOPIMiddleware::class => $this->middleware,
      WopiMapper::class => $this->mapper,
    });
    $this->service = new MountAuthenticationService(
      $this->session,
      $this->request,
      $this->encryption,
      $this->tokens,
      $this->container,
      $this->logger,
    );
  }

  public function testOrdinaryRequestsRetainSessionCheck(): void
  {
    $this->request->method('getRawPathInfo')->willReturn('/remote.php/dav/files/alice');
    $this->session->method('isLoggedIn')->willReturn(true, false);
    $this->container->expects($this->never())->method('get');
    self::assertTrue($this->service->authenticate($this->user));
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testVolatileWopiUserIsLoggedInAndEncryptionBound(): void
  {
    $this->wopiRequest();
    $this->session->method('getUser')->willReturn($this->user);
    $this->session->method('isLoggedIn')->willReturn(true);
    $bound = false;
    $this->encryption->method('getUserId')->willReturnCallback(function() use (&$bound) {
      return $bound ? 'alice' : null;
    });
    $this->encryption->expects($this->once())->method('bind')->with('alice', 'password')
      ->willReturnCallback(function() use (&$bound): void { $bound = true; });
    $token = $this->createMock(IToken::class);
    $token->method('getUID')->willReturn('wopi-token');
    $token->method('getLoginName')->willReturn('alice');
    $this->tokens->expects($this->once())->method('getToken')->with('alice@wopi-token')->willReturn($token);
    $this->tokens->expects($this->once())->method('getPassword')->with($token, 'alice@wopi-token')->willReturn('password');
    $this->session->expects($this->once())->method('login')->with('alice', 'password')->willReturn(true);
    self::assertTrue($this->service->authenticate($this->user));
  }

  public function testAlreadyBoundWopiUserDoesNotLogInAgain(): void
  {
    $this->wopiRequest();
    $this->session->method('getUser')->willReturn($this->user);
    $this->session->method('isLoggedIn')->willReturn(true);
    $this->encryption->method('getUserId')->willReturn('alice');
    $this->session->expects($this->never())->method('login');
    $this->tokens->expects($this->never())->method('getToken');
    self::assertTrue($this->service->authenticate($this->user));
  }

  public function testUnvalidatedRequestCannotRecoverCredentials(): void
  {
    $this->request->method('getRawPathInfo')->willReturn('/apps/richdocuments/wopi/files/1');
    $this->middleware->method('isWOPIRequest')->willReturn(false);
    $this->mapper->expects($this->never())->method('getWopiForToken');
    $this->tokens->expects($this->never())->method('getToken');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testDifferentWopiIdentitiesAndGuestAreRejected(): void
  {
    $this->wopiRequest(
      new MountTestWopi(editor: 'bob'),
      new MountTestWopi(fileUser: 'bob'),
      new MountTestWopi(type: 1),
    );
    $this->tokens->expects($this->never())->method('getToken');
    self::assertFalse($this->service->authenticate($this->user));
    self::assertFalse($this->service->authenticate($this->user));
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testMismatchedStoredTokenIsRejected(): void
  {
    $this->wopiRequest();
    $token = $this->createMock(IToken::class);
    $token->method('getUID')->willReturn('wopi-token');
    $token->method('getLoginName')->willReturn('bob');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->expects($this->never())->method('getPassword');
    $this->session->expects($this->never())->method('login');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testDifferentActiveUserCannotBeReplaced(): void
  {
    $this->wopiRequest();
    $other = $this->createStub(IUser::class);
    $other->method('getUID')->willReturn('bob');
    $this->session->method('getUser')->willReturn($other);
    $this->tokens->expects($this->never())->method('getToken');
    $this->session->expects($this->never())->method('login');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testEncryptionBoundToAnotherUserCannotBeReplaced(): void
  {
    $this->wopiRequest();
    $this->encryption->method('getUserId')->willReturn('bob');
    $this->tokens->expects($this->never())->method('getToken');
    $this->encryption->expects($this->never())->method('bind');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testFailedPasswordLoginDoesNotBindEncryption(): void
  {
    $this->wopiRequest();
    $token = $this->createStub(IToken::class);
    $token->method('getUID')->willReturn('wopi-token');
    $token->method('getLoginName')->willReturn('alice');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->method('getPassword')->willReturn('password');
    $this->session->method('login')->willReturn(false);
    $this->encryption->expects($this->never())->method('bind');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testExpiredWopiTokenCannotRecoverCredentials(): void
  {
    $this->request->method('getRawPathInfo')->willReturn('/apps/richdocuments/wopi/files/1');
    $this->request->method('getParam')->willReturn('wopi-token');
    $this->middleware->method('isWOPIRequest')->willReturn(true);
    $this->mapper->method('getWopiForToken')->willThrowException(new RuntimeException());
    $this->tokens->expects($this->never())->method('getToken');
    $this->session->expects($this->never())->method('login');
    self::assertFalse($this->service->authenticate($this->user));
  }

  public function testTokenErrorsDoNotLeakSecrets(): void
  {
    $this->wopiRequest();
    $this->tokens->method('getToken')->willThrowException(new RuntimeException('wopi-token password'));
    $this->logger->expects($this->once())->method('warning')
      ->with('Unable to authenticate WOPI mounts: ' . RuntimeException::class);
    $this->session->expects($this->never())->method('login');
    self::assertFalse($this->service->authenticate($this->user));
  }

  private function wopiRequest(MountTestWopi ...$wopis): void
  {
    $this->request->method('getRawPathInfo')->willReturn('/apps/richdocuments/wopi/files/1');
    $this->request->method('getParam')->willReturn('wopi-token');
    $this->middleware->method('isWOPIRequest')->willReturn(true);
    $this->mapper->method('getWopiForToken')->willReturn(...($wopis ?: [new MountTestWopi()]));
  }
}
