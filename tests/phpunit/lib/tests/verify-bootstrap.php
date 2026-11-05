<?php
/**
 * Isolated regression checks; no Nextcloud installation is required.
 *
 * @license AGPL-3.0-or-later
 */

namespace Psr\Log {
  interface LoggerInterface
  {
  }
}

namespace OCP {
  interface ITempManager
  {
    public function cleanOld(): void;
  }

  class Server
  {
    public static function get(string $name): object
    {
      return \OC::$server->get($name);
    }
  }
}

namespace OCA\RotDrop\Tests\Fixtures {
  abstract class AbstractDTO implements \JsonSerializable
  {
    public function jsonSerialize(): array
    {
      return get_object_vars($this);
    }
  }

  trait LoggerTrait
  {
    protected \Psr\Log\LoggerInterface $logger;

    public function logger(): \Psr\Log\LoggerInterface
    {
      return $this->logger;
    }
  }

  class ExecutableFinder
  {
  }

  class Logger implements \Psr\Log\LoggerInterface
  {
  }

  class TempManager implements \OCP\ITempManager
  {
    public bool $cleaned = false;

    public function cleanOld(): void
    {
      $this->cleaned = true;
    }
  }

  class Container
  {
    public array $aliases = [];
    public array $services = [];
    public int $aliasRegistrations = 0;

    public function registerAlias(string $alias, string $target): void
    {
      $this->aliases[$alias] = $target;
      ++$this->aliasRegistrations;
    }

    public function get(string $name): object
    {
      $name = $this->aliases[$name] ?? $name;
      if (!isset($this->services[$name])) {
        $class = new \ReflectionClass($name);
        $arguments = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
          $arguments[] = $this->get($parameter->getType()->getName());
        }
        $this->services[$name] = $class->newInstanceArgs($arguments);
      }
      return $this->services[$name];
    }
  }
}

namespace {
  class OC
  {
    public static \OCA\RotDrop\Tests\Fixtures\Container $server;
  }

  function check(bool $condition, string $message): void
  {
    if (!$condition) {
      throw new \RuntimeException($message);
    }
  }

  $appNamespace = $argv[1] ?? '';
  $firstHelper = $argv[2] ?? 'DatabaseProvider';
  if (!in_array($firstHelper, ['DatabaseProvider', 'DatabaseConfig'], true)) {
    throw new \InvalidArgumentException('Expected DatabaseProvider or DatabaseConfig as the second argument.');
  }
  if ($appNamespace !== 'missing-namespace') {
    define('PHPUNIT_NC_APP_NAMESPACE', $appNamespace);
  }
  define('PHPUNIT_NC_APP_NAME', 'test-app');

  \OC::$server = new \OCA\RotDrop\Tests\Fixtures\Container;
  $toolkitNamespace = $appNamespace . '\\Wrapped\\OCA\\RotDrop\\Toolkit';
  $fixtures = [
    'DTO\\AbstractDTO' => \OCA\RotDrop\Tests\Fixtures\AbstractDTO::class,
    'Traits\\LoggerTrait' => \OCA\RotDrop\Tests\Fixtures\LoggerTrait::class,
    'Service\\ExecutableFinder' => \OCA\RotDrop\Tests\Fixtures\ExecutableFinder::class,
  ];
  spl_autoload_register(static function(string $name) use ($toolkitNamespace, $fixtures, $appNamespace): void {
    foreach ($fixtures as $suffix => $fixture) {
      if ($name === $toolkitNamespace . '\\' . $suffix && $appNamespace !== 'missing-toolkit') {
        class_alias($fixture, $name);
      }
    }
  });

  try {
    require __DIR__ . '/../' . $firstHelper . '.php';
  } catch (\LogicException $exception) {
    if ($appNamespace === 'missing-namespace' || $appNamespace === '') {
      check(str_contains($exception->getMessage(), 'Define PHPUNIT_NC_APP_NAMESPACE'), 'Missing namespace diagnostic.');
    } elseif ($appNamespace === 'missing-toolkit') {
      check(str_contains($exception->getMessage(), 'scoped autoloader'), 'Missing toolkit diagnostic.');
    } else {
      throw $exception;
    }
    check(\OC::$server->aliasRegistrations === 0, 'Invalid setup must not register a finder service.');
    echo $appNamespace . ': expected bootstrap error' . PHP_EOL;
    exit(0);
  }
  check(!in_array($appNamespace, ['missing-namespace', 'missing-toolkit', ''], true), 'Invalid setup must fail.');

  require_once __DIR__ . '/../DatabaseProvider.php';
  require_once __DIR__ . '/../DatabaseConfig.php';
  require_once __DIR__ . '/../EnumDatabasePurpose.php';
  require_once __DIR__ . '/../bootstrap.php';

  $finder = new \OCA\RotDrop\Tests\Fixtures\ExecutableFinder;
  $tempManager = new \OCA\RotDrop\Tests\Fixtures\TempManager;
  $logger = new \OCA\RotDrop\Tests\Fixtures\Logger;
  \OC::$server->services[$toolkitNamespace . '\\Service\\ExecutableFinder'] = $finder;
  \OC::$server->services[\OCP\ITempManager::class] = $tempManager;
  \OC::$server->services[\OCA\RotDrop\Tests\Logger::class] = $logger;

  $provider = \OCP\Server::get(\OCA\RotDrop\Tests\DatabaseProvider::class);
  $finderProperty = new \ReflectionProperty($provider, 'executableFinder');
  check($finderProperty->getValue($provider) === $finder, 'DI must reuse the scoped finder instance.');
  check($provider->logger() === $logger, 'The toolkit logger trait must retain the injected logger.');
  check($tempManager->cleaned, 'Provider construction must clean old temporary files.');
  check(\OC::$server->aliasRegistrations === 1, 'Loading both helpers must bootstrap only once.');
  check($provider->databaseName(\OCA\RotDrop\Tests\EnumDatabasePurpose::APP) === 'test-app', 'Default database name changed.');
  check($provider->getDatabaseConfig() === null, 'Unstarted provider must not have a database config.');

  $config = new \OCA\RotDrop\Tests\DatabaseConfig('database', 'server', 'user', 'password');
  check(is_a($config, $toolkitNamespace . '\\DTO\\AbstractDTO'), 'DTO must extend the active toolkit base.');
  check(json_decode(json_encode($config), true) === [
    'databaseName' => 'database',
    'databaseServer' => 'server',
    'databaseUser' => 'user',
    'databasePassword' => 'password',
  ], 'DTO serialization changed.');
  check($finderProperty->getType()->getName() === \OCA\RotDrop\Tests\Toolkit\Service\ExecutableFinder::class, 'Finder must retain a neutral, concrete type.');
  try {
    new \OCA\RotDrop\Tests\DatabaseProvider($tempManager, new \stdClass);
    throw new \RuntimeException('Finder type safety was lost.');
  } catch (\TypeError) {
  }

  $otherApp = $appNamespace === 'OCA\\CAFEVDB' ? 'OCA\\CAFeVDBMembers' : 'OCA\\CAFEVDB';
  check(!class_exists($otherApp . '\\Wrapped\\OCA\\RotDrop\\Toolkit\\Service\\ExecutableFinder'), 'The other app must remain unavailable.');
  echo $appNamespace . ' (' . $firstHelper . ' first): OK' . PHP_EOL;
}
