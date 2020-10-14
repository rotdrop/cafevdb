<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVBD\Common\Util;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrin\ORM\Entities\GeoContinents;
use OCA\CAFEVDB\Database\Doctrin\ORM\Entities\GeoCountries;
use OCA\CAFEVDB\Database\Doctrin\ORM\Entities\GeoPostalCodes;

class GeoCodingService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const JSON = 1;
  const XML = 2;
  const RDF = 3;
  const GEONAMES_TAG = "geonames";
  const POSTALCODESLOOKUP_TAG = "postalcodes";
  const POSTALCODESSEARCH_TAG = "postalCodes";
  const PROVIDER_URL = 'http://api.geonames.org';
  private $userName = null;
  private $countryNames = [];
  private $continentNames = [];
  private $countryContinents = [];
  private $languages = [];

  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userName = $this->getConfigValue('orchestra').'_'.$this->appName();
  }

  /**Recurse to the backend and place one single request. This
   * should be abstracted further, but should do for the moment.
   */
  private function request($command, $parameters, $type = self::JSON)
  {
    GeoCoding::init();
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
    if ($type === self::JSON) {
      $url = self::PROVIDER_URL.'/'.$command.'JSON'.'?username='.$this->userName.'&'.$query;
      /* if ($command === 'search') { */
      /*   echo $url."\n"; */
      /* } */
      $response = file_get_contents($url);
      if ($response !== false) {
        return json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
      }
    }
    return null;
  }

  /**Fetch an array of known locations given incomplete data. This
   * uses an "OR" search in the data-base. The idea is that updating
   * input fields gradually should provide the user with the most
   * recent results.
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
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    $this->countryNames();
    $myContinent = $this->$countryContinents[$this->localeRegion()];

    $countries = [];
    if (!$country) {
      foreach ($this->$countryContinents as $cntry => $continent) {
        if ($continent !== $myContinent) {
          continue;
        }
        $countries[] = $cntry;
      }
    } else {
      $countries[] = $country;
    }

    $criteria = self::criteria();
    $expr = $criteria->expr();
    $criteria = (count($countries) == 1)
              ? $criteria->where($expr->like('Country', $countries[0]))
              : $criteria->where($expr->in('Country', $countries));
    $orExpr = [];
    if ($postalCode) {
      $orExpr{} = $expr->like('PostalCode', $postalCode);
    }
    if ($name) {
      $orExpr[] = $expr->like('Name', $name);
    }
    $criteria = $criteria->andWhere(call_user_func_array([$expr, 'orX'], $orExpr));
    $translations = $this->findAll(GeoPostCodeTranslations::class);
    $locations = [];
    foreach ($this->matching($criteria, GeoPostalCodes::class) as $location) {
      $location = [
        'Latitude' => $location->getLatitude(),
        'Longitude' => $location->getLongitude(),
        'Country' => $location->getCountry(),
        'PostalCode' => $location->getPostalcode(),
        'Name' => $location->getName(),
      ];



      $orExpr[] = $expr->like($language, $name);
      //$query .= " OR `Name` LIKE '%".$name."%'";
      //$query .= " OR `".$language."` LIKE '%".$name."%'";
    }

  }

  /**Fetch infornmation from the underlying geo-coding backend;
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
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    $this->countryNames(null);
    $myContinent = $this->$countryContinents[$this->localeRegion()];

    $parameters = [];
    $countries = [];
    if (!$country) {
      foreach ($this->$countryContinents as $cntry => $continent) {
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
        array_merge(array('postalcode' => $postalCode), $parameters)
      );
      if (isset($zipResults[self::POSTALCODESSEARCH_TAG]) &&
          is_array($zipResults[self::POSTALCODESSEARCH_TAG])) {
        $remoteLocations = array_merge($remoteLocations, $zipResults[self::POSTALCODESSEARCH_TAG]);
      }
    }
    if ($name) {
      $nameResults = $this->request(
        'postalCodeSearch',
        array_merge(array('placename' => $name), $parameters)
      );
      if (isset($nameResults[self::POSTALCODESSEARCH_TAG]) &&
          is_array($nameResults[self::POSTALCODESSEARCH_TAG])) {
        $remoteLocations = array_merge($remoteLocations, $nameResults[self::POSTALCODESSEARCH_TAG]);
      }
    }

    $languages = $this->languages();

    $locations = [];
    foreach ($remoteLocations as $zipCodePlace) {
      $lat = (int)($zipCodePlace['lat'] * 10000);
      $lng = (int)($zipCodePlace['lng'] * 10000);
      $name = $zipCodePlace['placeName'];
      $cntry = $zipCodePlace['countryCode'];
      $postalCode = $zipCodePlace['postalCode'];

      $location = [
        'Latitude' => $lat,
        'Longitude' => $lng,
        'Country' => $cntry,
        'PostalCode' => $postalCode,
        'Name' => $name
      ];

      $translations = [];
      foreach ($languages as $lang) {
        $translation = $this->translatePlaceName($name, $country, $lang);
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

      $entity = GeoPostalCodes::create()
              ->setLatitude($lat)
              ->setLongitude($lng)
              ->setCountry($cntry)
              ->setPostalcode($postalCode)
              ->setName($name);
      $postalCode = $this->merge($entity);

      foreach ($translations as $language => $translation) {
        $entity = GeoPostalCodeTranslations::create()
                ->setPostalcodeid($postalCode->getId())
                ->setTarget($language)
                ->setTranslation($translation);
        $this->merge($entity);
      }

    }

    $this->flush();

    return $locations;
  }

  /**Try to find a native translation by ping the remote provider
   */
  public function translatePlaceName($name, $country, $language = null)
  {
    if (!$language) {
      $locale = $this->locale();
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
   * @param[in] $postalCode Postal code to time-stamp.
   *
   * @param[in] $country The country the postal code belongs to.
   *
   * @param[in] $handle Database handle or false.
   */
  private function stampPostalCode($postalCode, $country)
  {
    // Remember we tried ... note that due to typos
    // (e.g. comma w/o following space) we may actually fail
    // to update some of those beasts. We simply pretend to
    // update all, e.g. for the timestamp we give a damn on
    // the name.
    $qb = $this->queryBuilder();
    $expr = $qb->expr();
    $qb->update(GeoPostalCodes::class, 'gpc')
       ->set('Updated', 'CURRENT_TIMESTAMP()')
       ->where(
         $expr->andX(
           $expr->eq('gpc.Country', $country),
           $expr->eq('gpc.PostalCode', $postalCode)
         ))
       ->getQuery()
       ->execute();
  }

  /**Log to the "system"-log (OwnCloud) and optionally also
   * HTML-code. The latter is a bug. Needed feed-back information
   * rather should be passed as pure data to the "calling" piece of
   * code.
   *
   * @param[in] $string The message to write to the log.
   *
   * @param[in] $level The message level, defaults to debug.
   *
   * @param[in] $echo Whether or not to output HTML-data.
   */
  private function log($string, $level = \OCP\ILogger::DEBUG, $context = [])
  {
    $this->log($level, __CLASS__ . ': ' . $string, $context);
  }

  private function error($string, $context = [])
  {
    $this->log($string, \OCP\ILogger::Error, $context);
  }

  /**Update the list of known zip-code - location relations, but
   * only for the registerted musicians.
   *
   * TODO: extend to all, but then have a look at the Updated field
   * in order to reduce the amount queries, updating one every 3
   * months should be sufficient.
   */
  public function updatePostalCodes($language = null, $handle = false, $limit = 100, $echo = false)
  {
    if (!$language) {
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    $zipCodes = [];

    $query = "SELECT DISTINCT t1.PostalCode, t1.Country, t1.Updated FROM
  `".self::POSTAL_CODES_TABLE."` as t1
    LEFT JOIN `Musiker` as t2
      ON t1.PostalCode = t2.Postleitzahl AND t1.Country = t2.Land
  WHERE TIMESTAMPDIFF(MONTH,Updated,NOW()) > 1
  ORDER BY `Updated` ASC
  LIMIT ".$limit;
    $result = mySQL::query($query);
    while ($line = mySQL::fetch($result)) {
      $zipCodes[$line['PostalCode']] = $line['Country'];
    }
    //echo $query.'<BR/>';
    //throw new \Exception($query);

    $numChanged = 0;
    $numTotal = 0;
    foreach ($zipCodes as $postalCode => $country) {
      $zipCodeInfo = $this->request('postalCodeLookup',
                                   array('country' => $country,
                                         'postalcode' => $postalCode));

      if (!isset($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
          !is_array($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
          count($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) == 0) {
        $this->stampPostalCode($postalCode, $country);
        $this->error("No remote information for ".$postalCode.'@'.$country);
        continue;
      }

      foreach ($zipCodeInfo[$this->POSTALCODESLOOKUP_TAG] as $zipCodePlace) {
        ++$numTotal;

        $lat  = (int)($zipCodePlace['lat'] * 10000);
        $lng  = (int)($zipCodePlace['lng'] * 10000);
        $name = $zipCodePlace['placeName'];

        $translations = [];
        foreach ($languages as $lang) {
          $translation = $this->translatePlaceName($name, $country, $lang);
          if (!$translation) {
            $translation = 'NULL';
          } else {
            $translation = Util::normalizeSpaces($translation);
            $translation = "'".$translation."'";
          }
          $translations[$lang] = $translation;
        }

        /* Normalize name and translation */
        $name = Util::normalizeSpaces($name);

        $query = "INSERT INTO `".self::POSTAL_CODES_TABLE."`
  (Latitude,Longitude,Country,PostalCode,Name,".implode(',',$languages).")
  VALUES
  (".$lat.",".$lng.",'".$country."','".$postalCode."','".$name."',".implode(',',$translations).")
  ON DUPLICATE KEY UPDATE
    Latitude = ".$lat.", Longitude = ".$lng.",
    Country = '".$country."',
    PostalCode = '".$postalCode."',
    Name = '".$name."'";

        foreach ($translations as $lang => $translation) {
          $query .= ",
    ".$lang." = ".$translation;
        }

        //throw new \Exception($query);
        //echo '<H4>Query: '.$query.'</H4><BR/>';
        mySQL::query($query);
        $hasChanged = mySQL::changedRows($handle);
        if ($hasChanged > 0) {
          $numChanged += $hasChanged;
        } else {
          // Still set the time-stamp in order to prevent starvation
          // of dynamic update procedures.
          $this->stampPostalCode($postalCode, $country);
        }
        if ($limit == 1) {
          $this->error($postalCode.'@'.$country.': '.$name);
        }
      }

    }
    $this->error('Affected Postal Code records: '.$numChanged.' of '.$numTotal);

    return true;
  }

  /**Return an array of PHP-supported country-codes and localized
   * names; this uses the PHP-internal locale support.
   *
   * @param[in] $language The desired language for the returned country names.
   *
   * @return An array in the form array(CODE => NAME) where CODE is
   * the two-letter ISO-code for the country and NAME the name of
   * the locale in either the language requested by the @a $language
   * parameter or the default Locale language deduced from the
   * personal settings of the current user.
   */
  public function localeCountryNames($language = null)
  {
    if (!$language) {
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }
    $locales = resourcebundle_locales('');
    $countryCodes = [];
    foreach ($locales as $locale) {
      $country = locale_get_region($locale);
      if ($country) {
        $lang = $language === 'native' ? $locale : $language;
        $countryCodes[$country] = locale_get_display_region($locale, $lang);
      }
    }
    asort($countryCodes);
    return $countryCodes;
  }

  /**Return the country-code for the requested or current locale. */
  public function localeCountryName($locale = null)
  {
    if (!$locale) {
      $locale = $this->locale();
    }
    $language = locale_get_primary_language($locale);
    return locale_get_display_region($locale, $language);
  }

  /**Return the two-letter region. */
  public function localeRegion($locale = null)
  {
    if (!$locale) {
      $locale = $this->locale();
    }
    return locale_get_region($locale);
  }


  /**Return the language for the requested of current locale. */
  public function localeLanguage($locale = null)
  {
    if (!$locale) {
      $locale = $this->locale();
    }
    return locale_get_primary_language($locale);
  }

  /**Synchronize the countries- and continents table with the
   * underlying backend. This function tries to update all stored
   * languages and the language from the current lcoale, and at the
   * very least the English version of the name is obtained (as well
   * a the native one for the countries). It also tries to add the
   * primary language of the current locale to the tables.
   */
  public function updateCountries($handle = false)
  {
    $languages = $this->languages(false);

    // add language of current locale
    $locale = $this->locale();
    $currentLang = locale_get_primary_language($locale);

    foreach(array($currentLang, 'en') as $lang) {
      if (!array_search($lang, $languages)) {
        $this->log('Added language '.$lang);
      }
    }
    $languages = $this->languages();

    $localeNames = $this->localeCountryNames('native');

    // first obtain english names
    $countryInfo = $this->request('countryInfo', array('lang' => 'en'));
    if (!isset($countryInfo[self::GEONAMES_TAG]) ||
        !is_array($countryInfo[self::GEONAMES_TAG])) {
      return false;
    }

    $numCountry = 0;
    $numContinent = 0;
    foreach ($countryInfo[self::GEONAMES_TAG] as $country) {
      $code = $country['countryCode'];
      $continent = $country['continent'];
      $native = isset($localeNames[$code]) ? $localeNames[$code] : '';
      $name = $country['countryName'];
      $continentName = $country['continentName'];

      // Update country table
      $query = "INSERT INTO `".self::COUNTRY_TABLE."`
  (ISO,Continent,NativeName,en) VALUES ('".$code."','".$continent."','".$native."','".$name."')
  ON DUPLICATE KEY UPDATE Continent = '".$continent."', NativeName = '".$native."', en = '".$name."'";
      //echo $query."<BR/>";
      mySQL::query($query);
      $numCountry += mySQL::changedRows($handle);

      // Update continent table
      $query = "INSERT INTO `".self::CONTINENT_TABLE."`
  (Code,en) VALUES ('".$continent."','".$continentName."')
  ON DUPLICATE KEY UPDATE en = '".$continentName."'";
      //echo $query."<BR/>";
      mySQL::query($query);
      $numContinent += mySQL::changedRows($handle);
    }
    self::log('Affected Rows for country setup: '.$numCountry);
    $this->log('Affected Rows for contient setup: '.$numContinent);

    foreach ($languages as $lang) {
      if ($lang === 'en') {
        continue;
      }

      // per force update all
      $numRows = $this->updateCountriesForLanguage($lang, true);

      $this->log('Affected rows for language '.$lang.': '.$numRows);
    }

    return true;
  }

  /**Update the locale cache for one specific language. If $force
   * === true recurse to the backend, otherwise update only if the
   * language is not yet present.
   */
  public function updateCountriesForLanguage($lang, $force = false)
  {
    if (!$force) {
      // get list of desired languages
      $countryFields = mySQL::columns(self::COUNTRY_TABLE);
      if (array_search($lang, $countryFields) !== false) {
        return true;
      }
    }

    // obtain localized info from server
    $countryInfo = $this->request('countryInfo', array('lang' => $lang));
    if (!isset($countryInfo[self::GEONAMES_TAG]) ||
        !is_array($countryInfo[self::GEONAMES_TAG])) {
      continue; // give up
    }

    // Process each entry in turn
    $numRows = 0;
    foreach ($countryInfo[self::GEONAMES_TAG] as $country) {
      $code = $country['countryCode'];
      $continent = $country['continent'];
      $name = $country['countryName'];
      $continentName = $country['continentName'];

      $this->setDatabaseRepository(GeoCountries::class);
      $entity = $this->find([$code, $lang]);
      if (empty($entity)) {
        $entity = GeoCountries::create()
                ->setIso($code)
                ->setTarget($lang);
      } else {
        $numRows += (int)($entity->getData() != $name);
      }
      $entity->setData($name);
      $this->persist($entity);

      // Update continent table
      $this->setDatabaseRepository(GeoContinents::class);
      $entity = $this->find([$continent, $lang]);
      if (empty($entity)) {
        $entity = GeoContinents::create()
                ->setCode($continent)
                ->setTarget($lang);
      } else {
        $numRows += (int)($entity->getTranslation() != $continentName);
      }
      $entity->setTranslation($continentName);
      $this->persist($entity);
    }
    $this->flush();

    return $numRows;
  }

  /**Get the number of languages supported in the database tables. */
  public function languages($force = false)
  {
    if (!$force && count($this->$languages) > 0) {
      return $this->$languages;
    }

    // get all languages
    $this->$languages = [];
    foreach ($this->columnNames(GeoContinents::class) as $column) {
      if ($column == 'Code') {
        continue;
      }
      $this->languages[] = $column;
    }

    return $this->$languages;
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
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    $countries = $this->countryNames($language);
    $countryGroups = [];
    foreach($this->$countryContinents as $country => $continent) {
      $countryGroups[$country] = $this->$continentNames[$language][$continent];
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
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    if (isset($this->$continentNames[$language])) {
      return $this->$continentNames[$language];
    }

    $continents = [];

    // sort s.t. the fallback-language comes first and is overwritten
    // later by the correct translation.
    $criteria = self::criteria()
              ->where($expr->in('Target', ['en', $language]))
              ->orderBy(['Target' => ('en' < $language ? 'ASC' : 'DESC')]);
    foreach ($this->matching($criteria, GeoContinents::class) as $translation) {
      $continents[$translation->getCode()] = $translation->getTranslation();
    }

    $this->$continentNames[$language] = $continents;

    return $continents;
  }

  /**Export the table of countries as key => name array w.r.t. the
   * current locale or the requested language.
   */
  public function countryNames($language = null)
  {
    if (!$language) {
      $locale = $this->locale();
      $language = locale_get_primary_language($locale);
    }

    if (isset($this->$countryNames[$language]) &&
        isset($this->$continentNames[$language]) &&
        count($this->$countryContinents) > 0) {
      return $this->$countryNames[$language];
    }

    // Fetch also the continent names for requested language
    $this->continentNames($language);

    // Fetch country names and continent associations
    $countries = [];
    $continents = [];

    $criteria = self::criteria()
              ->where($expr->in('Target', ['en', $language]))
              ->orderBy(['Target' => ('en' < $language ? 'ASC' : 'DESC')]);
    foreach ($this->matching($criteria, GeoCountries::class) as $country) {
      $iso = $country->getIso();
      $target = $country->getTarget();
      $data = $county->getData();
      switch ($target) {
      case '->': // continent
        $continents[$iso] = $data;
        break;
      case '@.': // native name, unused as of now
        break;
      default: // translation target, en will come first and set the default
        $countries[$iso] = $data;
        break;
      }
    }

    $this->$countryContinents = $continents;
    $this->$countryNames[$language] = $countries;

    return $countries;
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
