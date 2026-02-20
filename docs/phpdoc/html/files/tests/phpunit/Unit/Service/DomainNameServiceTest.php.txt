<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use DateTimeImmutable;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Service\DomainNameService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the DomainNameService class.  */
#[Attributes\CoversClass(DomainNameService::class)]
class DomainNameServiceTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Storage\GetAppStorageTrait;

  private DomainNameService $service;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->service = new DomainNameService;
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testIsIpv4(): void
  {
    $this->assertFalse($this->service->isIpv4('hallo'));
    $this->assertTrue($this->service->isIpv4('123.123.123.123'));
    $this->assertFalse($this->service->isIpv4('::1'));
  }

  /** @return void */
  public function testIsIpv6(): void
  {
    $this->assertFalse($this->service->isIpv6('hallo'));
    $this->assertFalse($this->service->isIpv6('123.123.123.123'));
    $this->assertTrue($this->service->isIpv6('::1'));
  }

  private const LOCALHOST = [
    DomainNameService::IN_A => ['127.0.0.1'],
    DomainNameService::IN_AAAA => ['::1'],
  ];

  private const EMPTY = [
    DomainNameService::IN_A => [],
    DomainNameService::IN_AAAA => [],
  ];

  /** @return void */
  public function testResolveLocalhost(): void
  {
    $result = $this->service->resolveHostname('localhost');
    $this->assertEquals(self::LOCALHOST, $result);
  }

  /** @return void */
  public function testResolveUnresolvable(): void
  {
    $result = $this->service->resolveHostname('i.am.not.there');
    $this->assertEquals(self::EMPTY, $result);
  }
}
