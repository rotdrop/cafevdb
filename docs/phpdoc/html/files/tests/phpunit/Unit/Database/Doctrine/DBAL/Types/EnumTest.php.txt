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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\DBAL\Types;

use Error;
use InvalidArgumentException;
use Throwable;
use ValueError;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IL10N;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

/** Test some aspects of the enums. */
#[Attributes\CoversClass(Types\EnumAttachmentOrigin::class)]
#[Attributes\CoversClass(Types\EnumVCalendarType::class)]
#[Attributes\CoversTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\CoversTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
class EnumTest extends TestCase
{
  private IL10N $l;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $mockProvider = MockProvider::create($this);
    $this->l = $mockProvider->getL10N();
  }

  /**
   * The enum intentionally is untranslated. This is rather a test to ensure
   * that the Nextcloud translation machinery strip tags from the beginning up
   * to the first colon.
   *
   * @return void
   */
  public function testEnumVCalendarTypeTranslations(): void
  {
    $translations = Types\EnumVCalendarType::getL10NValues($this->l);
    $this->assertEqualsCanonicalizing(Types\EnumVCalendarType::toArray(), $translations);
  }

  private const EXPECTED_TRANSLATIONS = [
    'upload' => 'Hochladen',
    'cloud' => 'Cloud',
    'participant-field' => 'Tabellenspalte',
    'template' => 'Vorlage',
  ];

  /**
   * Check that we get the expected translations. This is rather to make sure
   * that the Nextcloud translation machinery works.
   *
   * @return void
   */
  public function testEnumAttachmentOriginTranslations(): void
  {
    $this->assertEquals('de', $this->l->getLanguageCode());
    $translations = Types\EnumAttachmentOrigin::getL10NValues($this->l);
    $this->assertEqualsCanonicalizing(self::EXPECTED_TRANSLATIONS, $translations);
  }
}
