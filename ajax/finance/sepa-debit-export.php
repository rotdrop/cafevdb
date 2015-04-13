<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**@file
 *
 * AQ-Banking sepa-debit-transfer CSV exporter
 */
namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  date_default_timezone_set(Util::getTimezone());
  $date = strftime('%Y%m%d-%H%M%S');
  
  Error::exceptions(true);
  $output = '';
  $nl = "\n";
  
  ob_start();

  try {

    Config::init();

    // See wether we were passed specific variables ...
    $pmepfx      = Config::$pmeopts['cgi']['prefix']['sys'];
    $recordsKey  = $pmepfx.'mrecs';

    $projectId   = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', 'X');
    $table       = Util::cgiValue('Table', '');
    $selectedMandates = array_unique(Util::cgiValue($recordsKey, array()));
    
    if ($projectId < 0 || $projectName == 'X') {
      throw new \InvalidArgumentException($nl.L::t('Project name and/or id are missing'));
    }

    $encKey = Config::getEncryptionKey();

    switch ($table) {
    case 'InstrumentInsurance':
      // $oldIds = $selectedMandates;
      $selectedMandates = InstrumentInsurance::remapToDebitIds($selectedMandates);
      $debitTable = SepaDebitMandates::insuranceTableExport();
      // throw new \Exception('ID: '.print_r($selectedMandates, true).' old ID '.print_r($oldIds, true).' table '.print_r($debitTable, true));          
      $name = $date.'-aqbanking-debit-notes-insurance';
      $calendarProject = Config::getValue('memberTable');
      $calendarTitlePart = L::t('instrument insurances');
      break;
    default:
      $debitTable = SepaDebitMandates::projectTableExport($projectId);    
      $name = $date.'-aqbanking-debit-notes-'.$projectName;
      $calendarProject = $projectName;
      $calendarTitlePart = $projectName;
      break;
    }

    $filteredTable = array();
    foreach($selectedMandates as $id) {
      $row = $debitTable[$id];
      if (!Finance::decryptSepaMandate($row)) {
        throw new \InvalidArgumentException(
          $nl.
          L::t('Unable to decrypt debit mandate.').
          $nl.
          L::t('Full debit record:').
          $nl.
          print_r($row, true));
      }
      Finance::validateSepaMandate($row);
      if ($row['amount'] <= 0) {
        setlocale(LC_MONETARY, Util::getLocale());
        throw new \InvalidArgumentException(
          $nl.
          L::t('Refusing to debit %s.', array(money_format('%n', $row['amount']))).
          $nl.
          L::t('Full debit record:').
          $nl.
          print_r($row, true));
      }
      foreach($row['purpose'] as &$purposeLine) {
        $purposeLine = Finance::sepaTranslit($purposeLine);
        if (!Finance::validateSepaString($purposeLine)) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Illegal characters in debit purpose: %s. ', array($purposeLine)).
            $nl.
            L::t('Full debit record:').
            $nl.
            print_r($row, true));
        }
        if (strlen($purposeLine) > Finance::$sepaPurposeLength) {
          throw new \InvalidArgumentException(
            $nl.
            L::t('Purpose field has %d characters, allowed are %d.',
                 array(strlen($purposeLine), Finance::$sepaPurposeLength)).
            $nl.
            L::t('Full debit record:').
            $nl.
            print_r($row, true));
        }
      }
      $filteredTable[] = $row;
    }

    // We use 17 days, we have to announce 14 days in advance and
    // submit after 7 days the debit notes to the bank. The bank need
    // the debit notes up to 6 "working days" in advance. Worst case
    // would be Saturday to Monday which is then "hacked" by the 10
    // day limit
    $timeStamp = strtotime('+ 17 days');
    $aqDebitTable = SepaDebitMandates::aqBankingDebitNotes($filteredTable, $timeStamp);

    // We must not mix once, first, following debit notes. So extract
    // into single tables and provide one CSV file for each of the
    // different debit-note types.
    $aqSequenceTables = array();
    foreach($aqDebitTable as $row) {
      $sequenceType = $row['sequenceType'];
      if (!isset($acSequenceTables[$sequenceType])) {
        $acSequenceTables[$sequenceType] = array();
      } 
      $aqSequenceTables[$sequenceType][] = $row;
    }
    
    // Actually export the data.

    // The rows of the aqDebitTable must have the following fields:
    $aqColumns = array("localBic",
                       "localIban",
                       "remoteBic",
                       "remoteIban",
                       "date",
                       "value/value",
                       "value/currency",
                       "localName",
                       "remoteName",
                       "creditorSchemeId",
                       "mandateId",
                       "mandateDate/dateString",
                       "mandateDebitorName",
                       "sequenceType",
                       "purpose[0]",
                       "purpose[1]",
                       "purpose[2]",
                       "purpose[3]");

    $exportData = '';
    if (count($aqSequenceTables) <= 1) {
      foreach($aqSequenceTables as $sequenceType => $debitTable) {

        $outstream = fopen("php://memory", 'w');

        // fputcsv() is locale sensitive.
        setlocale(LC_ALL, 'C');
    
        fputcsv($outstream, $aqColumns, ";", '"');
        foreach($aqDebitTable as $row) {
          fputcsv($outstream, array_values($row), ";", '"');
        }
        rewind($outstream);
        $exportData = stream_get_contents($outstream);

        fclose($outstream);
      }
    } else {
      // export a ZIP-archive

      $tmpdir = get_temp_dir();
      $tmpFile = tempnam($tmpdir, Config::APP_NAME.'.zip');
      if ($tmpFile === false) {
        throw new \RuntimeException(L::t('Unable to create temporay file for zip-archive.'));
      }

      $zip = new \ZipArchive();
      $opened = $zip->open($tmpFile, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE );
      if ($opened !== true) {
        throw new \RuntimeException(L::t('Unable to open temporary file for zip-archive.'));
      }
      
      foreach($aqSequenceTables as $sequenceType => $debitTable) {
        $sequenceName = $name.'-'.$sequenceType.'.csv';

        $outstream = fopen("php://memory", 'w');
        
        // fputcsv() is locale sensitive.
        $oldLocale = setlocale(LC_ALL, 'C');
    
        fputcsv($outstream, $aqColumns, ";", '"');
        foreach($debitTable as $row) {
          fputcsv($outstream, array_values($row), ";", '"');
        }
        setlocale(LC_ALL, $oldLocale);

        rewind($outstream);
        $csvData = stream_get_contents($outstream);
        fclose($outstream);

        $zip->addFromString($sequenceName, $csvData);
      }

      if ($zip->close() === false) {
        throw new \RuntimeException(L::t('Unable to close temporary file for zip-archive.'));
      }

      $exportData = file_get_contents($tmpFile);
      if ($exportData === false) {
        throw new \RuntimeException(L::t('Unable to read zip archive from disk.'));
      }

      unlink($tmpFile);
    }
    
    // It worked out until now. Update the "last issued" stamp and
    // inject proper events into the finance calendar.

    $submissionStamp = strtotime('+ 7 days');
    Finance::financeEvent(L::t('Debit notes submission deadline').
                          ', '.
                          $calendarTitlePart,
                          L::t('Exported CSV file name:').
                          "\n\n".
                          $name.
                          "\n\n".
                          L::t('Due date:').' '.date('d.m.Y', $timeStamp),
                          $calendarProject,
                          $submissionStamp,
                          24*60*60 /* alert one day in advance */);
    Finance::financeTask(L::t('Debit notes submission deadline').
                         ', '.
                         $calendarTitlePart,
                         L::t('Exported CSV file name:').
                         "\n\n".
                         $name.
                         "\n\n".
                         L::t('Due date:').' '.date('d.m.Y', $timeStamp),
                         $calendarProject,
                         $submissionStamp,
                         24*60*60 /* alert one day in advance */);

    Finance::financeEvent(L::t('Debit notes due').
                          ', '.
                          $calendarTitlePart,
                          L::t('Exported CSV file name:').
                          "\n\n".
                          $name.
                          "\n\n".
                          L::t('Due date:').' '.date('d.m.Y', $timeStamp),
                          $calendarProject,
                          $timeStamp);

    Finance::stampSepaMandates($filteredTable, $timeStamp);

    // finally present the download data to the browser. The idea is
    // that the following code is unlikely to throw an error. So: if
    // it worked out until here, it should be ok

    if (count($aqSequenceTables) <= 1) {
      $name .= '-'.$sequenceType.'.csv';
      header('Content-type: text/csv');
    } else {
      $name .= '.zip';      
      header('Content-type: application/zip');
    }
    header('Content-disposition: attachment;filename='.$name);
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate'); // HTTP 1.1.
    header('Pragma: no-cache'); // HTTP 1.0.
    header('Expires: 0'); // Proxies.

    @ob_end_clean();
      
    $outstream = fopen("php://output",'w');

    fwrite($outstream, $exportData);
      
    fclose($outstream);      
    
    return true;

  } catch (\Exception $e) {

    $debugText = ob_get_contents();
    @ob_end_clean();

    $name = $date.'-CAFEVDB-exception.html';
    
    header('Content-type: text/html');
    header('Content-disposition: inline;filename='.$name);
    header('Cache-Control: max-age=0');

    echo <<<__EOT__
<!DOCTYPE HTML>
  <html>
    <head>
      <title>Exception Debug Output</title>
      <meta charset="utf-8">
    </head>
    <body>
__EOT__;

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';
    
    echo '<h1>'.
      L::t('PHP Exception Caught').
      '</h1>
<blockquote>'.
      L::t('Please copy the displayed text and send it by email to %s.', array($mailto)).
'</blockquote>
<div class="exception error name"><pre>'.$exceptionText.'</pre></div>
<div class="exception error trace"><pre>'.$trace.'</pre></div>';

    echo <<<__EOT__
  </body>
</html>
__EOT__;
}


} //namespace CAFVDB


?>
