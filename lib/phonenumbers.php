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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /**Telephone number validation */
  class PhoneNumbers
  {
    protected static $backend = false;
    protected static $currentNumber = false;
    protected static $currentObject = false;
    protected static $currentRegion = false;
    protected static $defaultPrefix = false;
    protected static $defaultRegion = false;
    protected static $numberTypes = false;

    static protected function init()
    {
      if (!self::$backend) {
        self::$backend = \libphonenumber\PhoneNumberUtil::getInstance();
      } else {
        return;
      }

      $orgPhone = Config::getValue('phoneNumber', '');
      if ($orgPhone != '') {
        $orgObject = self::$backend->parse($orgPhone, null);
        self::$defaultRegion = self::$backend->getRegionCodeForNumber($orgObject);
        $nationalSignificantNumber = self::$backend->getNationalSignificantNumber($orgObject);
        $areaCodeLength = self::$backend->getLengthOfGeographicalAreaCode($orgObject);
        if ($areaCodeLength > 0) {
          self::$defaultPrefix = substr($nationalSignificantNumber, 0, $areaCodeLength);
        } else {
          self::$defaultPrefix = '';
        }
      } else {
        self::$defaultPrefix = '';
        self::$defaultRegion = 'ZZ';
      }

      $r = new \ReflectionClass('\libphonenumber\PhoneNumberType');
      self::$numberTypes = array_flip($r->getConstants());
      foreach (self::$numberTypes as $id => $name) {
        if ($name != 'UAN' && $name != 'VOIP') {
          self::$numberTypes[$id] = L::t(strtolower(str_replace('_', ' ', $name)));
        }
      }

      if (false) {
        L::t('fixed line');
        L::t('mobile');
        L::t('fixed line or mobile');
        L::t('premium rate');
        L::t('shared cost');
        L::t('VOIP');
        L::t('personal number');
        L::t('pager');
        L::t('UAN');
        L::t('unknown');
        L::t('emergency');
        L::t('voicemail');
        L::t('short code');
        L::t('standard rate');
      }
    }

    /**Add the default area code if the number does not start with +
     * or 0, and do some other normalization, strip spaces etc
     */
    static public function normalize($number)
    {
      self::init();

      // convert html entities back to their character expressions
      $number = html_entity_decode($number);

      // convert non-breaking unicode space to normal space
      $number = str_replace("\xc2\xa0", "\x20", $number);

      // remove space
      $number = preg_replace('/\s+/', '', $number);

      // remove brackets
      $number = preg_replace('/^\((\+\d\d)\)/', '$1', $number);

      // remove intermediate (0) stuff
      $number = preg_replace('/^([^(]*\d+[^(]*)\(0\)/', '$1', $number);

      // replace leading dounle 00 by + as this seems to be better
      // understood by the libphonenumber backend.
      $number = preg_replace('/^00/', '+', $number);

      if ($number !== '') {
        // add local area code
        if (!preg_match('!^[0+]!', $number) && self::$defaultPrefix) {
          $number = '0' . self::$defaultPrefix . $number;
        }
      }

      return $number;
    }

    static public function validate($number, $region = null)
    {
      self::init();

      // add local area code and remove some of the usual human fuzzy
      // input ...
      $number = self::normalize($number);

      if ($number === '') {
        return false;
      }

      if (!$region && self::$defaultRegion) {
        $region = self::$defaultRegion;
      }

      try {

        if ($number != self::$currentNumber || $region != self::$currentRegion) {
          self::$currentNumber = $number;
          self::$currentRegion = $region;
          self::$currentObject = self::$backend->parse(self::$currentNumber, self::$currentRegion);
        }

        $result = self::$backend->isValidNumber(self::$currentObject);

      } catch (\Exception $e) {
        $result = false;
      }

      return $result;
    }

    static public function format($number = null, $region = null)
    {
      self::init();

      if ($number !== null && !self::validate($number, $region)) {
        return '';
      }

      return self::$backend->format(self::$currentObject,
                                    \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
    }

    static public function isMobile($number = null, $region = null)
    {
      self::init();

      if ($number !== null && !self::validate($number, $region)) {
        return false;
      }

      return self::$backend->getNumberType(self::$currentObject) == \libphonenumber\PhoneNumberType::MOBILE;
    }

    static public function metaData($number = null, $region = null)
    {
      self::init();

      if ($number !== null && !self::validate($number, $region)) {
        return false;
      }

      $carrierMapper = \libphonenumber\PhoneNumberToCarrierMapper::getInstance();
      $timeZoneMapper = \libphonenumber\PhoneNumberToTimeZonesMapper::getInstance();
      $geocoder = \libphonenumber\geocoding\PhoneNumberOfflineGeocoder::getInstance();
      $locale = Util::getLocale();

      $zone = $timeZoneMapper->getTimeZonesForNumber(self::$currentObject);
      if (isset($zone[0])) {
        $zone = $zone[0];
      } else {
        $zone = L::t('unknown');
      }

      $nl = "\n";
      $meta = '';
      $meta .= L::t('Type    : %s', array(self::$numberTypes[self::$backend->getNumberType(self::$currentObject)])).$nl;
      $meta .= L::t('Country : %s', array(self::$backend->getRegionCodeForNumber(self::$currentObject))).$nl;
      $meta .= L::t('Location: %s', array($geocoder->getDescriptionForNumber(self::$currentObject, $locale))).$nl;
      $meta .= L::t('TimeZone: %s', array($zone)).$nl;
      $provider = $carrierMapper->getNameForNumber(self::$currentObject, $locale);
      if ($provider !== '') {
        $meta .= L::t('Provider: %s', array($provider)).$nl;
      }

      return $meta;
    }

  };

} // namespace CAFEVDB

?>
