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

use OCA\CAFEVDB\Common\Util;

/**Use this as the actual EntityManager in order to be able to
 * construct it without a Factory and to define an extension point for
 * later.
 */
class EntityManager extends EntityManagerDecorator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const ENTITY_PATHS = [
    __DIR__ . "/Doctrine/ORM/Entities",
  ];
  const PROXY_DIR = __DIR__ . "/Doctrine/ORM/Proxies";
  const DEV_MODE = true;

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
    //list($config, $eventManager) = $this->createSimpleConfiguration();
    list($config, $eventManager) = $this->createExtendedConfiguration();
    if (self::DEV_MODE) {
      $config->setAutoGenerateProxyClasses(true);
    } else {
      $config->setAutoGenerateProxyClasses(false);
    }

    $config->addCustomStringFunction('timestampdiff', 'Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff');

    $config->addCustomHydrationMode('COLUMN_HYDRATOR', ColumnHydrator::class);

    $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
    $config->setNamingStrategy($namingStrategy);

    // obtaining the entity manager
    $entityManager = \Doctrine\ORM\EntityManager::create($this->connectionParameters($params), $config, $eventManager);

    return $entityManager;
  }

  private function createSimpleConfiguration()
  {
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration(self::ENTITY_PATHS, self::DEV_MODE, self::PROXY_DIR, $cache, $useSimpleAnnotationReader);
    return [ $config, null ];
  }

  private function createExtendedConfiguration()
  {
    // don't call internals directly
    $this->createSimpleConfiguration();

    // globally used cache driver, in production use APC or memcached
    $cache = new \Doctrine\Common\Cache\ArrayCache;

    // standard annotation reader
    $annotationReader = new \Doctrine\Common\Annotations\AnnotationReader;
    $cachedAnnotationReader = new \Doctrine\Common\Annotations\CachedReader(
      $annotationReader, // use reader
      $cache // and a cache driver
    );

    // create a driver chain for metadata reading
    $driverChain = new \Doctrine\ORM\Mapping\Driver\DriverChain();

    // load superclass metadata mapping only, into driver chain
    // also registers Gedmo annotations.NOTE: you can personalize it
    \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
      $driverChain, // our metadata driver chain, to hook into
      $cachedAnnotationReader // our cached annotation reader
    );

    // now we want to register our application entities,
    // for that we need another metadata driver used for Entity namespace
    $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
      $cachedAnnotationReader, // our cached annotation reader
      self::ENTITY_PATHS, // paths to look in
    );

    // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
    // register annotation driver for our application Entity namespace
    $driverChain->addDriver($annotationDriver, 'OCA\CAFEVDB\Database\\Doctrine\ORM\Entities');

    // general ORM configuration
    $config = new \Doctrine\ORM\Configuration;
    $config->setProxyDir(self::PROXY_DIR);
    $config->setProxyNamespace('OCA\CAFEVDB\Database\\Doctrine\ORM\Proxies');
    $config->setAutoGenerateProxyClasses(self::DEV_MODE); // this can be based on production config.

    // register metadata driver
    $config->setMetadataDriverImpl($driverChain);

    // use our already initialized cache driver
    $config->setMetadataCacheImpl($cache);
    $config->setQueryCacheImpl($cache);

    // Third, create event manager and hook prefered extension listeners
    $evm = new \Doctrine\Common\EventManager();

    // gedmo extension listeners

    // loggable
    $loggableListener = new \Gedmo\Loggable\LoggableListener;
    $loggableListener->setAnnotationReader($cachedAnnotationReader);
    $loggableListener->setUsername($this->encryptionService->userId());
    $evm->addEventSubscriber($loggableListener);

    // timestampable
    $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
    $timestampableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($timestampableListener);

    // soft deletable
    $config->addFilter('soft-deleteable', '\Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter');

    // sortable
    $sortableListener = new \Gedmo\Sortable\SortableListener;
    $sortableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($sortableListener);

    // mysql set names UTF-8 if required
    $evm->addEventSubscriber(new \Doctrine\DBAL\Event\Listeners\MysqlSessionInit());

    return [ $config, $evm ];
  }

  public function columnName($propertyName)
  {
    //return $this->getConfiguration()->getNamingStrategy()->propertyToColumnName($propertyName);
    return Util::camelCaseToDashes($propertyName, '_');
  }

  public function property($columnName)
  {
    return Util::dashesToCamelCase($columnName);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
