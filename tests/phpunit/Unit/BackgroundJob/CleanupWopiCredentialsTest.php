<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\BackgroundJob;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\PublicKeyToken;
use OC\Authentication\Token\PublicKeyTokenMapper;
use OCA\CAFEVDB\BackgroundJob\CleanupWopiCredentials;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\Token\IToken;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(CleanupWopiCredentials::class)]
#[AllowMockObjectsWithoutExpectations]
class CleanupWopiCredentialsTest extends TestCase
{
  private PublicKeyTokenMapper $mapper;
  private IProvider $provider;
  private IQueryBuilder $query;
  private IResult $result;
  private CleanupWopiCredentials $job;

  protected function setUp(): void
  {
    $time = $this->createStub(ITimeFactory::class);
    $time->method('getTime')->willReturn(2000);
    $this->mapper = $this->createMock(PublicKeyTokenMapper::class);
    $this->provider = $this->createMock(IProvider::class);
    $this->result = $this->createMock(IResult::class);
    $this->query = $this->createMock(IQueryBuilder::class);
    foreach (['select', 'from', 'where', 'andWhere', 'orderBy', 'setMaxResults'] as $method) {
      $this->query->method($method)->willReturnSelf();
    }
    $this->query->method('createNamedParameter')->willReturnCallback(fn($value) => json_encode($value));
    $expressions = $this->createStub(IExpressionBuilder::class);
    foreach (['gt', 'lt', 'eq'] as $method) {
      $expressions->method($method)->willReturnCallback(fn($left, $right) => "$left $method $right");
    }
    $this->query->method('expr')->willReturn($expressions);
    $functions = $this->createMock(IFunctionBuilder::class);
    $concat = $this->createStub(IQueryFunction::class);
    $concat->method('__toString')->willReturn('credential-name');
    $functions->expects($this->once())->method('concat')->with('"wopi_token_"', 'uid')->willReturn($concat);
    $this->query->method('func')->willReturn($functions);
    $this->query->method('executeQuery')->willReturn($this->result);
    $db = $this->createStub(IDBConnection::class);
    $db->method('getQueryBuilder')->willReturn($this->query);
    $this->job = new CleanupWopiCredentials($time, $db, $this->mapper, $this->provider);
  }

  public function testSelectsBoundedExpiredCredentialBatch(): void
  {
    $this->query->expects($this->once())->method('from')->with('authtoken');
    $this->query->expects($this->once())->method('where')->with('expires gt 0');
    $conditions = [];
    $this->query->expects($this->exactly(4))->method('andWhere')
      ->willReturnCallback(function($condition) use (&$conditions) {
        $conditions[] = $condition;
        return $this->query;
      });
    $this->query->expects($this->once())->method('setMaxResults')->with(1000);
    $this->result->method('fetchFirstColumn')->willReturn(['17']);
    $this->result->expects($this->once())->method('closeCursor');
    $token = $this->token(17, 1939);
    $this->mapper->expects($this->once())->method('getTokenById')->with(17)->willReturn($token);
    $this->provider->expects($this->once())->method('invalidateTokenById')->with('issued-token', 17);
    $this->runJob();
    self::assertContains('expires lt 1940', $conditions);
    self::assertContains('name eq credential-name', $conditions);
    self::assertContains('type eq ' . IToken::TEMPORARY_TOKEN, $conditions);
    self::assertContains('version eq ' . PublicKeyToken::VERSION, $conditions);
  }

  public function testRechecksExpiryAndIdentityBeforeDeleting(): void
  {
    $tokens = [
      $this->token(1, null),
      $this->token(2, 0),
      $this->token(3, -1),
      $this->token(4, 1940),
      $this->token(5, 2100),
      $this->token(6, 1900, 'ordinary app password'),
      $this->token(7, 1900, 'wopi_token_someone-else'),
      $this->token(8, 1900, type: IToken::PERMANENT_TOKEN),
    ];
    $this->result->method('fetchFirstColumn')->willReturn(range(1, 8));
    $this->mapper->method('getTokenById')->willReturnCallback(fn(int $id) => $tokens[$id - 1]);
    $this->provider->expects($this->never())->method('invalidateTokenById');
    $this->runJob();
  }

  public function testConcurrentRemovalDoesNotStopRemainingCleanup(): void
  {
    $this->result->method('fetchFirstColumn')->willReturn([1, 2, 3]);
    $this->mapper->method('getTokenById')->willReturnCallback(function(int $id) {
      if ($id === 1) {
        throw new DoesNotExistException('already removed');
      }
      return $this->token($id, 1900);
    });
    $deleted = [];
    $this->provider->expects($this->exactly(2))->method('invalidateTokenById')
      ->willReturnCallback(function(string $uid, int $id) use (&$deleted) {
        if ($id === 2) {
          throw new DoesNotExistException('removed after lookup');
        }
        $deleted[] = $id;
      });
    $this->runJob();
    self::assertSame([3], $deleted);
  }

  public function testOperationalFailuresAreReportedWithoutCredentials(): void
  {
    $this->result->method('fetchFirstColumn')->willReturn([1]);
    $this->mapper->method('getTokenById')->willThrowException(new RuntimeException('secret-token'));
    try {
      $this->runJob();
      self::fail('Cleanup failure was swallowed.');
    } catch (RuntimeException $exception) {
      self::assertSame('WOPI credential cleanup failed: ' . RuntimeException::class, $exception->getMessage());
      self::assertNull($exception->getPrevious());
    }
  }

  private function token(
    int $id,
    ?int $expiry,
    string $name = 'wopi_token_issued-token',
    int $type = IToken::TEMPORARY_TOKEN,
  ): PublicKeyToken {
    $token = new PublicKeyToken();
    $token->setId($id);
    $token->setUid('issued-token');
    $token->setName($name);
    $token->setType($type);
    $token->setExpires($expiry);
    return $token;
  }

  private function runJob(): void
  {
    (new ReflectionMethod($this->job, 'run'))->invoke($this->job, null);
  }
}
