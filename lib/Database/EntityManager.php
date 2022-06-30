<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\Event;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Setup;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Decorator\EntityManagerDecorator;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DatabaseConnection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform as DatabasePlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadata;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Configuration;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Symfony\Component\Cache\Adapter\ArrayAdapter;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Cache\ArrayCache;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Cache\Psr6\CacheAdapter;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Cache\Psr6\DoctrineProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Annotations\AnnotationReader;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Annotations\PsrCachedReader;

use function class_exists;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Doctrine as Ramsey;
use OCA\CAFEVDB\Wrapped\CJH\Doctrine\Extensions as CJH;

use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\MyCLabs\Enum\Enum as EnumType;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;

use OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Events;

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

  /**
   * @var string
   * Encryption-transformer key, see $this->getDataTransformer()
   */
  const TRANSFORM_ENCRYPT = 'encrypt';

  /**
   * @var string
   * Hash-transformer key, see $this->getDataTransformer()
   */
  const TRANSFORM_HASH = 'hash';

  /**
   * @var string
   * The name of the soft-deleteable filter
   */
  const SOFT_DELETEABLE_FILTER = 'soft-deleteable';

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager */
  private $entityManager;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var CloudLogger */
  private $sqlLogger;

  /**
   * @var array
   * Cache of entity names indexed by table names.
   */
  private $entityNames = null;

  /**
   * @var array
   * Cache of entity names indexed by class annotation
   */
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

  /** @var UndoableRunQueue */
  protected $preFlushActions;

  /** @var UndoableRunQueue */
  protected $preCommitActions;

  /** @var IEventDispatcher */
  protected $eventDispatcher;

  /** @var array */
  protected $lifeCycleEvents;

  /**
   * @var array
   *
   * Cache of the current database connection parameters
   */
  protected $databaseAccess = [];

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

    $this->preFlushActions = new UndoableRunQueue($this->logger, $this->l);
    $this->preCommitActions = new UndoableRunQueue($this->logger, $this->l);

    $this->bind();
    if (!$this->bound()) {
      $this->eventDispatcher = $this->appContainer->get(IEventDispatcher::class);
      $this->eventDispatcher->addListener(Events\EncryptionServiceBound::class, function(Events\EncryptionServiceBound $event) {
        $this->logDebug('LAZY BINDING ENTITY MANAGER');
        $this->bind();
      });
    }
  }

  /**
   * Dispatch the given event to the cloud's event dispatcher.
   *
   * @param Event $event.
   */
  public function dispatchEvent(Event $event)
  {
    if (empty($this->eventDispatcher)) {
      $this->eventDispatcher = $this->appContainer->get(IEventDispatcher::class);
    }
    return $this->eventDispatcher->dispatchTyped($event);
  }

  public function bound():bool
  {
    return !empty($this->wrapped);
  }

  /**
   * Initialize the wrapper if the EncryptionService has been bound to
   * a user and password.
   */
  public function bind()
  {
    if (!$this->encryptionService->bound()) {
      return;
    }
    if (!empty($this->wrapped)) {
      $this->close();
    }
    $userId = $this->encryptionService->getUserId() ?: $this->l->t('unknown');
    if (empty($this->wrapped) || $userId != $this->userId) {
      $this->userId = $userId;
      $debugMode = $this->encryptionService->getConfigValue('debugmode', 0);
      $debugMode = filter_var($debugMode, FILTER_VALIDATE_INT, ['min_range' => 0]) || 0;
      $this->debug = 0 != ($debugMode & ConfigService::DEBUG_QUERY);
      $this->showSoftDeleted = $this->encryptionService->getUserValue($this->userId, 'showdisabled') === 'on';
      $this->decorateClassMetadata = true;
    }
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    }
    $this->dispatchEvent(new Events\EntityManagerBoundEvent($this));
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
   * Close the entity-manager
   */
  public function close()
  {
    parent::close();
    $this->dispatchEvent(new Events\EntityManagerClosedEvent($this));
  }

  public function getWrappedObject():EntityManagerInterface
  {
    return $this->entityManager;
  }

  /*
   * Reopen the entity-manager after it has been closed, e.g. after a
   * failed transaction.
   */
  public function reopen()
  {
    $this->preFlushActions->clearActionQueue();
    $this->preCommitActions->clearActionQueue();
    $this->bind();
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
      $this->logException($t, 'Caught execption trying to ping database server ' . $params['user'] . '@' . $params['host'] . ':' . $params['dbname']);
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
    if (empty($this->databaseAccess)) {
      $this->databaseAccess = [
        'dbname' => $this->encryptionService->getConfigValue('dbname'),
        'user' => $this->encryptionService->getConfigValue('dbuser'),
        'password' => $this->encryptionService->getConfigValue('dbpassword'),
        'host' => $this->encryptionService->getConfigValue('dbserver'),
      ];
    }
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
    $connectionParams = array_merge($this->databaseAccess, $params, $driverParams, $charSetParams);
    return $connectionParams;
  }

  private function getEntityManager($params = null)
  {
    list($config, $eventManager) = $this->createSimpleConfiguration();
    list($config, $eventManager, $annotationReader) = $this->createGedmoConfiguration($config, $eventManager);

    $this->annotationReader = $annotationReader;

    // mysql set names UTF-8 if required
    $eventManager->addEventSubscriber(new \OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Event\Listeners\MysqlSessionInit());

    $eventManager->addEventListener([
      \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\ToolEvents::postGenerateSchema,
      ORM\Events::loadClassMetadata,
      ORM\Events::preUpdate,
      ORM\Events::postUpdate,
      ORM\Events::postLoad,
    ], $this);


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
      $entityManager->getFilters()->enable(self::SOFT_DELETEABLE_FILTER);
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
    $config->addCustomStringFunction('year', \OCA\CAFEVDB\Wrapped\DoctrineExtensions\Query\Mysql\Year::class);
    $config->addCustomStringFunction('group_concat', \OCA\CAFEVDB\Wrapped\DoctrineExtensions\Query\Mysql\GroupConcat::class);
    $config->addCustomStringFunction('if', \OCA\CAFEVDB\Wrapped\DoctrineExtensions\Query\Mysql\IfElse::class);
  }

  private function createSimpleConfiguration():array
  {
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration(self::ENTITY_PATHS, self::DEV_MODE, self::PROXY_DIR, $cache, $useSimpleAnnotationReader);
    $config->setEntityListenerResolver(new class($this->appContainer) extends ORM\Mapping\DefaultEntityListenerResolver {

      private $appContainer;

      public function __construct(IAppContainer $appContainer)
      {
        $this->appContainer = $appContainer;
      }

      public function resolve($className)
      {
        try {
          return parent::resolve($className);
        } catch (\Throwable $t) {
          $this->register($object = $this->appContainer->get($className));
          return $object;
        }
      }
    });
    return [ $config, new \OCA\CAFEVDB\Wrapped\Doctrine\Common\EventManager(), ];
  }

  private function createGedmoConfiguration($config, $evm)
  {
    // standard annotation reader
    $annotationReader = new AnnotationReader;
    $cache = new ArrayAdapter();
    $cachedAnnotationReader = new PsrCachedReader($annotationReader, $cache);

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
    $config->setMetadataCache($cache);
    $config->setQueryCacheImpl(DoctrineProvider::wrap($cache));

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
    $config->addFilter(self::SOFT_DELETEABLE_FILTER, \OCA\CAFEVDB\Wrapped\Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter::class);

    // blameable
    $blameableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Blameable\BlameableListener();
    $blameableListener->setAnnotationReader($cachedAnnotationReader);
    $blameableListener->setUserValue($this->userId);
    $evm->addEventSubscriber($blameableListener);

    // sluggable
    $sluggableListener =  $this->appContainer->get(Listeners\GedmoSluggableListener::class);
    $sluggableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($sluggableListener);

    // sortable
    // $sortableListener = new \OCA\CAFEVDB\Wrapped\Gedmo\Sortable\SortableListener;
    // $sortableListener->setAnnotationReader($cachedAnnotationReader);
    // $evm->addEventSubscriber($sortableListener);

    // encryption
    $transformerPool = new Transformable\Transformer\TransformerPool();
    $transformerPool[self::TRANSFORM_ENCRYPT] = $this->appContainer->get(
      Listeners\Transformable\Encryption::class
    );
    $transformerPool[self::TRANSFORM_HASH] = new Transformable\Transformer\PhpHashTransformer([
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
    $localeCode = $this->l->getLocaleCode();
    if (strpos($localeCode, '_') === false) {
      $localeCode = $localeCode . '_' . strtoupper($localeCode);
    }
    $translatableListener->setTranslatableLocale($localeCode);
    $translatableListener->setDefaultLocale(ConfigService::DEFAULT_LOCALE);
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
      $localeCode
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
   * Manipulate class-metadata
   */
  public function loadClassMetadata(\OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event\LoadClassMetadataEventArgs $args)
  {
    $metaData = $args->getClassMetadata();
    $className = $metaData->getName();
    $callbacks = [];
    foreach ($metaData->lifecycleCallbacks as $event => $eventHandlers) {
      switch ($event) {
      case ORM\Events::preUpdate:
      case ORM\Events::postUpdate:
        $this->lifeCycleEvents[$event][$className] = $eventHandlers;
        break;
      default:
        $callbacks[$event] = $eventHandlers;
      }
    }
    $metaData->setLifecycleCallbacks($callbacks);
  }

  /**
   * Forward some life-cycle callbacks, replacing the entity-manager
   * instance with ourselves, the decorated EntityManager.
   */
  public function preUpdate(ORM\Event\PreUpdateEventArgs $eventArgs)
  {
    $entity = $eventArgs->getEntity();
    $changeSet = $eventArgs->getEntityChangeSet();
    $tmpEventArgs = new ORM\Event\PreUpdateEventArgs($entity, $this, $changeSet);
    $handled = 0;
    foreach ($this->lifeCycleEvents[ORM\Events::preUpdate] as $className => $eventHandlers) {
      if ($entity instanceof $className) {
        foreach ($eventHandlers as $handler) {
          call_user_func([ $entity, $handler ], $tmpEventArgs);
          ++$handled;
        }
      }
    }
    if ($handled > 0) {
      $newChangeSet = array_merge($eventArgs->getEntityChangeSet(), $tmpEventArgs->getEntityChangeSet());
      foreach ($newChangeSet as $field => $value) {
        $eventArgs->setNewValue($field, $value[1]);
      }
    }
  }

  /**
   * Forward some life-cycle callbacks, replacing the entity-manager
   * instance with ourselves, the decorated EntityManager.
   */
  public function postUpdate(ORM\Event\LifecycleEventArgs $eventArgs)
  {
    $entity = $eventArgs->getEntity();
    $eventArgs = new ORM\Event\LifecycleEventArgs($entity, $this);
    foreach ($this->lifeCycleEvents[ORM\Events::postUpdate] as $className => $eventHandlers) {
      if ($entity instanceof $className) {
        foreach ($eventHandlers as $handler) {
          call_user_func([ $entity, $handler ], $eventArgs);
        }
      }
    }
  }

  /**
   * Remove unwanted constraints after schema generation.
   *
   * @param \OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $args
   *
   * @todo See that this is not necessary.
   */
  public function postGenerateSchema(ORM\Tools\Event\GenerateSchemaEventArgs $args)
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

  public function postLoad(ORM\Event\LifecycleEventArgs $args)
  {
    $entity = $args->getObject();
    if (\method_exists($entity, '__wakeup')) {
      $entity->__wakeup();
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
   * Register a pre-commit action and optionally an associated
   * undo-action. The actions are run after all data-base operation
   * have completed just before the final commit step. If the action
   * succeeds, then its $undoAction will be registered for the case
   * that the final commit throws an exception. In this case all
   * undo-actions will be executed in reverse order.
   *
   * In case of an error $action must throw an \Exception, its return
   * value is ignored.
   *
   * The callables need to run "stand-alone" without parameters.
   *
   * @param Callable|IUndoable $action Action to register.
   *
   * @param null|Callable $undo The associated undo-action. If
   * $actionm instanceof IUndoable then the $undo action is
   * ignored. It should rather be specified while constructing the
   * IUndoable instance.
   *
   * @return UndoableRunQueue  Return the run-queue for  easy chaining
   * via UndoableRunQueue::register().
   */
  public function registerPreCommitAction($action, ?Callable $undo = null):UndoableRunQueue
  {
    if (is_callable($action)) {
      $this->preCommitActions->register(new GenericUndoable($action, $undo));
    } else if ($action instanceof IUndoable) {
      $this->preCommitActions->register($action);
    } else  {
      throw new \RuntimeException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
    return $this->preCommitActions;
  }

  /**
   * Explicitly execute the registered actions in case that the order
   * of execution matters.
   */
  public function executePreCommitActions()
  {
    $this->preCommitActions->executeActions();
  }

  public function commit()
  {
    // execute all remaining pre-flush action
    $this->executePreFlushActions();
    $this->executePreCommitActions();
    parent::commit();
  }

  public function rollback()
  {
    // @todo we probably have to check if there is something to roll-back.
    parent::rollback();
    // undo does not throw, it just logs exceptions
    $this->preCommitActions->executeUndo();
    // undo does not throw, it just logs exceptions
    $this->preFlushActions->executeUndo();
  }

  /**
   * @see registerPreCommitAction
   *
   * The difference is that these function are executed when flush()
   * is called. The undo-queue is executed after the data-base rollback in case of an error.
   */
  public function registerPreFlushAction($action, ?Callable $undo = null):UndoableRunQueue
  {
    if (is_callable($action)) {
      $this->preFlushActions->register(new GenericUndoable($action, $undo));
    } else if ($action instanceof IUndoable) {
      $this->preFlushActions->register($action);
    } else  {
      throw new \RuntimeException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
    return $this->preFlushActions;
  }

  /**
   * Explicitly execute the registered actions in case that the order
   * of execution matters.
   */
  public function executePreFlushActions()
  {
    $this->preFlushActions->executeActions();
  }

  /**
   * {@inheritdoc}
   */
  public function flush($entity = null)
  {
    $this->executePreFlushActions();
    parent::flush($entity);
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
    if (is_array($this->annotationEntites[$annotationClass])) {
      return $this->annotationEntites[$annotationClass];
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
    if (is_array($this->annotationProperties[$annotationClass] ?? null)) {
      return $this->annotationProperties[$annotationClass];
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
   * Return the data-transformer for the given key.
   *
   * @param string $key Currently may be either self::TRANSFORM_ENCRYPT or
   * self::TRANSFORM_HASH.
   *
   * @return null|Transformable\Transformer\TransformerInterface
   */
  public function getDataTransformer(string $key):Transformable\Transformer\TransformerInterface
  {
    return $transformer = $this->transformerPool[$key]??null;
  }

  /**
   * Recrypt the given list/array of entities by forcing an update on the unit
   * of work. The underlying transformable listener will make sure that the
   * actual update will happen.
   *
   * @param iterable $entities
   *
   * @param null|callable $beforeLoad Optional callable which is invoked
   * before re-loading the list of entities. Can be used to tweak the app
   * encryption-key, e.g.
   *
   * @param null|callable $beforeFlush Optional callable which is invoked
   * before flushed the entities again to the database. Can be used to tweak
   * the app encryption-key, e.g.
   */
  public function recryptEntityList(iterable $entities, ?callable $beforeLoad = null, ?callable $beforeFlush = null)
  {
    /** @var Doctrine\ORM\UnitOfWork $unitOfWork */
    $unitOfWork = $this->getUnitOfWork();

    /** @var Doctrine\ORM\Listeners\Transformable\Encryption $transformer */
    $transformer = $this->transformerPool[self::TRANSFORM_ENCRYPT];

    $this->beginTransaction();
    try {

      if (!empty($beforeLoad)) {
        $beforeLoad();
      }

      $transformer->setCachable(false);

      // Read all entities into the cache
      foreach ($entities as $entity) {
        $this->refresh($entity); // needed ?
        $unitOfWork->scheduleForUpdate($entity);
      }

      if (!empty($beforeFlush)) {
        $beforeFlush();
      }

      // Flush to disk with new encryption key
      $this->flush();

      // The next lines should in principle not be necessary
      // ... refresh($entity) should re-read all entities from the
      // database.
      foreach ($entities as $entity) {
        $this->refresh($entity);
      }

      $transformer->setCachable(true);

      $this->commit();

    } catch (\Throwable $t) {

      $this->logError('Recrypting encrypted database entries failed, rolling back ...');
      $this->rollback();

      throw new Exceptions\RecryptionFailedException(
        $this->l->t('Recrypting encrypted data base entries failed, transaction has been rolled back.'),
        $t->getCode(), $t);
    }
  }

  /**
   * In order to change the encryption key the encrypted data has to
   * be decrypted with the old key and re-encrypted with the new key.
   *
   * @bug This function does not seem to belong here ...
   * @todo Find out where this function belongs to ...
   */
  public function recryptEncryptedProperties(?Crypto\ICryptor $newAppCryptor, ?Crypto\ICryptor $oldAppCryptor)
  {
    if (!$this->connected()) {
      throw new \RuntimeException($this->l->t('EntityManager is not connected to database.'));
    }

    $annotationClass = \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Annotation\Transformable::class;
    $transformables = $this->propertiesByAnnotation($annotationClass);

    /** @var Doctrine\ORM\Listeners\Transformable\Encryption $transformer */
    $transformer = $this->transformerPool[self::TRANSFORM_ENCRYPT];

    $encryptedEntities = [];
    foreach ($transformables as $annotationInfo) {
      foreach ($annotationInfo['properties'] as $field => $transformable) {
        if ($transformable->name == self::TRANSFORM_ENCRYPT) {
          $entityClass = $annotationInfo['entity'];
          $entities = $this->getRepository($entityClass)->findAll();
          $encryptedEntities = array_merge($encryptedEntities, $entities);
          break; // one encrypted property is sufficient
        }
      }
    }

    try {
      $this->recryptEntityList(
        $encryptedEntities,
        fn() => $transformer->setAppCryptor($oldAppCryptor),
        fn() => $transformer->setAppCryptor($newAppCryptor)
      );
    } catch (Exceptions\RecryptionFailedException $e) {

      $transformer->setAppCryptor($oldAppCryptor);
      try {
        $this->reopen(); // in case the caller catches the exception.
      } catch (\Throwable $t2) {
        $this->logException($t2, 'Reopening entity-manager failed.');
      }

      throw $e;
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
