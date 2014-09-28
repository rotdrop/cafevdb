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

  ob_start();

  Error::exceptions(true);
  Config::init();

  $_GET = array();

  $debugText = ''; ///< Diagnostic output, only enabled on request.
  $messageText = '';

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
    $requestData['errorDiagnostics'] = '';
  } else {
    $recipientsFilter = new EmailRecipientsFilter();
    $recipients = $recipientsFilter->selectedRecipients();
    $composer = new EmailComposer($recipients);
    $requestData['errorStatus'] = $composer->errorStatus();
    $requestData['errorDiagnostics'] = $composer->errorDiagnostics();
  }

  $request = $requestData['Request'];
  switch ($request) {
  case 'send':
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
      $requestData['errorDiagnostics']['Caption'] =
        L::t('Template could not be saved');
    }
    break;
  case 'validateEmailRecipients':
    $composer = new EmailComposer();
    $composer->validateFreeFormAddresses($requestData['Header'],
                                         $requestData['Recipients']);
    $requestData['errorStatus'] = $composer->errorStatus();
    $requestData['errorDiagnostics'] = $composer->errorDiagnostics();
    if ($requestData['errorStatus']) {
      $requestData['errorDiagnostics']['Caption'] =
        L::t('Email Address Validation Failed');      
    }
    break;
  default:
    throw new \InvalidArgumentException(L::t("Unknown request: `%s'.", $request));
  }
  
  $debugText .= ob_get_contents();
  @ob_end_clean();
  
  if ($requestData['errorStatus']) {
    $caption = $requestData['errorDiagnostics']['Caption'];

    if (Util::debugMode('request') || Util::debugMode('emailform')) {
      $debugText .= print_r($requestData, true);
    }

    $tmpl = new OCP\Template('cafevdb', 'part.emailform.errorstatus');
    $tmpl->assign('Projectname', $projectName);
    $tmpl->assign('ProjectId', $projectId);
    $tmpl->assign('Diagnostics', $requestData['errorDiagnostics']);
    $message = $tmpl->fetchPage();

    OCP\JSON::error(
      array('data' => array('projectName' => $projectName,
                            'projectId' => $projectId,
                            'caption' => $caption,
                            'message' => $message,
                            'request' => $request,
                            'requestData' => $requestData,
                            'debug' => htmlspecialchars($debugText))));
  } else {
  OCP\JSON::success(
    array('data' => array('projectName' => $projectName,
                          'projectId' => $projectId,
                          'request' => $request,
                          'requestData' => $requestData,
                          'debug' => htmlspecialchars($debugText))));
  }

  return true;

} catch (\Exception $e) {

  $debugText .= ob_get_contents();
  @ob_end_clean();

  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception. '.
                          'Please copy the displayed text and send it by email to the web-master.'),
        'debug' => htmlspecialchars($debugText))));
 
  return false;
}

?>
