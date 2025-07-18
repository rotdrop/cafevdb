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

namespace OCA\CAFEVDB\Tests\Unit\Common;

use OutOfBoundsException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use MathPHP\Number\Rational;
use OCA\CAFEVDB\Common\RationalNumber;

/** Test the RationalNumber class. */
#[CoversClass(RationalNumber::class)]
class RationalNumberTest extends TestCase
{
  /**
   * @{inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    parent::setup();
  }

  const DECIMALS = [
    '.1234' => '⁶¹⁷/₅₀₀₀',
    '-.1234' => '-⁶¹⁷/₅₀₀₀',
    '0.1234' => '⁶¹⁷/₅₀₀₀',
    '-0.1234' => '-⁶¹⁷/₅₀₀₀',
    '12345.678' => '12345 ³³⁹/₅₀₀',
    '-12345.678' => '-12345 ³³⁹/₅₀₀',
    '1234.' => '1234',
    '-1234' => '-1234',
    1234 => '1234',
    -1234 => '-1234',
  ];

  /** @return void */
  public function testFromDecimal():void
  {
    foreach (self::DECIMALS as $decimal => $rationalAsString) {
      $number = RationalNumber::fromDecimal($decimal);
      $this->assertEquals($rationalAsString, (string)$number);
    }
  }

  /** @return void */
  public function testToDecimal():void
  {
    $rationals = [
      (new RationalNumber(0, 435, 100000))->toDecimal(4) => '0.0044',
      (new RationalNumber(0, 433, -100000))->toDecimal(4) => '-0.0043',
      (new RationalNumber(-1, 437, -100000))->toDecimal(4) => '-1.0044',
      (new RationalNumber(0, 435, 100000))->toDecimal(4, 4) => '0.0044',
      (new RationalNumber(0, 433, -100000))->toDecimal(4, 4) => '-0.0043',
    ];
    foreach ($rationals as $decimal => $givenDecimal) {
      $this->assertEquals($decimal, $givenDecimal);
    }
    $outOfBoundsRationals = [
      [1, 437, 100000],
      [-1, 437, -100000],
    ];
    foreach ($outOfBoundsRationals as $ctorArgs) {
      $this->expectException(OutOfBoundsException::class);
      (new RationalNumber(...$ctorArgs))->toDecimal(4, 4);
    }
  }

  /** @return void */
  public function testCreate():void
  {
    $this->assertEquals(RationalNumber::create(1, 2, 3)->equals(new RationalNumber(1, 2, 3)), true);
  }

  /** @return void */
  public function fromRational():void
  {
    $ctorArgs = [1, 300, 200];
    $this->assertEquals(RationalNumber::fromRational(new Rational(...$ctorArgs))->equals(new RationalNumber(...$ctorArgs)), true);
  }

  /**
   * Test whether all this solves the floating point round-off problems.
   *
   * @return void
   */
  public function testRound():void
  {
    $roundingExamples = [
      // r1, r2, digits, wrong float rounding result, correct rational rounding result
      [ [ 72, 5, 10], [1, 19, 100], 2, 86.27, [86, 28, 100] ],
    ];
    foreach ($roundingExamples as $tuple) {
      $float = ($tuple[0][0] + $tuple[0][1] / $tuple[0][2]) * ($tuple[1][0] + $tuple[1][1] / $tuple[1][2]);
      $this->assertEquals(round($float, $tuple[2]), $tuple[3]); // wrong
      $rational = RationalNumber::create(...$tuple[0])->multiply(RationalNumber::create(...$tuple[1]));
      $this->assertEquals($rational->round($tuple[2])->equals(RationalNumber::create(...$tuple[4])), true);
    }
  }

  /**
   * Test the result type of the basic operations.
   *
   * @return void
   */
  public function testResultTypes():void
  {
    $rational1 = RationalNumber::create(1, 2, 3);
    $rational2 = RationalNumber::create(4, 5, 6);
    $this->assertEquals(get_class($rational1->multiply($rational2)), RationalNumber::class);
    $this->assertEquals(get_class($rational1->divide($rational2)), RationalNumber::class);
    $this->assertEquals(get_class($rational1->add($rational2)), RationalNumber::class);
    $this->assertEquals(get_class($rational1->subtract($rational2)), RationalNumber::class);
    $this->assertEquals(get_class($rational1->inverse()), RationalNumber::class);
    $this->assertEquals(get_class($rational1->abs()), RationalNumber::class);
  }
}
