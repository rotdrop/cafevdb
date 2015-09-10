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
    
    /**Export the stored data for one musician as vCard.
     *
     * @param[in] $musician One row from the musician table.
     */
    public static function vCard($musician)
    {
      $textProperties = array('FN', 'N', 'CATEGORIES', 'ADR');
      $uuid = isset($musician['UUID']) ? $musician['UUID'] : Util::generateUUID();
      $categories = array_map('trim',
                              array_merge(explode(',', $musician['Instrumente']),
                                          explode(',', $musician['Projekte'])));
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
          'CATEGORIES' => $categories
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
      $vcard->add('ADR',
                  [ '', // PO box
                    '', // address extension (appartment nr. and such)
                    $musician['Strasse'], // street
                    $musician['Stadt'], // city
                    '', // province
                    $musician['Postleitzahl'], //zip code
                    GeoCoding::countryNames('en')[$musician['Land']] // country
                    ],
                  [ 'TYPE' => 'home' ]);
      foreach($textProperties as $property) {
        if (isset($vcard->{$property})) {
          $vcard->{$property}['CHARSET'] = 'UTF-8';
        }
      }

      $inlineImage = new InlineImage('Musiker');
      $photo = $inlineImage->fetch($musician['Id']);
      if (isset($photo['Data']) && $photo['Data']) {
        $ocImage = new \OCP\Image();
        $ocImage->loadFromBase64($photo['Data']);
        $imageData = $ocImage->data(); // base64_decode($photo['Data']);
        $mimeType = $photo['MimeType'];
        if (self::VERSION == '4.0') {
          $type = $mimeType;
        } else {
          $type = explode('/', $mimeType);
          $type = strtoupper(array_pop($type));
        }
        $vcard->add('PHOTO', $imageData, ['ENCODING' => 'b', 'TYPE' => $type ]);
      }
      
      return $vcard;
    }
  };

} // namespace

?>
