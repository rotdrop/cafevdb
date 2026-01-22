<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Traits;

use ReflectionProperty;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait;

/** Test the DateTimeTrait which manufactures dates from any arguments. */
#[Attributes\CoversTrait(AutoIncrementTrait::class)]
class AutoIncrementTraitTest extends TestCase
{
  private ReflectionProperty $idProperty;

  private object $class;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->class = new class() {
      use AutoIncrementTrait;
    };
    $this->idProperty = new ReflectionProperty($this->class, 'id');
  }

  /** {@inheritdoc} */
  public function testStoreInvalidIntAsNull(): void
  {
    $this->class->setId(0);
    $idValue = $this->idProperty->getValue($this->class);
    $this->assertNull($idValue);
    $this->assertNull($this->class->getId());

    $this->class->setId(-10);
    $idValue = $this->idProperty->getValue($this->class);
    $this->assertNull($idValue);
    $this->assertNull($this->class->getId());
  }

  /** {@inheritdoc} */
  public function testStoreNullAsNull(): void
  {
    $this->class->setId(null);
    $idValue = $this->idProperty->getValue($this->class);
    $this->assertNull($idValue);
    $this->assertNull($this->class->getId());
  }

  /** {@inheritdoc} */
  public function testStoreValidIntAsIs(): void
  {
    $this->class->setId(17);
    $idValue = $this->idProperty->getValue($this->class);
    $this->assertEquals(17, $idValue);
    $this->assertEquals(17, $this->class->getId());
  }
}
