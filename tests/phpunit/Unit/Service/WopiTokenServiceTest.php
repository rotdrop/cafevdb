<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\PublicKeyToken;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\WopiTokenService;
use OCA\CAFEVDB\Service\WopiCredentialRegistry;
use OCA\Richdocuments\Db\WopiMapper;
use OCP\Authentication\Exceptions\InvalidTokenException;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class IssuedWopiToken
{
  public const TOKEN_TYPE_USER = 0;
  public int $type = self::TOKEN_TYPE_USER;
  public string $editor = 'alice';
  public string $fileUser = 'alice';
  public function getTokenType(): int { return $this->type; }
  public function getEditorUid(): string { return $this->editor; }
  public function getUserForFileAccess(): string { return $this->fileUser; }
  public function getFileid(): int { return 42; }
  public function getExpiry(): int { return 2000000000; }
}

class IssuedWopiMapper
{
  public function getWopiForToken(string $token): IssuedWopiToken { return new IssuedWopiToken(); }
}

#[CoversClass(WopiTokenService::class)]
#[AllowMockObjectsWithoutExpectations]
class WopiTokenServiceTest extends TestCase
{
  private IProvider $tokens;
  private IStore $store;
  private AuthorizationService $authorization;
  private File $file;
  private IMountPoint $mount;
  private IssuedWopiToken $wopi;
  private WopiTokenService $service;
  private ContainerInterface $container;
  private WopiCredentialRegistry $registry;

  protected function setUp(): void
  {
    $user = $this->createStub(IUser::class);
    $user->method('getUID')->willReturn('alice');
    $session = $this->createStub(IUserSession::class);
    $session->method('getUser')->willReturn($user);
    $session->method('isLoggedIn')->willReturn(true);
    $this->authorization = $this->createMock(AuthorizationService::class);
    $this->tokens = $this->createMock(IProvider::class);
    $this->store = $this->createMock(IStore::class);
    $this->mount = $this->createStub(IMountPoint::class);
    $this->file = $this->createStub(File::class);
    $this->file->method('getMountPoint')->willReturn($this->mount);
    $folder = $this->createStub(Folder::class);
    $folder->method('getFirstNodeById')->willReturn($this->file);
    $root = $this->createStub(IRootFolder::class);
    $root->method('getUserFolder')->willReturn($folder);
    $this->wopi = new IssuedWopiToken();
    $mapper = $this->createStub(IssuedWopiMapper::class);
    $mapper->method('getWopiForToken')->willReturn($this->wopi);
    $this->container = $this->createMock(ContainerInterface::class);
    $this->container->method('get')->willReturnCallback(fn(string $id) => match ($id) {
      WopiMapper::class => $mapper,
    });
    $this->registry = $this->createMock(WopiCredentialRegistry::class);
    $this->service = new WopiTokenService(
      $session, $this->authorization, $this->store, $this->tokens, $root, $this->container, $this->registry,
    );
  }

  public function testAuthorizationPrecedesWopiLookup(): void
  {
    $this->authorization->expects($this->once())->method('authorized')
      ->with('alice', AuthorizationService::PERMISSION_FILESYSTEM)->willReturn(false);
    $this->container->expects($this->never())->method('get');
    $this->store->expects($this->never())->method('getLoginCredentials');
    $this->registry->expects($this->never())->method('remember');
    $this->service->provideCredentials('issued-token');
  }

  public function testOrdinaryMountDoesNotStorePasswords(): void
  {
    $this->authorization->method('authorized')->willReturn(true);
    $this->mount->method('getOption')->willReturn(false);
    $this->store->expects($this->never())->method('getLoginCredentials');
    $this->tokens->expects($this->never())->method('generateToken');
    $this->service->provideCredentials('issued-token');
  }

  public function testGuestAndMismatchedIdentitiesAreIgnored(): void
  {
    $this->authorization->method('authorized')->willReturn(true);
    $this->store->expects($this->never())->method('getLoginCredentials');
    $this->wopi->type = 1;
    $this->service->provideCredentials('issued-token');
    $this->wopi->type = 0;
    $this->wopi->editor = 'bob';
    $this->service->provideCredentials('issued-token');
    $this->wopi->editor = 'alice';
    $this->wopi->fileUser = 'bob';
    $this->service->provideCredentials('issued-token');
  }

  public function testCreatesCompatibleExpiringCredential(): void
  {
    $this->eligible();
    $this->tokens->method('getToken')->willThrowException(new InvalidTokenException());
    $token = new PublicKeyToken();
    $token->setId(17);
    $token->setUid('issued-token');
    $token->setLoginName('alice');
    $this->tokens->expects($this->once())->method('generateToken')
      ->with('alice@issued-token', 'issued-token', 'alice', 'password', 'wopi_token_issued-token')->willReturn($token);
    $this->tokens->expects($this->once())->method('getPassword')->with($token, 'alice@issued-token')->willReturn('password');
    $this->tokens->expects($this->never())->method('setPassword');
    $this->tokens->expects($this->once())->method('updateToken')->with($token);
    $this->registry->expects($this->once())->method('remember')->with(17, 'issued-token', 2000000000);
    $this->service->provideCredentials('issued-token');
    self::assertSame(2000000000, $token->getExpires());
    self::assertSame(2000000000, $token->getLastActivity());
  }

  public function testReusesExistingCredential(): void
  {
    $this->eligible();
    $token = new PublicKeyToken();
    $token->setId(18);
    $token->setUid('issued-token');
    $token->setLoginName('alice');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->expects($this->once())->method('getPassword')->with($token, 'alice@issued-token')->willReturn('password');
    $this->tokens->expects($this->never())->method('setPassword');
    $this->tokens->expects($this->never())->method('generateToken');
    $this->registry->expects($this->once())->method('remember')->with(18, 'issued-token', 2000000000);
    $this->service->provideCredentials('issued-token');
  }

  public function testPasswordReadFailureDoesNotGenerateAnotherToken(): void
  {
    $this->eligible();
    $token = new PublicKeyToken();
    $token->setUid('issued-token');
    $token->setLoginName('alice');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->method('getPassword')->willThrowException(new InvalidTokenException());
    $this->tokens->expects($this->never())->method('generateToken');
    $this->expectException(InvalidTokenException::class);
    $this->service->provideCredentials('issued-token');
  }

  public function testExistingCredentialIdentityMismatchFailsExplicitly(): void
  {
    $this->eligible();
    $token = new PublicKeyToken();
    $token->setUid('another-token');
    $token->setLoginName('alice');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->expects($this->never())->method('getPassword');
    $this->tokens->expects($this->never())->method('setPassword');
    $this->tokens->expects($this->never())->method('generateToken');
    $this->expectException(RuntimeException::class);
    $this->service->provideCredentials('issued-token');
  }

  public function testExistingCredentialPasswordMismatchFailsExplicitly(): void
  {
    $this->eligible();
    $token = new PublicKeyToken();
    $token->setUid('issued-token');
    $token->setLoginName('alice');
    $this->tokens->method('getToken')->willReturn($token);
    $this->tokens->method('getPassword')->willReturn('different-password');
    $this->tokens->expects($this->never())->method('setPassword');
    $this->tokens->expects($this->never())->method('updateToken');
    $this->expectException(RuntimeException::class);
    $this->service->provideCredentials('issued-token');
  }

  public function testMissingPasswordFailsExplicitly(): void
  {
    $this->eligible(password: '');
    $this->tokens->expects($this->never())->method('generateToken');
    $this->expectException(RuntimeException::class);
    $this->service->provideCredentials('issued-token');
  }

  public function testRegistryFailureRevokesNewCredential(): void
  {
    $this->eligible();
    $this->tokens->method('getToken')->willThrowException(new InvalidTokenException());
    $token = new PublicKeyToken();
    $token->setId(17);
    $token->setUid('issued-token');
    $token->setLoginName('alice');
    $this->tokens->method('generateToken')->willReturn($token);
    $this->tokens->method('getPassword')->willReturn('password');
    $this->registry->method('remember')->willThrowException(new RuntimeException('Registry unavailable.'));
    $this->tokens->expects($this->once())->method('invalidateTokenById')->with('issued-token', 17);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Registry unavailable.');
    $this->service->provideCredentials('issued-token');
  }

  private function eligible(string $password = 'password'): void
  {
    $this->authorization->method('authorized')->willReturn(true);
    $this->mount->method('getOption')->willReturn(true);
    $credentials = $this->createStub(ICredentials::class);
    $credentials->method('getUID')->willReturn('alice');
    $credentials->method('getPassword')->willReturn($password);
    $this->store->method('getLoginCredentials')->willReturn($credentials);
  }
}
