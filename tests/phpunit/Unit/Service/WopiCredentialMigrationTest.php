<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use OC\DB\Schema\Table;
use OCA\CAFEVDB\Migration\Version100201Date20261114001000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version100201Date20261114001000::class)]
class WopiCredentialMigrationTest extends TestCase
{
  public function testCreatesUsableRegistrySchemaAndLeavesExistingTableAlone(): void
  {
    $schema = new Schema();
    $wrapper = $this->createMock(ISchemaWrapper::class);
    $wrapper->method('hasTable')->willReturnOnConsecutiveCalls(false, true);
    $wrapper->expects($this->once())->method('createTable')->with('cafevdb_wopi_credentials')
      ->willReturnCallback(fn(string $name) => new Table($schema->createTable($name)));
    $migration = new Version100201Date20261114001000();
    $output = $this->createStub(IOutput::class);
    self::assertSame($wrapper, $migration->changeSchema($output, fn() => $wrapper, []));
    self::assertNull($migration->changeSchema($output, fn() => $wrapper, []));

    $db = new PDO('sqlite::memory:');
    foreach ($schema->toSql(new SqlitePlatform()) as $sql) {
      $db->exec($sql);
    }
    $db->exec("INSERT INTO cafevdb_wopi_credentials (token_id, token_uid, expires) VALUES (17, 'issued-token', 2000)");
    self::assertSame(
      ['token_id' => 17, 'token_uid' => 'issued-token', 'expires' => 2000],
      $db->query('SELECT * FROM cafevdb_wopi_credentials')->fetch(PDO::FETCH_ASSOC),
    );
    self::assertNotNull($schema->getTable('cafevdb_wopi_credentials')->getPrimaryKey());
    self::assertTrue($schema->getTable('cafevdb_wopi_credentials')->hasIndex('cafevdb_wopi_expiry'));
  }
}
