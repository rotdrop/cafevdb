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
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Service\ConfigService;

/** Test aspects of the config-service .*/
#[Attributes\CoversClass(ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
class ConfigServiceTest extends TestCase
{
  private ConfigService $configService;

  /** @return void */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $this->configService = $mockProvider->getConfigService();
  }

  /** @return void */
  public function testGetLocale(): void
  {
    $this->assertEquals('de_DE', $this->configService->getLocale());
    $this->assertEquals('de_DE.UTF-8', $this->configService->getLocale(addEncoding: true));
    $this->assertEquals('en_GB', $this->configService->getLocale(lang: 'en_GB'));
    $this->assertEquals('en_GB.UTF-8', $this->configService->getLocale(lang: 'en_GB', addEncoding: true));
  }

  /** @return void */
  public function testGetAppLocale(): void
  {
    $this->assertEquals('de_DE.UTF-8', $this->configService->getAppLocale());
  }
}
