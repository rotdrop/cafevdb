#!/usr/bin/env php
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
 *
 * The purpose of this file is to generate dummy DTOs with the structure
 * dictated by the PHP implementation. The TypeScript unit tests can then
 * check work on semi-real data.
 */

$appDir = __DIR__ . '/../../../../';

require_once($appDir . 'vendor-bin/phpunit/vendor/autoload.php');
require_once($appDir . 'tests/phpunit/bootstrap.php');

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Tests\Unit\Controller\EntityRepositoryControllerTest;

if (count($argv) != 5) {
  throw new InvalidArgumentException("Usage: {$argv[0]} DTO_BASENAME OUTPUT_DIR OUTPUT_FILE DEPTH");
}

$entityName = $argv[1];
$outputDir = rtrim($argv[2], '/');
$outputFile = rtrim($argv[3], '/');
$depth = $argv[4];

switch ($entityName) {
  case 'Project':
    // $entityName = Entities\Project::class;
    $find = base64_encode(json_encode([ 'id' => 1 ]));
    break;
  case 'Musician':
    // $entityName = Entities\Musician::class;
    $find = base64_encode(json_encode([ 'id' => 1 ]));
    break;
  case 'ProjectParticipant':
    // $entityName = Entities\ProjectParticipant::class;
    $find = base64_encode(json_encode([ 'project' => 1, 'musician' => 1 ]));
    break;
  default:
    throw new InvalidArgumentException("Unsupported entity: {$entityName}");
}

$phpUnitTest = new EntityRepositoryControllerTest('Repository Controller Test');
$callGetEntities = new ReflectionMethod($phpUnitTest, 'callGetEntities');
$phpUnitTest->setup();
$args = [
  'entityName' => $entityName,
  'find' => $find,
  'findBy' => null,
  'limit' => null,
  'offset' => 0,
  'depth' => $depth,
];
$dto = $callGetEntities->invokeArgs($phpUnitTest, $args);

if (!file_exists($outputDir)) {
  mkdir($outputDir, 0700, recursive: true);
}
file_put_contents($outputDir . '/' . $outputFile, json_encode($dto, JSON_PRETTY_PRINT) . PHP_EOL);
