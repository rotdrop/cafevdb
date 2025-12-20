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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

use Carbon\CarbonImmutable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

/** TestClass in order to access the trait methods. */
class TestClass
{
  use DateTimeTrait {
    DateTimeTrait::ensureDate as public;
    DateTimeTrait::convertToDateTime as public;
    DateTimeTrait::convertToTimezoneDate as public;
  }
}

/** Test the DateTimeTrait which manufactures dates from any arguments. */
#[Attributes\CoversTrait(DateTimeTrait::class)]
class DateTimeTraitTest extends TestCase
{
  /** {@inheritdoc} */
  public function setup(): void
  {
  }

  /** {@inheritdoc} */
  public function testEnsureDate(): void
  {
    $this->assertEquals(new DateTimeImmutable('@1'), TestClass::ensureDate(null));
    $date = new DateTime;
    $this->assertEquals($date, TestClass::ensureDate($date));
  }

  /** {@inheritdoc} */
  public function testConvertToDateTime(): void
  {
    $this->assertEquals(null, TestClass::convertToDateTime(null));
    $this->assertEquals(new DateTimeImmutable('@17'), TestClass::convertToDateTime(17));
    $this->assertEquals(new DateTimeImmutable('@17'), TestClass::convertToDateTime('17'));
    $date = new DateTime;
    $this->assertEquals($date, TestClass::convertToDateTime($date));
    try {
      $result = TestClass::convertToDateTime(0);
      $this->assertEquals(true, false, 'InvalidArgumentException not thrown');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
    }
  }

  private const TIMEZONE_DATES = [
    'null' => '{
    "date": "2024-01-01 00:00:00.000000",
    "timezone_type": 3,
    "timezone": "Europe\/Berlin"
}',
    'Europe/Berlin' => '{
    "date": "2024-01-01 00:00:00.000000",
    "timezone_type": 3,
    "timezone": "Europe\/Berlin"
}',
    'UTC' => '{
    "date": "2024-01-01 00:00:00.000000",
    "timezone_type": 3,
    "timezone": "UTC"
}',
  ];

  /** {@inheritdoc} */
  public function testConvertToTimezoneDate(): void
  {
    $timeZone = new DateTimeZone('Europe/Berlin');
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', '2024-01-01 01:47:13', $timeZone);
    foreach (self::TIMEZONE_DATES as $tz => $output) {
      $timeZone = $tz === 'null' ? null : new DateTimeZone($tz);
      $date = TestClass::convertToTimezoneDate($dateTime, $timeZone);
      // echo json_encode($date, JSON_PRETTY_PRINT) . PHP_EOL;
      $this->assertEquals($output, json_encode($date, JSON_PRETTY_PRINT));
    }
  }
}
