<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
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

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

use PhpOffice\PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Exporter for the instrument insurances. This is special as it must only
 * contain official data and should be in a ready-to-send state in order to
 * just hand it on to the insurance brokers.
 */
class InsuranceSpreadsheetExporter extends AbstractSpreadsheetExporter
{
  use \OCA\CAFEVDB\Traits\SloppyTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  protected const MUSICIAN_KEY = 'musician';
  protected const OBJECT_KEY = 'object';
  protected const START_KEY = 'start';
  protected const DELETED_KEY = 'deleted';
  protected const ACCESSORY_KEY = 'accessory';
  protected const MANUFACTURER_KEY = 'manufacturer';
  protected const YEAR_OF_CONSTRUCTION_KEY = 'year';
  protected const AMOUNT_KEY = 'amount';
  protected const TOTALS_KEY = 'totals';

  protected const EXPORT_COLUMNS = [
    self::MUSICIAN_KEY,
    self::OBJECT_KEY,
    self::START_KEY,
    self::DELETED_KEY,
    self::ACCESSORY_KEY,
    self::MANUFACTURER_KEY,
    self::YEAR_OF_CONSTRUCTION_KEY,
    self::AMOUNT_KEY,
    self::TOTALS_KEY,
  ];

  protected const INPUT_INDEX_MUSICIAN = 0;
  protected const INPUT_INDEX_BILL_TO = self::INPUT_INDEX_MUSICIAN + 1;
  protected const INPUT_INDEX_OWNER = self::INPUT_INDEX_BILL_TO +1;
  protected const INPUT_INDEX_BROKER = self::INPUT_INDEX_OWNER + 1;
  protected const INPUT_INDEX_SCOPE = self::INPUT_INDEX_BROKER + 1;
  protected const INPUT_INDEX_OBJECT = self::INPUT_INDEX_SCOPE + 1;
  protected const INPUT_INDEX_IS_ACCESSORY = self::INPUT_INDEX_OBJECT + 1;
  protected const INPUT_INDEX_MANUFACTURER = self::INPUT_INDEX_IS_ACCESSORY + 1;
  protected const INPUT_INDEX_YEAR_OF_CONSTRUCTION = self::INPUT_INDEX_MANUFACTURER + 1;
  protected const INPUT_INDEX_INSURED_AMOUNT = self::INPUT_INDEX_YEAR_OF_CONSTRUCTION + 1;
  protected const INPUT_INDEX_INSURANCE_RATE = self::INPUT_INDEX_INSURED_AMOUNT + 1;
  protected const INPUT_INDEX_DUE_DATE = self::INPUT_INDEX_INSURANCE_RATE + 1;
  protected const INPUT_INDEX_INSURANCE_FEES = self::INPUT_INDEX_DUE_DATE + 1;
  protected const INPUT_INDEX_INSURANCE_START = self::INPUT_INDEX_INSURANCE_FEES + 1;
  protected const INPUT_INDEX_INSURANCE_END = self::INPUT_INDEX_INSURANCE_START + 1;

  /** @var PageRenderer\PMETableViewBase */
  protected $renderer;

  /** @var InstrumentInsuranceService */
  protected $insuranceService;

  /** @var FuzzyInputService */
  protected $fuzzyInputService;

  /** @var array */
  protected $spreadSheetColumns;

  /** @var string */
  protected $lastColumnAddress;

  /** @var string */
  protected $preLastColumnAddress;

  /**
   * Construct a spread-sheet exporter for selected tables.
   *
   * @param PageRenderer\InstrumentInsurances $renderer
   * Underlying renderer, see self::fillSheet().
   *
   * @param InstrumentInsuranceService $insuranceService
   *
   * @param FuzzyInputService $fuzzyInputService
   *
   * @param FontService $fontService
   */
  public function __construct(
    PageRenderer\InstrumentInsurances $renderer,
    InstrumentInsuranceService $insuranceService,
    FuzzyInputService $fuzzyInputService,
    FontService $fontService,
  ) {
    parent::__construct($renderer->configService(), $fontService);
    $this->renderer = $renderer;
    $this->insuranceService = $insuranceService;
    $this->fuzzyInputService = $fuzzyInputService;

    $this->preLastColumnAddress = chr(ord('A') + count(self::EXPORT_COLUMNS) - 2);
    $this->lastColumnAddress = chr(ord('A') + count(self::EXPORT_COLUMNS) - 1);

    $chars = range('A', $this->lastColumnAddress);
    $this->spreadSheetColumns = array_combine(self::EXPORT_COLUMNS, $chars);
  }

  /**
   * Implement parent::fillSheet() for exactly
   *
   * - template all-musicians, class PageRenderer\Musicians
   * - template project-participants, class PageRenderer\ProjectParticipants
   * - template sepa-bank-accounts, class PageRenderer\SepaBankAccounts
   *
   * All other renderers are rejected by throwing an exception,
   * although a couple of other tables should also work out of the box.
   *
   * @param PhpSpreadsheet\Spreadsheet $spreadSheet Spread-sheet to be filled.
   *
   * @param array $meta An array with at least the keys 'creator',
   * 'email', 'date'.
   *
   * @return array
   * ```
   * [
   *   'creator' => STRING,
   *   'email' => STRING,
   *   'date' => STRING,
   *   'name' => STRING,
   * ]
   * ```
   *
   * @todo Still quite messy.
   */
  public function fillSheet(PhpSpreadsheet\Spreadsheet $spreadSheet, array $meta):array
  {
    $renderer = $this->renderer; // short-cut
    $sheet = $spreadSheet->getActiveSheet();
    // $date = $meta['date'];

    $meta['name'] = $this->l->t('Instrument Insurances');

    // $template = $this->renderer->template();

    /* Export the table, create extra lines for each musician with its
     * total insurance amount and one extra row at the end which states
     * the over-all total insurance amount. Generate one sheet for each
     * broker-scope pair.
     */
    $brokerScope   = false;
    $musician      = false;
    $musicianTotal = 0.0;
    $musicianNextTotal = 0.0;
    $total         = 0.0;
    $nextTotal     = 0.0;
    $numRecords    = 0;

    $headerLine  = [
      self::MUSICIAN_KEY => $this->l->t('Musician'),
      self::OBJECT_KEY => $this->l->t('Insured Object'),
      self::START_KEY => $this->l->t('Start Date'),
      self::DELETED_KEY => $this->l->t('End Date'),
      self::ACCESSORY_KEY => $this->l->t('Accessory'),
      self::MANUFACTURER_KEY => $this->l->t('Manufacturer'),
      self::YEAR_OF_CONSTRUCTION_KEY => $this->l->t('Year of Construction'),
      self::AMOUNT_KEY => $this->l->t('Insurance Amount'),
      self::TOTALS_KEY => $this->l->t('Musician Insurance Total'),
    ];

    $brokerNames = $this->insuranceService->getBrokers();
    $rates = $this->insuranceService->getRates(true);

    $renderer->navigation(false); // inhibit navigation elements
    $renderer->render(false); // dry-run, prepare export

    $nowDate = new DateTimeImmutable;

    $dueDate = null;
    $lastDueDate = null;
    $utc = new DateTimeZone('UTC');

    $newMusician = false;

    $renderer->export(
      // Cell-data filter
      function ($i, $j, $cellData) {
        $cellData = trim($cellData);
        // filter out dummy dates, I really should clean up on the data-base level
        if ($cellData == '01.01.1970') {
          $cellData = '';
        }
        $cellData = html_entity_decode($cellData, ENT_COMPAT|ENT_HTML401, 'UTF-8');
        return $cellData;
      },
      function (
        $i,
        $lineData
      ) use (
        &$sheet,
        &$spreadSheet,
        &$offset,
        &$headerOffset,
        &$rowCnt,
        $headerLine,
        &$brokerScope,
        &$musician,
        &$musicianTotal,
        &$musicianNextTotal,
        &$total,
        &$nextTotal,
        &$numRecords,
        &$newMusician,
        $meta,
        $brokerNames,
        $rates,
        $nowDate,
        &$dueDate,
        &$lastDueDate,
        $utc,
      ) {
        if ($i == 1) {
          // $this->dumpRow($headerLine, $sheet, $i, $offset, $rowCnt, true);
          return;
        }

        $exportData = [];
        for ($k = 0; $k < 8; $k++) {
          $exportData[$k] = '';
        }

        if ($brokerScope === false) { // setup
          $musician    = strip_tags($lineData[self::INPUT_INDEX_MUSICIAN]);
          // $billTo      = $lineData[1];
          $broker      = $lineData[self::INPUT_INDEX_BROKER];
          $scope       = $lineData[self::INPUT_INDEX_SCOPE];
          $brokerScope = $broker . $scope;
          $newMusician = true;

          $sheet->setTitle($this->ellipsizeFirst($broker, $scope, PhpSpreadsheet\Worksheet\Worksheet::SHEET_TITLE_MAXIMUM_LENGTH, '; '));

          $dueDate = self::convertToTimezoneDate($rates[$brokerScope]['due'], $utc);
          $lastDueDate = $dueDate->modify('-1 year'); // starting date of insurance period
          $dueDate = $dueDate->modify('- 1 day'); // ending date of insurance period

          $headerOffset = $this->generateHeader($sheet, $meta, $rates[$brokerScope], $brokerNames[$broker], $nowDate);

          $offset = $headerOffset;
          $rowCnt = 0;
          $this->dumpRow($headerLine, $sheet, 1, $offset, $rowCnt, true);
        } else {

          $broker   = $lineData[self::INPUT_INDEX_BROKER];
          $scope    = $lineData[self::INPUT_INDEX_SCOPE];
          $newScope = $broker . $scope;

          $thisLineMusician = strip_tags($lineData[self::INPUT_INDEX_MUSICIAN]);
          if ($musician != $thisLineMusician || $newScope != $brokerScope) {
            $this->dumpMusicianTotal($sheet, $i, $offset++, $rowCnt, $musicianTotal, $musician);
            // if ($musicianTotal != $musicianNextTotal) {
            //   $this->dumpMusicianNextTotal($sheet, $i, $offset++, $rowCnt, $musicianNextTotal, $musician, $dueDate);
            // }
            $total += $musicianTotal;
            $nextTotal += $musicianNextTotal;
            $musicianTotal = 0.0;
            $musicianNextTotal = 0.0;
            $newMusician = true;
            $musician = $thisLineMusician;
          }

          if ($newScope != $brokerScope) {
            $this->dumpTotal($sheet, $i, $offset, $rowCnt, null);
            $this->dumpTotal($sheet, $i + 1, $offset, $rowCnt, $total);
            if ($nextTotal != $total) {
              $this->dumpTotal($sheet, $i + 2, $offset, $rowCnt, null);
              $this->dumpNextTotal($sheet, $i + 3, $offset, $rowCnt, $nextTotal, $dueDate);
            }
            $total = 0.0;
            $nextTotal = 0.0;

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex($spreadSheet->getSheetCount() - 1);
            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle($this->ellipsizeFirst($broker, $scope, PhpSpreadsheet\Worksheet\Worksheet::SHEET_TITLE_MAXIMUM_LENGTH, '; '));
            $brokerScope = $newScope;

            $dueDate = self::convertToTimezoneDate($rates[$brokerScope]['due'], $utc);
            $lastDueDate = $dueDate->modify('-1 year'); // starting date of insurance period
            $dueDate = $dueDate->modify('- 1 day'); // ending date of insurance period

            $headerOffset = $this->generateHeader($sheet, $meta, $rates[$brokerScope], $brokerNames[$broker], $nowDate);

            $offset = $headerOffset - $i + 2;
            $rowCnt = 0;

            $this->dumpRow($headerLine, $sheet, $i - 1, $offset, $rowCnt, true);
          }
        }

        $exportData[self::DELETED_KEY] = $lineData[self::INPUT_INDEX_INSURANCE_END];
        if (!empty($exportData[self::DELETED_KEY])) {
          $endDate = self::convertToTimezoneDate(self::convertToDateTime($exportData[self::DELETED_KEY]), $utc);
        } else {
          $endDate = null;
        }

        if ($endDate !== null && $endDate < $lastDueDate) {
          // ended before current insurance year, so skip it
          $this->logInfo('SKIPPING ' . $rowCnt . ' ' . $i);
          --$offset;
          return;
        }

        $exportData[self::START_KEY] = $lineData[self::INPUT_INDEX_INSURANCE_START];
        $startDate = self::convertToTimezoneDate(self::convertToDateTime($exportData[self::START_KEY]), $utc);

        if ($startDate < $lastDueDate) {
          // only note start-dates within or after the current period
          $exportData[self::START_KEY] = '';
        }

        $exportData[self::MUSICIAN_KEY] = $newMusician ? $musician : '';
        $exportData[self::OBJECT_KEY] = $lineData[self::INPUT_INDEX_OBJECT];
        $exportData[self::ACCESSORY_KEY] = $lineData[self::INPUT_INDEX_IS_ACCESSORY];
        $exportData[self::MANUFACTURER_KEY] = $lineData[self::INPUT_INDEX_MANUFACTURER];
        $exportData[self::YEAR_OF_CONSTRUCTION_KEY] = $lineData[self::INPUT_INDEX_YEAR_OF_CONSTRUCTION];
        $exportData[self::AMOUNT_KEY] = $lineData[self::INPUT_INDEX_INSURED_AMOUNT];
        $exportData[self::TOTALS_KEY] = '';

        $monetary = $this->fuzzyInputService->parseCurrency($lineData[self::INPUT_INDEX_INSURED_AMOUNT]);
        if ($monetary !== false) {
          if ($startDate <= $dueDate) {
            $musicianTotal += $monetary['amount'];
          }
          if ($endDate === null || $endDate > $dueDate) {
            $musicianNextTotal += $monetary['amount'];
          }
        }

        $this->dumpRow($exportData, $sheet, $i, $offset, $rowCnt);

        $numRecords = $i + 1;

        $newMusician = false;
      });

    $this->dumpMusicianTotal($sheet, $numRecords, $offset++, $rowCnt, $musicianTotal, $musician);
    // if ($musicianTotal != $musicianNextTotal) {
    //   $this->dumpMusicianNextTotal($sheet, $numRecords, $offset++, $rowCnt, $musicianNextTotal, $musician, $dueDate);
    // }
    $total += $musicianTotal;
    $nextTotal += $musicianNextTotal;
    $musicianTotal = 0.0;
    $musicianNextTotal = 0.0;

    // Then also dump the total insurance amount for the last sheet:
    $this->dumpTotal($sheet, $numRecords, $offset, $rowCnt, null);
    $this->dumpTotal($sheet, $numRecords + 1, $offset, $rowCnt, $total);
    if ($nextTotal != $total) {
      $this->dumpTotal($sheet, $numRecords + 2, $offset, $rowCnt, null);
      $this->dumpNextTotal($sheet, $numRecords + 3, $offset, $rowCnt, $nextTotal, $dueDate);
    }
    $total = 0.0;
    $nextTotal = 0.0;

    for ($sheetIdx = 0; $sheetIdx < $spreadSheet->getSheetCount(); $sheetIdx++) {
      $spreadSheet->setActiveSheetIndex($sheetIdx);
      $sheet = $spreadSheet->getActiveSheet();

      $sheet->getStyle($sheet->calculateWorkSheetDimension())->applyFromArray([
        'borders' => [
          'outline' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
        ],
      ]);

      $sheet->getStyle('A' . (1 + $headerOffset) . ':' . $this->lastColumnAddress . $sheet->getHighestRow())->applyFromArray([
        'borders' => [
          'allBorders' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
        ],
      ]);

      // Make the header a little bit prettier

      // Set wrap-text for the header line, width and height calculations are done by the parent class.
      $sheet->getStyle('A' . (1 + $headerOffset) . ':' . $this->lastColumnAddress . (1 + $headerOffset))->getAlignment()->setWrapText(true);

      $sheet->getStyle('A' . (1 + $headerOffset) . ':' . $this->lastColumnAddress . (1 + $headerOffset))->applyFromArray([
        'font' => [
          'bold' => true
        ],
        'alignment' => [
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
          'wrapText' => true,
        ],
        'borders' => [
          'allBorders' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
          'bottom' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_DOUBLE,
          ],
        ],
        'fill' => [
          'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => [
            'argb' => 'FFadd8e6',
          ],
        ],
      ]);

      /*
       *
       ***************************************************************************/

      /*-***************************************************************************
       *
       * Header fields
       *
       */

      $ptHeight = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($spreadSheet->getDefaultStyle()->getFont());

      $highCol = $this->lastColumnAddress;
      for ($i = 1; $i <= $headerOffset; ++$i) {
        $sheet->mergeCells('A' . $i . ':' . $highCol . $i);
        $sheet->getRowDimension($i)->setRowHeight(-1); // $ptHeight + $ptHeight / 6);
      }

      // Format the mess a little bit
      $sheet->getStyle('A1:' . $highCol . $headerOffset)->applyFromArray([
        'font' => [
          'bold'=> true,
        ],
        'alignment' => [
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
          'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => [
            'argb' => 'FFF0F0F0',
          ],
        ],
      ]);

      // @todo This seem to undo horizonatl alignment above
      $sheet->getStyle('A1:' . $highCol . ($headerOffset - 1))->applyFromArray([
        'alignment' => [
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
      ]);

      $font = clone $spreadSheet->getDefaultStyle()->getFont();
      $fontSize = $font->getSize() * 1.5;
      $font->setSize($fontSize);
      $ptHeight = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($font);
      $sheet->getStyle('A1:' . $highCol . '1')->applyFromArray([
        'font' => [
          'bold' => true,
          'size' => $fontSize,
        ],
      ]);
      $sheet->getRowDimension(1)->setRowHeight($ptHeight + $ptHeight / 6);
    }

    /*
     *
     ***************************************************************************/

    return $meta;
  }

  /**
   * @param Worksheet $sheet
   *
   * @param array $meta Meta information like creator, its email etc.
   *
   * @param array $rate Insurance rate data.
   *
   * @param array $broker Broker data.
   *
   * @param DateTimeInterface $nowDate
   *
   * @return int The number generated header lines.
   */
  protected function generateHeader(
    Worksheet $sheet,
    array $meta,
    array $rate,
    array $broker,
    DateTimeInterface $nowDate,
  ):int {
    $utc = new DateTimeZone('UTC');

    $dueDate = self::convertToTimezoneDate($rate['due'], $utc);
    $lastDueDate = $dueDate->modify('-1 year'); // starting date of insurance period
    $dueDate = $dueDate->modify('- 1 day'); // ending date of insurance period

    $humanDueDate = $this->dateTimeFormatter()->formatDate($dueDate, 'medium');
    $humanLastDueDate = $this->dateTimeFormatter()->formatDate($lastDueDate, 'medium');
    $humanDate = $this->dateTimeFormatter()->formatDate($nowDate);

    $cnt = 1;
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Instrument Insurances'));
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Provider') . ': ' . $broker['name'] . ', ' . $broker['address']);
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Policy Number') . ': ' . $rate['policy']);
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Geographical Scope') . ': ' . $rate['scope']);
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Insurance Period') . ': ' . $humanLastDueDate . ' - ' . $humanDueDate);
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Orchestra Contact') . ': ' . $meta['creator'] . ' &lt;' . $meta['email'] . '&gt;');
    $sheet->setCellValue('A' . $cnt++, $this->l->t('Date') . ': ' . $humanDate);

    return $cnt;
  }

  /**
   * @param array $exportData
   *
   * @param Worksheet $sheet
   *
   * @param int $row
   *
   * @param int $offset
   *
   * @param int $rowCnt Mutable.
   *
   * @param bool $header
   *
   * @return void
   */
  private function dumpRow(
    array $exportData,
    Worksheet $sheet,
    int $row,
    int $offset,
    int &$rowCnt,
    bool $header = false,
  ):void {
    $hightLightColumns = [ self::START_KEY ];
    $alertColumns = [ self::DELETED_KEY ];
    $moneyColumns = [
      self::YEAR_OF_CONSTRUCTION_KEY,
      self::AMOUNT_KEY,
      self::TOTALS_KEY,
    ];
    $column = 'A';
    foreach (self::EXPORT_COLUMNS as $columnKey) {
      $cellValue = $exportData[$columnKey];
      $sheet->setCellValue($column . ($row + $offset), $cellValue);
      ++$column;
    }
    if (!$header) {
      ++$rowCnt;
      $sheet->getStyle('A' . ($row + $offset).':'.$this->lastColumnAddress.($row+$offset))->applyFromArray([
        'fill' => [
          'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => [
            'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF',
          ],
        ],
      ]);
      foreach ($moneyColumns as $key) {
        $col = $this->spreadSheetColumns[$key];
        $sheet->getStyle($col . ($row + $offset))->applyFromArray([
          'alignment' => [
            'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
          ],
        ]);
      }
      foreach ($alertColumns as $key) {
        if (!empty($exportData[$key])) {
          $col = $this->spreadSheetColumns[$key];
          $styleArray['font'] = [
            'italic' => true,
          ];
          $italicRange = 'B' . ($row + $offset) . ':' . $this->lastColumnAddress . ($row + $offset);
          $sheet->getStyle($italicRange)->applyFromArray(
            $styleArray,
          );
          $styleArray = [
            'font' => [
              'bold' => true
            ],
            'fill' => [
              'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => [
                'argb' => 'FFFF0000',
              ],
            ],
          ];
          $colorRange = 'B' . ($row + $offset) . ':' . $this->lastColumnAddress . ($row + $offset);
          $sheet->getStyle($colorRange)->applyFromArray($styleArray);
        }
      }
      foreach ($hightLightColumns as $key) {
        if (!empty($exportData[$key])) {
          $col = $this->spreadSheetColumns[$key];
          $styleArray['font'] = [
            'italic' => true,
          ];
          $italicRange = 'B' . ($row + $offset) . ':' . $this->lastColumnAddress . ($row + $offset);
          $sheet->getStyle($italicRange)->applyFromArray(
            $styleArray,
          );
          $styleArray = [
            'font' => [
              'bold' => true
            ],
            'fill' => [
              'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => [
                'argb' => 'FF00FF00',
              ],
            ],
          ];
          $colorRange = 'B' . ($row + $offset) . ':' . $this->lastColumnAddress . ($row + $offset);
          $sheet->getStyle($colorRange)->applyFromArray($styleArray);
        }
      }
    }
  }

  /**
   * @param Worksheet $sheet
   *
   * @param int $row
   *
   * @param int $offset
   *
   * @param int $rowCnt Mutable.
   *
   * @param float $musicianTotal
   *
   * @param string $musician
   *
   * @return void
   */
  private function dumpMusicianTotal(
    Worksheet $sheet,
    int $row,
    int $offset,
    int &$rowCnt,
    float $musicianTotal,
    string $musician,
  ):void {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    $exportData[self::TOTALS_KEY] = $musicianTotal . preg_quote('€');
    // $label = $this->l->t('sub total %s', $musician);
    $exportData[self::MUSICIAN_KEY] = ''; // $label ?? '';
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $spreadSheetRow = $row + $offset;
    $sheet->mergeCells('A' . $spreadSheetRow . ':' . $this->preLastColumnAddress . $spreadSheetRow);
    // $sheet->getStyle('A' . $spreadSheetRow . ':' . $this->lastColumnAddress . $spreadSheetRow)->applyFromArray([
    //   'font' => [
    //     'bold' => true
    //   ],
    //   'alignment' => [
    //     'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
    //     'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    //   ],
    // ]);
  }

  /**
   * @param Worksheet $sheet
   *
   * @param int $row
   *
   * @param int $offset
   *
   * @param int $rowCnt Mutable.
   *
   * @param float $musicianNextTotal
   *
   * @param string $musician
   *
   * @param DateTimeInterface $dueDate
   *
   * @return void
   */
  protected function dumpMusicianNextTotal(
    Worksheet $sheet,
    int $row,
    int $offset,
    int &$rowCnt,
    float $musicianNextTotal,
    string $musician,
    DateTimeInterface $dueDate,
  ):void {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    $exportData[self::TOTALS_KEY] = '';
    $label = $this->l->t('total insured amount %s from %s: %s', [
      $musician,
      $this->dateTimeFormatter()->formatDate($dueDate, 'medium'),
      $musicianNextTotal . preg_quote('€'),
    ]);
    $exportData[self::MUSICIAN_KEY] = $label;
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $spreadSheetRow = $row + $offset;
    $sheet->mergeCells('A' . $spreadSheetRow . ':' . $this->preLastColumnAddress . $spreadSheetRow);
    $sheet->getStyle('A' . $spreadSheetRow . ':' . $this->lastColumnAddress . $spreadSheetRow)->applyFromArray([
      'font' => [
        'bold' => true
      ],
      'alignment' => [
        'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
        'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
      ],
    ]);
  }

  /**
   * @param Worksheet $sheet
   *
   * @param int $row
   *
   * @param int $offset
   *
   * @param int $rowCnt Mutable.
   *
   * @param null|float $total
   *
   * @return void
   */
  private function dumpTotal(
    Worksheet $sheet,
    int $row,
    int $offset,
    int &$rowCnt,
    ?float $total,
  ):void {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    if ($total !== null) {
      $exportData[self::MUSICIAN_KEY] = $this->l->t('Total Insurance Amount');
      $exportData[self::TOTALS_KEY] = $total . preg_quote('€');
    }
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $highRow = $sheet->getHighestRow();
    $sheet->mergeCells('A' . $highRow . ':' . $this->preLastColumnAddress . $highRow);
    $sheet->getStyle('A' . $highRow . ':' . $this->lastColumnAddress . $highRow)->applyFromArray([
      'font' => [
        'bold' => true
      ],
      'alignment' => [
        'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
        'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
      ],
      'fill' => [
        'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'color' => [
          'argb' => 'FFF0F0F0',
        ],
      ],
    ]);
  }

  /**
   * @param Worksheet $sheet
   *
   * @param int $row
   *
   * @param int $offset
   *
   * @param int $rowCnt Mutable.
   *
   * @param float $total
   *
   * @param DateTimeInterface $dueDate
   *
   * @return void
   */
  private function dumpNextTotal(
    Worksheet $sheet,
    int $row,
    int $offset,
    int &$rowCnt,
    float $total,
    DateTimeInterface $dueDate,
  ):void {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    $label = $this->l->t(
      'Next Total Insurance Amount from %s',
      $this->dateTimeFormatter()->formatDate($dueDate, 'medium'));
    $exportData[self::MUSICIAN_KEY] = $label;
    $exportData[self::TOTALS_KEY] = $total . preg_quote('€');
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $highRow = $sheet->getHighestRow();
    $sheet->mergeCells('A' . $highRow . ':' . $this->preLastColumnAddress . $highRow);
    $sheet->getStyle('A' . $highRow . ':' . $this->lastColumnAddress . $highRow)->applyFromArray([
      'font' => [
        'bold' => true
      ],
      'alignment' => [
        'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
        'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
      ],
      'fill' => [
        'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'color' => [
          'argb' => 'FFF0F0F0',
        ],
      ],
    ]);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
