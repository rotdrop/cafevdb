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
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Annotations\Reader as AnnotationReader;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Setup;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Decorator\EntityManagerDecorator;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DatabaseConnection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform as DatabasePlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Configuration;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Doctrine as Ramsey;
use OCA\CAFEVDB\Wrapped\CJH\Doctrine\Extensions as CJH;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\MyCLabs\Enum\Enum as EnumType;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;

use OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy;

use OCA\CAFEVDB\Common\Util;

/**
 * Use this as the actual EntityManager in order to be able to
 * construct it without a Factory and to define an extension point for
 * later.
 *
 * @todo Some of the methods should rather go to a meta-data
 * decorator.
 */
class EntityManager extends EntityManagerDecorator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const ENTITY_PATHS = [
    __DIR__ . "/Doctrine/ORM/Entities",
  ];
  const PROXY_DIR = __DIR__ . "/Doctrine/ORM/Proxies";
  const DEV_MODE = true;

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager */
  private $entityManager;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var CloudLogger */
  private $sqlLogger;

  /** @var array Cache of entity names indexed by table names. */
  private $entityNames = null;

  /** @var array Cache of entity names indexed by class annotation */
  private $annotationEntites = [];

  /**
   * @var array Cache of property names indexed by class annotation.
   * ```
   * [
   *   'entity' => CLASSNAME,
   *   'properties' => [ PROP1, PROP2, ... ],
   * ]
   * ```
   */
  private $annotationProperties = [];

  /** @var string */
  private $userId;

  /** @var IRequest */
  private $request;

  /** @var IUserSession */
  private $userSession;

  /** @var IAppContainer */
  private $appContainer;

  /** @var bool */
  private $debug;

  /** @var bool */
  private $showSoftDeleted;

  /** @var IL10N */
  private $l;

  /** @var bool */
  private $typesBound;

  /** @var bool */
  private $decorateClassMetadata = true;

  /** @var AnnotationReader */
  private $annotationReader;

  /** @var Transformable\Transformer\TransformerPool */
  private $transformerPool;

  // initial setup.
  public function __construct(
    EncryptionService $encryptionService
    , IAppContainer $appContainer
    , CloudLogger $sqlLogger
    , IRequest $request
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->encryptionService = $encryptionService;
    $this->appContainer = $appContainer;
    $this->sqlLogger = $sqlLogger;
    $this->request = $request;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->userId = $this->encryptionService->getUserId()?:$this->l->t('unknown');
    if (!$this->encryptionService->bound()) {
      // @todo: try to bind to unencrypted service account
      return;
    }

    $this->debug = 0 != ($encryptionService->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY);
    $this->showSoftDeleted = $encryptionService->getUserValue($this->userId, 'showdisabled') === 'on';

    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    }
    $this->decorateClassMetadata = true;
  }

  public function getConnection():?DatabaseConnection
  {
    if (empty($this->entityManager)) {
      return null;
    }
    return $this->entityManager->getConnection();
  }

  public function getPlatform():?DatabasePlatform
  {
    $connection = $this->getConnection();
    return $connection ? $connection->getDatabasePlatform() : null;
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
    if (!$this->encryptionService->bound()) {
      return;
    }
    $this->close();
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    $this->debug = 0 != ($this->encryptionService->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY);
    if ($this->connected()) {
      $this->registerTypes();
    }
  }

  /**
   * Check for a valid database connection.
   *
   * @return bool
   */
  public function connected():bool
  {
    $connection = $this->getConnection();
    if (empty($connection)) {
      return false;
    }
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
    try {
      if (!$connection->ping()) {
        if (!$connection->connect()) {
          $this->logError('db cannot connect');
          return false;
        }
      }
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }
    return true;
  }

  private function registerTypes()
  {
    if ($this->typesBound) {
      return;
    }
    $types = [
      Types\EnumFileType::class => 'enum',
      Types\EnumDataTransformation::class => 'enum',
      Types\EnumSepaTransaction::class => 'enum',
      Types\EnumParticipantFieldDataType::class => 'enum',
      Types\EnumParticipantFieldMultiplicity::class => 'enum',
      Types\EnumGeographicalScope::class => 'enum',
      Types\EnumMemberStatus::class => 'enum',
      Types\EnumProjectTemporalType::class => 'enum',
      Types\EnumVCalendarType::class => 'enum',
      // Ramsey\UuidType::class => null,
      // Ramsey\UuidBinaryType::class => 'binary',
      // Ramsey\UuidBinaryOrderedTimeType::class => 'binary',
      Types\UuidType::class => 'binary',
    ];

    $connection = $this->entityManager->getConnection();
    try {
      $platform = $connection->getDatabasePlatform();
      foreach ($types as $phpType => $sqlType) {
        if ($sqlType == 'enum') {
          $typeName = substr(strrchr($phpType, '\\'), 1);
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

      // Override datetime stuff
      Type::overrideType('datetime', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeType::class);
      Type::overrideType('datetime_immutable', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeImmutableType::class);
      Type::overrideType('datetimetz', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeType::class);
      Type::overrideType('datetimetz_immutable', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeImmutableType::class);
      $this->typesBound = true;
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

  private function getEntityManager($params = null)
  {
    list($config, $eventManager) = $this->createSimpleConfiguration();
    list($config, $eventManager, $annotationReader) = $this->createGedmoConfiguration($config, $eventManager);

    $this->annotationReader = $annotationReader;

    // mysql set names UTF-8 if required
    $eventManager->addEventSubscriber(new \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Event\Listeners\MysqlSessionInit());

    $eventManager->addEventListener([ \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\ToolEvents::postGenerateSchema ], $this);

    if (self::DEV_MODE) {
      $config->setAutoGenerateProxyClasses(true);
    } else {
      $config->setAutoGenerateProxyClasses(false);
    }

    $this->registerCustomFunctions($config);

    $config->addCustomHydrationMode('COLUMN_HYDRATOR', ColumnHydrator::class);

    $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
    $config->setNamingStrategy($namingStrategy);

    $quoteStrategy = new ReservedWordQuoteStrategy();
    $config->setQuoteStrategy($quoteStrategy);

    $config->setSQLLogger($this->sqlLogger);

    // obtaining the entity manager
    $entityManager = \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager::create($this->connectionParameters($params), $config, $eventManager);

    if (!$this->showSoftDeleted) {
      $entityManager->getFilters()->enable('soft-deleteable');
    }

    return $entityManager;
  }

  /**
   * @param Configuration $config
   */
  private function registerCustomFunctions(Configuration $config)
  {
    // $config->addCustomStringFunction('timestampdiff', \OCA\CAFEVDB\Wrapped\Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff::class);
    $config->addCustomDatetimeFunction('timestampdiff', \OCA\CAFEVDB\Wrapped\DoctrineExtensions\Query\Mysql\TimestampDiff::class);
    $config->addCustomStringFunction('greatest', \OCA\CAFEVDB\Wrapped\DoctrineExtensions\Query\Mysql\Greatest::class);
  }

  private function createSimpleConfiguration():array
  {
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration(self::ENTITY_PATHS, self::DEV_MODE, self::PROXY_DIR, $cache, $useSimpleAnnotationReader);
    return [ $config, new \OCA\CAFEVDB\Wrapped\Doctrine\Common\EventManager(), ];
  }

  private function createGedmoConfiguration($config, $evm)
  {
    // globally used cache driver, in production use APC or memcached
    $cache = new \OCA\CAFEVDB\Wrapped\Doctrine\Common\Cache\ArrayCache;

    // standard annotation reader
    $annotationReader = new \OCA\CAFEVDB\Wrapped\Doctrine\Common\Annotations\AnnotationReader;
    $cachedAnnotationReader = new \OCA\CAFEVDB\Wrapped\Doctrine\Common\Annotations\CachedReader(
      $annotationReader, // use reader
      $cache // and a cache driver
    );

    // create a driver chain for metadata reading
    $driverChain = new \OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\Driver\MappingDriverChain();

    // load superclass metadata mapping only, into driver chain
    // also registers Gedmo annotations.NOTE: you can personalize it
    \OCA\CAFEVDB\Wrapped\Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
      $driverChain, // our metadata driver chain, to hook into
      $cachedAnnotationReader // our cached annotation reader
    );
    //<<< Further annotations can go here
    \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\DoctrineExtensions::registerAnnotations();
    CJH\Setup::registerAnnotations();
    //>>>

    // now we want to register our application entities,
    // for that we need another metadata driver used for Entity namespace
    $annotationDriver = new \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\Driver\AnnotationDriver(
      $cachedAnnotationReader, // our cached annotation reader
      self::ENTITY_PATHS, // paths to look in
    );

    // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
    // register annotation driver for our application Entity namespace
    $driverChain->addDriver($annotationDriver, 'OCA\CAFEVDB\Database\Doctrine\ORM\Entities');

    // general ORM configuration
    //$config = new \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Configuration;
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
    //$loggableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Loggable\LoggableListener;
    $remoteAddress = $this->request->getRemoteAddress();
    $loggableListener = new Listeners\GedmoLoggableListener($this->userId, $remoteAddress);
    $loggableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($loggableListener);

    // timestampable
    $timestampableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Timestampable\TimestampableListener();
    $timestampableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($timestampableListener);

    // soft deletable
    $softDeletableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\SoftDeleteable\SoftDeleteableListener();
    $softDeletableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($softDeletableListener);
    $config->addFilter('soft-deleteable', \OCA\CAFEVDB\Wrapped\Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter::class);

    // blameable
    $blameableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Blameable\BlameableListener();
    $blameableListener->setAnnotationReader($cachedAnnotationReader);
    $blameableListener->setUserValue($this->userId);
    $evm->addEventSubscriber($blameableListener);

    // sluggable
    $sluggableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Sluggable\SluggableListener();
    $sluggableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($sluggableListener);

    // sortable
    // $sortableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Sortable\SortableListener;
    // $sortableListener->setAnnotationReader($cachedAnnotationReader);
    // $evm->addEventSubscriber($sortableListener);

    // encryption
    $transformerPool = new Transformable\Transformer\TransformerPool();
    $transformerPool['encrypt'] = new Listeners\Transformable\Encryption($this->encryptionService);
    $transformerPool['hash'] = new Transformable\Transformer\PhpHashTransformer([
      'algorithm' => 'sha256',
      'binary' => false,
    ]);
    $this->transformerPool = $transformerPool;
    $transformableListener = new Transformable\TransformableSubscriber($transformerPool);
    $transformableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($transformableListener);

    // translatable
    $translatableListener = $this->appContainer->get(Listeners\GedmoTranslatableListener::class);
    // current translation locale should be set from session or hook later into the listener
    // most important, before entity manager is flushed
    $translatableListener->setTranslatableLocale($this->l->getLanguageCode());
    $translatableListener->setDefaultLocale('en_US');
    $translatableListener->setTranslationFallback(true);
    $translatableListener->setPersistDefaultLocaleTranslation(true);
    $translatableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($translatableListener);

    $config->setDefaultQueryHint(
      \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
      \OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Query\TreeWalker\TranslationWalker::class
    );
    $config->setDefaultQueryHint(
      \OCA\CAFEVDB\Wrapped\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
      $this->l->getLanguageCode()
    );
    $config->setDefaultQueryHint(
      \OCA\CAFEVDB\Wrapped\Gedmo\Translatable\TranslatableListener::HINT_FALLBACK,
      1 // fallback to default values in case if record is not translated
    );

    // handle extra foreign key constraints
    $foreignKeyListener = new CJH\ForeignKey\Listener($this);
    $foreignKeyListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($foreignKeyListener);

    return [ $config, $evm, $annotationReader ];
  }

  /**
   * Remove unwanted constraints after schema generation.
   *
   * @param \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $args
   *
   * @todo See that this is not necessary.
   */
  public function postGenerateSchema(\OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $args)
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

      /** @var \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Column $column */
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
   *
   * @todo This unfortunately does not hack similar problems with
   * cascade="persist". There the "stock" persist operation seemingly
   * still causes problems.
   */
  public function persist($entity)
  {
    $meta = $this->getClassMetadata(get_class($entity));
    if ($meta->containsForeignIdentifier) {
      $columnValues = $meta->getIdentifierColumnValues($entity);

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

          // the actual keys may need remapping through join columns
          $targetColumnValues = $columnValues;
          foreach ($association['joinColumns'] as $joinColumn) {
            $sourceColumn = $joinColumn['name'];
            $targetColumn = $joinColumn['referencedColumnName'];
            if (isset($targetColumnValues[$sourceColumn])) {
              $value = $targetColumnValues[$sourceColumn];
              unset($targetColumnValues[$sourceColumn]);
              $targetColumnValues[$targetColumn] = $value;
            }
          }

          $targetEntityId = $targetMeta->extractKeyValues($targetColumnValues);
          if (empty($targetEntityId)) {
            $reference = null;
          } else {
            $reference = $this->getReference($targetEntity, $targetEntityId);
          }

          $meta->setFieldValue($entity, $property, $reference);

        } catch (\Throwable $t) {
          // can happen if the relation is allowed to be null
          // $this->logException($t);
        }
      }

    }
    return parent::persist($entity);
  }

  /**
   * @todo Get rid of this function, the meta-data class is rather an
   * internal data structure of Doctrine\ORM.
   */
  public function getClassMetadata($className)
  {
    if ($this->decorateClassMetadata) {
      return new ClassMetadataDecorator(
        $this->entityManager->getClassMetadata($className)
        , $this
        , $this->logger
        , $this->l);
    } else {
      return $this->entityManager->getClassMetadata($className);
    }
  }

  /**
   * Switch metadata-decoration on and off. A hack. The console
   * application needs it switched off.
   */
  public function decorateClassMetadata(bool $onOff)
  {
    $this->decorateClassMetadata = $onOff;
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

  /**
   * Return a list of entities tagged by the given annotation.
   */
  public function entitiesByAnnotation(string $annotationClass)
  {
    if (is_array($this->annotationsEntites[$annotationClass])) {
      return $this->annotationsEntites[$annotationClass];
    }
    $this->annotationEntites[$annotationClass] = [];
    $classNames = $this->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    foreach ($classNames as $className) {
      if (!empty($annotation = $this->annotationReader->getClassAnnotation($className, $annotationClass))) {
        $this->annotationEntities[$annotationClass][$className] = $annotation;
      }
    }
    return $this->annotationEntites[$annotationClass];
  }

  /**
   * Return a list of properties tagged by the given annotation.
   */
  public function propertiesByAnnotation(string $annotationClass)
  {
    if (is_array($this->annotationsProperties[$annotationClass])) {
      return $this->annotationsProperties[$annotationClass];
    }
    $this->annotationProperties[$annotationClass] = [];
    $classNames = $this->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    foreach ($classNames as $className) {
      $classMetaData = $this->getClassMetadata($className);
      $reflClass = $classMetaData->getReflectionClass();
      $properties = [];
      foreach ($reflClass->getProperties() as $property) {
        if (!empty($annotation = $this->annotationReader->getPropertyAnnotation($property, $annotationClass))) {
          $properties[$property->getName()] = $annotation;
        }
      }
      if (!empty($properties)) {
        $this->annotationProperties[$annotationClass][] = [
          'entity' => $className,
          'properties' => $properties,
        ];
      }
    }
    return $this->annotationProperties[$annotationClass];
  }

  /**
   * In order to change the encryption key the encrypted data has to
   * be decrypted with the old key and re-encrypted with the new key.
   *
   * @bug This function does not seem to belong here ...
   * @todo Find out where this function belongs to ...
   */
  public function recryptEncryptedProperties(string $newEncryptionKey)
  {
    if (!$this->connected()) {
      throw new \RuntimeException($this->l->t('EntityManager is not connected to database.'));
    }
    $annotationClass = \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Annotation\Transformable::class;
    $transformables = $this->propertiesByAnnotation($annotationClass);

    /** @var Doctrine\ORM\UnitOfWork $unitOfWork */
    $unitOfWork = $this->getUnitOfWork();

    /** @var Doctrine\ORM\Listeners\Transformable\Encryption $transformer */
    $transformer = $this->transformerPool['encrypt'];

    $oldEncryptionKey = $transformer->setEncryptionKey($newEncryptionKey);

    $encryptedEntities = [];
    $this->beginTransaction();
    try {
      foreach ($transformables as $annotationInfo) {
        $encryptedProperties = false;
        foreach ($annotationInfo['properties'] as $field => $transformable) {
          if ($transformable->name == 'encrypt') {
            $encryptedProperties = true;
            $encryptedEntities[] = $annotationInfo['entity'];
            break;
          }
        }
      }

      foreach ($encryptedEntities as $entityClass) {
        foreach ($this->getRepository($entityClass)->findAll() as $entity) {
          $unitOfWork->scheduleForUpdate($entity);
        }
      }
      $this->flush();

      $transformer->setDecryptionKey($newEncryptionKey);
      foreach ($encryptedEntities as $entityClass) {
        foreach ($this->getRepository($entityClass)->findAll() as $entity) {
          $this->refresh($entity);
        }
      }

      $this->commit();
    } catch (\Throwable $t) {
      // $this->logError('Recrypting encrypted data base entries failed, rolling back ...');
      $this->rollback();
      $transformer->setEncryptionKey($oldEncryptionKey);
      $transformer->setDecryptionKey($oldEncryptionKey);
      $this->reopen();
      throw new \RuntimeException(
        $this->l->t('Recrypting encrypted data base entries failed, transaction has been rolled back.'),
        $t->getCode(), $t);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
