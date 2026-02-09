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
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

use OCA\BAV\Service\BAV as BankAccountValidator;

use OCA\RotDrop\Tests\AbstractMockProvider;
use OCA\RotDrop\Tests\Logger;
use OCA\RotDrop\Tests\DatabaseProvider;

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
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/** Provide a couple of important services, partially using mocked classes. */
class MockProvider extends AbstractMockProvider
{
  public const USER_GROUP_VALUE = 'orchestra_group';

  /** Provide some overridable defaults. */
  public const CONFIG_MOCK_VALUES = [
    ConfigConstants::USER_GROUP_KEY => self::USER_GROUP_VALUE,
    ConfigConstants::CONFIG_LOCK_KEY => false,
    ConfigConstants::ORCHESTRA_LOCALE_KEY => 'de_DE.UTF-8',
  ];

  public const EXECUTIVE_BOARD_UID = parent::CLOUD_USER_UID;

  private array $appConfigValues = [];

  private array $userConfigValues = [];

  private array $systemConfigValues = [];

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
            return $this->databaseProvider->getDatabaseConfig()?->databaseName;
          case ConfigConstants::APP_DB_SERVER:
            return $this->databaseProvider->getDatabaseConfig()?->databaseServer;
          case ConfigConstants::APP_DB_USER:
            return $this->databaseProvider->getDatabaseConfig()?->databaseUser;
          case ConfigConstants::APP_DB_PASSWORD:
            return $this->databaseProvider->getDatabaseConfig()?->databasePassword;
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
      function(?string $uid = null) {
        if ($uid === null) {
          $uid = $this->getUser()->getUID();
        }
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
   * @return IEventDispatcher
   */
  public function getEventDispatcher(): IEventDispatcher
  {
    $className = IEventDispatcher::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $originalDispatcher = self::$originalInstances[$className] ?? null;
    $instance = $this->getMockBuilder(IEventDispatcher::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance->method('dispatchTyped')->willReturnCallback(
      function(mixed $event) use ($originalDispatcher) {
        switch (get_class($event)) {
          case CalendarObjectCreatedEvent::class:
          case CalendarObjectDeletedEvent::class:
          case CalendarObjectUpdatedEvent::class:
            if ($originalDispatcher) {
              $originalDispatcher->dispatchTyped($event);
            }
            break;
        }
      },
    );

    $this->instances[$className] = $instance;

    $instance->expects($this->never())->method('removeListener');

    return $instance;
  }

  /** @return ToolTipsService */
  public function getToolTipsService(): ToolTipsService
  {
    $className = ToolTipsService::class;

    if ($this->instances[$className] ?? null) {
      return $this->instances[$className];
    }

    $instance = new ToolTipsService(
      appContainer: $this->getAppContainer(),
      l: $this->getL10N(),
      logger: $this->getLoggerInterface(),
    );

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

  /** @return array */
  protected static function getMockedServices(): array
  {
    return array_merge(
      parent::getMockedServices(),
      [
        BankAccountValidator::class => fn(self $self) => $self->getBankAccountValidator(),
        ConfigService::class => fn(self $self) => $self->getConfigService(),
        Connection::class => fn(self $self) => $self->getEntityManager()->getConnection(),
        EncryptionService::class => fn(self $self) => $self->getEncryptionService(),
        EntityManager::class => fn(self $self) => $self->getEntityManager(),
        IConfig::class => fn(self $self) => $self->getCloudConfig(),
        IEventDispatcher::class => fn(self $self) => $self->getEventDispatcher(),
        Registration::USER_LOCALE => fn(self $self) => 'de_DE.UTF-8',
        RepositoryFactory::class => fn(self $self) => $self->getRepositoryFactory(),
        ToolTipsService::class => fn(self $self) => $self->getToolTipsService(),
        lcfirst(Registration::USER_LOCALE) => fn(self $self) => 'de_DE.UTF-8',
        UndoableRunQueue::class => fn(self $self) => new UndoableRunQueue(
          $self->getAppContainer(),
          $self->getLoggerInterface(),
          $self->getL10N(),
        ),
      ],
    );
  }
}
