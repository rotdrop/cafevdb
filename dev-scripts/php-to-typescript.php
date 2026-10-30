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

$phpToTypeScript = new PhpToTypeScript\PhpToTypeScript(
  collectors: [
    DTOCollector::class,
    EnumCollector::class,
    DatabaseEntityCollector::class,
  ],
  metaDataForwarderFolder: 'Wrapped/OCA/RotDrop/Toolkit/Doctrine/ORM',
  devScriptsFolder: __DIR__,
  excludes: $excludes,
  scopedNamespaces: $scopedNamespaces,
);

try {
  $phpToTypeScript->run(
    input: new \Symfony\Component\Console\Input\ArgvInput,
    output: \OCP\Server::get(ConsoleOutput::class),
  );
} catch (Throwable $t) {
  fwrite(STDERR, 'Dependency injection not set up: ' . $t->getMessage() . PHP_EOL . print_r($t->getTrace(), true) . PHP_EOL);
  exit(1);
}
