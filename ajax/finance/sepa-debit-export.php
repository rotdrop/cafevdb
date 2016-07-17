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
 * AQ-Banking sepa-debit-transfer CSV exporter
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

  $debugText = '';

  ob_start();

  try {

    Config::init();

    // See wether we were passed specific variables ...
    $pmepfx      = Config::$pmeopts['cgi']['prefix']['sys'];
    $recordsKey  = $pmepfx.'mrecs';

    $projectId   = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', 'X');
    $table       = Util::cgiValue('Table', '');
    $debitJob    = Util::cgiValue('debit-job', '');
    $selectedMandates = Util::cgiValue($recordsKey, array());

    if ($table === 'InstrumentInsurance') {
      $debitJob = 'insurance';
      $selectedMandates = InstrumentInsurance::remapToDebitIds($selectedMandates);
    }

    switch ($debitJob) {
    case 'insurance':
      $debitTable = SepaDebitMandates::insuranceTableExport();
      $fileName = $date.'-aqbanking-debit-notes-insurance';
      $calendarProject = Config::getValue('memberTable');
      $calendarTitlePart = L::t('instrument insurances');
      break;
    default:
      $debitAmountMax = Util::cgiValue('debit-note-amount', 1e12); // infinity
      $debitNoteSubject = Util::cgiValue('debit-note-subject', false);

      $subject = null;
      $targetAmount = 0.0;
      if (empty($debitJob)) {
        throw new \InvalidArgumentException(L::t('You did not tell what kind of debit-note you would like to draw.'));
      } else if ($debitJob === 'amount') {
        $subject = $debitNoteSubject;
        if (empty($subject)) {
          throw new \InvalidArgumentException(L::t('Please specify a subject for this debit note.'));
        }
        $targetAmount = $debitAmountMax;
        $targetAmount = FuzzyInput::currencyValue($targetAmount);
        if (empty($targetAmount)) {
          throw new \InvalidArgumentException(L::t('Please tell me about the amount you would like to draw.'));
        }
      }
      $debitTable =  SepaDebitMandates::projectTableExport(
        $projectId, $debitJob, $targetAmount, $subject);
      $fileName = $date.'-aqbanking-debit-notes-'.$projectName;
      $calendarProject = $projectName;
      $calendarTitlePart = $projectName;
      break;
    }

    $encKey = Config::getEncryptionKey();
    $filteredTable = array();
    foreach($debitTable as $row) {
      $id = $row['id'];
      if (array_search($id, $selectedMandates) === false) {
        continue;
      }
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
    $dueStamp = strtotime('+ '.SepaDebitMandates::GRACE_PERIOD.' days');
    $aqDebitTable = SepaDebitMandates::aqBankingDebitNotes($filteredTable, $dueStamp);

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

    if (count($aqSequenceTables) == 0) {
      throw new \Exception(L::t("No Debit-Mandates to export?"));
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

      foreach($aqSequenceTables as $sequenceType => $debitTable) {
        $sequenceName = $fileName.'-'.$sequenceType.'.csv';

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

    $submissionStamp = strtotime('+ '.((int)SepaDebitMandates::GRACE_PERIOD-(int)SepaDebitMandates::SUBMIT_LIMIT).' days');
    $calObjIds = array();
    $calObjIds[] =
      Finance::financeEvent(L::t('Debit notes submission deadline').
                            ', '.
                            $calendarTitlePart,
                            L::t('Exported CSV file name:').
                            "\n\n".
                            $fileName.
                            "\n\n".
                            L::t('Due date:').' '.date('d.m.Y', $dueStamp),
                            $calendarProject,
                            $submissionStamp,
                            24*60*60 /* alert one day in advance */);

    $calObjIds[] =
      Finance::financeTask(L::t('Debit notes submission deadline').
                           ', '.
                           $calendarTitlePart,
                           L::t('Exported CSV file name:').
                           "\n\n".
                           $fileName.
                           "\n\n".
                           L::t('Due date:').' '.date('d.m.Y', $dueStamp),
                           $calendarProject,
                           $submissionStamp,
                           24*60*60 /* alert one day in advance */);

    $calObjIds[] =
      Finance::financeEvent(L::t('Debit notes due').
                            ', '.
                            $calendarTitlePart,
                            L::t('Exported CSV file name:').
                            "\n\n".
                            $fileName.
                            "\n\n".
                            L::t('Due date:').' '.date('d.m.Y', $dueStamp),
                            $calendarProject,
                            $dueStamp);


    if (count($aqSequenceTables) <= 1) {
      $fileName .= '-'.$sequenceType.'.csv';
      $mimeType = 'text/csv';
    } else {
      $fileName .= '.zip';
      $mimeType = 'application/zip';
    }

    // Email notification ids cannot fetched from here
    $debitNoteId = ProjectPayments::recordDebitNotes(
      $projectId, $debitJob,
      $timeStamp, $submissionStamp, $dueStamp,
      $calObjIds);
    if ($debitNoteId === false) {
      throw new \Exception(L::t("Unable to store debit notes."));
    }

    $dataId = ProjectPayments::recordDebitNoteData($debitNoteId, $fileName, $mimeType, $exportData);
    if ($dataId === false) {
      throw new \Exception(L::t("Unable to store debit note data."));
    }

    $paymentIds = ProjectPayments::recordDebitNotePayments($debitNoteId, $filteredTable, $dueStamp);
    if ($paymentIds === false) {
      throw new \Exception(L::t("Unable to store debit note payments."));
    }

    // Fine. All went well. Finally report back success, the calling
    // JS snippet then may trigger download via debit-note-download
    // and open the email dialog.

    \OCP\JSON::success(
      array('data' => array(
              'message' => L::t('Request successful'),
              'debitnote' => array('Id' => $debitNoteId,
                                   'Job' => $debitJob,
                                   'DataId' => $dataId,
                                   'PaymentIds' => $paymentIds),
              'emailtemplate' => DebitNotes::emailTemplate($debitJob)
              )
        )
      );

    return true;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Debit-Note Export').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

    \OCP\JSON::error(
      array(
        'data' => array(
          'caption' => L::t('PHP Exception Caught'),
          'error' => 'exception',
          'exception' => $exceptionText,
          'trace' => $trace,
          'message' => L::t('Error, caught an exception. '.
                            'Please copy the displayed text and send it by email to %s.',
                            array($mailto)),
          'debug' => htmlspecialchars($debugText))));

    return false;

  }


} //namespace CAFVDB


?>
