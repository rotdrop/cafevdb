<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Download stored debit-note data.
 */
namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  date_default_timezone_set(Util::getTimezone());
  $timeStamp = time();
  $date = strftime('%Y%m%d-%H%M%S', $timeStamp);

  Error::exceptions(true);
  $output = '';
  $nl = "\n";

  ob_start();

  try {

    Config::init();

    $debitNoteId = Util::cgiValue('DebitNoteId', false);
    $projectId = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', '');
    $downloadCookie = Util::cgiValue('DownloadCookie', false);

    if ($debitNoteId === false) {
      throw new \InvalidArgumentException(L::t('Debit note id not provided'));
    }

    if ($debitNoteId <= 0) {
      throw new \InvalidArgumentException(
        L::t('Debit note id "%s" is not valid', array($debitNoteId)));
    }

    $exportData = DebitNotes::debitNoteData($debitNoteId);

    if (count($exportData) === 1) {
      $fileData = $exportData[0]['Data'];
      $fileName = $exportData[0]['FileName'];
      $mimeType = $exportData[0]['MimeType'];
    } else {

      // should not happen at the moment, but simply pack everything
      // into one archive.

      $debitNote = DebitNotes::debitNote($debitNoteId);
      if ($debitNote === false) {
        throw new \RuntimeException(L::t('Unable to fetch debit-note for id %d',
                                         array($debitNoteId)));
      }
      if ($projectId != $debitNote['ProjectId'] || $projectName === '') {
        $projectName = Projects::fetchName($debitNote['ProjectId']);
      }
      $date = strftime('%Y%m%d-%H%M%S', strtotime($debitNote['DateIssued']));

      $fileName = $date.'-aqbanking-debit-notes-'.$projectName.'zip';
      $mimeType = 'application/zip';

      if (false)  {
        // needs pecl_http for compression which is really rather
        // quite problematic.
        $zipStream = fopen("php://memory", 'w');
        $zip = new \ZipStreamer\ZipStreamer(
          array('outstream' => $zipStream,
                'compress' => \ZipStreamer\COMPR::DEFLATE,
                'level' => \ZipStreamer\COMPR::MAXIMUM)
          );

        foreach($exportData as $data) {
          $dataStream = fopen("php://memory", 'r+');
          fwrite($dataStream, $data['Data']);
          rewind($dataStream);
          $zip->addFileFromStream($dataStream, $data['FileName']);
          fclose($dataStream);
        }

        $zip->finalize();
        rewind($zipStream);
        $fileData = stream_get_contents($zipStream);
        fclose($zipStream);

      } else {

        $tmpdir = \OC::$server->getTempManager()->getTempBaseDir();
        $tmpFile = tempnam($tmpdir, Config::APP_NAME.'.zip');
        if ($tmpFile === false) {
          throw new \RuntimeException(L::t('Unable to create temporay file for zip-archive.'));
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($tmpFile, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE );
        if ($opened !== true) {
          throw new \RuntimeException(L::t('Unable to open temporary file for zip-archive.'));
        }

        foreach($exportData as $data) {
          $zip->addFromString($data['FileName'], $data['Data']);
        }

        if ($zip->close() === false) {
          throw new \RuntimeException(L::t('Unable to close temporary file for zip-archive.'));
        }

        $fileData = file_get_contents($tmpFile);
        if ($fileData === false) {
          throw new \RuntimeException(L::t('Unable to read zip archive from disk.'));
        }

        unlink($tmpFile);
      }

    }

    // finally present the download data to the browser. The idea is
    // that the following code is unlikely to throw an error. So: if
    // it worked out until here, it should be ok

    Config::sessionClose();

    header('Content-type: '.$mimeType);
    header('Content-disposition: attachment;filename='.$fileName);
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate'); // HTTP 1.1.
    header('Pragma: no-cache'); // HTTP 1.0.
    header('Expires: 0'); // Proxies.
    Util::setCookie('debit_note_download', $downloadCookie, false);

    @ob_end_clean();

    $outstream = fopen("php://output", 'w');

    fwrite($outstream, $fileData);

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
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Download-Form').
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
