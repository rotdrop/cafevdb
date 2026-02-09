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

use Throwable;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCA\RotDrop\Tests\DeprecationException;

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

  private const STRFTIME_DATA = [
    '25.01.26 13:00:33' => [
      'format' => '%x %X',
      'timestamp' => 1769342433,
      'tz' => 'Europe/Berlin',
      'locale' => 'de_DE.UTF-8',
    ],
    '2026/02/08' => [
      'format' => '%Y/%m/%d',
      'timestamp' => 1770508800,
      'tz' => 'Europe/Berlin',
      'locale' => 'de_DE.UTF-8',
    ],
    '2026/02/01' => [
      'format' => '%Y/%m/%d',
      'timestamp' => 1769904000,
      'tz' => 'Europe/Berlin',
      'locale' => 'de_DE',
    ],
    '25.01.2026' => [
      'format' => '%d.%m.%Y',
      'timestamp' => 1769299200,
      'tz' => 'Europe/Berlin',
      'locale' => 'de_DE',
    ],
  ];

  /**
   * @return void
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public function testStringFromTime(): void
  {
    foreach (self::STRFTIME_DATA as $expected => $arguments) {
      extract($arguments);
      $this->assertEquals($expected, Util::strftime($format, $timestamp, $tz, $locale));
    }
  }

  /**
   * \null as subject is explicitly allowed, so test it ...
   *
   * @return void
   */
  public function testExplodeWithNullSubject(): void
  {
    $this->expectNotToPerformAssertions();
    DeprecationException::throwOnDeprecations();
    try {
      Util::explode(' ', null);
    } catch (Throwable $t) {
      restore_error_handler();
      if ($t instanceof DeprecationException) {
        /** @var DeprecationException $t */
        print_r($t->getDeprecationWarning());
      }
      throw $t;
    }
    try {
      Util::explode(' ', null, flags: 0);
    } catch (Throwable $t) {
      restore_error_handler();
      if ($t instanceof DeprecationException) {
        /** @var DeprecationException $t */
        print_r($t->getDeprecationWarning());
      }
      throw $t;
    }
    try {
      Util::explode(' ', null, flags: Util::OMIT_EMPTY_FIELDS);
    } catch (Throwable $t) {
      restore_error_handler();
      if ($t instanceof DeprecationException) {
        /** @var DeprecationException $t */
        print_r($t->getDeprecationWarning());
      }
      throw $t;
    }
    restore_error_handler();
  }

  private const ARRAY_MERGE_DATA = [
    [
      // ordinary merge, non-recursive
      'input' => [ ['a', 'b', 'c'], ['a', 'b', 'c'], ['a', 'b', 'c'] ],
      'output' => ['a', 'b', 'c', 'a', 'b', 'c', 'a', 'b', 'c'],
    ],
    [
      // ordinary merge, recursive
      'input' => [
        ['a', 'b', 'c'],
        [ ['a', 'b', 'c'] ],
      ],
      'output' => ['a', 'b', 'c', ['a', 'b', 'c']],
    ],
    [
      // ordinary merge, recursive
      'input' => [
        [ 'a' => ['a', 'b', 'c'] ],
        [ 'a' => ['a', 'b', 'c'] ],
      ],
      'output' => [ 'a' => ['a', 'b', 'c', 'a', 'b', 'c'] ],
    ],
    [
      // non-consecutive numeric keys are also preserverd
      'input' => [
        [
          1 => 'first array scalar value',
          2 => [ 'a', 'b' ],
        ],
        [
          1 => 'second array Value',
          2 => [ 'a', 'b' ],
          4 => 'key-4 value',
        ]
      ],
      'output' => [
        1 => 'second array Value',
        2 => ['a', 'b', 'a', 'b'],
        4 => 'key-4 value',
      ],
    ],
  ];

  /**
   * Test some aspects of arrayMergeRecursive()
   *
   * @return void
   */
  public function testArrayMergeRecursive(): void
  {
    foreach (self::ARRAY_MERGE_DATA as $test) {
      $result = Util::arrayMergeRecursive(...$test['input']);
      $this->assertEquals($test['output'], $result);
    }
  }
}
