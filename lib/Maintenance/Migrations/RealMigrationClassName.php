<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Exception;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\MigrationsService;

/**
 * Store the real migration class name togethe with the version.
 *
 * The actual structural change has to be applied manually.
 */
class RealMigrationClassName extends AbstractMigration
{
  /** @var MigrationsService */
  protected $migrationsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
    MigrationsService $migrationsService,
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
    $this->migrationsService = $migrationsService;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Store the real migration class name together with the version.');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    $allMigrations = array_map(
      fn($version) =>  [
        'version' => $version,
        'migrationClassName' => self::getBaseClassName(get_parent_class(MigrationsService::MIGRATIONS_NAMESPACE . '\\Version' . $version)),
      ],
      array_keys($this->migrationsService->getAll())
    );
    $allMigrations = array_column($allMigrations, 'migrationClassName', 'version');

    $this->logInfo('ALL MIGRATIONS ' . print_r($allMigrations, true));

    $this->entityManager->beginTransaction();
    try {
      foreach ($allMigrations as $version => $shortClassName) {
        if (empty($shortClassName)) {
          continue;
        }
        if ($shortClassName == self::getBaseClassName(__CLASS__)) {
          continue;
        }
        /** @var Entities\Migration $migrationEntity */
        $migrationEntity = $this->entityManager->find(Entities\Migration::class, $version);
        if (empty($migrationEntity)) {
          continue;
        }
        $migrationEntity->setMigrationClassName($shortClassName);
      }
      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        try {
          $this->entityManager->rollback();
        } catch (\Throwable $t2) {
          $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
          }
      }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Transactional part of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }

    return true;
  }

  /**
   * @param string $className
   *
   * @return string
   */
  private static function getBaseClassName(string $className):string
  {
    return substr(strrchr($className, '\\'), 1);
  }
}
