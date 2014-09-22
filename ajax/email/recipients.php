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

  if (true || Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }

  $projectId      = Util::cgiValue('ProjectId', -1);
  $projectName    = Util::cgiValue('ProjectName', ''); // the name

  // TODO: check recipientsData

  // We only need to manipulate the options for the select box. The
  // other form elements are updated accordingly by their java-script
  // libraries or by the web-browser.

  $recipientsFilter = new EmailRecipientsFilter(Config::$pmeopts);

  if ($recipientsFilter->reloadState()) {
    // Rebuild the entire page
    $recipientsOptions = array();
    $missingEmailAddresses = '';

    $tmpl = new OCP\Template('cafevdb', 'part.emailform.recipients');
    $tmpl->assign('ProjectName', $projectName);
    $tmpl->assign('ProjectId', $projectId);
    
    // Needed for the recipient selection
    $tmpl->assign('RecipientsFormData', $recipientsFilter->formData());
    $filterHistory = $recipientsFilter->filterHistory();
    $tmpl->assign('FilterHistory', $filterHistory);
    $tmpl->assign('MemberStatusFilter', $recipientsFilter->memberStatusFilter());
    $tmpl->assign('BasicRecipientsSet', $recipientsFilter->basicRecipientsSet());
    $tmpl->assign('InstrumentsFilter', $recipientsFilter->instrumentsFilter());
    $tmpl->assign('EmailRecipientsChoices', $recipientsFilter->emailRecipientsChoices());
    $tmpl->assign('MissingEmailAddresses', $recipientsFilter->missingEmailAddresses());

    $contents = $tmpl->fetchPage();
  } else {
    $recipientsChoices = $recipientsFilter->emailRecipientsChoices();
    $recipientsOptions = Navigation::selectOptions($recipientsChoices);
    $missingEmailAddresses = $recipientsFilter->missingEmailAddresses();
    $missingEmailAddresses = htmlspecialchars(implode(', ', $missingEmailAddresses));
    $filterHistory = $recipientsFilter->filterHistory();
    $contents = '';
  }
  
  $debugText .= ob_get_contents();
  @ob_end_clean();
    
  OCP\JSON::success(
    array('data' => array('projectName' => $projectName,
                          'projectId' => $projectId,
                          'contents' => $contents,
                          'recipientsOptions' => $recipientsOptions,
                          'missingEmailAddresses' => $missingEmailAddresses,
                          'filterHistory' => $filterHistory,
                          'debug' => $debugText.$filterHistory)));

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
        'message' => L::t('Error, caught an exception. '.
                          'Please copy the displayed text and send it by email to the web-master.'),
        'debug' => $debugText)));
  return false;
}

?>
