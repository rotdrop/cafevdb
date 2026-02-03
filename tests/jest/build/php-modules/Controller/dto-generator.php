#!/usr/bin/env php
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
 *
 * The purpose of this file is to generate dummy DTOs with the structure
 * dictated by the PHP implementation. The TypeScript unit tests can then
 * check work on semi-real data.
 */

require_once(__DIR__ . '/../../../../../dev-scripts/lib/scripts/console-setup.php');

use OCA\CAFEVDB\Controller\DTO;

if (count($argv) != 3) {
  throw new InvalidArgumentException("Usage: {$argv[0]} DTO_BASENAME OUTPUT_DIR");
}

$dtoName = $argv[1];
$outputDir = rtrim($argv[2], '/');

switch ($dtoName) {
  case array_pop(explode('\\', DTO\DownloadsShareResponse::class)):
    $dto = new DTO\DownloadsShareResponse(
      messages: ['MESSAGE'],
      url: 'SHARE',
      path: 'FOLDER',
      expires: DateTime::createFromFormat('Y-m-d h:i:s', '2025-11-04 01:02:03'),
    );
    break;
  default:
    throw new InvalidArgumentException("Unsupported DTO: {$dtoName}");
}

mkdir($outputDir, 0700, recursive: true);
file_put_contents($outputDir . '/' . $dtoName . '.json', json_encode($dto, JSON_PRETTY_PRINT) . PHP_EOL);
