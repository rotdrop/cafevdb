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

namespace OCA\CAFEVDB\PageRenderer;

/**Try to correct common human input "errors", respectively
 * sloppiness. Not much, ATM. */
class FuzzyInput
{
  const HTML_TIDY = 1;
  const HTML_PURIFY = 2;
  const HTML_ALL = ~0;

  /**Check $input for "transposition error". Interchange each
   * consecutive pair of letters, try to validate by $callback, return
   * an array of transposed input strings, for which $callback
   * returned true.
   */
  public static function transposition($input, $callback)
  {
    if (!is_callable($callback)) {
      return array();
    }
    $result = array();
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

  /**Try to get the number of bugs from a currency value. We act
   * quite simple: Strip the currency symbols from the users locale
   * and then try to parse the number, first with the users locale,
   * then with the C locale.
   *
   * @return mixed Either @c false or the floating point value
   * extracted from the input string.
   */
  public static function currencyValue($value)
  {
    $amount = preg_replace('/\s+/u', '', $value);
    $fmt = new \NumberFormatter(Util::getLocale(), \NumberFormatter::CURRENCY);
    $cur = $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
    $amount = str_replace($cur, '', $amount);
    $cur = $fmt->getSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL);
    $amount = str_replace($cur, '', $amount);
    $fmt = new \NumberFormatter(Util::getLocale(), \NumberFormatter::DECIMAL);
    $parsed = $fmt->parse($amount);
    if ($parsed === false) {
      $fmt = new \NumberFormatter('en_US_POSIX', \NumberFormatter::DECIMAL);
      $parsed = $fmt->parse($amount);
    }
    return $parsed !== false ? sprintf('%.02f', $parsed) : $parsed;
  }

  /**Try to correct HTML code.*/
  public static function purifyHTML($dirtyHTML, $method = self::HTML_PURIFY)
  {
    $purifier = null;
    if ($method & self::HTML_PURIFY) {
      $cacheDir = Config::userCacheDirectory('HTMLPurifier');
      $config = \HTMLPurifier_Config::createDefault();
      $config->set('Cache.SerializerPath', $cacheDir);
      $config->set('HTML.TargetBlank', true);
      // TODO: maybe add further options
      $purifier = new \HTMLPurifier($config);
    }

    $tidy = null;
    $tidyConfig = null;
    if ($method & self::HTML_TIDY) {
      $tidyConfig = array(
        'indent'         => true,
        'output-xhtml'   => true,
        'show-body-only' => true,
        'wrap'           => 200
      );
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
