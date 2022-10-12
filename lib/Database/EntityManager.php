<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database;

use \RuntimeException;
use \InvalidArgumentException;

use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\Event;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Setup;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager as ORMEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Decorator\EntityManagerDecorator;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\ConnectionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DatabaseConnection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform as DatabasePlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Event\Listeners as DBALEventListeners;
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
use OCA\CAFEVDB\Wrapped\Doctrine as Doctrine;
use OCA\CAFEVDB\Wrapped\Gedmo;

use OCA\CAFEVDB\Wrapped\DoctrineExtensions;

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
use OCA\CAFEVDB\Database\Doctrine\ORM\Functions;
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

  /** @var IAppContainer */
  private $appContainer;

  /** @var bool */
  private $debug;

  /** @var bool */
  private $showSoftDeleted;

  /** @var bool */
  private $reopenAfterRollback;

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

  /**
   * @var array<int, UndoableRunQueue>
   * Pre-commit actions by translation level.
   */
  protected $preCommitActions;

  /**
   * @var int
   * We keep our own transaction nesting level in order to run the
   * pre-commit-hooks. As an alternative we could also override the
   * Connection and run the hooks there.
   */
  protected $transactionNestingLevel;

  /** @var UndoableRunQueue */
  protected $postCommitActions;

  /** @var IEventDispatcher */
  protected $eventDispatcher;

  /**
   * @var array
   *
   * Cache of the current database connection parameters
   */
  protected $databaseAccess = [];

  /**
   * @var array
   *
   * Event listeners only get access to the vanilla entity manager. In order
   * to give access to the decorated manager we supply a static lookup which
   * lets us determine the decorator for each decorated insance (ahem, "each":
   * there is probably always only one ...).
   */
  protected static $wrappedManagers = [];

  /** {@inheritdoc} */
  public function __construct(
    EncryptionService $encryptionService,
    IAppContainer $appContainer,
    CloudLogger $sqlLogger,
    IRequest $request,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->encryptionService = $encryptionService;
    $this->appContainer = $appContainer;
    $this->sqlLogger = $sqlLogger;
    $this->request = $request;
    $this->logger = $logger;
    $this->l = $l10n;

    $this->preFlushActions = clone $this->appContainer->get(UndoableRunQueue::class);
    $this->preCommitActions = [];
    $this->postCommitActions = clone $this->appContainer->get(UndoableRunQueue::class);

    $this->transactionNestingLevel = 0;
    $this->reopenAterRollback = true;

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
   * @param Event $event Dispatch the given event to the cloud's event dispatcher.
   *
   * @return void
   */
  public function dispatchEvent(Event $event):void
  {
    if (empty($this->eventDispatcher)) {
      $this->eventDispatcher = $this->appContainer->get(IEventDispatcher::class);
    }
    $this->eventDispatcher->dispatchTyped($event);
  }

  /**
   * @return bool Return \true if bound to the data-base, \false otherwise.
   */
  public function bound():bool
  {
    return !empty($this->wrapped);
  }

  /**
   * Initialize the wrapper if the EncryptionService has been bound to
   * a user and password.
   *
   * @return void
   */
  public function bind():void
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
    self::$wrappedManagers[spl_object_id($this->entityManager)] = $this;
    $this->dispatchEvent(new Events\EntityManagerBoundEvent($this));
  }

  /**
   * Give static access to the decorated entity manager.
   *
   * @param EntityManagerInterface $entityManager A potentially vanilla entity
   * manager. If the decorator is passed as argument, then it is gracefully
   * passed through.
   *
   * @return EntityManager The decorated entity Manager.
   */
  public static function getDecorator(EntityManagerInterface $entityManager):?EntityManager
  {
    if ($entityManager instanceof EntityManager) {
      return $entityManager;
    }
    return self::$wrappedManagers[spl_object_id($entityManager)] ?? null;
  }

  /** {@inheritdoc} */
  public function getConnection():?DatabaseConnection
  {
    if (empty($this->entityManager)) {
      return null;
    }
    return $this->entityManager->getConnection();
  }

  /** {@inheritdoc} */
  public function getPlatform():?DatabasePlatform
  {
    $connection = $this->getConnection();
    return $connection ? $connection->getDatabasePlatform() : null;
  }

  /**
   * Suspend query logging.
   *
   * @return void
   */
  public function suspendLogging():void
  {
    $this->sqlLogger->disable();
  }

  /**
   * Resume query logging.
   *
   * @return void
   */
  public function resumeLogging():void
  {
    $this->sqlLogger->enable($this->debug);
  }

  /**
   * @return null|string The user-id of the currently logged-in user
   * if known.
   */
  public function getUserId():?string
  {
    return $this->userId;
  }

  /** {@inheritdoc} */
  public function close():void
  {
    parent::close();
    $this->dispatchEvent(new Events\EntityManagerClosedEvent($this));
  }

  /**
   * @return EntityManagerInterface The wrapped entity manager.
   */
  public function getWrappedObject():EntityManagerInterface
  {
    return $this->entityManager;
  }

  /**
   * Reopen the entity-manager after it has been closed, e.g. after a
   * failed transaction.
   *
   * @return void
   */
  public function reopen():void
  {
    $this->preFlushActions->clearActionQueue();
    $this->preCommitActions = [];
    $this->postCommitActions->clearActionQueue();
    $this->transactionNestingLevel = 0;
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

  /**
   * Register the needed additional DBAL types.
   *
   * @return void
   */
  private function registerTypes():void
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
      Types\EnumDirEntryType::class => 'enum',
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

  /**
   * @param null|array $params Additional parameters.
   *
   * @return array The argument $params with merged needed additional
   * parameters. In particular merge the db authentication parameters.
   */
  private function connectionParameters(?array $params = null):array
  {
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

  /**
   * @param null|array $params Additional parameters.
   *
   * @return EntityManagerInterface Construct the wrapped entity manager instance.
   */
  private function getEntityManager(?array $params = null):EntityManagerInterface
  {
    list($config, $eventManager) = $this->createSimpleConfiguration();
    list($config, $eventManager, $annotationReader) = $this->createGedmoConfiguration($config, $eventManager);

    $this->annotationReader = $annotationReader;

    // mysql set names UTF-8 if required
    $eventManager->addEventSubscriber(new DBALEventListeners\MysqlSessionInit);

    $eventManager->addEventListener([
      ORM\Tools\ToolEvents::postGenerateSchema,
      // ORM\Events::loadClassMetadata,
      // ORM\Events::preUpdate,
      // ORM\Events::postUpdate,
      // ORM\Events::prePersist,
      // ORM\Events::postPersist,
      // ORM\Events::preRemove,
      // ORM\Events::postRemove,
      ORM\Events::postLoad, // still needed for __wakeup()
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
    $entityManager = ORMEntityManager::create($this->connectionParameters($params), $config, $eventManager);

    if (!$this->showSoftDeleted) {
      $entityManager->getFilters()->enable(self::SOFT_DELETEABLE_FILTER);
    }

    return $entityManager;
  }

  /**
   * @param Configuration $config
   *
   * @return void
   */
  private function registerCustomFunctions(Configuration $config):void
  {
    // $config->addCustomStringFunction('timestampdiff', \OCA\CAFEVDB\Wrapped\Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff::class);
    $config->addCustomDatetimeFunction('timestampdiff', DoctrineExtensions\Query\Mysql\TimestampDiff::class);
    $config->addCustomStringFunction('greatest', DoctrineExtensions\Query\Mysql\Greatest::class);
    $config->addCustomStringFunction('year', DoctrineExtensions\Query\Mysql\Year::class);
    $config->addCustomStringFunction('group_concat', DoctrineExtensions\Query\Mysql\GroupConcat::class);
    $config->addCustomStringFunction('if', DoctrineExtensions\Query\Mysql\IfElse::class);
    $config->addCustomStringFunction('regexp', DoctrineExtensions\Query\Mysql\Regexp::class);
    $config->addCustomStringFunction('bin2uuid', Functions\BinToUuid::class);
    $config->addCustomStringFunction('convert', Functions\ConvertUsing::class);
  }

  /**
   * @return array A simple configuration instance without extras.
   */
  private function createSimpleConfiguration():array
  {
    $cache = null;
    $useSimpleAnnotationReader = false;
    $config = Setup::createAnnotationMetadataConfiguration(self::ENTITY_PATHS, self::DEV_MODE, self::PROXY_DIR, $cache, $useSimpleAnnotationReader);
    $config->setEntityListenerResolver(new class($this->appContainer) extends ORM\Mapping\DefaultEntityListenerResolver {

      /** @var IAppContainer */
      private $appContainer;

      /** {@inheritdoc} */
      public function __construct(IAppContainer $appContainer)
      {
        $this->appContainer = $appContainer;
      }

      /** {@inheritdoc} */
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
    return [ $config, new Doctrine\Common\EventManager, ];
  }


  /**
   * @param Configuration $config An existing configuration to be augmented.
   *
   * @param Doctrine\Common\EventManager $evm Existing event-manager to reuse.
   *
   * @return array Generate a cooked configuration with Gedmo extensions.
   */
  private function createGedmoConfiguration(Configuration $config, Doctrine\Common\EventManager $evm):array
  {
    // standard annotation reader
    $annotationReader = new AnnotationReader;
    $cache = new ArrayAdapter();
    $cachedAnnotationReader = new PsrCachedReader($annotationReader, $cache);

    // create a driver chain for metadata reading
    $driverChain = new Doctrine\Persistence\Mapping\Driver\MappingDriverChain;

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
    $annotationDriver = new ORM\Mapping\Driver\AnnotationDriver(
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
    //$loggableListener = new Gedmo\Loggable\LoggableListener;
    $remoteAddress = $this->request->getRemoteAddress();
    $loggableListener = new Listeners\GedmoLoggableListener($this->userId, $remoteAddress);
    $loggableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($loggableListener);

    // timestampable
    $timestampableListener = new Gedmo\Timestampable\TimestampableListener();
    $timestampableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($timestampableListener);

    // soft deletable
    $softDeletableListener = new Gedmo\SoftDeleteable\SoftDeleteableListener();
    $softDeletableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($softDeletableListener);
    $config->addFilter(self::SOFT_DELETEABLE_FILTER, \OCA\CAFEVDB\Wrapped\Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter::class);

    // blameable
    $blameableListener = new Gedmo\Blameable\BlameableListener();
    $blameableListener->setAnnotationReader($cachedAnnotationReader);
    $blameableListener->setUserValue($this->userId);
    $evm->addEventSubscriber($blameableListener);

    // sluggable
    $sluggableListener =  $this->appContainer->get(Listeners\GedmoSluggableListener::class);
    $sluggableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($sluggableListener);

    // sortable
    // $sortableListener = new Gedmo\Sortable\SortableListener;
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
   * Call ENTITY::__wakeup() if it exists.
   *
   * @param ORM\Event\LifecycleEventArgs $eventArgs TBD.
   *
   * @return void
   */
  public function postLoad(ORM\Event\LifecycleEventArgs $eventArgs)
  {
    $entity = $eventArgs->getObject();
    if (\method_exists($entity, '__wakeup')) {
      $entity->__wakeup();
    }
  }

  /**
   * Remove unwanted constraints after schema generation.
   *
   * @param ORM\Tools\Event\GenerateSchemaEventArgs $args
   *
   * @return void
   *
   * @todo See that this is not necessary.
   */
  public function postGenerateSchema(ORM\Tools\Event\GenerateSchemaEventArgs $args)
  {
    $schema = $args->getSchema();
    // $entityManager = $args->getEntityManager();
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

  /**
   * @param string $propertyName The name of an entity property.
   *
   * @return string The associated database column name.
   *
   * @see property()
   */
  public function columnName(string $propertyName):string
  {
    //return $this->getConfiguration()->getNamingStrategy()->propertyToColumnName($propertyName);
    return Util::camelCaseToDashes($propertyName, '_');
  }

  /**
   * @param string $columnName A database column name.
   *
   * @return string The entity property name referring to $columnName.
   *
   * @see columnName()
   */
  public function property(string $columnName):string
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
   * @param mixed $entity The entity instance to persist.
   *
   * @return mixed The persisted entity (actually the argument $entity).
   *
   * @todo This unfortunately does not hack similar problems with
   * cascade="persist". There the "stock" persist operation seemingly
   * still causes problems.
   */
  public function persist(mixed $entity):mixed
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
   * undo-action. The actions are run after the currently active --
   * potentially nested -- transaction is committed. A commit will only
   * execute the actions registered at the current transaction nesting level.
   *
   * If the action succeeds, then its $undoAction will be registered for the
   * case that the commit throws an exception. In this case all
   * undo-actions will be executed in reverse order.
   *
   * In case of an error $action must throw an \Exception, its return
   * value is ignored.
   *
   * The callables need to run "stand-alone" without parameters.
   *
   * @param callable|IUndoable $action Action to register.
   *
   * @param null|callable $undo The associated undo-action. If $actionm
   * instanceof IUndoable then the $undo action is ignored. It should rather
   * be specified while constructing the IUndoable instance. The $undo
   * callable receives the return value of the $action callable as argument.
   *
   * @return UndoableRunQueue  Return the run-queue for  easy chaining
   * via UndoableRunQueue::register().
   */
  public function registerPreCommitAction($action, ?callable $undo = null):UndoableRunQueue
  {
    if (!$this->isOwnTransactionActive()) {
      throw new Exceptions\DatabaseTransactionNotActiveException($this->l->t('There is no active database transaction, cannot register pre-commit actions.'));
    }
    $level = $this->getOwnTransactionNestingLevel() - 1;
    $actions = $this->preCommitActions[$level];
    if (is_callable($action)) {
      $actions->register(new GenericUndoable($action, $undo));
    } elseif ($action instanceof IUndoable) {
      $actions->register($action);
    } else {
      throw new RuntimeException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
    return $actions;
  }

  /**
   * Explicitly execute the registered actions in case that the order of
   * execution matters. Only the pre-commit actions which were registered at
   * the current or a higher level will be executed.
   *
   * @return void
   *
   * @throws Exceptions\UndoableRunQueueException TBD.
   */
  public function executePreCommitActions()
  {
    if (!$this->isOwnTransactionActive()) {
      throw new Exceptions\DatabaseTransactionNotActiveException($this->l->t('There is no active database transaction, cannot execute pre-commit actions.'));
    }
    $level = $this->getOwnTransactionNestingLevel() - 1;
    $actions = $this->preCommitActions[$level] ?? null;
    if (!empty($actions) && !$actions->active()) {
      $actions->executeActions();
    }
  }

  /**
   * Register a post-commit action to be executed after the final data-base
   * commit succeeded, that is, the execution is post-poned until after
   * successful commit of the outermost transaction.
   *
   * Note that undo-actions are not taken into account, even
   * if the registered "Undoables" have an undo-facility, this will never get
   * executed.
   *
   * The execution of the corresponding run-queue is wrapped into a catch-all
   * block, any failing action may be logged but will not hinder the execution
   * of the other actions.
   *
   * @param callable|IUndoable $action The action to register.
   *
   * @return UndoableRunQueue  Return the run-queue for  easy chaining
   * via UndoableRunQueue::register().
   */
  public function registerPostCommitAction($action):UndoableRunQueue
  {
    if (is_callable($action)) {
      $this->postCommitActions->register(new GenericUndoable($action, undo: null));
    } elseif ($action instanceof IUndoable) {
      $this->postCommitActions->register($action);
    } else {
      throw new RuntimeException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
    return $this->postCommitActions;
  }

  /**
   * Explicitly execute the registered post-commit actions. These cannot be undone.
   *
   * @return bool The execution status of the post-commit run-queue. \false on
   * error, \true otherwise.
   */
  public function executePostCommitActions():bool
  {
    return $this->postCommitActions->executeActions(gracefully: true);
  }

  /**
   * @return array The list of exceptions thrown during execution of the run-queue.
   *
   * @see UndoableRunQueue::getRunQueueExceptions()
   */
  public function getPostCommitExceptions():array
  {
    return $this->postCommitActions->getRunQueueException();
  }

  /**
   * Return the transaction status of the underlying DBAL connection.
   *
   * @see \Doctrine\DBAL\Connection::isTransactionActive()
   *
   * @return bool
   */
  public function isTransactionActive():bool
  {
    return $this->entityManager->getConnection()->isTransactionActive();
  }

  /**
   * Return the transaction nesting level of the underlying DBAL connection.
   *
   * @see \Doctrine\DBAL\Connection::getTransactionNestingLevel()
   *
   * @return int
   */
  public function getTransactionNestingLevel():int
  {
    return $this->entityManager->getConnection()->getTransactionNestingLevel();
  }

  /**
   * Return the transaction status of this decorator.
   *
   * @return bool
   */
  public function isOwnTransactionActive():bool
  {
    return $this->transactionNestingLevel > 0;
  }

  /**
   * Return the transaction nesting level of transactions starting from this decorator.
   *
   * @return int
   */
  public function getOwnTransactionNestingLevel():int
  {
    return $this->transactionNestingLevel;
  }

  /**
   * Start a new transaction and manage the associated run-queues.
   *
   * @return void
   */
  public function beginTransaction()
  {
    parent::beginTransaction();
    $level = $this->transactionNestingLevel++;
    if (empty($this->preCommitActions[$level])) {
      $this->preCommitActions[$level] = clone $this->appContainer->get(UndoableRunQueue::class);
    } else {
      $this->preCommitActions[$level]->clearActionQueue();
    }
  }

  /**
   * Commit the currently pending transaction in the following order:
   *
   * - any left-over pre-flush actions are executed
   * - the pending pre-commit actions are executed
   * - the transaction is committed
   * - if this was the outer-most transaction, then the post-commit actions
   *   are executed
   *
   * @return bool The execution status of the post-commit run-queue if
   * that has been executed, otherwise \true.
   *
   * @see EntityManager::getTransactionNestingLevel()
   * @see EntityManager::registerPreFlushAction()
   * @see EntityManager::registerPreCommintAction()
   * @see EntityManager::registerPostCommitAction()
   *
   * @throws Exceptions\UndoableRunQueueException
   * @throws ConnectionException
   */
  public function commit():bool
  {
    // execute all remaining pre-flush action
    $this->executePreFlushActions();
    // execute all pre-commit action of the current level
    $this->executePreCommitActions();
    parent::commit();
    --$this->transactionNestingLevel;
    if (!$this->isTransactionActive()) {
      // execute non-undoable actions after the final commit succeeded.
      return $this->executePostCommitActions();
    }
    return true;
  }

  /**
   * Rollback the currently failed transactions, afterwards executed the
   * undo-queues of the callback-queues:
   *
   * 1. rollback
   * 2. run undo-actions of the pre-commit queue of the current level
   * 3. run undo-actions of the pre-flush queue
   *
   * @return void
   *
   * @throws ConnectionException
   */
  public function rollback()
  {
    // @todo we probably have to check if there is something to roll-back.
    parent::rollback();
    $level = --$this->transactionNestingLevel;

    // the post-commit actions cannot be undone
    // undo does not throw, it just logs exceptions
    $this->preCommitActions[$level]->executeUndo();
    $this->preFlushActions->executeUndo();

    if (!$this->isTransactionActive() && $this->reopenAfterRollback) {
      try {
        $this->entityManager->close();
        $this->entityManager->reopen();
      } catch (\Throable $t) {
        $this->logException($t, 'Unable to reopen after rollback');
      }
    }
  }

  /**
   * @param callable|IUndoable $action The action to be registered.
   *
   * @param null|callable $undo The undo action if $action is a mere callable.
   *
   * @return UndoableRunQueue
   *
   * @see registerPreCommitAction
   *
   * The difference is that these function are executed when flush()
   * is called. The undo-queue is executed after the data-base rollback in case of an error.
   */
  public function registerPreFlushAction($action, ?callable $undo = null):UndoableRunQueue
  {
    if (is_callable($action)) {
      $this->preFlushActions->register(new GenericUndoable($action, $undo));
    } elseif ($action instanceof IUndoable) {
      $this->preFlushActions->register($action);
    } else {
      throw new InvalidArgumentException($this->l->t('$action must be callable or an instance of "%s".', IUndoable::class));
    }
    return $this->preFlushActions;
  }

  /**
   * Explicitly execute the registered actions in case that the order
   * of execution matters.
   *
   * @return void
   *
   * @throws Exceptions\UndoableRunQueueException
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
   * {@inheritdoc}
   *
   * @todo Get rid of this function, the meta-data class is rather an
   * internal data structure of Doctrine\ORM.
   */
  public function getClassMetadata($className)
  {
    if ($this->decorateClassMetadata) {
      return new ClassMetadataDecorator(
        $this->entityManager->getClassMetadata($className),
        $this,
        $this->logger,
        $this->l,
      );
    } else {
      return $this->entityManager->getClassMetadata($className);
    }
  }

  /**
   * Switch metadata-decoration on and off. A hack. The console
   * application needs it switched off.
   *
   * @param bool $onOff TBD.
   *
   * @return void
   */
  public function decorateClassMetadata(bool $onOff):void
  {
    $this->decorateClassMetadata = $onOff;
  }

  /** @return void */
  private function createTableLookup():void
  {
    $this->entityNames = [];
    $classNames = $this->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    foreach ($classNames as $className) {
      $classMetaData = $this->getClassMetadata($className);
      $this->entityNames[$classMetaData->getTableName()] = $className;
    }
  }

  /**
   * @param string $table Data-base table name.
   *
   * @return null|string The associated entity-name, or null if not found.
   */
  public function entityOfTable(string $table):?string
  {
    if (empty($this->entityNames)) {
      $this->createTableLookup();
    }
    return $this->entityNames[$table]?:null;
  }

  /**
   * Return a list of entities tagged by the given annotation.
   *
   * @param string $annotationClass The annotation class-name to look up.
   *
   * @return array The list of found entity class-names.
   */
  public function entitiesByAnnotation(string $annotationClass)
  {
    if (is_array($this->annotationEntites[$annotationClass])) {
      return $this->annotationEntites[$annotationClass];
    }
    $this->annotationEntites[$annotationClass] = [];
    $classNames = $this->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    foreach ($classNames as $className) {
      $annotation = $this->annotationReader->getClassAnnotation($className, $annotationClass);
      if (!empty($annotation)) {
        $this->annotationEntities[$annotationClass][$className] = $annotation;
      }
    }
    return $this->annotationEntites[$annotationClass];
  }

  /**
   * Return a list of properties tagged by the given annotation.
   *
   * @param string $annotationClass The annotation class-name to look-up.
   *
   * @return array The list of annotated properties.
   */
  public function propertiesByAnnotation(string $annotationClass):array
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
        $annotation = $this->annotationReader->getPropertyAnnotation($property, $annotationClass);
        if (!empty($annotation)) {
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
    return $this->transformerPool[$key] ?? null;
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
   *
   * @return void
   *
   * @throws Exceptions\RecryptionFailedException
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
   * @param null|Crypto\ICryptor $newAppCryptor The new cryptor, may be null
   * if the data is to be stored unencrypted in the future.
   *
   * @param null|Crypto\ICryptor $oldAppCryptor The old cryptor, may be null
   * if the data has been stored unencrypted.
   *
   * @return void
   *
   * @throws Exceptions\RecryptionFailedException
   *
   * @bug This function does not seem to belong here ...
   *
   * @todo Find out where this function belongs to ...
   */
  public function recryptEncryptedProperties(?Crypto\ICryptor $newAppCryptor, ?Crypto\ICryptor $oldAppCryptor)
  {
    if (!$this->connected()) {
      throw new RuntimeException($this->l->t('EntityManager is not connected to database.'));
    }

    $annotationClass = \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Annotation\Transformable::class;
    $transformables = $this->propertiesByAnnotation($annotationClass);

    /** @var Doctrine\ORM\Listeners\Transformable\Encryption $transformer */
    $transformer = $this->transformerPool[self::TRANSFORM_ENCRYPT];

    $encryptedEntities = [];
    foreach ($transformables as $annotationInfo) {
      foreach ($annotationInfo['properties'] as $transformable) {
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
