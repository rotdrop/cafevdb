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
 * Connection to Owncloud Contacts app.
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

  $_GET = array(); //< CGI GET variables are disabled.

  $debugText = ''; //< Debug text for error diagnostics.
  $messageText = ''; //< Message text for status messages.

  if (Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }  

  // Get some common post data, rest has to be handled by the
  // recipients and the sender class.
  $projectId   = Util::cgiValue('ProjectId', -1); //< Project id, if any.
  $projectName = Util::cgiValue('ProjectName', ''); //< Project name, if any

  $freeForm  = Util::cgiValue('FreeFormRecipients', ''); //< Free form recipients from Cc: or Bcc:

  // Convert the free-form input to an array (possibly)
  $parser = new \Mail_RFC822(null, null, null, false); //< RFC822 parser for address verification.
  $recipients = $parser->parseAddressList($freeForm); //< Normalized recipients as array.
  $parseError = $parser->parseError(); //< Status from RFC822 parser.
  if ($parseError !== false) {
    \OCP\Util::writeLog(Config::APP_NAME,
                        "Parse-error on email address list: ".
                        vsprintf($parseError['message'], $parseError['data']),
                        \OCP\Util::DEBUG);
  }
  $freeForm = array();
  foreach($recipients as $emailRecord) {
    $email = $emailRecord->mailbox.'@'.$emailRecord->host;
    $name  = $emailRecord->personal;
    $freeForm[$email] = $name;
  }

  // Fetch all known address-book contacts with email
  $bookContacts = Contacts::emailContacts(); //<

  //\OCP\Util::writeLog(Config::APP_NAME, 'ADDRBOOK: '.print_r($bookContacts, true), \OC_LOG::DEBUG);
  //\OCP\Util::writeLog(Config::APP_NAME, 'ADDRBOOK: '.print_r($freeForm, true), \OC_LOG::DEBUG);

  $addressBookEmails = array();
  foreach($bookContacts as $entry) {
    $addressBookEmails[$entry['email']] = $entry['name'];
  }

  // Convert the free-form input in "book-format", but exclude those
  // contacts already present in the address-book in order not to list
  // contacts twice.
  $formContacts = array();
  foreach($freeForm as $email => $name) {
    if (isset($addressBookEmails[$email]) /* && $addressBookEmails[$email] == $name*/) {
      // FIXME: maybe "give a damn" on the name ...
      continue;
    }
    $formContacts[] = array('email' => $email,
                            'name' => $name,
                            'addressBook' => L::t('Form Input'),
                            'class' => 'free-form');
  }

  // The total options list is the union of the (remaining) free-form
  // addresses and the address-book entries
  $emailOptions = array_merge($formContacts, $bookContacts);

  // Now convert it into a form Navigation::selectOptions()
  // understands
  $selectOptions = array();
  foreach($emailOptions as $entry) {
    $email = $entry['email'];
    if ($entry['name'] == '') {
      $displayName = $email;
    } else {
      $displayName = $entry['name'].' <'.$email.'>';
    }
    
    $option = array('value' => $email,
                    'name' => $displayName,
                    'flags' => isset($freeForm[$email]) ? Navigation::SELECTED : 0,
                    'group' => $entry['addressBook']);
    if (isset($entry['class'])) {
      $option['groupClass'] = $entry['class'];
    }
    $selectOptions[] = $option;
  }

  \OCP\Util::writeLog(Config::APP_NAME, 'ADDRBOOK: '.print_r($selectOptions, true), \OC_LOG::DEBUG);

  //$phpMailer = new \PHPMailer(true); could validate addresses here

  $tmpl = new OCP\Template('cafevdb', 'addressbook');

  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);
  $tmpl->assign('EmailOptions', $selectOptions);

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
