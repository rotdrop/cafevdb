<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\Export;

use PhpOffice\PhpSpreadsheet;

use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\PageRenderer;

use OCA\CAFEVDB\Service\ConfigService;

class InsuranceSpreadsheetExporter extends AbstractSpreadsheetExporter
{
  use \OCA\CAFEVDB\Traits\SloppyTrait;

  protected const MUSICIAN_KEY = 'musician';
  protected const OBJECT_KEY = 'object';
  protected const ACCESSORY_KEY = 'accessory';
  protected const MANUFACTURER_KEY = 'manufacturer';
  protected const YEAR_OF_CONSTRUCTION_KEY = 'year';
  protected const AMOUNT_KEY = 'amount';
  protected const TOTALS_KEY = 'totals';

  protected const EXPORT_COLUMNS = [
    self::MUSICIAN_KEY,
    self::OBJECT_KEY,
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

  /** @var PageRenderer\PMETableViewBase */
  protected $renderer;

  /** @var InstrumentInsuranceService */
  protected $insuranceService;

  /** @var FuzzyInputService */
  protected $fuzzyInputService;

  /**
   * Construct a spread-sheet exporter for selected tables.
   *
   * @param PageRenderer\PMETableViewBase $renderer
   * Underlying renderer, see self::fillSheet()
   *
   * @param InstrumentInsuranceService $insuranceService
   *
   * @param FuzzyInputService $fuzzyInputService
   */
  public function __construct(
    PageRenderer\InstrumentInsurances $renderer
    , InstrumentInsuranceService $insuranceService
    , FuzzyInputService $fuzzyInputService
  ) {
    parent::__construct($renderer->configService());
    $this->renderer = $renderer;
    $this->insuranceService = $insuranceService;
    $this->fuzzyInputService = $fuzzyInputService;
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
   * 'email', 'date'
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
    $creator = $meta['creator'];
    $email = $meta['email'];
    $date = $meta['date'];

    $name  = $this->l->t('Instrument Insurances');

    $template = $this->renderer->template();

    $offset = $headerOffset = 6;
    $rowCnt = 0;

    /* Export the table, create extra lines for each musician with its
     * total insurance amount and one extra row at the end which states
     * the over-all total insurance amount. Generate one sheet for each
     * broker-scope pair.
     */
    $brokerScope   = false;
    $musician      = false;
    $musicianTotal = 0.0;
    $total         = 0.0;
    $numRecords    = 0;

    $headerLine  = [
      self::MUSICIAN_KEY => $this->l->t('Musician'),
      self::OBJECT_KEY => $this->l->t('Insured Object'),
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

    $humanDate = $this->dateTimeFormatter()->formatDate(new \DateTimeImmutable);

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
      /* We rely on the table layout A = musician, C = broker, D = scope */
      function ($i, $lineData) use (&$sheet, &$spreadSheet, &$offset, &$rowCnt, $headerLine,
                                    &$brokerScope, &$musician, &$musicianTotal, &$total,
                                    &$numRecords, $name, $creator, $email, $brokerNames, $rates,
                                    $humanDate, $headerOffset) {
        if ($i == 1) {
          $this->dumpRow($headerLine, $sheet, $i, $offset, $rowCnt, true);
          return;
        }

        $exportData = [];
        for ($k = 0; $k < 8; $k++) {
          $exportData[$k] = '';
        }

        $newMusician = false;

        if ($brokerScope === false) { // setup
          $musician    = $lineData[self::INPUT_INDEX_MUSICIAN];
          // $billTo      = $lineData[1];
          $broker      = $lineData[self::INPUT_INDEX_BROKER];
          $scope       = $lineData[self::INPUT_INDEX_SCOPE];
          $brokerScope = $broker.$scope;
          $newMusician = true;

          $sheet->setTitle($this->ellipsizeFirst($broker, $scope, PhpSpreadsheet\Worksheet\Worksheet::SHEET_TITLE_MAXIMUM_LENGTH, '; '));

          $sheet->setCellValue("A1", $name . ", " . $brokerNames[$lineData[self::INPUT_INDEX_BROKER]]['name'] . ', ' . $brokerNames[$lineData[self::INPUT_INDEX_BROKER]]['address']);
          $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");
          $sheet->setCellValue("A3", $this->l->t('Policy Number').": ".$rates[$brokerScope]['policy']);
          $sheet->setCellValue("A4", $this->l->t('Geographical Scope').": ".$lineData[self::INPUT_INDEX_SCOPE]);
          $sheet->setCellValue("A5", $this->l->t('Date').": ".$humanDate);
        } else {
          $broker   = $lineData[self::INPUT_INDEX_BROKER];
          $scope    = $lineData[self::INPUT_INDEX_SCOPE];
          $newScope = $broker.$scope;

          if ($musician != $lineData[self::INPUT_INDEX_MUSICIAN] || $newScope != $brokerScope) {
            $this->dumpMusicianTotal($sheet, $i, $offset++, $rowCnt, $musicianTotal);
            $total += $musicianTotal;
            $musicianTotal = 0.0;
            $newMusician = true;
            $musician = $lineData[self::INPUT_INDEX_MUSICIAN];
          }

          if ($newScope != $brokerScope) {
            $this->dumpTotal($sheet, $i, $offset, $rowCnt, $total);

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex($spreadSheet->getSheetCount() - 1);
            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle($this->ellipsizeFirst($broker, $scope, PhpSpreadsheet\Worksheet\Worksheet::SHEET_TITLE_MAXIMUM_LENGTH, '; '));
            $brokerScope = $newScope;
            $offset = $headerOffset - $i + 2;
            $rowCnt = 0;
            $this->dumpRow($headerLine, $sheet, $i-1, $offset, $rowCnt, true);

            $sheet->setCellValue(
              "A1", $name
              . ", " . $brokerNames[$lineData[self::INPUT_INDEX_BROKER]]['name']
              . ", " . $brokerNames[$lineData[self::INPUT_INDEX_BROKER]]['address']
            );
            $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");
            $sheet->setCellValue("A3", $this->l->t('Policy Number').": ".$rates[$brokerScope]['policy']);
            $sheet->setCellValue("A4", $this->l->t('Geographical Scope').": ".$lineData[self::INPUT_INDEX_SCOPE]);
            $sheet->setCellValue("A5", $this->l->t('Date').": ".$humanDate);
          }
        }

        //  0: musician
        //  1: bill-to
        //  2: broker
        //  3: scope
        //  4: object
        //  5: is-accessory
        //  6: manufacturer
        //  7: year-of-construction
        //  8: insured amount
        //  9: insurance rate
        // 10: amount-to-pay with taxes
        // 11: start of insurance

        $exportData[self::MUSICIAN_KEY] = $newMusician ? $lineData[self::INPUT_INDEX_MUSICIAN] : '';
        $exportData[self::OBJECT_KEY] = $lineData[self::INPUT_INDEX_OBJECT];
        $exportData[self::ACCESSORY_KEY] = $lineData[self::INPUT_INDEX_IS_ACCESSORY];
        $exportData[self::MANUFACTURER_KEY] = $lineData[self::INPUT_INDEX_MANUFACTURER];
        $exportData[self::YEAR_OF_CONSTRUCTION_KEY] = $lineData[self::INPUT_INDEX_YEAR_OF_CONSTRUCTION];
        $exportData[self::AMOUNT_KEY] = $lineData[self::INPUT_INDEX_INSURED_AMOUNT];
        $exportData[self::TOTALS_KEY] = '';

        $monetary = $this->fuzzyInputService->parseCurrency($lineData[self::INPUT_INDEX_INSURED_AMOUNT]);
        if ($monetary !== false) {
          $musicianTotal += $monetary['amount'];
        }

        $this->dumpRow($exportData, $sheet, $i, $offset, $rowCnt);

        $numRecords = $i+1;
      });

    $this->dumpMusicianTotal($sheet, $numRecords, $offset++, $rowCnt, $musicianTotal);
    $total += $musicianTotal;
    $musicianTotal = 0.0;
    $this->dumpTotal($sheet, $numRecords, $offset, $rowCnt, $total);

    // Then also dump the total insurance amount for the last sheet:

    for ($sheetIdx = 0; $sheetIdx < $spreadSheet->getSheetCount(); $sheetIdx++) {
      $spreadSheet->setActiveSheetIndex($sheetIdx);
      $sheet = $spreadSheet->getActiveSheet();

      // Make the header a little bit prettier
      $pt_height = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($spreadSheet->getDefaultStyle()->getFont());
      $sheet->getRowDimension(1+$headerOffset)->setRowHeight($pt_height+$pt_height/4);
      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->applyFromArray([
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
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
        ],
        'fill' => [
          'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => [
            'argb' => 'FFadd8e6',
          ],
        ],
      ]);

      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->getAlignment()->setWrapText(true);

      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().$sheet->getHighestRow())->applyFromArray([
        'borders' => [
          'allBorders' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
        ],
      ]);

      $sheet->getStyle($sheet->calculateWorkSheetDimension())->applyFromArray([
        'borders' => [
          'outline' => [
            'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
        ],
      ]);

      /*
       *
       ***************************************************************************/

      /****************************************************************************
       *
       * Header fields
       *
       */

      $highCol = $sheet->getHighestColumn();
      for($i = 1; $i < $headerOffset; ++$i) {
        $sheet->mergeCells("A".$i.":".$highCol.$i);
      }

      // Format the mess a little bit
      $sheet->getStyle("A1:".$highCol.($headerOffset-1))->applyFromArray([
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

      $sheet->getStyle("A1:".$highCol.($headerOffset-1))->applyFromArray([
        'alignment' => [
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
      ]);
    }

    /*
     *
     ***************************************************************************/

    $meta['name'] = $name;

    return $meta;
  }

  private function dumpRow($exportData, $sheet, $row, $offset, &$rowCnt, $header = false)
  {
    $moneyColumns = ['E', 'F', 'G']; // aligned right
    $column = 'A';
    foreach (self::EXPORT_COLUMNS as $columnKey) {
      $cellValue = $exportData[$columnKey];
      $sheet->setCellValue($column.($row+$offset), $cellValue);
      if ($header) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
      }
      ++$column;
    }
    if (!$header) {
      ++$rowCnt;
      $sheet->getStyle('A'.($row+$offset).':'.$sheet->getHighestColumn().($row+$offset))->applyFromArray([
        'fill' => [
          'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => [
            'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF',
          ],
        ],
      ]
      );
      foreach($moneyColumns as $col) {
        $sheet->getStyle($col.($row+$offset))->applyFromArray([
          'alignment' => [
            'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
          ],
        ]);
      }
    }
  }

  private function dumpMusicianTotal($sheet, $row, $offset, &$rowCnt, $musicianTotal)
  {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    $exportData[self::TOTALS_KEY] = $musicianTotal.preg_quote('€');
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
  }

  private function dumpTotal($sheet, $row, $offset, &$rowCnt, &$total)
  {
    $exportData = array_combine(self::EXPORT_COLUMNS, array_fill(0, count(self::EXPORT_COLUMNS), ''));
    $exportData[self::MUSICIAN_KEY] = $this->l->t('Total Insurance Amount');
    $exportData[self::TOTALS_KEY] = $total.preg_quote('€');
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $total = 0.0;
    $highRow = $sheet->getHighestRow();
    $lastColumnChr = $sheet->getHighestColumn();
    $preLastColumnChr = chr(ord($lastColumnChr)-1);
    $sheet->mergeCells("A".$highRow.":".$preLastColumnChr.$highRow);
    $sheet->getStyle("A".$highRow.":".$lastColumnChr.$highRow)->applyFromArray([
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
