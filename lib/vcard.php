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
 * vCard export/import for musicians.
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB {

  use \Sabre\VObject;

  class VCard
  {
    const VERSION = '3.0';

    /**Import the given vCard into the musician data-base. This is
     * somewhat problematic: the CAFeV DB data-base does not support
     * fancy fields. Being a layman-orchestra, we prefer private
     * entries for everything and just choose the first stuff
     * available if no personal data is found.
     *
     * We import the following properties
     *
     * N, UID, LANG, EMAIL, TEL, REV, ADR, BDAY, CATEGORIES, PHOTO
     *
     * CATEGORIES are used to code instruments. PHOTO
     *
     * $param[in} string $vCard Serialized vCard data.
     *
     * @param[in] boolean $preferWork Set to @c true in order to
     * favour work over home data.
     *
     * @return associative data suitable for insertion into the
     * 'Musiker' table, additionally photo data.
     *
     * @bug Looks complicated like hell. Simplify?
     */
    public static function import($vCard, $preferWork = false)
    {
      $row = array();
      // first step: parse the vCard into a Sabre\VObject
      try {
        $obj = \Sabre\VObject\Reader::read(
          $vCard,
          \Sabre\VObject\Reader::OPTION_FORGIVING|\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
          );

        if (isset($obj->N)) {
          // we honour only surname and prename, and give a damn in
          // particular on title madness.
          $parts = $obj->N->getParts();
          $row['Name'] = $parts[0];
          $row['Vorname'] = $parts[1];
        }

        if (isset($obj->TEL)) {
          foreach($obj->TEL as $tel) {
            $work = false;
            $cell = false;
            $skip = false;
            if ($param = $tel['TYPE']) {
              foreach ($param as $type) {
                switch($type) {
                case 'WORK': $work = true; break;
                case 'CELL': $cell = true; break;
                case 'HOME': $work = false; break;
                default: $skip = true; break;
                }
              }
            }
            if ($skip) {
              continue; // FAX etc.
            }
            $key = $cell ? 'MobilePhone' : 'FixedLinePhone';
            if ($work == $preferWork || !isset($row[$key])) {
              $row[$key] = (string)$tel;
            }
          }
        }

        if (isset($obj->EMAIL)) {
          foreach ($obj->EMAIL as $email) {
            $work = false;
            $skip = false;
            if ($param = $email['TYPE']) {
              foreach ($param as $type) {
                switch($type) {
                case 'WORK': $work = true; break;
                case 'HOME': $work = false; break;
                default: $skip = true; break;
                }
              }
            }
            if ($skip) {
              continue; // unknown
            }
            $key = 'Email';
            if ($work == $preferWork || !isset($row[$key])) {
              $row[$key] = (string)$email;
            }
          }
        }

        if (isset($obj->UID)) {
          $row['UUID'] = (string)$obj->UID;
        }

        if (isset($obj->LANG)) {
          $row['Sprachpräferenz'] = (string)$obj->LANG;
        }

        if (isset($obj->BDAY)) {
          $row['Geburtstag'] = date('Y-m-d H:i:s', strtotime((string)$obj->BDAY));
        }

        if (isset($obj->REV)) {
          $row['Aktualisiert'] = date('Y-m-d H:i:s', strtotime((string)$obj->REV));
        }

        if (isset($obj->ADR)) {
          $fields = array(false, // 'pobox', // unsupported
                          false, // 'ext', // well ...
                          'Strasse',
                          'Stadt',
                          false, // 'region', // unsupported
                          'Postleitzahle',
                          'Land');

          foreach ($obj->ADR as $addr) {
            $work = false;
            $skip = false;
            if ($param = $addr['TYPE']) {
              foreach ($param as $type) {
                switch($type) {
                case 'WORK': $work = true; break;
                case 'HOME': $work = false; break;
                default: $skip = true; break;
                }
              }
            }
            if ($skip) {
              continue; // unknown
            }
            if ($work != $preferWork && (isset($row['Land']) ||
                                         isset($row['Strasse']) ||
                                         isset($row['Stadt']) ||
                                         isset($row['Postleitzahl']))) {
              continue;
            }
            // we only support regular addresses, if address
            // extensions are present they are added to the street
            // address. If the street address contains newlines,
            // replace them by ", ".
            $parts = array_map('trim', $addr->getParts());
            if (implode('', $parts) === $parts[2]) {
              $parts[2] = str_replace("\xc2\xa0", "\x20", $parts[2]);
              $address = array_map('trim', preg_split("/[\n,]/", $parts[2]));
              if (count($address) == 3) {
                // assume street, city, country, otherwise give up
                $parts[2] = $address[0];
                $zip = substr($address[1], 0, 5);
                if (is_numeric($zip)) {
                  $address[1] = substr($address[1], 6);
                  $parts[5] = $zip;
                }
                $parts[3] = $address[1];
                $parts[6] = $address[2];
                // TODO: split the ZIP code
              }
            }
            //echo is_array($parts)."\n";
            //print_r($parts);
            foreach ($parts as $idx => $value) {
              if ($value && $fields[$idx]) {
                $row[$fields[$idx]] = $value;
              }
            }
          }
        } // ADR

        if (isset($obj->PHOTO)) {
          $photo = $obj->PHOTO;
          // Need to do more? YEP: fetch the MimeType as well
          // works different with vCard 4.0 compared to < 4.0
          //
          // < v4.0:
          //echo $obj->PHOTO['ENCODING']."\n";
          //echo $obj->PHOTO['TYPE']."\n";
          //
          // v4.0
          // PHOTO property is always a data URI, like (e.g.)
          // PHOTO:data:image/jpeg;base64\,/9j/4AA.....
          $row['Photo'] = $photo->getRawMimeDirValue();
        }

      } catch (\Exception $e) {
        echo __METHOD__.": ". "Error parsing card-data.";
      }

      return $row;
    }

    /**Export the stored data for one musician as vCard.
     *
     * @param[in] $musician One row from the musician table.
     *
     * @param[in] $version vCard version -- which must be one
     * supported by \Sabre\VObject. Defaults to 3.0 for compatibility
     * reasons. Note that many (mobile) devices still only use the
     * stone-age v2.1 format.
     */
    public static function export($musician, $version = self::VERSION)
    {
      $textProperties = array('FN', 'N', 'CATEGORIES', 'ADR', 'NOTE');
      $uuid = isset($musician['UUID']) ? $musician['UUID'] : Util::generateUUID();
      $categories = array('cafevdb');
      if ($musician['Instrumente']) {
        $categories =  array_merge($categories, explode(',', $musician['Instrumente']));
      }
      if ($musician['Projekte']) {
        $categories =  array_merge($categories, explode(',', $musician['Projekte']));
      }
      $categories = array_map('trim', $categories);
      $appinfo = \OCP\App::getAppInfo('cafevdb');
      $appversion = \OCP\App::getAppVersion('cafevdb');
      $prodid = '-//CAF e.V.//NONSGML ' . $appinfo['name'] . ' ' . $appversion.'//EN';

      $vcard = new VObject\Component\VCard(
        [
          'VERSION' => self::VERSION,
          'PRODID' => $prodid,
          'UID' => $uuid,
          'FN' => $musician['Vorname'].' '.$musician['Name'],
          'N' => [ $musician['Name'], $musician['Vorname'] ],
          ]);
      if ($musician['Sprachpräferenz']) {
        $vcard->add('LANG', $musician['Sprachpräferenz']);
      }
      if ($musician['Email']) {
        $vcard->add('EMAIL', $musician['Email']);
      }
      if ($musician['MobilePhone']) {
        $vcard->add('TEL', $musician['MobilePhone'], ['TYPE' => 'cell']);
      }
      if ($musician['FixedLinePhone']) {
        $vcard->add('TEL', $musician['FixedLinePhone']);
      }
      if ($musician['Geburtstag'] != 0) {
        $vcard->add('BDAY', new \DateTime($musician['Geburtstag']));
      }
      if ($musician['Aktualisiert'] != 0) {
        $vcard->add('REV', (new \DateTime($musician['Aktualisiert']))->format(\DateTime::W3C));
      }
      $countryNames = GeoCoding::countryNames('en');
      if (!isset($countryNames[$musician['Land']])) {
        $country = null;
      } else {
        $country = $countryNames[$musician['Land']];
      }

      $vcard->add('ADR',
                  [ '', // PO box
                    '', // address extension (appartment nr. and such)
                    $musician['Strasse'], // street
                    $musician['Stadt'], // city
                    '', // province
                    $musician['Postleitzahl'], //zip code
                    $country
                    ],
                  [ 'TYPE' => 'home' ]);
      $vcard->add('CATEGORIES', $categories);

      $musicianId = isset($musician['MusikerId']) ? $musician['MusikerId'] : $musician['Id'];
      $inlineImage = new InlineImage('Musiker');
      $photo = $inlineImage->fetch($musicianId);
      if (isset($photo['Data']) && $photo['Data']) {
        $ocImage = new \OCP\Image();
        $ocImage->loadFromBase64($photo['Data']);
        $imageData = $ocImage->data(); // base64_decode($photo['Data']);
        $mimeType = $photo['MimeType'];
        if (self::VERSION == '4.0') {
          $type = $mimeType;
          //$vcard->add('PHOTO', 'data:'.$type.';base64,'.$imageData);
        } else {
          $type = explode('/', $mimeType);
          $type = strtoupper(array_pop($type));
        }
        // TODO: does not work with 4.0
        $vcard->add('PHOTO', $imageData, ['ENCODING' => 'b', 'TYPE' => $type ]);
      }
      if (self::VERSION != '4.0') {
        foreach($textProperties as $property) {
          if (isset($vcard->{$property})) {
            $vcard->{$property}['CHARSET'] = 'UTF-8';
          }
        }
      }

      return $vcard;
    }
  };

} // namespace

?>
