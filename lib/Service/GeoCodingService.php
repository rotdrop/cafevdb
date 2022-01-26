<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or1
 * modify it under th52 terms of the GNU GENERAL PUBLIC LICENSE
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\Expr\Join;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\GeoContinent;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\GeoCountry;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\GeoPostalCode;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\GeoPostalCodeTranslation;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician;

class GeoCodingService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DEFAULT_LANGUAGE = 'en';
  const JSON = 1;
  const XML = 2;
  const RDF = 3;
  const DRY = 99;
  const GEONAMES_TAG = "geonames";
  const POSTALCODESLOOKUP_TAG = "postalcodes";
  const POSTALCODESSEARCH_TAG = "postalCodes";
  const PROVIDER_URL = 'http://api.geonames.org';
  const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
  const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';
  private $userName = null;
  private $countryNames = [];
  private $continentNames = [];
  private $countryContinents = [];
  private $languages = [];

  /** @var bool */
  private $debug = false;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userName = $this->getConfigValue('orchestra').'_'.$this->appName();
    $this->debug = $this->shouldDebug(ConfigService::DEBUG_GEOCODING);
  }

  protected function debug(string $message, array $context = [], $shift = 2) {
    if ($this->debug) {
      $this->logInfo($message, $context, $shift + 1);
    } else {
      $this->logDebug($message, $context, $shift + 1);
    }
  }

  /**
   * Recurse to the backend and place one single request. This
   * should be abstracted further, but should do for the moment.
   *
   * @param string $command
   *
   * @param mixed $parameters
   *
   * @param string $type
   *
   * @return null|array
   */
  private function request($command, $parameters, $type = self::JSON)
  {
    if (isset($parameters['postalCode']) && !isset($parameters['postalcode'])) {
      $parameters['postalcode'] = $parameters['postalCode'];
      unset($parameters['postalCode']);
    }
    if (isset($parameters['country']) && is_array($parameters['country'])) {
      $countries = $parameters['country'];
      unset($parameters['country']);
      $query = http_build_query($parameters, '', '&');
      foreach ($countries as $country) {
        $query .= '&'.http_build_query(array('country' => $country));
      }
    } else {
      $query = http_build_query($parameters, '', '&');
    }
    if ($type === self::JSON || $type === self::DRY) {
      $url = self::PROVIDER_URL.'/'.$command.'JSON'.'?username='.$this->userName.'&'.$query;
      if ($type === self::DRY) {
        return $url;
      } else {
        $this->debug($url);
      }
      $response = file_get_contents($url);
      if ($response !== false) {
        $json = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        $this->debug(print_r($json, true));
        return $json;
      }
    }
    return null;
  }

  /**
   * Given contry, city, postalcode try to as OSM about all matching
   * streets. Note that not all countries have postal-code areas
   * defined, so this may only work well in certain regions of western
   * Europe. If a query does not return any results and we have a city
   * name, then ATM we simply repeat the query with the given city.
   *
   * Example overpass API data:
   * ```
   * [out:csv("name";false)];
   * area["ISO3166-1"="DE"]->.country;
   * area[postal_code="47800"]->.postal_code;
   * way(area.country)(area.postal_code)[highway][name~="^Blah"];
   * // sort and remove duplicates
   * for (t["name"])
   * (
   *   make x name=_.val;
   *   out;
   * );
   * ```
   *
   *
   */
  public function autoCompleteStreet($country = null, $city = null, $postalCode = null)
  {
    if (empty($country) && empty($city) && empty($postalCode)) {
      return [];
    }

    $countryArea =
      $postalCodeArea =
      $cityArea = '';

    $oql = '[out:csv("name";false)];
';
    if (!empty($country)) {
      $oql .= sprintf('area["ISO3166-1"="%s"]->.country;
', $country);
      $countryArea = '(area.country)';
    }
    if (!empty($postalCode)) {
      $oql .= sprintf('area[postal_code="%s"]->.postalCode;
', $postalCode);
      $postalCodeArea = '(area.postalCode)';
    } else if (!empty($city)) {
      // querying for the city as well does not seem to speed up things.
      $oql .= sprintf('area[name~"%s"]->.city;
', $city);
      $cityArea = '(area.city)';
    }
    $oql .= sprintf('way%1$s%2$s%3$s[highway][name%4$s];
', $countryArea, $postalCodeArea, $cityArea, empty($partialStreet) ? '' : "~\"$partialStreet\"");
    $oql .= 'for (t["name"])
(
  make x name=_.val;
  out;
);
';

    $queryUrl = self::OVERPASS_URL . '?' . \http_build_query([ 'data' => $oql ]);
    $this->debug('OQL ' . print_r(preg_split('/\r\n|\r|\n/', $oql), true));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $queryUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if (empty($response)) {
      if (!empty($postalCode) && !empty($city)) {
        return $this->autoCompleteStreet($country, $city);
      }
      return [];
    } else {
      // overpass may return an HTML page with errors
      /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
      $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
      $mimeType = $mimeTypeDetector->detectString($response);
      if ($mimeType != 'text/plain') {
        // assume it is an error
        return [];
      }
    }
    $results = array_filter(preg_split('/\r\n|\r|\n/', $response));
    $this->debug(print_r($results, true));
    return $results;
  }

  /**
   * Fetch an array of known locations given incomplete data. This
   * uses an "OR" search in the data-base. The idea is that updating
   * input fields gradually should provide the user with the most
   * recent results.
   *
   * @return array
   */
  public function cachedLocations($postalCode = null,
                                  $name = null,
                                  $country = null,
                                  $language = null)
  {
    if (!$postalCode && !$name) {
      return [];
    }

    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    $this->countryNames();
    $myContinent = $this->countryContinents[$this->localeRegion()];

    $countries = [];
    if (!$country) {
      foreach ($this->countryContinents as $cntry => $continent) {
        if ($continent !== $myContinent) {
          continue;
        }
        $countries[] = $cntry;
      }
    } else {
      $countries[] = $country;
    }

    $expr = $this->expr();
    $qb = $this->queryBuilder()
               ->select('gpc')
               ->from(GeoPostalCode::class, 'gpc');
    if (count($countries) == 1) {
      $qb->where($expr->like('gpc.country', ':country'))
         ->setParameter('country', $countries[0]);
    } else {
      $qb->where($expr->in('gpc.country', ':countries'))
         ->setParameter('countries', $countries);
    }
    $orExpr = [];
    if ($postalCode) {
      $orExpr[] = $expr->eq('gpc.postalCode', ':postalCode');
    }
    if ($name) {
      $orExpr[] = $expr->eq('gpc.name', ':name');
    }
    if (!empty($orExpr)) {
      $qb = $qb->andWhere(call_user_func_array([$expr, 'orX'], $orExpr));
      if ($postalCode) {
        $qb->setParameter('postalCode',  $postalCode);
      }
      if ($name) {
        $qb->setParameter('name', $name);
      }
    }

    $this->debug($qb->getDql());
    $locations = [];
    foreach ($qb->getQuery()->execute() as $location) {
      $oneLocation = [
        'Latitude' => $location->getLatitude(),
        'Longitude' => $location->getLongitude(),
        'Country' => $location->getCountry(),
        'PostalCode' => $location->getPostalCode(),
        'Name' => $location->getName(),
      ];

      // and now it comes: if I understand this correctly the
      // associations of ORM will seemlessly provide the array of all
      // translations.
      /** @var GeoPostalCodeTranslation $translation */
      foreach ($location->getTranslations() as $translation) {
        $oneLocation[$translation->getTarget()] = $translation->getTranslation();
      }
      $locations[] = $oneLocation;
    }
    return $locations;
    }

  /**
   * Fetch information from the underlying geo-coding backend;
   * store the retrieved information in our local cache. This
   * functions inserts any new loations into the local cache of
   * known locations.
   */
  public function remoteLocations($postalCode = null,
                                  $name = null,
                                  $country = null,
                                  $language = null)
  {
    if (!$postalCode && !$name) {
      return []; // no-go
    }

    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    $this->countryNames(null);
    $myContinent = $this->countryContinents[$this->localeRegion()];

    $parameters = [];
    $countries = [];
    if (!$country) {
      foreach ($this->countryContinents as $cntry => $continent) {
        if ($continent !== $myContinent) {
          continue;
        }
        $countries[] = $cntry;
      }
      $parameters['country'] = $countries;
    } else if ($country !== '%') {
      $parameters['country'] = $country;
    } else {
      // world search
    }

    $remoteLocations = [];
    // place two requests, one for the postal code, one for the name
    if ($postalCode) {
      $zipResults = $this->request(
        'postalCodeSearch',
        array_merge(['postalCode' => $postalCode], $parameters)
      );
      if (isset($zipResults[self::POSTALCODESSEARCH_TAG]) &&
          is_array($zipResults[self::POSTALCODESSEARCH_TAG])) {
        $remoteLocations = array_merge($remoteLocations, $zipResults[self::POSTALCODESSEARCH_TAG]);
      }
    }
    if ($name) {
      $nameResults = $this->request(
        'postalCodeSearch',
        array_merge(['placename' => $name], $parameters)
      );
      if (isset($nameResults[self::POSTALCODESSEARCH_TAG]) &&
          is_array($nameResults[self::POSTALCODESSEARCH_TAG])) {
        $remoteLocations = array_merge($remoteLocations, $nameResults[self::POSTALCODESSEARCH_TAG]);
      }
    }

    $languages = $this->getLanguages();

    $locations = [];
    foreach ($remoteLocations as $zipCodePlace) {
      $lat = (double)($zipCodePlace['lat']);
      $lng = (double)($zipCodePlace['lng']);
      $placeName = $zipCodePlace['placeName'];
      $placeCountry = $zipCodePlace['countryCode'];
      $postalCode = $zipCodePlace['postalCode'];

      $location = [
        'Latitude' => $lat,
        'Longitude' => $lng,
        'Country' => $placeCountry,
        'PostalCode' => $postalCode,
        'Name' => $placeName
      ];

      $translations = [];
      foreach ($languages as $lang) {
        $translation = $this->translatePlaceName($placeName, $country, $lang);
        if (!$translation) {
          $translation = 'NULL';
        } else {
          $translation = Util::normalizeSpaces($translation);
          $location[$lang] = $translation;
          $translation = "'".$translation."'";
        }
        $translations[$lang] = $translation;
      }

      $locations[] = $location;

      $hasChanged = false;
      $this->setDatabaseRepository(GeoPostalCode::class);
      /** @var GeoPostalCode $geoPostalCode */
      $geoPostalCode = $this->findOneBy([
        'country' => $placeCountry,
        'postalCode' => $postalCode,
        'name' => $placeName,
      ]);
      if (empty($geoPostalCode)) {
        $geoPostalCode = GeoPostalCode::create()
                ->setCountry($placeCountry)
                ->setPostalCode($postalCode)
                ->setName($placeName);
        $hasChanged = true;
      } else {
        if (($lat != $geoPostalCode->getLatitude()) || ($lng != $geoPostalCode->getLongitude())) {
          $hasChanged = true;
        }
        }
      if ($hasChanged) {
        $geoPostalCode->setLongitude($lng);
        $geoPostalCode->setLatitude($lat);
        $this->persist($geoPostalCode);
        $this->flush(); // needed for update the translations below
      }

      foreach ($translations as $language => $translation) {
        $hasChanged = false;
        $this->setDatabaseRepository(GeoPostalCodeTranslation::class);
        $entity = $this->find([
          'geoPostalCode' => $geoPostalCode,
          'target' => $lang,
        ]);
        if (empty($entity)) {
          $entity = GeoPostalCodeTranslation::create()
                       ->setGeoPostalCode($geoPostalCode)
                       ->setTarget($lang);
          $hasChanged = true;
        } else {
          if ($translation != $entity->getTranslation()) {
            $hasChanged = true;
          }
        }
        if ($hasChanged) {
          $entity->setTranslation($translation);
          $this->persist($entity);
        }
      }

    }

    $this->flush();

    return $locations;
  }

  /**Try to find a native translation by pinging the remote provider
   */
  public function translatePlaceName($name, $country, $language = null)
  {
    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    $translation = $this->request(
      'search',
      [
        'name' => $name,
        'country' => $country,
        'lang' => $language,
        'featureClass' => 'P',
        'maxRows' => 1,
      ]
    );
    if (is_array($translation) &&
        isset($translation['geonames']) &&
        count($translation['geonames']) === 1 &&
        isset($translation['geonames'][0]['name'])) {
      return $translation['geonames'][0]['name'];
    } else {
      return null;
    }
  }

  /**Forcibly set the update-time-stamp for the given postal
   * code. This is needed for the case where some data-base or
   * remote queries fail in order to prevent starvation of the
   * dynamic update procedure.
   *
   * Note that this may affect more than one entry.
   *
   * @param $postalCode Postal code to time-stamp.
   *
   * @param $country The country the postal code belongs to.
   */
  private function stampPostalCode($postalCode, $country)
  {
    // Remember we tried ... note that due to typos
    // (e.g. comma w/o following space) we may actually fail
    // to update some of those beasts. We simply pretend to
    // update all, e.g. for the timestamp we give a damn on
    // the name.

    $expr = $this->expr();
    $qb = $this->queryBuilder()
               ->update(GeoPostalCode::class, 'gpc')
               ->set('gpc.updated', "'".(new \DateTime)->format('Y-m-d H:i:s.u')."'")
               ->where(
                 $expr->andX(
                   $expr->eq('gpc.country', ':country'),
                   $expr->eq('gpc.postalCode', ':postalCode')
                 ))
               ->setParameter('country', $country)
               ->setParameter('postalCode', $postalCode);
    $this->debug($qb->getDql());
    $qb->getQuery()
       ->execute();
    $this->flush();
    $this->debug('Stamped postal code ' . $postalCode . '@' . $country);
  }

  /**Update the list of known zip-code - location relations, but
   * only for the registerted musicians.
   *
   * TODO: extend to all, but then have a look at the Updated field
   * in order to reduce the amount queries, updating once every 3
   * months should be sufficient.
   */
  public function updatePostalCodes($language = null, $limit = 100, $forcedZipCodes = [])
  {
    if (empty($language)) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    $this->debug("Updating postal codes for language " . $language);

    // only fetch "old" postal codes for registered musicians.
    $qb = $this->queryBuilder()
               ->select('gpc')
               ->from(GeoPostalCode::class, 'gpc')
               ->leftJoin(
                 Musician::class, 'm',
                 Join::WITH,
                 'gpc.country = m.country AND gpc.postalCode = m.postalCode'
               )
               ->where('gpc.updated IS NULL')
               ->orWhere('TIMESTAMPDIFF(MONTH, gpc.updated, CURRENT_TIMESTAMP()) >= 1')
               ->orderBy('gpc.updated', 'ASC')
               ->setMaxResults($limit);
    $this->debug($qb->getDql());

    $zipCodes = [];
    foreach ($qb->getQuery()->getResult() as $postalCode) {
      $zipCodes[] = [
        'country' => $postalCode->getCountry(),
        'postalCode' => $postalCode->getPostalCode()
      ];
    }
    $zipCodes = array_merge($zipCodes, $forcedZipCodes);

    $this->debug('ZIP CODES '.print_r($zipCodes, true));

    $numChanged = 0;
    $numTotal = 0;
    foreach ($zipCodes as $zipCode) {
      $this->debug(print_r($zipCode, true));
      $zipCodeInfo = $this->request('postalCodeLookup', $zipCode);
      $postalCode = $zipCode['postalCode'];
      $country = $zipCode['country'];

      if (!isset($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
          !is_array($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
          count($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) == 0) {
        $this->stampPostalCode($postalCode, $country);
        $this->logError("No remote information for ".$postalCode.'@'.$country. ', query-url ' . $this->request('postalCodeLookup', $zipCode, self::DRY));
        continue;
      }

      foreach ($zipCodeInfo[self::POSTALCODESLOOKUP_TAG] as $zipCodePlace) {
        ++$numTotal;

        $lat  = (double)($zipCodePlace['lat']);
        $lng  = (double)($zipCodePlace['lng']);
        $name = $zipCodePlace['placeName'];

        $translations = [];
        foreach ($this->languages($language) as $lang) {
          $translation = $this->translatePlaceName($name, $country, $lang);
          $translation = Util::normalizeSpaces($translation);
          $this->debug(print_r($translations, true));
          $this->debug(print_r($lang, true));
          $this->debug(print_r($translation, true));
          if (empty($translation)) {
            continue;
          }
          $translations[$lang] = $translation;
        }

        /* Normalize name and translation */
        $name = Util::normalizeSpaces($name);

        $hasChanged = false;
        $newEntity = true;
        $this->setDatabaseRepository(GeoPostalCode::class);
        /** @var Entities\GeoPostalCode $geoPostalCode */
        $geoPostalCode = $this->findOneBy([
          'country' => $country,
          'postalCode' => $postalCode,
          'name' => $name,
        ]);
        if (empty($geoPostalCode)) {
          $geoPostalCode = GeoPostalCode::create()
                         ->setCountry($country)
                         ->setPostalCode($postalCode)
                         ->setName($name);
          $hasChanged = true;
          $newEntity = true;
        } else {
          if (($lat != $geoPostalCode->getLatitude()) || ($lng != $geoPostalCode->getLongitude())) {
            $hasChanged = true;
          }
        }
        if ($hasChanged) {
          $geoPostalCode->setLongitude($lng);
          $geoPostalCode->setLatitude($lat);
          $this->persist($geoPostalCode);
          if ($newEntity) {
            $this->flush(); // generate id for new entity
          }
          $numChanged++;
        }

        foreach ($translations as $lang => $translation) {
          $hasChanged = false;
          $this->setDatabaseRepository(GeoPostalCodeTranslation::class);
          $entity = $this->findOneBy([
            'geoPostalCode' => $geoPostalCode,
            'target' => $lang,
          ]);
          $isNew = empty($entity);
          if (empty($entity)) {
            $entity = GeoPostalCodeTranslation::create()
                    ->setGeoPostalCode($geoPostalCode)
                    ->setTarget($lang);
            $hasChanged = true;
          } else {
            if ($translation != $entity->getTranslation()) {
              $hasChanged = true;
            }
          }
          if ($hasChanged) {
            $entity->setTranslation($translation);
            $this->persist($entity);
            try {
              $this->flush();
            } catch (\Throwable $t) {
              $this->logError('PostalCodeTranslation ' . $geoPostalCode->getId() . ' target ' . $lang . ' new ' . (int)$isNew . ' but caught exception');
              $this->logException($t);
            }
          }
        }

        if ($limit == 1) {
          $this->debug($postalCode.'@'.$country.': '.$name);
        }
      }

      // Still set the time-stamp in order to prevent starvation
      // of dynamic update procedures.
      $this->stampPostalCode($postalCode, $country);
    }
    $this->flush();

    $this->debug('Affected Postal Code records: '.$numChanged.' of '.$numTotal);

    return true;
  }

  /**
   * Return an array of PHP-supported country-codes and localized
   * names; this uses the PHP-internal locale support.
   *
   * @param $language The desired language for the returned country names.
   *
   * @return An array in the form array(CODE => NAME) where CODE is
   * the two-letter ISO-code for the country and NAME the name of
   * the locale in either the language requested by the @a $language
   * parameter or the default Locale language deduced from the
   * personal settings of the current user.
   */
  public function localeCountryNames($language = null)
  {
    if (empty($language)) {
      $language = locale_get_primary_language($this->getLocale());
    }
    $locales = resourcebundle_locales('');
    $countryCodes = [];
    foreach ($locales as $locale) {
      $country = locale_get_region($locale);
      if ($country) {
        $countryCodes[$country] = locale_get_display_region($locale, $language);
      }
    }
    asort($countryCodes);
    return $countryCodes;
  }

  /** Return the country-code for the requested or current locale. */
  public function localeCountryName($locale = null)
  {
    if (!$locale) {
      $locale = $this->getLocale();
    }
    $language = locale_get_primary_language($locale);
    return locale_get_display_region($locale, $language);
  }

  /**Return the two-letter region. */
  public function localeRegion($locale = null)
  {
    if (!$locale) {
      $locale = $this->getLocale();
    }
    return locale_get_region($locale);
  }


  /**Return the language for the requested or current locale. */
  public function localeLanguage($locale = null)
  {
    if (!$locale) {
      $locale = $this->getLocale();
    }
    return locale_get_primary_language($locale);
  }

  /**Synchronize the countries- and continents table with the
   * underlying backend. This function tries to update all stored
   * languages and the language from the current locale, and at the
   * very least the English version of the name is obtained (as well
   * a the native one for the countries). It also tries to add the
   * primary language of the current locale to the tables.
   */
  public function updateCountries($force = false)
  {
    $languages = $this->getLanguages();

    foreach ($languages as $lang) {
      $numRows = $this->updateCountriesForLanguage($lang, $force);
      $this->debug('Affected rows for language '.$lang.': '.print_r($numRows, true));
    }

    return true;
  }

  /**Update the locale cache for one specific language.*/
  public function updateCountriesForLanguage($lang, $force = false)
  {
    if (!$force && $this->count(['target' => $lang], GeoCountry::class) > 0) {
      $this->debug('language '.$lang.' already retrieved and update not forced, skipping update.');
      return 0;
    }

    // obtain localized info from server
    $countryInfo = $this->request('countryInfo', array('lang' => $lang));
    if (!isset($countryInfo[self::GEONAMES_TAG]) ||
        !is_array($countryInfo[self::GEONAMES_TAG])) {
      return false; // give up
    }

    $localeCountyNames = $this->localeCountryNames($lang);

    // Process each entry in turn
    $numRows = 0;
    foreach ($countryInfo[self::GEONAMES_TAG] as $country) {
      $code = $country['countryCode'];
      $continent = $country['continent'];
      $name = $country['countryName'];
      $continentName = $country['continentName'];
      $languages = $country['languages'];

      $localeName = $localeCountryNames[$code];
      if ($localeName != $name) {
        $this->debug($lang.'_'.$code.': '.$localeName.' / '.$name.' (php/remote)');
      }

      $this->setDatabaseRepository(GeoCountry::class);
      /** @var GeoCountry $countryEntity */
      $countryEntity = null;
      if (!empty($name)) {
        $countryEntity = $this->find(['iso' => $code, 'target' => $lang]);
        if (empty($countryEntity)) {
          $countryEntity = GeoCountry::create()
            ->setIso($code)
            ->setTarget($lang);
        } else {
          $numRows += (int)($countryEntity->getL10nName() != $name);
        }
        $countryEntity->setL10nName($name);
        $this->persist($countryEntity);
      }

      if (!empty($continentName)) {
        // Update continent table
        /** @var GeoContinent $continentEntity */
        $this->setDatabaseRepository(GeoContinent::class);
        $continentEntity = $this->find(['code' => $continent, 'target' => $lang]);
        if (empty($continentEntity)) {
          $continentEntity = GeoContinent::create()
                  ->setCode($continent)
                  ->setTarget($lang);
        } else {
          $numRows += (int)($continentEntity->getL10nName() != $continentName);
        }
        $continentEntity->setL10nName($continentName);
        if (!empty($countryEntity)) {
          $countryEntity->setContinent($continentEntity);
        }
        $this->persist($continentEntity);
      }
    }

    $this->flush();

    return $numRows;
  }

  /** Return the languages supported in the database tables. */
  public function getLanguages($extraLang = null)
  {
    $languages = $this->languages;
    if (empty($languages)) {
      // get all languages
      $languages = $this->queryBuilder()
                        ->select('gpct.target')
                        ->from(GeoPostalCodeTranslation::class, 'gpct')
                        ->distinct(true)
                        ->getQuery()
                        ->execute();
      $this->debug('LANGUAGES '.print_r($languages, true));
      $languages = array_map(function($value) { return $value['target'];}, $languages);
      $languages = array_filter($languages, function($value) { return $value !== '=>'; });
    }
    // add language of current locale
    $locale = $this->getLocale();
    $currentLang = locale_get_primary_language($locale);

    $languages = array_unique(array_merge([self::DEFAULT_LANGUAGE, $currentLang], $languages));
    $this->languages = array_filter(array_unique(array_merge([self::DEFAULT_LANGUAGE, $currentLang, $extraLang], $languages)));

    $this->debug(print_r($this->languages, true));

    return $this->languages;
  }

  /**Export the table of known countries w.r.t. to the current
   * locale or the requested language, adding the continent as
   * grouping information. This function returns the actual human
   * readable continent names, not the two-letter codes.
   *
   * The return value is an array array(CountryCode => ContinentName)
   */
  public function countryContinents($language = null)
  {
    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    $countries = $this->countryNames($language);
    $countryGroups = [];
    foreach($this->countryContinents as $country => $continent) {
      $countryGroups[$country] = $this->continentNames[$language][$continent];
    }

    // Sort it according to continents

    return $countryGroups;
  }

  /**Export the table of continents as key => name array w.r.t. the
   * current locale or the requested language.
   */
  public function continentNames($language = null)
  {
    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    if (isset($this->continentNames[$language])) {
      return $this->continentNames[$language];
    }

    $continents = [];

    // sort s.t. the fallback-language comes first and is overwritten
    // later by the correct translation.
    $criteria = self::criteriaWhere(['target' => [self::DEFAULT_LANGUAGE, $language]])
              ->orderBy(['target' => (self::DEFAULT_LANGUAGE < $language ? 'ASC' : 'DESC')]);

    /** @var GeoContinent $continent */
    foreach ($this->matching($criteria, GeoContinent::class) as $continent) {
      $continents[$continent->getCode()] = $continent->getL10nName();
    }

    $this->continentNames[$language] = $continents;

    return $continents;
  }

  /**
   * Export the table of countries as key => name array w.r.t. the
   * current locale or the requested language.
   *
   * @todo Grouping by continents could now be done at the data-base level.
   */
  public function countryNames($language = null)
  {
    if (!$language) {
      $locale = $this->getLocale();
      $language = locale_get_primary_language($locale);
    }

    if (isset($this->countryNames[$language]) &&
        isset($this->continentNames[$language]) &&
        count($this->countryContinents) > 0) {
      return $this->countryNames[$language];
    }

    // Fetch also the continent names for requested language
    $this->continentNames($language);

    // Fetch country names and continent associations
    $countries = [];
    $continents = [];

    $criteria = self::criteriaWhere(['target' => [self::DEFAULT_LANGUAGE, $language]])
      ->orderBy(['target' => (self::DEFAULT_LANGUAGE < $language ? 'ASC' : 'DESC')]);

    $countryData = [];
    /** @var GeoContinent $continent */
    foreach ($this->matching($criteria, GeoContinent::class) as $continent) {
      /** @var GeoCountry $country */
      foreach ($continent->getCountries() as $country) {
        $iso = $country->getIso();
        $l10nName = $country->getL10nName();
        $countries[$iso] = $l10nName;
        $continents[$iso] = $continent->getCode();
      }
    }

    $this->countryContinents = $continents;
    $this->countryNames[$language] = $countries;

    return $countries;
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
