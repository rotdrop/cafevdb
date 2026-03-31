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
 */

namespace OCA\CAFEVDB\Tests\Unit\Common;

use OutOfBoundsException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCA\CAFEVDB\Common\NumberFormatter;
use OCA\CAFEVDB\Toolkit\Common\RationalNumber;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the NumberFormatter class. */
#[Attributes\CoversClass(NumberFormatter::class)]
#[Attributes\UsesClass(RationalNumber::class)]
class NumberFormatterTest extends TestCase
{
  private NumberFormatter $numberFormatter;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    DeprecationException::throwOnDeprecations();

    $this->numberFormatter = new NumberFormatter(locale: 'de_DE.UTF-8');
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testFormatCurrency(): void
  {
    $amount = 1.0 / 3.0;
    $expected = '0,33 €'; // Unicode non-breaking space.
    $result = $this->numberFormatter->formatCurrency($amount);
    $this->assertEquals($expected, $result);
    $result = $this->numberFormatter->formatCurrency(RationalNumber::create($amount));
    $this->assertEquals($expected, $result);
    $result = $this->numberFormatter->formatCurrency((string)$amount);
    $this->assertEquals($expected, $result);
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }
}
