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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversMethod;

use OCA\CAFEVDB\Common\RationalNumber;

/** Test the RationalNumber class. */
#[CoversMethod(RationalNumber::class, 'fromDecimal')]
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
}
