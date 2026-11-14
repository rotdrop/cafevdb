<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\BackgroundJob;

use OC\Authentication\Token\IProvider;
use OCA\CAFEVDB\BackgroundJob\CleanupWopiCredentials;
use OCA\CAFEVDB\Service\WopiCredentialRegistry;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\Token\IToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(CleanupWopiCredentials::class)]
#[AllowMockObjectsWithoutExpectations]
class CleanupWopiCredentialsTest extends TestCase
{
  private WopiCredentialRegistry $registry;
  private IProvider $provider;
  private CleanupWopiCredentials $job;

  protected function setUp(): void
  {
    $time = $this->createStub(ITimeFactory::class);
    $time->method('getTime')->willReturn(2000);
    $this->registry = $this->createMock(WopiCredentialRegistry::class);
    $this->provider = $this->createMock(IProvider::class);
    $this->job = new CleanupWopiCredentials($time, $this->registry, $this->provider);
  }

  public function testInvalidatesOnlyRegisteredCredentialAndForgetsItAfterwards(): void
  {
    $this->registry->expects($this->once())->method('findExpired')->with(1940, 1000)
      ->willReturn([['token_id' => '17', 'token_uid' => 'issued-token', 'expires' => '1939']]);
    $this->provider->expects($this->once())->method('getTokenByUser')->with('issued-token')
      ->willReturn([$this->token(16), $this->token(17)]);
    $deleted = false;
    $this->provider->expects($this->once())->method('invalidateTokenById')->with('issued-token', 17)
      ->willReturnCallback(function() use (&$deleted) { $deleted = true; });
    $this->registry->expects($this->once())->method('forget')->with(17, 'issued-token', 1939)
      ->willReturnCallback(function() use (&$deleted) { self::assertTrue($deleted); });
    $this->runJob();
  }

  public function testRechecksRegistryExpiryBeforeDeleting(): void
  {
    $entries = [];
    foreach ([0, -1, 1940, 2100] as $expiry) {
      $entries[] = ['token_id' => 17, 'token_uid' => 'issued-token', 'expires' => $expiry];
    }
    $this->registry->method('findExpired')->willReturn($entries);
    $this->provider->expects($this->never())->method('getTokenByUser');
    $this->registry->expects($this->never())->method('forget');
    $this->runJob();
  }

  public function testMissingCredentialsAndConcurrentRemovalAreForgotten(): void
  {
    $entries = [
      ['token_id' => 17, 'token_uid' => 'issued-token', 'expires' => 1900],
      ['token_id' => 18, 'token_uid' => 'issued-token', 'expires' => 1900],
    ];
    $this->registry->method('findExpired')->willReturn($entries);
    $this->provider->method('getTokenByUser')->willReturnOnConsecutiveCalls([], [$this->token(18)]);
    $this->provider->expects($this->once())->method('invalidateTokenById')->with('issued-token', 18)
      ->willThrowException(new DoesNotExistException('already removed'));
    $this->registry->expects($this->exactly(2))->method('forget');
    $this->runJob();
  }

  public function testMismatchedCredentialsAreNotDeletedOrForgotten(): void
  {
    $this->registry->method('findExpired')
      ->willReturn([['token_id' => 17, 'token_uid' => 'issued-token', 'expires' => 1900]]);
    $this->provider->method('getTokenByUser')->willReturn([$this->token(17, 'ordinary app password')]);
    $this->provider->expects($this->never())->method('invalidateTokenById');
    $this->registry->expects($this->never())->method('forget');
    $this->expectException(RuntimeException::class);
    $this->runJob();
  }

  public function testOperationalFailuresRemainRetryableAndDoNotExposeCredentials(): void
  {
    $this->registry->method('findExpired')
      ->willReturn([['token_id' => 17, 'token_uid' => 'issued-token', 'expires' => 1900]]);
    $this->provider->method('getTokenByUser')->willReturn([$this->token(17)]);
    $this->provider->method('invalidateTokenById')->willThrowException(new RuntimeException('secret-token'));
    $this->registry->expects($this->never())->method('forget');
    try {
      $this->runJob();
      self::fail('Cleanup failure was swallowed.');
    } catch (RuntimeException $exception) {
      self::assertSame('WOPI credential cleanup failed: ' . RuntimeException::class, $exception->getMessage());
      self::assertNull($exception->getPrevious());
    }
  }

  private function token(int $id, string $name = 'wopi_token_issued-token'): IToken
  {
    $token = $this->createStub(IToken::class);
    $token->method('getId')->willReturn($id);
    $token->method('getUID')->willReturn('issued-token');
    $token->method('getName')->willReturn($name);
    $token->method('getType')->willReturn(IToken::TEMPORARY_TOKEN);
    return $token;
  }

  private function runJob(): void
  {
    (new ReflectionMethod($this->job, 'run'))->invoke($this->job, null);
  }
}
