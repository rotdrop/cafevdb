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

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Traits;

use Error;
use InvalidArgumentException;
use Throwable;
use TypeError;
use ValueError;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

/** Example enum for testing. */
enum StringEnumExample: string
{
  use BackedEnumTrait;

  case ONE = 'one';
  case TWO = 'two';
}

/** Other example enum for testing. */
enum IntEnumExample: int
{
  use BackedEnumTrait;

  case ONE = 1;
  case TWO = 2;
}

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversTrait(BackedEnumTrait::class)]
class BackedEnumTraitTest extends TestCase
{
  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
  }

  /** @return void */
  public function testValuesType(): void
  {
    foreach (StringEnumExample::values() as $value) {
      $this->assertIsString($value);
    }
    foreach (IntEnumExample::values() as $value) {
      $this->assertIsInt($value);
    }
  }



  /** @return void */
  public function testGetFromValue(): void
  {
    foreach (StringEnumExample::values() as $value) {
      $this->assertEquals($value, StringEnumExample::get($value)->value);
    }
    foreach (IntEnumExample::values() as $value) {
      $this->assertEquals($value, IntEnumExample::get($value)->value);
    }
  }

  /** @return void */
  public function testGetFromName(): void
  {
    foreach (StringEnumExample::names() as $name) {
      $this->assertEquals($name, StringEnumExample::get($name)->name);
    }
    foreach (IntEnumExample::names() as $name) {
      $this->assertEquals($name, IntEnumExample::get($name)->name);
    }
  }

  /** @return void */
  public function testGetFromInstance(): void
  {
    foreach (StringEnumExample::cases() as $instance) {
      $this->assertEquals($instance, StringEnumExample::get($instance));
    }
    foreach (IntEnumExample::cases() as $instance) {
      $this->assertEquals($instance, IntEnumExample::get($instance));
    }
  }

  /** @return void */
  public function testGetFromInvalidExceptionChain(): void
  {
    try {
      StringEnumExample::get('blahblahblah');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
      $this->assertInstanceOf(Error::class, $t->getPrevious());
      $this->assertInstanceOf(ValueError::class, $t->getPrevious()->getPrevious());
    }
    // Strict types, so passing null or other enums should throw.
    try {
      StringEnumExample::get(null);
    } catch (Throwable $t) {
      $this->assertInstanceOf(TypeError::class, $t);
    }
    // Strict types, so passing null or other enums should throw.
    try {
      StringEnumExample::get(IntEnumExample::ONE);
    } catch (Throwable $t) {
      $this->assertInstanceOf(TypeError::class, $t);
    }
  }
}
