<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use Sabre\VObject\Property;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCP\AppFramework\IAppContainer;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;
use OCP\Constants;
use OCP\Image;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician;
use OCA\CAFEVDB\AddressBook\MusicianCardBackend;
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
   * The static musicians address-book of the orchestra app is excluded.
   */
  public function emailContacts()
  {
    /** @var MusicianCardBackend $musicianCardBackend */
    $musicianCardBackend = $this->di(MusicianCardBackend::class);
    $musiciansKey = $musicianCardBackend->getURI();
    $result = [];
    $addressBooks = $this->contactsManager->getUserAddressBooks();
    /** @var IAddressBook $addressBook */
    foreach ($addressBooks as $addressBook) {
      if ($addressBook->getKey() == $musiciansKey) {
        continue;
      }
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
            'uid'   => $uid,
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

  public function flattenVCard(string $cardUri, VCard $vCard, bool $withTypes = true)
  {
    $result = [
      'URI' => $cardUri,
    ];
    foreach ($vCard->children() as $property) {
      if ($property->name === 'PHOTO') {
        if ($property->getValueType() === 'BINARY' && $this->getTypeFromProperty($property)) {
          $uri = 'data:image/'.strtolower($property['TYPE']).';base64,'.$property->getRawMimeDirValue();
          $result[$property->name] = 'VALUE=uri:' . $uri;
        } else if ($property->getValueType() === 'URI') {
          $result[$property->name] = 'VALUE=uri:' . $property->getValue();
        } else {
          $result[$property->name] = $property->getValue();
        }
      } elseif (in_array($property->name, ['URL', 'GEO', 'CLOUD', 'ADR', 'EMAIL', 'IMPP', 'TEL', 'X-SOCIALPROFILE', 'RELATED', 'LANG', 'X-ADDRESSBOOKSERVER-MEMBER'])) {
        if (!isset($result[$property->name])) {
          $result[$property->name] = [];
        }

        $type = $this->getTypeFromProperty($property);
        if ($withTypes) {
          $result[$property->name][] = [
            'type' => $type,
            'value' => $property->getValue()
          ];
        } else {
          $result[$property->name][] = $property->getValue();
        }
      } else {
        $result[$property->name] = $property->getValue();
      }
    }
    return $result;
  }

  /**
   * Get the type of the current property
   *
   * @param Property $property
   * @return null|string
   */
  private function getTypeFromProperty(Property $property) {
    $parameters = $property->parameters();
    // Type is the social network, when it's empty we don't need this.
    if (isset($parameters['TYPE'])) {
      /** @var \Sabre\VObject\Parameter $type */
      $type = $parameters['TYPE'];
      return $type->getValue();
    }

    return null;
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
  public function importVCard(VCard $vCard)
  {
    $cardData = $this->flattenVCard($vCard->URI ?? null, $vCard, withType: true);
    return $this->importCardData($cardData);
  }

  public function importCardData(array $cardData, bool $preferWork = true)
  {
    $version = $cardData['VERSION'];

    $entity = new Musician();
    if (!empty($cardData['N'])) {
        // we honour only surname and prename, and give a damn in
        // particular on title madness.
      $parts = explode(';', $cardData['N']);
      $entity
        ->setSurName($parts[0])
        ->setFirstName($parts[1]);
    }

    // in principle FN would be the displayName
    if (!empty($value = $cardData['FN'])) {
      $entity->setDisplayName($value);
    }

    foreach (($cardData['TEL'] ?? []) as $tel) {
      $type = strtolower($tel['type']);
      $number = $tel['value'];
      $work = strpos($type, 'work') !== false;
      $cell = strpos($type, 'cell') !== false;
      $voice = strpos($type, 'voice') !== false;
      $home = strpos($type, 'home') !== false;

      if (!empty($type) && !$voice && !$home && !$work && !$cell) {
        continue; // FAX etc.
      }
      $key = $cell ? 'mobilePhone' : 'fixedLinePhone';
      if (($work && $preferWork)
          || (!empty($type) && empty($typed[$key]))
          || empty($entity[$key])) {
        $entity[$key] = $number;
        $typed[$key] = !empty($type);
      }
    }

    $typed = false;
    foreach (($cardData['EMAIL'] ?? []) as $email) {
      $type = strtolower($email['type']);
      $address = $email['value'];
      $work = strpos($type, 'work') !== false;

      $key = 'email';
      if (($work && $preferWork) || (!$typed && !empty($type)) || empty($entity[$key])) {
        $entity[$key] = $address;
        $typed = !empty($type);
      }
    }

    if (!empty($value = $cardData['UID'] ?? null)) {
      $entity['UUID'] = $value;
    }

    if (!empty($value = $cardData['LANG'] ?? null)) {
      $entity['language'] = $value;
    }

    if (!empty($value = $cardData['BDAY'] ?? null)) {
      $entity['birthday'] = new \DateTimeImmutable($value);
    }

    if (!empty($value = $cardData['REV'] ?? null)) {
      $entity['updated'] = new \DateTimeImmutable($value);
    }

    // [ADR] => Array ( [0] => Array ( [type] => home [value] => ;;Seestraße 70;Leonberg;;71229;Germany ) )
    $typed = false;
    foreach (($cardData['ADR'] ?? []) as $addr) {
      $type = strtolower($email['type']);
      $address = $addr['value'];
      $work = strpos($type, 'work') !== false;

      if (($work && $preferWork)
          || (!$typed && !empty($type))
          || (empty($entity['country'])
              && empty($entity['street'])
              && empty($entity['city'])
              && empty($entity['postalCode']))) {

        $address = Util::normalizeSpaces($address); // unicode
        $address = explode(';', $address);

        $entity['addressSupplement'] = $address[1];
        $street = Util::normalizeSpaces($address[2]);
        // if the first word or the last word of the street start with a
        // digit, then we treat it as the street-number. This should hack most
        // of the cases for _us_ ...
        $this->logInfo('STREET IS ' . $street);
        $lastWord = substr($street, strrpos($street, ' ') + 1);
        $firstWord = substr($street, 0, strpos($street, ' '));
        $this->logInfo('LAST FIRST' . $lastWord . ' / ' . $firstWord);
        if (ctype_digit($lastWord[0])) {
          $streetNumber = $lastWord;
          $street = substr($street, 0, -strlen($lastWord)-1);
        } else if (ctype_digit($firstWord[0])) {
          $streetNumber = $firstWord;
          $street = substr($street, strlen($firstWord) + 1);
        } else {
          $streetNumber = '';
        }
        $entity['street'] = $street;
        $entity['streetNumber'] = $streetNumber;
        $entity['city'] = $address[3];
        $entity['postalCode'] = $address[5];
        $entity['country'] = $address[6];

        $typed = !empty($type);

        $geoCodingService = $this->geoCodingService();
        $languages = $geoCodingService->getLanguages(true);
        foreach($languages as $language) {
          $countries = $geoCodingService->countryNames($language);
          $iso = array_search($entity['country'], $countries);
          if ($iso !== false) {
            $entity['country'] = $iso;
          }
        }
      }
    } // ADR

    if (!empty($value = $cardData['CATEGORIES'] ?? null)) {
      $instrumentsRepository = $this->getDatabaseRepository(Entities\Instrument::class);
      $instrumentsInfo = $instrumentsRepository->describeAll(useEntities: true);
      $instruments = $instrumentsInfo['byId'];
      $categories = explode(',', $value);
      $musicianInstruments = [];
      /** @var Entities\Instrument $instrument */
      foreach($instruments as $instrument) {
        $instrumentName = $instrument->getName();
        if (array_search($instrument, $categories)) {
            $musicianInstruments[] = $instrument;
        }
      }
      // now we need to convert to "MusicianInstrument"
      $musicianInstruments = array_map(function($instrument) use ($entity) {
        $musicianInstrument = (new Entities\MusicianInstrument())
          ->setMusician($entity)
          ->setInstrument($instrument);
      }, $musicianInstruments);

      $entity['instruments'] = new ArrayCollection($musicianInstruments);
    }

    // [PHOTO] => VALUE=uri:http://localhost/nextcloud-git/remote.php/dav/addressbooks/users/claus/z-app-generated--cafevdb--cafevdb-musicians/musician-32653235-3461-6335-2d34-3064362d3461.vcf?photo
    if (!empty($value = $cardData['PHOTO'] ?? null)) {
      // complicated:
      //
      // - extract the image datas or URI
      // - construct a data-base file + data (two entities)
      // - construct the MusicianPhoto entity

      $havePhoto = false;

      // fetch the image data, we only support data-uri ATM
      if (preg_match('|^(VALUE=uri:)?data:(image/[^;]+);base64\\\?,|', $value, $matches)) {
        $mimeType = $matches[1];
        $imageData = base64_decode(substr($entity['photo'], strlen($matches[0])));
        $havePhoto = true;
      } else if (str_starts_with($value, 'VALUE=uri:')) {
        $url = substr($value, strlen('VALUE=uri:'));
        /** @var RequestService $requestService */
        $requestService = $this->di(RequestService::class);
        $imageData = $requestService->getFromInternalUrl($url);
        $havePhoto = true;
      }

      if ($havePhoto)  {
        $image = new Image;
        $image->loadFromData($imageData);

        $imageEntity = new Entities\Image(fileName: null, image: $image);
        $musicianPhoto = (new Entities\MusicianPhoto())
          ->setOwner($entity)
          ->setImage($imageEntity);
      }
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
    if ($musician->getEmailAddress()) {
      $vcard->add('EMAIL', $musician->getEmailAddress());
    }
    if ($musician['MobilePhone']) {
      $vcard->add('TEL', $musician['mobilePhone'], ['TYPE' => [ 'cell', 'voice' ] ]);
    }
    if ($musician['FixedLinePhone']) {
      $vcard->add('TEL', $musician['fixedLinePhone'], ['TYPE' => 'voice' ]);
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
                  $musician['addressSupplement'], // address extension (appartment nr. and such)
                  $musician['street'] . ' ' . $musician['streetNumber'], // street
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
