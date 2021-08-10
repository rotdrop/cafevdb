<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception as DBALException;

class MigrationsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const MIGRATIONS_FOLDER = __DIR__ . '/../Maintenance/Migrations/';
  private const MIGRATIONS_NAMESPACE = 'OCA\CAFEVDB\\Maintenance\\Migrations';

  private $unappliedMigrations = null;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10n();
  }

  public function needsMigration():bool
  {
    $this->ensureMigrationsAreLoaded();
    return !empty($this->unappliedMigrations);
  }

  public function applyAll()
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

  public function getUnapplied()
  {
    $this->ensureMigrationsAreLoaded();
    return array_keys($this->unappliedMigrations);
  }

  public function getAll()
  {
    return array_keys($this->findMigrations(self::MIGRATIONS_FOLDER));
  }

  public function getLatest()
  {
    return $this->findLatestVersion();
  }

  public function apply(string $version)
  {
    $allMigrations = $this->findMigrations(self::MIGRATIONS_FOLDER);
    if (!empty($allMigrations[$version])) {
      $this->applyMigration($version, $allMigrations[$version]);
    }
  }

  protected function applyMigration(string $version, string $className)
  {
    /** @var IMigration $instance */
    $instance = $this->di($className);
    $result = $instance->execute();
    if ($result !== true) {
      throw new \RuntimeException($this->l->t("Migration %s has failed to execute.", $className));
    }

    /** @var Entities\Migration $migrationRecord */
    $migrationRecord = (new Entities\Migration)->setVersion($version);
    $this->persist($migrationRecord);
    $this->flush();
  }

  protected function ensureMigrationsAreLoaded()
  {
    $this->unappliedMigrations = $this->findUnappliedMigrations(self::MIGRATIONS_FOLDER);
  }

  protected function findLatestVersion():?string
  {
    /** @var Entities\Migration $latestMigration */
    try {
      $latestMigration = $this->getDatabaseRepository(Entities\Migration::class)->findOneBy([], [ 'version' => 'DESC' ]);
    } catch (DBALException\TableNotFoundException $tnfe) {
      // Ok, there is no migrations table, handle this inside the initial migration
      $this->logInfo('NO MIGRATIONS TABLE');
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

  protected function findMigrations(string $directory):array
  {
    $directory = realpath($directory);
    if ($directory === false || !file_exists($directory) || !is_dir($directory)) {
      return [];
    }

    $iterator = new \RegexIterator(
      new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
