<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version060000Date20201001210735 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
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

		if (!$schema->hasTable('cafevdb_progress_status')) {
			$table = $schema->createTable('cafevdb_progress_status');
			$table->addColumn('id', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('tag', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('current', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('target', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addUniqueIndex(['id', 'user'], 'id_user');
		}

		if (!$schema->hasTable('cafevdb_blog')) {
			$table = $schema->createTable('cafevdb_blog');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('author', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created', 'bigint', [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('editor', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('modified', 'bigint', [
				'notnull' => false,
				'length' => 11,
			]);
			$table->addColumn('message', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('inreplyto', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => -1,
			]);
			$table->addColumn('deleted', 'bigint', [
				'notnull' => false,
				'length' => 11,
			]);
			$table->addColumn('priority', 'smallint', [
				'notnull' => false,
				'length' => 1,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('popup', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('reader', 'string', [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->setPrimaryKey(['id']);
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
