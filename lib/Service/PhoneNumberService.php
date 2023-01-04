<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2015, 2020, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use ReflectionClass;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberFormat;

/**
 * Telephone number validation
 */
class PhoneNumberService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\RotDrop\Toolkit\Traits\FakeTranslationTrait;

  /** @var \libphonenumber\PhoneNumberUtil */
  private $backend = false;
  private $currentNumber = false;
  private $currentObject = false;
  private $currentRegion = false;
  private $defaultPrefix = false;
  private $defaultRegion = false;
  private $numberTypes = false;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(ConfigService $configService)
  {
    $this->configService = $configService;
    $this->l = $this->l10n();
  }
  // phpcs:enable

  /** @return void */
  private function initializeDefaults():void
  {
    $this->backend = PhoneNumberUtil::getInstance();

    $orgPhone = $this->getConfigValue('phoneNumber', '');
    if ($orgPhone != '') {
      try {
        $orgObject = $this->backend->parse($orgPhone, null);
        $this->defaultRegion = $this->backend->getRegionCodeForNumber($orgObject);
        $nationalSignificantNumber = $this->backend->getNationalSignificantNumber($orgObject);
        $areaCodeLength = $this->backend->getLengthOfGeographicalAreaCode($orgObject);
        if ($areaCodeLength > 0) {
          $this->defaultPrefix = substr($nationalSignificantNumber, 0, $areaCodeLength);
        } else {
          $this->defaultPrefix = '';
        }
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to parse non-empty orga-phone-number: ' . $orgPhone);
        $orgPhone = '';
      }
    }

    if (empty($orgPhone)) {
      $this->defaultPrefix = '';
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
      $country = locale_get_region($locale);
      $this->logDebug($language.' / '.$country);
      $this->defaultRegion = $country;
    }
  }

  /** @return PhoneNumberUtil */
  private function getBackend():PhoneNumberUtil
  {
    if ($this->backend === false) {
      $this->initializeDefaults();
    }
    return $this->backend;
  }

  /** @return string */
  private function getDefaultRegion()
  {
    if ($this->defaultRegion === false) {
      $this->initializeDefaults();
    }
    return $this->defaultRegion;
  }

  /** @return string */
  private function getDefaultPrefix()
  {
    if ($this->defaultPrefix === false) {
      $this->initializeDefaults();
    }
    return $this->defaultPrefix;
  }

  /**
   * Lazy initializer for the number-type-to-locale conversion.
   *
   * @param int $type
   *
   * @return string
   */
  private function translateNumberType(int $type)
  {
    if ($this->numberTypes === false) {
      $r = new ReflectionClass(PhoneNumberType::class);
      $this->numberTypes = array_flip($r->getConstants());
      foreach ($this->numberTypes as $id => $name) {
        if ($name != 'UAN' && $name != 'VOIP') {
          $this->numberTypes[$id] = $this->l->t(strtolower(str_replace('_', ' ', $name)));
        }
      }
    }
    return $this->numberTypes[$type];
  }

  /**
   * Inject some translations with a never called fake-function. This has to
   * be kept in sync with the constants of \libphonenumber\PhoneNumberType.
   *
   * @return void
   */
  protected static function translationHack():void
  {
    self::t('fixed line');
    self::t('mobile');
    self::t('fixed line or mobile');
    self::t('premium rate');
    self::t('shared cost');
    self::t('VOIP');
    self::t('personal number');
    self::t('pager');
    self::t('UAN');
    self::t('unknown');
    self::t('emergency');
    self::t('voicemail');
    self::t('short code');
    self::t('standard rate');
  }

  /**
   * Add the default area code if the number does not start with +
   * or 0, and do some other normalization, strip spaces etc
   *
   * @param string $number
   *
   * @return string
   */
  public function normalize(string $number):string
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
      if (!preg_match('!^[0+]!', $number) && $this->getDefaultPrefix()) {
        $number = '0' . $this->getDefaultPrefix() . $number;
      }
    }

    return $number;
  }

  /**
   * @param string $number
   *
   * @param null|string $region
   *
   * @return bool
   */
  public function validate(string $number, ?string $region = null):bool
  {
    // add local area code and remove some of the usual human fuzzy
    // input ...
    $number = $this->normalize($number);

    if ($number === '') {
      return false;
    }

    if (!$region && $this->getDefaultRegion()) {
      $region = $this->getDefaultRegion();
    }

    try {

      if ($number != $this->currentNumber || $region != $this->currentRegion) {
        $this->currentNumber = $number;
        $this->currentRegion = $region;
        $this->currentObject = $this->getBackend()->parse($this->currentNumber, $this->currentRegion);
      }

      $result = $this->getBackend()->isValidNumber($this->currentObject);

    } catch (\Throwabled $e) {
      $result = false;
    }

    return $result;
  }

  /**
   * @param null|string $number
   *
   * @param null|string $region
   *
   * @return string
   */
  public function format(?string $number = null, ?string $region = null):string
  {
    if ($number !== null && !$this->validate($number, $region)) {
      return '';
    }

    return $this->getBackend()->format($this->currentObject, PhoneNumberFormat::INTERNATIONAL);
  }

  /**
   * @param null|string $number
   *
   * @param null|string $region
   *
   * @return bool
   */
  public function isMobile(?string $number = null, ?string $region = null):bool
  {
    if ($number !== null && !$this->validate($number, $region)) {
      return false;
    }

    return $this->getBackend()->getNumberType($this->currentObject) == \libphonenumber\PhoneNumberType::MOBILE;
  }

  /**
   * Return new-line formatted meta-data for the current or the given
   * number and region.
   *
   * @param string|null $number The number to parse.
   *
   * @param string|null $region The region code if not included in the number.
   *
   * @param string $newline The new-line delimiter.
   *
   * @return string Empty string if the number cannot be validated,
   * newline separated meta-data else.
   */
  public function metaData($number = null, $region = null, string $newline = "\n")
  {
    if ($number !== null) {
      try {
        if (!$this->validate($number, $region)) {
          return '';
        }
      } catch (\Throwable $t) {
        return '';
      }
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

    $meta = [];
    $meta [] = $this->l->t('Type    : %s', [ $this->translateNumberType($this->getBackend()->getNumberType($this->currentObject)) ]);
    $meta [] = $this->l->t('Country : %s', [ $this->getBackend()->getRegionCodeForNumber($this->currentObject)]);
    $meta [] = $this->l->t('Location: %s', [ $geocoder->getDescriptionForNumber($this->currentObject, $locale) ]);
    $meta [] = $this->l->t('TimeZone: %s', [ $zone ]);
    $provider = $carrierMapper->getNameForNumber($this->currentObject, $locale);
    if ($provider !== '') {
      $meta [] = $this->l->t('Provider: %s', [ $provider ]);
    }

    return implode($newline, $meta);
  }
}
