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

try {

  ob_start();

  Error::exceptions(true);
  Config::init();

  $_GET = array();

  $debugText = '';
  $messageText = '';

  if (Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }

  $projectId   = Util::cgiValue('ProjectId', -1);
  $projectName = Util::cgiValue('ProjectName', ''); // the name
  $request     = Util::cgiValue('Request', 'update');
  $formElement = Util::cgiValue('FormElement', 'everything');

  $recipientsFilter = new EmailRecipientsFilter();
  $composer = new EmailComposer($recipientsFilter->selectedRecipients());

  switch ($request) {
  case 'update':
    if ($formElement == 'everything') {
      $elementData = '';
      $formElement = '';

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
      $tmpl->assign('fileAttach', array());
      $tmpl->assign('ComposerFormData', $composer->formData());  

      $elementData = $tmpl->fetchPage();
    } else {    
      $contents = '';

      switch ($formElement) {
      case 'TO':
        $elementData = $composer->toString();
        break;
      default:
        throw new \InvalidArgumentException(L::t("Unknown form element: `%s'.", $formElement));
      }
    }
    $requestData = array('formElement' => $formElement,
                         'elementData' => $elementData);
    break;
  case 'setTemplate':
    $requestData = array('templateName' => $composer->currentEmailTemplate(),
                         'message' => $composer->messageText());
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
                          'debug' => $debugText)));

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
        'debug' => $debugText)));
 
  return false;
}

?>
