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

use OutOfBoundsException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCA\CAFEVDB\Common\Transliterator;

/** Test the LoginNameSlug\generate() function. */
#[Attributes\CoversClass(Transliterator::class)]
#[Attributes\CoversMethod(Transliterator::class, 'transliterate')]
#[Attributes\CoversMethod(Transliterator::class, 'generateUserIdSlug')]
class TransliteratorTest extends TestCase
{
  private Transliterator $transliterator;

  /**
   * @{inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    parent::setup();
    $this->transliterator = new Transliterator('de_DE.UTF-8');
  }

  private const ASCII_STRING = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~';

  const TRANSLITERATIONS = [
    'oeOeaeAeueUess' => [ 'data' => 'öÖäÄüÜß', 'locale' => 'de_DE.UTF-8' ],
    'oeOeae AeueUess' => [ 'data' => 'öÖä ÄüÜß', 'locale' => 'de_DE.UTF-8' ],
    'Doe, John' => [ 'data' => 'Doe, John', 'locale' => 'de_DE.UTF-8' ],
    'Doe; John' => [ 'data' => 'Doe; John', 'locale' => 'de_DE.UTF-8' ],
    self::ASCII_STRING => [ 'data' => self::ASCII_STRING, 'locale' => 'de_DE.UTF-8' ],
  ];

  const LOGIN_NAMES = [
    'ursula.kemeny' => [
      'names' => [ 'firstName' => 'Ursula', 'surName' => 'Kemény', ],
      'locale' => 'de_DE.UTF-8',
    ],
    'claus-justus.heine' => [
      'names' => [ 'firstName' => 'Claus-Justus', 'surName' => 'Heine', ],
      'locale' => 'de_DE.UTF-8',
    ],
    'maren.kroeger' => [
      'names' => [ 'firstName' => 'Maren', 'surName' => 'Kröger', ],
      'locale' => 'de_DE.UTF-8',
    ],
    'john-james.doe' => [
      'names' => [ 'firstName' => 'John James', 'surName' => 'Doe' ],
      'locale' => 'C.UTF-8',
    ],
    'john#james|doe' => [
      'names' => [ 'firstName' => 'John James', 'surName' => 'Doe' ],
      'locale' => 'C.UTF-8',
      'separator' => '|',
      'wordSeparator' => '#',
    ],
  ];

  /** @return void */
  public function testTransliterate():void
  {
    foreach (self::TRANSLITERATIONS as $expected => $testData) {
      $result = call_user_func_array([ $this->transliterator, 'transliterate' ], $testData);
      $this->assertEquals($result, $expected);
    }
  }

  /** @return void */
  public function testGenerateUserIdSlug():void
  {
    foreach (self::LOGIN_NAMES as $expected => $testData) {
      $result = call_user_func_array([ $this->transliterator, 'generateUserIdSlug' ], $testData);
    }
    $this->assertEquals($result, $expected);
  }
}
