<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

use Doctrine\DBAL\Types\Types;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version060000Date20201022230000 extends SimpleMigrationStep
{
  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   * @return null|ISchemaWrapper
   */
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {

    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();
    $table = $schema->getTable('cafevdb_progress_status');
    $table->dropColumn('tag');
    $table->dropIndex('id_user');
    $table->dropColumn('user');
    $table->addColumn('user_id', Types::STRING, [
      'notnull' => true,
      'length' => 64,
    ]);
    $table->dropColumn('id');
    $table->addColumn('id', Types::BIGINT, [
      'autoincrement' => true,
      'notnull' => true,
      'length' => 11,
      'unsigned' => true,
    ]);
    $table->setPrimaryKey(['id']);
    $table->addColumn('last_modified', Types::BIGINT, [
      'notnull' => true,
      'length' => 11,
      'unsigned' => true,
    ]);
    $table->changeColumn('current', [
      'notnull' => false,
      'length' => 11,
      'unsigned' => true,
    ]);
    $table->changeColumn('target', [
      'notnull' => false,
      'length' => 11,
      'unsigned' => true,
    ]);
    return $schema;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}
