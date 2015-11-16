<?php

// TODO: inject summation formuals

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\Projects;
use CAFEVDB\Util;
use CAFEVDB\Finance;
use CAFEVDB\InstrumentInsurance;

OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled(Config::APP_NAME);

function dumpRow($exportData, $sheet, $row, $offset, &$rowCnt, $header = false)
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

function dumpMusicianTotal($sheet, $row, $offset, &$rowCnt, $musicianTotal)
{
  $exportData = array(8);
  for ($k = 0; $k < 8; $k++) {
    $exportData[$k] = '';
  }
  $exportData[7] = $musicianTotal.preg_quote('€');
  dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
}

function dumpTotal($sheet, $row, $offset, &$rowCnt, &$total)
{
  $exportData = array(8);
  for ($k = 0; $k < 8; $k++) {
    $exportData[$k] = '';
  }
  $exportData[0]  = L::t('Total Insurance Amount: ');
  $exportData[7]  = $total.preg_quote('€');
  dumpRow($exportData, $sheet, $row, $offset, $rowCnt);
  $total = 0.0;
  $exportData[7] = '';
  $highRow = $sheet->getHighestRow();
  $sheet->mergeCells("A".$highRow.":"."G".$highRow);
  $sheet->getStyle("A".$highRow.":"."H".$highRow)->applyFromArray(
    array(
      'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
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
}

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

$table = new InstrumentInsurance(false);
$name = L::t('instrument insurances');

$table->deactivate();
$table->display(); // strange, but need be here

$creator   = Config::getValue('emailfromname', 'Bilbo Baggins');
$email     = Config::getValue('emailfromaddress', 'bilbo@nowhere.com');
$date      = strftime('%Y%m%d-%H%M%S');
$humanDate = strftime('%d.%m.%Y'); // %H:%M:%S');
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
  L::t('Musician'),
  L::t('Instrument'),
  L::t('Manufacturer'),
  L::t('Year of Construction'),
  L::t('Instrument Insurance Amount'),
  L::t('Accessory'),
  L::t('Accessory Insurance Amount'),
  L::t('Musician Insurance Total')
  );

$brokerNames = InstrumentInsurance::fetchBrokers();
$rates = InstrumentInsurance::fetchRates(false, true);

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
  /*We rely on the table layout A = musician, C = broker, D = scope
   */
  function ($i, $lineData) use (&$sheet, &$objPHPExcel, &$offset, &$rowCnt, $headerLine,
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

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex($objPHPExcel->getSheetCount() - 1);
        $sheet = $objPHPExcel->getActiveSheet();
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

for ($sheetIdx = 0; $sheetIdx < $objPHPExcel->getSheetCount(); $sheetIdx++) {
  $objPHPExcel->setActiveSheetIndex($sheetIdx);
  $sheet = $objPHPExcel->getActiveSheet();

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

?>
