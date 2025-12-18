<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DeprecationLogger;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Functions;
use OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators\ColumnHydrator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ReservedWordQuoteStrategy;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Wrapped\CJH\Doctrine\Extensions as CJH;
use OCA\CAFEVDB\Wrapped\Doctrine as Doctrine;
use OCA\CAFEVDB\Wrapped\DoctrineExtensions;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DatabaseConnection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\ConnectionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Event\ConnectionEventArgs;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Event\Listeners as DBALEventListeners;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform as DatabasePlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types as DBALTypes;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Configuration;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Decorator\EntityManagerDecorator;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager as ORMEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\ORMSetup;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use OCA\CAFEVDB\Wrapped\Firehed\DbalLogger;
use OCA\CAFEVDB\Wrapped\Gedmo;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Doctrine as Ramsey;
use OCA\CAFEVDB\Wrapped\Symfony\Component\Cache\Adapter\ArrayAdapter;

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
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const ENTITY_PATHS = [
    __DIR__ . '/Doctrine/ORM/Entities',
  ];

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

  /** Enable SQL query logging */
  private bool $debug = false;

  /** Disable on-disk caching of ORM. */
  private bool $devMode = false;

  /** @var bool */
  private $showSoftDeleted;

  /** @var bool */
  private $reopenAfterRollback;

  /** @var bool */
  private bool $typesBound = false;

  /** @var bool */
  private $decorateClassMetadata = true;

  /** @var Gedmo\Mapping\Driver\AttributeReader */
  private $attributeReader;

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

  /** @var null|GedmoTranslatableListener */
  protected ?Listeners\GedmoTranslatableListener $translatable = null;

  /**
   * Locale -provider of the translatable listener.
   */
  protected ?IL10N $translatableL10n;

  /**
   * @var array
   *
   * Cache of the current database connection parameters
   */
  protected $databaseAccess = [];

  /**
   * @var array<int, Throwable>
   *
   * In order to make the real exceptions visible exceptions can be remembered
   * vie pushTransactionExceptions() and retrieved later in the top level
   * code.
   */
  protected array $transactionExceptions = [];

  /**
   * @var EntityManager
   *
   * The entity manager is a singleton. There is only one.
   */
  protected static ?EntityManager $instance = null;

  /** {@inheritdoc} */
  public function __construct(
    DeprecationLogger $deprecationLogger,
    private CloudLogger $sqlLogger,
    private EncryptionService $encryptionService,
    private IL10N $l,
    private IRequest $request,
    protected IAppContainer $appContainer,
    protected ILogger $logger,
    protected string $appName,
    protected IConfig $cloudConfig,
  ) {
    $this->preFlushActions = clone $this->appContainer->get(UndoableRunQueue::class);
    $this->preCommitActions = [];
    $this->postCommitActions = clone $this->appContainer->get(UndoableRunQueue::class);

    $this->transactionNestingLevel = 0;
    $this->reopenAfterRollback = true;

    $deprecationLogger = clone $deprecationLogger;
    $deprecationLogger->setLogLevel(\OCP\ILogger::DEBUG);
    Doctrine\Deprecations\Deprecation::enableWithPsrLogger($deprecationLogger);

    $this->bind();
    if (!$this->bound()) {
      $this->eventDispatcher = $this->appContainer->get(IEventDispatcher::class);
      $this->eventDispatcher->addListener(Events\EncryptionServiceBound::class, function(Events\EncryptionServiceBound $event) {
        $this->logDebug('LAZY BINDING ENTITY MANAGER');
        $this->bind();
      });
    }
    self::$instance = $this;
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
      $debugMode = $this->cloudConfig->getUserValue($this->userId, $this->appName, EnumPersonalSettingsKey::DEBUG_MODE->value, 0);
      $debugMode = filter_var($debugMode, FILTER_VALIDATE_INT, ['min_range' => 0]) ?: 0;
      $this->debug = 0 != ($debugMode & ConfigConstants::DEBUG_QUERY);
      $this->devMode = 0 != ($debugMode & ConfigConstants::DEBUG_ORM);
      $this->showSoftDeleted = $this->cloudConfig->getUserValue($this->userId, $this->appName, EnumPersonalSettingsKey::SHOW_DISABLED->value) === 'on';
      $this->decorateClassMetadata = true;
    }
    parent::__construct($this->getEntityManager());
    $this->entityManager = $this->wrapped;
    if ($this->connected()) {
      $this->registerTypes();
    } else {
      $this->logError('NOT CONNECTED');
    }
    $this->dispatchEvent(new Events\EntityManagerBoundEvent($this));
  }

  /**
   * The entity manager is a singleton: there is only one. If there is any
   * then return the instance, if there is no (yet) any return null.
   *
   * @return null|EntityManager
   */
  public static function getInstance():?EntityManager
  {
    return self::$instance;
  }

  /** {@inheritdoc} */
  public function getConnection():DatabaseConnection
  {
    if (empty($this->entityManager)) {
      throw new Exceptions\DatabaseNotConnectedException($this->l->t('There is no entity-manager initialized yet.'));
    }
    try {
      return $this->entityManager->getConnection();
    } catch (ConnectionException $t) {
      throw new Exceptions\DatabaseNotConnectedException($this->l->t('The entity-manager is unable to connect to the database.'));
    }
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
   * Enable query logging.
   *
   * @return void
   */
  public function enableLogging():void
  {
    $this->sqlLogger->enable(true);
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
   * Check for a valid database connection, try to establish a connection if
   * not connected.
   *
   * @return bool
   */
  public function connected():bool
  {
    try {
      $connection = $this->getConnection();
    } catch (Exceptions\DatabaseNotConnectedException $e) {
      if (str_contains($this->request->getPathInfo(), 'apps/' . $this->appName)) {
        $this->logException($e);
      }
      return false;
    }
    $params = $connection->getParams();
    $impossible = false;
    foreach ([ 'host', 'user', 'password', ConfigConstants::APP_DB_NAME ] as $key) {
      if (empty($params[$key])) {
        $impossible = true;
      }
    }
    if ($impossible) {
      $this->logError('Unable to access database, connection parameters are unset');
      return false;
    }
    try {
      if (!$connection->isConnected()) {
        // $connection->connect() is deprecated
        $connection->getNativeConnection();
        return $connection->isConnected();
      }
    } catch (Throwable $t) {
      if (str_contains($this->request->getPathInfo() ?? '', 'apps/' . $this->appName)) {
        $this->logException($t, 'Caught execption checking connection to database server ' . $params['user'] . '@' . $params['host'] . ':' . $params[ConfigConstants::APP_DB_NAME]);
      }
      return false;
    }
    return true;
  }

  private const DBAL_TYPES = [
    Types\EnumAccessPermission::class => 'enum',
    Types\EnumDataTransformation::class => 'enum',
    Types\EnumDirEntryType::class => 'enum',
    Types\EnumParticipationContext::class => 'enum',
    Types\EnumFileType::class => 'enum',
    Types\EnumGender::class => 'enum',
    // Types\EnumGeographicalScope::class => 'enum',
    Types\EnumGnuCashSlotType::class => 'enum',
    Types\EnumMemberStatus::class => 'enum',
    Types\EnumParticipantFieldDataType::class => 'enum',
    Types\EnumParticipantFieldMultiplicity::class => 'enum',
    Types\EnumParticipationStatus::class => 'enum',
    Types\EnumProjectTemporalType::class => 'enum',
    Types\EnumSepaTransaction::class => 'enum',
    Types\EnumTaxType::class => 'enum',
    Types\EnumVCalendarType::class => 'enum',
    // Ramsey\UuidType::class => null,
    // Ramsey\UuidBinaryType::class => 'binary',
    // Ramsey\UuidBinaryOrderedTimeType::class => 'binary',
    Types\UuidType::class => 'uuid_binary',
    Types\DecimalRationalP2S2Type::class => 'decimal_rational_2_2',
    Types\DecimalRationalP4S4Type::class => 'decimal_rational_4_4',
    Types\DecimalRationalMonetaryType::class => 'decimal_rational_monetary',
    Types\ArrayType::class => 'array',
  ];

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

    $connection = $this->entityManager->getConnection();
    try {
      $platform = $connection->getDatabasePlatform();
      foreach (self::DBAL_TYPES as $phpType => $sqlType) {
        if ($sqlType == 'enum') {
          $typeName = substr(strrchr($phpType, '\\'), 1);
          if (!Type::hasType($typeName)) {
            Types\EnumType::registerEnumType($typeName, $phpType);
          }
        } else {
          $instance = new $phpType;
          $typeName = $instance->getName();
          if (!Type::hasType($typeName)) {
            Type::addType($typeName, $phpType);
          }
        }
        if (!empty($sqlType)) {
          $platform->registerDoctrineTypeMapping($sqlType, $typeName);
        }
      }
      // Override datetime stuff
      Type::overrideType('date', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeType::class);
      Type::overrideType('date_immutable', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeImmutableType::class);
      Type::overrideType('datetime', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeType::class);
      Type::overrideType('datetime_immutable', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeImmutableType::class);
      Type::overrideType('datetimetz', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeType::class);
      Type::overrideType('datetimetz_immutable', \OCA\CAFEVDB\Wrapped\Carbon\Doctrine\DateTimeImmutableType::class);
      $this->typesBound = true;
    } catch (Throwable $t) {
      throw new Exceptions\DatabaseException($this->l->t('Unable to register types with DBAL.'), previous: $t);
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
        'dbname' => $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_NAME),
        'user' => $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_USER),
        'password' => $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_PASSWORD),
        'host' => $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_SERVER),
      ];
      if (str_contains(($this->databaseAccess['host'] ?? ''), '://')) {
        $parser = new DBAL\Tools\DsnParser;
        $parsedParams = $parser->parse($this->databaseAccess['host']);
        $this->databaseAccess = array_merge($this->databaseAccess, $parsedParams);
      }
    }
    $driverParams = [
      'driver' => 'pdo_mysql',
      'wrapperClass' => Connection::class,
    ];
    $charSetParams = [
      'collate' => Constants::FULL_COLLATION,
      'charset' => Constants::CHARACTER_SET,
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
    $conParams = $this->connectionParameters($params);

    list($config, $eventManager) = $this->createSimpleConfiguration();
    list($config, $eventManager, $attributeReader) = $this->createGedmoConfiguration($config, $eventManager);

    $this->attributeReader = $attributeReader;

    // mysql set names UTF-8 if required
    // $eventManager->addEventSubscriber(new DBALEventListeners\MysqlSessionInit($conParams['charset'], $conParams['collate']));

    $eventManager->addEventListener([
      ORM\Tools\ToolEvents::postGenerateSchema,
      // ORM\Events::loadClassMetadata,
      // ORM\Events::preUpdate,
      // ORM\Events::postUpdate,
      // ORM\Events::prePersist,
      // ORM\Events::postPersist,
      // ORM\Events::preRemove,
      // ORM\Events::postRemove,
      // \Doctrine\DBAL\Events::postConnect,
      ORM\Events::postLoad, // still needed for __wakeup()
    ], $this);

    $this->registerCustomFunctions($config);

    $config->addCustomHydrationMode('COLUMN_HYDRATOR', ColumnHydrator::class);

    $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
    $config->setNamingStrategy($namingStrategy);

    $quoteStrategy = new ReservedWordQuoteStrategy();
    $config->setQuoteStrategy($quoteStrategy);

    // $config->setSQLLogger($this->sqlLogger);

    $middleware = new DBalLogger\Middleware($this->sqlLogger);
    $config->setMiddlewares([$middleware]);

    $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory);

    // obtaining the entity manager

    $connection = DBAL\DriverManager::getConnection($conParams, $config, $eventManager);
    $entityManager = new ORMEntityManager($connection, $config, $eventManager);

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
    $config->addCustomStringFunction('bin2uuid', Functions\BinToUuid::class);
    $config->addCustomStringFunction('convert', Functions\ConvertUsing::class);
    $config->addCustomStringFunction('greatest', DoctrineExtensions\Query\Mysql\Greatest::class);
    $config->addCustomStringFunction('group_concat', DoctrineExtensions\Query\Mysql\GroupConcat::class);
    $config->addCustomStringFunction('if', DoctrineExtensions\Query\Mysql\IfElse::class);
    $config->addCustomStringFunction('regexp', DoctrineExtensions\Query\Mysql\Regexp::class);
    $config->addCustomStringFunction('sha2', DoctrineExtensions\Query\Mysql\Sha2::class);
    $config->addCustomStringFunction('year', DoctrineExtensions\Query\Mysql\Year::class);
  }

  /**
   * @return array A simple configuration instance without extras.
   */
  private function createSimpleConfiguration():array
  {
    $cache = null;
    $config = ORMSetup::createAttributeMetadataConfig(self::ENTITY_PATHS, $this->devMode);
    $config->enableNativeLazyObjects(true);
    $config->setEntityListenerResolver(new class($this->appContainer) extends ORM\Mapping\DefaultEntityListenerResolver {
      /** {@inheritdoc} */
      public function __construct(
        private IAppContainer $appContainer,
      ) {
      }

      /** {@inheritdoc} */
      public function resolve(string $className):object
      {
        try {
          return parent::resolve($className);
        } catch (Throwable $t) {
          $this->register($object = $this->appContainer->get($className));
          return $object;
        }
      }
    });
    $config->setDefaultRepositoryClassName(Repositories\EntityRepository::class);
    $config->setRepositoryFactory($this->appContainer->get(Repositories\RepositoryFactory::class));

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
    // create a driver chain for metadata reading
    $driverChain = new Doctrine\Persistence\Mapping\Driver\MappingDriverChain;

    // load superclass metadata mapping only, into driver chain
    // also registers Gedmo annotations.NOTE: you can personalize it
    \OCA\CAFEVDB\Wrapped\Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
      $driverChain, // our metadata driver chain, to hook into
    );
    //<<< Further annotations can go here
    \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\DoctrineExtensions::registerAnnotations();
    CJH\Setup::registerAnnotations();
    //>>>

    // now we want to register our application entities,
    // for that we need another metadata driver used for Entity namespace
    $attributeDriver = new ORM\Mapping\Driver\AttributeDriver(
      self::ENTITY_PATHS, // paths to look in
    );

    // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
    // register annotation driver for our application Entity namespace
    $driverChain->addDriver($attributeDriver, 'OCA\CAFEVDB\Database\Doctrine\ORM\Entities');

    // register metadata driver
    $config->setMetadataDriverImpl($driverChain);

    // gedmo extension listeners
    $attributeReader = new Gedmo\Mapping\Driver\AttributeReader();

    // loggable
    //$loggableListener = new Gedmo\Loggable\LoggableListener;
    $remoteAddress = $this->request->getRemoteAddress();
    $loggableListener = new Listeners\GedmoLoggableListener($this->userId, $remoteAddress);
    $loggableListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($loggableListener);

    // timestampable
    $timestampableListener = new Gedmo\Timestampable\TimestampableListener();
    $timestampableListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($timestampableListener);

    // soft deletable
    $softDeletableListener = new Gedmo\SoftDeleteable\SoftDeleteableListener();
    $softDeletableListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($softDeletableListener);
    $config->addFilter(self::SOFT_DELETEABLE_FILTER, \OCA\CAFEVDB\Wrapped\Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter::class);

    // blameable
    $blameableListener = new Gedmo\Blameable\BlameableListener();
    $blameableListener->setAnnotationReader($attributeReader);
    $blameableListener->setUserValue($this->userId);
    $evm->addEventSubscriber($blameableListener);

    // sluggable
    $sluggableListener =  $this->appContainer->get(Listeners\GedmoSluggableListener::class);
    $sluggableListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($sluggableListener);

    // sortable
    // $sortableListener = new Gedmo\Sortable\SortableListener;
    // $sortableListener->setAnnotationReader($attributeReader);
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
    $transformableListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($transformableListener);

    // translatable
    $this->translatable = $translatableListener = $this->appContainer->get(Listeners\GedmoTranslatableListener::class);
    // current translation locale should be set from session or hook later into the listener
    // most important, before entity manager is flushed
    $localeCode = $this->getLocaleCode($this->l);
    $this->translatableL10n = $this->l;
    $translatableListener->setTranslatableLocale($localeCode);
    $translatableListener->setDefaultLocale(ConfigConstants::DEFAULT_LOCALE);
    $translatableListener->setTranslationFallback(true);
    $translatableListener->setPersistDefaultLocaleTranslation(true);
    $translatableListener->setAnnotationReader($attributeReader);
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
    $foreignKeyListener->setAnnotationReader($attributeReader);
    $evm->addEventSubscriber($foreignKeyListener);

    return [ $config, $evm, $attributeReader ];
  }

  /**
   * Call ENTITY::__wakeup() if it exists.
   *
   * @param ORM\Event\PostLoadEventArgs $eventArgs TBD.
   *
   * @return void
   */
  public function postLoad(ORM\Event\PostLoadEventArgs $eventArgs)
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
        $column->setComment(trim(sprintf('%s enum(%s)', $column->getComment(), implode(',', $column->getType()->toArray()))));
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
   * @param object $entity The entity instance to persist.
   *
   * @return void
   *
   * @todo This unfortunately does not hack similar problems with
   * cascade="persist". There the "stock" persist operation seemingly
   * still causes problems.
   */
  public function persist(object $entity):void
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

        } catch (Throwable $t) {
          // can happen if the relation is allowed to be null
          // $this->logException($t);
        }
      }

    }
    parent::persist($entity);
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
   * @param Closure|IUndoable $action Action to register.
   *
   * @param null|callable $undo The associated undo-action. If $actionm
   * instanceof IUndoable then the $undo action is ignored. It should rather
   * be specified while constructing the IUndoable instance. The $undo
   * callable receives the return value of the $action callable as argument.
   *
   * @return UndoableRunQueue  Return the run-queue for  easy chaining
   * via UndoableRunQueue::register().
   */
  public function registerPreCommitAction(Closure|IUndoable $action, ?Closure $undo = null):UndoableRunQueue
  {
    if (!$this->isOwnTransactionActive()) {
      throw new Exceptions\DatabaseTransactionNotActiveException($this->l->t('There is no active database transaction, cannot register pre-commit actions.'));
    }
    $level = $this->getOwnTransactionNestingLevel() - 1;
    $actions = $this->preCommitActions[$level];
    if ($action instanceof Closure) {
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
    $this->checkForRollbackOnly();
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
      $this->postCommitActions->register(new GenericUndoable($action, undoCallback: null));
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
    if ($this->entityManager === null) {
      return false;
    }
    $connection = $this->entityManager->getConnection();
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
  public function beginTransaction():void
  {
    parent::beginTransaction();
    $level = $this->transactionNestingLevel++;
    if (empty($this->preCommitActions[$level])) {
      $this->preCommitActions[$level] = clone $this->appContainer->get(UndoableRunQueue::class);
    } else {
      $this->preCommitActions[$level]->clearActionQueue();
    }
    if ($this->transactionNestingLevel == 1) {
      $this->transactionExceptions = [];
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
   * @return void
   *
   * @see EntityManager::getTransactionNestingLevel()
   * @see EntityManager::registerPreFlushAction()
   * @see EntityManager::registerPreCommintAction()
   * @see EntityManager::registerPostCommitAction()
   *
   * @throws Exceptions\UndoableRunQueueException
   * @throws ConnectionException
   */
  public function commit():void
  {
    $this->checkForRollbackOnly();
    // execute all remaining pre-flush action
    $this->executePreFlushActions();
    // execute all pre-commit action of the current level
    $this->executePreCommitActions();
    parent::commit();
    --$this->transactionNestingLevel;
    if (!$this->isTransactionActive()) {
      // execute non-undoable actions after the final commit succeeded.
      $this->executePostCommitActions();
    }
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
  public function rollback():void
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
        $this->reopen();
      } catch (\Throable $t) {
        $this->logException($t, 'Unable to reopen after rollback');
      }
    }
  }

  /**
   * @return void
   *
   * @throws Exceptions\DatabaseRollbackOnlyException
   */
  private function checkForRollbackOnly():void
  {
    $connection = $this->getConnection();
    if ($connection && $connection->isTransactionActive() && $connection->isRollbackOnly()) {
      throw new Exceptions\DatabaseRollbackOnlyException(
        $this->l->t('The connection is marked for rollback only.'),
        previous: $this->transactionExceptions[0] ?? null,
      );
    }
  }

  /**
   * @param Throwable $exception
   *
   * @return void
   */
  public function pushTransactionException(Throwable $exception):void
  {
    $this->logException($exception, 'Remembering transaction exception');
    $this->transactionExceptions[] = $exception;
  }

  /**
   * @return array<int, Throwable>
   */
  public function getTransactionExceptions():ArrayAdapter
  {
    return $this->transactionExceptions;
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
    $this->checkForRollbackOnly();
  }

  /**
   * {@inheritdoc}
   */
  public function flush():void
  {
    $this->executePreFlushActions();
    parent::flush();
    $this->executePreFlushActions(); // in case the pre-flush handlers added to the list ...
  }

  /**
   * {@inheritdoc}
   *
   * @todo Get rid of this function, the meta-data class is rather an
   * internal data structure of Doctrine\ORM.
   */
  public function getClassMetadata(string $className):ClassMetadataInterface
  {
    if ($this->decorateClassMetadata) {
      return new ClassMetadataDecorator(
        metaData: $this->entityManager->getClassMetadata($className),
        entityManager: $this,
        l: $this->l,
        logger: $this->logger,
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
      $annotation = $this->attributeReader->getClassAnnotation($className, $annotationClass);
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
        $annotation = $this->attributeReader->getPropertyAnnotation($property, $annotationClass);
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
  public function getDataTransformer(string $key):?Transformable\Transformer\TransformerInterface
  {
    return $this->transformerPool[$key] ?? null;
  }

  /**
   * Compute the locale code from an IL10N object.
   *
   * @param null|IL10N $l10n Locale provider.
   *
   * @return string
   */
  protected function getLocaleCode(?IL10N $l10n): string
  {
    $locale = $l10n->getLocaleCode();
    if ($locale === null) {
      $locale = ConfigConstants::DEFAULT_LOCALE;
    } else {
      $locale = $this->l->getLocaleCode();
    }
    if (strpos($locale, '_') === false) {
      $locale = $locale . '_' . strtoupper($locale);
    }
    return $locale;
  }

  /**
   * Set the locale for the translatable Listeners*
   *
   * @param null|IL10N $l10n Locale provider.
   *
   * @return null|IL10N
   */
  public function setTranslatableL10N(?IL10N $l10n):?IL10N
  {
    $locale = $this->getLocaleCode($l10n);
    $oldLocale = $this->getLocaleCode($this->translatableL10n);
    if ($oldLocale != $this->translatable->getTranslatableLocale()) {
      throw new UnexpectedValueException($this->l->t('Unexpected locale settings "%1$s" vs. "%2$s".', [
        $oldLocale, $this->translatable->getTranslatableLocale(),
      ]));
    }
    $this->translatable->setTranslatableLocale($locale);
    $this->getConfiguration()->setDefaultQueryHint(
      Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
      $locale
    );
    $oldL10n = $this->translatableL10n;
    $this->translatableL10n = $l10n;

    return $oldL10n;
  }

  /**
   * @return null|IL10N
   */
  public function getTranslatableL10n():?IL10N
  {
    return $this->translatableL10n;
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

    } catch (Throwable $t) {

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

    $annotationClass = \OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Transformable::class;
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
      } catch (Throwable $t2) {
        $this->logException($t2, 'Reopening entity-manager failed.');
      }

      throw $e;
    }
  }
}
