<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\BackgroundJob;

use OC\Files\Utils\Scanner;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Class ScanFiles is a background job used to run the file scanner over the user
 * accounts to ensure integrity of the file cache. This is a copy of
 *
 * OCA\Files\BackgroundJob\ScanFiles
 *
 * which is triggered by a JS timer via Ajax from the front-end in order to
 * get authenticated background jobs.
 */
class ScanFiles extends \OC\BackgroundJob\TimedJob
{
  /** Amount of users that should get scanned per execution */
  public const USERS_PER_SESSION = 500;

  /**
   * @param IConfig $config
   * @param IEventDispatcher $dispatcher
   * @param LoggerInterface $logger
   * @param IDBConnection $connection
   */
  public function __construct(
    private IConfig $config,
    private IEventDispatcher $dispatcher,
    private LoggerInterface $logger,
    private IDBConnection $connection,
  ) {
    // Run once per 10 minutes
    $this->setInterval(60 * 10);
  }

  /**
   * @param string $user
   *
   * @return void
   */
  protected function runScanner(string $user)
  {
    try {
      $scanner = new Scanner(
        $user,
        null,
        $this->dispatcher,
        $this->logger
      );
      $scanner->backgroundScan('');
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage(), ['exception' => $e, 'app' => 'files']);
    }
    \OC_Util::tearDownFS();
  }

  /**
   * Find a storage which have unindexed files and return a user with access to the storage
   *
   * @return string|false
   */
  private function getUserToScan()
  {
    $query = $this->connection->getQueryBuilder();
    $query->select('user_id')
      ->from('filecache', 'f')
      ->innerJoin('f', 'mounts', 'm', $query->expr()->eq('storage_id', 'storage'))
      ->where($query->expr()->lt('size', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->gt('parent', $query->createNamedParameter(-1, IQueryBuilder::PARAM_INT)))
      ->setMaxResults(1);

    return $query->execute()->fetchOne();
  }

  /** {@inheritdoc} */
  public function run($argument)
  {
    $usersScanned = 0;
    $lastUser = '';
    $user = $this->getUserToScan();
    while ($user && $usersScanned < self::USERS_PER_SESSION && $lastUser !== $user) {
      $this->runScanner($user);
      $lastUser = $user;
      $user = $this->getUserToScan();
      $usersScanned += 1;
    }

    if ($lastUser === $user) {
      $this->logger->warning("User $user still has unscanned files after running background scan, background scan might be stopped prematurely");
    }
  }
}
