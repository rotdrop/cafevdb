#!/usr/bin/env php
<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025, 2026 Claus-Justus Heine
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

/*-****************************************************************************
 *
 * Inject NC app setup
 *
 */

require_once(__DIR__ . '/console-setup.php');

/**
 * Get a password from the shell.
 *
 * This function works on *nix systems only and requires shell_exec and stty.
 *
 * @param null|string $prompt
 *
 * @param boolean $stars Wether or not to output stars for given characters.
 *
 * @return string
 */
function getPassword(?string $prompt = null, bool $stars = false)
{
  if (!empty($prompt)) {
    echo $prompt.": ";
  }
  // Get current style
  $oldStyle = shell_exec('stty -g');

  if ($stars === false) {
    shell_exec('stty -echo');
    $password = rtrim(fgets(STDIN), "\n");
  } else {
    shell_exec('stty -icanon -echo min 1 time 0');

    $password = '';
    while (true) {
      $char = fgetc(STDIN);

      if ($char === "\n") {
        break;
      } elseif (ord($char) === 127) {
        if (strlen($password) > 0) {
          fwrite(STDOUT, "\x08 \x08");
          $password = substr($password, 0, -1);
        }
      } else {
        fwrite(STDOUT, "*");
        $password .= $char;
      }
    }
  }

  // Reset old style
  shell_exec('stty ' . $oldStyle);
  echo "\n";

  // Return the password
  return $password;
}

$appDir = __DIR__ . '/..';

if (getenv('CAFEVDB_USER') !== false) {
  $cafevDbUser = getenv('CAFEVDB_USER');
} else {
  $cafevDbUser = $user['name'];
}

$authenticated = false;
$passwordMethod = 'file';

$options = getopt('hp::u:', [ 'help', 'password::', 'user:' ]);

// first run over options to get the password
foreach ($options as $option => $value) {
  switch ($option) {
    case 'p':
    case 'password':
      $passwordMethod = $value ?: 'console';
      break;
    case 'u':
    case 'user':
      $cafevDbUser = $value;
      break;
  }
}

foreach ($argv as $key => $value) {
  if (strpos($value, '-p') === 0
      || strpos($value, '--pass') === 0
      || strpos($value, '-u') === 0
      || strpos($value, '--user') === 0) {
    unset($argv[$key]);
    unset($_SERVER['argv'][$key]);
  }
}

switch ($passwordMethod) {
  case 'file':
    $cafevDbPassword = file_get_contents($appDir . '/.clipassword');
    break;
  case 'stdin':
    $cafevDbPassword = trim(fgets(fopen('php://stdin', 'r')));
    break;
  case 'console':
    $cafevDbPassword = getPassword("Password for " . $cafevDbUser, true);
    break;
}

if (empty($cafevDbPassword)) {
  echo "Unable to obtain database credentials with method $passwordMethod." . PHP_EOL;
  exit(1);
}

$userManager = \OC::$server->get(\OCP\IUserManager::class);
$userSession = \OC::$server->get(\OCP\IUserSession::class);

$user = $userManager->get($cafevDbUser);
$userSession->setUser($user);

use OCA\CAFEVDB\Service\EncryptionService;

$encryptionService = \OC::$server->get(EncryptionService::class);
$encryptionService->bind($cafevDbUser, $cafevDbPassword);

/*
 *
 *
 *
 *****************************************************************************/

use Composer\InstalledVersions;
use OCA\CAFEVDB\Wrapped\Composer\InstalledVersions as WrappedInstalledVersions;

$installedVersions = [
  WrappedInstalledVersions::class => __DIR__ . "/../vendor-wrapped/composer/InstalledVersions.php",
  InstalledVersions::class => __DIR__ . "/../vendor/composer/InstalledVersions.php",
];
foreach ($installedVersions as $class => $file) {
  if (!class_exists($class, false) && file_exists($file)) {
    include_once $file;
  }
}

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../vendor-wrapped/autoload.php";

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Console\ConsoleRunner;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use OCA\CAFEVDB\Database\EntityManager;

/** @var EntityManager */
$entityManager = \OC::$server->query(EntityManager::class);
$entityManager->decorateClassMetadata(false);

$cli = ConsoleRunner::createApplication(new SingleManagerProvider($entityManager));

use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Tools\Console\ConsoleRunner as MigrationsConsoleRunner;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\JsonFile;

$configurationLoader = new JsonFile(__DIR__ . '/../appinfo/migrations.json');
$dependencyFactory = DependencyFactory::fromEntityManager(
  $configurationLoader,
  new ExistingEntityManager($entityManager),
);
MigrationsConsoleRunner::addCommands($cli, $dependencyFactory);

$cli->run();
