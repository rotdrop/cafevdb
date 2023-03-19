<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use RuntimeException;
use RegexIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\InvalidFieldNameException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception as DBALException;

/** Manage database migrations. */
class MigrationsService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const MIGRATIONS_FOLDER = __DIR__ . '/../Maintenance/Migrations/';
  public const MIGRATIONS_NAMESPACE = 'OCA\CAFEVDB\\Maintenance\\Migrations';

  /** @var IAppContainer */
  private $appContainer;

  /** @var null|array */
  private $unappliedMigrations = null;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IL10N $l10n,
    ILogger $logger,
    IAppContainer $appContainer,
    EntityManager $entityManager,
  ) {
    $this->l = $l10n;
    $this->logger = $logger;
    $this->appContainer = $appContainer;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /**
   * Check whether there need any migrations to be applied.
   *
   * @return bool
   */
  public function needsMigration():bool
  {
    $this->ensureMigrationsAreLoaded();
    return !empty($this->unappliedMigrations);
  }

  /**
   * Apply all found migrations, stop when one is failing.
   *
   * @return void
   */
  public function applyAll():void
  {
    $this->ensureMigrationsAreLoaded();
    foreach ($this->unappliedMigrations as $version => $className) {
      $this->logInfo('Trying to apply migration ' . $version . ', PHP-class ' . $className);
      try {
        $this->applyMigration($version, $className);
      } catch (\Throwable $t) {
        $this->logException($t);
        break;
      }
    }
  }

  /**
   * @return array All unapplied migrations. The migration classes are
   * instatiated using depency injection with the app-container.
   */
  public function getUnapplied():array
  {
    if (!$this->entityManager->connected()) {
      return [];
    }
    $this->ensureMigrationsAreLoaded();
    return array_map(fn($className) => $this->appContainer->get($className)->description(), $this->unappliedMigrations);
  }

  /**
   * @return array Get all migration classes, instantiate all of them via
   * dependency injection with the app-container.
   */
  public function getAll():array
  {
    if (!$this->entityManager->connected()) {
      return [];
    }
    return array_map(fn($className) => $this->appContainer->get($className)->description(), $this->findMigrations(self::MIGRATIONS_FOLDER));
  }

  /** @return string The latest migration in the migrations folder. */
  public function getLatest():string
  {
    return $this->findLatestVersion();
  }

  /**
   * Apply the migration with the given version
   *
   * @param string $version
   *
   * @return void
   */
  public function apply(string $version):void
  {
    $allMigrations = $this->findMigrations(self::MIGRATIONS_FOLDER);
    if (!empty($allMigrations[$version])) {
      $this->applyMigration($version, $allMigrations[$version]);
    }
  }

  /**
   * Get the description of the migration with the given version
   *
   * @param string $className The version (which is also the class-name).
   *
   * @return string The description
   */
  public function description(string $className):string
  {
    /** @var IMigration $instance */
    $instance = $this->appContainer->get($className);
    return $instance->description();
  }

  /**
   * Apply the given migration.
   *
   * @param string $version
   *
   * @param string $className
   *
   * @return void
   */
  protected function applyMigration(string $version, string $className):void
  {
    /** @var IMigration $instance */
    $instance = $this->appContainer->get($className);

    $this->entityManager->close();
    $this->entityManager->reopen();

    $result = $instance->execute();
    if ($result !== true) {
      throw new RuntimeException($this->l->t("Migration %s has failed to execute.", $className));
    }

    $this->entityManager->close();
    $this->entityManager->reopen();

    $migrationClassName = self::getBaseClassName($className);

    /** @var Entities\Migration $migrationRecord */
    $migrationRecord = (new Entities\Migration)
      ->setVersion($version)
      ->setMigrationClassName($migrationClassName);

    $this->persist($migrationRecord);
    $this->flush();
  }

  /**
   * Ensure all unapplied migrations are loaded.
   *
   * @return void
   */
  protected function ensureMigrationsAreLoaded():void
  {
    if ($this->unappliedMigrations === null) {
      $this->unappliedMigrations = $this->findUnappliedMigrations(self::MIGRATIONS_FOLDER);
    }
  }

  /** @return void */
  protected function createMigrationsTable():void
  {
    $sql = "CREATE TABLE IF NOT EXISTS Migrations (
  version char(14) CHARACTER SET ascii NOT NULL,
  created datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (version)
)";
    $connection = $this->entityManager->getConnection();
    $stmt = $connection->prepare($sql);
    $stmt->executeQuery();
  }

  /** @return void */
  protected function sanitizeMigrationsTable():void
  {
    $sql = "ALTER TABLE Migrations ADD COLUMN IF NOT EXISTS migration_class_name VARCHAR(512) NOT NULL;
ALTER TABLE Migrations ADD COLUMN IF NOT EXISTS version char(14) CHARACTER SET ascii NOT NULL;
ALTER TABLE Migrations ADD COLUMN IF NOT EXISTS created datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';
ALTER TABLE Migrations ADD COLUMN IF NOT EXISTS updated datetime(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'";
    $connection = $this->entityManager->getConnection();
    $stmt = $connection->prepare($sql);
    $stmt->executeQuery();
  }

  /**
   * @param bool $autoFix
   *
   * @return null|string The most recent migration applied, as stored in the database.
   */
  protected function findLatestVersion(bool $autoFix = true):?string
  {
    if (!$this->entityManager->connected()) {
      return null;
    }
    /** @var Entities\Migration $latestMigration */
    try {
      $latestMigration = $this->getDatabaseRepository(Entities\Migration::class)->findOneBy([], [ 'version' => 'DESC' ]);
    } catch (DBALException\TableNotFoundException $tnfe) {
      // Ok, there is no migrations table, handle this inside the initial migration
      $this->logInfo('NO MIGRATIONS TABLE');
      if ($autoFix) {
        $this->createMigrationsTable();
        return $this->findLatestVersion(autoFix: false);
      }
    } catch (InvalidFieldNameException $ifne) {
      $this->logInfo('MIGRATIONS TABLE SEEMS BROKEN');
      if ($autoFix) {
        $this->sanitizeMigrationsTable();
        return $this->findLatestVersion(autoFix: false);
      }
    } catch (\Throwable $t) {
      $this->logException($t);
    }
    if (empty($latestMigration)) {
      $this->logInfo('NO MIGRATIONS HAVE BEEN APPLIED YET.');
      return null;
    }
    $this->logInfo('LATEST ' . $latestMigration->getVersion());
    return $latestMigration->getVersion();
  }

  /**
   * Find all unapplied migrations.
   *
   * @param string $directory
   *
   * @return array
   */
  protected function findUnappliedMigrations(string $directory):array
  {
    $allMigrations = $this->findMigrations($directory);
    $latestVersion = $this->findLatestVersion();
    return empty($latestVersion)
      ? $allMigrations
      : array_filter($allMigrations, function($version) use ($latestVersion) {
        return $version > $latestVersion;
      }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * @param string $directory
   *
   * @return array
   */
  protected function findMigrations(string $directory):array
  {
    $directory = realpath($directory);
    if ($directory === false || !file_exists($directory) || !is_dir($directory)) {
      return [];
    }

    $iterator = new RegexIterator(
      new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
      ),
      '#^.+\\/Version\d+\\.php$#i',
      \RegexIterator::GET_MATCH);

    $files = array_keys(iterator_to_array($iterator));
    uasort($files, function ($a, $b) {
      preg_match('/^Version(\d+)\\.php$/', basename($a), $matchA);
      preg_match('/^Version(\d+)\\.php$/', basename($b), $matchB);
      if (!empty($matchA) && !empty($matchB)) {
        if ($matchA[1] !== $matchB[1]) {
          return ($matchA[1] < $matchB[1]) ? -1 : 1;
        }
        return ($matchA[2] < $matchB[2]) ? -1 : 1;
      }
      return (basename($a) < basename($b)) ? -1 : 1;
    });

    $migrations = [];

    foreach ($files as $file) {
      $className = basename($file, '.php');
      $version = (string) substr($className, 7);
      $migrations[$version] = sprintf('%s\\%s', self::MIGRATIONS_NAMESPACE, $className);
    }

    return $migrations;
  }

  /**
   * @param string $className
   *
   * @return string
   */
  private static function getBaseClassName(string $className):string
  {
    return substr(strrchr(get_parent_class($className), '\\'), 1);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
