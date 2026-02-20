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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Service;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit\Service\AppInfoService;
use OCA\CAFEVDB\Toolkit\Traits\Constants;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(AppInfoService::class)]
class AppInfoServiceTest extends TestCase
{
  /** @return void */
  public function testGetAppInfoAppName(): void
  {
    $this->assertNotNull(AppInfoService::getAppInfoAppName(__DIR__));
  }

  /** @return void */
  public function testGetAppInfoPath(): void
  {
    $this->assertStringEndsWith(Constants::INFO_FILE, AppInfoService::getAppInfoPath(__DIR__));
  }

  /** @return void */
  public function testGetAppInfo(): void
  {
    $appName = AppInfoService::getAppInfoAppName(__DIR__);
    $service = \OCP\Server::get(AppInfoService::class);
    $info = $service->getAppInfo();
    $this->assertEquals($appName, $info['id']);
  }
}
