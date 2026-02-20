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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IL10N;

use OCA\CAFEVDB\EmailForm\EnumGlobalSubstitutionKey as TranslatableEnum;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(TranslatableEnum::class)]
#[Attributes\CoversTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\CoversTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
class EnumGlobalSubstitutionKeyTest extends TestCase
{
  private IL10N $l10n;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $mockProvider = MockProvider::create($this);
    $this->l10n = $mockProvider->getL10N();
  }

  /** @return void */
  public function testAllValuesAreTranslated(): void
  {
    $l10nPrefix = TranslatableEnum::l10nTag();
    $array = TranslatableEnum::toArray();
    foreach ($array as $case => $value) {
      $translation = $this->l10n->t($l10nPrefix . $value);
      $this->assertNotEquals($l10nPrefix . $value, $translation);
      $translation = $translation == $value ? $this->l10n->t($value) : $translation;
      $this->assertNotEquals($value, $translation);
      $this->assertEquals(TranslatableEnum::$case($this->l10n), $translation);
    }
  }
}
