<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

/******************************************************************************
 *
 * We may want to move the office stuff to a separate service
 *
 */

use PhpOffice\PhpSpreadsheet;
use OCA\CAFEVDB\PageRenderer\Util\PhpSpreadsheetValueBinder;

/*
 *
 *****************************************************************************/

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;
use OCP\ITempManager;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

class PmeTableController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  // use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var HistoryService */
  private $historyService;

  /** @var ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var string */
  private $userId;

  /** @var IL10N */
  protected $l;

  /** @var ILogger */
  protected $logger;

  /** @var \OCP\AppFramework\IAppContainer */
  private $appContainer;

  /** @var \OCP\ITempManager */
  private $tempManager;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , ConfigService $configService
    , HistoryService $historyService
    , RequestParameterService $parameterService
    , PHPMyEdit $phpMyEdit
    , ITempManager $tempManager
    , $userId
    , IL10N $l10n
    , ILogger $logger
  ) {
    parent::__construct($appName, $request);

    $this->appContainer = $appContainer;
    $this->parameterService = $parameterService;
    $this->historyService = $historyService;
    $this->configService = $configService;
    $this->pme = $phpMyEdit;
    $this->tempManager = $tempManager;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->l = $l10n;
  }

  /**
   * Return template for table load
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function serviceSwitch($topic)
  {
    switch ($topic) {
    case 'load':
      return $this->load();
    case 'export':
      return $this->export();
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

  /**
   * Return template for table load
   */
  private function load()
  {
    try {
      $templateRenderer = $this->parameterService->getParam('templateRenderer');
      $template = $this->parameterService->getParam('template');
      $dialogMode = !empty($this->parameterService->getParam('ambientContainerSelector'));
      $reloadAction = false;
      $reloadAction = $this->parameterService->getParam(
        $this->pme->cgiSysName('_reloadfilter'),
        $this->parameterService->getParam($this->pme->cgiSysName('_reloadlist'))
      ) !== null;

      $historySize = -1;
      $historyPosition = -1;
      if (!$dialogMode && !$reloadAction) {
        $this->historyService->push($this->parameterService->getParams());
        $historySize = $this->historyService->size();
        $historyPosition = $this->historyService->position();
      }

      if (empty($templateRenderer)) {
        return self::grumble(['error' => $this->l->t("missing arguments"),
                              'message' => $this->l->t("No template-renderer submitted."), ]);
      }

      $renderer = $this->appContainer->query($templateRenderer);
      if (empty($renderer)) {
        return self::response(
          $this->l->t("Template-renderer `%s' cannot be found.", [$templateRenderer]),
          Http::INTERNAL_SERVER_ERROR);
      }
      // $renderer->navigation(false); NOPE, navigation is needed, number of query records may change.

      $template = 'pme-table';
      $templateParameters = [
        'renderer' => $renderer,
        'templateRenderer' => $templateRenderer,
        'template' => $template,
        'recordId' => $this->pme->getCGIRecordId(),
      ];

      $response = new TemplateResponse($this->appName, $template, $templateParameters, 'blank');

      $response->addHeader('X-'.$this->appName.'-history-size', $historySize);
      $response->addHeader('X-'.$this->appName.'-history-position', $historyPosition);

      if (!$dialogMode && !$reloadAction) {
        $this->historyService->store();
      }

      return $response;

    } catch (\Throwable $t) {
      $this->logException($t, __METHOD__);
      return self::grumble($this->exceptionChainData($t));
    }
  }

  /**
   * Return template for table load
   */
  private function export()
  {
    $templateRenderer = $this->parameterService->getParam('templateRenderer');
    $template = $this->parameterService->getParam('template');

    if (empty($templateRenderer)) {
      return self::grumble(['error' => $this->l->t("missing arguments"),
                            'message' => $this->l->t("No template-renderer submitted."), ]);
    }

    $renderer = $this->appContainer->query($templateRenderer);
    if (empty($renderer)) {
      return self::response(
        $this->l->t("Template-renderer `%s' cannot be found.", [$templateRenderer]),
        Http::INTERNAL_SERVER_ERROR);
    }

    $this->logInfo('Template: '.$template);

    switch ($template) {
    case 'all-musicians':
      $name  = $this->l->t('Musicians');
      break;
    case 'project-participants':
      $projectId = $table->projectId;
      $projectName = $table->projectName;
      $name = $this->l->t("project participants for %s", $projectName);
      $instrumentCol = 2;
      break;
    // case 'instrument-insurance':
    //   if (false) {
    //     $table = new CAFEVDB\InstrumentInsurance(false);
    //     $name = $this->l->t('instrument insurances');
    //     break;
    //   } else {
    //     return include('insurance-export.php');
    //   }
    case 'sepa-debit-mandates':
      $name = $this->l->t('SEPA debit mandates');
      break;
    default:
      return self::grumble($this->l->t('Table export for table "%s" not yet implemented.', $template));
    }

    $renderer->navigation(false);
    $renderer->render(false); // prepare export

    // $missing = array();
    // $missingInfo = array();
    // if (isset($projectId) &&
    //     $projectId > 0 &&
    //     $table->defaultOrdering()) {
    //   $numbers = Projects::fetchMissingInstrumentation($table->projectId);

    //   if (false) {
    //     OCP\Util::writeLog('cafevdb',
    //                        print_r($missing, true),
    //                        OCP\Util::INFO);
    //   }
    //   $missingInfo["missing"]     = $numbers;
    //   $missingInfo["instruments"] = array_keys($numbers);
    //   $missingInfo["lastKeyword"] = false;
    //   $missingInfo["column"]      = $instrumentCol;
    //   $missing = array_filter($numbers, function ($val) {
    //     return $val['Registered'] > 0 || $val['Confirmed'] > 0;
    //   });
    // }

    $creator   = $this->getConfigValue('emailfromname', 'Bilbo Baggins');
    $email     = $this->getConfigValue('emailfromaddress', 'bilbo@nowhere.com');
    $date      = strftime('%Y%m%d-%H%M%S');
    $humanDate = strftime('%d.%m.%Y %H:%M:%S');

    $locale = $this->getLocale();

    $spreadSheet = new PhpSpreadsheet\Spreadsheet();
    $spreadSheet->getDefaultStyle()->getFont()->setName('Arial');
    $spreadSheet->getDefaultStyle()->getFont()->setSize(12);

    $validLocale = PhpSpreadsheet\Settings::setLocale($locale);
    if (!$validLocale) {
      $this->logError('Unable to set locale to "'.$locale.'"');
    }

    $valueBinder = \OC::$server->query(PhpSpreadsheetValueBinder::class);
    PhpSpreadsheet\Cell\Cell::setValueBinder($valueBinder);
    /** @todo Make the font path configurable, disable feature if fonts not found. */
    try {
      PhpSpreadsheet\Shared\Font::setTrueTypeFontPath('/usr/share/fonts/corefonts/');
      PhpSpreadsheet\Shared\Font::setAutoSizeMethod(PhpSpreadsheet\Shared\Font::AUTOSIZE_METHOD_EXACT);
    } catch (\Throwable $t) {
      $this->logException($t);
    }

    // Set document properties
    $spreadSheet->getProperties()->setCreator($creator)
                ->setLastModifiedBy($creator)
                ->setTitle('CAFEV-'.$name)
                ->setSubject('CAFEV-'.$name)
                ->setDescription("Exported Database-Table")
                ->setKeywords("office 2007 openxml php ".$name)
                ->setCategory("Database Table Export");
    $sheet = $spreadSheet->getActiveSheet();

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
      function ($i, $lineData) use ($sheet, &$offset, &$rowCnt, &$missingInfo) {
        if ($i >= 2 && !empty($missingInfo)) {
          //error_log(print_r($lineData, true));
          //error_log(print_r($missingInfo, true));
          $col = $missingInfo["column"];
          while (!empty($missingInfo["instruments"]) &&
                 $missingInfo["instruments"][0] != $lineData[$col]) {
            $instrument = array_shift($missingInfo["instruments"]);
            for ($k = 0; $k < $missingInfo["missing"][$instrument]['Registered']; ++$k) {
              $sheet->setCellValue(chr(ord("A")+$col).($i+$k+$offset), $instrument);
              ++$rowCnt;
              $sheet->getStyle('A'.($i+$k+$offset).':'.$sheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
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
          $sheet->setCellValue($column.($i+$offset), $cellValue);
          if ($i == 1) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
          }
          ++$column;
        }
        if ($i >= 2) {
          ++$rowCnt;
          $this->logInfo('STYLE FOR '.'A'.($i+$offset).':'.$sheet->getHighestColumn().($i+$offset));
          $style = [
            'fill' => [
              'fillType' => PhpSpreadsheet\Style\Fill::FILL_SOLID,
              'color' => [
                'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
              ],
            ],
          ];
          $sheet->getStyle('A'.($i+$offset).':'.$sheet->getHighestColumn().($i+$offset))->applyFromArray($style);
        }
      });

    /*
     **************************************************************************
     *
     * Dump remaining missing musicians
     *
     */

    // finally, dump all remaining missing musicians ...
    if (!empty($missingInfo)) {
      $i      = $sheet->getHighestRow();
      $offset = 1;
      $col = $missingInfo["column"];
      while (!empty($missingInfo["instruments"])) {
        $instrument = array_shift($missingInfo["instruments"]);
        for ($k = 0; $k < $missingInfo["missing"][$instrument]['Registered']; ++$k) {
          $sheet->setCellValue(chr(ord("A")+$col).($i+$k+$offset), $instrument);
          ++$rowCnt;
          $sheet->getStyle('A'.($i+$k+$offset).':'.$sheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
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
     **************************************************************************
     *
     * Make the header a little bit prettier
     *
     */

    $pt_height = PhpSpreadsheet\Shared\Font::getDefaultRowHeightByFont($spreadSheet->getDefaultStyle()->getFont());
    $sheet->getRowDimension(1+$headerOffset)->setRowHeight($pt_height+$pt_height/4);
    $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->applyFromArray([
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

    $sheet->getStyle('A2:'.$sheet->getHighestColumn().$sheet->getHighestRow())->applyFromArray([
      'borders' => [
        'allBorders'  => [
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
     **************************************************************************
     *
     * Add some extra rows with the missing instrumenation numbers.
     *
     */

    if (isset($projectId)) {
      if (count($missing) > 0) {
        $missingStart = $rowNumber = $sheet->getHighestRow() + 4;

        $sheet->setCellValue("A$rowNumber", $this->l->t("Missing Musicians"));
        $sheet->mergeCells("A$rowNumber:C$rowNumber");
        $sheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

        // Format the mess a little bit
        $sheet->getStyle("A$rowNumber:B$rowNumber")->applyFromArray(
          array(
            'font'    => array(
              'bold'      => true
            ),
            'alignment' => array(
              'horizontal' => PhpSpreadsheet\Style\lignment::HORIZONTAL_CENTER_CONTINUOUS,
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

        $sheet->setCellValue("A$rowNumber", $this->l->t("Instrument"));
        $sheet->setCellValue("B$rowNumber", $this->l->t("Registered"));
        $sheet->setCellValue("C$rowNumber", $this->l->t("Confirmed"));
        $sheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

        // Format the mess a little bit
        $sheet->getStyle("A$rowNumber:C$rowNumber")->applyFromArray(
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
          if ($number['Registered'] <= 0 && $number['Confirmed'] <= 0) {
            continue;
          }
          ++$rowNumber;
          ++$cnt;
          $sheet->setCellValue("A$rowNumber", $instrument);
          $sheet->setCellValue("B$rowNumber", $number['Registered']);
          $sheet->setCellValue("C$rowNumber", $number['Confirmed']);

          $sheet->getStyle("A$rowNumber:C$rowNumber")->applyFromArray(
            array(
              'fill' => array(
                'fillType'  => PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => array('argb' => ($cnt % 2 == 0) ? 'FFBFDEB9' : 'FFF6FFDA')
              )
            )
          );
        }

        $sheet->getStyle("A".($missingStart).":C$rowNumber")->applyFromArray([
          'borders' => [
            'allBorders' => [
              'borderStyle' => PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
          ],
        ]);
      }
    }

    /*
     **************************************************************************
     *
     * Header fields
     *
     */

    $highCol = $sheet->getHighestColumn();
    $sheet->mergeCells("A1:".$highCol."1");
    $sheet->mergeCells("A2:".$highCol."2");

    $sheet->setCellValue("A1", $name.", ".$humanDate);
    $sheet->setCellValue("A2", $creator." &lt;".$email."&gt;");

    // Format the mess a little bit
    $sheet->getStyle("A1:".$highCol."2")->applyFromArray(
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

    $sheet->getStyle("A1:".$highCol."2")->applyFromArray(
      array(
        'alignment' => array(
          'horizontal' => PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
          'vertical' => PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ),
      )
    );

    /*
     **************************************************************************
     *
     * Dump the data to the client
     *
     */

    $tmpFile = $this->tempManager->getTemporaryFile($this->appName());
    register_shutdown_function(function() {
      $this->tempManager->clean();
    });

    $writer = new PhpSpreadsheet\Writer\Xlsx($spreadSheet);
    $writer->save($tmpFile);

    $data = file_get_contents($tmpFile);
    unlink($tmpFile);

    $fileName  = $date.'-'.$this->appName().'-'.$name.'.xlsx';

    return new DataDownloadResponse(
      $data, $fileName,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    return self::grumble($this->l->t('Table export not yet implemented.'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
