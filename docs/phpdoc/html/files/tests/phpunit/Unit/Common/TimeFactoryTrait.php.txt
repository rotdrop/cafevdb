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
 */

namespace OCA\CAFEVDB\Tests\Unit\Common;

use DateTimeImmutable;
use DateTimeInterface;

use OCP\AppFramework\Utility\ITimeFactory;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Tests\MockProvider;

/** Helper trait to generate a mocked TimeFactory. */
trait TimeFactoryTrait
{
  private MockProvider $mockProvider;

  protected DateTimeInterface $now;

  protected TimeFactory $timeFactory;

  /**
   * @param ?DateTimeInterface $now
   *
   * @return void
   */
  protected function mockTimeFactory(?DateTimeInterface $now = null): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->now = $now ?? DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', '2099-01-01 23:47:15.123456');

    /** @var TimeFactory $timeFactory */
    $this->timeFactory = $this->getMockBuilder(TimeFactory::class)->getMock();
    $this->timeFactory->method('now')->willReturnCallback(fn() => $this->now);
    $this->timeFactory->method('sleepUntil')->willReturnCallback(function (float $timestamp) {
      $diff = $timestamp - $this->now->getTimeStamp();
      if ($diff > 0) {
        return time_sleep_until(time() + $diff);
      }
      return false;
    });
    $this->timeFactory->expects($this->never())->method('withTimeZone');
    $this->mockProvider->registerClassInstance(TimeFactory::class, $this->timeFactory, global: true);
    $this->mockProvider->registerClassInstance(ITimeFactory::class, $this->timeFactory, global: true);
  }
}
