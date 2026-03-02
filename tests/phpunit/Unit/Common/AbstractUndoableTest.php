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

use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Common\AbstractUndoable;

/**
 *  Test the LoginNameSlug\generate() function.
 */
#[Attributes\CoversClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
class AbstractUndoableTest extends TestCase
{
  // public for access in self::$instance
  public const SORT_ORDER = 15;

  private AbstractUndoable $instance;

  /** @return void */
  public function setup(): void
  {
    $this->instance = new class() extends AbstractUndoable {
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
      // phpcs:disable Squiz.Commenting.FunctionComment.Missing
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.BraceOnSameLine
      public function __construct() { $this->sortOrder = AbstractUndoableTest::SORT_ORDER; }
      public function do(): void {}
      public function undo(): void {}
      public function reset(): void {}
      // phpcs:enable
    };
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testInitialize(): void
  {
    $appContainer = $this->createStub(ContainerInterface::class);
    $this->instance->initialize($appContainer);
    $instanceAppContainer = new ReflectionProperty($this->instance, 'appContainer');
    $this->assertEquals($appContainer, $instanceAppContainer->getValue($this->instance));
  }

  /** @return void */
  public function testGetSortOrder(): void
  {
    $appContainer = $this->createStub(ContainerInterface::class);
    $this->assertEquals(self::SORT_ORDER, $this->instance->getSortOrder());
  }
}
