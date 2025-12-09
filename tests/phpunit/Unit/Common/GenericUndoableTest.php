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

use ReflectionProperty;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test the LoginNameSlug\generate() function. */
#[Attributes\CoversClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
class GenericUndoableTest extends TestCase
{
  private const INITIAL_DATA = 'INITIAL';
  private const DO_RESULT = 'RESULT';

  /** @return void */
  public function testConstruction(): void
  {
    $this->expectNotToPerformAssertions();
    new GenericUndoable(
      doCallback: fn() => true,
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      undoCallback: function(bool $arg) {},
      sortOrder: 0,
    );
    new GenericUndoable(
      doCallback: fn() => true,
    );
    new GenericUndoable(
      doCallback: fn() => true,
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      undoCallback: function(bool $arg) {},
    );
  }

  /** @return void */
  public function testDoUndo(): void
  {
    $data = self::INITIAL_DATA;
    $undoable = new GenericUndoable(
      doCallback: function() use (&$data) {
        $data = self::DO_RESULT;
        return self::INITIAL_DATA;
      },
      undoCallback: function(string $initialValue) use (&$data) {
        $data = $initialValue;
      },
      sortOrder: 0,
    );

    $undoResult = new ReflectionProperty($undoable, 'doResult');

    $undoable->do();
    $this->assertEquals(self::INITIAL_DATA, $undoResult->getValue($undoable));
    $this->assertEquals(self::DO_RESULT, $data);

    $undoable->undo($undoResult);
    $this->assertEquals(self::INITIAL_DATA, $data);
  }
}
