<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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

use Locale;

use OCA\CAFEVDB\Wrapped\Org\Heigl\Hyphenator\Hyphenator;
use OCA\CAFEVDB\Wrapped\Org\Heigl\Hyphenator\Tokenizer as HyphenatorTokenizer;
use OCA\CAFEVDB\Wrapped\Org\Heigl\Hyphenator\Options as HyphenatorOptions;
use OCA\CAFEVDB\Service\L10N\AppL10N;

/** General static utility routines. */
class CamelCaseString
{
  use \OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait {
    camelCaseToDashes as public;
    dashesToCamelCase as public;
  }

  /**
   * @param Hyphenator $hyphenator
   *
   * @param string $appLocale
   */
  public function __construct(
    /** @var Hyphenator\Hyphenator */
    private Hyphenator $hyphenator,
    /** @var string */
    private string $appLocale,
  ) {
    $options = new HyphenatorOptions()
      ->setHyphen(' ')
      ->setDefaultLocale(Locale::canonicalize($appLocale))
      ->setFilters('NonStandard')
      // ->setFilters('Simple, CustomMarkup')
      ->setTokenizers(['Whitespace', 'Punctuation'])
      ;
    $this->hyphenator->setOptions($options);
  }

  /**
   * @param int $minLen
   *
   * @return void
   */
  private function setHyphenatorOptions(int $minLen): void
  {
    $this->hyphenator->getOptions()
      ->setRightMin($minLen)
      ->setLeftMin($minLen)
      ->setWordMin(2 * $minLen + 1);
  }

  /**
   * Shorten the given string by iteratively reducing the size of its
   * camel-case components until it fits into the given length.
   *
   * @param string $string The string to shorten.
   *
   * @param integer $limit The target length.
   *
   * @param integer $minLen Defaults to 2. Components smaller than this will not
   * be shortened. This also means that the target-length may not be reached.
   *
   * @return string
   */
  public function shorten(string $string, int $limit, int $minLen = 2):string
  {
    $excess = strlen($string) - $limit;
    if ($excess > 0) {
      $this->setHyphenatorOptions($minLen);
      $parts = explode(' ', self::camelCaseToDashes($string, ' '));
      $parts = array_merge(...array_map(
        function(string $word) {
          $isWord = preg_match('/^\p{L}+$/u', $word);
          if (!$isWord) {
            return [$word];
          }
          $splits = array_map(fn(string $x) => explode(' ', $x), $this->hyphenator->hyphenate($word));
          if (empty($splits)) {
            return [$word];
          }
          $dist = array_map(fn(array $x) => abs(strlen($x[0]) - strlen($x[1])), $splits);
          $argMinDist = array_search(min($dist), $dist);
          return $splits[$argMinDist];
        },
        $parts,
      ));
      // \OCP\Util::writeLog('cafevdb', print_r($parts, true), \OCP\Util::INFO);
      do {
        $shortened = false;
        foreach ($parts as &$part) {
          if (strlen($part)  > $minLen) {
            $part = substr($part, 0, -1);
            $excess --;
            $shortened = true;
          }
        }
      } while ($excess > 0 && $shortened);
      $string = self::dashesToCamelCase(implode(' ', $parts), capitalizeFirstCharacter: true, dashes: ' ');
    }
    return $string;
  }
}
