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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\ConversionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\DecimalType;

/**
 * Abstract base class for decimal types
 */
abstract class AbstractDecimalRationalType extends DecimalType
{
  protected const PRECISION = 10;
  protected const SCALE = 0;
  protected const NAME = 'decimal_rational';

  /**
   * {@inheritDoc}
   */
  public function getName()
  {
    return static::NAME . '_' . static::PRECISION . '_' . static::SCALE;
  }

  /**
   * {@inheritDoc}
   *
   * This overrides precision and scale with the class constants
   */
  public function getSQLDeclaration(array $column, AbstractPlatform $platform)
  {
    $column['precision'] = static::PRECISION;
    $column['scale'] = static::SCALE;
    return $platform->getDecimalTypeDeclarationSQL($column);
  }

  /**
   * {@inheritDoc}
   */
  public function convertToPHPValue($value, AbstractPlatform $platform)
  {
    if ($value === null || $value === '') {
      return null;
    }
    if ($value instanceof RationalNumber) {
      return $value;
    }
    return RationalNumber::fromDecimal($value);
  }

  /**
   * {@inheritdoc}
   *
   * @param RationalNumber|int|float|string|null $value
   * @param AbstractPlatform $platform
   *
   * @return string|null
   *
   * @throws ConversionException
   */
  public function convertToDatabaseValue($value, AbstractPlatform $platform)
  {
    if ($value === null || $value === '') {
      return null;
    }
    $originalValue = $value;
    if (!($value instanceof RationalNumber)) {
      if (is_float($value)) {
        $value = RationalNumber::fromFloat($value);
      } elseif (is_int($value)) {
        $value = RationalNumber::create($value);
      } elseif (is_string($value)) {
        $value = RationalNumber::fromDecimal($value);
      } else {
        throw ConversionException::conversionFailed($value, static::NAME);
      }
    }
    try {
      return $value->toDecimal(static::SCALE, static::PRECISION);
    } catch (Throwable $t) {
      throw ConversionException::conversionFailed($originalValue, static::NAME, $t);
    }
  }
}
