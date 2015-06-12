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

    $country = Util::cgiValue($dataPfx.'Land');
    $city = Util::cgiValue($dataPfx.'Stadt');
    $street = Util::cgiValue($dataPfx.'Strasse');
    $zip = Util::cgiValue($dataPfx.'Postleitzahl');
    $active = Util::cgiValue('ActiveElement');

    $city = Util::normalizeSpaces($city);
    $zip = Util::normalizeSpaces($zip);
    $street = Util::normalizeSpaces($street);

    $locations = GeoCoding::cachedLocations($zip, $city, $country);
    //$message = print_r($locations, true);

    if (count($locations) == 0 && ($city || $zip)) {
      // retry remotely with given country
      $locations = GeoCoding::remoteLocations($zip, $city, $country);
      if (count($locations) == 0) {
        // retry without country, i.e. on same continent
        $locations = GeoCoding::cachedLocations($zip, $city, null);
        if (count($locations) == 0) {
          // still no luck: try a world search
          $locations = GeoCoding::cachedLocations($zip, $city, '%');
          if (count($locations) == 0) {
            // retry with remote service, on this continent ...
            $locations = GeoCoding::remoteLocations($zip, $city, null);
          }
        }
      }
    }

    if (false) {
    if ($active === $dataPfx.'Stadt' && $city) {
      $singleLoc = GeoCoding::cachedLocations(null, $city, $country);
      if (count($singleLoc) == 0) {
        $singleLoc = GeoCoding::remoteLocations(null, $city, $country);
        if (count($singleLoc) == 0) {
          $singleLoc = GeoCoding::cachedLocations(null, $city, null);
          if (count($singleLoc) == 0) {
            $singleLoc = GeoCoding::cachedLocations(null, $city, '%');
            if (count($singleLoc) == 0) {
              $singleLoc = GeoCoding::remoteLocations(null, $city, null);
            }
          }
        }
      }
      $locations = array_merge($locations, $singleLoc);
    } else if ($active === $dataPfx.'Postleitzahl' && $zip) {
      $singleLoc = GeoCoding::cachedLocations($zip, null, $country);
      if (count($singleLoc) == 0) {
        $singleLoc = GeoCoding::remoteLocations($zip, null, $country);
        if (count($singleLoc) == 0) {
          $singleLoc = GeoCoding::cachedLocations($zip, null, null);
          if (count($singleLoc) == 0) {
            $singleLoc = GeoCoding::cachedLocations($zip, null, '%');
            if (count($singleLoc) == 0) {
              $singleLoc = GeoCoding::remoteLocations($zip, null, null);
            }
          }
        }
      }
      $locations = array_merge($locations, $singleLoc);
    }
    }

    $cities = array();
    $postalCodes = array();
    $countries = array();
    foreach($locations as $location) {
      $cities[] = $location['Name'];
      $postalCodes[] = $location['PostalCode'];
      $countries[] = $location['Country'];
    };
    sort($cities, SORT_LOCALE_STRING);
    sort($postalCodes, SORT_LOCALE_STRING);
    sort($countries);

    $cities = array_values(array_unique($cities, SORT_LOCALE_STRING));
    $postalCodes = array_values(array_unique($postalCodes, SORT_LOCALE_STRING));
    $countries = array_values(array_unique($countries));

    //$message .= print_r($countries, true);

    \OCP\JSON::success(
      array('data' => array('message' => nl2br($message),
                            'city' => $city,
                            'zip' => $zip,
                            'street' => $street,
                            'suggestions' => array('cities' => $cities,
                                                   'postalCodes' => $postalCodes,
                                                   'countries' => $countries),
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