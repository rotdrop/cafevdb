<?php
/**
 * Some PHP classes shared between my Nextcloud apps.
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

namespace OCA\RotDrop\Tests;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use Pimple\Container as InnerContainer;
use OC\AppFramework\Utility\SimpleContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

use OCA\RotDrop\Tests\Logger;
use OCA\RotDrop\Tests\DatabaseProvider;

/** Provide a couple of important services, partially using mocked classes. */
abstract class AbstractMockProvider
{
  public const CLOUD_USER_UID = 'john.doe';

  public readonly string $appName;

  private ReflectionMethod $getMockBuilderMethod;

  private ReflectionMethod $createStubMethod;

  private static IAppContainer $appContainer;

  private static array $mockedServices;

  private static array $originalInstances = [];

  private static InnerContainer $serverContainerSnapshot;

  private static array $appContainerSnapshots = [];

  /** {@inheritdoc} */
  private function __construct(
    protected App $app,
    protected DatabaseProvider $databaseProvider,
    protected Logger $logger,
    protected TestCase $testCase,
  ) {
    $this->appName = $app->get('appName');

    $this->getMockBuilderMethod = new ReflectionMethod($this->testCase, 'getMockBuilder');
    $this->createStubMethod = new ReflectionMethod($this->testCase, 'createStub');

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
    return new static(
      app: \OCP\Server::get(\PHPUNIT_NC_APP_NAMESPACE . '\\AppInfo\\Application'),
      databaseProvider: \OCP\Server::get(DatabaseProvider::class),
      logger: \OCP\Server::get(Logger::class),
      testCase: $testCase,
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
    $snapshot->offsetUnset(\OC\URLGenerator::class);
    // print_r($snapshot->keys());
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

  /**
   * Register a service globally.
   *
   * @param string $service Service or class-name.
   *
   * @return void
   */
  private function registerService(string $service): void
  {
    $otherAppContainer = null;
    if (str_starts_with($service, \OCA::class) && !str_starts_with($service, \PHPUNIT_NC_APP_NAMESPACE)) {
      $appContainers = new ReflectionProperty(\OC\ServerContainer::class, 'appContainers')->getValue(\OC::$server);
      [, $app] = explode('\\', $service, 3);
      $app = strtolower($app);
      $otherAppContainer = $appContainers[$app] ?? null;
    }
    $appContainer = self::$appContainer;
    $mockContainer = $this->getAppContainer();
    if (str_starts_with($service, \PHPUNIT_NC_APP_NAMESPACE)) {
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
      if ($otherAppContainer) {
        $otherAppContainer->registerService($service, $factory);
      }
    }
  }

  /** @return void */
  private function registerServices(): void
  {
    self::$mockedServices = self::$mockedServices ?? static::getMockedServices();
    self::$appContainer = $this->app->get(IAppContainer::class);
    self::$appContainer->registerService(LoggerInterface::class, fn() => $this->logger);
    \OC::$server->registerService(LoggerInterface::class, fn() => $this->logger);
    $appContainers = new ReflectionProperty(\OC\ServerContainer::class, 'appContainers')->getValue(\OC::$server);
    if (empty(self::$serverContainerSnapshot)) {
      self::$serverContainerSnapshot = self::snapshotContainer(\OC::$server);
      foreach ($appContainers as $app => $container) {
        self::$appContainerSnapshots[$app] = self::snapshotContainer($container);
      }
    } else {
      self::restoreContainer(\OC::$server, self::$serverContainerSnapshot);
      foreach (self::$appContainerSnapshots as $app => $container) {
        self::restoreContainer($appContainers[$app], self::$appContainerSnapshots[$app]);
      }
    }
    foreach (array_keys(self::$mockedServices) as $service) {
      $this->registerService($service);
    }
    // echo get_class($appContainer->get(LoggerInterface::class)) . PHP_EOL;
    // echo get_class(\OC::$server->get(LoggerInterface::class)) . PHP_EOL;
  }

  /**
   * @param string $className The name of the class to mock.
   *
   * @return MockBuilder An instance tied to $this->testCase.
   */
  protected function getMockBuilder(string $className): MockBuilder
  {
    return $this->getMockBuilderMethod->invoke($this->testCase, $className);
  }

  /**
   * @param string $className The name of the class to create a stub for.
   *
   * @return Stub A stub instance tied to $this->testCase.
   */
  protected function createStub(string $className): Stub
  {
    return $this->createStubMethod->invoke($this->testCase, $className);
  }

  /** {@inheritdoc} */
  protected function never(): mixed
  {
    $method = new ReflectionMethod($this->testCase, 'never');
    return $method->invoke($this->testCase);
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
          return self::CLOUD_USER_UID;
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

    $instance->method('getUID')->willReturn(self::CLOUD_USER_UID);

    $this->instances[$className] = $instance;

    // $instance->expects($this->never())->method('getFirstLogin');

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

    // $instance->expects($this->never())->method('setVolatileActiveUser');

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

    // $instance->expects($this->never())->method('getEnv');

    return $instance;
  }

  /**
   * @return LoggerInterface
   */
  public function getLoggerInterface(): LoggerInterface
  {
    return $this->logger;
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

  /** @return array */
  protected static function getMockedServices(): array
  {
    return [
      'userId' => fn(self $self) => $self->getUserSession()->getUser()->getUID(),
      IL10N::class => fn(self $self) => $self->getL10N(),
      IRequest::class => fn(self $self) => $self->getRequest(),
      ISession::class => fn(self $self) => $self->getSession(),
      IUserSession::class => fn(self $self) => $self->getUserSession(),
      LoggerInterface::class => fn(self $self) => $self->getLoggerInterface(),
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

    $instance = $this->createStub(IAppContainer::class);

    $instance->method('get')->willReturnCallback(
      function(string $service) {
        if (!empty($this->instances[$service])) {
          return $this->instances[$service];
        }
        if (!empty(self::$mockedServices[$service])) {
          return self::$mockedServices[$service]($this);
        }
        // try to generate "the real thing"
        $newInstance = $this->app->getContainer()->get($service);
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
          $instance = $this->app->getContainer()->resolve($service);
        }
        return $instance;
      }
    );

    // $instance->expects($this->never())->method('registerMiddleWare');

    $this->instances[$className] = $instance;

    return $instance;
  }

  private static array $appRoutes;

  /**
   * Return the array of ROUTE_NAME => \Symfony\Component\Routing\Route of
   * defined routes for this app.
   *
   * @return array<\Symfony\Component\Routing\Route>
   */
  public function getAppRoutes(): array
  {
    if (self::$appRoutes ?? null) {
      return self::$appRoutes;
    }
    $urlGenerator = $this->getAppContainer()->get(\OCP\IURLGenerator::class);
    $routerProperty = new ReflectionProperty(\OC\URLGenerator::class, 'router');
    /** @var \OC\URLGenerator::class $router */
    $router = $routerProperty->getValue($urlGenerator);
    $router->loadRoutes($this->appName);
    $getCollection = new ReflectionMethod($router, 'getCollection');
    // The router does define collections for each app but does not use them it seems.
    // $collectionName = $this->appName
    $collectionName = 'root';
    /** @var Symfony\Component\Routing\RouteCollection $rootCollection */
    $rootCollection = $getCollection->invoke($router, $collectionName);
    // echo '#ROUTES ' . count($rootCollection->all()) . PHP_EOL;
    /** @var Symfony\Component\Routing\Route $route */
    self::$appRoutes = array_filter(
      $rootCollection->all(),
      fn(string $key) => str_starts_with($key, $this->appName . '.') || str_starts_with($key, 'ocs.' . $this->appName . '.'),
      ARRAY_FILTER_USE_KEY,
    );
    return self::$appRoutes;
  }
}
