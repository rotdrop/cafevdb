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

    $cgiPfx = Config::$pmeopts['cgi']['prefix']['data'];
    
    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    $class   = Util::cgiValue('DisplayClass');
    $control = Util::cgiValue('control');

    switch($class) {
    case 'InsuranceBrokers':
      $cgiKeys = array('broker' => 'ShortName');
      $values = array();
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = Util::cgiValue($cgiPfx.$cgiKey, false);
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }

      switch($control) {
      case 'submit':
      case 'broker':
        $broker = $values['broker'];
        // No whitespace, s.v.p., and CamelCase
        $origBroker = $broker;
        
        $broker = trim($broker);
        $broker = ucwords($broker);
        $broker = preg_replace('/\s+/', '', $broker);

        if ($broker != $origBroker) {
          $infoMessage .= L::t("Broker-name has been simplified.");
          $values['broker'] = $broker;
        }
        break;
      default:
        $errorMessage = L::t("Internal error: unknown request: %s", array($control));
        break;
      }
      if ($errorMessage == '') {
        \OCP\JSON::success(
          array('data' => array_merge($values,
                                      array('message' => $infoMessage,
                                            'debug' => $debugText))));
        return true;
      }
      
      break;
    case 'InsuranceRates':
      $cgiKeys = array('broker' => 'Broker',
                       'rate' => 'Rate');
      $values = array();
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = Util::cgiValue($cgiPfx.$cgiKey, false);
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }

      switch($control) {
      case 'submit':
      case 'broker':
        $broker = $values['broker'];
        // No whitespace, s.v.p., and CamelCase
        $origBroker = $broker;
        
        $broker = trim($broker);
        $broker = ucwords($broker);
        $broker = preg_replace('/\s+/', '', $broker);

        if ($broker != $origBroker) {
          $infoMessage .= L::t("Broker-name has been simplified.");
          $values['broker'] = $broker;
        }
        if ($control != 'submit') {
          break;
        }
      case 'rate':
        $rate = $values['rate'];
        $rate = floatval($rate);
        if ($rate <= 0 || $rate > 1e-2) {
          $errorMessage = L::t('Invalid insurance rate %f, should be larger than 0 and less than 1 percent.',
                               array($rate));
        }
        break;
      default:
        $errorMessage = L::t("Internal error: unknown request: %s", array($control));
        break;
      }
      if ($errorMessage == '') {
        \OCP\JSON::success(
          array('data' => array_merge($values,
                                      array('message' => $infoMessage,
                                            'debug' => $debugText))));
        return true;
      }
      break;
    case 'InstrumentInsurance':
      // control -> name mapping
      $cgiKeys = array('musician-id' => 'MusicianId',
                       'bill-to-party' => 'BillToParty',
                       'broker-select' => 'Broker',
                       'scope-select' => 'GeographicalScope',
                       'insured-item' => 'Object',
                       'accessory' => 'Accessory',
                       'manufacturer' => 'Manufacturer',
                       'construction-year' => 'YearOfConstruction',
                       'amount' => 'InsuranceAmount');
      $values = array();
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = Util::cgiValue($cgiPfx.$cgiKey, false);
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }
      
      switch ($control) {
      case 'submit':
      case 'musician-id':
        $value = $values['musician-id'];
        if ($value === false) {
          // must not be empty
          $errorMessage = L::t('Insured musician is missing');
        } else {
          // ? check perhaps for existence, however, this is an id
          // generated from a select box with values from the DB.
        }
        if ($control != 'submit') {
          break;
        }
      case 'bill-to-party':
        $value = $values['bill-to-party'];
        if ($value !== false) {
          // ? check perhaps for existence, however, this is an id
          // generated from a select box with values from the DB.
        }
        if ($control != 'submit') {
          break;
        }
      case 'broker-select':
        $value = $values['broker-select'];
        if ($value === false) {
          // must not be empty
          $errorMessage = L::t('Insurance broker is missing.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'scope-select':
        $value = $values['scope-select'];
        if ($value === false) {
          // must not be empty
          $errorMessage = L::t('Geographical scope for the insurance is missing.');
        }
        if ($control != 'submit') { 
          break;
        }
      case 'insured-item':
        $value = $values['insured-item'];
        if ((string)$value == '') {
          $errorMessage = L::t('Insured object has not been specified.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'accessory':
        $value = $values['accessory'];
        if ($value === false) {
          // must not be empty
          $errorMessage = L::t('Object classification (instrument, accessory) is missing.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'manufacturer': 
        $value = $values['manufacturer'];
        if ($value === false || $value == '') {
          $infoMessage .= L::t("Manufacturer field is empty.");
        } else {
          // Mmmh.
        }
        if ($control != 'submit') {
          break;
        }
      case 'construction-year': 
        $value = Util::cgiValue($cgiPfx.$cgiKeys['construction-year']);
        if ((string)$value == '' || $value === (string)L::t('unknown')) {
          $infoMessage .= L::t("Construction year is unknown.");          
          $values['construction-year'] = L::t('unknown');
          // allow free-style like "ca. 1900" and such.
        /* } else if ($value != L::t('unknown') && !preg_match("/[0-9]{4}/", $value)) { */
        /*   $errorMessage = L::t("Construction year must be either a literal `%s' or a four digit year, you typed %s.", */
        /*                        array(L::t('unknown'), $value)); */
        }
        if ($control != 'submit') {
          break;
        }
      case 'amount':          
        $value = Util::cgiValue($cgiPfx.$cgiKeys['amount']);
        if ((string)$value == '') {
          $errorMessage = L::t('The insurance amount is missing.');
        } else {
          $LocaleInfo = localeconv();
          $value = str_replace($LocaleInfo["mon_thousands_sep"] , "", $value);
          $value = str_replace($LocaleInfo["mon_decimal_point"] , ".", $value);
          if (!is_numeric($value)) {
            $errorMessage = L::t('Insurance amount should be a mere number');
          }
          if ((string)floatval($value) != (string)intval($value)) {
            $errorMessage = L::t('Insurance amount should be an integral number');
          }
        }
        break; // break at last item
      default:
        $errorMessage = L::t("Internal error: unknown request");
        break;
      }

      if ($errorMessage == '') {
        \OCP\JSON::success(
          array('data' => array_merge($values,
                                      array('message' => $infoMessage,
                                            'debug' => $debugText))));
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
          'data' => array('error' => L::t("missing or wrong arguments"),
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
