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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Common\Functions;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use function OCA\CAFEVDB\Common\Functions\dump;
use function OCA\CAFEVDB\Common\Functions\sprintf;
use function OCA\CAFEVDB\Common\Functions\strCmpEmptyLast;
use function OCA\CAFEVDB\Common\Functions\strcat;

/** Test. */
enum StringEnum: string
{
  case ONE = 'one';
  case TWO = 'two';
}

/** Test. */
enum IntegerEnum: int
{
  case ONE = 1;
  case TWO = 2;
}

/** Test some simple functions. */
#[Attributes\CoversNamespace(\OCA\CAFEVDB\Common\Functions::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Common\VarDumper::class)]
class FunctionsTest extends TestCase
{
  /** @return void */
  public function testDump(): void
  {
    // Primarily test that this just does not throw.
    $result = dump($this);
    $this->assertStringStartsWith(__CLASS__ . ' {', $result);
  }

  /** @return void */
  public function testSprintf(): void
  {
    $format = '%s %s %04d %04d';
    $expected = \sprintf(
      $format,
      StringEnum::ONE->value,
      StringEnum::TWO->value,
      IntegerEnum::ONE->value,
      IntegerEnum::TWO->value,
    );
    $result = sprintf($format, StringEnum::ONE, StringEnum::TWO, IntegerEnum::ONE, IntegerEnum::TWO);
    $this->assertEquals($expected, $result);
  }

  /** @return void */
  public function testStrCmpEmptyLast(): void
  {
    $this->assertEquals(0, strCmpEmptyLast(null, null));
    $this->assertEquals(0, strCmpEmptyLast('', ''));
    $this->assertEquals(0, strCmpEmptyLast(null, ''));
    $this->assertEquals(0, strCmpEmptyLast('', null));
    $this->assertEquals(1, strCmpEmptyLast(null, 'a'));
    $this->assertEquals(-1, strCmpEmptyLast('a', null));
    $this->assertEquals(1, strCmpEmptyLast('', 'a'));
    $this->assertEquals(-1, strCmpEmptyLast('a', ''));

    $this->assertEquals(-1, strCmpEmptyLast('a', 'b'));
    $this->assertEquals(0, strCmpEmptyLast('a', 'a'));
    $this->assertEquals(1, strCmpEmptyLast('b', 'a'));
  }

  /** @return void */
  public function testStrcat(): void
  {
    $expected = implode('', [
      'A',
      StringEnum::ONE->value,
      StringEnum::TWO->value,
      (string)IntegerEnum::ONE->value,
      (string)IntegerEnum::TWO->value,
      'B',
      ]);
    $result = strcat('A', StringEnum::ONE, StringEnum::TWO, IntegerEnum::ONE, IntegerEnum::TWO, 'B');
    $this->assertEquals($expected, $result);
  }
}
