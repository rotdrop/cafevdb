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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

$topDir = realpath(__DIR__ . '/..') . '/';
$topDirLen = strlen($topDir);
$prefix = $topDir . 'lib/';
$prefixLen = strlen($prefix);
$namespace = 'OCA\\' . (string)(new SimpleXMLElement(file_get_contents($topDir . 'appinfo/info.xml'))->namespace);

$finder = Finder::create()
  ->in($prefix)
  ->name(['*.php'])
  ->sortByName();

/** @var SplFileInfo $fileInfo */
foreach ($finder as $path => $fileInfo) {
  $content = file_get_contents($path);
  if (!preg_match('/(^|\n)enum\\s+\\w+/', $content)) {
    continue;
  }
  $enumFile = substr($path, $prefixLen, -4);
  $enum = $namespace . '\\' . str_replace('/', '\\', $enumFile);
  if (class_exists($enum, true) && method_exists($enum, 'getL10NValues')) {
    $reflection = new ReflectionEnum($enum);
    $startLine = $reflection->getStartLine();
    // echo 'TRANSLATABLE ENUM ' . $enum . ' ' . $enum::L10N_TAG . PHP_EOL;
    $shortFile = substr($path, $topDirLen);
    $tag = $enum::l10nTag();
    foreach ($enum::toArray() as $key => $value) {
      $reflectionCase = new ReflectionEnumBackedCase($enum, $key);
      $docComment = $reflectionCase->getDocComment();
      // echo "{$key} => {$value}" . PHP_EOL;
      $poEntry =<<<EOF
#. TRANSLATORS: An enum value of "enum $enum".
#. TRANSLATORS: $key => $value

EOF;
      if (!empty($docComment)) {
        $poEntry .= implode(
          '',
          array_map(
            fn(string $line) => '#. TRANSLATORS: ' . preg_replace('/^[\/* ]+/', '', $line) . "\n",
            explode("\n", $docComment),
          ),
        );
      }
      $poEntry .=<<<EOF
#: $shortFile:$startLine
#, php-format
msgid "$tag$value"
msgstr ""

EOF;
      echo $poEntry . PHP_EOL;
    }
  }
}
