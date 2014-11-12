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

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Error::exceptions(true);
  $debugText = '';
  $errorMessage = '';
  $infoMessage = '';

  ob_start();

  try {
    $_GET = array(); // only post is allowed

    Config::init();
    
    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    $class   = Util::cgiValue('DisplayClass');
    $control = Util::cgiValue('control');

    switch($class) {
    case 'InsuranceRates':
      $broker = Util::cgiValue('PME_data_Broker');
      $rate = Util::cgiValue('PME_data_Rate');
      switch($control) {
      case 'broker':
        // No whitespace, s.v.p., and CamelCase
        $origBroker = $broker;
        
        $broker = trim($broker);
        $broker = ucfirst($broker);
        $broker = preg_replace('/\s+/', '', $broker);

        if ($broker != $origBroker) {
          $infoMessage .= L::t("Broker-name has been simplified.");
        }
        break;
      case 'rate':
        $rate = floatval($rate);
        if ($rate <= 0 || $rate > 1e-2) {
          $errorMessage = L::t('Invalid insurance rate %f, should be larger than 0 and less than 1 percent.',
                               array($rate));
        }
        break;
      default:
        $errorMessage = L::t("Internal error: unknown request");
        break;
      }
      if ($errorMessage == '') {
        \OCP\JSON::success(
          array('data' => array('broker' => $broker,
                                'rate' => $rate,
                                'message' => $infoMessage,
                                'debug' => $debugText)));
        return true;
      }
      break;
    default:
      $errorMessage = L::t("Internal error: unknown request");
      break;
    }
    
    if ($errorMessage != '') {
      $debugText .= ob_get_contents();
      @ob_end_clean();
      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("missing arguments"),
                          'message' => $errorMessage,
                          'debug' => $debugText)));
      return false;
    }
    
  } catch (\Exception $e) {
    
    unset($recipientsFilter);

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
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

} //namespace CAFVDB

?>
