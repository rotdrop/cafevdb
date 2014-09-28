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

/**@file
 * Load a PME table without outer controls, intended usage are
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
  $addressBookCandidates = Util::cgiValue('AddressBookCandidates', array());

  \OCP\Util::writeLog(Config::APP_NAME, 'SAVE: '.print_r($_POST, true), \OC_LOG::DEBUG);
  \OCP\Util::writeLog(Config::APP_NAME, 'SAVE: '.print_r($addressBookCandidates, true), \OC_LOG::DEBUG);

  $formContacts = array();  
  foreach($addressBookCandidates as $record) {
    // This is already pre-parsed. If there is a natural name for the
    // person, then it is the thing until the first occurence of '<'.
    $text = $record['text']; // use html?
    $name = strchr($text, '<', true);
    if ($name !== false) {
      $name = trim($name);
    } else {
      $name = '';
    }
    $email = $record['value'];
    $formContacts[] = array(
      'email' => $email,
      'name' => $name,
      'display' => htmlspecialchars($name.' <'.$email.'>')
      );
  }
  $failedContacts = array();
  foreach($formContacts as $contact) {
    if (Contacts::addEmailContact($contact) === false) {
      $failedContacts[] = $contact['display'];
    }
  }

  $debugText .= ob_get_contents();
  @ob_end_clean();
  
  if (count($failedContacts) == 0) {
    OCP\JSON::success(array('data' => array('debug' => $debugText)));
    return true;
  } else {
    OCP\JSON::error(
      array('data' => array(
              'message' => L::t('The following contacts could not be stored: %s',
                                array(implode(', ', $failedContacts))),
              'debug' => $debugText)));
    return false;
  }

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
