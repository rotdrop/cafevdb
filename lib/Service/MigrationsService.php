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

class MigrationsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const MIGRATIONS_FOLDER = __DIR__ . '/../Maintenance/Migrations/';
  private const MIGRATIONS_NAMESPACE = 'OCA\CAFEVDB\\Maintenance\\Migrations';

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
    $this->logInfo(print_r($this->findMigrations(self::MIGRATIONS_FOLDER), true));

    /** @var Entities\Migration $latestMigration */
    $latestMigration = $this->getDatabaseRepository(Entities\Migration::class)->findOneBy([], [ 'version' => 'DESC' ]);

    $this->logInfo('LATEST: ' . (empty($latestMigration) ? 'null' : $latestMigration->getVersion()));

    return false;
  }

  protected function findMigrations(string $directory)
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
