<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use InvalidArgumentException;

use MathPHP\Number\Rational;

/**
 * Rational numbers, exact fractions. This is mainly useful in a context where
 * the possible denominator are well known, which often is the case in a
 * monetary context.
 */
class RationalNumber extends Rational
{
  /**
   * Round "half away from zero".
   *
   * @param int $precision Number of decimal places after the comma.
   *
   * @return RationalNumber A new instance modeling the rounded number.
   */
  public function round(int $precision = 0):RationalNumber
  {
    $rollIn = pow(10, $precision + 1);
    $roundInc = ($this->getWholePart() + $this->getNumerator() < 0) ? -5 : 5;
    return new RationalNumber(0, intdiv($this->multiply($rollIn)->getWholePart() + $roundInc, 10), $rollIn / 10);
  }

  /**
   * @return -1 for negative numbers, +1 for positive numbers, 0 for 0.
   */
  public function sign():int
  {
    if ($this->getWholePart() < 0 || $this->getNumerator() < 0) {
      return -1;
    } elseif ($this->getWholePart() == 0 && $this->getNumerator == 0) {
      return 0;
    }
    return 1;
  }

  /**
   * Return a correctly rounded floating point string with the given number of
   * fractional digits.
   *
   * @param int $precision Number of fractional digits.
   *
   * @return string
   */
  public function toDecimal(int $precision = 0):string
  {
    $rollIn = pow(10, $precision + 1);
    $sign = $this->sign();
    $fixedPoint = str_pad(intdiv($this->abs()->multiply($rollIn)->getWholePart() + 5, 10), $precision + 1, '0', STR_PAD_LEFT);
    $result = substr($fixedPoint, 0, -$precision) . '.' . substr($fixedPoint, -$precision);
    return $sign < 0 ? '-' . $result : $result;
  }

  /**
   * Initialize an instance from a "vanialla" decimal string. Only supported
   * formats are (optional in square brackets):
   *
   * [-][D1...DN][0][.][F1....FM]
   *
   * e.g. .1234, -.1234, 0.1234, -0.1234, 1234.5678, -1234.5678, 1234.
   *
   * Read. "scientific" notation like 1e-6 is not supported.
   *
   * @param string|int $decimal
   *
   * @return RationalNumber
   */
  public static function fromDecimal(string|int $decimal):RationalNumber
  {
    if (is_int($decimal)) {
      return new RationalNumber($decimal, 0, 1);
    }
    $matches = [];
    if (!preg_match_all('/^(-)?([1-9]\d*|0?)(?:\.(\d*))?$/', $decimal, $matches)) {
      throw new InvalidArgumentException('Unable to parse input string "' . $decimal . '".');
    }
    $sign = $matches[1][0] == '-' ? -1 : 1;
    $integralPart = empty($matches[2]) ? 0 : (int)$matches[2][0];
    $fractionalPart = empty($matches[3]) ? 0 : (int)$matches[3][0];
    return new RationalNumber($sign * $integralPart, $sign * $fractionalPart, pow(10, strlen($fractionalPart)));
  }
}
