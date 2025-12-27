#!/usr/bin/env php
<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

/*-****************************************************************************
 *
 * Inject NC app setup
 *
 */

require_once(__DIR__ . '/console-setup.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor-wrapped/autoload.php');
require_once(__DIR__ . '/../vendor-bin/typescript-transformer/vendor/autoload.php');

use OCA\CAFEVDB\Wrapped;
use OCA\CAFEVDB\DevScripts\PhpToTypeScript;

use Spatie\TypeScriptTransformer\Transformers;

// store output of different transformers in different files

$outputPrefix = __DIR__ . '/../build/ts-types/php-';
$outputSuffix = '.d.ts';
$sourcePrefix = __DIR__ . '/../';

$outputFiles = [
  'types' => [
    'transformers' => [
      Transformers\EnumTransformer::class,
      PhpToTypeScript\ClassConstantsTransformer::class,
      Transformers\DtoTransformer::class,
    ],
    'paths' => [
      'lib',
    ],
  ],
];

$excludes = [
  'lib/Database/Doctrine/ORM/Proxies',
];

$phpToTypeScript = new PhpToTypeScript\PhpToTypeScript(
  configInfo: $outputFiles,
  excludes: $excludes,
);

$phpToTypeScript->run(
  new \Symfony\Component\Console\Input\ArgvInput,
  new \Symfony\Component\Console\Output\ConsoleOutput,
);
