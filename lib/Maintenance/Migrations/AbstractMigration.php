<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Abstract migration class, the derived classes have to fill
 * AbstractMigration::$sql.
 */
abstract class AbstractMigration implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  protected const STRUCTURAL = 'structural';
  protected const TRANSACTIONAL = 'transactional';

  /**
   * @var array
   *
   * Split the SQL commands into "structural" commands which cannot be wrapped
   * into a transactions due to some "auto commit" "feature" of MySQL/MariaDB
   * and/or the used PHP SQL-engine and "transactional" SQL commands which can
   * be undone by wrapping them in to a transaction.
   *
   * @todo "structural" recover from failed "structural" commands.
   */
  protected static $sql = [
    self::STRUCTURAL => [],
    self::TRANSACTIONAL => [],
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /**
   * Execute the SQL instructions defined in AbstractMigration::$sql
   *
   * @return bool
   */
  public function execute():bool
  {
    $connection = $this->entityManager->getConnection();

    try {
      foreach (static::$sql[self::STRUCTURAL] as $sql) {
        if (!is_array($sql)) {
          $sql = [ 'statement' => $sql, 'bind' => null ];
        }
        $statement = $connection->prepare($sql['statement']);
        if (is_callable($sql['bind']??null)) {
            call_user_func($sql['bind'], $statement);
        }
        $statement->execute();
      }
    } catch (\Throwable $t) {
      throw new Exceptions\DatabaseMigrationException($this->l->t('Structural part of migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }

    if (!empty(static::$sql[self::TRANSACTIONAL])) {
      $connection->beginTransaction();
      try {
        foreach (static::$sql[self::TRANSACTIONAL] as $sql) {
          if (!is_array($sql)) {
            $sql = [ 'statement' => $sql, 'bind' => null ];
          }
          $statement = $connection->prepare($sql['statement']);
          if (is_callable($sql['bind']??null)) {
            call_user_func($sql['bind'], $statement);
          }
          $statement->execute();
        }
        if ($connection->isTransactionActive()) {
          $connection->commit();
        }
      } catch (\Throwable $t) {
        if ($connection->isTransactionActive()) {
          try {
            $connection->rollBack();
          } catch (\Throwable $t2) {
            $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
          }
        }
        throw new Exceptions\DatabaseMigrationException($this->l->t('Transactional part of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
      }
    }
    return true;
  }
}
