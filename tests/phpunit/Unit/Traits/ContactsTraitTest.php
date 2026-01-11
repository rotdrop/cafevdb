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

namespace OCA\CAFEVDB\Tests\Unit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IConfig;

use OCA\CAFEVDB\Traits\ContactsTrait;
use OCA\CAFEVDB\Tests\MockProvider;

// phpcs:disable
class Instance
{
  use ContactsTrait {
    flattenAddressBooks as public;
  }
}
// phpcs:enable

/**
 * Test the ContactsTrait.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */
#[Attributes\CoversTrait(ContactsTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
class ContactsTraitTest extends TestCase
{
  /** {@inheritdoc} */
  public function setup(): void
  {
    // $mockProvider = MockProvider::create($this);
  }

  /** @return void */
  public function testTraitUsage(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testNoAddressBooks(): void
  {
    $this->assertEquals([], Instance::flattenAddressBooks([]));
  }
}
