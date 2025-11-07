<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

// hacked versions ...
use OCA\CAFEVDB\Wrapped\Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Transliterate any given name into an ASCII login name. In principle the
 * Symfony AsciiSlugger does all this, but unfortunately also replaces all
 * non-[a-zA-Z0-9] characters by the separator.
 */
class Transliterator
{
  protected string $locale;

  protected AsciiSlugger $slugger;

  /** {@inheritdoc} */
  public function __construct(
    string $appLocale,
  ) {
    $this->locale = $appLocale;
    $this->slugger = new AsciiSlugger;
  }

  /** @return string */
  public function getLocale():string
  {
    return $this->locale;
  }

  /**
   * @param string $locale
   *
   * @return Transliterate
   */
  public function setLocale(string $locale):Transliterate
  {
    $this->locale = $locale;

    return $this;
  }

  /**
   * @param string $data The data to transliterate.
   *
   * @param null|string $locale The locale to use for transliteration.
   *
   * @return string Pure ASCII transliterated string.
   */
  public function transliterate(string $data, ?string $locale = null):string
  {
    return $this->slugger->transliterate($data, $locale ?? $this->locale)->toString();
  }

  /**
   * Generate a login-name from given unicode natural names. Spaces will be converted
   *
   * @param array $names First-, nick- and sur-name. The nick-name is optional
   * and may be empty or even omitted.
   *
   * ```
   * [
   *   'firstName' => UTF_8_FIRSTNAME,
   *   'nickName' => UTF_8_NICKNAME,
   *   'surName' => UTF_8_SURNAME,
   * ]
   * ```
   *
   * The keys are actually arbitrary.
   *
   * @param null|string $locale
   *
   * @param array $preferred The preferred kyes into $names to use for
   * slugging. These will be used if they are present. If not, then the
   * remaining present keys are used.
   *
   * @param string $separator The outer separator between the components of
   * $names.
   *
   * @param string $wordSeparator The "inner" separator which will replace
   * sequences of white space.
   *
   * @return string An ASCII only no-spaces slug suitable to be used as login-name.
   */
  public function generateUserIdSlug(
    array $names,
    ?string $locale = null,
    array $preferred = ['nickName', 'surName'],
    string $separator = '.',
    string $wordSeparator = '-',
  ):string {
    $preferredParts = array_filter(array_intersect_key($names, array_flip($preferred)));
    if (count($preferredParts) == count($preferred)) {
      $parts = $preferredParts;
    } else {
      $parts = array_filter($names);
    }
    $slugger = new AsciiSlugger;
    $parts = array_map(fn(string $part) => $slugger->slug($part, $wordSeparator, $locale ?? $this->locale)->lower()->toString(), $parts);

    return implode($separator, $parts);
  }
}
