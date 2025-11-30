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
use InvalidArgumentException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use MathPHP\Number\Rational;
use OCA\CAFEVDB\Common\RationalNumber;

/** Test the RationalNumber class. */
#[Attributes\CoversClass(RationalNumber::class)]
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
    $this->expectException(InvalidArgumentException::class);
    RationalNumber::fromDecimal('hutzliputzli');
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
      (new RationalNumber(0, 1, 3))->toDecimal(-1) => '0.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX, '3'),
      (new RationalNumber(0, 1, 3))->toDecimal() => '0.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX, '3'),
      (new RationalNumber(0, -2, 3))->toDecimal(-1) => '-0.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 2, '6') . '7',
      (new RationalNumber(0, -2, 3))->toDecimal() => '-0.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 2, '6') . '7',
      (new RationalNumber(12, 1, 3))->toDecimal(-1) => '12.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 2, '3'),
      (new RationalNumber(12, 1, 3))->toDecimal() => '12.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 2, '3'),
      (new RationalNumber(-123, -2, 3))->toDecimal(-1) => '-123.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 1 - 3, '6') . '7',
      (new RationalNumber(-123, -2, 3))->toDecimal() => '-123.' . str_pad('', RationalNumber::DECIMAL_DIGITS_MAX - 1 - 3, '6') . '7',
      (new RationalNumber(0, 19, 100))->toDecimal(-1) => '0.19',
      (new RationalNumber(0, 19, 100))->toDecimal() => '0.19',
      (new RationalNumber(0, 391, 1000))->toDecimal(-1) => '0.391',
      (new RationalNumber(0, 391, 1000))->toDecimal() => '0.391',
      (new RationalNumber(0, 18189, 20000))->toDecimal(-1, 0) => '0.90945',
    ];
    foreach ($rationals as $decimal => $givenDecimal) {
      $this->assertEquals($givenDecimal, $decimal);
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

  const FLOATS = [
    .1234,
    -.1234,
    12345.678,
    -12345.678,
    1234,
    -1234,
  ];

  /** @return void */
  public function testFromFloat():void
  {
    foreach (self::FLOATS as $float) {
      $number = RationalNumber::fromFloat($float)->toFloat();
      $this->assertEquals($number, $float);
    }
  }

  const SIGNS = [
    '.1234' => 1,
    '-.1234' => -1,
    '0.0' => 0,
  ];

  /** @return void */
  public function testSign():void
  {
    foreach (self::SIGNS as $decimal => $sign) {
      $number = RationalNumber::fromDecimal($decimal)->sign();
      $this->assertEquals($number, $sign);
    }
  }

  /** @return void */
  public function testZero():void
  {
    $this->assertEquals(RationalNumber::fromDecimal(0), RationalNumber::zero());
    $this->assertEquals(RationalNumber::fromDecimal(0), RationalNumber::createZeroValue());
  }


  const CREATE_DATA = [
    [ '¹/₂', 0.5 ],
    [ '-¹/₂', -0.5],
    [ '¹/₁₆', 1.0/16.0 ],
    [ '-¹/₁₆', -1.0/16.0 ],
    [ '¹/₂', '0.5' ],
    [ '-¹/₂', '-0.5' ],
  ];

  /**
   * Test generation from various stuff.
   *
   * @return void
   */
  public function testCreate():void
  {
    $this->assertEquals(true, RationalNumber::create(1, 2, 3)->equals(new RationalNumber(1, 2, 3)));
    foreach (self::CREATE_DATA as $pair) {
      [$expected, $origin] = $pair;
      $rational = RationalNumber::create($origin);
      $this->assertEquals($expected, (string)$rational);
      $this->assertEquals($expected, (string)RationalNumber::create($rational));
      $this->assertEquals($expected, (string)RationalNumber::Create(new Rational(
        $rational->getWholePart(),
        $rational->getNumerator(),
        $rational->getDenominator(),
      )));
    }
    $this->expectException(InvalidArgumentException::class);
    RationalNumber::create(0.5, 1, 2);
  }

  /** @return void */
  public function fromRational():void
  {
    $ctorArgs = [1, 300, 200];
    $this->assertEquals(true, RationalNumber::fromRational(new Rational(...$ctorArgs))->equals(new RationalNumber(...$ctorArgs)));
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
      // check that float gives indeed the wrong result:
      $this->assertEquals($tuple[3], round($float, $tuple[2])); // wrong
      $rational = RationalNumber::create(...$tuple[0])->multiply(RationalNumber::create(...$tuple[1]));
      $this->assertEquals(true, $rational->round($tuple[2])->equals(RationalNumber::create(...$tuple[4])));
    }
  }

  /**
   * Test some more arithmetic.
   *
   * @return void
   */
  public function testPow():void
  {
    $rationals = [
      (string)RationalNumber::create(2)->pow(4) => '16',
      (string)(new RationalNumber(2))->pow(-4) => '¹/₁₆',
      (string)(new RationalNumber(0, 1, 3))->pow(4) => '¹/₈₁',
      (string)(new RationalNumber(0, 1, 3))->pow(-4) => '81',
      (string)(new RationalNumber(2))->pow(0) => '1',
      (string)RationalNumber::zero()->pow(0) => '1',
    ];
    foreach ($rationals as $powString => $givenString) {
      $this->assertEquals($givenString, $powString);
    }
  }

  /**
   * Test some more arithmetic.
   *
   * @return void
   */
  public function testMinMax():void
  {
    // first element is expected result.
    $rationals = [
      [ RationalNumber::create(1, 1, 2), RationalNumber::create(1, 1, 2), 'min' ],
      [ RationalNumber::create(1, 1, 2), RationalNumber::create(1, 1, 2), 'max' ],
      [ RationalNumber::create(1, 1, 2), RationalNumber::create(-1, 1, 2), 'max' ],
      [ RationalNumber::create(-1, 1, 2), RationalNumber::create(1, 1, 2), 'min' ],
      [ RationalNumber::create(1, 1, 2), RationalNumber::create(2, 1, 2), 'min' ],
      [ RationalNumber::create(1, 1, 2)->negEq(), RationalNumber::create(2, 1, 2)->negEq(), 'max' ],
    ];
    foreach ($rationals as $testData) {
      $this->assertEquals($testData[0], RationalNumber::{$testData[2]}($testData[0], $testData[1]));
    }
  }

  /**
   * Test in-place assignments.
   *
   * @return void
   */
  public function testInPlaceOperations():void
  {
    $rational2 = RationalNumber::create(4, 5, 6);

    // test whether the operation gives the same result as the non-assigning operations
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational2, (string)$rational1->assign($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->add($rational2), (string)$rational1->addEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->sub($rational2), (string)$rational1->subEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->mul($rational2), (string)$rational1->mulEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->div($rational2), (string)$rational1->divEq($rational2));
    $rational1 = RationalNumber::create(-1, -2, 3);
    $this->assertEquals((string)$rational1->abs(), (string)$rational1->absEq());
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->inv(), (string)$rational1->invEq());
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals((string)$rational1->neg(), (string)$rational1->negEq());

    // test whether we really operate in place
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->assign($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->addEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->subEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->mulEq($rational2));
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->divEq($rational2));
    $rational1 = RationalNumber::create(-1, -2, 3);
    $this->assertEquals(true, $rational1 === $rational1->absEq());
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->invEq());
    $rational1 = RationalNumber::create(1, 2, 3);
    $this->assertEquals(true, $rational1 === $rational1->negEq());
  }

  /**
   * Test comparisons.
   *
   * @return void
   */
  public function testComparisons():void
  {
    $rational1 = RationalNumber::create(1, 2, 3);
    $rational2 = RationalNumber::create(4, 5, 6);
    $operations = [
      'eq' => false,
      'gt' => false,
      'ge' => false,
      'lt' => true,
      'le' => true,
    ];
    foreach ($operations as $operation => $result) {
      $this->assertEquals($result, $rational1->{$operation}($rational2));
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
    $this->assertEquals(RationalNumber::class, get_class($rational1->multiply($rational2)));
    $this->assertEquals(RationalNumber::class, get_class($rational1->divide($rational2)));
    $this->assertEquals(RationalNumber::class, get_class($rational1->add($rational2)));
    $this->assertEquals(RationalNumber::class, get_class($rational1->subtract($rational2)));
    $this->assertEquals(RationalNumber::class, get_class($rational1->inverse()));
    $this->assertEquals(RationalNumber::class, get_class($rational1->abs()));
  }
}
