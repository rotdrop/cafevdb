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
use CAFEVDB\Contacts;

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

  $freeForm  = Util::cgiValue('FreeFormRecipients', '');

  // Convert the free-form input to an array (possibly)
  $freeForm = Contacts::parseAddrListToArray($freeForm);

  // Fetch all known address-book contacts with email
  $bookContacts = Contacts::emailContacts();

  // The total options list is the union of the (remaining) free-form
  // addresses and the address-book entries
  $emailOptions = array_merge($bookContacts, $freeForm);

  foreach ($emailOptions as $email => $name) {
    if ($name == '') {
      $emailOptions[$email] = $email;
    } else {
      $emailOptions[$email] = $name.' <'.$email.'>';
    }
  }
  asort($emailOptions);

  //$phpMailer = new \PHPMailer(true); could validate addresses here

  $tmpl = new OCP\Template('cafevdb', 'addressbook');

  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);
  $tmpl->assign('EmailOptions', $emailOptions);
  $tmpl->assign('EmailSelection', $freeForm);

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
        'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'),
        'debug' => $debugText)));
  return false;
}

?>
