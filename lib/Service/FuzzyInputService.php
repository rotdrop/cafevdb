<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Common\Util;

/**
 * Try to correct common human input "errors", respectively
 * sloppiness. Not much, ATM.
 */
class FuzzyInputService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const HTML_TIDY = 1;
  const HTML_PURIFY = 2;
  const HTML_ALL = ~0;

  /** @var \OCA\CAFEVDB\Storage\UserStorage */
  private $userStorage;

  public function __construct(
    ConfigService $config
    , UserStorage $userStorage
  ) {
    $this->configService = $config;
    $this->userStorage = $userStorage;
    $this->l = $this->l10n();
  }

  /**
   * Check $input for "transposition error". Interchange each
   * consecutive pair of letters, try to validate by $callback, return
   * an array of transposed input strings, for which $callback
   * returned true.
   */
  public static function transposition($input, $callback)
  {
    if (!is_callable($callback)) {
      return [];
    }
    $result = [];
    $len = strlen($input);
    for ($idx = 0; $idx < $len - 1; ++$idx) {
      $victim = $input;
      $victim[$idx] = $input[$idx+1];
      $victim[$idx+1] = $input[$idx];
      if ($callback($victim)) {
        $result[] = $victim;
      }
    }
    return $result;
  }

  /**
   * Try to get the number of bucks from a currency value. We act
   * quite simple: Strip the currency symbols from the users locale
   * and then try to parse the number, first with the users locale,
   * then with the C locale.
   *
   * @param string $value Any input string.
   *
   * @return mixed Either @c false or the floating point value
   * extracted from the input string.
   */
  public function currencyValue(string $value)
  {
    $locale = $this->getLocale();
    $amount = preg_replace('/\s+/u', '', $value);
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $cur = $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
    $amount = str_replace($cur, '', $amount);
    $cur = $fmt->getSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL);
    $amount = str_replace($cur, '', $amount);
    $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
    $parsed = $fmt->parse($amount);
    if ($parsed === false) {
      $fmt = new \NumberFormatter('en_US_POSIX', \NumberFormatter::DECIMAL);
      $parsed = $fmt->parse($amount);
    }
    return $parsed !== false ? sprintf('%.02f', $parsed) : $parsed;
  }

  /**
   * Ensure the given word is a sensible camel-case slug:
   *
   * - if it does not contain spaces, then only capitalize the first
   * letter, if requested.
   *
   * - if it contains spaces, then first convert consecutive capital
   *  letters a the start of each word to lower case, then camelcalize
   *  it.
   *
   * @return The resuling camel-case string.
   */
  public function ensureCamelCase(string $slug, bool $capitalizeFirst = true):string
  {
    $slug = Util::normalizeSpaces($slug);
    $words = explode(' ', $slug);
    if (count($words) > 1) {
      foreach ($words as &$word) {
        $word = preg_replace_callback(
          '/\b([A-Z][A-Z]+)/',
          function($arg) { return strtolower($arg[1]); },
          $word);
      }
    }
    foreach ($words as &$word) {
      $word = Util::camelCaseToDashes($word, ' ');
      $word = Util::dashesToCamelCase($word, $capitalizeFirst, ' ');
    }
    $slug = implode('', $words);
    return $slug;
  }

  /**
   * Try to parse a floating point value.
   *
   * @param string $value Input value. Maybe a percentage.
   *
   * @return bool|float
   */
  public function floatValue(string $value)
  {
    $amount = preg_replace('/\s+/u', '', $value);
    $locales = [ $this->getLocale(), 'en_US_POSIX' ];
    $parsed = false;
    foreach ($locales as $locale) {
      $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);

      $decimalSeparator = $fmt->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
      $groupingSeparator = $fmt->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

      $decPos = strpos($amount, $decimalSeparator);
      $grpPos = strpos($amount, $groupingSeparator);

      if ($grpPos !== false && $decPos === false) {
        // unlikely: 1,000, we assume 1,000.00 would be used
        continue;
      } else if ($decPos < $grpPos) {
        // unlikely: 1.000,00 in en_US
        continue;
      }

      $parsed = $fmt->parse($amount);
      if ($parsed !== false) {
        $percent = $fmt->getSymbol(\NumberFormatter::PERCENT_SYMBOL);
        if (preg_match('/'.$percent.'/u', $amount)) {
            $parsed /= 100.0;
        }
        break;
      }
    }
    return $parsed !== false ? (float)$parsed : $parsed;
  }

  /**
   *  Try to correct HTML code.
   */
  public function purifyHTML($dirtyHTML, $method = self::HTML_PURIFY)
  {
    $purifier = null;
    if ($method & self::HTML_PURIFY) {
      $cacheDir = $this->userStorage->getCacheFolder('HTMLPurifier');
      $config = \HTMLPurifier_Config::createDefault();
      $config->set('Cache.SerializerPath', $cacheDir);
      $config->set('HTML.TargetBlank', true);
      // TODO: maybe add further options
      $purifier = new \HTMLPurifier($config);
    }

    $tidy = null;
    $tidyConfig = null;
    if ($method & self::HTML_TIDY) {
      $tidyConfig = [
        'indent'         => true,
        'output-xhtml'   => true,
        'show-body-only' => true,
        'wrap'           => 200
      ];
      $tidy = new \tidy;
    }

    if (!empty($tidy)) {
      $tidy->parseString($dirtyHTML, $tidyConfig, 'utf8');
      $tidy->cleanRepair();
      $dirtyHTML = (string)$tidy;
    }

    if (!empty($purifier)) {
      $dirtyHTML = $purifier->purify($dirtyHTML);
    }

    return $dirtyHTML;
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
