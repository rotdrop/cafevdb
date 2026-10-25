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

/*
 * Dispatch between the two test modes:
 *
 * - Nextcloud mode: the app is installed inside a Nextcloud server tree,
 *   the server's test bootstrap is available.
 * - Standalone mode: only the composer dependencies (including the
 *   nextcloud/ocp stubs) are available. Tests tagged with the PHPUnit group
 *   "nextcloud" must be excluded, see "make phpunit-standalone".
 *
 * Standalone mode is selected explicitly with PHPUNIT_STANDALONE=1 or
 * implicitly if no Nextcloud server tree is found.
 */
$serverBootstrap = __DIR__ . '/../../../../tests/bootstrap.php';
$standalone = filter_var(getenv('PHPUNIT_STANDALONE'), FILTER_VALIDATE_BOOLEAN)
  || !file_exists($serverBootstrap);
define('PHPUNIT_STANDALONE', $standalone);

require_once $standalone ? __DIR__ . '/bootstrap-standalone.php' : __DIR__ . '/bootstrap-nextcloud.php';
