<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\RotDrop\Tests;

use RuntimeException;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception as ProcessExceptions;

use OCP\ITempManager;
use Psr\Log\LoggerInterface;

use OCA\RotDrop\Tests\Logger;

use OCA\RotDrop\Toolkit\Service\ExecutableFinder;

/** Setup a real database server in order to avoid excessive mocking. */
class DatabaseProvider
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  private const DATABASE_SETUP_SCRIPT = 'mariadb-install-db';

  private const DATABASE_SERVER = 'mariadbd-safe';

  private const DATABASE_CLIENT = 'mariadb';

  private const DATABASE_DUMP = 'mariadb-dump';

  private const DATABASE_USER = 'phpunit';

  public const CLOUD_DB_USER = 'nextcloud';

  private const DATABASE_PASSWORD = 'nothing';

  private const DEFAULTS_FILE_CONTENTS = '[mariadbd]
skip-networking
';

  private const SERVER_READY_STATEMENT = 'ready for connections';

  private ?Process $databaseProcess = null;

  private ?int $serverPid = null;

  private ?string $dbFolder;

  private ?DatabaseConfig $databaseConfig = null;

  protected LoggerInterface $logger;

  protected string $appName;

  /** {@inheritdoc} */
  public function __construct(
    protected ITempManager $tempManager,
    protected ExecutableFinder $executableFinder,
  ) {
    $this->appName = PHPUNIT_NC_APP_NAME;
    $this->tempManager->cleanOld();
    $this->logger = \OCP\Server::get(Logger::class);
  }

  /**
   * Setup a clean database and start a server on it.
   *
   * @return array
   */
  public function startServer(): array
  {
    $this->stopServer();

    $this->dbFolder = $dbFolder = rtrim($this->tempManager->getTemporaryFolder(), '/');
    $setupBinary = $this->executableFinder->find(self::DATABASE_SETUP_SCRIPT);

    $dbDataDir = $dbDataDir = $this->dbFolder . '/db-data';
    $serverSocketFile = $this->dbFolder. '/server-socket';
    $serverPidFile = $this->dbFolder . '/server.pid';
    $serverErrorFile = $this->dbFolder . '/server.err';
    $serverDefaultsFile = $this->dbFolder . '/mariadbd.cnf';
    $unixUser = get_current_user();

    $process = new Process([
      $setupBinary,
      '--basedir=/usr',
      '--datadir=' . $dbDataDir,
      '--user=' . $unixUser,
      '--no-defaults',
    ]);
    $process->run();

    file_put_contents($serverDefaultsFile, self::DEFAULTS_FILE_CONTENTS);

    $serverBinary = $this->executableFinder->find(self::DATABASE_SERVER);
    $this->databaseProcess = new Process([
      $serverBinary,
      '--defaults-file=' . $serverDefaultsFile,
      '--basedir=/usr',
      '--core-file-size=0',
      '--datadir=' . $dbDataDir,
      '--user=' . $unixUser,
      '--skip-syslog',
      '--socket=' . $serverSocketFile,
      '--pid-file=' . $serverPidFile,
      '--log-error=' . $serverErrorFile,
    ]);
    $this->databaseProcess->start();
    $timeout = 10000;
    while (!file_exists($serverErrorFile) && $timeout > 0) {
      --$timeout;
      usleep(10);
    }
    if (!file_exists($serverErrorFile)) {
      throw new RuntimeException('Server might not have been started successfully ' . $this->databaseProcess->getErrorOutput() . ' ' . $timeout);
    }
    $errorFp = fopen($serverErrorFile, 'r');
    $timeout = 10000; // up to roughly 10 seonds.

    while ($timeout-- > 0) {
      $read = [$errorFp];
      $write = null;
      $except = null;
      $numChanged = stream_select($read, $write, $except, 1, 0);
      if ($numChanged === false) {
        throw new RuntimeException('Error file "' . $serverErrorFile . '" did not change after ' . $timeout . ' seconds.');
      }
      $consume = 10;
      while (fread($errorFp, max(4096, filesize($serverErrorFile))) && --$consume > 0);
      $serverFeedback = file_get_contents($serverErrorFile);
      if (str_contains($serverFeedback, self::SERVER_READY_STATEMENT)) {
        break;
      }
      usleep(1000);
    }
    if ($timeout === 0) {
      throw new RuntimeException('Unable to setup database server: ' . file_get_contents($serverErrorFile));
    }

    $this->serverPid = (int)file_get_contents($this->dbFolder . '/server.pid');

    $dbUser = self::DATABASE_USER;
    $dbPassword = self::DATABASE_PASSWORD;
    $cloudDbUser = self::CLOUD_DB_USER;

    $initSql = "DELETE FROM mysql.user WHERE USER = '';
CREATE USER '{$dbUser}'@'%' IDENTIFIED BY '{$dbPassword}';
GRANT USAGE ON *.* TO '{$dbUser}'@'%' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;
CREATE USER '{$cloudDbUser}'@'%' IDENTIFIED BY '{$dbPassword}';
GRANT USAGE ON *.* TO '{$cloudDbUser}'@'%' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;
CREATE USER '{$cloudDbUser}'@'localhost' IDENTIFIED BY '{$dbPassword}';
GRANT USAGE ON *.* TO '{$cloudDbUser}'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;
CREATE DATABASE {$this->databaseName(EnumDatabasePurpose::APP)};
CREATE DATABASE {$this->databaseName(EnumDatabasePurpose::CLOUD_CONNECTOR)};
GRANT ALL PRIVILEGES ON {$this->databaseName(EnumDatabasePurpose::APP)}.* TO {$dbUser}@`%` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `{$this->databaseName(EnumDatabasePurpose::APP)}\\_%`.* TO {$dbUser}@`%` WITH GRANT OPTION;
FLUSH PRIVILEGES;
";

    $clientBinary = $this->executableFinder->find(self::DATABASE_CLIENT);
    $process = new Process([
      $clientBinary,
      '--protocol=SOCKET',
      '--socket=' . $serverSocketFile,
      '--user=' . $unixUser,
    ]);
    $process->setInput($initSql)->run();

    $this->logger('Database server ready: ' . file_get_contents($serverErrorFile));

    return compact(
      'dbFolder',
      'dbDataDir',
      'serverSocketFile',
      'serverPidFile',
      'serverErrorFile',
      'unixUser',
      'dbUser',
      'dbPassword',
    );
  }

  /**
   * @param EnumDatabasePurpose $which
   *
   * @return string
   */
  public function databaseName(EnumDatabasePurpose $which): string
  {
    $name = $this->appName;
    if ($which == EnumDatabasePurpose::CLOUD_CONNECTOR) {
      $name .= '_cloud_connector';
    }
    return $name;
  }

  /**
   * Dump the given database to disk.
   *
   * @param EnumDatabasePurpose $which
   *
   * @param string $dumpFileBase
   *
   * @return void
   */
  public function dumpDatabase(
    EnumDatabasePurpose $which,
    string $dumpFileBase,
  ): void {
    $unixUser = get_current_user();
    $serverSocketFile = $this->dbFolder. '/server-socket';
    $dumpBinary = $this->executableFinder->find(self::DATABASE_DUMP);
    $dbName = $this->databaseName($which);
    $dumpProcess = new Process([
      $dumpBinary,
      '--protocol=SOCKET',
      '--socket=' . $serverSocketFile,
      '--user=' . $unixUser,
      '--routines',
      $dbName,
    ]);
    $dumpProcess->run();
    $sql = $dumpProcess->getOutput();
    file_put_contents(\PHPUNIT_ARTIFACTS . '/' . $dumpFileBase . '-' . $dbName . '.sql', $sql);
  }

  /**
   * @param EnumDatabasePurpose $which
   *
   * @param string $sqlFile
   *
   * @return void
   */
  public function loadSql(
    EnumDatabasePurpose $which,
    string $sqlFile,
  ): void {
    $sql = file_get_contents($sqlFile);
    $unixUser = get_current_user();
    $serverSocketFile = $this->dbFolder. '/server-socket';
    $clientBinary = $this->executableFinder->find(self::DATABASE_CLIENT);
    $process = new Process([
      $clientBinary,
      '--protocol=SOCKET',
      '--socket=' . $serverSocketFile,
      '--user=' . $unixUser,
      $this->databaseName($which),
    ]);
    $process->setInput($sql)->run();
  }

  /**
   * @return ?array
   */
  public function getDatabaseConfig(): ?DatabaseConfig
  {
    if (($this->databaseProcess ?? null) === null) {
      return null;
    }
    if ($this->databaseConfig !== null) {
      return $this->databaseConfig;
    }
    $socket = urlencode($this->dbFolder. '/server-socket');
    $url = sprintf(
      '%s://%s:%s@%s?unix_socket=%s',
      'pdo-mysql', self::DATABASE_USER, self::DATABASE_PASSWORD, 'localhost', $socket,
    );
    $this->databaseConfig = new DatabaseConfig(
      databaseName: $this->databaseName(EnumDatabasePurpose::APP),
      databaseServer: $url,
      databaseUser: self::DATABASE_USER,
      databasePassword: self::DATABASE_PASSWORD,
    );
    return $this->databaseConfig;
  }

  /**
   * Stop a running server and remove all associated data.
   *
   * @return void
   */
  public function stopServer(): void
  {
    if (!empty($this->databaseProcess)) {
      // No need to be kind
      $this->databaseProcess->stop(0, SIGKILL);
      posix_kill($this->serverPid, SIGKILL);
      $this->tempManager->clean();
      $this->databaseProcess = null;
      $this->dbFolder = null;
      $this->serverPid = null;
    }
  }
}
