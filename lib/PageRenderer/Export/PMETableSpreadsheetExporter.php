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

class PMETableSpreadsheetExporter extends AbstractSpreadsheetExporter
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
  public function __construct(
    PageRenderer\PMETableViewBase $renderer
    , ?ProjectService $projectService = null
  ) {
    parent::__construct($renderer->configService());
    $this->renderer = $renderer;
    $this->projectService = $projectService;
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
    $workSheet = $spreadSheet->getActiveSheet();
    $creator = $meta['creator'];
    $email = $meta['email'];
    $date = $meta['date'];

    $template = $this->renderer->template();
    switch ($template) {
    case 'all-musicians':
      $name  = $this->l->t('Musicians');
      break;
    case 'project-participants':
      $projectId = $renderer->getProjectId();
      $projectName = $renderer->getProjectName();
      $name = $this->l->t('project participants for %s', $projectName);
      $instrumentLabel = $this->l->t('Instrument');
      $instrumentCol = true;
      break;
    case 'sepa-bank-accounts':
      $name = $this->l->t('SEPA bank accounts');
      break;
    default:
      throw new \InvalidArgumentException($this->l->t('Table export for table "%s" not yet implemented.', $template));
    }

    $renderer->navigation(false); // inhibit navigation elements
    $renderer->render(false); // dry-run, prepare export

    // @todo This is only relevant for the participants table
    $missing = [];
    $missingInfo = [];
    if (isset($projectId)
        && $projectId > 0
        && $renderer->defaultOrdering()
        && !empty($this->projectService)) {
      $balance = $this->projectService->instrumentationBalance($projectId, true);

      $missingInfo['missing']     = $balance;
      $missingInfo['instruments'] = array_keys($balance);
      $missingInfo['lastKeyword'] = false;
      $missingInfo['column'] = $instrumentCol;
      $missingInfo['label'] = $instrumentLabel;
      $missing = array_filter($balance, function ($val) {
        return $val['registered'] > 0 || $val['confirmed'] > 0;
      });
    }

    $offset = $headerOffset = 3;
    $rowCnt = 0;

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
      // Dump-row callback
      function ($i, $lineData) use ($workSheet, &$offset, &$rowCnt, &$missingInfo) {
        if ($i < 2 && $missingInfo['column'] === true) {
          // search header for instrument label
          $missingInfo['column'] = array_search($missingInfo['label'], $lineData);
        }
        if ($i >= 2 && !empty($missingInfo) && $missingInfo['column'] >= 0) {
          // $this->logInfo(print_r($lineData, true));
          // $this->logInfo(print_r($missingInfo, true));
          $instrumentCol = $missingInfo['column'];
          while (!empty($missingInfo['instruments']) &&
                 ($missingInfo['instruments'][0] != $lineData[$instrumentCol])) {
            $instrument = array_shift($missingInfo['instruments']);
            for ($k = 0; $k < $missingInfo['missing'][$instrument]['registered']; ++$k) {
              $workSheet->setCellValue(chr(ord('A')+$instrumentCol).($i+$k+$offset), $instrument);
              ++$rowCnt;
              $workSheet->getStyle('A'.($i+$k+$offset).':'.$workSheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
                array(
                  'fill' => array(
                    'fillType'       => PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => array(
                      'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
                    )
                  )
                )
              );
            }
            $offset += $k;
          }
        }
        $column = 'A';
        foreach ($lineData as $cellValue) {
          $workSheet->setCellValue($column.($i+$offset), $cellValue);
          if ($i == 1) {
            $workSheet->getColumnDimension($column)->setAutoSize(true);
          }
          ++$column;
        }
        if ($i >= 2) {
          ++$rowCnt;
          $style = [
            'fill' => [
              'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => [
                'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
              ],
            ],
          ];
          $workSheet->getStyle('A'.($i+$offset).':'.$workSheet->getHighestColumn().($i+$offset))->applyFromArray($style);
        }
      });

    /*
     *
     **************************************************************************
     *
     * Dump remaining missing musicians
     *
     */

    // finally, dump all remaining missing musicians ...
    if (!empty($missingInfo) && $missingInfo['column'] >= 0) {
      $i      = $workSheet->getHighestRow();
      $offset = 1;
      $instrumentCol = $missingInfo['column'];
      while (!empty($missingInfo['instruments'])) {
        $instrument = array_shift($missingInfo['instruments']);
        for ($k = 0; $k < $missingInfo['missing'][$instrument]['registered']; ++$k) {
          $workSheet->setCellValue(chr(ord('A')+$instrumentCol).($i+$k+$offset), $instrument);
          ++$rowCnt;
          $workSheet->getStyle('A'.($i+$k+$offset).':'.$workSheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
            array(
              'fill' => array(
                'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => array(
                  'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
                )
              )
            )
          );
        }
        $offset += $k;
      }
    }

    /*
     *
     **************************************************************************
     *
     * Make the header a little bit prettier
     *
     */

    $pt_height = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($spreadSheet->getDefaultStyle()->getFont());
    $workSheet->getRowDimension(1+$headerOffset)->setRowHeight($pt_height+$pt_height/4);
    $workSheet->getStyle('A'.(1+$headerOffset).':'.$workSheet->getHighestColumn().(1+$headerOffset))->applyFromArray([
      'font' => [
        'bold' => true
      ],
      'alignment' => [
        'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
        'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
      ],
      'borders' => [
        'allBorders' => [
          'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN
        ],
        'bottom' => [
          'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN
        ],
      ],
      'fill' => [
        'fillType'       => PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'color' => [
          'argb' => 'FFadd8e6',
        ],
      ],
    ]);

    $workSheet->getStyle('A2:'.$workSheet->getHighestColumn().$workSheet->getHighestRow())->applyFromArray([
      'borders' => [
        'allBorders'  => [
          'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
      ],
    ]);

    $workSheet->getStyle($workSheet->calculateWorkSheetDimension())->applyFromArray([
      'borders' => [
        'outline' => [
          'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
      ],
    ]);

    /*
     *
     **************************************************************************
     *
     * Add some extra rows with the missing instrumenation numbers.
     *
     */

    if (isset($projectId)) {
      if (count($missing) > 0) {
        $missingStart = $rowNumber = $workSheet->getHighestRow() + 4;

        $workSheet->setCellValue("A$rowNumber", $this->l->t('Missing Musicians'));
        $workSheet->mergeCells("A$rowNumber:D$rowNumber");
        $workSheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

        // Format the mess a little bit
        $workSheet->getStyle("A$rowNumber:D$rowNumber")->applyFromArray(
          array(
            'font'    => array(
              'bold'      => true
            ),
            'alignment' => array(
              'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
              'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ),
            'fill' => array(
              'fillType'       => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => array(
                'argb' => 'FFFF0000'
              )
            )
          )
        );

        ++$rowNumber;

        $workSheet->setCellValue("A$rowNumber", $this->l->t('Instrument'));
        $workSheet->setCellValue("B$rowNumber", $this->l->t('Required'));
        $workSheet->setCellValue("C$rowNumber", $this->l->t('Not Registered'));
        $workSheet->setCellValue("D$rowNumber", $this->l->t('Not Confirmed'));
        $workSheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

        // Format the mess a little bit
        $workSheet->getStyle("A$rowNumber:D$rowNumber")->applyFromArray(
          array(
            'font'    => array(
              'bold'      => true
            ),
            'alignment' => array(
              'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
              'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ),
            'fill' => array(
              'fillType'       => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => array(
                'argb' => 'FFBFDEB9'
              )
            )
          )
        );

        $cnt = 0;
        foreach ($missing as $instrument => $number) {
          if ($number['registered'] <= 0 && $number['confirmed'] <= 0) {
            continue;
          }
          ++$rowNumber;
          ++$cnt;
          $workSheet->setCellValue("A$rowNumber", $instrument);
          $workSheet->setCellValue("B$rowNumber", $number['required']);
          $workSheet->setCellValue("C$rowNumber", $number['registered']);
          $workSheet->setCellValue("D$rowNumber", $number['confirmed']);

          $workSheet->getStyle("A$rowNumber:D$rowNumber")->applyFromArray(
            array(
              'fill' => array(
                'fillType'  => PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => array('argb' => ($cnt % 2 == 0) ? 'FFBFDEB9' : 'FFF6FFDA')
              )
            )
          );
        }

        $workSheet->getStyle("A".($missingStart).":D$rowNumber")->applyFromArray([
          'borders' => [
            'allBorders' => [
              'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
          ],
        ]);
      }
    }

    /*
     *
     **************************************************************************
     *
     * Header fields
     *
     */

    $highCol = $workSheet->getHighestColumn();
    $workSheet->mergeCells("A1:".$highCol.'1');
    $workSheet->mergeCells("A2:".$highCol.'2');

    $workSheet->setCellValue('A1', $name.', '.$this->dateTimeFormatter()->formatDate($date));
    $workSheet->setCellValue('A2', $creator.' &lt;'.$email.'&gt;');

    // Format the mess a little bit
    $workSheet->getStyle('A1:'.$highCol.'2')->applyFromArray(
      array(
        'font'    => array(
          'bold'   => true,
        ),
        'alignment' => array(
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER_CONTINUOUS,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ),
        'fill' => array(
          'fillType'       => PhpSpreadsheet\Style\Fill::FILL_SOLID,
          'color' => array(
            'argb' => 'FFF0F0F0'
          )
        )
      )
    );

    $workSheet->getStyle('A1:'.$highCol.'2')->applyFromArray(
      array(
        'alignment' => array(
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ),
      )
    );

    /*
     * This tiny little bit of too ugly code hacked the spread-sheet export ;/
     *
     *************************************************************************/

    $meta['name'] = $name;

    return $meta;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
