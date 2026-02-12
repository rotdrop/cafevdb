<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use Throwable;
use UnexpectedValueException;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\View;

/**
 * Connect to a GnuCash account book stored in a MariaDB database. This is
 * realized by hijacking the accounts, books, commodities, slots, splits and
 * transactions tables of an existing GnuCash database, moving the tables to
 * the cafevdb database and replacing the original tables by views with
 * security definer.
 */
class GnuCashDatabaseService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const GNU_CASH_TABLES = [
    'accounts',
    'books',
    'commodities',
    'slots',
    'splits',
    'transactions',
  ];
  private const INSERT_STMT = 'INSERT INTO %1$s (%3$s) SELECT %3$s FROM %2$s ON DUPLICATE KEY UPDATE %1$s.guid = %2$s.guid';
  private const CREATE_VIEW_STMT = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT *
FROM %2$s';

  /** @var string */
  private $appDbHost;

  /** @var string */
  private $appDbUser;

  /** @var string */
  private $appDbName;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private Connection $connection,
    private EncryptionService $encryptionService,
    private IL10N $l,
    protected ILogger $logger,
  ) {
    $this->appDbName = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_NAME);
    $this->appDbUser = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_USER);
    $this->appDbHost = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_SERVER);
  }
  // phpcs:enable

  /**
   * Copy the data out of the given GnuCash data-base.
   *
   * @param string $gnuCashDatabase
   *
   * @param null|string $user
   *
   * @param null|string $password
   *
   * @param null|string $host
   *
   * @return void
   */
  public function copyGnuCashTables(
    string $gnuCashDatabase,
    ?string $user = null,
    ?string $password = null,
    ?string $host = null,
  ):void {
    // // $em is your Doctrine\ORM\EntityManager instance
    // $schemaManager = $em->getConnection()->getSchemaManager();
    // // array of Doctrine\DBAL\Schema\Column
    // $columns = $schemaManager->listTableColumns($tableName);

    // $columnNames = [];
    // foreach($columns as $column){
    //   $columnNames[] = $column->getName();
    // }
    // // $columnNames contains all column names

    /** @var SchemaManager $schemaManager */
    $schemaManager = $this->connection->getSchemaManager();

    $gncConnection = $this->connection->bind(
      $gnuCashDatabase,
      $user,
      $password,
      $host,
    );
    /** @var SchemaManager $gncSchemaManager */
    $gncSchemaManager = $gncConnection->getSchemaManager();

    $gncViews = array_keys($gncSchemaManager->listViews());

    foreach (self::GNU_CASH_TABLES as $gncTable) {
      if (in_array($gncTable, $gncViews)) {
        // test is not perfect, but just let's skip it if it is a view already.
        continue;
      }
      $gncColumns = array_map(
        fn($column) => $column->getName(),
        $gncSchemaManager->listTableColumns($gncTable),
      );
      sort($gncColumns);

      $ormTable = 'GnuCash' . ucfirst($gncTable);
      $ormColumns = array_map(
        fn($column) => $column->getName(),
        $schemaManager->listTableColumns($ormTable),
      );
      sort($ormColumns);

      if ($gncColumns != $ormColumns) {
        print_r($ormColumns);
        print_r($gncColumns);
        throw new UnexpectedValueException(
          $this->l->t('%1$s column names differ from expected column-names.', 'GnuCash'),
        );
      }

      $sql = vsprintf(
        'SET FOREIGN_KEY_CHECKS=0;'
        . self::INSERT_STMT, [
          $this->appDbName . '.' . $ormTable,
          $gnuCashDatabase . '.' . $gncTable,
          implode(',', $gncColumns),
        ],
      );
      $this->logInfo('SQL ' . $sql);
      $this->connection->prepare($sql)->executeQuery();

      $gncSchemaManager->renameTable($gncTable, $gncTable . '_old');

      try {
        $sql = vsprintf(
          self::CREATE_VIEW_STMT, [
            $gnuCashDatabase . '.' . $gncTable,
            $this->appDbName . '.' . $ormTable,
          ],
        );
        $this->logInfo('SQL ' . $sql);
        $gncConnection->prepare($sql)->executeQuery();
      } catch (Throwable $t) {
        $this->logException($t);
        $gncSchemaManager->renameTable($gncTable . '_old', $gncTable);
      }
    }
  }
}
