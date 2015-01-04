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
 * Lead-in handler for email-form.
 */

namespace CAFEVDB {  

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

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

    // Get some common post data, rest has to be handled by the
    // recipients and the sender class.
    $projectId   = Util::cgiValue('ProjectId', -1);
    $projectName = Util::cgiValue('ProjectName', '');

    $recipientsFilter = new EmailRecipientsFilter();
    $recipients = $recipientsFilter->selectedRecipients();
    $composer = new EmailComposer($recipients);

    $tmpl = new \OCP\Template('cafevdb', 'emailform');

    $tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
    $tmpl->assign('uploadMaxHumanFilesize',
                  \OCP\Util::humanFileSize(Util::maxUploadSize()), false);
    $tmpl->assign('requesttoken', \OCP\Util::callRegister());

    $tmpl->assign('ProjectName', $projectName);
    $tmpl->assign('ProjectId', $projectId);

    // Provide enough data s.t. a form-reload will bump the user to the
    // form the email-dialog was opened from. Ideally, we intercept the
    // form submit in javascript and simply close the dialog. Most of
    // the stuff below is a simple safe-guard.
    $pmepfx   = Config::$pmeopts['cgi']['prefix']['sys'];
    $emailKey = $pmepfx.'mrecs';
    $tmpl->assign('FormData', array('ProjectName' => $projectName,
                                    'ProjectId' => $projectId,
                                    'Template' => Util::cgiValue('Template', ''),
                                    'DisplayClass' => Util::cgiValue('DisplayClass', ''),
                                    'requesttoken' => \OCP\Util::callRegister(),
                                    $emailKey => Util::cgiValue($emailKey, array()))
      );

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
    $tmpl->assign('eventAttachments', $composer->eventAttachments());
    $tmpl->assign('ComposerFormData', $composer->formData());
  
    // Needed for the recipient selection
    $tmpl->assign('RecipientsFormData', $recipientsFilter->formData());
    $history = $recipientsFilter->filterHistory();
    $tmpl->assign('FilterHistory', $recipientsFilter->filterHistory());
    $tmpl->assign('MemberStatusFilter', $recipientsFilter->memberStatusFilter());
    $tmpl->assign('BasicRecipientsSet', $recipientsFilter->basicRecipientsSet());
    $tmpl->assign('InstrumentsFilter', $recipientsFilter->instrumentsFilter());
    $tmpl->assign('EmailRecipientsChoices', $recipientsFilter->emailRecipientsChoices());
    $tmpl->assign('MissingEmailAddresses', $recipientsFilter->missingEmailAddresses());

    $html = $tmpl->fetchPage();

    $debugText .= ob_get_contents();
    @ob_end_clean();
  
    \OCP\JSON::success(
      array('data' => array('contents' => $html,
                            'projectName' => $projectName,
                            'projectId' => $projectId,
                            'filterHistory' => $history,
                            'debug' => $debugText)));

    unset($recipientsFilter);
    unset($composer);
  
    return true;

  } catch (\Exception $e) {

    unset($recipientsFilter);
    unset($composer);
  
    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
      '&body='.rawurlencode($exceptionText."\r\n".$trace."\r\n"."_POST[] = ".print_r($_POST, true));
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
