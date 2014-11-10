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
 * @brief Mass-email composition AJAX handler.
 */

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  try {

    Error::exceptions(true);
    Config::init();
    
    $_GET = array();

    setlocale(LC_ALL, Util::getLocale());
    $name = strftime('%Y%m%d-%H%M%S').'-CAFEVDB-instrument-insurance.html';

    // See wether we were passed specific variables ...
    $pmepfx      = Config::$pmeopts['cgi']['prefix']['sys'];
    $recordsKey  = $pmepfx.'mrecs';

    $projectId   = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', 'X');
    $table       = Util::cgiValue('Table', '');
    $insuranceItems = array_unique(Util::cgiValue($recordsKey, array()));

    $handle = mySQL::connect(Config::$pmeopts);

    $selectedMusicians = InstrumentInsurance::remapToMusicianIds($insuranceItems, $handle);

    $insurances = array();
    foreach($selectedMusicians as $idx => $musicianId) {
      $insurances[] = InstrumentInsurance::musicianOverview($musicianId, $handle);
    }
    
    mySQL::close($handle);

    if (true) {

      $name = /*strftime('%Y%m%d-%H%M%S').'-*/'CAFEVDB-instrument-insurance.pdf';

      //$letter = PDFLetter::testLetter($name, 'D');

      $letter = InstrumentInsurance::musicianOverviewLetter($insurances[0]);
      
      header('Content-type: application/pdf');
      header('Content-disposition: attachment;filename='.$name);
      header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
      header('Pragma: no-cache'); // HTTP 1.0.
      header('Expires: 0'); // Proxies.
      
      $outstream = fopen("php://output",'w');

      fwrite($outstream, $letter);
      
      fclose($outstream);
      
    } else {
      
    header('Content-type: text/html');
    header('Content-disposition: attachment;filename='.$name);
    header('Cache-Control: max-age=0');

  echo <<<__EOT__
<!DOCTYPE HTML>
  <html>
    <head>
      <title>$name</title>
      <meta charset="utf-8">
    </head>
    <body>
__EOT__;

  $css = "insurance-overview-table";

  echo '
<style>
  table.'.$css.' {
    border: #000 1px solid;
    border-collapse:collapse;
    border-spacing:0px;
    width:auto;
  }
  table.'.$css.' th, table.'.$css.' td {
    border: #000 1px solid;
    min-width:5em;
    padding: 0.1em 0.5em 0.1em 0.5em;
  }
  table.'.$css.' td.summary {
    text-align:right;
  }
  td.money, td.percentage {
    text-align:right;
  }
  table.totals {
    font-weight:bold;
  }
</style>';
  
  foreach($insurances as $insurancePayer) {
    foreach($insurancePayer['musicians'] as $id => $insurance) {
      echo '
<table class="'.$css.'">
  <tr>
    <th>'.L::t('Vendor').'</th>
    <th>'.L::t('Scope').'</th>
    <th>'.L::t('Object').'</th>
    <th>'.L::t('Manufacturer').'</th>
    <th>'.L::t('Amount').'</th>
    <th>'.L::t('Rate').'</th>
    <th>'.L::t('Fee').'</th> 
  </tr>';
      foreach($insurance['items'] as $object) {
        echo '
  <tr>
    <td class="text">'.$object['broker'].'</td>
    <td class="text">'.L::t($object['scope']).'</td>
    <td class="text">'.$object['object'].'</td>
    <td class="text">'.$object['manufacturer'].'</td>
    <td class="money">'.money_format('%n', $object['amount']).'</td>
    <td class="percentage">'.($object['rate']*100.0).' %'.'</td>
    <td class="money">'.money_format('%n', $object['fee']).'</td>
  </tr>';
      }
      echo '
  <tr>
    <td class="summary" colspan="6">'.
      L::t('Sub-totals (excluding taxes)',
           array(InstrumentInsurance::TAXES)).'
    </td>
    <td class="money">'.money_format('%n', $insurance['subTotals']).'</td>
  </tr>
</table>';
    }
    $totals = $insurancePayer['totals'];
    $taxRate = floatval(InstrumentInsurance::TAXES);
    $taxes = $totals * $taxRate;
    echo '
<table class="totals">
  <tr>
    <td class="summary">'.L::t('Total amount excluding taxes:').'</td>
    <td class="money">'.money_format('%n', $totals).'</td>
  </tr>
  <tr>
    <td class="summary">'.L::t('%0.2f %% insurance taxes:', array($taxRate*100.0)).'</td>
    <td class="money">'.money_format('%n', $taxes).'</td>
  </tr>
  <tr>
    <td class="summary">'.L::t('Total amount to pay:').'</td>
    <td class="money">'.money_format('%n', $totals+$taxes).'</td>
  </tr>
</table>';
  }
  
  echo '<pre>';
  print_r($insurances);
  echo '</pre>';
  
  echo <<<__EOT__
  </body>
</html>
__EOT__;
    }
    
  
  } catch (\Exception $e) {

    header('Content-type: text/html');
    header('Cache-Control: max-age=0');

  echo <<<__EOT__
<!DOCTYPE HTML>
  <html>
    <head>
      <title>CAFEVDB Exception Error Page</title>
      <meta charset="utf-8">
    </head>
    <body>
__EOT__;

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Instrument-Insurance Export').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

    echo '<h1>'.L::t('PHP Exception Caught').'</h1>
<blockquote>'.L::t('Error, caught an exception. '.
                   'Please copy the displayed text and send it by email to %s.',
                   array($mailto)).'</blockquote>
<pre>'.$exceptionText.'</pre>
<h2>'.L::t('Trace').'</h2>
<pre>'.$trace.'</pre>';

    echo <<<__EOT__
  </body>
</html>
__EOT__;

  }

} // namespace

?>
