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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Repositories;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Service\L10N\BiDirectionalL10N;
use OCA\CAFEVDB\Maintenance\Migrations\Version19700101000002 as InitialInstrumentsMigration;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the BiDirectionalL10N service. */
#[Attributes\CoversClass(BiDirectionalL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
class BiDirectionalL10NTest extends TestCase
{
  private BiDirectionalL10N $instance;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $this->instance = new BiDirectionalL10N(
      appName: $mockProvider->appName,
      logger: $mockProvider->getLoggerInterface(),
      l10n: $mockProvider->getL10N(),
    );
  }

  /** {@inheritdoc} */
  public function testSetup(): void
  {
    $this->assertInstanceOf(BiDirectionalL10N::class, $this->instance);
  }

  /** {@inheritdoc} */
  public function testMusicTranslations(): void
  {
    $names = array_merge(
      InitialInstrumentsMigration::INSTRUMENT_FAMILY_NAMES,
      array_keys(InitialInstrumentsMigration::INSTRUMENTS),
    );
    foreach ($names as $name) {
      $translated = $this->instance->t($name);
      $this->assertNotEquals($name, $translated);
      $backTranslated = $this->instance->backTranslate($translated);
      if ($name == 'other' || $name == 'miscellaneous') {
        $allowed = ['other', 'miscellaneous'];
      } else {
        $allowed = [$name];
      }
      $allowed = array_merge($allowed, array_map(fn(string $name) => ucfirst($name), $allowed));
      $this->assertTrue(array_search($backTranslated, $allowed) !== false);
    }
  }
}
