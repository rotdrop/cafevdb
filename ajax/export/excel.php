<?php

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\Projects;
use CAFEVDB\Util;

OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled(Config::APP_NAME);

/** PHPExcel root directory */
if (!defined('PHPEXCEL_ROOT')) {
  /**
   * @ignore
   */
  define('PHPEXCEL_ROOT', dirname(__FILE__) . '/../../3rdparty/');
  require(PHPEXCEL_ROOT . 'PHPExcel/Autoloader.php');
}

//print_r($_POST);

$template = Util::cgiValue('Template', '');

$projectId = false;
$table = false;
switch ($template) {
case 'all-musicians':
  $table = new CAFEVDB\Musicians(false, false);
  $name  = L::t('musicians');
  break;
case 'brief-instrumentation':
  $table = new CAFEVDB\BriefInstrumentation(false);
  $projectId   = $table->projectId;
  $projectName = $table->projectName;
  $name = L::t("%s-brief", array($projectName));
  $instrumentCol = 2;
  break;
case 'detailed-instrumentation':
  $table = new CAFEVDB\DetailedInstrumentation(false);
  $projectId = $table->projectId;
  $projectName = $table->projectName;
  $name = L::t("%s-detailed", array($projectName));
  $instrumentCol = 0;
  break;
case 'instrument-insurance':
  if (false) {
    $table = new CAFEVDB\InstrumentInsurance(false);
    $name = L::t('instrument insurances');
    break;
  } else {
    return include('insurance-export.php');
  }
case 'sepa-debit-mandates':
  $table = new CAFEVDB\SepaDebitMandates(false);
  $name = L::t('SEPA debit mandates');
  break;
}

if ($table) {
  $table->deactivate();
  $table->navigation(false);
  $table->display(); // strange, but need be here

  $missing = array();
  $missingInfo = array();
  if (isset($projectId) &&
      $projectId > 0 &&
      $table->defaultOrdering()) {
    $numbers = Projects::fetchMissingInstrumentation($table->projectId);

    if (false) {
      OCP\Util::writeLog('cafevdb',
                         print_r($missing, true),
                         OCP\Util::INFO);
    }
    $missingInfo["missing"]     = $numbers;
    $missingInfo["instruments"] = array_keys($numbers);
    $missingInfo["lastKeyword"] = false;
    $missingInfo["column"]      = $instrumentCol;
    $missing = array_filter($numbers, function ($val) { return $val > 0; });
  }

  $creator   = Config::getValue('emailfromname', 'Bilbo Baggins');
  $email     = Config::getValue('emailfromaddress', 'bilbo@nowhere.com');
  $date      = strftime('%Y%m%d-%H%M%S');
  $humanDate = strftime('%d.%m.%Y %H:%M:%S');
  $filename  = $date.'-CAFEV-'.$name.'.xlsx';

  $lang = \OC_L10N::findLanguage(Config::APP_NAME);
  $locale = $lang.'_'.strtoupper($lang).'.UTF-8';

  /* $oldlocale = setlocale(LC_ALL, 0); */
  /* setlocale(LC_ALL, $locale); */

  // Create new PHPExcel object
  $objPHPExcel = new PHPExcel();
  $objPHPExcel->getDefaultStyle()->getFont()->setName('Arial');
  $objPHPExcel->getDefaultStyle()->getFont()->setSize(12);

  PHPExcel_Settings::setLocale($locale);
  PHPExcel_Cell::setValueBinder( new CAFEVDB\PHPExcel\ValueBinder() );
  PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);

  // Set document properties
  $objPHPExcel->getProperties()->setCreator($creator)
    ->setLastModifiedBy($creator)
    ->setTitle('CAFEV-'.$name)
    ->setSubject('CAFEV-'.$name)
    ->setDescription("Exported Database-Table")
    ->setKeywords("office 2007 openxml php ".$name)
    ->setCategory("Database Table Export");
  $sheet = $objPHPExcel->getActiveSheet();

  $offset = $headerOffset = 3;
  $rowCnt = 0;

  $table->export(
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
        $col = $missingInfo["column"];
        while (!empty($missingInfo["instruments"]) &&
               $missingInfo["instruments"][0] != $lineData[$col]) {
          $instrument = array_shift($missingInfo["instruments"]);
          for ($k = 0; $k < $missingInfo["missing"][$instrument]; ++$k) {
            $sheet->setCellValue(chr(ord("A")+$col).($i+$k+$offset), $instrument);
            ++$rowCnt;
            $sheet->getStyle('A'.($i+$k+$offset).':'.$sheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
              array(
                'fill' => array(
                  'type'       => PHPExcel_Style_Fill::FILL_SOLID,
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
        $sheet->getStyle('A'.($i+$offset).':'.$sheet->getHighestColumn().($i+$offset))->applyFromArray(
          array(
            'fill' => array(
              'type'       => PHPExcel_Style_Fill::FILL_SOLID,
              'color' => array(
                'argb' => ($rowCnt % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
                )
              )
            )
          );
      }
    });

  // finally, dump all remaining missing musicians ...
  if (!empty($missingInfo)) {
    $i      = $sheet->getHighestRow();
    $offset = 1;
    $col = $missingInfo["column"];
    while (!empty($missingInfo["instruments"])) {
      $instrument = array_shift($missingInfo["instruments"]);
      for ($k = 0; $k < $missingInfo["missing"][$instrument]; ++$k) {
        $sheet->setCellValue(chr(ord("A")+$col).($i+$k+$offset), $instrument);
        ++$rowCnt;
        $sheet->getStyle('A'.($i+$k+$offset).':'.$sheet->getHighestColumn().($i+$k+$offset))->applyFromArray(
          array(
            'fill' => array(
              'type'       => PHPExcel_Style_Fill::FILL_SOLID,
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

  // Make the header a little bit prettier
  $pt_height = PHPExcel_Shared_Font::getDefaultRowHeightByFont($objPHPExcel->getDefaultStyle()->getFont());
  $sheet->getRowDimension(1+$headerOffset)->setRowHeight($pt_height+$pt_height/4);
  $sheet->getStyle("A".(1+$headerOffset).":".$sheet->getHighestColumn().(1+$headerOffset))->applyFromArray(
    array(
      'font'    => array(
        'bold'      => true
        ),
      'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
        'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
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

  $sheet->getStyle('A2:'.$sheet->getHighestColumn().$sheet->getHighestRow())->applyFromArray(
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

  /****************************************************************************
   *
   * Add some extra rows with the missing instrumenation numbers.
   *
   */

  if (isset($projectId)) {
    if (count($missing) > 0) {
      $missingStart = $rowNumber = $sheet->getHighestRow() + 4;

      $sheet->setCellValue("A$rowNumber", L::t("Missing Musicians"));
      $sheet->mergeCells("A$rowNumber:B$rowNumber");
      $sheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

      // Format the mess a little bit
      $sheet->getStyle("A$rowNumber:B$rowNumber")->applyFromArray(
        array(
          'font'    => array(
            'bold'      => true
            ),
          'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
          'fill' => array(
            'type'       => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array(
              'argb' => 'FFFF0000'
              )
            )
          )
        );

      ++$rowNumber;

      $sheet->setCellValue("A$rowNumber", L::t("Instrument"));
      $sheet->setCellValue("B$rowNumber", L::t("Missing"));
      $sheet->getRowDimension($rowNumber)->setRowHeight($pt_height+$pt_height/4);

      // Format the mess a little bit
      $sheet->getStyle("A$rowNumber:B$rowNumber")->applyFromArray(
        array(
          'font'    => array(
            'bold'      => true
            ),
          'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
          'fill' => array(
            'type'       => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array(
              'argb' => 'FFBFDEB9'
              )
            )
          )
        );

      $cnt = 0;
      foreach ($missing as $instrument => $number) {
        if ($number <= 0) {
          continue;
        }
        ++$rowNumber;
        ++$cnt;
        $sheet->setCellValue("A$rowNumber", $instrument);
        $sheet->setCellValue("B$rowNumber", $number);

        $sheet->getStyle("A$rowNumber:B$rowNumber")->applyFromArray(
          array(
            'fill' => array(
              'type'  => PHPExcel_Style_Fill::FILL_SOLID,
              'color' => array('argb' => ($cnt % 2 == 0) ? 'FFBFDEB9' : 'FFF6FFDA')
              )
            )
          );
      }

      $sheet->getStyle("A".($missingStart).":B$rowNumber")->applyFromArray(
        array(
          'borders' => array(
            'allborders'     => array('style' => PHPExcel_Style_Border::BORDER_THIN),
            )
          )
        );

    }
  }

  /*
   *
   ***************************************************************************/

  /****************************************************************************
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

  $sheet->getStyle("A1:".$highCol."2")->applyFromArray(
    array(
      'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
        'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),

      )
    );

  /*
   *
   ***************************************************************************/

  //setlocale(LC_ALL, $oldlocale);

  $tmpdir = ini_get('upload_tmp_dir');
  if ($tmpdir == '') {
    $tmpdir = \OC::$server->getTempManager()->getTempBaseDir();
  }
  $tmpFile = tempnam($tmpdir, Config::APP_NAME);
  if ($tmpFile === false) {
    return false;
  }

  register_shutdown_function(function($file) {
      if (is_file($file)) {
        unlink($file);
      }
    }, $tmpFile);

  $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
  $objWriter->save($tmpFile);

  $table = file_get_contents($tmpFile);
  unlink($tmpFile);

  if ($table !== false) {
    // Redirect output to a client’s web browser (Excel2007)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    echo $table;
  }

  return;
}

header('Content-type: text/plain');
header('Content-disposition: attachment;filename=debug.txt');

echo L::t('The export function for this table is not implemented, sorry.')."\n\n";
print_r($_POST);

?>
