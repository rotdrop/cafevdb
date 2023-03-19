<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCA\CAFEVDB\Migration;

use Doctrine\DBAL\Types\Types;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version060000Date20201001210735 extends SimpleMigrationStep
{
  /** {@inheritdoc} */
  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
  {
  }

  /** {@inheritdoc} */
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('cafevdb_progress_status')) {
      $table = $schema->createTable('cafevdb_progress_status');
      $table->addColumn('id', Types::BIGINT, [
        'autoincrement' => true,
        'notnull' => true,
        'unsigned' => true,
      ]);
      $table->setPrimaryKey(['id']);

      $table->addColumn('user_id', Types::STRING, [
        'notnull' => true,
        'length' => 64,
      ]);

      $table->addColumn('current', Types::BIGINT, [
        'notnull' => true,
        'unsigned' => true,
        'default' => 0,
      ]);

      $table->addColumn('target', Types::BIGINT, [
        'notnull' => true,
        'unsigned' => true,
        'default' => 0,
      ]);

      $table->addColumn('data', Types::JSON, [
        'notnull' => false,
      ]);

      $table->addColumn('last_modified', Types::BIGINT, [
        'notnull' => true,
        'unsigned' => true,
      ]);

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

  /** {@inheritdoc} */
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
  {
  }
}
