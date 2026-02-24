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
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Common\AbstractDecimalRational;
use OCA\CAFEVDB\Common\DecimalRationalP2S2 as NumberType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalP2S2Type as DatabaseType;

/** Test some aspects of quasi-fixed-point numbers. */
#[Attributes\CoversClass(DatabaseType::class)]
#[Attributes\CoversClass(AbstractDecimalRational::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
class DecimalRationalP2S2TypeTest extends TestCase
{
  use TestDecimalRationalTypeTrait;

  private const NUMBER_CLASS = NumberType::class;
  private const DATABASE_TYPE_CLASS = DatabaseType::class;
}
