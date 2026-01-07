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
use OCA\CAFEVDB\Maintenance\Migrations as MigrationsNamespace;
use OCA\CAFEVDB\Database\Doctrine\Migrations\DependencyFactory;
use OCA\CAFEVDB\Database\Doctrine\Migrations\EnumMigrationDirection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\TableNotFoundException;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Exception\MigrationClassNotFound;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Metadata\AvailableMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Metadata\ExecutedMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\MigratorConfiguration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Version\Version;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Manage doctrine database migrations. As Doctrine\Migrations comes with its
 * own complete set of console commands it is not necessary to provide much
 * services, we just need getUnapplied() and apply() in order to service the
 * frontend MigrationsController.
 */
class DoctrineMigrationsService implements MigrationsServiceInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private ?array $appliedMigrations = null;

  private ?array $unappliedMigrations = null;

  private ?array $executedMigrations = null;

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

  /**
   * Clear the runtime cache of migrations.
   *
   * @return void
   */
  public function clearCache(): void
  {
    $this->executedMigrations =
      $this->appliedMigrations =
      $this->unappliedMigrations = null;
  }

  /**
   * Get a flat array of already executed migrations from the versions table
   * from the DB.
   *
   * @return array<string>
   */
  private function getExecutedMigrations(): array
  {
    if ($this->executedMigrations !== null) {
      return $this->executedMigrations;
    }
    // Costly, it will also do a table diff.
    // $executedMigrations = $dependencyFactory->getMetadataStorage()->getExecutedMigrations();
    // $executedVersions = array_map(
    //   fn(ExecutedMigration $migration): string => substr((string)$migration->getVersion(), -14),
    //   $executedMigrations->getItems(),
    // );
    try {
      $versions = $this->entityManager->getRepository(DoctrineMigrationsVersion::class)->findAll();
      $this->executedMigrations = array_map(fn(DoctrineMigrationsVersion $version) => substr($version->getVersion(), -14), $versions);
    } catch (TableNotFoundException) {
      // ok then: empty
      $this->executedMigrations = [];
    }
    return $this->executedMigrations;
  }

  /** {@inheritdoc} */
  public function getApplied(): array
  {
    if ($this->appliedMigrations !== null) {
      return $this->appliedMigrations;
    }

    $dependencyFactory = $this->getDependencyFactory();
    $allMigrations = $dependencyFactory->getMigrationPlanCalculator()->getMigrations();
    $executedVersions = $this->getExecutedMigrations();
    $applied = array_map(fn() => null, array_flip($executedVersions));
    /** @var AvailableMigration $migration */
    foreach ($allMigrations->getItems() as $migration) {
      $version = substr((string)$migration->getVersion(), -14);
      if (in_array($version, $executedVersions)) {
        $applied[$version] = $migration->getMigration()->getDescription();
      }
    }

    ksort($applied);

    $this->appliedMigrations = $applied;

    return $this->appliedMigrations;
  }

  /** {@inheritdoc} */
  public function getUnapplied(): array
  {
    if ($this->unappliedMigrations !== null) {
      return $this->unappliedMigrations;
    }
    $dependencyFactory = $this->getDependencyFactory();
    $allMigrations = $dependencyFactory->getMigrationPlanCalculator()->getMigrations();
    $executedVersions = $this->getExecutedMigrations();
    $unapplied = [];
    /** @var AvailableMigration $migration */
    foreach ($allMigrations->getItems() as $migration) {
      $version = substr((string)$migration->getVersion(), -14);
      if (in_array($version, $executedVersions)) {
        continue;
      }
      $unapplied[$version] = $migration->getMigration()->getDescription();
    }

    ksort($unapplied);

    $this->unappliedMigrations = $unapplied;

    return $this->unappliedMigrations;
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
    $planItems = $plan->getItems();
    foreach ($planItems as $planItem) {
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

    foreach ($planItems as $planItem) {
      $versionString = substr((string)$planItem->getVersion(), -14);
      $description = $planItem->getMigration()->getDescription();
      if ($direction === EnumMigrationDirection::UP) {
        $this->appliedMigrations[$versionString] = $description;
        unset($this->unappliedMigrations[$versionString]);
        $this->executedMigrations[] = $versionString;
      } else {
        $this->unappliedMigrations[$versionString] = $description;
        unset($this->appliedMigrations[$versionString]);
        $index = array_search($versionString, $this->executedMigrations);
        if ($index !== false) {
          unset($this->executedMigrations[$versionString]);
        }
      }
    }
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
        MigrationsNamespace::class => realpath(__DIR__ . '/../Maintenance/Migrations'),
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
      configurationLoader: $configurationLoader,
      emLoader: new ExistingEntityManager($this->entityManager),
      logger: $this->logger,
      appContainer: $this->appContainer,
    );
  }
}
