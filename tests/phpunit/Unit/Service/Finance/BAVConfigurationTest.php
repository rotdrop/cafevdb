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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use InvalidArgumentException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OCP\IConfig;

use OCA\CAFEVDB\Service\Finance\BAVConfiguration;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\SchemaTool;
use OCA\CAFEVDB\Wrapped\rotdrop\bav\Configuration\ConfigurationRegistry;
use OCA\CAFEVDB\Wrapped\rotdrop\bav\DataBackend\Orm\Doctrine\DoctrineBackendContainer;
use OCA\CAFEVDB\Wrapped\rotdrop\bav\DataBackend\Orm\Doctrine\DoctrineDataBackend;

#[Attributes\CoversClass(BAVConfiguration::class)]
class BAVConfigurationTest extends TestCase
{
  /**
   * @param array $settings Nextcloud system configuration.
   *
   * @param array $expected Expected DBAL connection parameters.
   */
  #[Attributes\DataProvider('databaseSettings')]
  public function testUsesNextcloudDatabase(array $settings, array $expected): void
  {
    $configuration = new BAVConfiguration($this->cloudConfig($settings));
    $backendContainer = $configuration->getDataBackendContainer();
    self::assertInstanceOf(DoctrineBackendContainer::class, $backendContainer);
    $parameters = $backendContainer->getEntityManager()->getConnection()->getParams();

    self::assertSame($expected, $parameters);
    self::assertTrue($configuration->isAutomaticInstallation());
  }

  /** @return array */
  public static function databaseSettings(): array
  {
    $credentials = ['user' => '', 'password' => '', 'driverOptions' => []];
    return [
      'default SQLite' => [
        [],
        ['driver' => 'pdo_sqlite'] + $credentials + ['path' => \OC::$SERVERROOT . '/data/nextcloud.db'],
      ],
      'configured SQLite' => [
        ['dbtype' => 'sqlite3', 'datadirectory' => '/cloud-data', 'dbname' => 'cloud'],
        ['driver' => 'pdo_sqlite'] + $credentials + ['path' => '/cloud-data/cloud.db'],
      ],
      'MySQL' => [
        ['dbtype' => 'mysql', 'dbhost' => 'database', 'dbname' => 'cloud', 'dbuser' => 'cloud'],
        ['driver' => 'pdo_mysql', 'user' => 'cloud', 'password' => '', 'driverOptions' => [], 'dbname' => 'cloud', 'host' => 'database'],
      ],
      'MySQL port' => [
        ['dbtype' => 'mysql', 'dbhost' => 'database:3307'],
        ['driver' => 'pdo_mysql'] + $credentials + ['dbname' => 'nextcloud', 'port' => 3307, 'host' => 'database'],
      ],
      'MySQL socket' => [
        ['dbtype' => 'mysql', 'dbhost' => 'localhost:/run/mysqld/mysqld.sock'],
        ['driver' => 'pdo_mysql'] + $credentials + ['dbname' => 'nextcloud', 'unix_socket' => '/run/mysqld/mysqld.sock', 'host' => 'localhost'],
      ],
      'PostgreSQL IPv6 port' => [
        ['dbtype' => 'pgsql', 'dbhost' => '[::1]:5433'],
        ['driver' => 'pdo_pgsql'] + $credentials + ['dbname' => 'nextcloud', 'port' => 5433, 'host' => '::1'],
      ],
      'PostgreSQL IPv6' => [
        ['dbtype' => 'pgsql', 'dbhost' => '::1'],
        ['driver' => 'pdo_pgsql'] + $credentials + ['dbname' => 'nextcloud', 'host' => '::1'],
      ],
      'driver options' => [
        ['dbtype' => 'mysql', 'dbdriveroptions' => [\PDO::ATTR_TIMEOUT => 10]],
        ['driver' => 'pdo_mysql', 'user' => '', 'password' => '', 'driverOptions' => [\PDO::ATTR_TIMEOUT => 10], 'dbname' => 'nextcloud', 'host' => 'localhost'],
      ],
      'PostgreSQL TLS' => [
        ['dbtype' => 'pgsql', 'pgsql_ssl' => ['mode' => 'verify-full', 'rootcert' => '/cloud/ca.pem']],
        ['driver' => 'pdo_pgsql'] + $credentials + ['dbname' => 'nextcloud', 'host' => 'localhost', 'sslmode' => 'verify-full', 'sslrootcert' => '/cloud/ca.pem'],
      ],
    ];
  }

  public function testUnsupportedDatabaseDoesNotFallBackToFiles(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported Nextcloud database type: unsupported');

    new BAVConfiguration($this->cloudConfig(['dbtype' => 'unsupported']));
  }

  public function testExistingBankTablesAreSharedAcrossConnections(): void
  {
    $dataDir = sys_get_temp_dir() . '/cafevdb-bav-' . bin2hex(random_bytes(8));
    self::assertTrue(mkdir($dataDir));
    $settings = ['dbtype' => 'sqlite', 'datadirectory' => $dataDir, 'dbname' => 'cloud', 'dbtableprefix' => 'oc_'];
    $previousConfiguration = ConfigurationRegistry::getConfiguration();
    $seedConnection = null;
    $connection = null;

    try {
      $seedConfiguration = new BAVConfiguration($this->cloudConfig($settings));
      $entityManager = $seedConfiguration->getDataBackendContainer()->getEntityManager();
      $seedConnection = $entityManager->getConnection();
      (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
      $seedConnection->insert('bav_bank', ['id' => 70010080, 'validator' => '00']);
      $seedConnection->insert('bav_agency', [
        'id' => 1,
        'bank' => 70010080,
        'bic' => 'PBNKDEFFXXX',
        'city' => 'Bonn',
        'postcode' => '53113',
        'shortTerm' => 'Postbank',
        'name' => 'Postbank',
      ]);
      $seedConnection->update('bav_bank', ['mainAgency' => 1], ['id' => 70010080]);
      $seedConnection->insert('bav_meta', ['name' => 'lastModified', 'value' => (string)time()]);
      $seedConnection->close();

      $configuration = new BAVConfiguration($this->cloudConfig($settings));
      ConfigurationRegistry::setConfiguration($configuration);
      $entityManager = $configuration->getDataBackendContainer()->getEntityManager();
      $connection = $entityManager->getConnection();
      $backend = new DoctrineDataBackend($entityManager);

      self::assertTrue($backend->isInstalled());
      $agency = $backend->getMainAgency($backend->getBank('70010080'));
      self::assertSame('PBNKDEFFXXX', $agency->getBIC());
      self::assertSame('Postbank', $agency->getName());
      self::assertSame('Bonn', $agency->getCity());
      $tables = $connection->createSchemaManager()->listTableNames();
      sort($tables);
      self::assertSame(['bav_agency', 'bav_bank', 'bav_meta'], $tables);
    } finally {
      ConfigurationRegistry::setConfiguration($previousConfiguration);
      $connection?->close();
      $seedConnection?->close();
      if (file_exists($dataDir . '/cloud.db')) {
        unlink($dataDir . '/cloud.db');
      }
      rmdir($dataDir);
    }
  }

  /** @return IConfig */
  private function cloudConfig(array $settings): IConfig
  {
    $config = $this->createStub(IConfig::class);
    $config->method('getSystemValue')
      ->willReturnCallback(fn(string $key, mixed $default = '') => $settings[$key] ?? $default);
    return $config;
  }
}
