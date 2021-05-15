<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  use \OCA\CAFEVDB\Traits\DateTimeTrait { convertToDateTime as public; }

  public const OMIT_EMPTY_FIELDS = 1;
  public const TRIM = 2;

  public const FILE_EXTENSIONS_BY_MIME_TYPE = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/vnd.microsoft.icon' => 'ico',
    'application/pdf' => 'pdf',
  ];

  public static function fileExtensionFromMimeType($mimeType)
  {
    if (!empty(self::FILE_EXTENSIONS_BY_MIME_TYPE[$mimeType])) {
      return self::FILE_EXTENSIONS_BY_MIME_TYPE[$mimeType];
    }
    // as a wild guess we return anything after the slash if it is at
    // most 4 characters.
    list($first, $second) = explode('/', $mimeType);
    if (strlen($second) <= 4) {
      return $second;
    }
    return null;
  }

  /**
   * Merge $arrays recursively where later arguments in the list
   * override the values of previous arguments and numeric keys are
   * just appended.
   *
   * @param mxied ...$arrays
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
    $name = preg_replace('/\s*,/', ',', $name);
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
    if (!empty($flags)) {
      $pregFlags = ($flags & self::OMIT_EMPTY_FIELDS) ? PREG_SPLIT_NO_EMPTY : 0;
      $trimExpr = ($flags & self::TRIM) ? '\s*' : '';
      return preg_split('/'.$trimExpr.preg_quote($delim, '/').$trimExpr.'/', $string, -1, $pregFlags);
    } else {
      return explode($delim, $string);
    }
  }

  /**
   * Explode a string of the form
   * ```
   * A:B,C:D,...
   * ```
   * into an array of the shape
   * ```
   * [
   *   A => B,
   *   C => D,
   *   D => $default,
   *   ...
   * ]
   * ```
   * Only the first $keyDelimiter is taken into account.
   */
  static public function explodeIndexed(?string $data, $default = null, string $delimiter = ',', string $keyDelimiter = ':'):array
  {
    $matrix = array_map(
      function($row) use ($keyDelimiter, $default) {
        $row = explode($keyDelimiter, $row, 2);
        if (!isset($row[1]) || $row[1] === '') {
          $row[1] = $default;
        }
        return $row;
      },
      self::explode($delimiter, $data, self::TRIM|self::OMIT_EMPTY_FIELDS));
    return array_column($matrix, 1, 0);
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
  public static function dateTime($arg1 = "now", $arg2 = null, $arg3 = null):\DateTimeImmutable
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
    } else if ($arg2 === null && $arg3 === null) {
      $timeStamp = filter_var($arg1, FILTER_VALIDATE_INT, [ 'min' => 0 ]);
      if ($timeStamp !== false) {
        return (new \DateTimeImmutable())->setTimestamp($timeStamp);
      } else if (is_string($arg1)) {
         return new \DateTimeImmutable($arg1);
      }
    } else if ($arg3 === null) {
      return new \DateTimeImmutable($arg1, $arg2);
    }
    throw new \InvalidArgumentException('Unsupported arguments');
  }

  /**
   * Turn a php $_FILES array of the form
   * ```
   * [
   *   KEY => [
   *     IDX => VALUE,
   *   ],
   * ]
   * ```
   * into
   * ```
   * [
   *   IDX => [
   *     KEY => VALUE,
   *   ],
   * ]
   * ```
   */
  public static function transposeArray(array $files)
  {
    $result = [];
    $fileKeys = array_keys($files);
    foreach ($files as $key => $values) {
      if (!is_array($values)) {
        $values = [ $values ];
      }
      foreach ($values as $idx => $value) {
        $result[$idx][$key] = $value;
      }
    }
    return $result;
  }

  public static function fileUploadError(int $code, \OCP\IL10N $l)
  {
    switch ($code) {
    case UPLOAD_ERR_OK:
      return $l->t('There is no error, the file uploaded with success');
    case UPLOAD_ERR_INI_SIZE:
      return $l->t('The uploaded file exceeds the upload_max_filesize directive in php.ini: %s',
                   ini_get('upload_max_filesize'));
    case UPLOAD_ERR_FORM_SIZE:
      return $l->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
    case UPLOAD_ERR_PARTIAL:
      return $l->t('The uploaded file was only partially uploaded');
    case UPLOAD_ERR_NO_FILE:
      return $l->t('No file was uploaded');
    case UPLOAD_ERR_NO_TMP_DIR:
      return $l->t('Missing a temporary folder');
    case UPLOAD_ERR_CANT_WRITE:
      return $l->t('Failed to write to disk');
    case UPLOAD_ERR_EXTENSION:
      return $l->t('A PHP extension stopped the file upload.');
    default:
      return $l->t('Unknown upload error');
    }
  }

  static public function getEasterSunday($year = null, ?\DateTimeZone $timeZone = null)
  {
    if (empty($year)) {
      $year = date('Y');
    }
    if (empty($timeZone)) {
      $timeZone = new \DateTimeZone('UTC');
    }
    $base = new \DateTimeImmutable($year . '-03-21', $timeZone);
    $days = easter_days($year);

    $easterSunday = $base->add(new \DateInterval("P{$days}D"));

    return $easterSunday;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
