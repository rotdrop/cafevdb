<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

use NumberFormatter as PhpNumberFormatter;
use NumberToWords\NumberToWords;
use NumberToWords\CurrencyTransformer\CurrencyTransformer;
use NumberToWords\NumberTransformer\NumberTransformer;

use OCP\IL10N;

/**
 * A wrapper around https://github.com/kwn/number-to-words to make it even
 * more convenient.
 */
class NumberFormatter
{
  protected PhpNumberFormatter $numberFormatter;

  protected PhpNumberFormatter $currencyFormatter;

  protected NumberToWords $numberToWords;

  protected CurrencyTransformer $currencyTransformer;

  protected NumberTransformer $numberTransformer;

  protected string $lang;

  protected string $currencyISOCode;

  protected string $currencySymbol;

  /**
   * @var string
   *
   * The localized word for the digitial separator.
   */
  protected string $fractionalSeparatorWord;

  /**
   * @var bool
   *
   * The position of the fractional part in relation to the fractional
   * separator word.
   */
  protected bool $fractionalPartLast;

  /**
   * @var bool
   *
   * \true if the fractional part is spelled out in ascending order.
   */
  protected boll $factionalPartAscending;

  /**
   * @param string $locale
   */
  public function __construct(protected string $locale)
  {
    $this->provideLanguage($locale);
  }

  /**
   * @param string $locale
   *
   * @return NumberFormatter
   */
  public function setLocale(string $locale):NumberFormatter
  {
    $this->locale = $locale;

    $this->provideLanguage($locale);

    return $this;
  }

  /**
   * Initialize locale dependent internal state from the given locale
   * string.
   *
   * @param string $locale
   *
   * @return void
   */
  protected function provideLanguage(string $locale):void
  {
    $this->lang = locale_get_primary_language($locale);
    $this->numberToWords = new NumberToWords();
    $this->numberTransformer = $this->numberToWords->getNumberTransformer($this->lang);
    $this->currencyTransformer = $this->numberToWords->getCurrencyTransformer($this->lang);

    // KWN has some bugs


    $this->numberFormatter = new PhpNumberFormatter($locale, PhpNumberFormatter::DECIMAL);
    $this->numberSpeller = new PhpNumberFormatter($locale, PhpNumberFormatter::SPELLOUT);

    $this->currencyFormatter = new PhpNumberFormatter($locale, PhpNumberFormatter::CURRENCY);
    $this->currencyIsoCode = $this->currencyFormatter->getTextAttribute(PhpNumberFormatter::CURRENCY_CODE);
    $this->currencySymbol = $this->currencyFormatter->getSymbol(PhpNumberFormatter::CURRENCY_SYMBOL);

    // The following may not work for all locales, the idea is to "ask" the
    // PHP NumberFormatter class about the word of the fractional separator
    // and the ordering of the spelled out fractional part.

    $nullWord = $this->numberSpeller->format(0);
    $oneWord = $this->numberSpeller->format(1);
    $zeroDotOne = explode(' ', $this->numberSpeller->format(0.1));
    if (count($zeroDotOne) != 3) {
      throw new UnexpectedValueException('0.1 to be spelled out as a three digit word, but got "' . implode(' ', $zeroDotOne) . '".');
    }
    if ($zeroDotOne[0] == $nullWord && $zeroDotOne[2] == $oneWord) {
      $this->fractionalSeparatorWord = $zeroDotOne[1];
      $this->fractionalPartLast = true;
    } elseif ($zeroDotOne[0] == $nullWord && $zeroDotOne[2] == $oneWord) {
      $this->fractionalSeparatorWord = $zeroDotOne[1];
      $this->fractionalPartLast = false;
    } else {
      throw new UnexpectedValueException('Expected the fractional part to be located either in front or after the decimal separator word.');
    }
    $zeroDotZeroOne = explode(' ', $this->numberSpeller->format(0.01));
    if (count($zeroDotOne) < 3) {
      throw new UnexpectedValueException('Expected 0.01 to be spelled out with at least three words, but got "' . implode(' ', $zeroDotZeroOne) . '".');
    }
    $this->fractionalPartAscending =
      $zeroDotZeroOne[0] == $this->fractionalSeparatorWord
      || ($zeroDotZeroOne[0] == $nullWord
          && $zeroDotZeroOne[1] == $this->fractionalSeparatorWord);
  }

  /**
   * Convert the given currency value to words using the provided or the
   * implied three letter currency code.
   *
   * @param float $value The value to convert.
   *
   * @param null|string $currencyISOCode The currency ISO code to use. If \null
   * use the currency code implied by the locale setting.
   *
   * @return string Textual representation of the given currency value.
   */
  public function currencyToWords(float $value, ?string $currencyISOCode = null):string
  {
    $currencyISOCode = $currencyISOCode ?? $this->currencyIsoCode;
    $words = $this->currencyTransformer->toWords((int)($value * 100.0), $currencyISOCode);
    return $this->kwnSanitizer($words, $currencyISOCode);
  }

  /**
   * Convert the given currency value to a localize number representation the provided
   * or implied three letter currency code.
   *
   * @param float $value The value to convert.
   *
   * @param null|string $currencyISOCode The currency ISO code to use. If \null
   * use the currency code implied by the locale setting.
   *
   * @return string Textual representation of the given currency value.
   */
  public function formatCurrency(float $value, ?string $currencyISOCode = null):string
  {
    return $this->currencyFormatter->formatCurrency((float)$value, $currencyISOCode ?? $this->currencyIsoCode);
  }

  /**
   * Convert the given number to words.
   *
   * @param float $value The value to convert.
   *
   * @param int $minDigits Minimum number of digits to spell out. Defaults to
   * 1.
   *
   * @param int $maxDigits Maximum number of digits to spell out. Defaults to
   * 2.
   *
   * @return string Textual representation of the given currency value.
   */
  public function numberToWords(float $value, int $minDigits = 1, int $maxDigits = 2):string
  {
    return $this->numberToWordsPhp($value, $minDigits, $maxDigits);
  }

  /**
   * Convert the given number to words.
   *
   * @param float $value The value to convert.
   *
   * @param int $minDigits Minimum number of digits to spell out. Defaults to
   * 1.
   *
   * @param int $maxDigits Maximum number of digits to spell out. Defaults to
   * 2.
   *
   * @return string Textual representation of the given currency value.
   */
  public function numberToWordsKWN(float $value, int $minDigits = 1, int $maxDigits = 2):string
  {
    $minDigits = min($minDigits, $maxDigits);
    $maxDigits = max($minDigits, $maxDigits);
    $value = round($value, $maxDigits);
    $factor = pow(10.0, $maxDigits);
    $digitsPart = abs((int)($value * $factor) % $factor);
    $majorPart = (int)$value;
    $majorPartWords = $this->numberTransformer->toWords($majorPart);
    $digits = [];
    while ($maxDigits-- > 0) {
      $digits[] = $digitsPart % 10;//$this->numberTransformer->toWords($digitsPart % 10);
      $digitsPart /= 10;
    }
    // strip trailing zeros
    while (true) {
      $value = reset($digits);
      if ($value > 0 || count($digits) <= $minDigits) {
        break;
      }
      array_shift($digits);
    }
    if ($this->fractionalPartAscending) {
      $digits = array_pad(array_reverse($digits), $minDigits, 0);
    } else {
      $digits = array_reverse(array_pad(array_reverse($digits), $minDigits, 0));
    }
    $digits = array_map(fn(int $digit) => $this->numberTransformer->toWords($digit), $digits);
    if ($this->fractionalPartLast) {
      $parts = [ $majorPartWords ];
      if (count($digits) > 0) {
        $parts[] = $this->fractionalSeparatorWord;
        $parts = array_merge($parts, $digits);
      }
    } else {
      if (count($digits) > 0) {
        $parts = $digits;
        $parts[] = $this->fractionalSeparatorWord;
      }
      $parts[] = $majorPartWords;
    }
    return $this->kwnSanitizer(implode(' ', $parts));
  }

  /**
   * Work around some shortcomings of kwn/number-to-words for some known
   * issues.
   *
   * @param string $words
   *
   * @param null|string $currencyISOCode The currency ISO code to use. If \null
   * use the currency code implied by the locale setting.
   *
   * @return string
   */
  protected function kwnSanitizer(string $words, ?string $currencyISOCode = null):string
  {
    switch ($this->lang) {
      case 'de':
        $words = lcfirst($words);
        if ($currencyISOCode !== null) {
          $nullCurrency = $this->currencyTransformer->toWords(0, $currencyISOCode);
          $nullWord = $this->numberTransformer->toWords(0);
          $currencyWords = trim(str_replace($nullWord, '', $nullCurrency));
          $parts = [];
          if (str_starts_with($words, 'minus')) {
            $parts[] = 'minus';
            $words = substr($words, strlen('minus'));
          }
          $currencyPos = strpos($words, $currencyWords);
          $number = trim(substr($words, 0, $currencyPos));
          $rest = trim(substr($words, $currencyPos));
          if (str_starts_with($number, 'eins')) {
            $number = 'ein' . substr($number, strlen('eins'));
          }
          $number = str_replace(' ', '', $number);
          if (str_ends_with($rest, 'cent')) {
            $rest = substr($rest, 0, -strlen('cent')) . 'Cent';
          }
          $parts[] = $number;
          $parts[] = $rest;
          $words = implode(' ', $parts);
        }
        break;
      default:
        break;
    }
    return $words;
  }

  /**
   * Convert the given number to words.
   *
   * @param float $value The value to convert.
   *
   * @param int $minDigits Minimum number of digits to spell out. Defaults to
   * 1.
   *
   * @param int $maxDigits Maximum number of digits to spell out. Defaults to
   * 2.
   *
   * @return string Textual representation of the given currency value.
   */
  public function numberToWordsPhp(float $value, int $minDigits = 1, int $maxDigits = 2):string
  {
    $minDigits = min($minDigits, $maxDigits);
    $maxDigits = max($minDigits, $maxDigits);
    $value = round($value, $maxDigits);
    $factor = pow(10.0, $maxDigits);
    $value = ((float)(int)$value) + (((int)($value * $factor) % $factor) / $factor);

    $optionalFactor = pow(10.0, $maxDigits - $minDigits + 1);
    $fractionalPart = abs((int)($value * $factor) % $factor);
    $forceDigits = $fractionalPart % $optionalFactor == 0;

    if ($forceDigits) {
      $excessDigit = pow(10.0, -($minDigits + 1));
      $value = $value >= 0 ? $value + $excessDigit : $value - $excessDigit;
    }
    $words = explode(' ', str_replace("\xc2\xad", '', $this->numberSpeller->format($value)));
    if ($forceDigits) {
      $separatorPos = array_search($this->fractionalSeparatorWord, $words);
      if ($this->fractionalPartLast) {
        if ($this->fractionalPartAscending) {
          $words = array_slice($words, 0, $separatorPos + $minDigits + 1);
        } else {
          $words = array_merge(
            array_slice($words, 0, $separatorPos + 1),
            array_slice($words, - $minDigits),
          );
        }
      } else {
        if ($this->fractionalPartAscending) {
          $words = array_merge(
            array_slice($words, 0, $minDigits),
            array_slice($words, -$separatorPos - 1),
          );
        } else {
          $words = array_merge(
            array_slice($words, $separatorPos - $minDigits, $minDigits),
            array_slice($words, -$separatorPos - 1),
          );
        }
      }
    }
    return implode(' ', $words);
  }

  /**
   * Convert the given number to a localize string representation using
   * digits.
   *
   * @param float $value The value to convert.
   *
   * @param int $minDigits Minimum number of digits to spell out. Defaults to
   * 1.
   *
   * @param int $maxDigits Maximum number of digits to spell out. Defaults to
   * 2.
   *
   * @return string Textual representation of the given currency value.
   */
  public function formatNumber(float $value, int $minDigits = 1, int $maxDigits = 2):string
  {
    $minDigits = min($minDigits, $maxDigits);
    $maxDigits = max($minDigits, $maxDigits);
    $this->numberFormatter->setAttribute(PhpNumberFormatter::MIN_FRACTION_DIGITS, $minDigits);
    $this->numberFormatter->setAttribute(PhpNumberFormatter::MAX_FRACTION_DIGITS, $maxDigits);
    return $this->numberFormatter->format((float)$value);
  }
}
