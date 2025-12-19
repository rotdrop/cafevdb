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
use ValueError;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

/** Example enum for testing. */
enum EnumExample: string
{
  use BackedEnumTrait;

  case ONE = 'one';
  case TWO = 'two';
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
  public function testGetFromValue(): void
  {
    foreach (EnumExample::values() as $value) {
      $this->assertEquals($value, EnumExample::get($value)->value);
    }
  }

  /** @return void */
  public function testGetFromName(): void
  {
    foreach (EnumExample::names() as $name) {
      $this->assertEquals($name, EnumExample::get($name)->name);
    }
  }

  /** @return void */
  public function testGetFromInstance(): void
  {
    foreach (EnumExample::cases() as $instance) {
      $this->assertEquals($instance, EnumExample::get($instance));
    }
  }

  /** @return void */
  public function testGetFromInvalid(): void
  {
    $this->expectException(InvalidArgumentException::class);
    EnumExample::get('blahblahblah');
  }

  /** @return void */
  public function testGetFromInvalidExceptionChain(): void
  {
    try {
      EnumExample::get('blahblahblah');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
      $this->assertInstanceOf(Error::class, $t->getPrevious());
      $this->assertInstanceOf(ValueError::class, $t->getPrevious()->getPrevious());
    }
  }
}
