<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
      $this->l->t('Musician'),
      $this->l->t('Instrument'),
      $this->l->t('Manufacturer'),
      $this->l->t('Year of Construction'),
      $this->l->t('Instrument Insurance Amount'),
      $this->l->t('Accessory'),
      $this->l->t('Accessory Insurance Amount'),
      $this->l->t('Musician Insurance Total')
    ];

    $brokerNames = $this->insuranceService->getBrokers();
    $rates = $this->insuranceService->getRates(true);

    $renderer->navigation(false); // inhibit navigation elements
    $renderer->render(false); // dry-run, prepare export

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
          $musician    = $lineData[0];
          $broker      = $lineData[2];
          $scope       = $lineData[3];
          $brokerScope = $broker.$scope;
          $newMusician = true;

          $sheet->setTitle($broker.' '.$scope);

          $sheet->setCellValue("A1", $name.", ".$brokerNames[$lineData[2]]['name']);
          $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");
          $sheet->setCellValue("A3", $this->l->t('Policy Number').": ".$rates[$brokerScope]['policy']);
          $sheet->setCellValue("A4", $this->l->t('Geographical Scope').": ".$lineData[3]);
          $sheet->setCellValue("A5", $this->l->t('Date').": ".$humanDate);
        } else {
          $broker   = $lineData[2];
          $scope    = $lineData[3];
          $newScope = $broker.$scope;

          if ($musician != $lineData[0] || $newScope != $brokerScope) {
            $this->dumpMusicianTotal($sheet, $i, $offset++, $rowCnt, $musicianTotal);
            $total += $musicianTotal;
            $musicianTotal = 0.0;
            $newMusician = true;
            $musician = $lineData[0];
          }

          if ($newScope != $brokerScope) {
            $this->dumpTotal($sheet, $i, $offset, $rowCnt, $total);

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex($spreadSheet->getSheetCount() - 1);
            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle($broker.' '.$scope);
            $brokerScope = $newScope;
            $offset = $headerOffset - $i + 2;
            $rowCnt = 0;
            $this->dumpRow($headerLine, $sheet, $i-1, $offset, $rowCnt, true);

            $sheet->setCellValue("A1", $name.", ".$brokerNames[$lineData[2]]['name']);
            $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");
            $sheet->setCellValue("A3", $this->l->t('Policy Number').": ".$rates[$brokerScope]['policy']);
            $sheet->setCellValue("A4", $this->l->t('Geographical Scope').": ".$lineData[3]);
            $sheet->setCellValue("A5", $this->l->t('Date').": ".$humanDate);
          }
        }

        $exportData[0] = $newMusician ? $lineData[0] : '';
        if ($lineData[5] == $this->l->t('false')) {
          $exportData[1] = $lineData[4];
          $exportData[2] = $lineData[6];
          $exportData[3] = $lineData[7];
          $exportData[4] = $lineData[8];
        } else {
          $exportData[5] = $lineData[4]." ".$lineData[6];
          if ($lineData[7] != $this->l->t('unknown')) {
            $exportData[5] .= ", ".$lineData[7];
          }
          $exportData[6] = $lineData[8];
        }
        $exportData[7] = '';

        $monetary = $this->fuzzyInputService->parseCurrency($lineData[8]);
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
          'allborders' => [
            'style' => PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
          'bottom' => [
            'style' => PhpSpreadsheet\Style\Border::BORDER_THIN,
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
          'allborders' => [
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
    $moneyColumns = ['E', 'G', 'H']; // aligned right
    $column = 'A';
    foreach ($exportData as $cellValue) {
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
    $exportData = array_fill(0, 8, '');
    $exportData[7] = $musicianTotal.preg_quote('€');
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
  }

  private function dumpTotal($sheet, $row, $offset, &$rowCnt, &$total)
  {
    $exportData = array_fill(0, 8, '');
    $exportData[0]  = $this->l->t('Total Insurance Amount: ');
    $exportData[7]  = $total.preg_quote('€');
    $this->dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
    $total = 0.0;
    $exportData[7] = '';
    $highRow = $sheet->getHighestRow();
    $sheet->mergeCells("A".$highRow.":"."G".$highRow);
    $sheet->getStyle("A".$highRow.":"."H".$highRow)->applyFromArray([
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
