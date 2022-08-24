<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use League\HTMLToMarkdown\HtmlConverter as HtmlToMarkDown;

class Util
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait { convertToDateTime as public; }

  private static $markDownConverter;

  public const OMIT_EMPTY_FIELDS = 1;
  public const TRIM = 2;
  public const ESCAPED = 4;

  public const FILE_EXTENSIONS_BY_MIME_TYPE = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/vnd.microsoft.icon' => 'ico',
    'image/svg+xml' => 'svg',
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

  /** Array-map including keys. */
  public static function arrayMapAssoc(callable $f, array $a)
  {
    return array_column(array_map($f, array_keys($a), $a), 1, 0);
  }

  /** Normalize spaces and commas after and before spaces. */
  public static function normalizeSpaces($name, $singleSpace = ' ', $stripLinebreaks = false)
  {
    $name = str_replace("\xc2\xa0", "\x20", $name);
    $name = trim($name);
    $name = str_replace("\r\n", "\n", $name);
    if ($stripLinebreaks) {
      $name = str_replace("\n", $singleSpace, $name);
    }
    $name = preg_replace('/\h+/u', $singleSpace, $name);
    $name = preg_replace('/\h+([\n.,;:?!])/u', '$1', $name);

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
   * Convert the given HTML string to markdown. Return null if it is empty.
   *
   * @param null|string $html Input HTML or null.
   *
   * @return null|string Output markdown or null.
   */
  public static function htmlToMarkDown(?string $html):?string
  {
    if (!empty($html)) {
      if (empty(self::$markDownConverter)) {
        self::$markDownConverter = new HtmlToMarkDown;
      }
      return self::$markDownConverter->convert($html);
    } else {
      return null;
    }
  }

  public static function arraySliceKeys($array, $keys = null)
  {
    if ($keys === null) {
      return is_array($array) ? $array : [];
    }
    if (!is_array($keys)) {
      $keys = [ $keys ];
    }
    if (!is_array($array)) {
      return [];
    } else {
      return array_intersect_key($array, array_fill_keys($keys, '1'));
    }
  }

  /**
   * A preg_explode() wrapper with some extra features.
   *
   * @param string $delim The delimiter string.
   *
   * @param null|string $string The string to split.
   *
   * @param int $flags Default is self::OMIT_EMPTY_FIELDS|self::ESCAPED
   * - self::OMIT_EMPTY_FIELDS Omit empty fields from the output array
   * - self::TRIM Trim white-space around the delimiter
   * - self::ESCAPED Ignore escaped delimiters and replace escaped delimiters
   *   in the output array.. See paramterer $escape.
   *
   * @param string $escape The escape character when self::ESCAPED is set.
   *
   * @return array
   */
  static public function explode(string $delim, ?string $string, int $flags = self::OMIT_EMPTY_FIELDS|self::ESCAPED, string $escape = '\\'):array
  {
    if (empty($flags)) {
      return explode($delim, $string);
    }
    $pregFlags = ($flags & self::OMIT_EMPTY_FIELDS) ? PREG_SPLIT_NO_EMPTY : 0;
    $trimExpr = ($flags & self::TRIM) ? '\s*' : '';
    if (($flags & self::ESCAPED) && !empty($escape)) {
      return
        self::unescapeDelimiter(
          preg_split('/'.$trimExpr.preg_quote($escape, '/').'.'.'(*SKIP)(*FAIL)|'.preg_quote($delim, '/').$trimExpr.'/s', $string, -1, $pregFlags),
          $delim,
          $escape);
    } else {
      return preg_split('/'.$trimExpr.preg_quote($delim, '/').$trimExpr.'/', $string, -1, $pregFlags);
    }
  }

  /**
   * Counter-part to self::explode()
   */
  static public function implode(string $delim, array $array, int $flags = self::ESCAPED, string $escape = '\\'):string
  {
    if ($flags & self::ESCAPED) {
      $array = self::escapeDelimiter($array, $delim, $escape);
    }
    return implode($delim, $array);
  }

  /*
   * Escape delimiters in the given value.
   *
   * @param string|array $value
   *
   * @param string $delim
   *
   * @param string $escape
   *
   * @return string|array
   */
  static public function escapeDelimiter($value, string $delim, string $escape = '\\')
  {
    return str_replace(
        [ $escape, $delim ],
        [ $escape . $escape, $escape . $delim ],
        $value);
  }

  /*
   * Un-escape delimiters in the given value.
   *
   * @param string|array $value
   *
   * @param string $delim
   *
   * @param string $escape
   *
   * @return string|array
   */
  static public function unescapeDelimiter($value, string $delim, string $escape = '\\')
  {
    return str_replace(
      [ $escape . $escape, $escape . $delim ],
      [ $escape, $delim ],
      $value);
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
   *
   * Only the first $keyDelimiter is taken into account. This means
   * that it is not necessary to explode the key-delimiter.
   */
  static public function explodeIndexed(?string $data, $default = null, string $delimiter = ',', string $keyDelimiter = ':', string $escapeChar = '\\'):array
  {
    if (empty($data)) {
      return [];
    }
    $matrix = array_map(
      function($row) use ($keyDelimiter, $default) {
        $row = explode($keyDelimiter, $row, 2);
        if (!isset($row[1]) || $row[1] === '') {
          $row[1] = $default;
        }
        return $row;
      },
      self::explode($delimiter, $data, self::TRIM|self::OMIT_EMPTY_FIELDS|self::ESCAPED, $escapeChar));
    return array_column($matrix, 1, 0);
  }

  /**
   * Explode a string of the form
   * ```
   * A:B,C:D,E,C:F...
   * ```
   * into an array of the shape
   * ```
   * [
   *   A => [ B ],
   *   C => [ D, F ],
   *   E => [ $default ],
   *   ...
   * ]
   * ```
   */
  static public function explodeIndexedMulti(?string $data, $default = null, string $delimiter = ',', string $keyDelimiter = ':'):array
  {
    $matrix = [];
    foreach (self::explode($delimiter, $data, self::TRIM|self::OMIT_EMPTY_FIELDS) as $item) {
      $row = explode($keyDelimiter, $item, 2);
      if (!isset($row[1]) || $row[1] === '') {
        $row[1] = $default;
      }
      $matrix[$row[0]][] = $row[1];
    }
    return $matrix;
  }

  /**
   * Undo self::explodeIndexedMulti().
   */
  static public function implodeIndexedMulti(?array $data, string $delimiter = ',', string $keyDelimiter = ':'):string
  {
    if (empty($data)) {
      return '';
    }
    $result = [];
    foreach ($data as $key => $values) {
      foreach ($values as &$value) {
        $value = $key.$keyDelimiter.$value;
      }
      $result[] = implode($delimiter, $values);
    }
    return implode($delimiter, $result);
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

  /**
   * Locale-aware "human readable" file-size.
   *
   * @param int $bytes The raw value in bytes.
   *
   * @param null|string $locale Defaults to 'en_US_POSIX'
   *
   * @param bool $binary Use MiB etc. if true, other decimal system.
   */
  public static function humanFileSize(int $bytes, string $locale = null, bool $binary = true, int $digits = 2)
  {
    $prefix = [ '', 'K', 'M', 'G', 'T', 'P', 'E', 'Z' ];
    $locale = $locale ?? 'en_US_POSIX';
    $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
    $multiplier = $binary ? 1024.0 : 1000.0;
    $bytes = (float)$bytes;
    $exp = 0;
    while ($exp < count($prefix) && $bytes > $multiplier) {
      ++$exp;
      $bytes /= $multiplier;
    }
    $postfix = $prefix[$exp];
    if ($binary && $postfix !== '') {
      $postfix .= 'i';
    }
    $postfix .= 'B';
    return $fmt->format(round($bytes, $digits)) . ' ' . $postfix;
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
   * Dumps a string into a traditional hex dump for programmers,
   * in a format similar to the output of the BSD command hexdump -C file.
   * The default result is a string.
   * Supported options:
   * <pre>
   *   line_sep        - line seperator char, default = "\n"
   *   bytes_per_line  - default = 16
   *   pad_char        - character to replace non-readble characters with, default = '.'
   * </pre>
   *
   * @param string $string
   * @param array $options
   * @param string|array
   */
  public static function hexDump($string, array $options = null)
  {
    if (!is_scalar($string)) {
      throw new InvalidArgumentException('$string argument must be a string');
    }
    if (!is_array($options)) {
      $options = array();
    }
    $line_sep       = isset($options['line_sep'])   ? $options['line_sep']          : "\n";
    $bytes_per_line = @$options['bytes_per_line']   ? $options['bytes_per_line']    : 16;
    $pad_char       = isset($options['pad_char'])   ? $options['pad_char']          : '.'; # padding for non-readable characters

    $text_lines = str_split($string, $bytes_per_line);
    $hex_lines  = str_split(bin2hex($string), $bytes_per_line * 2);

    $offset = 0;
    $output = array();
    $bytes_per_line_div_2 = (int)($bytes_per_line / 2);
    foreach ($hex_lines as $i => $hex_line) {
      $text_line = $text_lines[$i];
      $output [] =
                sprintf('%08X',$offset) . '  ' .
                str_pad(
                  strlen($text_line) > $bytes_per_line_div_2
                  ?
                  implode(' ', str_split(substr($hex_line,0,$bytes_per_line),2)) . '  ' .
                  implode(' ', str_split(substr($hex_line,$bytes_per_line),2))
                  :
                  implode(' ', str_split($hex_line,2))
                  , $bytes_per_line * 3) .
                '  |' . preg_replace('/[^\x20-\x7E]/', $pad_char, $text_line) . '|';
      $offset += $bytes_per_line;
    }
    $output []= sprintf('%08X', strlen($string));
    return @$options['want_array'] ? $output : join($line_sep, $output) . $line_sep;
  }

  /**
   * Unset all array elements with value $value.
   *
   * @param array $hayStack The array to modify.
   *
   * @param mixed $value The value to remove.
   *
   * @return int The number of array slots that have been unset. As
   * the values need not be unique this can be any non-negative
   * integer.
   */
  public static function unsetValue(array &$hayStack, $value):int
  {
    $numUnset = 0;
    while (($key = array_search($value, $hayStack)) !== false) {
      unset($hayStack[$key]);
      ++$numUnset;
    }
    return $numUnset;
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
