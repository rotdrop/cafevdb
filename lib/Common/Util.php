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

namespace OCA\CAFEVDB\Common;

class Util
{
  const OMIT_EMPTY_FIELDS = 1;

  private static $externalScripts = [];

  /**
   * Merge $arrays recursively where later arguments in the list
   * override the values of previous arguments and numeric keys are
   * just appended.
   *
   * @param mixed ...$arrays
   *
   * @return array
   */
  public static function arrayMergeRecursive(...$arrays):array
  {
    if (count($arrays) == 0) {
      return [];
    }
    $result = $arrays[0];
    unset($arrays[0]);
    foreach ($arrays as $array) {
      $result = self::arrayMergeTwoRecursive($result, $array);
    }
    return $result;
  }

  /** Inner work-horse for arrayMergeRecursive. */
  private static function arrayMergeTwoRecursive($dest, $override)
  {
    foreach ($override as $key => $value) {
      if (is_integer($key)) {
        $dest[] = $value;
      } elseif (isset($dest[$key]) && is_array($dest[$key]) && is_array($value)) {
        $dest[$key] = self::arrayMergeTwoRecursive($dest[$key], $value);
      } else {
        $dest[$key] = $value;
      }
    }
    return $dest;
  }

  /** Normalize spaces and commas after and before spaces. */
  public static function normalizeSpaces($name, $singleSpace = ' ')
  {
    /* Normalize name and translation */
    $name = str_replace("\xc2\xa0", "\x20", $name);
    $name = trim($name);
    $name = preg_replace('/\s*,([^\s])/', ','.$singleSpace.'$1', $name);
    $name = preg_replace('/\s+/', $singleSpace, $name);

    return $name;
  }

  /** Remove all whitespace */
  public static function removeSpaces($name)
  {
    return self:: normalizeSpaces($name, '');
  }

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

  /**
   * Explode, but omit empty array members, i.e. return empty array
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
    $nonce = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();
    $scripts = '';
    foreach(self::$externalScripts as $script) {
      $scripts .=<<<__EOT__
<script nonce="$nonce" type="text/javascript" src="$script" defer></script>

__EOT__;
    }
    self::$externalScripts = array(); // don't dump twice.

    return $scripts;
  }

  /**Format the right way (tm). */
  public static function strftime($format, $timestamp = null, $tz = null, $locale = null)
  {
    $oldtz = date_default_timezone_get();
    if ($tz) {
      date_default_timezone_set($tz);
    }

    $oldlocale = setlocale(LC_TIME, '0');
    if ($locale) {
      setlocale(LC_TIME, $locale);
    }
    $result = strftime($format, $timestamp);

    setlocale(LC_TIME, $oldlocale);
    date_default_timezone_set($oldtz);

    return $result;
  }

  /**
   * Take any dashed or "underscored" lower-case string and convert to
   * camel-case.
   *
   * @param string $string the string to convert.
   *
   * @param bool $capitalizeFirstCharacter self explaining.
   *
   * @param string $dashes Characters to replace.
   */
  public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false, $dashes = '_-')
  {
    $str = str_replace(str_split($dashes), '', ucwords($string, $dashes));

    if (!$capitalizeFirstCharacter) {
      $str[0] = strtolower($str[0]);
    }

    return $str;
  }

  /**
   * Take an camel-case string and convert to lower-case with dashes
   * or underscores between the words. First letter may or may not
   * be upper case.
   *
   * @param string $string String to work on.
   *
   * @param string $separator Separator to use, defaults to '-'.
   */
  public static function camelCaseToDashes($string, $separator = '-')
  {
    return strtolower(preg_replace('/([A-Z])/', $separator.'$1', lcfirst($string)));
  }

  /**
   * Unset all array elements with value $value.
   *
   * @param array $hayStack The array to modify.
   *
   * @param mixed $value The value to remove.
   *
   * @return array The resulting array. Note that $hayStack is also
   * passed by reference.
   */
  public static function unsetValue(array &$hayStack, $value)
  {
    while (($key = array_search($value, $hayStack)) !== false) {
      unset($hayStack[$key]);
    }
    return $hayStack;
  }

  /**
   * Quick and dirty convenience function which combines
   * "constructors" into a single function. In particular the case
   * wherer $arg1 is already a date-time object is handled gracefully
   * by either pass-through to the return value or converting it to a
   * \DateTimeImmutable.
   *
   * @param string|\DateTimeImmutable|\DateTime|\DateTimeInterface TBD.
   *
   * @param null|string|\DateTimeZone TBD.
   *
   * @param null|string|\DateTimeZone TBD.
   *
   * @return \DateTimeImmutable TBD.
   */
  public static function dateTime($arg1 = "now", $arg2 = null, $arg3 = null)
  {
    if ($arg1 instanceof \DateTimeImmutable) {
      if ($arg2 !== null || $arg3 !== null) {
        throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
      }
      return $arg1;
    }
    if ($arg1 instanceof \DateTime) {
      if ($arg2 !== null || $arg3 !== null) {
        throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
      }
      return \DateTimeImmutable::createFromMutable($arg1);
    }
    if ($arg1 instanceof \DateTimeInterface) {
      if ($arg2 !== null || $arg3 !== null) {
        throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
      }
      return \DateTimeImmutable::createFromInterface($arg1);
    }
    if (is_string($arg1) && is_string($arg2)) {
      return \DateTimeImmutable::createFromFormat($arg1, $arg2, $arg3);
    } else if ($arg3 === null) {
      return new \DateTimeImmutable($arg1, $arg2);
    }
    throw new \InvalidArgumentException('Unsupported arguments');
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
