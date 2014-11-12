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

  $tmpFile = false;
  
  try {

    Error::exceptions(true);
    Config::init();
    
    $_GET = array();

    setlocale(LC_ALL, Util::getLocale());

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

    if (count($insurances) == 1) {
      // export a single PFF
      $firstName = $insurances[0]['payer']['firstName'];
      $surName = $insurances[0]['payer']['surName'];
      $id =  $insurances[0]['payerId'];
      
      $name = InstrumentInsurance::musicianOverviewPDFName($insurances[0]);

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
      // export a zip-archive in order to avoid tons of download dialogs

      $tmpdir = ini_get('upload_tmp_dir');
      if ($tmpdir == '') {
        $tmpdir = sys_get_temp_dir();
      }      
      $tmpFile = tempnam($tmpdir, Config::APP_NAME.'.zip');
      if ($tmpFile === false) {
        throw new \RuntimeException(L::t('Unable to create temporay file for zip-archive.'));
      }

      $zip = new \ZipArchive();
      $opened = $zip->open($tmpFile, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE );
      if ($opened !== true) {
        throw new \RuntimeException(L::t('Unable to open temporary file for zip-archive.'));
      }

      foreach ($insurances as $insurance) {
        $name = InstrumentInsurance::musicianOverviewPDFName($insurance);
        $letter = InstrumentInsurance::musicianOverviewLetter($insurance);

        if ($zip->addFromString($name, $letter) === false) {
          throw new \RuntimeException(L::t('Unable to add %s to zip-archive',
                                           array($name)));
        }
      }

      if ($zip->close() === false) {
        throw new \RuntimeException(L::t('Unable to close temporary file for zip-archive.'));
      }

      $zipData = file_get_contents($tmpFile);
      if ($zipData === false) {
        throw new \RuntimeException(L::t('Unable to read zip archive from disk.'));
      }

      unlink($tmpFile);
      
      $name = strftime('%Y%m%d-%H%M%S').'-'.strtolower(L::t('instrument-insurance')).'.zip';

      header('Content-type: application/zip');
      header('Content-disposition: attachment;filename='.$name);
      header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
      header('Pragma: no-cache'); // HTTP 1.0.
      header('Expires: 0'); // Proxies.
      
      $outstream = fopen("php://output",'w');

      fwrite($outstream, $zipData);
      
      fclose($outstream);
      
    }
    
  } catch (\Exception $e) {

    if ($tmpFile !== false) {
      unlink($tmpFile);
    }
    
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
