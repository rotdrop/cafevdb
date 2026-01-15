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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait;
use OCA\CAFEVDB\Wrapped\Carbon\Carbon as WrappedCarbon;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as WrappedCarbonImmutable;

/** TestClass in order to access the trait methods. */
class TestClass
{
  use DateTimeTrait {
    DateTimeTrait::convertToDateTime as public;
  }
}

/** Test the DateTimeTrait which manufactures dates from any arguments. */
#[Attributes\CoversTrait(DateTimeTrait::class)]
class DateTimeTraitTest extends TestCase
{
  private const DATE_TIME_CLASSES = [
    DateTime::class => false,
    DateTimeImmutable::class => false,
    Carbon::class => false,
    CarbonImmutable::class => false,
    WrappedCarbon::class => false,
    WrappedCarbonImmutable::class => true,
  ];

  /** {@inheritdoc} */
  public function testConvertToDateTime(): void
  {
    $this->assertTrue(null === TestClass::convertToDateTime(null));
    $this->assertEquals(new DateTimeImmutable('@17'), TestClass::convertToDateTime(17));
    $this->assertEquals(new DateTimeImmutable('@17'), TestClass::convertToDateTime('17'));

    foreach (self::DATE_TIME_CLASSES as $class => $pass) {
      $date = new $class;
      $result = TestClass::convertToDateTime($date);
      $this->assertTrue(($date === $result) === $pass);
      $this->assertInstanceOf($pass ? $class : DateTimeImmutable::class, $result);
    }
    try {
      TestClass::convertToDateTime(0);
      $this->assertEquals(true, false, 'InvalidArgumentException not thrown');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
    }
  }
}
