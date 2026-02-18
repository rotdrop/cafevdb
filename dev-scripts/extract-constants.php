<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023-2026 Claus-Justus Heine
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

$appDir = realpath(__DIR__) . '/..';
define('ROT_DROP_DEV_SCRIPTS_APP_DIR', $appDir);

require_once(__DIR__ . '/lib/scripts/console-setup.php');

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor-wrapped/autoload.php');

$reflectionClass = new ReflectionClass($argv[1]);

if (isset($argv[2])) {
  echo $reflectionClass->getConstant($argv[2]);
} else {
  echo json_encode($reflectionClass->getConstants());
}
