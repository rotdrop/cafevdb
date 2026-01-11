<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use SplObjectStorage;
use UnexpectedValueException;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

use Pimple\Container as InnerContainer;
use OC\Session\Memory as MemorySession;
use OC\AppFramework\Utility\SimpleContainer;

use OCP\AppFramework\IAppContainer;
use OCP\Authentication\LoginCredentials\ICredentials as ILoginCredentials;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory as L10NFactory;
use OCP\IRequest;
use OCP\IUser;
use OCP\ISession;
use OCP\IUserSession;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

use OCA\BAV\Service\BAV as BankAccountValidator;

use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Database\Doctrine;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\Registration;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/** Provide a couple of important services, partially using mocked classes. */
class MockProvider
{
  public const USER_GROUP_VALUE = 'orchestra_group';

  /** Provide some overridable defaults. */
  public const CONFIG_MOCK_VALUES = [
    ConfigConstants::USER_GROUP_KEY => self::USER_GROUP_VALUE,
    ConfigConstants::CONFIG_LOCK_KEY => false,
    ConfigConstants::ORCHESTRA_LOCALE_KEY => 'de_DE.UTF-8',
  ];

  public const EXECUTIVE_BOARD_UID = 'john.doe';

  public readonly string $appName;

  private array $instances = [];

  private static array $mockedServices;

  private static array $originalInstances = [];

  private static InnerContainer $serverContainerSnapshot;

  private static InnerContainer $appContainerSnapshot;

  private array $appConfigValues = [];

  private array $userConfigValues = [];

  private array $systemConfigValues = [];

  private ReflectionMethod $getMockBuilderMethod;

  public const TEST_IBAN = 'DE02700100800030876808';
  public const IBAN_INFO = [
    'iban' => self::TEST_IBAN,
    'country' => 'Deutschland (DE)',
    'bic' => 'PBNKDEFFXXX',
    'blz' => '70010080',
    'account' => '0030876808',
    'bank' => 'Postbank Ndl der Deutsche Bank',
    'city' => 'München',
  ];

  /** {@inheritdoc} */
  private function __construct(
    protected TestCase $testCase,
    protected DatabaseProvider $databaseProvider,
    protected Logger $logger,
  ) {
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $this->appName = $app->get('appName');

    $this->getMockBuilderMethod = new ReflectionMethod($this->testCase, 'getMockBuilder');

    $this->registerServices();
  }

  /**
   * Create a new instance.
   *
   * @param TestCase $testCase
   *
   * @return self
   */
  public static function create(TestCase $testCase): self
  {
    return new self(
      testCase: $testCase,
      databaseProvider: \OCP\Server::get(DatabaseProvider::class),
      logger: \OCP\Server::get(Logger::class),
    );
  }

  /**
   * Take a snapshot of the given container. This ain't pretty, so better find
   * a way not to inject mock objects into the container, as those cannot be
   * shared accross tests. As long as we do this we take a snapshot of the
   * container and restore that afterwards.
   *
   * @param SimpleContainer $container Server- or app-container.
   *
   * @return InnerContainer Level-one clone of the inner container used inside
   * the SimpleContainer class. That means: the cached objects as such are
   * kept, but the containers storing the objects are cloned.
   */
  private static function snapshotContainer(SimpleContainer $container): InnerContainer
  {
    $innerContainer = new ReflectionProperty(SimpleContainer::class, 'container')->getValue($container);
    $reflectionContainer = new ReflectionClass($innerContainer);
    $snapshot = new InnerContainer;
    /** @var ReflectionProperty $property */
    foreach ($reflectionContainer->getProperties() as $propertyAccessor) {
      $property = $propertyAccessor->getValue($innerContainer);
      if (is_object($property) && get_class($property) === SplObjectStorage::class) {
        $snapshotProperty = new SplObjectStorage();
        $snapshotProperty->addAll($property);
        $property = $snapshotProperty;
      }
      $propertyAccessor->setValue($snapshot, $property);
    }
    $snapshot->offsetUnset(\OC\DateTimeZone::class);
    return $snapshot;
  }

  /**
   * Restore the previously generated snapshot.
   *
   * @param SimpleContainer $container
   *
   * @param InnerContainer $snapshot
   *
   * @return void
   */
  private static function restoreContainer(SimpleContainer $container, InnerContainer $snapshot): void
  {
    /** @var InnerContainer $innerContainer */
    $innerContainer = new ReflectionProperty(SimpleContainer::class, 'container')->getValue($container);
    // print_r($innerContainer->keys());
    // print_r($snapshot->keys());
    $reflectionContainer = new ReflectionClass($innerContainer);
    /** @var ReflectionProperty $propertyAccessor */
    foreach ($reflectionContainer->getProperties() as $propertyAccessor) {
      $property = $propertyAccessor->getValue($snapshot);
      if (is_object($property) && get_class($property) === SplObjectStorage::class) {
        /** @var SplObjectStorage $property */
        /** @var SplObjectStorage $containerProperty */
        $containerProperty = new SplObjectStorage();
        $containerProperty->addAll($property);
        $property = $containerProperty;
      }
      $propertyAccessor->setValue($innerContainer, $property);
    }
  }

  /** @return void */
  private function registerServices(): void
  {
    self::$mockedServices = self::$mockedServices ?? self::getMockedServices();
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $appContainer = $app->get(IAppContainer::class);
    $appContainer->registerService(LoggerInterface::class, fn() => $this->logger);
    \OC::$server->registerService(LoggerInterface::class, fn() => $this->logger);
    if (empty(self::$serverContainerSnapshot)) {
      self::$serverContainerSnapshot = self::snapshotContainer(\OC::$server);
      self::$appContainerSnapshot = self::snapshotContainer($appContainer);
    } else {
      self::restoreContainer(\OC::$server, self::$serverContainerSnapshot);
      self::restoreContainer($appContainer, self::$appContainerSnapshot);
    }
    $mockContainer = $this->getAppContainer();
    foreach (array_keys(self::$mockedServices) as $service) {
      if (str_starts_with($service, \OCA\CAFEVDB::class)) {
        $appContainer->registerService($service, function() use ($service, $mockContainer) {
          return $mockContainer->get($service);
        });
      } else {
        if (empty(self::$originalInstances[$service])) {
          self::$originalInstances[$service] = $appContainer->get($service);
        }
        $factory = function() use ($service, $mockContainer, $appContainer) {
          $result = $mockContainer->get($service);
          if ($result === null) {
            if (empty(self::$originalInstances[$service])) {
              $result = $appContainer->get($service);
            } else {
              $result = self::$originalInstances[$service];
            }
          }
          return $result;
        };
        \OC::$server->registerService($service, $factory);
        $appContainer->registerService($service, $factory);
      }
    }
    // echo get_class($appContainer->get(LoggerInterface::class)) . PHP_EOL;
    // echo get_class(\OC::$server->get(LoggerInterface::class)) . PHP_EOL;
  }

  /**
   * @param string $className The name of the class to mock.
   *
   * @return MockBuilder An instance tied to $this->testCase.
   */
  protected function getMockBuilder(string $className):MockBuilder
  {
    return $this->getMockBuilderMethod->invoke($this->testCase, $className);
  }

  /** {@inheritdoc} */
  protected function never(): mixed
  {
    $method = new ReflectionMethod($this->testCase, 'never');
    return $method->invoke($this->testCase);
  }

  /** {@inheritdoc} */
  protected function atMost(int $count = 2): mixed
  {
    $method = new ReflectionMethod($this->testCase, 'atMost');
    return $method->invoke($this->testCase, $count);
  }

  /**
   * Mock the cloud config provider.
   *
   * @return IConfig
   */
  public function getCloudConfig():IConfig
  {
    $className = IConfig::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder($className)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('setAppValue')->willReturnCallback(
      function(string $appName, string $key, mixed $value): void {
        /* ignore */
      },
    );
    $instance->method('getAppValue')->willReturnCallback(
      function(string $appName, string $key, mixed $default = null): mixed {
        // print_r($this->appConfigValues);
        if (isset($this->appConfigValues[$appName . $key])) {
          return $this->appConfigValues[$appName . $key];
        }
        $newKey = array_search($key, OldSettingsKeys::APP_KEYS);
        if ($newKey !== false) {
          $key = $newKey;
        }
        if (isset(self::CONFIG_MOCK_VALUES[$key])) {
          return self::CONFIG_MOCK_VALUES[$key];
        }
        switch ($key) {
          case ConfigConstants::APP_DB_NAME:
          case ConfigConstants::APP_DB_SERVER:
          case ConfigConstants::APP_DB_USER:
          case ConfigConstants::APP_DB_PASSWORD:
            $dbConfig = $this->databaseProvider->getDatabaseConfig();
            return $dbConfig[$key] ?? null;
          case ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY:
          case OldSettingsKeys::APP_KEYS[ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY]:
            return null;
        }
        return $default;
      }
    );
    $instance->method('setAppValue')->willReturnCallback(
      function(string $appName, string $key, mixed $value): void {
        $this->appConfigValues[$appName . $key] = $value;
        // print_r($this->appConfigValues);
      },
    );
    $instance->method('deleteAppValue')->willReturnCallback(
      function(string $appName, string $key): void {
        unset($this->appConfigValues[$appName . $key]);
      },
    );
    $instance->method('getAppKeys')->willReturnCallback(
      function(string $appName): array {
        $appNameLen = strlen($appName);
        $appKeys =
          array_map(
            fn(string $key) => substr($key, $appNameLen),
            array_filter(
              array_keys($this->appConfigValues),
              fn(string $key) => str_starts_with($key, $appName),
            ),
          );
        return $appKeys;
      },
    );
    $instance->method('getUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key, mixed $default = null) {
        $value = $default;
        if (isset($this->userConfigValues[$userId . $appName . $key])) {
          $value = $this->userConfigValues[$userId . $appName . $key];
        } elseif ($userId == self::EXECUTIVE_BOARD_UID && $appName == 'core') {
          // Default to German stuff as this is the only region where the app
          // is used ATM.
          switch ($key) {
            case 'timezone':
              $value = 'Europe/Berlin';
              break;
            case 'lang':
              $value = 'de';
              break;
            case 'locale':
              $value = 'de';
              break;
          }
        }
        return $value;
      },
    );
    $instance->method('setUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key, mixed $value) {
        if ($key == 'timezone') {
          print_r(compact('userId', 'appName', 'key', 'value'));
        }
        $this->userConfigValues[$userId . $appName . $key] = $value;
      },
    );
    $instance->method('deleteUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key): void {
        unset($this->userConfigValues[$userId . $appName . $key]);
      },
    );
    $instance->method('setSystemValue')->willReturnCallback(
      function(string $key, mixed $value): void {
        $this->systemConfigValues[$key] = $value;
      }
    );
    $instance->method('getSystemValue')->willReturnCallback(
      function(string $key, mixed $default = null): mixed {
        // echo $key . ' => ' . ($this->systemConfigValues[$key] ?? $default) . PHP_EOL;
        return $this->systemConfigValues[$key] ?? $default;
      }
    );

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('setSystemValues');

    return $instance;
  }

  /**
   * @return ConfigService
   */
  public function getConfigService(): ConfigService
  {
    $className = ConfigService::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = new ConfigService(
      appName: $this->appName,
      appContainer: $this->getAppContainer(),
      logger: $this->getLoggerInterface(),
      l: $this->getL10N(),
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return EntitiyManager
   */
  public function getEntityManager(): EntityManager
  {
    $className = EntityManager::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $l = $this->getL10N();
    $cloudLogger = new Doctrine\DBAL\Logging\CloudLogger(
      encryptionService: $this->getEncryptionService(),
      eventDispatcher: $this->getEventDispatcher(),
      logger: $this->getLoggerInterface(),
      l: $l,
    );
    $instance = new EntityManager(
      deprecationLogger: $app->get(Doctrine\DeprecationLogger::class),
      sqlLogger: $cloudLogger,
      encryptionService: $this->getEncryptionService(),
      l: $l,
      request: $this->getRequest(),
      appContainer: $this->getAppContainer(),
      logger: $this->getLoggerInterface(),
      appName: $this->appName,
      cloudConfig: $this->getCloudConfig(),
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return RepositoryFactory
   */
  public function getRepositoryFactory(): RepositoryFactory
  {
    $className = RepositoryFactory::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = new RepositoryFactory(
      appContainer: $this->getAppContainer(),
      logger: $this->logger,
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return IRequest
   */
  public function getRequest(): IRequest
  {
    $className = IRequest::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IRequest::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getPathInfo')->willReturn('/apps/' . $this->appName . '/blahblah');

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('getEnv');

    return $instance;
  }

  /**
   * @return LoggerInterface
   */
  public function getLoggerInterface(): LoggerInterface
  {
    return $this->logger;
  }

  /** @return EncryptionService */
  public function getEncryptionService(): EncryptionService
  {
    $className = EncryptionService::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $instance = new EncryptionService(
      appName: $app->get('appName'),
      cloudConfig: $this->getCloudConfig(),
      asymKeyService: $this->getAsymmetricKeyService(),
      hasher: \OCP\Server::get(IHasher::class),
      eventDispatcher: $this->getEventDispatcher(),
      logger: $this->getLoggerInterface(),
      authorization: $this->getAuthorizationService(),
      userSession: $this->getUserSession(),
      cryptoFactory: $this->getCryptoFactory(),
      credentialsStore: $this->getCredentialsStore(),
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return AsymmetricKeyService
   */
  public function getAsymmetricKeyService(): Crypto\AsymmetricKeyService
  {
    $className = Crypto\AsymmetricCryptorInterface::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(Crypto\AsymmetricKeyService::class)
      ->disableOriginalConstructor()
      ->getMock();
    // $instance->method('initEncryptionKeyPair')->willReturn();
    $instance->method('getSharedPrivateValue')->willReturn(null);
    $instance->method('getCryptor')->willReturnCallback(
      function(string $userId) {
        return new class implements Crypto\AsymmetricCryptorInterface {
          /** {@inheritdoc} */
          public function setPrivateKey(mixed $privKey, ?string $password = null): Crypto\AsymmetricCryptorInterface
          {
            return $this;
          }
          /** {@inheritdoc} */
          public function setPublicKey(mixed $pubKey): Crypto\AsymmetricCryptorInterface
          {
            return $this;
          }
          /** {@inheritdoc} */
          public function getPublicKey():mixed
          {
            return 'nothing';
          }
          /** {@inheritdoc} */
          public function sign(?string $data):string
          {
            return 'nothing';
          }
          /** {@inheritdoc} */
          public function verify(?string $data, string $signature):bool
          {
            return true;
          }
          /** {@inheritdoc} */
          public function canSign():bool
          {
            return true;
          }
          /** {@inheritdoc} */
          public function canVerify():bool
          {
            return true;
          }
          /** {@inheritdoc} */
          public function encrypt(?string $data):?string
          {
            return $data;
          }
          /** {@inheritdoc} */
          public function decrypt(?string $data):?string
          {
            return $data;
          }
          /** {@inheritdoc} */
          public function canEncrypt():bool
          {
            return true;
          }
          /** {@inheritdoc} */
          public function canDecrypt():bool
          {
            return true;
          }
          /** {@inheritdoc} */
          public static function isEncrypted(?string $data):?bool
          {
            return true;
          }
        };
      },
    );

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('removeRecryptionRequestHandledNotification');

    return $instance;
  }

  /**
   * @return AuthorizationService
   */
  public function getAuthorizationService(): AuthorizationService
  {
    $className = AuthorizationService::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(AuthorizationService::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getUserPermissions')->willReturnCallback(
      function(string $uid) {
        switch ($uid) {
          case self::EXECUTIVE_BOARD_UID:
            return AuthorizationService::PERMISSION_ALL;
          default:
            return AuthorizationService::PERMISSION_NONE;
        }
      }
    );

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('isAdmin');

    return $instance;
  }

  /**
   * @return Crypro\CryptoFactoryInterface
   */
  public function getCryptoFactory(): Crypto\CryptoFactoryInterface
  {
    $className = Crypto\CryptoFactoryInterface::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = new Crypto\HaliteCryptoFactory($this->getAppContainer());

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return ICredentialsStore
   */
  public function getCredentialsStore(): ICredentialsStore
  {
    $className = ICredentialsStore::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(ICredentialsStore::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getLoginCredentials')->willReturn(
      new class implements ILoginCredentials {
        /** {@inheritdoc} */
        public function getUID()
        {
          return self::EXECUTIVE_BOARD_UID;
        }
        /** {@inheritdoc} */
        public function getLoginName()
        {
          return $this->getUID();
        }
        /** {@inheritdoc} */
        public function getPassword()
        {
          return 'nothing';
        }
      }
    );

    $this->instances[$className] = $instance;

    $instance->expects($this->atMost(2))->method('getLoginCredentials');

    return $instance;
  }

  /**
   * @return IUser
   */
  public function getUser(): IUser
  {
    $className = IUser::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IUser::class)
      ->disableOriginalConstructor()
      ->getMock();

    $instance->method('getUID')->willReturn(self::EXECUTIVE_BOARD_UID);

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('getFirstLogin');

    return $instance;
  }

  /**
   * @return ISession
   */
  public function getSession(): ISession
  {
    $className = ISession::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = new MemorySession;
    $instance->set('user_id', $this->getUser()->getUID());
    // $instance->set('timezone', :

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return IUserSession
   */
  public function getUserSession(): IUserSession
  {
    $className = IUserSession::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(\OC\User\Session::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getUser')->willReturn($this->getUser());
    $instance->method('getSession')->willReturn($this->getSession());

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('setVolatileActiveUser');

    return $instance;
  }

  /**
   * @return IEventDispatcher
   */
  public function getEventDispatcher(): IEventDispatcher
  {
    $className = IEventDispatcher::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IEventDispatcher::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('removeListener');

    return $instance;
  }

  /** @return IL10N */
  public function getL10N(): IL10N
  {
    $className = IL10N::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    /** @var L10NFactory $factory */
    $factory = \OCP\Server::get(L10NFactory::class);
    $instance = $factory->get($this->appName, 'de');

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * Fake the bank account validator as obtaining real result is really
   * involved and would result in a round-trip to the Deutsche Bundesbank and
   * excessive database operations.
   *
   * @return BAV
   */
  public function getBankAccountValidator(): BankAccountValidator
  {
    $className = BankAccountValidator::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder($className)
      ->disableOriginalConstructor()
      ->getMock();

    $instance->method('getMainAgency')
      ->with(self::IBAN_INFO['blz'])
      ->willReturn(new class() {
        /** @return string */
        public function getBIC() {
          return MockProvider::IBAN_INFO['bic'];
        }
        /** @return string */
        public function getName() {
          return MockProvider::IBAN_INFO['bank'];
        }
        /** @return string */
        public function getCity() {
          return MockProvider::IBAN_INFO['city'];
        }
      });
    $instance->method('isValidBank')
      ->with(self::IBAN_INFO['blz'])
      ->willReturn(true);
    $instance->method('isValidAccount')
      ->with(self::IBAN_INFO['account'])
      ->willReturn(true);

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('update');

    return $instance;
  }

  /**
   * Register a class instance to be returned by the app-container.
   *
   * @param string $className
   *
   * @param mixed $instance
   *
   * @return void
   */
  public function registerClassInstance(string $className, mixed $instance): void
  {
    if ($instance === null) {
      unset($this->instances[$className]);
    } else {
      $this->instances[$className] = $instance;
    }
  }

  /** @return array */
  private static function getMockedServices(): array
  {
    return [
      BankAccountValidator::class => fn(self $self) => $self->getBankAccountValidator(),
      ConfigService::class => fn(self $self) => $self->getConfigService(),
      Connection::class => fn(self $self) => $self->getEntityManager()->getConnection(),
      EncryptionService::class => fn(self $self) => $self->getEncryptionService(),
      EntityManager::class => fn(self $self) => $self->getEntityManager(),
      IConfig::class => fn(self $self) => $self->getCloudConfig(),
      IEventDispatcher::class => fn(self $self) => $self->getEventDispatcher(),
      IL10N::class => fn(self $self) => $self->getL10N(),
      IRequest::class => fn(self $self) => $self->getRequest(),
      ISession::class => fn(self $self) => $self->getSession(),
      IUserSession::class => fn(self $self) => $self->getUserSession(),
      Registration::USER_LOCALE => fn(self $self) => 'de_DE.UTF-8',
      lcfirst(Registration::USER_LOCALE) => fn(self $self) => 'de_DE.UTF-8',
      RepositoryFactory::class => fn(self $self) => $self->getRepositoryFactory(),
      UndoableRunQueue::class => fn(self $self) => new UndoableRunQueue(
        $self->getAppContainer(),
        $self->getLoggerInterface(),
        $self->getL10N(),
      ),
    ];
  }

  /**
   * @return IAppContainer
   */
  public function getAppContainer(): IAppContainer
  {
    $className = IAppContainer::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IAppContainer::class)
      ->disableOriginalConstructor()
      ->getMock();

    $instance->method('get')->willReturnCallback(
      function(string $service) {
        if (!empty($this->instances[$service])) {
          return $this->instances[$service];
        }
        if (!empty(self::$mockedServices[$service])) {
          return self::$mockedServices[$service]($this);
        }
        $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
        // try to generate "the real thing"
        $newInstance = $app->get($service);
        if ($newInstance === null) {
          throw new RuntimeException($service . ' NOT FOUND');
        }
        $this->instances[$service] = $newInstance;
        // echo __CLASS__ . '::' . __METHOD__ . ': RETURNING NEW ' . $service . PHP_EOL;
        return $newInstance;
      },
    );

    $instance->method('resolve')->willReturnCallback(
      function(string $service) {
        $oldInstance = $this->instances[$service] ?? null;
        unset($this->instances[$service]);
        if (!empty(self::$mockedServices[$service])) {
          $instance = self::$mockedServices[$service]($this);
        }
        if ($oldInstance) {
          $this->instances[$service] = $oldInstance;
        } else {
          unset($this->instances[$service]);
        }
        if (empty($instance)) {
          $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
          $instance = $app->getContainer()->resolve($service);
        }
        return $instance;
      }
    );

    $instance->expects($this->never())->method('registerMiddleWare');

    $this->instances[$className] = $instance;

    return $instance;
  }
}
