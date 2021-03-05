<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

use Doctrine\DBAL\Types\Types;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version060000Date20201022230000 extends SimpleMigrationStep
{
  /** @var IDBConnection */
  private $connection;

  /**
   * Version1008Date20181105104826 constructor.
   *
   * @param IDBConnection $connection
   */
  public function __construct(IDBConnection $connection) {
    $this->connection = $connection;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {

    // $schema = $schemaClosure();
    // $table = $schema->getTable('cafevdb_blog');
    // $table->addColumn('inreplyto_tmp', 'integer', [
    //   'notnull' => true,
    //   'length' => 4,
    //   'default' => -1,
    // ]);
    // $query = $this->connection->getQueryBuilder();
    // $query->update('cafevdb_blog')
    //       ->set('inreplyto_tmp', 'inreplyto');
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   * @return null|ISchemaWrapper
   */
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {

    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    $table = $schema->getTable('cafevdb_blog');
    $table->addColumn('in_reply_to', 'integer', [
      'notnull' => true,
      'length' => 4,
      'default' => -1,
    ]);

    return $schema;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
    $schema = $schemaClosure();
    $table = $schema->getTable('cafevdb_blog');
    $query = $this->connection->getQueryBuilder();
    $query->update('cafevdb_blog')
          ->set('in_reply_to', 'inreplyto');
    $table->dropColumn('inreplyto');
  }
}
