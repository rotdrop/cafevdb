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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace OCA\CAFEVDB\Common;

class Util
{
  const OMIT_EMPTY_FIELDS = 1;

  /**Return the timezone, from the calendar app.
   * @@TODO: this does not work anymore
   */
  public static function getTimezone()
  {
    $zone = \OC::$server->getDateTimeZone()->getTimeZone()->getName();
    if ($zone == '') {
      $zone = 'UTC';
    }
  }

  /**Explode, but omit empty array members, i.e. return empty array
   * for empty string.
   */
  static public function explode($delim, $string, $flags = self::OMIT_EMPTY_FIELDS)
  {
    if ($flags === self::OMIT_EMPTY_FIELDS) {
        return preg_split('/'.preg_quote($delim, '/').'/', $string, -1, PREG_SPLIT_NO_EMPTY);
    } else {
      return explode($delim, $string);
    }
  }

  /**Return the locale. */
  public static function getLocale($lang = null)
  {
    if (empty($lang)) {
      $lang = \OC::$server->getL10NFactory()->findLanguage(Config::APP_NAME);
    }
    $locale = $lang.'_'.strtoupper($lang).'.UTF-8';
    return $locale;
  }

  /**Return the currency symbol for the locale. */
  public static function currencySymbol($locale = null)
  {
    if (empty($locale)) {
      $locale = self::getLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
  }

  //!Just display the given value
  public static function moneyValue($value, $locale = null)
  {
    $oldlocale = setlocale(LC_MONETARY, '0');
    empty($locale) && $locale = self::getLocale();
    setlocale(LC_MONETARY, $locale);
    $result = money_format('%n', (float)$value);
    setlocale(LC_MONETARY, $oldlocale);
    return $result;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
