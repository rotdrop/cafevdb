<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Copilot <223556219+Copilot@users.noreply.github.com>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\CAFEVDB\Service\WopiCredentialRegistry;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/** Create the app-owned expiry registry for WOPI password credentials. */
class Version100201Date20261114001000 extends SimpleMigrationStep
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
    $schema = $schemaClosure();
    if ($schema->hasTable(WopiCredentialRegistry::TABLE_NAME)) {
      return null;
    }
    $table = $schema->createTable(WopiCredentialRegistry::TABLE_NAME);
    $table->addColumn('token_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
    $table->addColumn('token_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
    $table->addColumn('expires', Types::BIGINT, ['notnull' => true]);
    $table->setPrimaryKey(['token_id']);
    $table->addIndex(['expires'], 'cafevdb_wopi_expiry');
    return $schema;
  }
}
