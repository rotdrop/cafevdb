<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumMemberStatus;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumVCalendarType;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;

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

  /** @var CloudLogger */
  private $sqlLogger;

  // @@TODO catch failures, allow construction without database for
  // initial setup.
  public function __construct(
    EncryptionService $encryptionService
    , CloudLogger $sqlLogger
    , ILogger $logger
    , IL10N $l10n
  )
  {
    $this->encryptionService = $encryptionService;
    $this->sqlLogger = $sqlLogger;
    $this->logger = $logger;
    $this->l = $l10n;
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    }
  }

  public function reopen()
  {
    $this->close();
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
      EnumExtraFieldDataType::class => 'enum',
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

    $config->setSQLLogger($this->sqlLogger);

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
    $loggableListener->setUsername($this->encryptionService->userId()?:'unknown');
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

    $evm->addEventListener([ \Doctrine\ORM\Tools\ToolEvents::postGenerateSchema ], $this);

    return [ $config, $evm ];
  }

  /**
   * Remove unwanted constraints after schema generation.
   *
   * @param \Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $args
   *
   * @todo See that this is not necessary.
   */
  public function postGenerateSchema(\Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $args)
  {
    $schema = $args->getSchema();
    $em = $args->getEntityManager();
    foreach ($schema->getTables() as $table) {

        // tweak foreign keys
      foreach ($table->getForeignKeys() as $foreignKey) {
        if (false && $foreignKey->getForeignTableName() == 'ProjectInstrumentationNumbers') {
          $table->removeForeignKey($foreignKey->getName());
        }
      }

      $enumColumns = [];
      // inject enum values into comments
      foreach ($table->getColumns() as $column) {
        if ($column->getType() instanceof EnumType) {
          $enumColumns[] = $column;
        }
      }

      /** @var \Doctrine\DBAL\Schema\Column $column */
      foreach ($enumColumns as $column) {
        $column->setComment(trim(sprintf('%s (%s)', $column->getComment(), implode(',', $column->getType()->getValues()))));
      }
    }
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

  /**
   * Persist an entity after performing some special tweaks:
   *
   * - nesting primary foreign keys is not supported, but a
   *   work-around can be established by additional associations. Fill
   *   those to-one associations with references if the necessary keys
   *   are available.
   *
   * Then just move the modified entity on to the ordinary persister.
   */
  public function persist($entity)
  {
    $meta = $this->getClassMetadata(get_class($entity));
    if ($meta->containsForeignIdentifier) {
      $columnValues = $this->getIdentifierColumnValues($entity, $meta);

      foreach ($meta->associationMappings as $property => $association) {

        if (!$meta->isSingleValuedAssociation($property)) {
          // only to-one makes sense
          continue;
        }

        if (array_search($property, $meta->identifier) !== false) {
          // all primary keys must already be there
          continue;
        }

        if (!empty($meta->getFieldValue($entity, $property))) {
          // don't override values already present
          continue;
        }

        try {
          $targetEntity = $association['targetEntity'];
          $targetMeta = $this->getClassMetadata($targetEntity);

          $targetEntityId = $this->extractKeyValues($targetMeta, $columnValues);
          $reference = $this->getReference($targetEntity, $targetEntityId);

          $meta->setFieldValue($entity, $property, $reference);

        } catch (\Throwable $t) {
          $this->logException($t);
        }
      }

    }
    return parent::persist($entity);
  }

  /**
   * The related MetaData::getIdentifierValues() function does not
   * handle recursive into associations. Extract the column values of
   * primary foreign keys by recursing into the meta-data.
   *
   * @return array
   * ```
   * [ COLUMN1 => VALUE1, ... ]
   * ```
   */
  private function getIdentifierColumnValues($entity, $meta)
  {
    $columnValues = [];
    foreach ($meta->getIdentifierValues($entity) as $field => $value) {
      if (isset($meta->associationMappings[$field])) {
        $association = $meta->associationMappings[$field];
        $targetEntity = $association['targetEntity'];
        $targetMeta = $this->getClassMetadata($targetEntity);
        if (count($association['joinColumns']) != 1) {
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $joinInfo = $association['joinColumns'][0];
        $columnName = $joinInfo['name'];
        $targetColumn = $joinInfo['referencedColumnName'];
        $targetField = $targetMeta->fieldNames[$targetColumn];
        if ($value instanceof $targetEntity) {
          $columnValues[$columnName] = $targetMeta->getFieldValue($value, $targetField);
        } else {
          // assume this is the column value, not the entity of the foreign key
          $columnValues[$columnName] = $value;
          // replace the value by a reference
          $reference = $this->getReference($targetEntity, [ $targetColumn => $value ]);
          $meta->setFieldValue($entity, $field, $reference);
        }
      } else {
        $columnName = $meta->fieldMappings[$field]['columnName'];
        $columnValues[$columnName] = $value;
      }
    }
    return $columnValues;
  }

  /**
   * Generate ids for use with find and self::persist() from database
   * column values. $columnValues is allowed to contain excess data
   * which comes in handy when recursing into associations.
   *
   * @param \Doctrine\ORM\Mapping\ClassMetadataInfo $meta Class-meta.
   *
   * @param array $columnValues The actual identifier values indexed by
   * the database column names (read: not the entity-class-names, but
   * the raw column names in the database).
   *
   * @return array
   * ```
   * [
   *   PROPERTY => ELEMENTARY_FIELD_VALUE,
   *   ...
   * ]
   * ```
   */
  public function extractKeyValues($meta, array $columnValues):array
  {
    $entityId = [];
    foreach ($meta->identifier as $field) {
      if (isset($meta->associationMappings[$field])) {
        if (count($meta->associationMappings[$field]['joinColumns']) != 1) {
          throw new \Exception($this->l->t('Foreign keys as principle keys cannot be composite'));
        }
        $columnName = $meta->associationMappings[$field]['joinColumns'][0]['name'];
      } else {
        $columnName = $meta->fieldMappings[$field]['columnName'];
        if (!isset($columnValues[$columnName])) {
          throw new \Exception($this->l->t('Unexpected id: %s', $field));
        }
      }
      $entityId[$field] = $columnValues[$columnName];
    }
    return $entityId;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
