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
      $filteredTable[] = $row;
    }

    // Consistency check. In case of an error, generate an exception.
    foreach($filteredTable as $mandate) {
      if ($mandate['projectFee'] <= 0) {
        throw new \InvalidArgumentException(L::t('Refusing to debit 0€. Full debit record: %s',
                                                 array(print_r($mandate, true))));
      }
    } 
    
    
    $name = $date.'-aqbanking-debit-notes-'.$projectName.'.csv';
    
    header('Content-type: text/ascii');
    header('Content-disposition: attachment;filename='.$name);
    header('Cache-Control: max-age=0');

    @ob_end_clean();

    print_r($_POST);

    $aqDebitTable = SepaDebitMandates::aqBankingDebitNotes($filteredTable);

    print_r($aqDebitTable);
    
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
