<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Database\Doctrine\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;

/** Manage doctrine database migrations. */
class DoctrineMigrationsService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConsoleLogger $logger,
    protected EntityManager $entityManager,
  ) {
    $this->logger = $logger;
  }
  // phpcs:enable

  /** @return ?string The latest applied migration. null if none has been applied yet. */
  public function getLatest(): ?string
  {
    $aliasResolver = $this->getDependencyFactory()->getVersionAliasResolver();
    $version = (string)$aliasResolver->resolveVersionAlias('current');
    if ($version === '0') {
      return null;
    }
    return $version;
  }

  /**
   * Generate the "Dependency factory" needed to run the Doctrine Migrations services.
   *
   * @return DependencyFactory
   */
  public function getDependencyFactory(): DependencyFactory
  {
    /** @var ClassMetadata $versionMetadata */
    $versionMetadata = $this->entityManager->getClassMetadata(DoctrineMigrationsVersion::class);
    $configuration = [
      'table_storage' => [
        'table_name' => $versionMetadata->getTableName(),
        'version_column_name' => $versionMetadata->getFieldMapping('version')->columnName,
        'version_column_length' => $versionMetadata->getFieldMapping('version')->length,
        'executed_at_column_name' => $versionMetadata->getFieldMapping('executedAt')->columnName,
        'execution_time_column_name' => $versionMetadata->getFieldMapping('executionTime')->columnName,
      ],
      'migrations_paths' => [
        MigrationsNamespace::class => realpath(__DIR__ . '/../Database/Doctrine/Migrations'),
      ],
      'all_or_nothing' => true,
      'transactional' => true,
      'check_database_platform' => true,
      'organize_migrations' => 'none',
      'connection' => null,
      'em' => null,
    ];
    $configurationLoader = new ConfigurationArray($configuration);
    return DependencyFactory::fromEntityManager(
      $configurationLoader,
      new ExistingEntityManager($this->entityManager),
      $this->logger,
    );
  }
}
