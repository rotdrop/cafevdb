<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use ReflectionMethod;
use RuntimeException;
use UnexpectedValueException;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\IAppContainer;
use OCP\Authentication\LoginCredentials\ICredentials as ILoginCredentials;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Database\Doctrine;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/** Provide a couple of important services, partially using mocked classes. */
class MockProvider
{
  public const USER_GROUP_VALUE = 'orchestra_group';

  public const CONFIG_MOCK_VALUES = [
    ConfigConstants::USER_GROUP_KEY => self::USER_GROUP_VALUE,
    ConfigConstants::CONFIG_LOCK_KEY => false,
  ];

  public const EXECUTIVE_BOARD_UID = 'john.doe';

  public readonly string $appName;

  private array $instances = [];

  private TestCase $mockOwner;

  private array $appConfigValues = [];

  private array $userConfigValues = [];

  /** {@inheritdoc} */
  public function __construct(
    protected DatabaseProvider $databaseProvider,
    protected IHasher $hasher,
    protected Logger $logger,
  ) {
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $this->appName = $app->get('appName');

    $this->instances[LoggerInterface::class] = $logger;

    $this->mockOwner = new class('Placeholder') extends TestCase
    {
      /**
       * Make the mock-builder public.
       *
       * @param string $className
       *
       * @return MockBuilder
       */
      public function exportMockBuilder(string $className): MockBuilder
      {
        return $this->getMockBuilder($className);
      }
    };
  }

  /**
   * @param string $className The name of the class to mock.
   *
   * @return MockBuilder An instance tied to $this->mockOwner.
   */
  protected function getMockBuilder(string $className):MockBuilder
  {
    return $this->mockOwner->exportMockBuilder($className);
  }

  /**
   * Mock the cloud config provider.
   *
   * @return IConfig
   */
  public function getCloudConfig():IConfig
  {
    $className = IConfig::class;

    if ($this->instances[$className]) {
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
    $instance->method('getUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key, mixed $default = null) {
        return $this->userConfigValues[$userId . $appName . $key] ?? $default;
      },
    );
    $instance->method('setUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key, mixed $value) {
        $this->userConfigValues[$userId . $appName . $key] = $value;
      },
    );
    $instance->method('deleteUserValue')->willReturnCallback(
      function(string $userId, string $appName, string $key): void {
        unset($this->userConfigValues[$userId . $appName . $key]);
      },
    );
    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return ConfigService
   */
  public function getConfigService(): ConfigService
  {
    $className = ConfigService::class;

    if ($this->instances[$className]) {
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

    if ($this->instances[$className]) {
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

    if ($this->instances[$className]) {
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

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IRequest::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getPathInfo')->willReturn('/apps/' . $this->appName . '/blahblah');

    $this->instances[$className] = $instance;

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

    if ($this->instances[$className]) {
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

    if ($this->instances[$className]) {
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

    return $instance;
  }

  /**
   * @return AuthorizationService
   */
  public function getAuthorizationService(): AuthorizationService
  {
    $className = AuthorizationService::class;

    if ($this->instances[$className]) {
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

    return $instance;
  }

  /**
   * @return Crypro\CryptoFactoryInterface
   */
  public function getCryptoFactory(): Crypto\CryptoFactoryInterface
  {
    $className = Crypto\CryptoFactoryInterface::class;

    if ($this->instances[$className]) {
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

    if ($this->instances[$className]) {
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

    return $instance;
  }

  /**
   * @return IUser
   */
  public function getUser(): IUser
  {
    $className = IUser::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IUser::class)
      ->disableOriginalConstructor()
      ->getMock();

    $instance->method('getUID')->willReturn(self::EXECUTIVE_BOARD_UID);

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return IUserSession
   */
  public function getUserSession(): IUserSession
  {
    $className = IUserSession::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = $this->getMockBuilder(IUserSession::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getUser')->willReturn($this->getUser());

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return IEventDispatcher
   */
  public function getEventDispatcher(): IEventDispatcher
  {
    $className = IEventDispatcher::class;

    $instance = $this->getMockBuilder(IEventDispatcher::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @return IL10N
   */
  public function getL10N(): IL10N
  {
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    return $app->get(IL10N::class);
  }

  /**
   * @return IAppContainer
   */
  public function getAppContainer(): IAppContainer
  {
    $className = IAppContainer::class;

    if ($this->instances[$className]) {
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
        switch ($service) {
          case IEventDispatcher::class:
            return $this->getEventDispatcher();
          case IUserSession::class:
            return $this->getUserSession();
          case ConfigService::class:
            return $this->getConfigService();
          case EncryptionService::class:
            return $this->getEncryptionService();
          case IConfig::class:
            return $this->getCloudConfig();
          case RepositoryFactory::class:
            return $this->getRepositoryFactory();
          case UndoableRunQueue::class:
            return new UndoableRunQueue(
              $this->getAppContainer(),
              $this->getLoggerInterface(),
              $this->getL10N(),
            );
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

    $this->instances[$className] = $instance;

    return $instance;
  }
}
