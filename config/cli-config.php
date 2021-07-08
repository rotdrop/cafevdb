<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/******************************************************************************
 *
 * Inject NC app setup
 *
 */

$corePrefix = '/../../..';

require_once __DIR__ . $corePrefix . '/lib/versioncheck.php';

use OC\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

define('OC_CONSOLE', 1);

function exceptionHandler($exception) {
    echo "An unhandled exception has been thrown:" . PHP_EOL;
    echo $exception;
    exit(1);
}
try {
    require_once __DIR__ . $corePrefix . '/lib/base.php';

    // set to run indefinitely if needed
    if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
        @set_time_limit(0);
    }

    if (!OC::$CLI) {
        echo "This script can be run from the command line only" . PHP_EOL;
        exit(1);
    }

    set_exception_handler('exceptionHandler');

    if (!function_exists('posix_getuid')) {
        echo "The posix extensions are required - see http://php.net/manual/en/book.posix.php" . PHP_EOL;
        exit(1);
    }
    $user = posix_getpwuid(posix_getuid());
    $configUser = posix_getpwuid(fileowner(OC::$configDir . 'config.php'));
    if ($user['name'] !== $configUser['name']) {
        echo "Console has to be executed with the user that owns the file config/config.php" . PHP_EOL;
        echo "Current user: " . $user['name'] . PHP_EOL;
        echo "Owner of config.php: " . $configUser['name'] . PHP_EOL;
        echo "Try adding 'sudo -u " . $configUser['name'] . " ' to the beginning of the command (without the single quotes)" . PHP_EOL;
        echo "If running with 'docker exec' try adding the option '-u " . $configUser['name'] . "' to the docker command (without the single quotes)" . PHP_EOL;
        exit(1);
    }

    $oldWorkingDir = getcwd();
    if ($oldWorkingDir === false) {
        echo "This script can be run from the CAFeV-DB root directory only." . PHP_EOL;
        echo "Can't determine current working dir - the script will continue to work but be aware of the above fact." . PHP_EOL;
    } elseif ($oldWorkingDir !== __DIR__ && !chdir(__DIR__)) {
        echo "This script can be run from the CAFeV-DB root directory only." . PHP_EOL;
        echo "Can't change to Nextcloud root directory." . PHP_EOL;
        exit(1);
    }

    if (!function_exists('pcntl_signal') && !in_array('--no-warnings', $argv)) {
        echo "The process control (PCNTL) extensions are required in case you want to interrupt long running commands - see http://php.net/manual/en/book.pcntl.php" . PHP_EOL;
    }

    $application = new Application(
        \OC::$server->getConfig(),
        \OC::$server->getEventDispatcher(),
        \OC::$server->getRequest(),
        \OC::$server->get(\Psr\Log\LoggerInterface::class),
        \OC::$server->query(\OC\MemoryInfo::class)
    );
    // $application->loadCommands(new ArgvInput(), new ConsoleOutput());
    // $application->run();
} catch (Exception $ex) {
    exceptionHandler($ex);
} catch (Error $ex) {
    exceptionHandler($ex);
} catch (Throwable $ex) {
    exceptionHandler($ex);
}

/**
 * Get a password from the shell.
 *
 * This function works on *nix systems only and requires shell_exec and stty.
 *
 * @param  boolean $stars Wether or not to output stars for given characters
 * @return string
 */
function getPassword($prompt = null, $stars = false)
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
            } else if (ord($char) === 127) {
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;

$appDir = __DIR__ . '/..';

if (isset($_ENV['CAFEVDB_USER'])) {
    $cafevDbUser = $_ENV['CAFEVDB_USER'];
} else {
    $cafevDbUser = $user['name'];
}
$GLOBALS['cafevdb-user'] = $cafevDbUser;

$authenticated = false;
$passwordMethod = 'file';

$options = getopt('hp::', [ 'help', 'password::' ]);

// first run over options to get the password
foreach($options as $option => $value) {
  switch ($option) {
  case 'p':
  case 'password':
    $passwordMethod = $value ?: 'console';
    break;
  }
}

foreach ($argv as $key => $value) {
  if (strpos($value, '-p') === 0
      || strpos($value, '--pass') === 0)  {
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
  $cafevDbPassword = getPassword("Password for " . $cafevDbUser . ": ", true);
  break;
}

if (empty($cafevDbPassword)) {
  echo "Unable to obtain database credentials with method $passwordMethod." . PHP_EOL;
  exit(1);
}

$encryptionService = \OC::$server->query(EncryptionService::class);
$encryptionService->bind($cafevDbUser, $cafevDbPassword);
$encryptionService->initUserKeyPair();
$encryptionService->initAppEncryptionKey();

/*
 *
 *
 *
 *****************************************************************************/

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../vendor-wrapped/autoload.php";

// otherwise Redaxo4Embedded's InstalledVersions is pulled in by autoload
$installedVersions = __DIR__ . "/../vendor/composer/InstalledVersions.php";
if (file_exists($installedVersions)) {
  require_once $installedVersions;
}

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\Console\ConsoleRunner;
use OCA\CAFEVDB\Database\EntityManager;

/** @var EntityManager */
$entityManager = \OC::$server->query(EntityManager::class);
$entityManager->decorateClassMetadata(false);

return ConsoleRunner::createHelperSet($entityManager);
