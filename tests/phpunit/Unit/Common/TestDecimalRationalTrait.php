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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Common;

use OCA\CAFEVDB\Common\AbstractDecimalRational;
use OCA\RotDrop\Tests\DeprecationException;

/** Test quasi-fixed-point numbers. */
trait TestDecimalRationalTrait
{
  private int $precision;

  private int $scale;

  /** {@inheritdoc} */
  public function setup(): void
  {
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->precision = (self::NUMBER_CLASS)::PRECISION;
    $this->scale = (self::NUMBER_CLASS)::SCALE;
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /**
   * @param int|string $whole
   *
   * @param int|string $fraction
   *
   * @return string
   */
  private function padDecimal(int|string $whole, int|string $fraction): string
  {
    $whole = (string)$whole;
    if ($whole != 0) {
      $this->assertLessThanOrEqual($this->precision - $this->scale, strlen($whole));
    }
    $fraction = (string)$fraction;
    return $whole . '.' . substr(str_pad($fraction, $this->scale, '0'), 0, $this->scale);
  }

  /**
   * Test toDecimal() and jsonSerialize().
   *
   * @return void
   */
  public function testToDecimal(): void
  {
    $values = [
      [(self::NUMBER_CLASS)::create(0, 1, 2), $this->padDecimal(0, 5)],
      [(self::NUMBER_CLASS)::create(0, 1, 3), $this->padDecimal(0, 3333333333333333)],
    ];
    if ($this->precision - $this->scale >= 1) {
      $values[] = [(self::NUMBER_CLASS)::create(1, 1, 2), $this->padDecimal(1, 5)];
    }
    foreach ($values as $testCase) {
      $decimal = $testCase[0]->toDecimal();
      $this->assertEquals($testCase[1], $decimal);
      $jsonString = $testCase[0]->jsonSerialize();
      $this->assertEquals($testCase[1], $jsonString);
    }
  }
}
