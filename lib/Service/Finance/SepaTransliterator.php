<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use OCA\CAFEVDB\Common\Transliterator;

/** Special transliterator for SEPA stuff. */
class SepaTransliterator
{
  /**
   * @var string
   *
   * The must-be-supported character set.
   */
  public const CHARSET = "a-zA-Z0-9 /?:().,'+-";

  public const TRANSLIT_EXTRA = [
    '[' => '(',
    ']' => ')',
    '{' => '(',
    '}' => ')',
    ';' => '/',
    '_' => ' ',
    '$' => 'USD',
    '£' => 'GBP',
    '&' => 'et.',
    '@' => 'at',
  ];

  /** {@inheritdoc} */
  public function __construct(
    private Transliterator $transliterator,
  ) {
  }

  /**
   * Convert an UTF-8 encoded string to something simpler, with
   * transliteration of special characters. Even nowadays it differes from
   * bank to bank, and the allowed character definitely is not UTF-8, but a
   * very limited subset of ASCII.
   *
   * @param string $string String to convert.
   *
   * @param ?string $locale If not given default to the app-locale.
   *
   * @return string
   */
  public function translit(string $string, ?string $locale = null): string
  {
    $result = $this->transliterator->transliterate(
      str_replace(
        array_keys(self::TRANSLIT_EXTRA),
        array_values(self::TRANSLIT_EXTRA),
        $string,
      ),
      $locale,
    );
    return $result;
  }

  /**
   * Validate whether the given string conforms to a very restricted
   * character set. {@see self::translit()}
   *
   * @param string $string The string to validate.
   *
   * @return bool The validation result.
   */
  public function validate(string $string): bool
  {
    return !preg_match('@[^' . self::CHARSET . ']@', $string);
  }
}
