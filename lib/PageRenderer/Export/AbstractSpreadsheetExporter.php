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

use DateTimeImmutable;

use PhpOffice\PhpSpreadsheet;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\FontService;

/** Abstract base class for spread-sheet export */
abstract class AbstractSpreadsheetExporter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  protected const WIDTH_EXTRA_SPACE = 2; // pt
  protected const MIN_WIDTH = 20; // pt

  /** Array of supported file-types */
  const FILE_TYPES = [
    ExportFormat::EXCEL => [
      'writer' => 'Xlsx',
      'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'extension' => 'xlsx',
    ],
    ExportFormat::ODS => [
      'writer' => 'Ods',
      'mimeType' => 'application/vnd.oasis.opendocument.spreadsheet',
      'extension' => 'ods',
    ],
    ExportFormat::CSV => [
      'writer' => 'Csv',
      'mimeType' => 'text/csv',
      'extension' => 'csv',
    ],
    ExportFormat::HTML => [
      'writer' => 'Html',
      'mimeType' => 'text/html',
      'extension' => 'html',
    ],
    ExportFormat::PDF => [
      'writer' => 'Pdf\\Mpdf',
      'mimeType' => 'application/pdf',
      'extension' => 'pdf',
    ],
  ];

  /** @var FontService */
  protected $fontService;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    FontService $fontService,
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->fontService = $fontService;
  }
  // phpcs:enable

  /**
   * Fill the given work-sheet with data. Do whatever is necessary.
   *
   * @param PhpSpreadsheet\Spreadsheet $spreadSheet Spread-sheet to be
   * filled. Passed empty safe meta-data and basic styling (page-size
   * and such).
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
   */
  abstract protected function fillSheet(PhpSpreadsheet\Spreadsheet $spreadSheet, array $meta):array;

  /**
   * Write out the generated sheet(s) to the given file-name.
   *
   * @param string $fileName TBD.
   *
   * @param string|ExportFormat $format One of the keys of self::FILE_TYPES.
   *
   * @return array
   * ```
   * [
   *   'mimeType' => MIME_TYPE_STRING,
   *   'extension' => FILE_EXTENSION_EXCLUDING_DOT,
   * ]
   * ```
   */
  public function export(string $fileName, $format)
  {
    $format = new ExportFormat(strtolower($format));

    $creator   = $this->getConfigValue('emailfromname', 'Bilbo Baggins');
    $email     = $this->getConfigValue('emailfromaddress', 'bilbo@nowhere.com');

    $locale = $this->getLocale();


    $fontPath = $this->fontService->getFontsFolderName();
    PhpSpreadsheet\Shared\Font::setTrueTypeFontPath($fontPath);
    PhpSpreadsheet\Shared\Font::setAutoSizeMethod(PhpSpreadsheet\Shared\Font::AUTOSIZE_METHOD_EXACT);

    $spreadSheet = new PhpSpreadsheet\Spreadsheet();
    $defaultFont = $this->fontService->getDefaultFontName();
    $spreadSheet->getDefaultStyle()->getFont()->setName($defaultFont);
    $spreadSheet->getDefaultStyle()->getFont()->setSize(12);

    $validLocale = PhpSpreadsheet\Settings::setLocale($locale);
    if (!$validLocale) {
      $this->logError('Unable to set locale to "'.$locale.'"');
    }

    /** @todo move to namespace */
    $valueBinder = $this->di(PhpSpreadsheetValueBinder::class);
    PhpSpreadsheet\Cell\Cell::setValueBinder($valueBinder);

    /*
     *
     **************************************************************************
     *
     * Let the hard work be done by something else ...
     *
     */

    $meta = $this->fillSheet($spreadSheet, [
      'creator' => $creator,
      'email' => $email,
      'date' => new DateTimeImmutable,
    ]);

    /*
     *
     **************************************************************************
     *
     * Adjust the column height and width computations, PhpSpreadsheet is not
     * godd in doing that ...
     *
     */

    for ($sheetIdx = 0; $sheetIdx < $spreadSheet->getSheetCount(); $sheetIdx++) {
      $spreadSheet->setActiveSheetIndex($sheetIdx);
      $sheet = $spreadSheet->getActiveSheet();

      $wrapTextValues = [];
      $wrapTextRows = [];
      $highestColumn = $sheet->getHighestColumn();
      $highestRow = $sheet->getHighestRow();

      // set wrap-text values to empty string in order not to spoil the width
      // computations.
      for ($column = 'A'; $column <= $highestColumn; $column++) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
        for ($row = 1; $row <= $highestRow; $row++) {
          $cellAddress = $column . $row;
          $cell = $sheet->getCell($cellAddress);
          if ($cell->getStyle()->getAlignment()->getWrapText()) {
            $wrapTextValues[$cellAddress] = $cell->getValue();
            $wrapTextRows[] = $row;
            $cell->setValue('');
          }
        }
      }

      $sheet->calculateColumnWidths();

      // restore cell-values
      foreach ($wrapTextValues as $cellAddress => $cellValue) {
        $sheet->getCell($cellAddress)->setValue($cellValue);
      }

      // disable auto-size and fetch column-width
      $columnWidth = [];
      for ($column = 'A'; $column <= $highestColumn; $column++) {
        $columnDimensions = $sheet->getColumnDimension($column);
        $columnDimensions->setAutoSize(false);
        $width = $columnDimensions->getWidth('pt');
        $width = max($width + 2.0 * self::WIDTH_EXTRA_SPACE, self::MIN_WIDTH);
        $columnDimensions->setWidth($width, 'pt');
        $columnWidth[$column] = $columnDimensions->getWidth(); // Excel units
      }

      // compute the height of the wrap-text rows
      foreach ($wrapTextRows as $row) {
        $font = $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->getFont();
        $optimalHeight = 0;
        for ($column = 'A'; $column <= $highestColumn; $column++) {
          $cell = $sheet->getCell($column . $row);
          $cellXf = $spreadSheet->getCellXfByIndex($cell->getXfIndex());
          $font = $cellXf->getFont();
          $singleLineHeaderWidth = PhpSpreadsheet\Shared\Font::calculateColumnWidth(
            $font,
            $cell->getValue(),
            $cellXf->getAlignment()->getTextRotation(),
            $font,
            filterAdjustment: false,
            indentAdjustment: 0,
          );
          $numberOfLines = (int)ceil($singleLineHeaderWidth / $columnWidth[$column]);
          $fontHeight = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($font);
          $optimalHeight = max($optimalHeight, $numberOfLines * $fontHeight + $fontHeight / 4);
        }
        $sheet->getRowDimension($row)->setRowHeight($optimalHeight);
      }
    } // loop over sheets

    /*
     *
     **************************************************************************
     *
     * Set spread-sheet properties after callback to content-filler
     *
     */

    $spreadSheet->getProperties()->setCreator($meta['creator'])
                ->setLastModifiedBy($meta['creator'])
                ->setTitle('CAFEV-'.$meta['name'])
                ->setSubject('CAFEV-'.$meta['name'])
                ->setDescription('Exported Database-Table')
                ->setKeywords('office 2007 openxml php '.$meta['name'])
                ->setCategory('Database Table Export');

    /*
     *
     **************************************************************************
     *
     * Try to avoid truncated pages
     *
     */

    for ($sheetIdx = 0; $sheetIdx < $spreadSheet->getSheetCount(); $sheetIdx++) {
      $spreadSheet->setActiveSheetIndex($sheetIdx);
      $sheet = $spreadSheet->getActiveSheet();

      $pageSetup = $sheet->getPageSetup();
      $pageSetup->setFitToPage(true);
      $pageSetup->setOrientation(PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
      $pageSetup->setPaperSize(PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
    }

    /*
     **************************************************************************
     *
     * Dump the data to the given file
     *
     */

    $fileType = self::FILE_TYPES[$format->getValue()];

    $writerClass = '\\PhpOffice\\PhpSpreadsheet\\Writer\\' . $fileType['writer'];
    $writer = new $writerClass($spreadSheet);

    $writer->save($fileName);

    // that was it ...

    return array_merge($fileType, $meta);
  }
}
