<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * Be nasty to the average DAU and refuse to add nonsense phone numbers.
 */
namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Error::exceptions(true);
  $debugText = '';
  $message = '';

  ob_start();

  try {
    $_GET = array(); // only post is allowed

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    Config::init();

    $dataPfx = Config::$pmeopts['cgi']['prefix']['data'];

    $mobile    = Util::cgiValue($dataPfx.'MobilePhone', '');
    $fixedLine = Util::cgiValue($dataPfx.'FixedLinePhone', '');

    $mobileIsMobile = false;
    $fixedIsMobile = false;

    $mobileValid = false;
    $fixedValid = false;

    // Format and possibly switch roles; i.e.: try to compensate for
    // the DAU.
    if (PhoneNumbers::validate($mobile)) {
      $mobile = PhoneNumbers::format();
      $mobileIsMobile = PhoneNumbers::isMobile();
      $mobileValid = true;
    } else if ($mobile != '') {
      $message .= L::t('The phone number %s does not appear to be a valid phone number. ',
                       array($mobile));
    }

    if (PhoneNumbers::validate($fixedLine)) {
      $fixedLine = PhoneNumbers::format();
      $fixedIsMobile = PhoneNumbers::isMobile();
      $fixedValid = true;
    } else if ($fixedLine != '') {
      $message .= L::t('The phone number %s does not appear to be a valid phone number. ',
                       array($fixedLine));
    }

    if (!$fixedValid && $mobileValid && !$mobileIsMobile) {
      $tmp = $fixedLine;
      $fixedLine = $mobile;
      $mobile = $tmp;
      $fixedValid = true;
      $mobileValid = false;
      $fixedIsMobile = false;
      $message = L::t('This (%s) is a fixed line phone number, injecting it in the correct column.',
                     array($fixedLine));
    }
    if (!$mobileValid && $fixedLine != '' && $fixedIsMobile) {
      $tmp = $mobile;
      $mobile = $fixedLine;
      $fixedLine = $tmp;
      $mobileIsMobile = true;
      $fixedIsMobile = false;
      $message = L::t('This (%s) is a mobile phone number, injecting it in the correct column.',
                     array($mobile));
    }
    if ($mobile != '' && $fixedLine != '' && !$mobileIsMobile && $fixedIsMobile) {
      $tmp = $fixedLine;
      $fixedLine = $mobile;
      $mobile = $tmp;
    } else if ($mobileValid && !$mobileIsMobile) {
      $message .= L::t('The phone number %s does not appear to be a mobile phone number. ',
                       array($mobile));
    }

    \OCP\JSON::success(
      array('data' => array('message' => nl2br($message),
                            'mobilePhone' => $mobile,
                            'fixedLinePhone' => $fixedLine,
                            'debug' => $debugText)));

    return true;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Musician Form').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
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

}

?>