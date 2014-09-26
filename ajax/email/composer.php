<?php
/**Orchestra member, musician and project management application.
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

/**@file Load a PME table without outer controls, intended usage are
 * jQuery dialogs. This is the Ajax query callback; it loads a
 * template with "pme-table" which actually echos the HTML.
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

  $debugText = '';
  $messageText = '';

  if (true || Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }

  $defaultData = array('Request' => 'update',
                       'FormElement' => 'everything',
                       'ProjectId' => Util::cgiValue('ProjectId', -1),
                       'ProjectName' => Util::cgiValue('ProjectName', ''));
  $requestData = array_merge($defaultData, Util::cgiValue('emailComposer', array()));
  $projectId   = $requestData['ProjectId'];
  $projectName = $requestData['ProjectName'];

  $composer = false;
  if (!isset($requestData['SingleItem'])) {
    $recipientsFilter = new EmailRecipientsFilter();
    $composer = new EmailComposer($recipientsFilter->selectedRecipients());
    $requestData['errorStatus'] = $composer->errorStatus();
    $requestData['errorDiagnostics'] = $composer->errorDiagnostics();
  } else {
    $requestData['errorStatus'] = false;
    $requestData['errorDiagnostics'] = '';
  }

  $request = $requestData['Request'];
  switch ($request) {
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
    }
    break;
  case 'validateEmailRecipients':
    $mailer = new \PHPMailer(true);
    $parser = new \Mail_RFC822(null, null, null, false);

    $brokenRecipients = array();
    $recipients = $parser->parseAddressList($requestData['Recipients']);
    $parseError = $parser->parseError();
    if ($parseError !== false) {
      \OCP\Util::writeLog(Config::APP_NAME,
                          "Parse-error on email address list: ".
                          vsprintf($parseError['message'], $parseError['data']),
                          \OCP\Util::DEBUG);
      // We report the entire string.
      $brokenRecipients[] = L::t($parseError['message'], $parseError['data']);
    } else {
      \OCP\Util::writeLog(Config::APP_NAME,
                          "Parsed address list: ".
                          print_r($recipients, true),
                          \OCP\Util::DEBUG);
      foreach ($recipients as $emailRecord) {
        $email = $emailRecord->mailbox.'@'.$emailRecord->host;
        $name  = $emailRecord->personal;
        if ($name == '') {
          $recipient = $email;
        } else {
          $recipient = $name.' <'.$email.'>';
        }
        if (!$mailer->validateAddress($email)) {
          $brokenRecipients[] = htmlspecialchars($recipient);
        }
      }
    }
    $requestData['brokenRecipients'] = $brokenRecipients;
    break;
  default:
    throw new \InvalidArgumentException(L::t("Unknown request: `%s'.", $request));
  }
  
  $debugText .= ob_get_contents();
  @ob_end_clean();
    
  OCP\JSON::success(
    array('data' => array('projectName' => $projectName,
                          'projectId' => $projectId,
                          'request' => $request,
                          'requestData' => $requestData,
                          'debug' => 'id: '.$projectId.' name: '.$projectName.' '.htmlspecialchars($debugText))));

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
