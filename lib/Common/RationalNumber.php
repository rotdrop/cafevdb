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
use OutOfBoundsException;

use MathPHP\Number\Rational;

/**
 * Rational numbers, exact fractions. This is mainly useful in a context where
 * the possible denominator are well known, which often is the case in a
 * monetary context.
 */
class RationalNumber extends Rational
{
  /**
   * {@inheritdoc}
   *
   * @param bool $normalized Assume the three ingredients do not need normalization.
   */
  public function __construct(int $integralPart, int $numerator = 0, int $denominator = 1, bool $skipNormalization = false)
  {
    if ($skipNormalization) {
      $this->whole = $integralPart;
      $this->numerator = $numerator;
      $this->denominator = $denominator;
    } else {
      parent::__construct($integralPart, $numerator, $denominator);
    }
  }

  /**
   * Generator method.
   *
   * @param int|float|string|RationalNumber $integralPartOrAny
   *
   * @param int $numerator
   *
   * @param int $denominator
   *
   * @return RationalNumber
   */
  public static function create(
    int|float|string|RationalNumber $integralPartOrAny,
    int $numerator = null,
    int $denominator = null,
  ):RationalNumber {
    if (!is_int($integralPartOrAny)) {
      if ($numerator !== null && $denominator !== null) {
        throw new InvalidArgumentException(
          'Too many arguments: only 1 is expected: "'
          . implode('", "', [$integralPartOrAny, $numerator, $denominator])
          . '".'
        );
      }
      if (is_float($integralPartOrAny)) {
        return static::fromFloat($integralPartOrAny);
      } elseif (is_string($integralPartOrAny)) {
        return static::fromDecimal($integralPartOrAny);
      } elseif ($integralPartOrAny instanceof RationalNumber) {
        return clone $integralPartOrAny;
      }
    }
    return new RationalNumber($integralPartOrAny, $numerator ?? 0, $denominator ?? 1);
  }

  /**
   * Generate a new instance from a given base-class instance.
   *
   * @param Rational $rational Construct an instance given a base-class instance.
   *
   * @return RationalNumber
   */
  public static function fromRational(Rational $rational):RationalNumber
  {
    return new RationalNumber($rational->whole, $rational->numerator, $rational->denominator, true);
  }

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
    } elseif ($this->getWholePart() == 0 && $this->getNumerator() == 0) {
      return 0;
    }
    return 1;
  }

  /**
   * Return a correctly rounded floating point string with the given number of
   * fractional digits. Intentionally the naming of the arguments $scale and
   * $precision corresponds to the Doctrine ORM "decimal"-type parameters.
   *
   * @param int $scale Number of fractional digits to produce. If the rational
   * number cannot be exactly represented by the given number of digits then
   * the result is rounded "5 away from zero".
   *
   * @param int $precision Total number of decimal digits. If <= 0 then there
   * is no limit on the number of digits. If positive and the rational number
   * does not fit into specified number of digits, an OutOfBounds exception is
   * thrown.
   *
   * @return string
   *
   * @throws OutOfBoundsException
   */
  public function toDecimal(int $scale = 0, int $precision = 0):string
  {
    $rollIn = pow(10, $scale + 1);
    $sign = $this->sign();
    $fixedPoint = str_pad(intdiv($this->abs()->multiply($rollIn)->getWholePart() + 5, 10), $scale + 1, '0', STR_PAD_LEFT);
    $integralPart = substr($fixedPoint, 0, -$scale);
    $fractionalPart = substr($fixedPoint, -$scale);
    $result = $integralPart . '.' . $fractionalPart;
    if ($precision > 0 && (strlen(ltrim($integralPart, '0')) + strlen($fractionalPart)) > $precision) {
      $bound = str_pad('', $precision - $scale, '9') . '.' . str_pad('', $scale, '9');
      throw new OutOfBoundsException(
        'The rational number ' . (string)$this . ' (' . $this->toFloat() . ') does not fit into the range [-' . $bound . ', ' . $bound . '].'
      );
    }
    return $sign < 0 ? '-' . $result : $result;
  }

  /**
   * Initialize an instance from a "vanilla" decimal string. Only supported
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

  /**
   * Try to convert the given float into a RationalNumber.
   *
   * @param float $value
   *
   * @return RationalNumber
   *
   * @bug
   */
  public static function fromFloat(float $value):RationalNumber
  {
    $valueString = sprintf('%.16f', $value);
    // @todo: range checking
    return self::fromDecimal($valueString);
  }

  /** {@inheritdoc} */
  public function abs():RationalNumber
  {
    return self::fromRational(parent::abs());
  }

  /** {@inheritdoc} */
  public function inverse():RationalNumber
  {
    return self::fromRational(parent::inverse());
  }

  /** {@inheritdoc} */
  public function add($r):RationalNumber
  {
    return self::fromRational(parent::add($r));
  }

  /** {@inheritdoc} */
  public function subtract($r):RationalNumber
  {
    return self::fromRational(parent::subtract($r));
  }

  /** {@inheritdoc} */
  public function multiply($r):RationalNumber
  {
    return self::fromRational(parent::multiply($r));
  }

  /** {@inheritdoc} */
  public function divide($r):RationalNumber
  {
    return self::fromRational(parent::divide($r));
  }
}
