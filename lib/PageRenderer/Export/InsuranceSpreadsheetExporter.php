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

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\Util\PhpSpreadsheetValueBinder;

use OCA\CAFEVDB\Service\ConfigService;

class InsuranceSpreadsheetExporter extends AbstractSpreadsheetExporter
{
  /** @var PageRenderer\PMETableViewBase */
  protected $renderer;

  /** @var ProjectService */
  protected $projectService;

  /**
   * Construct a spread-sheet exporter for selected tables.
   *
   * @param PageRenderer\PMETableViewBase $renderer
   * Underlying renderer, see self::fillSheet()
   *
   * @param null|ProjectService
   */
  public function __construct(PageRenderer\InstrumentInsurances $renderer) {
    parent::__construct($renderer->configService());
    $this->renderer = $renderer;
  }

  /**
   * Implement parent::fillSheet() for exactly
   *
   * - template all-musicians, class PageRenderer\Musicians
   * - template project-participants, class PageRenderer\ProjectParticipants
   * - template sepa-debit-mandates, class PageRenderer\SepaDebitMandates
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

    //@@@@@@@@@@@@@@

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

    $headerLine  = array(
      $this->l->t('Musician'),
      $this->l->t('Instrument'),
      $this->l->t('Manufacturer'),
      $this->l->t('Year of Construction'),
      $this->l->t('Instrument Insurance Amount'),
      $this->l->t('Accessory'),
      $this->l->t('Accessory Insurance Amount'),
      $this->l->t('Musician Insurance Total')
    );

    $brokerNames = InstrumentInsurance::fetchBrokers();
    $rates = InstrumentInsurance::fetchRates(false, true);

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
          dumpRow($headerLine, $sheet, $i, $offset, $rowCnt, true);
          return;
        }

        $exportData = array();
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
          $sheet->setCellValue("A3", L::t('Policy Number').": ".$rates[$brokerScope]['policy']);
          $sheet->setCellValue("A4", L::t('Geographical Scope').": ".$lineData[3]);
          $sheet->setCellValue("A5", L::t('Date').": ".$humanDate);
        } else {
          $broker   = $lineData[2];
          $scope    = $lineData[3];
          $newScope = $broker.$scope;

          if ($musician != $lineData[0] || $newScope != $brokerScope) {
            dumpMusicianTotal($sheet, $i, $offset++, $rowCnt, $musicianTotal);
            $total += $musicianTotal;
            $musicianTotal = 0.0;
            $newMusician = true;
            $musician = $lineData[0];
          }

          if ($newScope != $brokerScope) {
            dumpTotal($sheet, $i, $offset, $rowCnt, $total);

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex($spreadSheet->getSheetCount() - 1);
            $sheet = $spreadSheet->getActiveSheet();
            $sheet->setTitle($broker.' '.$scope);
            $brokerScope = $newScope;
            $offset = $headerOffset - $i + 2;
            $rowCnt = 0;
            dumpRow($headerLine, $sheet, $i-1, $offset, $rowCnt, true);

            $sheet->setCellValue("A1", $name.", ".$brokerNames[$lineData[2]]['name']);
            $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");
            $sheet->setCellValue("A3", L::t('Policy Number').": ".$rates[$brokerScope]['policy']);
            $sheet->setCellValue("A4", L::t('Geographical Scope').": ".$lineData[3]);
            $sheet->setCellValue("A5", L::t('Date').": ".$humanDate);
          }
        }

        $exportData[0] = $newMusician ? $lineData[0] : '';
        if ($lineData[5] == L::t('false')) {
          $exportData[1] = $lineData[4];
          $exportData[2] = $lineData[6];
          $exportData[3] = $lineData[7];
          $exportData[4] = $lineData[8];
        } else {
          $exportData[5] = $lineData[4]." ".$lineData[6];
          if ($lineData[7] != L::t('unknown')) {
            $exportData[5] .= ", ".$lineData[7];
          }
          $exportData[6] = $lineData[8];
        }
        $exportData[7] = '';

        $monetary = Finance::parseCurrency($lineData[8]);
        if ($monetary !== false) {
          $musicianTotal += $monetary['amount'];
        }

        dumpRow($exportData, $sheet, $i, $offset, $rowCnt);

        $numRecords = $i+1;
      });

    dumpMusicianTotal($sheet, $numRecords, $offset++, $rowCnt, $musicianTotal);
    $total += $musicianTotal;
    $musicianTotal = 0.0;
    dumpTotal($sheet, $numRecords, $offset, $rowCnt, $total);

    // Then also dump the total insurance amount for the last sheet:

    for ($sheetIdx = 0; $sheetIdx < $spreadSheet->getSheetCount(); $sheetIdx++) {
      $spreadSheet->setActiveSheetIndex($sheetIdx);
      $sheet = $spreadSheet->getActiveSheet();

      // Make the header a little bit prettier
      $pt_height = PHPExcel_Shared_Font::getDefaultRowHeightByFont($spreadSheet->getDefaultStyle()->getFont());
      $sheet->getRowDimension(1+$headerOffset)->setRowHeight($pt_height+$pt_height/4);
      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->applyFromArray(
        array(
          'font'    => array(
            'bold'      => true
          ),
          'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            'wrapText' => true
          ),
          'borders' => array(
            'allborders'     => array(
              'style' => PHPExcel_Style_Border::BORDER_THIN
            ),
            'bottom'     => array(
              'style' => PHPExcel_Style_Border::BORDER_THIN
            ),
          ),
          'fill' => array(
            'type'       => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array(
              'argb' => 'FFadd8e6'
            )
          )
        )
      );

      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->getAlignment()->setWrapText(true);

      $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().$sheet->getHighestRow())->applyFromArray(
        array(
          'borders' => array(
            'allborders'     => array(
              'style' => PHPExcel_Style_Border::BORDER_THIN
            ),
          )
        )
      );

      $sheet->getStyle($sheet->calculateWorkSheetDimension())->applyFromArray(
        array(
          'borders' => array(
            'outline'     => array(
              'style' => PHPExcel_Style_Border::BORDER_THIN
            ),
          )
        )
      );

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
      $sheet->getStyle("A1:".$highCol.($headerOffset-1))->applyFromArray(
        array(
          'font'    => array(
            'bold'   => true,
          ),
          'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
          ),
          'fill' => array(
            'type'       => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array(
              'argb' => 'FFF0F0F0'
            )
          )
        )
      );

      $sheet->getStyle("A1:".$highCol.($headerOffset-1))->applyFromArray(
        array(
          'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
          ),

        )
      );
    }

    /*
     *
     ***************************************************************************/

    //@@@@@@@@@@@@@@

    $meta['name'] = $name;

    return $meta;
  }

  private static function dumpRow($exportData, $sheet, $row, $offset, &$rowCnt, $header = false)
  {
    $moneyColumns = array('E', 'G', 'H'); // aligned right
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
      $sheet->getStyle('A'.($row+$offset).':'.$sheet->getHighestColumn().($row+$offset))->applyFromArray(
        array(
          'fill' => array(
            'type'       => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array(
              'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
            )
          )
        )
      );
      foreach($moneyColumns as $col) {
        $sheet->getStyle($col.($row+$offset))->applyFromArray(
          array(
            'alignment' => array(
              'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
              'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            )
          )
        );
      }
    }
  }

  private static function dumpMusicianTotal($sheet, $row, $offset, &$rowCnt, $musicianTotal)
  {
    $exportData = array(8);
    for ($k = 0; $k < 8; $k++) {
      $exportData[$k] = '';
    }
    $exportData[7] = $musicianTotal.preg_quote('€');
    dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
