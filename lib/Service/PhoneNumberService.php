<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use \libphonenumber\PhoneNumberUtil;

/**
 * Telephone number validation
 */
class PhoneNumberService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \libphonenumber\PhoneNumberUtil */
  private $backend = false;
  private $currentNumber = false;
  private $currentObject = false;
  private $currentRegion = false;
  private $defaultPrefix = false;
  private $defaultRegion = false;
  private $numberTypes = false;

  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->backend = PhoneNumberUtil::getInstance();

    $orgPhone = $this->getConfigValue('phoneNumber', '');
    if ($orgPhone != '') {
      $orgObject = $this->backend->parse($orgPhone, null);
      $this->defaultRegion = $this->backend->getRegionCodeForNumber($orgObject);
      $nationalSignificantNumber = $this->backend->getNationalSignificantNumber($orgObject);
      $areaCodeLength = $this->backend->getLengthOfGeographicalAreaCode($orgObject);
      if ($areaCodeLength > 0) {
        $this->defaultPrefix = substr($nationalSignificantNumber, 0, $areaCodeLength);
      } else {
        $this->defaultPrefix = '';
      }
    } else {
      $this->defaultPrefix = '';
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
      $country = locale_get_region($locale);
      $this->logDebug($language.' / '.$country);
      $this->defaultRegion = $country;
    }

    $r = new \ReflectionClass('\libphonenumber\PhoneNumberType');
    $this->numberTypes = array_flip($r->getConstants());
    foreach ($this->numberTypes as $id => $name) {
      if ($name != 'UAN' && $name != 'VOIP') {
        $this->numberTypes[$id] = $this->l->t(strtolower(str_replace('_', ' ', $name)));
      }
    }

    if (false) {
      $this->l->t('fixed line');
      $this->l->t('mobile');
      $this->l->t('fixed line or mobile');
      $this->l->t('premium rate');
      $this->l->t('shared cost');
      $this->l->t('VOIP');
      $this->l->t('personal number');
      $this->l->t('pager');
      $this->l->t('UAN');
      $this->l->t('unknown');
      $this->l->t('emergency');
      $this->l->t('voicemail');
      $this->l->t('short code');
      $this->l->t('standard rate');
    }
  }

  /**
   * Add the default area code if the number does not start with +
   * or 0, and do some other normalization, strip spaces etc
   */
  public function normalize($number)
  {

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
      if (!preg_match('!^[0+]!', $number) && $this->defaultPrefix) {
        $number = '0' . $this->defaultPrefix . $number;
      }
    }

    return $number;
  }

  public function validate($number, $region = null)
  {
    // add local area code and remove some of the usual human fuzzy
    // input ...
    $number = $this->normalize($number);

    if ($number === '') {
      return false;
    }

    if (!$region && $this->defaultRegion) {
      $region = $this->defaultRegion;
    }

    try {

      if ($number != $this->currentNumber || $region != $this->currentRegion) {
        $this->currentNumber = $number;
        $this->currentRegion = $region;
        $this->currentObject = $this->backend->parse($this->currentNumber, $this->currentRegion);
      }

      $result = $this->backend->isValidNumber($this->currentObject);

    } catch (\Throwabled $e) {
      $result = false;
    }

    return $result;
  }

  public function format($number = null, $region = null)
  {
    if ($number !== null && !$this->validate($number, $region)) {
      return '';
    }

    return $this->backend->format($this->currentObject,
                                  \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
  }

  public function isMobile($number = null, $region = null)
  {
    if ($number !== null && !$this->validate($number, $region)) {
      return false;
    }

    return $this->backend->getNumberType($this->currentObject) == \libphonenumber\PhoneNumberType::MOBILE;
  }

  /**
   * Return new-line formatted meta-data for the current or the given
   * number and region.
   *
   * @param string|null $number The number to parse.
   *
   * @param string|null $region The region code if not included in the number.
   *
   * @param string $nl The new-line delimiter.
   *
   * @return string Empty string if the number cannot be validated,
   * newline separated meta-data else.
   */
  public function metaData($number = null, $region = null, string $nl = "\n")
  {
    if ($number !== null && !$this->validate($number, $region)) {
      return '';
    }

    $carrierMapper = \libphonenumber\PhoneNumberToCarrierMapper::getInstance();
    $timeZoneMapper = \libphonenumber\PhoneNumberToTimeZonesMapper::getInstance();
    $geocoder = \libphonenumber\geocoding\PhoneNumberOfflineGeocoder::getInstance();
    $locale = $this->getLocale();

    $zone = $timeZoneMapper->getTimeZonesForNumber($this->currentObject);
    if (isset($zone[0])) {
      $zone = $zone[0];
    } else {
      $zone = $this->l->t('unknown');
    }

    $meta = '';
    $meta .= $this->l->t('Type    : %s', [ $this->numberTypes[$this->backend->getNumberType($this->currentObject)] ]).$nl;
    $meta .= $this->l->t('Country : %s', [ $this->backend->getRegionCodeForNumber($this->currentObject)]).$nl;
    $meta .= $this->l->t('Location: %s', [ $geocoder->getDescriptionForNumber($this->currentObject, $locale) ]).$nl;
    $meta .= $this->l->t('TimeZone: %s', [ $zone ]).$nl;
    $provider = $carrierMapper->getNameForNumber($this->currentObject, $locale);
    if ($provider !== '') {
      $meta .= $this->l->t('Provider: %s', [ $provider ]).$nl;
    }

    return $meta;
  }

};
