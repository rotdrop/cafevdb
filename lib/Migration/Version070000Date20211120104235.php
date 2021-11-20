<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version070000Date20211120104235 extends SimpleMigrationStep
{
  /** @var IDBConnection */
  private $connection;

  /**
   * Constructor.
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
  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
  {}

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   * @return null|ISchemaWrapper
   */
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();
    $table = $schema->getTable('cafevdb_blog');
    $column = $table->getColumn('in_reply_to');
    $column->setType(Type::getType(Types::BIGINT));
    $column->setOptions([
      'notnull' => false,
      'length' => 20,
      'default' => null,
    ]);
    return $schema;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
   * @param array $options
   */
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();
    $table = $schema->getTable('cafevdb_blog');
    /** @var IQueryBuilder $qb */
    $qb = $this->connection->getQueryBuilder();
    $qb->update('cafevdb_blog')
      ->set('in_reply_to', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
      ->where($qb->expr()->eq('in_reply_to', $qb->createNamedParameter(-1, IQueryBuilder::PARAM_INT)))
      ->execute();
  }
}
