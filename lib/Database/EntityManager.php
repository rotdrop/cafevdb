<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database;

use OCP\ILogger;
use OCP\IL10N;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;

use Ramsey\Uuid\Doctrine as Ramsey;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldKind;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumMemberStatus;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType;

use OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator;

/**Use this as the actual EntityManager in order to be able to
 * construct it without a Factory and to define an extension point for
 * later.
 */
class EntityManager extends EntityManagerDecorator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var \Doctrine\ORM\EntityManager */
  private $entityManager;

  /** @var \OCA\CAFEVDB\Service\EncryptionService */
  private $encryptionService;

  // @@TODO catch failures, allow construction without database for
  // initial setup.
  public function __construct(
    EncryptionService $encryptionService
    , ILogger $logger
    , IL10N $l10n
  )
  {
    $this->encryptionService = $encryptionService;
    $this->logger = $logger;
    $this->l = $l10n;
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    }
  }

  private function connected()
  {
    $connection = $this->entityManager->getConnection();
    $params = $connection->getParams();
    $impossible = false;
    foreach ([ 'host', 'user', 'password', 'dbname' ] as $key) {
      if (empty($params[$key])) {
        $impossible = true;
      }
    }
    if ($impossible) {
      $this->logError('Unable to access database, connection parameters are unset');
      return false;
    }
    if (!$connection->ping()) {
      if (!$connection->connect()) {
        $this->logError('db cannot connect');
        return false;
      }
    }
    return true;
  }

  private function registerTypes()
  {
    $types = [
      EnumExtraFieldKind::class => 'enum',
      EnumExtraFieldMultiplicity::class => 'enum',
      EnumMemberStatus::class => 'enum',
      EnumProjectTemporalType::class => 'enum',
      EnumVCalendarType::class => 'enum',
      Ramsey\UuidType::class => null,
      Ramsey\UuidBinaryType::class => 'binary',
      Ramsey\UuidBinaryOrderedTimeType::class => 'binary',
    ];

    $connection = $this->entityManager->getConnection();
    try {
      $platform = $connection->getDatabasePlatform();
      foreach ($types as $type => $sqlType) {
        $instance = new $type;
        $typeName = $instance->getName();
        Type::addType($typeName, $type);
        if (!empty($sqlType)) {
          $platform->registerDoctrineTypeMapping($sqlType, $typeName);
        }
      }
    } catch (\Throwable $t) {
      $this->logException($t);
    }
  }

  private function connectionParameters($params = null) {
    $connectionParams = [
      'dbname' => $this->encryptionService->getConfigValue('dbname'),
      'user' => $this->encryptionService->getConfigValue('dbuser'),
      'password' => $this->encryptionService->getConfigValue('dbpassword'),
      'host' => $this->encryptionService->getConfigValue('dbserver'),
    ];
    $driverParams = [
      'driver' => 'pdo_mysql',
      'wrapperClass' => Connection::class,
      'configService' => $this->configService,
    ];
    $charSetParams = [
      'collate' => 'utf8mb4_bin',
      'charset' => 'utf8mb4',
      'row_format' => 'compressed',
    ];
    !is_array($params) && ($params = []);
    $connectionParams = array_merge($connectionParams, $params, $driverParams, $charSetParams);
    return $connectionParams;
  }

  // Create a simple "default" Doctrine ORM configuration for Annotations
  private function getEntityManager($params = null)
  {
    $paths = [
      __DIR__ . "/Doctrine/ORM/Entities",
    ];
    $isDevMode = true;
    $proxyDir = __DIR__ . "/Doctrine/ORM/Proxies";
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration($paths, $isDevMode, $proxyDir, $cache, $useSimpleAnnotationReader);
    if ($isDevMode) {
      $config->setAutoGenerateProxyClasses(true);
    } else {
      $config->setAutoGenerateProxyClasses(false);
    }

    $config->addCustomStringFunction('timestampdiff', 'Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff');

    $config->addCustomHydrationMode('COLUMN_HYDRATOR', ColumnHydrator::class);

    $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
    $config->setNamingStrategy($namingStrategy);

    // obtaining the entity manager
    $entityManager = \Doctrine\ORM\EntityManager::create($this->connectionParameters($params), $config);

    return $entityManager;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
