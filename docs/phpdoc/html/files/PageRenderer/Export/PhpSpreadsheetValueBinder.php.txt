<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2021, 2022 Claus-Justus Heine
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
namespace OCA\CAFEVDB\PageRenderer\Export;

use Html2Text\Html2Text;
use PhpOffice\PhpSpreadsheet;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Special value-binder class with tweaks the standard
 * implementation from PHPExcel a little bit. In particular: EURO,
 * date-formats, some "germanisms".
 */
class PhpSpreadsheetValueBinder extends PhpSpreadSheet\Cell\DefaultValueBinder implements PhpSpreadSheet\Cell\IValueBinder
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var IL10N */
  private $l;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var FuzzyInputService */
  private $fuzzyInputService;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10n $l10n,
    IURLGenerator $urlGenerator,
    FuzzyInputService $fuzzyInputService
  ) {
    //parent::__construct();
    $this->logger = $logger;
    $this->l10n = $l10n;
    $this->urlGenerator = $urlGenerator;

    $this->l = $l10n;
    $this->fuzzyInputService = $fuzzyInputService;
  }
  // phpcs:enable

  /**
   * Bind value to a cell
   *
   * @param PhpSpreadsheet\Cell\Cell $cell Cell to bind value to.
   *
   * @param mixed $value Value to bind in cell.
   *
   * @return bool
   */
  public function bindValue(PhpSpreadsheet\Cell\Cell $cell, mixed $value = null)
  {
    // sanitize UTF-8 strings
    if (is_string($value)) {
      $value = PhpSpreadsheet\Shared\StringHelper::sanitizeUTF8($value);
    }

    // Find out data type
    $dataType = parent::dataTypeForValue($value);

    // Copied over with bug-fixes: currency values can be
    // negative. And we want to support €, of course.
    if ($dataType === PhpSpreadsheet\Cell\DataType::TYPE_STRING && !$value instanceof PhpSpreadsheet\RichText\RichText) {
      // Check for currency
      $monetary = $this->fuzzyInputService->parseCurrency($value, true);
      if ($monetary !== false) {
        switch ($monetary['currency']) {
          case '€':
            // Set value
            $cell->setValueExplicit($monetary['amount'], PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            // Set style
            $format = PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
            //$format = '#.##0,00 [$€];[ROT]-#.##0,00 [$€]';
            $format = '#,##0.00 [$€]';
            $format = '#,##0.00 [$€];[RED]-#,##0.00 [$€]';
            $cell->getWorksheet()->getStyle($cell->getCoordinate())
              ->getNumberFormat()->setFormatCode($format);
            return true;
          case '$':
            $cell->setValueExplicit($monetary['amount'], PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            // Set style
            $cell->getWorksheet()->getStyle($cell->getCoordinate())
              ->getNumberFormat()->setFormatCode(PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            return true;
        }
      }

      // Interpret some basic html
      $hrefre = '#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#i';
      if (preg_match_all($hrefre, $value, $matches, PREG_SET_ORDER)) {
        $absUrls = [];
        $urlUuid = Uuid::create();
        $absUrlCount = 0;
        foreach ($matches as &$match) {
          $url = $match[1];
          if ($url[0] == '/' || !str_starts_with($url, 'mailto:')) {
            $absUrl = $this->urlGenerator->getAbsoluteURL($url);
            $match[1] = $absUrl;
            $absUrlKey = $urlUuid . '_' . $absUrlCount;
            $absUrls[$absUrlKey] = $absUrl;
            $value = str_replace($url, $absUrlKey, $value);
            $absUrlCount++;
          }
          if (str_starts_with($url, 'mailto:')) {
            // strip potential query parameters
            $queryPos = strrpos($url, '?');
            if ($queryPos !== false) {
              $match[1] = substr($url, 0, $queryPos);
              str_replace($url, $match[1], $value);
            }
          }
        }
        $value = str_replace(array_keys($absUrls), array_values($absUrls), $value);
        $cell->getHyperlink()->setUrl($matches[0][1]);
        if (count($matches) == 1) {
          $cell->setValueExplicit($matches[0][2], PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        } else {
          $h2t = new Html2Text($value, [ 'width' => 0 ]);
          $value = trim($h2t->get_text());
          $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        return true;
      }

      // Handle remaining stuff by html2text
      $h2t = new Html2Text($value, [ 'width' => 0 ]);
      $value = trim($h2t->get_text());

      // Well, 'ja' or 'nein' ... should also count for truth values, maybe
      switch (strtoupper($value)) {
        case strtoupper($this->l->t('yes')):
        case strtoupper($this->l->t('true')):
          $value = PhpSpreadsheet\Calculation\Calculation::getTRUE();
          break;
        case strtoupper($this->l->t('no')):
        case strtoupper($this->l->t('false')):
          $value = PhpSpreadsheet\Calculation\Calculation::getFALSE();
          break;
      }

      //        Test for booleans using locale-setting
      if ($value == PhpSpreadsheet\Calculation\Calculation::getTRUE()) {
        $cell->setValueExplicit(true, PhpSpreadsheet\Cell\DataType::TYPE_BOOL);
        return true;
      } elseif ($value == PhpSpreadsheet\Calculation\Calculation::getFALSE()) {
        $cell->setValueExplicit(false, PhpSpreadsheet\Cell\DataType::TYPE_BOOL);
        //$cell->setValueExplicit('=FALSE()', PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
        return true;
      }

      // Check for number in scientific format
      if (preg_match('/^'.PhpSpreadsheet\Calculation\Calculation::CALCULATION_REGEXP_NUMBER.'$/', $value)) {
        $cell->setValueExplicit((float) $value, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        return true;
      }

      // Check for fraction
      if (false) {
        //Nope, phone-number look like fractions but are not
        if (preg_match('/^([+-]?) *([0-9]*)\s?\/\s*([0-9]*)$/', $value, $matches)) {
          // Convert value to number
          $value = $matches[2] / $matches[3];
          if ($matches[1] == '-') {
            $value = 0 - $value;
          }
          $cell->setValueExplicit((float) $value, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
          // Set style
          $cell->getWorksheet()->getStyle($cell->getCoordinate())
            ->getNumberFormat()->setFormatCode('??/??');
          return true;
        } elseif (preg_match('/^([+-]?)([0-9]*) +([0-9]*)\s?\/\s*([0-9]*)$/', $value, $matches)) {
          // Convert value to number
          $value = $matches[2] + ($matches[3] / $matches[4]);
          if ($matches[1] == '-') {
            $value = 0 - $value;
          }
          $cell->setValueExplicit((float) $value, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
          // Set style
          $cell->getWorksheet()->getStyle($cell->getCoordinate())
            ->getNumberFormat()->setFormatCode('# ??/??');
          return true;
        }
      }

      // Check for percentage
      if (preg_match('/^\-?[0-9]*\.?[0-9]*\s?\%$/', $value)) {
        // Convert value to number
        $value = (float) str_replace('%', '', $value) / 100;
        $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Set style
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getNumberFormat()->setFormatCode(PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        return true;
      }

      // Check for time without seconds e.g. '9:45', '09:45'
      if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d$/', $value)) {
        // Convert value to number
        list($hours, $minutes) = Util::explode(':', $value);
        $days = $hours / 24 + $minutes / 1440;
        $cell->setValueExplicit($days, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Set style
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getNumberFormat()->setFormatCode(PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_TIME3);
        return true;
      }

      // Check for time with seconds '9:45:59', '09:45:59'
      if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
        // Convert value to number
        list($hours, $minutes, $seconds) = Util::explode(':', $value);
        $days = $hours / 24 + $minutes / 1440 + $seconds / 86400;
        // Convert value to number
        $cell->setValueExplicit($days, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Set style
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getNumberFormat()->setFormatCode(PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_TIME4);
        return true;
      }

      // Check for datetime, e.g. '2008-12-31', '2008-12-31 15:59', '2008-12-31 15:59:10'
      $date = PhpSpreadsheet\Shared\Date::stringToExcel($value);
      if ($date !== false) {
        // Convert value to number
        $cell->setValueExplicit($date, PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Determine style. Either there is a time part or not. Look for ':'
        if (strpos($value, ':') !== false) {
          $formatCode = 'dd.mm.yyyy h:mm:ss';
        } else {
          $formatCode = 'dd.mm.yyyy';
        }
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getNumberFormat()->setFormatCode($formatCode);
        return true;
      }

      // Check for newline character "\n"
      if (strpos($value, "\n") !== false) {
        $cell->setValueExplicit($value, PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        // Set style
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getAlignment()->setWrapText(true);
        $cell->getWorksheet()->getStyle($cell->getCoordinate())
          ->getAlignment()->setHorizontal(PhpSpreadsheet\Style\Alignment::HORIZONTAL_JUSTIFY);
        return true;
      }
    }

    // Not bound yet? Use parent...
    return parent::bindValue($cell, $value);
  }
}
