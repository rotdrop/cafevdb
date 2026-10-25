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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Service\Finance\SepaTransliterator;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\EnableDeprecationsTrait;

/** Test the SepaTransliterator. */
#[Attributes\CoversClass(SepaTransliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class SepaTransliteratorTest extends TestCase
{
  use EnableDeprecationsTrait;

  private MockProvider $mockProvider;

  private SepaTransliterator $transliterator;

  private string $appLocale;

  /** {@inheritdoc} */
  public function setup(): void
  {
    self::enableDeprecations();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $appContainer = $this->mockProvider->getAppContainer();

    $this->appLocale = $appContainer->get('AppLocale');
    // echo $this->appLocale . PHP_EOL;
    $this->transliterator = $appContainer->get(SepaTransliterator::class);
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /**
   * Check that each extra chars are indeed transliterated.
   *
   * @return void
   */
  public function testExtraCharsConsistency(): void
  {
    foreach (SepaTransliterator::TRANSLIT_EXTRA as $extra => $replacement) {
      $output = $this->transliterator->translit($extra);
      $this->assertEquals($replacement, $output);
    }
  }

  /** @return void */
  public function testGermanUmlautTransliteration(): void
  {
    $input = 'äöüßÄÖÜ';
    $expected = 'aeoeuessAEOEUE';
    $output = $this->transliterator->translit($input);
    $this->assertEquals($expected, $output);
  }

  /** @return void */
  public function testSpecialCharactersTransliteration(): void
  {
    $input = '€ $ £ § & @';
    $expected = 'EUR USD GBP ? et. at';
    $output = $this->transliterator->translit($input);
    $this->assertEquals($expected, $output);
    $this->assertTrue($this->transliterator->validate($output));
  }

  /** @return void */
  public function testValidate(): void
  {
    $good = $this->transliterator->validate('BlahBlah' . implode('', array_values(SepaTransliterator::TRANSLIT_EXTRA)));
    $this->assertEquals(true, $good);
    $bad = $this->transliterator->validate(implode('', array_keys(SepaTransliterator::TRANSLIT_EXTRA)));
    $this->assertEquals(false, $bad);
  }

}
