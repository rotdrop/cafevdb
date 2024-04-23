<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Service\EncryptionService;

/**
 * Connect to a GnuCash account book stored in a MariaDB database. This is
 * realized by hijacking the accounts, books, commodities, slots, splits and
 * transactions tables of an existing GnuCash database, moving the tables to
 * the cafevdb database and replacing the original tables by views with
 * security definer.
 */
class GnuCashConnectorService
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
  private const INSERT_STMT = 'INSERT INTO %1$s (%3$s) SELECT %3$s FROM %2$s';
  private const CREATE_VIEW_STMT = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT *
FROM %2$s';

  /** @var Connection */
  private $connection;

  /** @var string */
  private $appDbHost;

  /** @var string */
  private $appDbUser;

  /** @var string */
  private $appDbName;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IAppContainer $appContainer,
    protected ILogger $logger,
    protected IL10N $l,
    private EncryptionService $encryptionService,
  ) {
    if ($this->encryptionService->bound()) {
      $this->connection = $this->appContainer->get(Connection::class);
      $this->appDbName = $this->encryptionService->getConfigValue('dbname');
      $this->appDbUser = $this->encryptionService->getConfigValue('dbuser');
      $this->appDbHost = $this->encryptionService->getConfigValue('dbserver');
    }
  }
  // phpcs:enable

  /**
   * Copy the data out of the given GnuCash data-base.
   */
  public function copyGnuCashTables(string $gnuCashDatabase)
  {
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

    $gncConnection = $this->connection->bind($gnuCashDatabase);
    /** @var SchemaManager $gncSchemaManager */
    $gncSchemaManager = $gncConnection->getSchemaManager();

    foreach (self::GNU_CASH_TABLES as $gncTable) {
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
        throw new UnexpectedValueException(
          $this->l->t('%1$s column names differ from expected column-names.', 'GnuCash'),
        );
      }

      $sql = vsprintf(
        self::INSERT_STMT, [
          $this->appDbName . '.' . $ormTable,
          $gnuCashDatabase . '.' . $gncTable,
          implode(',', $gncColumns),
        ],
      );
      $this->logDebug('SQL ' . $sql);
      $this->connection->prepare($sql)->execute();

      $gncSchemaManager->renameTable($gncTable, $gncTable . '_old');

      $sql = vsprintf(
        self::CREATE_VIEW_STMT, [
          $gnuCashDatabase . '.' . $gncTable,
          $this->appDbName . '.' . $ormTable,
        ],
      );
      $this->logDebug('SQL ' . $sql);
      $this->connection->prepare($sql)->execute();
    }
  }
}
