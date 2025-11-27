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
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/** Provide a couple of important services, partially using mocked classes. */
class MockProvider
{
  public const CONFIG_MOCK_VALUES = [
    ConfigConstants::USER_GROUP_KEY => 'orchestra_group',
    ConfigConstants::CONFIG_LOCK_KEY => false,
  ];

  public const EXECUTIVE_BOARD_UID = 'john.doe';

  public readonly string $appName;

  private array $instances = [];

  private TestCase $mockOwner;

  /** {@inheritdoc} */
  public function __construct(
    protected DatabaseProvider $databaseProvider,
    protected IHasher $hasher,
    protected Logger $logger,
  ) {
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $this->appName = $app->get('appName');

    $this->instances[LoggerInterface::class] = $logger;

    $this->mockOwner = new class('Placeholder') extends TestCase {
    };
  }

  /**
   * Use the ReflectionMethod class to get hold of the protected
   * TestCase::getMockBuilder() method.
   *
   * @param TestCase $testCase The calling test-case.
   *
   * @param string $className The name of the class to mock.
   *
   * @return MockBuild An instance tied to $testCase.
   */
  protected static function getMockBuilder(TestCase $testCase, string $className):MockBuilder
  {
    $method = new ReflectionMethod(get_class($testCase), 'getMockBuilder');
    return $method->invoke($testCase, $className);
  }

  /**
   * Mock the cloud config provider.
   *
   * @param TestCase $testCase
   *
   * @return IConfig
   */
  public function getCloudConfig(TestCase $testCase):IConfig
  {
    $className = IConfig::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, $className)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('setAppValue')->willReturnCallback(
      function(string $appName, string $key, mixed $value): void {
        /* ignore */
      },
    );
    $instance->method('getAppValue')->willReturnCallback(
      function(string $appName, string $key, mixed $default): mixed {
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
          case strtolower(ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY):
            return null;
        }
        throw new UnexpectedValueException('Unexpected config key in test-suite: "' . $key .'".');
      }
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return EntitiyManager
   */
  public function getEntityManager(TestCase $testCase): EntityManager
  {
    $className = EntityManager::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $l = $this->getL10N($testCase);
    $cloudLogger = new Doctrine\DBAL\Logging\CloudLogger(
      encryptionService: $this->getEncryptionService($testCase),
      eventDispatcher: $this->getEventDispatcher($testCase),
      logger: $this->getLoggerInterface($testCase),
      l: $l,
    );
    $instance = new EntityManager(
      deprecationLogger: $app->get(Doctrine\DeprecationLogger::class),
      sqlLogger: $cloudLogger,
      encryptionService: $this->getEncryptionService($testCase),
      l: $l,
      request: $this->getRequest($testCase),
      appContainer: $this->getAppContainer($testCase),
      logger: $this->getLoggerInterface($testCase),
      appName: $this->appName,
      cloudConfig: $this->getCloudConfig($testCase),
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return RepositoryFactory
   */
  public function getRepositoryFactory(TestCase $testCase): RepositoryFactory
  {
    $className = RepositoryFactory::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = new RepositoryFactory(
      appContainer: $this->getAppContainer($testCase),
      logger: $this->logger,
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return IRequest
   */
  public function getRequest(TestCase $testCase): IRequest
  {
    $className = IRequest::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, IRequest::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getPathInfo')->willReturn('/apps/' . $this->appName . '/blahblah');

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return LoggerInterface
   */
  public function getLoggerInterface(TestCase $testCase): LoggerInterface
  {
    return $this->logger;
  }

  /** @return EncryptionService */
  public function getEncryptionService(TestCase $testCase): EncryptionService
  {
    $className = EncryptionService::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    $instance = new EncryptionService(
      appName: $app->get('appName'),
      containerConfig: $this->getCloudConfig($testCase),
      asymKeyService: $this->getAsymmetricKeyService($testCase),
      hasher: \OCP\Server::get(IHasher::class),
      eventDispatcher: $this->getEventDispatcher($testCase),
      logger: $this->getLoggerInterface($testCase),
      authorization: $this->getAuthorizationService($testCase),
      userSession: $this->getUserSession($testCase),
      cryptoFactory: $this->getCryptoFactory($testCase),
      credentialsStore: $this->getCredentialsStore($testCase),
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return AsymmetricKeyService
   */
  public function getAsymmetricKeyService(TestCase $testCase): Crypto\AsymmetricKeyService
  {
    $className = Crypto\AsymmetricCryptorInterface::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, Crypto\AsymmetricKeyService::class)
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
   * @param TestCase $testCase
   *
   * @return AuthorizationService
   */
  public function getAuthorizationService(TestCase $testCase): AuthorizationService
  {
    $className = AuthorizationService::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, AuthorizationService::class)
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
   * @param TestCase $testCase
   *
   * @return Crypro\CryptoFactoryInterface
   */
  public function getCryptoFactory(TestCase $testCase): Crypto\CryptoFactoryInterface
  {
    $className = Crypto\CryptoFactoryInterface::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = new Crypto\HaliteCryptoFactory($this->getAppContainer($testCase));

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return ICredentialsStore
   */
  public function getCredentialsStore(TestCase $testCase): ICredentialsStore
  {
    $className = ICredentialsStore::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, ICredentialsStore::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getLoginCredentials')->willReturn(
      new class implements ILoginCredentials {
        /** {@inheritdoc} */
        public function getUID() {
          return self::EXECUTIVE_BOARD_UID;
        }
        /** {@inheritdoc} */
        public function getLoginName() {
          return $this->getUID();
        }
        /** {@inheritdoc} */
        public function getPassword() {
          return 'nothing';
        }
      }
    );

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return IUser
   */
  public function getUser(TestCase $testCase): IUser
  {
    $className = IUser::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, IUser::class)
      ->disableOriginalConstructor()
      ->getMock();

    $instance->method('getUID')->willReturn(self::EXECUTIVE_BOARD_UID);

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return IUserSession
   */
  public function getUserSession(TestCase $testCase): IUserSession
  {
    $className = IUserSession::class;

    if ($this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, IUserSession::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('getUser')->willReturn($this->getUser($testCase));

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return IEventDispatcher
   */
  public function getEventDispatcher(TestCase $testCase): IEventDispatcher
  {
    $className = IEventDispatcher::class;

    $instance = self::getMockBuilder($this->mockOwner, IEventDispatcher::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->instances[$className] = $instance;

    return $instance;
  }

  /**
   * @param TestCase $testCase
   *
   * @return IL10N
   */
  public function getL10N(TestCase $testCase): IL10N
  {
    $app = \OCP\Server::get(\OCA\CAFEVDB\AppInfo\Application::class);
    return $app->get(IL10N::class);
  }

  /**
   * @param TestCase $testCase
   *
   * @return IAppContainer
   */
  public function getAppContainer(TestCase $testCase): IAppContainer
  {
    $className = IAppContainer::class;

    if (false && $this->instances[$className]) {
      return $this->instances[$className];
    }

    $instance = self::getMockBuilder($this->mockOwner, IAppContainer::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('get')->willReturnCallback(
      function(string $service) use ($testCase) {
        // echo __CLASS__ . '::' . __METHOD__ . ': CALLED WITH ' . $service . PHP_EOL;
        if (!empty($this->instances[$service])) {
          // echo __CLASS__ . '::' . __METHOD__ . ': RETURNING CACHED ' . $service . PHP_EOL;
          return $this->instances[$service];
        }
        switch ($service) {
          case IEventDispatcher::class:
            return $this->getEventDispatcher($testCase);
          case RepositoryFactory::class:
            return $this->getRepositoryFactory($testCase);
          case UndoableRunQueue::class:
            return new UndoableRunQueue(
              $this->getAppContainer($testCase),
              $this->getLoggerInterface($testCase),
              $this->getL10N($testCase),
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
