<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCA\CAFEVDB\Service\WopiCredentialRegistry;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(WopiCredentialRegistry::class)]
#[AllowMockObjectsWithoutExpectations]
class WopiCredentialRegistryTest extends TestCase
{
  private IDBConnection $db;
  private IQueryBuilder $query;
  private WopiCredentialRegistry $registry;

  protected function setUp(): void
  {
    $this->db = $this->createMock(IDBConnection::class);
    $this->query = $this->createMock(IQueryBuilder::class);
    foreach (['select', 'from', 'delete', 'where', 'andWhere', 'orderBy', 'addOrderBy', 'setMaxResults'] as $method) {
      $this->query->method($method)->willReturnSelf();
    }
    $this->query->method('createNamedParameter')->willReturnCallback(fn($value) => json_encode($value));
    $expressions = $this->createStub(IExpressionBuilder::class);
    foreach (['gt', 'lt', 'eq'] as $method) {
      $expressions->method($method)->willReturnCallback(fn($left, $right) => "$left $method $right");
    }
    $this->query->method('expr')->willReturn($expressions);
    $this->db->method('getQueryBuilder')->willReturn($this->query);
    $this->registry = new WopiCredentialRegistry($this->db);
  }

  public function testRemembersNewAndReusedCredentialsByTokenId(): void
  {
    $this->db->expects($this->exactly(2))->method('setValues')
      ->with('*PREFIX*cafevdb_wopi_credentials', ['token_id' => 17], ['token_uid' => 'issued-token', 'expires' => 2000]);
    $this->registry->remember(17, 'issued-token', 2000);
    $this->registry->remember(17, 'issued-token', 2000);
  }

  public function testFindsOnlyPositiveExpiredEntriesWithBoundedResults(): void
  {
    $this->query->expects($this->once())->method('from')->with('cafevdb_wopi_credentials');
    $this->query->expects($this->once())->method('where')->with('expires gt 0');
    $this->query->expects($this->once())->method('andWhere')->with('expires lt 1940');
    $this->query->expects($this->once())->method('setMaxResults')->with(1000);
    $result = $this->createMock(IResult::class);
    $rows = [['token_id' => '17', 'token_uid' => 'issued-token', 'expires' => '1900']];
    $result->method('fetchAllAssociative')->willReturn($rows);
    $result->expects($this->once())->method('closeCursor');
    $this->query->method('executeQuery')->willReturn($result);
    self::assertSame($rows, $this->registry->findExpired(1940, 1000));
  }

  public function testClosesCursorOnReadFailure(): void
  {
    $result = $this->createMock(IResult::class);
    $result->method('fetchAllAssociative')->willThrowException(new RuntimeException());
    $result->expects($this->once())->method('closeCursor');
    $this->query->method('executeQuery')->willReturn($result);
    $this->expectException(RuntimeException::class);
    $this->registry->findExpired(1940, 1000);
  }

  public function testForgetsOnlyTheProcessedRegistryEntry(): void
  {
    $this->query->expects($this->once())->method('delete')->with('cafevdb_wopi_credentials');
    $this->query->expects($this->once())->method('where')->with('token_id eq 17');
    $conditions = [];
    $this->query->expects($this->exactly(2))->method('andWhere')
      ->willReturnCallback(function($condition) use (&$conditions) {
        $conditions[] = $condition;
        return $this->query;
      });
    $this->query->expects($this->once())->method('executeStatement');
    $this->registry->forget(17, 'issued-token', 1900);
    self::assertSame(['token_uid eq "issued-token"', 'expires eq 1900'], $conditions);
  }
}
