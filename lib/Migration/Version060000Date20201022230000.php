<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

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
    $table->addColumn('uuid', 'binary', [
      'notnull' => true,
      'length' => 16,
      'fixed' => true,
    ]);
    //$table->dropIndex('id_user');
    //$table->setPrimaryKey(['id']);
    $table->addUniqueIndex(['uuid'], 'uuid');
    return $schema;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}
