#!/usr/bin/env php
<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

// phpcs:disable PSR1.Files.SideEffects

ini_set('display_errors', 'stderr');

$appDir = realpath(__DIR__) . '/..';
define('ROT_DROP_DEV_SCRIPTS_APP_DIR', $appDir);

try {
  require_once(__DIR__ . '/lib/scripts/console-setup.php');
  require_once($appDir . '/vendor/autoload.php');
  require_once($appDir . '/vendor-wrapped/autoload.php');
  $autoloader = require_once($appDir . '/vendor-bin/php-to-typescript/vendor/autoload.php');
} catch (\Throwable $t) {
  fwrite(STDERR, 'Composer autoloads not set up: ' . $t->getMessage() . PHP_EOL);
  exit(1);
}

use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Console\ConsoleOutput;
use OCA\RotDrop\DevScripts\PhpToTypeScript;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\DTO\AbstractDTO;
use Spatie\TypeScriptTransformer\Collectors\EnumCollector;
use Spatie\TypeScriptTransformer\Transformers\DtoTransformer;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;
use OCA\RotDrop\DevScripts\PhpToTypeScript\Collectors\DatabaseEntityCollector;
use OCA\RotDrop\DevScripts\PhpToTypeScript\Collectors\DTOCollector;
use OCA\CAFEVDB\AppInfo\Application;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable\Encryption;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable\Transformer\TransformerInterface;

// store output of different transformers in different files

$excludes = [
  'vendor-wrapped/rotdrop/nextcloud-app-toolkit/src/Service/ArchiveService.php',
  'vendor-wrapped/rotdrop/nextcloud-app-toolkit/src/Backend/ArchiveBackend.php',
  'vendor-wrapped/rotdrop/nextcloud-app-toolkit/src/Backend/ArchiveFormats.php',
];

$scopedNamespaces = [
  \Doctrine::class,
  \Carbon::class,
  \Ramsey\Uuid::class,
];

// These overrides belong only to this metadata-generation process, never to
// the runtime container. No user keys, credentials or application SQL are needed.
$metadataConnection = null;
$createEntityManager = function() use (&$metadataConnection): object {
  \OCP\Server::get(Application::class);
  $appContainer = \OC::$server->getRegisteredAppContainer(Application::getAppName());
  $appContainer->registerParameter('databaseServerVersion', '11.7.0-MariaDB');
  $appContainer->registerService(EncryptionService::class, fn() => new class extends EncryptionService {
    /** {@inheritdoc} */
    public function __construct()
    {
    }

    /** {@inheritdoc} */
    public function bound(): bool
    {
      return true;
    }

    /** {@inheritdoc} */
    public function getConfigValue(string $key, mixed $default = null, bool $ignoreLock = false)
    {
      return $default;
    }

    /** {@inheritdoc} */
    public function getUserValue(string|EnumPersonalSettingsKey $key, mixed $default = null, ?string $userId = null): mixed
    {
      return $default;
    }
  });
  $appContainer->registerService(Encryption::class, fn() => new class implements TransformerInterface {
    /** {@inheritdoc} */
    public function transform(?string $value, mixed &$context = null): mixed
    {
      throw new LogicException('Encryption is unavailable during metadata generation.');
    }

    /** {@inheritdoc} */
    public function reverseTransform(?string $value, mixed &$context = null): mixed
    {
      throw new LogicException('Decryption is unavailable during metadata generation.');
    }

    /** {@inheritdoc} */
    public function isCachable(): bool
    {
      return false;
    }
  });
  // EntityManagerBoundEvent subscribers may initialize repositories or storage.
  // Keep their application-state work out of the metadata-only process.
  $appContainer->registerService(\OCP\EventDispatcher\IEventDispatcher::class, fn($container) => new \OC\EventDispatcher\EventDispatcher(
    new \Symfony\Component\EventDispatcher\EventDispatcher,
    $container,
    $container->get(\Psr\Log\LoggerInterface::class),
  ));

  $entityManager = $appContainer->get(EntityManager::class);
  $entityManager->decorateClassMetadata(false);
  $metadataConnection = $entityManager->getConnection();

  return $entityManager;
};

$collectors = [
  DTOCollector::class,
  EnumCollector::class,
  DatabaseEntityCollector::class,
];

$phpToTypeScript = new PhpToTypeScript\PhpToTypeScript(
  collectors: $collectors,
  devScriptsFolder: __DIR__,
  entityManagerFactory: $createEntityManager,
  excludes: $excludes,
  metaDataForwarderFolder: 'Wrapped/OCA/RotDrop/Toolkit/Doctrine/ORM',
  scopedNamespaces: $scopedNamespaces,
);

try {
  $phpToTypeScript->run(
    input: new \Symfony\Component\Console\Input\ArgvInput,
    output: \OCP\Server::get(ConsoleOutput::class),
  );
  if ($metadataConnection?->isConnected()) {
    throw new LogicException('Metadata generation opened the application database connection.');
  }
} catch (Throwable $t) {
  fwrite(STDERR, 'Dependency injection not set up: ' . $t->getMessage() . PHP_EOL . print_r($t->getTrace(), true) . PHP_EOL);
  exit(1);
}
