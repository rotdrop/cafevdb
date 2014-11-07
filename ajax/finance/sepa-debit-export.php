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
  
  ob_start();

  try {

    Config::init();

    // See wether we were passed specific variables ...
    $pmepfx      = Config::$pmeopts['cgi']['prefix']['sys'];
    $recordsKey  = $pmepfx.'mrecs';

    $projectId = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', 'X');
    $selectedMandates = array_unique(Util::cgiValue($recordsKey, array()));
    
    if ($projectId < 0 || $projectName == 'X') {
      throw new \InvalidArgumentException(L::t('Project name and/or id are missing'));
    }

    $encKey = Config::getEncryptionKey();
    $debitTable = SepaDebitMandates::projectTableExport($projectId);
    $filteredTable = array();
    foreach($selectedMandates as $id) {
      $row = $debitTable[$id];
      if (!Finance::decryptSepaMandate($row)) {
        throw new \InvalidArgumentException(L::t('Unable to decrypt debit mandate. Full debit record: %s',
                                                 array(print_r($row, true))));
      }
      Finance::validateSepaMandate($row);
      if ($row['projectFee'] <= 0) {
        throw new \InvalidArgumentException(L::t('Refusing to debit 0€. Full debit record: %s',
                                                 array(print_r($row, true))));
      }
      foreach($row['purpose'] as &$purposeLine) {
        $purposeLine = Finance::sepaTranslit($purposeLine);
        if (!Finance::validateSepaString($purposeLine)) {
          throw new \InvalidArgumentException(L::t('Illegal characters in debit purpose: %s. '.
                                                   'Full debit record: %s',
                                                   array($purposeLine, print_r($row, true))));
        }
        if (strlen($purposeLine) > Finance::$sepaPurposeLength) {
          throw new \InvalidArgumentException(L::t('Purpose field has %d characters, allowed are %d. '.
                                                   'Full debit record: %s',
                                                   array(strlen($purposeLine),
                                                         Finance::$sepaPurposeLength,
                                                         print_r($row, true))));
        }
      }
      $filteredTable[] = $row;
    }

    // It worked out until now. Update the "last issued" stamp

    $handle = mySQL::connect(Config::$pmeopts);
    $nowStamp = time();
    $nowdate = date('Y-m-d', $nowStamp);
    $table = Finance::$dataBaseInfo['table'];
    $allQuery = '';
    foreach($filteredTable as $debitNote) {
      $query = "UPDATE `".$table."` SET `lastUsedDate` = '".$nowdate."' WHERE `id` = ".$debitNote['id'];
      $result = mySQL::query($query, $handle);
      $allQuery .= $query;
    }
    mySQL::close($handle);

    //throw new \InvalidArgumentException($allQuery);
    
    // Actually export the data.
    
    $name = $date.'-aqbanking-debit-notes-'.$projectName.'.csv';
    
    header('Content-type: text/ascii');
    header('Content-disposition: attachment;filename='.$name);
    header('Cache-Control: max-age=0');

    @ob_end_clean();

    //print_r($_POST);
    //print_r($blah);
    //print_r($debitTable);
    //print_r($selectedMandates);    

    $aqDebitTable = SepaDebitMandates::aqBankingDebitNotes($filteredTable);

    //print_r($aqDebitTable);

    // The rows of the aqDebitTable must have the following fields:
    $aqColumns = array("localBic",
"localIban","remoteBic","remoteIban","date","value/value","value/currency","localName","remoteName","creditorSchemeId","mandateId","mandateDate/dateString","mandateDebitorName","sequenceType","purpose[0]","purpose[1]","purpose[2]","purpose[3]");

    $outstream = fopen("php://output",'w');

    fputcsv($outstream, $aqColumns, ";", '"');
    foreach($aqDebitTable as $row) {
      fputcsv($outstream, array_values($row), ";", '"');
    }
    fclose($outstream);

    return true;
    
  } catch (\Exception $e) {

    $debugText = ob_get_contents();
    @ob_end_clean();

    $name = $date.'-CAFEVDB-exception.html';
    
    header('Content-type: text/html');
    header('Content-disposition: attachment;filename='.$name);
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

    echo '<h1>'.L::t('PHP Exception Caught').'</h1>
<blockquote>'.L::t('Error, caught an exception. '.
                   'Please copy the displayed text and send it by email to %s.',
                   array($mailto)).'</blockquote>
<pre>'.$exceptionText.'</pre>
<h2>'.L::t('Trace').'</h2>
<pre>'.$trace.'</pre>';
  }

  echo <<<__EOT__
  </body>
</html>
__EOT__;

} //namespace CAFVDB


?>
