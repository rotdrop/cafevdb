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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCA\CAFEVDB\Common\Util;

/** Test aspects of the Util class. */
#[Attributes\CoversClass(Util::class)]
#[Attributes\CoversTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
class UtilTest extends TestCase
{
  private const CAMEL_CASE_TO_DASHES_DATA = [
    'Camel1234Case!' => 'camel_1234_case_!',
  ];

  /** @return void */
  public function testCamelCaseToDashes(): void
  {
    foreach (self::CAMEL_CASE_TO_DASHES_DATA as $string => $expected) {
      $this->assertEquals($expected, Util::camelCaseToDashes($string, separator: '_'));
      $this->assertEquals($expected, Util::camelCaseToDashes(lcfirst($string), separator: '_'));
    }
  }

  /** @return void */
  public function testDashesToCamelCase(): void
  {
    foreach (self::CAMEL_CASE_TO_DASHES_DATA as $expected => $string) {
      $this->assertEquals($expected, Util::dashesToCamelCase($string, capitalizeFirstCharacter: true));
      $this->assertEquals(lcfirst($expected), Util::dashesToCamelCase($string, capitalizeFirstCharacter: false));
    }
  }
}
