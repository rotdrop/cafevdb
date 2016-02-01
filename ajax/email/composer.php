<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  // Need to unset to trigger destructers in the correct order
  $composer = false;
  $recipientsFilter = false;

  // Need to suspend the session for the progress bar (otherwise opening
  // the current session in the progress-callback will block until
  // send-script has finished)
  $sessionSuspended = false;

  $caption = ''; ///< Optional status message caption.
  $messageText = ''; ///< Optional status message.
  $debugText = ''; ///< Diagnostic output, only enabled on request.

  function storedEmailOptions($composer)
  {
    $stored = $composer->storedEmails();
    $options = '';
    $options .= '
            <optgroup label="'.L::t('Drafts').'">
';
    foreach ($stored['drafts'] as $draft) {
      $options .= '
              <option value="__draft-'.$draft['value'].'">'.$draft['name'].'</option>
';
    }
    $options .= '
            </optgroup>';
    $options .= '<optgroup label="'.L::t('Templates').'">
';
    foreach ($stored['templates'] as $template) {
      $options .= '
              <option value="'.$template.'">'.$template.'</option>
';
    }
    $options .= '
            </optgroup>';

    return $options;
  }

  try {

    // Close this session in order to enable progress feed-back
    session_write_close();
    $sessionSuspended = true;

    ob_start();

    Error::exceptions(true);
    Config::init();

    $_GET = array();

    if (Util::debugMode('request') || Util::debugMode('emailform')) {
      $debugText .= '$_POST = '.print_r($_POST, true);
    }

    $defaultData = array('Request' => 'update',
                         'FormElement' => 'everything',
                         'ProjectId' => Util::cgiValue('ProjectId', -1),
                         'ProjectName' => Util::cgiValue('ProjectName', ''),
                         'DebitNoteId' => Util::cgiValue('DebitNoteId', -1));
    $requestData = array_merge($defaultData, Util::cgiValue('emailComposer', array()));
    $projectId   = $requestData['ProjectId'];
    $projectName = $requestData['ProjectName'];
    $debitNoteId = $requestData['DebitNoteId'];

    $composer = false;
    if (isset($requestData['SingleItem'])) {
      $requestData['errorStatus'] = false;
      $requestData['diagnostics'] = '';
    } else {
      $recipientsFilter = new EmailRecipientsFilter();
      $recipients = $recipientsFilter->selectedRecipients();
      $composer = new EmailComposer($recipients);
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
    }

    $request = $requestData['Request'];
    switch ($request) {
    case 'send':
      if (!$composer->errorStatus()) {
        // Echo something back on success, error diagnostics are handled
        // in a unified way at the end of this script.
        $diagnostics = $composer->statusDiagnostics();
        $caption = $diagnostics['Caption'];

        $tmpl = new \OCP\Template('cafevdb', 'part.emailform.statuspage');
        $tmpl->assign('Projectname', $projectName);
        $tmpl->assign('ProjectId', $projectId);
        $tmpl->assign('Diagnostics', $diagnostics);
        $messageText = $tmpl->fetchPage();

        // Update list of drafts after sending the message (draft has
        // been deleted)
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      }
      break;
    case 'cancel':
      // simply let it do the cleanup
      $composer = new EmailComposer();
      $blah = $composer->cleanTemporaries();
      $debugText .= "foo".print_r($blah, true);
      break;
    case 'update':
      $formElement = $requestData['FormElement'];
      if ($formElement == 'everything') {
        $tmpl = new \OCP\Template('cafevdb', 'part.emailform.composer');
        $tmpl->assign('ProjectName', $projectName);
        $tmpl->assign('ProjectId', $projectId);

        $tmpl->assign('templateName', $composer->currentEmailTemplate());
        $tmpl->assign('storedEmails', $composer->storedEmails());
        $tmpl->assign('TO', $composer->toString());
        $tmpl->assign('BCC', $composer->blindCarbonCopy());
        $tmpl->assign('CC', $composer->carbonCopy());
        $tmpl->assign('mailTag', $composer->subjectTag());
        $tmpl->assign('subject', $composer->subject());
        $tmpl->assign('message', $composer->messageText());
        $tmpl->assign('sender', $composer->fromName());
        $tmpl->assign('catchAllEmail', $composer->fromAddress());
        $tmpl->assign('fileAttachments', $composer->fileAttachments());
        $tmpl->assign('eventAttachments', $composer->eventAttachments());
        $tmpl->assign('ComposerFormData', $composer->formData());

        $elementData = $tmpl->fetchPage();
      } else {
        switch ($formElement) {
        case 'TO':
          $elementData = $composer->toString();
          break;
        case 'FileAttachments':
          $composer = new EmailComposer();
          $fileAttach = $composer->fileAttachments();
          $elementData = array('options' => Navigation::selectOptions(EmailComposer::fileAttachmentOptions($fileAttach)),
                               'fileAttach' => $fileAttach);
          break;
        case 'EventAttachments':
          $composer = new EmailComposer();
          $eventAttach = $composer->eventAttachments();
          $elementData = array('options' => Navigation::selectOptions(EmailComposer::eventAttachmentOptions($projectId, $eventAttach)),
                               'eventAttach' => $eventAttach);
          break;
        default:
          throw new \InvalidArgumentException(L::t("Unknown form element: `%s'.", $formElement));
        }
      }
      $requestData['formElement'] = $formElement;
      $requestData['elementData'] = $elementData;
      break;
    case 'deleteTemplate':
    case 'setTemplate':
      $requestData['templateName'] = $composer->currentEmailTemplate();
      $requestData['message'] = $composer->messageText();
      $requestData['subject'] = $composer->subject();
      if ($request == 'setTemplate') {
        break;
      }
    case 'saveTemplate':
      if (!$requestData['errorStatus'])  {
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      } else {
        $requestData['diagnostics']['Caption'] =
          L::t('Template could not be saved');
      }
      break;
    case 'saveDraft':
      if (!$requestData['errorStatus'])  {
        $requestData['storedEmailOptions'] = storedEmailOptions($composer);
        $requestData['messageDraftId'] = $composer->messageDraftId();
      } else {
        $requestData['diagnostics']['Caption'] = L::t('Draft could not be saved');
      }
      break;
    case 'deleteDraft':
      $debugText .= L::t("Deleted draft message with id %d",
                         array($requestData['messageDraftId']));
      $requestData['storedEmailOptions'] = storedEmailOptions($composer);
      $requestData['messageDraftId'] = -1;
      break;
    case 'loadDraft':
      // This seems to be somewhat tricky. The procedure here is to
      // replace the $_POST array by the saved data, reconstruct the
      // composer and the recipient dialogs. Better way than that???

      $_POST = array_merge($_POST, $composer->loadDraft());
      $_POST['emailComposer']['MessageDraftId'] = $composer->messageDraftId();

      // Update project name and id
      $projectId = $requestData['ProjectId'] = $_POST['ProjectId'];
      $projectName = $requestData['ProjectName'] = $_POST['ProjectName'];
      $debitNoteId = $requestData['DebitNoteId'] = $_POST['DebitNoteId'];
      $requestData['messageDraftId'] = $composer->messageDraftId();

      $recipientsFilter = new EmailRecipientsFilter();
      $recipients = $recipientsFilter->selectedRecipients();

      $composer = new EmailComposer($recipients);
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();

      // Composer template
      $msgTmpl = new \OCP\Template('cafevdb', 'part.emailform.composer');
      $msgTmpl->assign('ProjectName', $projectName);
      $msgTmpl->assign('ProjectId', $projectId);

      $msgTmpl->assign('templateName', $composer->currentEmailTemplate());
      $msgTmpl->assign('storedEmails', $composer->storedEmails());
      $msgTmpl->assign('TO', $composer->toString());
      $msgTmpl->assign('BCC', $composer->blindCarbonCopy());
      $msgTmpl->assign('CC', $composer->carbonCopy());
      $msgTmpl->assign('mailTag', $composer->subjectTag());
      $msgTmpl->assign('subject', $composer->subject());
      $msgTmpl->assign('message', $composer->messageText());
      $msgTmpl->assign('sender', $composer->fromName());
      $msgTmpl->assign('catchAllEmail', $composer->fromAddress());
      $msgTmpl->assign('fileAttachments', $composer->fileAttachments());
      $msgTmpl->assign('eventAttachments', $composer->eventAttachments());
      $msgTmpl->assign('ComposerFormData', $composer->formData());

      $msgData = $msgTmpl->fetchPage();

      // Recipients template
      $rcptTmpl = new \OCP\Template('cafevdb', 'part.emailform.recipients');
      $rcptTmpl->assign('ProjectName', $projectName);
      $rcptTmpl->assign('ProjectId', $projectId);

      // Needed for the recipient selection
      $rcptTmpl->assign('RecipientsFormData', $recipientsFilter->formData());
      $filterHistory = $recipientsFilter->filterHistory();
      $rcptTmpl->assign('FilterHistory', $filterHistory);
      $rcptTmpl->assign('MemberStatusFilter', $recipientsFilter->memberStatusFilter());
      $rcptTmpl->assign('BasicRecipientsSet', $recipientsFilter->basicRecipientsSet());
      $rcptTmpl->assign('InstrumentsFilter', $recipientsFilter->instrumentsFilter());
      $rcptTmpl->assign('EmailRecipientsChoices', $recipientsFilter->emailRecipientsChoices());
      $rcptTmpl->assign('MissingEmailAddresses', $recipientsFilter->missingEmailAddresses());
      $rcptTmpl->assign('FrozenRecipients', $recipientsFilter->frozenRecipients());

      $rcptData = $rcptTmpl->fetchPage();

      $requestData['composerForm'] = $msgData;
      $requestData['recipientsForm'] = $rcptData;

      // $debugText .= print_r($_POST, true);
      $debugText .= L::t("Loaded new draft message with id %d",
                         array($requestData['messageDraftId']));

      break;
    case 'validateEmailRecipients':
      $composer = new EmailComposer();
      $composer->validateFreeFormAddresses($requestData['Header'],
                                           $requestData['Recipients']);
      $requestData['errorStatus'] = $composer->errorStatus();
      $requestData['diagnostics'] = $composer->statusDiagnostics();
      if ($requestData['errorStatus']) {
        $requestData['diagnostics']['Caption'] =
          L::t('Email Address Validation Failed');
      }
      break;
    default:
      throw new \InvalidArgumentException(L::t("Unknown request: `%s'.", $request));
    }

    unset($composer);
    unset($recipientsFilter);

    // Restart sesssion when finished.
    session_start();
    $sessionSuspended = false;

    $debugText .= ob_get_contents();
    @ob_end_clean();

    if (Util::debugMode('request') || Util::debugMode('emailform')) {
      $debugText .= print_r($requestData, true);
    }

    if ($requestData['errorStatus']) {
      $caption = $requestData['diagnostics']['Caption'];

      $tmpl = new \OCP\Template('cafevdb', 'part.emailform.statuspage');
      $tmpl->assign('Projectname', $projectName);
      $tmpl->assign('ProjectId', $projectId);
      $tmpl->assign('Diagnostics', $requestData['diagnostics']);
      $messageText = $tmpl->fetchPage();

      \OCP\JSON::error(
        array('data' => array('projectName' => $projectName,
                              'projectId' => $projectId,
                              'caption' => $caption,
                              'message' => $messageText,
                              'request' => $request,
                              'requestData' => $requestData,
                              'debug' => htmlspecialchars($debugText))));
    } else {
      \OCP\JSON::success(
        array('data' => array('projectName' => $projectName,
                              'projectId' => $projectId,
                              'caption' => $caption,
                              'message' => $messageText,
                              'request' => $request,
                              'requestData' => $requestData,
                              'debug' => htmlspecialchars($debugText))));
    }

    return true;

  } catch (\Exception $e) {

    unset($composer);
    unset($recipientsFilter);

    if ($sessionSuspended) {
      // Restart sesssion when finished.
      session_start();
    }

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
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

} // namespace CAFEVDB

?>
