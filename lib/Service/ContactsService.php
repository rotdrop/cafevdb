<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Sabre\VObject\Component\VCard;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use OCP\AppFramework\IAppContainer;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Constants;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician;

use OCA\CAFEVDB\Common\Util;

/** Contacts handling. */
class ContactsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const VCARD_VERSION = '4.0';

  /** @var IAppContainer */
  private $appContainer;

  /** @var IContactsManager */
  private $contactsManager;

  public function __construct(
    ConfigService $configService
    , IContactsManager $contactsManager
    , IAppContainer $appContainer
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->contactsManager = $contactsManager;
    $this->appContainer = $appContainer;
    $this->entityManager = $entityManager;
  }

  private function geoCodingService()
  {
    return $this->di(GeoCodingService::class);
  }

  /**
   * Find a matching addressbook by its displayname.
   *
   * @param $displayName The display name.
   *
   * @param $includeShared Include also shared addressbooks.
   *
   * @return The address-book object (row of the database, i.e. an array).
   */
  public function addressBookByName($displayName, $includeShared = false)
  {
    $addressBooks = $this->contactsManager->getUserAddressBooks();

    foreach ($addressBooks as $addressBook) {
      // $displayName = $addressBook->getDisplayName();
      // $key = $addressBook->getKey();
      // $uri = $addressBook->getUri();
      // $permissions = $adressBook->getPermissions();
      if (!$addressBook->isShared() && !$includeShared) {
        continue;
      }
      if ($displayName == $addressBook->getDisplayName()) {
        return $addressBook;
      }
    }
    return null;
  }

  /**
   * Fetch a list of contacts with email addresses for the current
   * user. The return value is a "matrix" of the form
   *
   * ```
   * [
   *   [
   *     'email' => 'email@address.com',
   *     'name'  => 'John Doe',
   *     'addressbook' => 'Bookname',
   *   ]
   * ]
   * ```
   *
   * As of now categories are not exported for shared address-books,
   * so we simply group the entries by addressbook-name.
   *
   * @todo Check for musicians address book
   */
  public function emailContacts()
  {
    $result = [];
    $addressBooks = $this->contactsManager->getUserAddressBooks();
    foreach ($addressBooks as $addressBook) {
      // @todo skip musicians address book
      $bookName = $addressBook->getDisplayName();
      $contacts = $addressBook->search('', [ 'FN', 'EMAIL' ], [] /* options */);
      foreach ($contacts as $contact) {
        // $this->logInfo('CONTACT: ' . print_r($contact, true));
        $uid = $contact['UID'];
        $fn = $contact['FN']??'';
        $emails = $contact['EMAIL']??null;
        if (empty($emails)) {
          continue;
        }
        if (!is_array($emails)) {
          $emails = [ $emails ];
        }
        $theseContacts = [];
        foreach ($emails as $email) {
          if (empty($email)) {
            continue;
          }
          $theseContacts[] = [
            'uid'    => $uid,
            'email' => $email,
            'name'  => $fn,
            'addressBook' => $bookName,
          ];
        }
        usort($theseContacts, function($a, $b) {
          $aName = $a['name'] != '' ? $a['name'] : $a['email'];
          $bName = $b['name'] != '' ? $b['name'] : $b['email'];
          return strcmp($aName, $bName);
        });
        $result = array_merge($result, $theseContacts);
      }
    }
    return $result;
  }

  /**
   * Add the given email address as possibly new entry to the address book.
   *
   * @param array $emailContact Contact to be added `[ 'name' => FN, 'email' => EMAIL ]`
   *
   * @param string $addressBookKey If set, the id of the address-book to add
   * entries to. Otherwise the @c addressbookid config-value will be
   * used. If none is set, return @c false.
   */
  public function addEmailContact($emailContact, $addressBookKey = null)
  {
    if (empty($addressBookKey)) {
      $addressBookKey = $this->getConfigValue('generaladdressbookid', false);
      if (empty($addressBookKey)) {
        return null;
      }
    }
    $newContact = $this->contactsManager->createOrUpdate(
      [
        'EMAIL' => $emailContact['email'],
        'FN' => $emailContact['name'],
      ],
      $addressBookKey);

    return $newContact;
  }

  /**
   * Import the given vCard into the musician data-base. This is
   * somewhat problematic: the CAFeV DB data-base does not support
   * fancy fields. Other things: being a layman-orchestra, we prefer
   * private entries for everything and just choose the first stuff
   * available if no personal data is found.
   *
   * We import the following properties
   *
   * N, UID, LANG, EMAIL, TEL, REV, ADR, BDAY, CATEGORIES, PHOTO
   *
   * CATEGORIES are used to code instruments and project membership.
   *
   * @param string $vCard Serialized vCard data.
   *
   * @param boolean $preferWork Set to @c true in order to
   * favour work over home data.
   *
   * @return Musiker entity.
   *
   * @bug Looks complicated like hell. Simplify?
   */
  public function import($vCard, $preferWork = false)
  {
    $entity = new Musician();
    // first step: parse the vCard into a Sabre\VObject
    try {
      $obj = \Sabre\VObject\Reader::read(
        $vCard,
        \Sabre\VObject\Reader::OPTION_FORGIVING|\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
      );

      $version = (string)$obj->VERSION;

      if (isset($obj->N)) {
        // we honour only surname and prename, and give a damn in
        // particular on title madness.
        $parts = $obj->N->getParts();
        $entity->setName($parts[0])
               ->setVorname($parts[1]);
      }

      if (isset($obj->TEL)) {
        foreach($obj->TEL as $tel) {
          $work = false;
          $cell = false;
          $skip = false;
          if ($param = $tel['TYPE']) {
            $skip = true;
            foreach ($param as $type) {
              switch($type) {
              case 'WORK': $work = true; $skip = false; break;
              case 'CELL': $cell = true; $skip = false; break;
              case 'HOME': $work = false; $skip = false; break;
              default: break;
              }
            }
          }
          if ($skip) {
            continue; // FAX etc.
          }
          $key = $cell ? 'MobilePhone' : 'FixedLinePhone';
          if ($work == $preferWork || !isset($entity[$key])) {
            $entity[$key] = (string)$tel;
          }
        }
      }

      if (isset($obj->EMAIL)) {
        foreach ($obj->EMAIL as $email) {
          $work = false;
          $skip = false;
          if ($param = $email['TYPE']) {
            $skip = true;
            foreach ($param as $type) {
              switch($type) {
              case 'WORK': $work = true; $skip = false; break;
              case 'HOME': $work = false; $skip = false; break;
              default: break;
              }
            }
          }
          if ($skip) {
            continue; // unknown
          }
          $key = 'Email';
          if ($work == $preferWork || !isset($entity[$key])) {
            $entity[$key] = (string)$email;
          }
        }
      }

      if (isset($obj->UID)) {
        $entity['UUID'] = (string)$obj->UID;
      }

      if (isset($obj->LANG)) {
        $entity['Sprachpräferenz'] = (string)$obj->LANG;
      }

      if (isset($obj->BDAY)) {
        $entity['Geburtstag'] = date('Y-m-d H:i:s', strtotime((string)$obj->BDAY));
      }

      if (isset($obj->REV)) {
        $entity['Aktualisiert'] = date('Y-m-d H:i:s', strtotime((string)$obj->REV));
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
            $skip = true;
            foreach ($param as $type) {
              switch($type) {
              case 'WORK': $work = true; $skip = false; break;
              case 'HOME': $work = false; $skip = false;  break;
              default: break;
              }
            }
          }
          if ($skip) {
            continue; // unknown
          }
          if ($work != $preferWork && (isset($entity['Land']) ||
                                       isset($entity['Strasse']) ||
                                       isset($entity['Stadt']) ||
                                       isset($entity['Postleitzahl']))) {
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
              $entity[$fields[$idx]] = $value;
            }
          }

          $geoCodingService = $this->geoCodingService();
          $languages = $geoCodingService->languages(true);
          foreach($languages as $language) {
            $countries = $geoCodingService->countryNames($language);
            $iso = array_search($entity['countries'], $countries);
            if ($iso !== false) {
              $entity['Land'] = $iso;
            }
          }
        }
      } // ADR

      if (isset($obj->CATEGORIES)) {
        $instrumentInfo = Instruments::fetchInfo();
        $instruments = $instrumentInfo['byId'];
        $categories = $obj->CATEGORIES->getParts();
        $musicianInstruments = array();
        foreach($instruments as $instrument) {
          if (array_search($instrument, $categories)) {
            $musicianInstruments[] = $instrument;
          }
        }
        $entity['Instruments'] = implode(',', $musicianInstruments);
      }

      if (isset($obj->PHOTO)) {
        $photo = $obj->PHOTO;
        $havePhoto = false;
        if ((float)$version >= 4.0) {
          $rawData = $photo->getRawMimeDirValue();
          if (preg_match('|^data:(image/[^;]+);base64\\\?,|', $rawData, $matches)) {
            $mimeType = $matches[1];
            $imageData = substr($entity['Portrait'], strlen($matches[0]));
            $haveData = true;
          }
        } else {
          $type = $obj->PHOTO['TYPE'];
          $mimeType = 'image/'.strtolower($type);
          $imageData = $photo->getRawMimeDirValue();
          $havePhoto = true;
        }

        if ($havePhoto) {
          $entity['Portrait'] = 'data:'.$mimeType.';base64,'.$imageData;
        }
      }

    } catch (\Exception $e) {
      $this->logError(__METHOD__.": ". "Error parsing card-data " . $e->getMessage() . " " . $e->getTraceAsString());
    }

    return $entity;
  }

  /**
   * Export the stored data for one musician as vCard.
   *
   * @param Musician $musician One row from the musician table.
   *
   * @param $version vCard version -- which must be one
   * supported by \\Sabre\\VObject. Defaults to 3.0 for compatibility
   * reasons. Note that many (mobile) devices still only use the
   * stone-age v2.1 format.
   */
  public function export(Musician $musician, $version = self::VCARD_VERSION)
  {
    $textProperties = array('FN', 'N', 'CATEGORIES', 'ADR', 'NOTE');
    $uuid = (string)(isset($musician['uuid']) ? $musician['uuid'] : $this->generateUUID());
    $categories = [ 'cafevdb' ];
    foreach ($musician['instruments'] as $musicianInstrument) {
      $categories[] = $musicianInstrument['instrument']['name'];
    }
    foreach ($musician['projectParticipation'] as $participant) {
      $categories[] = $participant['project']['name'];
    }
    $prodid = '-//CAF e.V.//NONSGML ' . $this->appName() . ' ' . $this->appVersion() . '//EN';

    $vcard = new VCard(
      [
        'VERSION' => $version,
        'PRODID' => $prodid,
        'UID' => $uuid,
        'FN' => $musician['firstName'].' '.$musician['surName'],
        'N' => [ $musician['surName'], $musician['firstName'] ],
        'CATEGORIES' => $categories,
      ]);
    if ($musician['language']) {
      $vcard->add('LANG', $musician['language']);
    }
    if ($musician['Email']) {
      $vcard->add('EMAIL', $musician['email']);
    }
    if ($musician['MobilePhone']) {
      $vcard->add('TEL', $musician['mobilePhone'], ['TYPE' => 'cell']);
    }
    if ($musician['FixedLinePhone']) {
      $vcard->add('TEL', $musician['fixedLinePhone']);
    }
    if (!empty($musician['birthday'])) {
      $birthDay = $musician['birthday'];
      if (is_string($birthDay)) {
        $birthDay = Util::dateTime($birthDay);
      }
      $vcard->add('BDAY', $birthDay);
    }
    if (!empty($musician['updated'])) {
      $vcard->add('REV', (Util::dateTime($musician['updated']))->format(\DateTime::W3C));
    }
    $countryNames = $this->geoCodingService()->countryNames('en');
    if (!isset($countryNames[$musician['country']])) {
      $country = null;
    } else {
      $country = $countryNames[$musician['country']];
    }

    $vcard->add('ADR',
                [ '', // PO box
                  '', // address extension (appartment nr. and such)
                  $musician['street'], // street
                  $musician['city'], // city
                  '', // province
                  $musician['postalCode'], //zip code
                  $country
                ],
                [ 'TYPE' => 'home' ]);

    $photo = null;
    if (!empty($musician['photo'])) {
      if ($musician['photo'] instanceof Entities\MusicianPhoto) {
        $image = $musician['photo']->getImage(); //  ['image'];
        $photo = [
          'data' => $image->getFileData()->getData('base64'),
          'mimeType' => $image->getMimeType(), //['mimeType'],
        ];

      } else if (is_string($musician['photo'])
                 && preg_match('|^data:(image/[^;]+);base64\\\?,|', $musician['photo'], $matches)) {
        // data uri
        $mimeType = $matches[1];
        $imageData = substr($musician['photo'], strlen($matches[0]));
        $photo = [
          'mimeType' => $mimeType,
          'data' => $imageData,
        ];
      } else {
        throw new \Exception('Strange photo value');
      }

      if (!empty($photo['data'])) {
        $mimeType = $photo['mimeType'];
        $data = $photo['data'];
        if ($version == '4.0') {
          $vcard->add('PHOTO', 'data:'.$mimeType.';base64,'.$data);
        } else {
          $type = Util::explode('/', $mimeType);
          $type = strtoupper(array_pop($type));
          $data = base64_decode($data);
          $vcard->add('PHOTO', $data, ['ENCODING' => 'b', 'TYPE' => $type ]);
        }
      }
    }

    if ($version != '4.0') {
      foreach($textProperties as $property) {
        if (isset($vcard->{$property})) {
          $vcard->{$property}['CHARSET'] = 'UTF-8';
        }
      }
    }

    return $vcard;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
