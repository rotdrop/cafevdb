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

putenv('TEST_DONT_LOAD_APPS=1');
require_once __DIR__ . '/../../../../tests/bootstrap.php';

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../vendor-wrapped/autoload.php";

define('PHPUNIT_NC_APP_NAME', \OCA\CAFEVDB\AppInfo\Application::getAppName());

$wantedApps = [
  \PHPUNIT_NC_APP_NAME,
  \OCA\CAFEVDB\AppInfo\Application::getMembersAppName(),
  'files',
  'files_sharing',
];

$appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
foreach ($wantedApps as $app) {
  $appManager->loadApp($app);
}

define('PHPUNIT_APPDIR', realpath(\OCA\CAFEVDB\Toolkit\Service\AppInfoService::getAppFolderPath()));
define('PHPUNIT_ARTIFACTS', PHPUNIT_APPDIR . '/build/artifacts/tests/phpunit');

$databaseProvider = \OCP\Server::get(\OCA\RotDrop\Tests\DatabaseProvider::class);

// stop and cleanup potentially running db-servers
register_shutdown_function([$databaseProvider, 'stopServer']);

error_reporting(E_ALL);
