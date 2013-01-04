<?php

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
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

class MyValueBinder extends PHPExcel_Cell_DefaultValueBinder implements PHPExcel_Cell_IValueBinder
{
  /**
   * Bind value to a cell
   *
   * @param PHPExcel_Cell $cell	Cell to bind value to
   * @param mixed $value			Value to bind in cell
   * @return boolean
   */
  public function bindValue(PHPExcel_Cell $cell, $value = null)
  {		
    // sanitize UTF-8 strings
    if (is_string($value)) {
      $value = PHPExcel_Shared_String::SanitizeUTF8($value);
    }

    // Find out data type
    $dataType = parent::dataTypeForValue($value);

    // Copied over with bug-fixes: currency values can be
    // negative. And we want to support €, of course.
    if ($dataType === PHPExcel_Cell_DataType::TYPE_STRING && !$value instanceof PHPExcel_RichText) {
      // Check for currency
      $currencyCode = PHPExcel_Shared_String::SanitizeUTF8('€');
      if (preg_match('/^ *-? *(\d{1,3}(\,\d{3})*|(\d+))(\.\d{2})? *'.preg_quote($currencyCode).' *$/', $value)) {
        // Convert value to number
        $value = (float) trim(str_replace(array($currencyCode,','), '', $value));
        $cell->setValueExplicit($value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Set style
        $format = PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
        //$format = '#.##0,00 [$€];[ROT]-#.##0,00 [$€]';
        $format = '#,##0.00 [$€]';
        $format = '#,##0.00 [$€];[RED]-#,##0.00 [$€]';
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode( $format );
        return true;
      }

      // Check for currency in USD, why not
      if (preg_match('/^\$ *(\d{1,3}(\,\d{3})*|(\d+))(\.\d{2})?$/', $value)) {
        // Convert value to number
        $value = (float) trim(str_replace(array('$',','), '', $value));
        $cell->setValueExplicit( $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Set style
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE );
        return true;
      }

      // Interpret some basic html
      $hrefre = '/^ *<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a> *$/ie';
      if (preg_match($hrefre, $value, $matches)) {
        $cell->setValueExplicit($matches[2], PHPExcel_Cell_DataType::TYPE_STRING);
        $cell->getHyperlink()->setUrl($matches[1]);
        return true;
      }

      // Handle remaining stuff by html2text
      $h2t = new \html2text();
      $h2t->set_encoding('utf-8');
      $h2t->set_html($value);
      $value = trim($h2t->get_text());

      // Well, 'ja' or 'nein' ... should also count for truth values, maybe
      switch(strtoupper($value)) {
      case strtoupper(L::t('yes')):
      case strtoupper(L::t('true')):
        $value = PHPExcel_Calculation::getTRUE();
        break;
      case strtoupper(L::t('no')):
      case strtoupper(L::t('false')):
        $value = PHPExcel_Calculation::getFALSE();
        break;
      }

      //	Test for booleans using locale-setting
      if ($value == PHPExcel_Calculation::getTRUE()) {
        $cell->setValueExplicit( TRUE, PHPExcel_Cell_DataType::TYPE_BOOL);
        return true;
      } elseif($value == PHPExcel_Calculation::getFALSE()) {
        $cell->setValueExplicit( FALSE, PHPExcel_Cell_DataType::TYPE_BOOL);
        return true;
      }

      // Check for number in scientific format
      if (preg_match('/^'.PHPExcel_Calculation::CALCULATION_REGEXP_NUMBER.'$/', $value)) {
        $cell->setValueExplicit( (float) $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        return true;
      }

      // Check for fraction
      if (false) {
        //Nope, phone-number look like fractions but are not
        if (preg_match('/^([+-]?) *([0-9]*)\s?\/\s*([0-9]*)$/', $value, $matches)) {
          // Convert value to number
          $value = $matches[2] / $matches[3];
          if ($matches[1] == '-') $value = 0 - $value;
          $cell->setValueExplicit( (float) $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
          // Set style
          $cell->getParent()->getStyle( $cell->getCoordinate() )
            ->getNumberFormat()->setFormatCode( '??/??' );
          return true;
        } elseif (preg_match('/^([+-]?)([0-9]*) +([0-9]*)\s?\/\s*([0-9]*)$/', $value, $matches)) {
          // Convert value to number
          $value = $matches[2] + ($matches[3] / $matches[4]);
          if ($matches[1] == '-') $value = 0 - $value;
          $cell->setValueExplicit( (float) $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
          // Set style
          $cell->getParent()->getStyle( $cell->getCoordinate() )
            ->getNumberFormat()->setFormatCode( '# ??/??' );
          return true;
        }
      }
                        
      // Check for percentage
      if (preg_match('/^\-?[0-9]*\.?[0-9]*\s?\%$/', $value)) {
        // Convert value to number
        $value = (float) str_replace('%', '', $value) / 100;
        $cell->setValueExplicit( $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Set style
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00 );
        return true;
      }

      // Check for time without seconds e.g. '9:45', '09:45'
      if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d$/', $value)) {
        // Convert value to number
        list($h, $m) = explode(':', $value);
        $days = $h / 24 + $m / 1440;
        $cell->setValueExplicit($days, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Set style
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME3 );
        return true;
      }

      // Check for time with seconds '9:45:59', '09:45:59'
      if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
        // Convert value to number
        list($h, $m, $s) = explode(':', $value);
        $days = $h / 24 + $m / 1440 + $s / 86400;
        // Convert value to number
        $cell->setValueExplicit($days, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Set style
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode( PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4 );
        return true;
      }

      // Check for datetime, e.g. '2008-12-31', '2008-12-31 15:59', '2008-12-31 15:59:10'
      if (($d = PHPExcel_Shared_Date::stringToExcel($value)) !== false) {
        // Convert value to number
        $cell->setValueExplicit($d, PHPExcel_Cell_DataType::TYPE_NUMERIC);
        // Determine style. Either there is a time part or not. Look for ':'
        if (strpos($value, ':') !== false) {
          $formatCode = 'dd.mm.yyyy h:mm:ss';
        } else {
          $formatCode = 'dd.mm.yyyy';
        }
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getNumberFormat()->setFormatCode($formatCode);
        return true;
      }

      // Check for newline character "\n"
      if (strpos($value, "\n") !== FALSE) {
        $cell->setValueExplicit($value, PHPExcel_Cell_DataType::TYPE_STRING);
        // Set style
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getAlignment()->setWrapText(TRUE);
        $cell->getParent()->getStyle( $cell->getCoordinate() )
          ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);
        return true;
      }
    }

    // Not bound yet? Use parent...
    return parent::bindValue($cell, $value);
    
  }
}

/* TODO: add a date to the export file */
//print_r($_POST);

$template = Util::cgiValue('Template', '');

$table = false;
switch ($template) {
case 'all-musicians':
  $table = new CAFEVDB\Musicians(false, false);
  $name  = 'musicians';
  break;
case 'brief-instrumentation':
  $table = new CAFEVDB\BriefInstrumentation(false);
  $name = Util::cgiValue('Project').'-brief';
  break;
case 'detailed-instrumentation':
  $table = new CAFEVDB\DetailedInstrumentation(false);
  $name = Util::cgiValue('Project').'-detailed';
  break;
}

if ($table) {
  $creator  = Config::getValue('emailfromname', 'Bilbo Baggins');
  $email    = Config::getValue('emailfromaddress', 'bilbo@nowhere.com');
  $filename = strftime('%Y%m%d-%H%M%S').'-CAFEV-'.$name.'.xlsx';

  $lang = \OC_L10N::findLanguage(Config::APP_NAME);
  $locale = $lang.'_'.strtoupper($lang).'.UTF-8';

  /* $oldlocale = setlocale(LC_ALL, 0); */
  /* setlocale(LC_ALL, $locale); */

  // Create new PHPExcel object
  $objPHPExcel = new PHPExcel();
  $objPHPExcel->getDefaultStyle()->getFont()->setName('Arial');
  $objPHPExcel->getDefaultStyle()->getFont()->setSize(12);

  PHPExcel_Settings::setLocale($locale);
  PHPExcel_Cell::setValueBinder( new MyValueBinder() );
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

  $table->deactivate();
  $table->display(); // strange, but need be here
  $table->export(
    function ($i, $j, $cellData) {
      $cellData = trim($cellData);
      // filter out dummy dates, I really should clean up on the data-base level
      if ($cellData == '01.01.1970') {
        $cellData = '';
      }
      $cellData = html_entity_decode($cellData);
      return $cellData;
    },
    function ($i, $lineData) use ($sheet) {
      $column = 'A';
      foreach ($lineData as $cellValue) {
        $sheet->setCellValue($column.$i, $cellValue);
        if ($i == 1) {
          $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        ++$column;
      }
      if ($i >= 2) {
        $sheet->getStyle('A'.$i.':'.$sheet->getHighestColumn().$i)->applyFromArray(
          array(
            'fill' => array(
              'type'       => PHPExcel_Style_Fill::FILL_SOLID,
              'color' => array(
                'argb' => ($i % 2 == 0) ? 'FFB7CEEC' : 'FFC6DEFF'
                )
              )
            )
          );
      }
    });
  
  // Make the header a little bit prettier
  $pt_height = PHPExcel_Shared_Font::getDefaultRowHeightByFont($objPHPExcel->getDefaultStyle()->getFont());
  $sheet->getRowDimension(1)->setRowHeight(2*$pt_height);
  $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray(
    array(
      'font'    => array(
        'bold'      => true
        ),
      'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER_CONTINUOUS,
        ),
      'borders' => array(
        'allborders'     => array(
          'style' => PHPExcel_Style_Border::BORDER_HAIR
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
          'style' => PHPExcel_Style_Border::BORDER_HAIR
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
  
  //setlocale(LC_ALL, $oldlocale);

// Redirect output to a client’s web browser (Excel2007)
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="'.$filename.'"');
  header('Cache-Control: max-age=0');

  $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
  $objWriter->save('php://output');

} else {
  header('Content-type: text/plain');
  header('Content-disposition: attachment;filename=debug.txt');

  print_r($_POST);
}

?>
