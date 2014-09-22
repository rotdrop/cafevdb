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
  $projectName = Util::cgiValue('Project', ''); // the name

  $recipientsFilter = new EmailRecipientsFilter(Config::$pmeopts);  

  $tmpl = new OCP\Template('cafevdb', 'emailform');

  $tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
  $tmpl->assign('uploadMaxHumanFilesize',
                OCP\Util::humanFileSize(Util::maxUploadSize()), false);
  $tmpl->assign('requesttoken', \OCP\Util::callRegister());

  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);

  // Needed for the editor
  $tmpl->assign('templateName', '');
  $tmpl->assign('templateNames', array());
  $tmpl->assign('BCC', '');
  $tmpl->assign('CC', '');
  $tmpl->assign('mailTag', '[CAFEV-Blah]');
  $tmpl->assign('subject', '');
  $tmpl->assign('message', '');
  $tmpl->assign('sender', '');
  $tmpl->assign('catchAllEmail', '');
  $tmpl->assign('fileAttach', array());
  
  // Needed for the recipient selection
  $tmpl->assign('RecipientsFormData', $recipientsFilter->formData());
  $tmpl->assign('FilterHistory', $recipientsFilter->filterHistory());
  $tmpl->assign('MemberStatusFilter', $recipientsFilter->memberStatusFilter());
  $tmpl->assign('BasicRecipientsSet', $recipientsFilter->basicRecipientsSet());
  $tmpl->assign('InstrumentsFilter', $recipientsFilter->instrumentsFilter());
  $tmpl->assign('EmailRecipientsChoices', $recipientsFilter->emailRecipientsChoices());
  $tmpl->assign('MissingEmailAddresses', $recipientsFilter->missingEmailAddresses());

  $html = $tmpl->fetchPage();

  $debugText .= ob_get_contents();
  @ob_end_clean();
  
  OCP\JSON::success(
    array('data' => array('contents' => $html,
                          'projectName' => $projectName,
                          'projectId' => $projectId,
                          'debug' => $debugText)));
  
  return true;

} catch (\Exception $e) {

  $debugText .= ob_get_contents();
  @ob_end_clean();

  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'),
        'debug' => $debugText)));
  return false;
}

?>
