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

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Navigation;
use CAFEVDB\EmailRecipientsFilter;
use CAFEVDB\EmailComposer;
use CAFEVDB\Contacts;

try {

  // Close this session in order to enable progress feed-back
  session_write_close();

  ob_start();

  Error::exceptions(true);
  Config::init();

  $_GET = array();

  $caption = ''; ///< Optional status message caption.
  $messageText = ''; ///< Optional status message.
  $debugText = ''; ///< Diagnostic output, only enabled on request.

  if (Util::debugMode('request') || Util::debugMode('emailform')) {
    $debugText .= '$_POST = '.print_r($_POST, true);
  }

  $defaultData = array('Request' => 'update',
                       'FormElement' => 'everything',
                       'ProjectId' => Util::cgiValue('ProjectId', -1),
                       'ProjectName' => Util::cgiValue('ProjectName', ''));
  $requestData = array_merge($defaultData, Util::cgiValue('emailComposer', array()));
  $projectId   = $requestData['ProjectId'];
  $projectName = $requestData['ProjectName'];

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

      $tmpl = new OCP\Template('cafevdb', 'part.emailform.statuspage');
      $tmpl->assign('Projectname', $projectName);
      $tmpl->assign('ProjectId', $projectId);
      $tmpl->assign('Diagnostics', $diagnostics);
      $messageText = $tmpl->fetchPage();
    }
    break;
  case 'cancel':
    // simply let it do the cleanup
    $composer = new EmailComposer();
    break;
  case 'update':
    $formElement = $requestData['FormElement'];
    if ($formElement == 'everything') {
      $tmpl = new OCP\Template('cafevdb', 'part.emailform.composer');
      $tmpl->assign('ProjectName', $projectName);
      $tmpl->assign('ProjectId', $projectId);

      // Needed for the editor
      $tmpl->assign('templateName', $composer->currentEmailTemplate());
      $tmpl->assign('templateNames', $composer->emailTemplates());
      $tmpl->assign('TO', $composer->toString());
      $tmpl->assign('BCC', $composer->blindCarbonCopy());
      $tmpl->assign('CC', $composer->carbonCopy());
      $tmpl->assign('mailTag', $composer->subjectTag());
      $tmpl->assign('subject', $composer->subject());
      $tmpl->assign('message', $composer->messageText());
      $tmpl->assign('sender', $composer->fromName());
      $tmpl->assign('catchAllEmail', $composer->fromAddress());
      $tmpl->assign('fileAttachments', $composer->fileAttachments());
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
    if ($request == 'setTemplate') {
      break;
    }
  case 'saveTemplate':
    if (!$requestData['errorStatus'])  {
      $templates = $composer->emailTemplates();
      $options = '';
      foreach ($templates as $template) {
        $options .= '<option value="'.$template.'">'.$template.'</option>
';
      }
      $requestData['templateOptions'] = $options;
    } else {
      $requestData['diagnostics']['Caption'] =
        L::t('Template could not be saved');
    }
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
  
  $debugText .= ob_get_contents();
  @ob_end_clean();

  // Restart sesssion when finished.
  session_start();

  if (Util::debugMode('request') || Util::debugMode('emailform')) {
    $debugText .= print_r($requestData, true);
  }

  if ($requestData['errorStatus']) {
    $caption = $requestData['diagnostics']['Caption'];

    $tmpl = new OCP\Template('cafevdb', 'part.emailform.statuspage');
    $tmpl->assign('Projectname', $projectName);
    $tmpl->assign('ProjectId', $projectId);
    $tmpl->assign('Diagnostics', $requestData['diagnostics']);
    $messageText = $tmpl->fetchPage();

    OCP\JSON::error(
      array('data' => array('projectName' => $projectName,
                            'projectId' => $projectId,
                            'caption' => $caption,
                            'message' => $messageText,
                            'request' => $request,
                            'requestData' => $requestData,
                            'debug' => htmlspecialchars($debugText))));
  } else {
    OCP\JSON::success(
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

  $debugText .= ob_get_contents();
  @ob_end_clean();

  // Restart sesssion when finished.
  session_start();

  $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
  $trace = $e->getTraceAsString();

  $admin = Config::adminContact();

  $mailto = $admin['email'].
    '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
    '&body='.rawurlencode($exceptionText."\r\n".$trace);
  $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

  OCP\JSON::error(
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

?>
