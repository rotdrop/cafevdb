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
 */

namespace OCA\CAFEVDB\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Toolkit;

/**
 * Test the Toolkit\Exceptions\DatabaseEntityException class and some of its child
 * classes.
 */
#[Attributes\CoversClass(Exceptions\DatabaseEntityNotFoundException::class)]
#[Attributes\CoversClass(Exceptions\DatabaseEntityNotUniqueException::class)]
#[Attributes\CoversClass(Exceptions\DatabaseInconsistentValueException::class)]
#[Attributes\CoversClass(Toolkit\Exceptions\DatabaseEntityException::class)]
#[Attributes\CoversClass(Toolkit\Exceptions\DatabaseMissingIdentifierException::class)]
class DatabaseEntityExceptionTest extends TestCase
{
  private const MESSAGE = 'MESSAGE';
  private const CODE = 666;
  private const PREVIOUS = null;
  private const ENTITY_NAME = 'ENTITY';
  private const IDENTIFIER = [ 'id' => -1 ];
  private const FIELD = 'FIELD';
  private const EXPECTED = 'EXPECTED';
  private const ACTUAL = 'ACTUAL';

  /** @return void */
  public function testDatabaseEntityException(): void
  {
    try {
      throw new Exceptions\DatabaseEntityException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
    }
  }

  /** @return void */
  public function testDatabaseEntityNotFoundException(): void
  {
    try {
      throw new Exceptions\DatabaseEntityNotFoundException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        identifier: self::IDENTIFIER,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::IDENTIFIER, $e->identifier);
    }
  }

  /** @return void */
  public function testDatabaseEntityNotUniqueException(): void
  {
    try {
      throw new Exceptions\DatabaseEntityNotUniqueException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        criteria: self::IDENTIFIER,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::IDENTIFIER, $e->criteria);
    }
  }

  /** @return void */
  public function testDatabaseMissingIdentifierException(): void
  {
    try {
      throw new Exceptions\DatabaseMissingIdentifierException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        incompleteIdentifier: self::IDENTIFIER,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::IDENTIFIER, $e->incompleteIdentifier);
    }
  }

  /** @return void */
  public function testDatabaseEntityInconsistenValueException(): void
  {
    try {
      throw new Exceptions\DatabaseInconsistentValueException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        field: self::FIELD,
        expected: self::EXPECTED,
        actual: self::ACTUAL,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::FIELD, $e->field);
      $this->assertEquals(self::EXPECTED, $e->expected);
      $this->assertEquals(self::ACTUAL, $e->actual);
    }
  }
}
