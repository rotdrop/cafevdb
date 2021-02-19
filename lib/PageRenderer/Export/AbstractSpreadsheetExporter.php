<?php // Hey, Emacs, we are -*- php -*- mode!
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
use OCA\CAFEVDB\PageRenderer\Util\PhpSpreadsheetValueBinder;

use OCA\CAFEVDB\Service\ConfigService;

abstract class AbstractSpreadsheetExporter
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

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

  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * Fill the given work-sheet with data. Do whatever is necessary.
   *
   * @param PhpSpreadsheet\Spreadsheet $spreadSheet Spread-sheet to be
   * filled. Passed empty safe meta-data and basic styling (page-size
   * and such).
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
    // @todo Format according to locate
    $date = strftime('%d.%m.%Y %H:%M:%S');

    $locale = $this->getLocale();

    $spreadSheet = new PhpSpreadsheet\Spreadsheet();
    $spreadSheet->getDefaultStyle()->getFont()->setName('Arial');
    $spreadSheet->getDefaultStyle()->getFont()->setSize(12);

    $validLocale = PhpSpreadsheet\Settings::setLocale($locale);
    if (!$validLocale) {
      $this->logError('Unable to set locale to "'.$locale.'"');
    }

    /** @todo move to namespace */
    $valueBinder = \OC::$server->query(PhpSpreadsheetValueBinder::class);
    PhpSpreadsheet\Cell\Cell::setValueBinder($valueBinder);
    try {
      /** @todo Make the font path configurable, disable feature if fonts not found. */
      PhpSpreadsheet\Shared\Font::setTrueTypeFontPath('/usr/share/fonts/corefonts/');
      PhpSpreadsheet\Shared\Font::setAutoSizeMethod(PhpSpreadsheet\Shared\Font::AUTOSIZE_METHOD_EXACT);
    } catch (\Throwable $t) {
      $this->logException($t);
    }

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
      'date' => $date,
    ]);

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

    $pageSetup = $spreadSheet->getActiveSheet()->getPageSetup();
    $pageSetup->setFitToPage(true);
    $pageSetup->setOrientation(PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $pageSetup->setPaperSize(PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);

    /*
     **************************************************************************
     *
     * Dump the data to the given file
     *
     */

    $fileType = self::FILE_TYPES[$format->getValue()];

    $writerClass = '\\PhpOffice\\PhpSpreadsheet\\Writer\\'.$fileType['writer'];
    $writer = new $writerClass($spreadSheet);

    $writer->save($fileName);

    // that was it ...

    return $fileType;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
