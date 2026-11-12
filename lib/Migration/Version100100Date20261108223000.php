<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Migration;

use Closure;
use Override;

use Doctrine\DBAL\Types\Types;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

use OCA\CAFEVDB\Service\ProblemReportGithubService;

/**
 * Generate a table which maps fingerprints of problem reports to the GitHub
 * issues they have been submitted to, in order to avoid duplicate issues.
 */
class Version100100Date20261108223000 extends SimpleMigrationStep
{
  /**
   * @param IOutput $output
   * @param Closure $schemaClosure
   * @param array $options
   *
   * @return null|ISchemaWrapper
   */
  #[Override]
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    $tableName = ProblemReportGithubService::TABLE_NAME;

    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();
    if (!$schema->hasTable($tableName)) {
      $table = $schema->createTable($tableName);
      $table->addColumn('id', Types::BIGINT, [
        'autoincrement' => true,
        'notnull' => true,
        'unsigned' => true,
      ]);
      $table->setPrimaryKey(['id']);

      $table->addColumn('fingerprint', Types::STRING, [
        'notnull' => true,
        'length' => 64,
      ]);
      $table->addColumn('repository', Types::STRING, [
        'notnull' => true,
        'length' => 140,
      ]);
      $table->addColumn('issue_number', Types::BIGINT, [
        'notnull' => true,
        'unsigned' => true,
      ]);
      $table->addColumn('issue_url', Types::STRING, [
        'notnull' => true,
        'length' => 255,
      ]);
      $table->addColumn('occurrences', Types::INTEGER, [
        'notnull' => true,
        'default' => 1,
      ]);
      $table->addColumn('last_reported', Types::INTEGER, [
        'notnull' => true,
        'default' => 0,
      ]);
      $table->addUniqueIndex(['fingerprint', 'repository'], 'cafevdb_pr_fp_repo');

      return $schema;
    }

    return null;
  }
}
