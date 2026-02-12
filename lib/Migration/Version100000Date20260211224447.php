<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Cloud\Entities\TOSException;
use OCA\CAFEVDB\Toolkit\Service\AppInfoService;

/**
 * Generate a table to store ToS exceptions for public shares s.t. those are
 * accessible by simpe GET requests without cookies.
 */
class Version100000Date20260211224447 extends SimpleMigrationStep
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure
   * @param array $options
   *
   * @return void
   */
  #[Override]
  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
  {
  }

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
    $appName = AppInfoService::getAppInfoAppName();
    $tableName = $this->makeTableName($appName, TOSException::class);

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

      $table->addColumn('share_token', Types::STRING, [
        'notnull' => true,
        'length' => 32,
      ]);

      $table->addColumn('ip_ranges', Types::STRING, [
        'notnull' => true,
        'length' => 255,
      ]);
    }

    return $schema;
  }

  /**
   * @param IOutput $output
   * @param Closure $schemaClosure
   * @param array $options
   *
   * @return void
   */
  #[Override]
  public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
  {
  }
}
