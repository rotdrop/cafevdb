<?php
/**
 * Orchestra member, musician and project management application.
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

use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Configuration;

use MediaMonks\Doctrine\Transformable;
use Ramsey\Uuid\Doctrine as Ramsey;
use CJH\Doctrine\Extensions as CJH;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use MyCLabs\Enum\Enum as EnumType;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;

use OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;

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

  /** @var array Cache of entity names indexed by table names. */
  private $entityNames = null;

  /** @var string */
  private $userId;

  /** @var IRequest */
  private $request;

  /** @var IUserSession */
  private $userSession;

  /** @var bool */
  private $debug;

  // @@todo catch failures, allow construction without database for
  // initial setup.
  public function __construct(
    EncryptionService $encryptionService
    , CloudLogger $sqlLogger
    , IRequest $request
    , ILogger $logger
    , IL10N $l10n
  )
  {
    $this->encryptionService = $encryptionService;
    $this->sqlLogger = $sqlLogger;
    $this->request = $request;
    $this->userSession = $userSession;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->userId = $this->encryptionService->getUserId()?:$this->l->t('unknown');
    $this->debug = 0 != ($encryptionService->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY);
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    }
  }

  public function suspendLogging()
  {
    $this->sqlLogger->disable();
  }

  public function resumeLogging()
  {
    $this->sqlLogger->enable($this->debug);
  }

  /*
   * @return null|string The user-id of the currently logged-in user
   * if known.
   */
  public function getUserId():string
  {
    return $this->userId;
  }

  /*
   * Reopen the entity-manager after it has been closed, e.g. after a
   * failed transaction.
   */
  public function reopen()
  {
    $this->close();
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    // if ($this->connected()) {
    // $this->registerTypes();
    // }
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
      Types\EnumExtraFieldDataType::class => 'enum',
      Types\EnumExtraFieldMultiplicity::class => 'enum',
      Types\EnumMemberStatus::class => 'enum',
      Types\EnumGeographicalScope::class => 'enum',
      Types\EnumProjectTemporalType::class => 'enum',
      Types\EnumVCalendarType::class => 'enum',
      Ramsey\UuidType::class => null,
      Ramsey\UuidBinaryType::class => 'binary',
      Ramsey\UuidBinaryOrderedTimeType::class => 'binary',
    ];

    $connection = $this->entityManager->getConnection();
    try {
      $platform = $connection->getDatabasePlatform();
      foreach ($types as $phpType => $sqlType) {
        if ($sqlType == 'enum') {
          $typeName = end(explode('\\', $phpType));
          Types\EnumType::registerEnumType($typeName, $phpType);

          // variant in lower case
          $blah = strtolower($typeName);
          Types\EnumType::registerEnumType($blah, $phpType);
          $platform->registerDoctrineTypeMapping($sqlType, $blah);

        } else {
          $instance = new $phpType;
          $typeName = $instance->getName();
          Type::addType($typeName, $phpType);
        }
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
    list($config, $eventManager) = $this->createSimpleConfiguration();
    //list($config, $eventManager) = $this->createKnpConfiguration($config, $eventManager);
    list($config, $eventManager) = $this->createGedmoConfiguration($config, $eventManager);

    // mysql set names UTF-8 if required
    $eventManager->addEventSubscriber(new \Doctrine\DBAL\Event\Listeners\MysqlSessionInit());

    $eventManager->addEventListener([ \Doctrine\ORM\Tools\ToolEvents::postGenerateSchema ], $this);

    if (self::DEV_MODE) {
      $config->setAutoGenerateProxyClasses(true);
    } else {
      $config->setAutoGenerateProxyClasses(false);
    }

    $this->registerCustomFunctions($config);

    $config->addCustomHydrationMode('COLUMN_HYDRATOR', ColumnHydrator::class);

    $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
    $config->setNamingStrategy($namingStrategy);

    $config->setSQLLogger($this->sqlLogger);

    // obtaining the entity manager
    $entityManager = \Doctrine\ORM\EntityManager::create($this->connectionParameters($params), $config, $eventManager);

    return $entityManager;
  }

  /**
   * @param Configuration $config
   */
  private function registerCustomFunctions(Configuration $config)
  {
    // $config->addCustomStringFunction('timestampdiff', 'Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff');
    $config->addCustomDatetimeFunction('timestampdiff', 'DoctrineExtensions\Query\Mysql\TimestampDiff');
    $config->addCustomStringFunction('greatest', 'DoctrineExtensions\Query\Mysql\Greatest');
  }

  private function createSimpleConfiguration():array
  {
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration(self::ENTITY_PATHS, self::DEV_MODE, self::PROXY_DIR, $cache, $useSimpleAnnotationReader);
    return [ $config, new \Doctrine\Common\EventManager(), ];
  }

  private function createKnpConfiguration($config, $evm)
  {
    //$loggable = new \Knp\DoctrineBehaviors\EventSubscriber\LoggableSubscriber($this->psrLogger);
    //$evm->addEventSubscriber($loggable);

    $timeStampable = new \Knp\DoctrineBehaviors\EventSubscriber\TimestampableSubscriber('datetime');
    $evm->addEventSubscriber($timeStampable);

    $softDeletable = new \Knp\DoctrineBehaviors\EventSubscriber\SoftDeletableSubscriber;
    $evm->addEventSubscriber($softDeletable);

    return [ $config, $evm, ];
  }

  private function createGedmoConfiguration($config, $evm)
  {
    // globally used cache driver, in production use APC or memcached
    $cache = new \Doctrine\Common\Cache\ArrayCache;

    // standard annotation reader
    $annotationReader = new \Doctrine\Common\Annotations\AnnotationReader;
    $cachedAnnotationReader = new \Doctrine\Common\Annotations\CachedReader(
      $annotationReader, // use reader
      $cache // and a cache driver
    );

    // create a driver chain for metadata reading
    $driverChain = new \Doctrine\Persistence\Mapping\Driver\MappingDriverChain();

    // load superclass metadata mapping only, into driver chain
    // also registers Gedmo annotations.NOTE: you can personalize it
    \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
      $driverChain, // our metadata driver chain, to hook into
      $cachedAnnotationReader // our cached annotation reader
    );
    //<<< Further annotations can go here
    \MediaMonks\Doctrine\DoctrineExtensions::registerAnnotations();
    CJH\Setup::registerAnnotations();
    //>>>

    // now we want to register our application entities,
    // for that we need another metadata driver used for Entity namespace
    $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
      $cachedAnnotationReader, // our cached annotation reader
      self::ENTITY_PATHS, // paths to look in
    );

    // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
    // register annotation driver for our application Entity namespace
    $driverChain->addDriver($annotationDriver, 'OCA\CAFEVDB\Database\Doctrine\ORM\Entities');

    // general ORM configuration
    //$config = new \Doctrine\ORM\Configuration;
    $config->setProxyDir(self::PROXY_DIR);
    $config->setProxyNamespace('OCA\CAFEVDB\Database\Doctrine\ORM\Proxies');
    $config->setAutoGenerateProxyClasses(self::DEV_MODE); // this can be based on production config.

    // register metadata driver
    $config->setMetadataDriverImpl($driverChain);

    // use our already initialized cache driver
    $config->setMetadataCacheImpl($cache);
    $config->setQueryCacheImpl($cache);

    // gedmo extension listeners

    // loggable
    //$loggableListener = new \Gedmo\Loggable\LoggableListener;
    $userName = $this->userId;
    $remoteAddress = $this->request->getRemoteAddress();
    $loggableListener = new Listeners\GedmoLoggableListener($userName, $remoteAddress);
    $loggableListener->setAnnotationReader($cachedAnnotationReader);
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

    // encryption
    $transformerPool = new Transformable\Transformer\TransformerPool();
    $transformerPool['encrypt'] = new class($this->encryptionService) implements Transformable\Transformer\TransformerInterface {

      private $encryptionKey;

      private $encryptionService;

      public function __construct($encryptionService)
      {
        $this->encryptionService = $encryptionService;
        $this->encryptionKey = $this->encryptionService->getAppEncryptionKey();
      }

      public function transform($value)
      {
        return $this->encryptionService->encrypt($value, $this->encryptionKey);
      }

      public function reverseTransform($value)
      {
        return $this->encryptionService->decrypt($value, $this->encryptionKey);
      }

    };
    $transformableListener = new Transformable\TransformableSubscriber($transformerPool);
    $transformableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($transformableListener);

    // handle extra foreign key constraints
    $foreignKeyListener = new CJH\ForeignKey\Listener($this);
    $foreignKeyListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($foreignKeyListener);

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
        if ($column->getType() instanceof Types\EnumType) {
          $enumColumns[] = $column;
        }
      }

      /** @var \Doctrine\DBAL\Schema\Column $column */
      foreach ($enumColumns as $column) {
        $column->setComment(trim(sprintf('%s enum(%s)', $column->getComment(), implode(',', $column->getType()->getValues()))));
      }
    }
  }

  public function isTransactionActive()
  {
    return $this->entityManager->getConnection()->isTransactionActive();
  }

  public function getTransactionNestingLevel()
  {
    return $this->entityManager->getConnection()->getTransactionNestingLevel();
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
   * handle recursion into associations. Extract the column values of
   * primary foreign keys by recursing into the meta-data.
   *
   * @param mixed $entity The entity to extract the values from.
   *
   * @param \Doctrine\ORM\Mapping\ClassMetadata $meta The meta-data
   * for the given $entity.
   *
   * @return array
   * ```
   * [ COLUMN1 => VALUE1, ... ]
   * ```
   * The array is indexed by the database column-names.
   *
   * @note As a side-effect, $entity is modified if a given foreign
   * key is just a simple identifier value (like an int) and not en
   * entity instance or reference.
   */
  public function getIdentifierColumnValues($entity, $meta)
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
          $reference = $this->getReference($targetEntity, [ $targetField => $value ]);
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
          if ($meta->usesIdGenerator()) {
            // possibly an attempt to extract from non-existing field.
            continue;
          }
          throw new \Exception(
            $this->l->t('Missing value and no generator for identifier field: %s', $field));
        }
      }
      $entityId[$field] = $columnValues[$columnName];
    }
    return $entityId;
  }

  /**
   * Compute the mapping between entity-properties ("field-name") and
   * plain SQL column-names. This is somewhat complicated when foreign
   * keys are used.
   *
   *
   * @param \Doctrine\ORM\Mapping\ClassMetadataInfo $meta Class-meta.
   *
   * @return array
   * ```
   * [
   *   PROPERTY => SQL_COLUMN_NAME
   *   ...
   * ]
   * ```
   */
  public function identifierColumns($meta):array
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
      }
      $entityId[$field] = $columnName;
    }
    return $entityId;
  }

  private function createTableLookup()
  {
    $this->entityNames = [];
    $classNames = $this->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    foreach ($classNames as $className) {
      $classMetaData = $this->getClassMetadata($className);
      $this->entityNames[$classMetaData->getTableName()] = $className;
    }
  }

  public function entityOfTable(string $table): ?string
  {
    if (empty($this->entityNames)) {
      $this->createTableLookup();
    }
    return $this->entityNames[$table]?:null;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
