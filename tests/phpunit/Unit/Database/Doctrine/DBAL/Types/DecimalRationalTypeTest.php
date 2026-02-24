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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\DBAL\Types;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Common\AbstractDecimalRational;
use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Database\Constants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\ConversionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Exception\SerializationFailed;
use OCA\RotDrop\Tests\DeprecationException;

/** Unit-test class. */
class DecimalRationalTestClass extends AbstractDecimalRational
{
  public const PRECISION = 7;
  public const SCALE = 4;
}

/** Unit-test class. */
class DecimalRationalTypeTestClass extends AbstractDecimalRationalType
{
  protected const NUMBER_CLASS = DecimalRationalTestClass::class;
}

/** Test the given number type class */
#[Attributes\CoversClass(AbstractDecimalRationalType::class)]
#[Attributes\CoversClass(AbstractDecimalRational::class)]
#[Attributes\UsesClass(RationalNumber::class)]
class DecimalRationalTypeTest extends TestCase
{
  use TestDecimalRationalTypeTrait;

  private const NUMBER_CLASS = DecimalRationalTestClass::class;
  private const DATABASE_TYPE_CLASS = DecimalRationalTypeTestClass::class;
}
