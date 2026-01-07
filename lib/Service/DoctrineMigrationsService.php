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

use InvalidArgumentException;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Database\Doctrine\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Database\Doctrine\Migrations\EnumMigrationDirection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Metadata\AvailableMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Metadata\ExecutedMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Version\Version;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\MigratorConfiguration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Exception\MigrationClassNotFound;

/**
 * Manage doctrine database migrations. As Doctrine\Migrations comes with its
 * own complete set of console commands it is not necessary to provide much
 * services, we just need getUnapplied() and apply() in order to service the
 * frontend MigrationsController.
 */
class DoctrineMigrationsService implements MigrationsServiceInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConsoleLogger $logger,
    protected EntityManager $entityManager,
    protected IAppContainer $appContainer,
    protected IL10N $l,
  ) {
    $this->logger = $logger;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getUnapplied(): array
  {
    $dependencyFactory = $this->getDependencyFactory();
    $allMigrations = $dependencyFactory->getMigrationPlanCalculator()->getMigrations();
    $executedMigrations = $dependencyFactory->getMetadataStorage()->getExecutedMigrations();
    $executedVersions = array_map(
      fn(ExecutedMigration $migration): string => substr((string)$migration->getVersion(), -14),
      $executedMigrations->getItems(),
    );
    $unapplied = [];
    /** @var AvailableMigration $migration */
    foreach ($allMigrations->getItems() as $migration) {
      $version = substr((string)$migration->getVersion(), -14);
      if (in_array($version, $executedVersions)) {
        continue;
      }
      $unapplied[$version] = $migration->getMigration()->getDescription();
    }

    return $unapplied;
  }

  /** {@inheritdoc} */
  public function apply(string $version, EnumMigrationDirection $direction = EnumMigrationDirection::UP): void
  {
    $dependencyFactory = $this->getDependencyFactory();
    $dependencyFactory->getMetadataStorage()->ensureInitialized();
    $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
    $migrationClassName = MigrationsNamespace::class . '\\Version' . $version;
    try {
      $plan = $planCalculator->getPlanForVersions(
        array_map(static fn (string $version): Version => new Version($version), [$migrationClassName]),
        $direction->value,
      );
    } catch (MigrationClassNotFound $t) {
      throw new InvalidArgumentException(
        $this->l->t('A migration with the version "%s" does not exist.', $version),
        previous: $t,
      );
    }
    $numTransactional = 0;
    $numNonTransactional = 0;
    foreach ($plan->getItems() as $planItem) {
      $transactional = (int)$planItem->getMigration()->isTransactional();
      $numTransactional += $transactional;
      $numNonTransactional += 1 - $transactional;
    }
    if ($numTransactional == 0) {
      $allOrNothing = false;
    } elseif ($numNonTransactional == 0) {
      $allOrNothing = true;
    } else {
      throw new InvalidArgumentException(
        $this->l->t(
          'The migration with the version "%s" involves other additional migrations,'
          . ' however, this mixed structural and content changes which is not supported at the moment.', $version),
      );
    }

    $this->logInfo('Executing {version} {direction}', [
      'direction' => $plan->getDirection(),
      'version' => $version,
    ]);
    $migrator = $dependencyFactory->getMigrator();
    $migratorConfiguration = new MigratorConfiguration()
      ->setDryRun(false)
      ->setTimeAllQueries(true)
      ->setAllOrNothing($allOrNothing);
    /* $sql = */$migrator->migrate($plan, $migratorConfiguration);
  }

  /**
   * Generate the "Dependency factory" needed to run the Doctrine Migrations
   * services. This is also used by the setup code of the CLI commands.
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
    return MigrationsNamespace\DependencyFactory::fromEntityManager(
      configurationLoader: $configurationLoader,
      emLoader: new ExistingEntityManager($this->entityManager),
      logger: $this->logger,
      appContainer: $this->appContainer,
    );
  }
}
