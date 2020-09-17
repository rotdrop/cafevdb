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

  private static $externalScripts = [];

  /**Wrapper around htmlspecialchars(); avoid double encoding, standard
   * options, UTF-8 for stone-age PHP versions.
   */
  public static function htmlEscape($string, $ent = null, $double_encode = false)
  {
    if (!$ent) {
      $ent = ENT_COMPAT;
      if (defined('ENT_HTML401')) {
          $ent |= ENT_HTML401;
      }
    }
    return htmlspecialchars($string, $ent, 'UTF-8', $double_encode);
  }

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

  /**Decode the record idea from the CGI data, return -1 if none
   * found.
   *
   * @param getParam callable(key, default = null)
   *
   * @@TODO This is totally ugly. Is it really needed?
   */
  public static function getCGIRecordId($getParam, $prefix = null)
  {
    if (!isset($prefix)) {
      Config::init();
      $prefix = Config::$pmeopts['cgi']['prefix']['sys'];
    }
    $recordKey = $prefix.'rec';
    $recordId  = $getParam($recordKey, -1);
    $opreq     = $getParam($prefix.'operation');
    $op        = parse_url($opreq, PHP_URL_PATH);
    $opargs    = array();
    parse_str(parse_url($opreq, PHP_URL_QUERY), $opargs);
    if ($recordId < 0 && isset($opargs[$recordKey]) && $opargs[$recordKey] > 0) {
      $recordId = $opargs[$recordKey];
    }

    return $recordId > 0 ? $recordId : -1;
  }

  /**Return the maximum upload file size.*/
  public static function maxUploadSize($target = 'temporary')
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFilesize = min($upload_max_filesize, $post_max_size);

    if ($target == 'cloud') {
      $freeSpace = \OC_Filesystem::free_space('/');
      $freeSpace = max($freeSpace, 0);
      $maxUploadFilesize = min($maxUploadFilesize, $freeSpace);
    }
    return $maxUploadFilesize;
  }

  /**Add some java-script external code (e.g. Google maps). Emit it
   * with emitExternalScripts().
   */
  public static function addExternalScript($script = '')
  {
    self::$externalScripts[] = $script;
  }

  /**Dump all external java-script scripts previously added with
   * addExternalScript(). Each inline-script is wrapped into a separate
   * @<script@>@</script@> element to make debugging easier.
   */
  public static function emitExternalScripts()
  {
    $scripts = '';
    foreach(self::$externalScripts as $script) {
      $scripts .=<<<__EOT__
<script type="text/javascript" src="$script"></script>

__EOT__;
    }
    self::$externalScripts = array(); // don't dump twice.

    return $scripts;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
