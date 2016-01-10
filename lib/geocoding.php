<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /** Helper class for Geo-Coding stuff.
   */
  class GeoCoding
  {
    const JSON = 1;
    const XML = 2;
    const RDF = 3;
    const CONTINENT_TABLE = "GeoContinents";
    const COUNTRY_TABLE = "GeoCountries";
    const POSTAL_CODES_TABLE = "GeoPostalCodes";
    const GEONAMES_TAG = "geonames";
    const POSTALCODESLOOKUP_TAG = "postalcodes";
    const POSTALCODESSEARCH_TAG = "postalCodes";
    const PROVIDER_URL = 'http://api.geonames.org';
    protected static $userName = null;
    protected static $countryNames = array();
    protected static $continentNames = array();
    protected static $countryContinents = array();
    protected static $languages = array();

    public static function init()
    {
      if (!self::$userName) {
        Config::init();
        self::$userName = Config::getValue('orchestra').'_'.Config::APP_NAME;
      }
    }

    /**Recurse to the backend and place one single request. This
     * should be abstracted further, but should do for the moment.
     */
    protected static function request($command, $parameters, $type = self::JSON)
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
        $url = self::PROVIDER_URL.'/'.$command.'JSON'.'?username='.self::$userName.'&'.$query;
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
    public static function cachedLocations($postalCode = null,
                                           $name = null,
                                           $country = null,
                                           $language = null,
                                           $handle = false)
    {
      if (!$postalCode && !$name) {
        return array();
      }

      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      self::countryNames();
      $myContinent = self::$countryContinents[self::localeRegion()];

      $countries = array();
      if (!$country) {
        foreach (self::$countryContinents as $cntry => $continent) {
          if ($continent !== $myContinent) {
            continue;
          }
          $countries[] = $cntry;
        }
      } else {
        $countries[] = $country;
      }

      $query = "SELECT * FROM `".self::POSTAL_CODES_TABLE."` WHERE 1";
      if (count($countries) == 1) {
        $query .= " AND `Country` LIKE '".$countries[0]."'";
      } else {
        $query .= " AND `Country` IN ('".implode("','", $countries)."')";
      }
      $query .= " AND ( 0 ";

      if ($postalCode) {
        $query .= " OR `PostalCode` LIKE '".$postalCode."'";
      }
      if ($name) {
        $query .= " OR `Name` LIKE '%".$name."%'";
        $query .= " OR `".$language."` LIKE '%".$name."%'";
      }
      $query .= " )";

      if (false && $country === '%') {
        throw new \Exception($query);
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $result = mySQL::query($query, $handle);
      $locations = array();
      while ($line = mySQL::fetch($result)) {
        $locations[] = $line;
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $locations;
    }

    /**Fetch infornmation from the underlying geo-coding backend;
     * store the retrieved information in our local cache. This
     * functions inserts any new loations into the local cache of
     * known locations.
     */
    public static function remoteLocations($postalCode = null,
                                           $name = null,
                                           $country = null,
                                           $language = null,
                                           $handle = false)
    {
      if (!$postalCode && !$name) {
        return array(); // no-go
      }

      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      // insert into local cache and format output array
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      self::countryNames(null, $handle);
      $myContinent = self::$countryContinents[self::localeRegion()];

      $parameters = array();
      $countries = array();
      if (!$country) {
        foreach (self::$countryContinents as $cntry => $continent) {
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

      $remoteLocations = array();
      // place two requests, one for the postal code, one for the name
      if ($postalCode) {
        $zipResults = self::request('postalCodeSearch',
                                    array_merge(array('postalcode' => $postalCode),
                                                $parameters));
        if (isset($zipResults[self::POSTALCODESSEARCH_TAG]) &&
            is_array($zipResults[self::POSTALCODESSEARCH_TAG])) {
          $remoteLocations = array_merge($remoteLocations, $zipResults[self::POSTALCODESSEARCH_TAG]);
        }
      }
      if ($name) {
        $nameResults = self::request('postalCodeSearch',
                                     array_merge(array('placename' => $name),
                                                 $parameters));
        if (isset($nameResults[self::POSTALCODESSEARCH_TAG]) &&
            is_array($nameResults[self::POSTALCODESSEARCH_TAG])) {
          $remoteLocations = array_merge($remoteLocations, $nameResults[self::POSTALCODESSEARCH_TAG]);
        }
      }

      $languages = self::languages();

      $locations = array();
      foreach ($remoteLocations as $zipCodePlace) {
        $lat = (int)($zipCodePlace['lat'] * 10000);
        $lng = (int)($zipCodePlace['lng'] * 10000);
        $name = $zipCodePlace['placeName'];
        $cntry = $zipCodePlace['countryCode'];
        $postalCode = $zipCodePlace['postalCode'];

        $location = array('Latitude' => $lat,
                          'Longitude' => $lng,
                          'Country' => $cntry,
                          'PostalCode' => $postalCode,
                          'Name' => $name);

        $translations = array();
        foreach ($languages as $lang) {
          $translation = self::translatePlaceName($name, $country, $lang);
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

        $query = "INSERT INTO `".self::POSTAL_CODES_TABLE."`
  (Latitude,Longitude,Country,PostalCode,Name,".implode(',',$languages).")
  VALUES
  (".$lat.",".$lng.",'".$cntry."','".$postalCode."','".$name."',".implode(',',$translations).")
  ON DUPLICATE KEY UPDATE
    Latitude = ".$lat.", Longitude = ".$lng.",
    Country = '".$cntry."',
    PostalCode = '".$postalCode."',
    Name = '".$name."'";

        foreach ($translations as $lang => $translation) {
          $query .= ",
    ".$lang." = ".$translation;
        }

        //throw new \Exception($query);
        mySQL::query($query, $handle); // give a damn on errors
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $locations;
    }

    /**Try to find a native translation by ping the remote provider
     */
    public static function translatePlaceName($name, $country, $language = null)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      $translation = self::request('search', array('name' => $name,
                                                   'country' => $country,
                                                   'lang' => $language,
                                                   'featureClass' => 'P',
                                                   'maxRows' => 1));
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
    private static function stampPostalCode($postalCode, $country, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Remember we tried ... note that due to typos
      // (e.g. comma w/o following space) we may actually fail
      // to update some of those beasts. We simply pretend to
      // update all, e.g. for the timestamp we give a damn on
      // the name.
      $query = "UPDATE `".self::POSTAL_CODES_TABLE."`
  SET `Updated` = NOW()
  WHERE `Country` = '".$country."' AND
        `PostalCode` = '".$postalCode."'";
      mySQL::query($query, $handle);

      // We don't care for a failing query.
      if ($ownConnection) {
        mySQL::close($handle);
      }
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
    private static function log($string, $level = \OCP\Util::DEBUG, $echo = false)
    {
      if ($echo) {
        echo '<H4>'.htmlspecialchars($string).'</H4></BR>';
      }
      \OCP\Util::writeLog(Config::APP_NAME, 'GeoCoding: '.$string, $level);
    }

    /**Update the list of known zip-code - location relations, but
     * only for the registerted musicians.
     *
     * TODO: extend to all, but then have a look at the Updated field
     * in order to reduce the amount queries, updating one every 3
     * months should be sufficient.
     */
    public static function updatePostalCodes($language = null, $handle = false, $limit = 100, $echo = false)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Provide the requested language column.
      $languages = self::provideLanguageColumn($language, $handle);

      $zipCodes = array();

      $query = "SELECT DISTINCT t1.PostalCode, t1.Country, t1.Updated FROM
  `".self::POSTAL_CODES_TABLE."` as t1
    LEFT JOIN `Musiker` as t2
      ON t1.PostalCode = t2.Postleitzahl AND t1.Country = t2.Land
  WHERE TIMESTAMPDIFF(MONTH,Updated,NOW()) > 1
  ORDER BY `Updated` ASC
  LIMIT ".$limit;
      $result = mySQL::query($query, $handle);
      while ($line = mySQL::fetch($result)) {
        $zipCodes[$line['PostalCode']] = $line['Country'];
      }
      //echo $query.'<BR/>';
      //throw new \Exception($query);

      $numChanged = 0;
      $numTotal = 0;
      foreach ($zipCodes as $postalCode => $country) {
        $zipCodeInfo = self::request('postalCodeLookup',
                                     array('country' => $country,
                                           'postalcode' => $postalCode));

        if (!isset($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
            !is_array($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) ||
            count($zipCodeInfo[self::POSTALCODESLOOKUP_TAG]) == 0) {
	  self::stampPostalCode($postalCode, $country, $handle);
          self::log("No remote information for ".$postalCode.'@'.$country, \OCP\Util::ERROR, true);
          continue;
        }

        foreach ($zipCodeInfo[self::POSTALCODESLOOKUP_TAG] as $zipCodePlace) {
          ++$numTotal;

          $lat  = (int)($zipCodePlace['lat'] * 10000);
          $lng  = (int)($zipCodePlace['lng'] * 10000);
          $name = $zipCodePlace['placeName'];

          $translations = array();
          foreach ($languages as $lang) {
            $translation = self::translatePlaceName($name, $country, $lang);
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
          mySQL::query($query, $handle);
          $hasChanged = mySQL::changedRows($handle);
          if ($hasChanged > 0) {
            $numChanged += $hasChanged;
          } else {
	    // Still set the time-stamp in order to prevent starvation
	    // of dynamic update procedures.
	    self::stampPostalCode($postalCode, $country, $handle);
          }
          if ($limit == 1) {
            self::log($postalCode.'@'.$country.': '.$name, \OCP\Util::ERROR, true);
          }
        }

}
      self::log('Affected Postal Code records: '.$numChanged.' of '.$numTotal, \OCP\Util::ERROR, true);

      if ($ownConnection) {
        mySQL::close($handle);
      }

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
    public static function localeCountryNames($language = null)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }
      $locales = resourcebundle_locales('');
      $countryCodes = array();
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
    public static function localeCountryName($locale = null)
    {
      if (!$locale) {
        $locale = Util::getLocale();
      }
      $language = locale_get_primary_language($locale);
      return locale_get_display_region($locale, $language);
    }

    /**Return the two-letter region. */
    public static function localeRegion($locale = null)
    {
      if (!$locale) {
        $locale = Util::getLocale();
      }
      return locale_get_region($locale);
    }


    /**Return the language for the requested of current locale. */
    public static function localeLanguage($locale = null)
    {
      if (!$locale) {
        $locale = Util::getLocale();
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
    public static function updateCountries($handle = false)
    {
      self::init();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $languages = self::languages(false, $handle);

      // add language of current locale
      $locale = Util::getLocale();
      $currentLang = locale_get_primary_language($locale);

      foreach(array($currentLang, 'en') as $lang) {
        if (!array_search($lang, $languages)) {
          self::provideLanguageColumn($lang, $handle);
          self::log('Added language '.$lang);
        }
      }
      $languages = self::languages();

      $localeNames = self::localeCountryNames('native');

      // first obtain english names
      $countryInfo = self::request('countryInfo', array('lang' => 'en'));
      if (!isset($countryInfo[self::GEONAMES_TAG]) ||
          !is_array($countryInfo[self::GEONAMES_TAG])) {
        if ($ownConnection) {
          mySQL::close($handle);
        }
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
        mySQL::query($query, $handle);
        $numCountry += mySQL::changedRows($handle);

        // Update continent table
        $query = "INSERT INTO `".self::CONTINENT_TABLE."`
  (Code,en) VALUES ('".$continent."','".$continentName."')
  ON DUPLICATE KEY UPDATE en = '".$continentName."'";
        //echo $query."<BR/>";
        mySQL::query($query, $handle);
        $numContinent += mySQL::changedRows($handle);
      }
      self::log('Affected Rows for country setup: '.$numCountry);
      self::log('Affected Rows for contient setup: '.$numContinent);

      foreach ($languages as $lang) {
        if ($lang === 'en') {
          continue;
        }

        // per force update all
        $numRows = self::updateCountriesForLanguage($lang, true, $handle);

        self::log('Affected rows for language '.$lang.': '.$numRows);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return true;
    }

    /**Update the locale cache for one specific language. If $force
     * === true recurse to the backend, otherwise update only if the
     * language is not yet present.
     */
    public static function updateCountriesForLanguage($lang, $force = false, $handle = false)
    {
      self::init();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      if (!$force) {
        // get list of desired languages
        $countryFields = mySQL::columns(self::COUNTRY_TABLE, $handle);
        if (array_search($lang, $countryFields) !== false) {
          if ($ownConnection) {
            mySQL::close($handle);
          }
          return true;
        }
      }

      // obtain localized info from server
      $countryInfo = self::request('countryInfo', array('lang' => $lang));
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

        // Update country table
        $query = "INSERT INTO `".self::COUNTRY_TABLE."`
  (ISO,".$lang.") VALUES ('".$code."','".$name."')
  ON DUPLICATE KEY UPDATE ".$lang." = '".$name."'";
        //echo $query."<BR/>";
        mySQL::query($query, $handle);
        $numRows += mySQL::changedRows($handle);

        // Update continent table
        $query = "INSERT INTO `".self::CONTINENT_TABLE."`
  (Code,".$lang.") VALUES ('".$continent."','".$continentName."')
  ON DUPLICATE KEY UPDATE ".$lang." = '".$continentName."'";
        //echo $query."<BR/>";
        mySQL::query($query, $handle);
        $numRows += mySQL::changedRows($handle);
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $numRows;
    }

    /**Make sure that a column for the requested language exists in
     * the country and continents table.
     */
    protected static function provideLanguageColumn($language, $handle = false)
    {
      self::init();

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Add it to the tables
      $query = "ALTER TABLE `".self::COUNTRY_TABLE."` ADD `".$language."`
  VARCHAR(180)
  CHARACTER SET utf8
  COLLATE utf8_general_ci
  NULL";
      mySQL::query($query);

      $query = "ALTER TABLE `".self::CONTINENT_TABLE."` ADD `".$language."`
  VARCHAR(180)
  CHARACTER SET utf8
  COLLATE utf8_general_ci
  NULL";
      mySQL::query($query);

      $query = "ALTER TABLE `".self::POSTAL_CODES_TABLE."` ADD `".$language."`
  VARCHAR(180)
  CHARACTER SET utf8
  COLLATE utf8_general_ci
  NULL
  AFTER `Name`";
      mySQL::query($query);

      self::languages(true /* force update */, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return self::$languages;
    }

    /**Get the number of languages supported in the database tables. */
    public static function languages($force = false, $handle = false)
    {
      self::init();

      if (!$force && count(self::$languages) > 0) {
        return self::$languages;
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // get all languages
      self::$languages = array();
      $query = "SHOW COLUMNS FROM `".self::CONTINENT_TABLE."`";
      $result = mySQL::query($query);
      while ($line = mySQL::fetch($result)) {
        if ($line['Field'] == 'Code') {
          continue;
        }
        self::$languages[] = $line['Field'];
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return self::$languages;
    }

    /**Export the table of known countries w.r.t. to the current
     * locale or the requested language, adding the continent as
     * grouping information. This function returns the actual human
     * readable continent names, not the two-letter codes.
     *
     * The return value is an array array(CountryCode => ContinentName)
     */
    public static function countryContinents($language = null, $handle = false)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      $countries = self::countryNames($language, $handle);
      $countryGroups = array();
      foreach(self::$countryContinents as $country => $continent) {
        $countryGroups[$country] = self::$continentNames[$language][$continent];
      }

      // Sort it according to continents

      return $countryGroups;
    }

    /**Export the table of continents as key => name array w.r.t. the
     * current locale or the requested language.
     */
    public static function continentNames($language = null, $handle = false)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      if (isset(self::$continentNames[$language])) {
        return self::$continentNames[$language];
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Provide the requested language column.
      self::provideLanguageColumn($language, $handle);

      $continents = array();
      $query = "SELECT Code,en,".$language." FROM `".self::CONTINENT_TABLE."` WHERE 1";
      $result = mySQL::query($query, $handle);
      while ($line = mySQL::fetch($result)) {
        $continents[$line['Code']] = empty($line[$language]) ? $line['en'] : $line[$language];
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      self::$continentNames[$language] = $continents;

      return $continents;
    }

    /**Export the table of countries as key => name array w.r.t. the
     * current locale or the requested language.
     */
    public static function countryNames($language = null, $handle = false)
    {
      if (!$language) {
        $locale = Util::getLocale();
        $language = locale_get_primary_language($locale);
      }

      self::init();

      if (isset(self::$countryNames[$language]) &&
          isset(self::$continentNames[$language]) &&
          count(self::$countryContinents) > 0) {
        return self::$countryNames[$language];
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      // Provide the requested language column.
      self::provideLanguageColumn($language, $handle);

      // Fetch also the continent names for requested language
      self::continentNames($language, $handle);

      // Fetch country names and continent associations
      $countries = array();
      $continents = array();
      $query = "SELECT ISO,Continent,en,".$language." FROM `".self::COUNTRY_TABLE."`
  WHERE 1
  ORDER BY Continent ASC";
      $result = mySQL::query($query, $handle);
      while ($line = mySQL::fetch($result)) {
        $countries[$line['ISO']] = empty($line[$language]) ? $line['en'] : $line[$language];
        $continents[$line['ISO']] = $line['Continent'];
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      self::$countryContinents = $continents;
      self::$countryNames[$language] = $countries;

      return $countries;
    }


  }; // class GeoCoding

} // namespace CAFEVDB

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
