<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Tests;

date_default_timezone_set('UTC');

// The phpunit composer-bin autoloader already provides the app, the
// toolkit, the test helpers and the nextcloud/ocp stubs.
require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../vendor-wrapped/autoload.php";

define('ROT_DROP_PHPUNIT', true);
define('PHPUNIT_NC_APP_NAME', \OCA\CAFEVDB\AppInfo\Application::getAppName());
define('PHPUNIT_NC_APP_NAMESPACE', \OCA\CAFEVDB::class);
define('PHPUNIT_NC_MOCKED_APPS', [\OCA\CAFEVDB::class, \OCA\BAV::class]);
define('PHPUNIT_APPDIR', realpath(__DIR__ . '/../..'));
define('PHPUNIT_ARTIFACTS', PHPUNIT_APPDIR . '/build/artifacts/tests/phpunit');

error_reporting(E_ALL);
ini_set('display_errors', 1);
