<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use DateTimeImmutable;
use Throwable;


use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;

use OCP\AppFramework\IAppContainer;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\Image;

use OCA\CAFEVDB\AddressBook\MusicianCardBackend;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\Transliterator;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\ConfigConstants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumGender;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Listener\ContactsCardEventListener;

/** Contacts handling. */
class ContactsService
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const VCARD_VERSION = '4.0';

  const AVATAR_SIZE = 256;

  const TYPED_PROPERTIES = [
    'URL',
    'GEO',
    'CLOUD',
    'ADR',
    'EMAIL',
    'IMPP',
    'TEL',
    'X-SOCIALPROFILE',
    'RELATED',
    'LANG',
    'X-ADDRESSBOOKSERVER-MEMBER',
  ];

  /** @var array<string, IAddressBook> */
  private array $addressBooksByUri = [];

  /**
   * @var array<int, Entities\Musician>
   *
   * Arrays id => musician for already registered contact syncs.
   */
  private array $contactSynchronizations = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IAvatarManager $avatarManager,
    private IContactsManager $contactsManager,
    private Transliterator $transliterator,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    protected IAppContainer $appContainer,
  ) {
    $this->l = $this->configService->getAppL10n();
  }
  // phpcs:enable

  /** @return GeoCodingService */
  private function geoCodingService():GeoCodingService
  {
    return $this->appContainer->get(GeoCodingService::class);
  }

  /** @return PhoneNumberService */
  private function phoneNumberService():PhoneNumberService
  {
    return $this->appContainer->get(PhoneNumberService::class);
  }

  /**
   * Get a addressBook with the given uri. If the address-book is shared the
   * uri is tweak to match the NC-style "..._shared_by_..." uri.
   *
   * @param null|string $uri A string in the form PRINCIPAL_URI/ADDRESS_BOOK_URI,
   * e.g. cameratashareholder/general
   *
   * @return null|IAddressBook
   */
  public function addressBookByUri(?string $uri):?IAddressBook
  {
    if (empty($uri)) {
      return null;
    }
    list($principal, $addressBookUri) = explode('/', $uri);
    if ($principal != $this->userId()) {
      $addressBookUri .= '_shared_by_' . $principal;
    }
    if (!array_key_exists($addressBookUri, $this->addressBooksByUri)) {
      $this->addressBooksByUri[$addressBookUri] = null;
      /** @var IAddressBook $addressBook */
      foreach ($this->contactsManager->getUserAddressBooks() as $addressBook) {
        if ($addressBookUri === $addressBook->getUri()) {
          $this->addressBooksByUri[$addressBookUri] = $addressBook;
          break;
        }
      }
    }
    return $this->addressBooksByUri[$addressBookUri];
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
   * The static musicians address-book of the orchestra app is excluded. This
   * is used by the email-form.
   *
   * @return array
   */
  public function emailContacts():array
  {
    /** @var MusicianCardBackend $musicianCardBackend */
    $musicianCardBackend = $this->appContainer->get(MusicianCardBackend::class);
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
        $uid = $contact['UID'];
        $fullName = $contact['FN']??'';
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
            'name'  => $fullName,
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
   * @param array $emailContact Contact to be added `[ 'name' => FN, 'email' => EMAIL ]`.
   *
   * @param null|string $addressBookKey If set, the id of the address-book to add
   * entries to. Otherwise the @c addressbookid config-value will be
   * used. If none is set, return null.
   *
   * @return null|array
   */
  public function addEmailContact(array $emailContact, ?string $addressBookKey = null):?array
  {
    if (empty($addressBookKey)) {
      $addressBookKey = $this->getConfigValue(ConfigConstants::GENERAL_ADDRESS_BOOK_ID_KEY, false);
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
   * @param null|string $cardUri
   *
   * @param VCard $vCard
   *
   * @param bool $withTypes
   *
   * @return array
   */
  public function flattenVCard(?string $cardUri, VCard $vCard, bool $withTypes = true):array
  {
    $result = [
      'URI' => $cardUri,
    ];
    foreach ($vCard->children() as $property) {
      if ($property->name === 'PHOTO') {
        if ($property->getValueType() === 'BINARY' && $this->getTypeFromProperty($property)) {
          $uri = 'data:image/' . strtolower($property['TYPE']) . ';base64,' . $property->getRawMimeDirValue();
          $result[$property->name] = 'VALUE=uri:' . $uri;
        } elseif ($property->getValueType() === 'URI') {
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
            'type' => strtoupper($type),
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
   *
   * @return null|string
   */
  private function getTypeFromProperty(Property $property):?string
  {
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
   * somewhat problematic: the CAFeV DB database does not support
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
   * @param null|Entities\Musician $entity If given update the given entity
   * from the card-data, otherwise create a new entity.
   *
   * @param VCard $vCard Serialized vCard data.
   *
   * @param bool $preferWork Prefer WORK variant for all properties which have
   * multiple types. Default \true.
   *
   * @param bool $keepExisting Rather keep the properties of a given musician
   * entity instead of overwriting them. Instruments will be added from the
   * categories and empty fields of the given musician entity will be filled.
   *
   * @return null|Entities\Musician entity.
   *
   * @bug Looks complicated like hell. Simplify?
   */
  public function importVCard(
    null|Entities\Musician $entity,
    VCard $vCard,
    bool $preferWork = true,
    bool $keepExisting = true,
  ):?Entities\Musician {
    $cardData = $this->flattenVCard($vCard->URI ?? null, $vCard, withTypes: true);
    return $this->importCardData($entity, $cardData, $preferWork, $keepExisting);
  }

  /**
   * @param null|Entities\Musician $entity If given update the given entity
   * from the card-data, otherwise create a new entity.
   *
   * @param array $cardData
   *
   * @param bool $preferWork Prefer WORK variant for all properties which have
   * multiple types. Default \true.
   *
   * @param bool $keepExisting Rather keep the properties of a given musician
   * entity instead of overwriting them. Instruments will be added from the
   * categories and empty fields of the given musician entity will be filled.
   *
   * @return null|Entities\Musician
   */
  public function importCardData(
    null|Entities\Musician $entity,
    array $cardData,
    bool $preferWork = true,
    bool $keepExisting = false,
  ):?Entities\Musician {

    if ($entity === null) {
      $keepExisting = false;
      $entity = new Entities\Musician();
    }

    if (empty($cardData['N']) && !empty($cardData['FN'])) {
      if (str_contains($cardData['FN'], ',')) {
        $parts = array_map(fn(string $part) => Util::normalizeSpaces($part), explode(',', $cardData['FN']));
        $firstName = array_pop($parts);
        $surName = implode(',', $parts);
      } else {
        $parts = array_map(fn(string $part) => Util::normalizeSpaces($part), explode(' ', $cardData['FN']));
        $surName = array_pop($parts);
        $firstName = implode(' ', $parts);
      }
      $cardData['N'] = $surName . ';' . $firstName;
    }

    if (!empty($cardData['N'])) {
      // we honour only surname and prename, and give a damn in
      // particular on title madness.
      $parts = array_map(fn(string $part) => Util::normalizeSpaces($part), explode(';', $cardData['N']));
      if (!$keepExisting || empty($entity->getSurName())) {
        $entity->setSurName($parts[0]);
      }
      if (!$keepExisting || empty($entity->getFirstName())) {
        $entity->setFirstName($parts[1]);
      }
    }

    if (!$keepExisting || empty($entity->getNickName())) {
      $typed = [];
      foreach (($cardData['NICKNAME'] ?? []) as $property) {
        $type = strtoupper((string)$property['type']) ?: 'UNTYPED';
        $typed[$type] = $property['value'];
      }
      if (!empty($typed)) {
        if ($preferWork && !empty($typed['WORK'])) {
          $value = $typed['WORK'];
        } else {
          $value = array_shift($typed);
        }
        $entity->setNickName($value);
      }
    }

    if (!$keepExisting || empty($entity->getUserIdSlug())) {
      // set the user-id slug ....
      $parts = empty($entity->getNickName())
        ? [ $entity->getFirstName() ]
        : [ $entity->getNickName() ];
      $parts[] = $entity->getSurName();
      $slug = $this->transliterator->generateUserIdSlug([
        'firstName' => $entity->getFirstName(),
        'nickName' => $entity->getNickName(),
        'surName' => $entity->getSurName(),
      ]);
      $entity->setUserIdSlug($slug);
    }

    // in principle FN would be the displayName
    $value = Util::normalizeSpaces($cardData['FN']);
    if (!empty($value) && (!$keepExisting || $entity->getDisplayName() === null)) {
      $entity->setDisplayName(null);
      $firstNameFirst = !str_contains($cardData['FN'], ',');
      $publicName = $entity->getPublicName($firstNameFirst);
      if ($publicName != $value) {
        $entity->setDisplayName($value);
      } else {
        // avoid redundant display name setttings
        $entity->setDisplayName(null);
      }
    }

    $entityValues = [];
    foreach (($cardData['TEL'] ?? []) as $tel) {
      $type = strtoupper($tel['type']);
      $number = $tel['value'];
      $work = strpos($type, 'WORK') !== false;
      $cell = strpos($type, 'CELL') !== false;
      $voice = strpos($type, 'VOICE') !== false;
      // $home = strpos($type, 'HOME') !== false;

      if (!empty($type) && !$voice  && !$cell) {
        continue; // FAX etc.
      }
      $key = $cell ? 'mobilePhone' : 'fixedLinePhone';
      if (($work && $preferWork)
          || (!empty($type) && empty($typed[$key]))
          || empty($entityValues[$key])) {
        $entityValues[$key] = $number;
        $typed[$key] = !empty($type);
      }
    }
    foreach ($entityValues as $key => $value) {
      if (!$keepExisting || empty($entity[$key])) {
        $entity[$key] = $value;
      }
    }

    if (!$keepExisting || empty($entity->getEmail())) {
      $typed = false;
      foreach (($cardData['EMAIL'] ?? []) as $email) {
        $type = strtoupper($email['type']);
        $address = $email['value'];
        $work = strpos($type, 'WORK') !== false;

        $key = 'email';
        if (($work && $preferWork) || (!$typed && !empty($type)) || empty($entity[$key])) {
          $entity[$key] = $address;
          $typed = !empty($type);
        }
      }
    }

    if (!$keepExisting || empty($entity->getUuid())) {
      $value = $cardData['UID'] ?? null;
      if (!empty($value)) {
        $entity['UUID'] = $value;
      }
    }

    if (!$keepExisting || empty($entity->getLanguage())) {
      $typed = false;
      foreach (($cardData['LANG'] ?? []) as $lang) {
        $type = strtoupper($lang['type']);
        $value = $lang['value'];
        $work = strpos($type, 'WORK') !== false;

        $key = 'language';
        if (($work && $preferWork) || (!$typed && !empty($type)) || empty($entity[$key])) {
          $entity[$key] = $value;
          $typed = !empty($type);
        }
      }
    }

    if (!$keepExisting || empty($entity->getBirthday())) {
      $value = $cardData['BDAY'] ?? null;
      if (!empty($value)) {
        $entity['birthday'] = new DateTimeImmutable($value);
      }
    }

    if (!$keepExisting || empty($entity->getUpdated())) {
      $value = $cardData['REV'] ?? null;
      if (!empty($value)) {
        $entity['updated'] = new DateTimeImmutable($value);
      }
    }

    $isWorkAddress = false;

    // [ADR] => Array ( [0] => Array ( [type] => home [value] => ;;Seestraße 70;Leonberg;;71229;Germany ) )
    $entityValues = [];
    $typed = false;
    foreach (($cardData['ADR'] ?? []) as $addr) {
      $type = strtoupper($addr['type']);
      $address = $addr['value'];
      $work = strpos($type, 'WORK') !== false;

      if (($work && $preferWork)
          || (!$typed && !empty($type))
          || (empty($entityValues['country'])
              && empty($entityValues['street'])
              && empty($entityValues['city'])
              && empty($entityValues['postalCode']))) {
        $isWorkAddress = $work;

        $address = Util::normalizeSpaces($address); // unicode
        $address = explode(';', $address);

        $poBox = $address[0];
        $entityValues['addressSupplement'] = $address[1];
        $street = Util::normalizeSpaces($address[2]);
        // if the first word or the last word of the street start with a
        // digit, then we treat it as the street-number. This should hack most
        // of the cases for _us_ ...
        $lastWord = substr($street, strrpos($street, ' ') + 1);
        $firstWord = substr($street, 0, strpos($street, ' '));
        if (!empty($lastWord) && ctype_digit($lastWord[0])) {
          $streetNumber = $lastWord;
          $street = substr($street, 0, -strlen($lastWord)-1);
        } elseif (!empty($firstWord) && ctype_digit($firstWord[0])) {
          $streetNumber = $firstWord;
          $street = substr($street, strlen($firstWord) + 1);
        } else {
          $streetNumber = '';
        }

        $entityValues['poBox'] = $poBox;
        $entityValues['street'] = $street;
        $entityValues['streetNumber'] = $streetNumber;
        $entityValues['city'] = $address[3];
        $entityValues['postalCode'] = $address[5];
        $entityValues['country'] = $address[6];

        $typed = !empty($type);

        $geoCodingService = $this->geoCodingService();
        $languages = $geoCodingService->getLanguages(true);
        foreach ($languages as $language) {
          $countries = $geoCodingService->countryNames($language);
          $iso = array_search($entityValues['country'], $countries);
          if ($iso !== false) {
            $entityValues['country'] = $iso;
          }
        }
      }
    } // ADR
    foreach ($entityValues as $key => $value) {
      if (!$keepExisting || empty($entity[$key])) {
        $entity[$key] = $value;
      }
    }

    if (!$keepExisting || empty($entity->getOrganization())) {
      $value = $cardData['ORG'] ?? null;
      $entity->setOrganization($value);
    }

    if (!$keepExisting || empty($entity->getJobTitle())) {
      $value = $cardData['TITLE'] ?? null;
      $entity->setJobTitle($value);
    }

    if (!$isWorkAddress && empty($entity->getAddressSupplement()) && !empty($entityValues)) {
      $publicName = $entity->getPublicName(firstNameFirst: true);
      $entity->setAddressSupplement('c/o ' . $publicName);
    }

    $value = $cardData['CATEGORIES'] ?? null;
    if (!empty($value)) {
      $categories = explode(',', $value);
      $instrumentsRepository = $this->getDatabaseRepository(Entities\Instrument::class);
      $instrumentCategories = $instrumentsRepository->findNames();
      $instrumentCategories = array_intersect($categories, $instrumentCategories);
      $musicianInstruments = $entity->getInstruments()
        ->map(fn(Entities\MusicianInstrument $instrument) => $instrument->getName())
        ->toArray();
      $missingInstruments = array_diff($instrumentCategories, $musicianInstruments);
      $excessInstruments = array_diff($musicianInstruments, $instrumentCategories);
      if (!empty($missingInstruments)) {
        $instruments = $instrumentsRepository->findBy(
          [ 'name' => $missingInstruments ],
          orderBy: [ 'name' => 'INDEX' ],
        );
        foreach ($missingInstruments as $instrumentName) {
          $instrument = $instruments[$instrumentName];
          if (empty($instrument)) {
            continue;
          }
          $entity->addInstrument($instrument);
        }
      }
      if (!$keepExisting) {
        $excessInstruments = $entity->getInstruments()
          ->filter(fn(Entities\MusicianInstrument $instrument) => in_array($instrument->getName(), $excessInstruments));
        /** @var Entities\MusicianInstrument $musicianInstrument */
        foreach ($excessInstruments as $musicianInstrument) {
          $this->remove($musicianInstrument); // soft if in use
          if ($musicianInstrument->unused()) {
            $this->remove($musicianInstrument);
            $entity->getInstruments()->remove($musicianInstrument);
          }
        }
      }
    }

    if (!$keepExisting || empty($entity->getGender())) {
      $value = $cardData['GENDER'] ?? null;
      if (!empty($value)) {
        switch ($value) {
          case 'M':
            $entity->setGender(EnumGender::MALE);
            break;
          case 'F':
            $entity->setGener(EnumGender::FEMALE);
            break;
          case 'O':
            $entity->setGender(EnumGender::DIVERSE);
            break;
          case 'N':
          case 'U':
            break;
        }
      }
    }

    if (!empty($entity->getOrganization())) {
      $entity->setDefaultParticipationStatus(EnumParticipationStatus::ASSOCIATED);
    }

    // Ignore image data.

    return $entity;
  }

  /**
   * Export the stored data for one musician as vCard.
   *
   * @param Entities\Musician $musician One row from the musician table.
   *
   * @param string $version vCard version -- which must be one
   * supported by \\Sabre\\VObject. Defaults to 3.0 for compatibility
   * reasons. Note that many (mobile) devices still only use the
   * stone-age v2.1 format.
   *
   * @return VCard
   */
  public function export(Entities\Musician $musician, string $version = self::VCARD_VERSION):?VCard
  {
    /** @var Entities\Musician $musician */
    $textProperties = [ 'FN', 'N', 'CATEGORIES', 'ADR', 'NOTE', 'ORG', 'TITLE' ];
    $uuid = (string)($musician->getUuid() ? $musician->getUuid() : $this->generateUUID());
    $categories = [ $this->appName() ];
    /** @var Entities\MusicianInstrument $musicianInstrument */
    foreach ($musician->getInstruments() as $musicianInstrument) {
      $categories[] = $musicianInstrument->getName();
    }
    /** @var Entities\ProjectParticipant $participant */
    foreach ($musician->getProjectParticipation() as $participant) {
      $categories[] = $participant->getProject()->getName();
    }
    $prodid = '-//CAF e.V.//NONSGML ' . $this->appName() . ' ' . $this->appVersion() . '//EN';

    $baseParameters =
      (!empty($musician->getOrganization()) && !str_starts_with((string)$musician->getAddressSupplement(), 'c/o'))
      ? [ 'TYPE' => [ 'WORK' ] ]
      : [ 'TYPE' => [ 'HOME' ] ];

    $vCard = new VCard(
      [
        'VERSION' => $version,
        'PRODID' => $prodid,
        'UID' => $uuid,
        'FN' => $musician->getPublicName(firstNameFirst: true),
        'N' => [ $musician->getSurName(), $musician->getFirstName() ],
        'CATEGORIES' => $categories,
      ]);
    if ($musician->getNickName()) {
      $vCard->add('NICKNAME', $musician->getNickName());
    }
    if ($musician->getOrganization()) {
      $vCard->add('ORG', $musician->getOrganization());
    }
    if ($musician->getJobTitle()) {
      $vCard->add('TITLE', $musician->getJobTitle());
    }
    if ($musician->getLanguage()) {
      $vCard->add('LANG', $musician->getLanguage(), $baseParameters);
    }
    $gender = null;
    switch ($musician->getGender()) {
      case EnumGender::MALE:
        $gender = 'M';
        break;
      case EnumGender::FEMALE:
        $gender = 'F';
        break;
      case EnumGender::DIVERSE:
        $gender = 'O';
        break;
    }
    if ($gender) {
      $vCard->add('GENDER', $gender);
    }
    if ($musician->getEmail()) {
      $vCard->add('EMAIL', $musician->getEmail(), $baseParameters);
    }
    if ($musician->getMobilePhone()) {
      $vCard->add(
        'TEL',
        $musician->getMobilePhone(),
        Util::arrayMergeRecursive($baseParameters, [ 'TYPE' => [ 'CELL' ] ]),
      );
    }
    if ($musician->getFixedLinePhone()) {
      $vCard->add(
        'TEL',
        $musician->getFixedLinePhone(),
        Util::arrayMergeRecursive($baseParameters, [ 'TYPE' => [ 'VOICE' ] ]),
      );
    }
    if (!empty($musician['birthday'])) {
      $birthDay = $musician['birthday'];
      if (is_string($birthDay)) {
        $birthDay = self::convertToDateTime($birthDay);
      }
      $vCard->add('BDAY', $birthDay);
    }
    if (!empty($musician['updated'])) {
      $vCard->add('REV', gmdate('Ymd\THis\Z', self::convertToDateTime($musician['updated'])->getTimestamp()));
    }
    $countryNames = $this->geoCodingService()->countryNames('en');
    if (!isset($countryNames[$musician['country']])) {
      $country = null;
    } else {
      $country = $countryNames[$musician['country']];
    }

    $vCard->add(
      'ADR', [
        $musician['poBox'], // PO box
        $musician['addressSupplement'], // address extension (appartment nr., c/o and such)
        $musician['street'] . ' ' . $musician['streetNumber'], // street
        $musician['city'], // city
      '', // province
        $musician['postalCode'], //zip code
        $country
      ],
      $baseParameters,
    );

    $gender = $musician->getGender();
    switch ($gender) {
      case EnumGender::MALE:
        $vCard->add('GENDER', 'M');
        break;
      case EnumGender::FEMALE:
        $vCard->add('GENDER', 'F');
        break;
      case EnumGender::DIVERSE:
        $vCard->add('GENDER', 'O');
        break;
      default:
        break;
    }

    if (!$musician->getCloudAccountDeactivated()) {
      /** @var IAvatar $avatar */
      try {
        $avatar = $this->avatarManager->getAvatar($musician->getUserIdSlug());
      } catch (Throwable $t) {
        $avatar = null;
      }

      if ($avatar) {
        /** @var \OCP\IImage $image */
        $image = $avatar->get(self::AVATAR_SIZE);
        $mimeType = $image->mimeType();
        $data = $image->data();
        if ($version == '4.0') {
          $vCard->add('PHOTO', 'data:' . $mimeType . ';base64,' . base64_encode($data));
        } else {
          $type = Util::explode('/', $mimeType);
          $type = strtoupper(array_pop($type));
          $data = base64_decode($data);
          $vCard->add('PHOTO', $data, ['ENCODING' => 'b', 'TYPE' => $type ]);
        }
      }
    }

    if ($version != '4.0') {
      foreach ($textProperties as $property) {
        if (isset($vCard->{$property})) {
          $vCard->{$property}['CHARSET'] = 'UTF-8';
        }
      }
    }

    return $vCard;
  }

  /**
   * Merge a musician entity into an existing contact. The result can be fed
   * in to IAddressBook::createOrUpdate(). The URI component of $target is
   * preserved. If the musician is (soft-)deleted, then the link to the
   * address-book is removed.
   *
   * VCard arguments will be flattened first by ContactsService::flattenVCard().
   *
   * @param array|VCard $target
   *
   * @param Entities\Musician $musician
   *
   * @return arry
   */
  public function mergeMusician(
    array|VCard $target,
    Entities\Musician $musician,
  ): array {
    if ($target instanceof VCard) {
      $target = $this->flattenVCard($target->URI || null, $target, withTypes: true);
    }

    $instrumentCategories = $this->getDatabaseRepository(Entities\Instrument::class)->findNames();
    $projectCategories = $this->getDatabaseRepository(Entities\Project::class)->findNames();
    $targetCategories = array_diff(
      explode(',', $target['CATEGORIES']),
      $instrumentCategories,
      $projectCategories,
      [ $this->appName() ],
    );

    if ($musician->getDeleted() !== null) {
      // unlink the contact and remove all app-categories
      sort($targetCategories);
      $target['CATEGORIES'] = implode(',', $targetCategories);
      return $target;
    }

    $source = $this->flattenVCard(cardUri: null, vCard: $this->export($musician), withTypes: true);

    $result = Util::arrayMergeRecursive([], $target, $source, [ 'URI' => $target['URI'] ?? null ]);
    if (empty($result['URI'])) {
      unset($result['URI']);
    }

    $sourceCategories = explode(',', $source['CATEGORIES']);
    $categories = array_unique(array_merge($targetCategories, $sourceCategories));
    sort($categories);
    $result['CATEGORIES'] = implode(',', $categories);

    // eliminate redundant or empty typed properties.
    foreach (self::TYPED_PROPERTIES as $typedPropery) {
      if (!empty($target[$typedPropery])) {
        $result[$typedPropery] = $target[$typedPropery];
      }
      foreach (($result[$typedPropery] ?? []) as $key => $property) {
        if (empty($property['value'])) {
          unset($result[$typedPropery][$key]);
        }
        if ($typedPropery == 'TEL') {
          $phoneNumberService = $this->phoneNumberService();
          if ($phoneNumberService->validate($property['value'])) {
            $result[$typedPropery][$key]['value'] = $phoneNumberService->format();
            if ($phoneNumberService->isMobile()) {
              $result[$typedPropery][$key]['type'] = str_replace('VOICE', 'CELL', $result[$typedPropery][$key]['type']);
            } else {
              $result[$typedPropery][$key]['type'] = str_replace('CELL', 'VOICE', $result[$typedPropery][$key]['type']);
            }
          } else {
            $this->logError('PHONE NUMBER VALIDATION FAILED FOR ' . $property['value']);
            // unset($result[$typedPropery][$key]); should we?
          }
        }
      }
      if (!empty($source[$typedPropery])) {
        $sourceProperty = $source[$typedPropery][0];
        foreach ($result[$typedPropery] as $key => $property) {
          if ($typedPropery == 'ADR') {
            // assume a match if street, city and PO-code match
            $address = explode(';', Util::normalizeSpaces($property['value']));
            $sourceAddress = explode(';', Util::normalizeSpaces($sourceProperty['value']));
            if ($address[2] == $sourceAddress[2]
                && $address[3] == $sourceAddress[3]
                && $address[5] == $sourceAddress[5]) {
              unset($result[$typedPropery][$key]);
            }
          } elseif ($property['value'] == $sourceProperty['value']) {
            unset($result[$typedPropery][$key]);
          }
        }
        array_unshift($result[$typedPropery], $sourceProperty);
      }
    }

    return $result;
  }

  /**
   * Register a pre-commit action in order to merge changed data into a linked
   * addressbook. This is done "brute-force": the musician is exported into a
   * vCard and the result is then "merged" by ContactService::mergeMusician()
   * into an existing entry, if that is found.
   *
   * @param Entities\Musician $musician The musician which has been altered.
   *
   * @param null|string $oldAddressBookUri If $musician->getAddressBookUri() is
   * null, then this specifies the addressbook of a linked contact which is to
   * be unlinked.
   *
   * @return null|bool Return \null if a registration is already in progress,
   * \false if there is no database transaction active and \true if it was
   * otherwise possible and not redundant to register a pre-commit action.
   */
  public function registerContactSynchronization(Entities\Musician $musician, ?string $oldAddressBookUri = null): ?bool
  {
    if (!empty($this->contactSynchronizations[$musician->getId()])) {
      return false;
    }
    if (!$this->entityManager->isTransactionActive()) {
      return false;
    }
    $this->contactSynchronizations[$musician->getId()] = $musician;
    /** @var ContactsCardEventListener $listener */
    $listener = $this->appContainer->get(ContactsCardEventListener::class);
    $this->entityManager->registerPreCommitAction(
      new GenericUndoable(
        function() use ($musician, $oldAddressBookUri, $listener) {
          if ($listener->isActive()) {
            // avoid recursion, the listener may cause change events which
            // should not in turn cause modifications in the address books.
            unset($this->contactSynchronizations[$musician->getId()]);
            return null;
          }
          if ($musician->isExpired() && $this->entityManager->contains($musician)) {
            $musician->setAddressBookUri(null);
            $this->flush();
          }
          if ($musician->getAddressBookUri() === null) {
            // addressbook has been unlinked, do nothing.
            unset($this->contactSynchronizations[$musician->getId()]);
            return null;
          }
          $addressBookUri = $oldAddressBookUri ?? $musician->getAddressBookUri();
          if (empty($addressBookUri)) {
            unset($this->contactSynchronizations[$musician->getId()]);
            return null;
          }
          $oldAddressBook = $this->addressBookByUri($addressBookUri);
          $oldContact = $this->findMusicianContact($musician, $addressBookUri);
          if ($oldContact === null) {
            $this->logError(
              'Broken address-book link for musician "' . $musician->getPublicName(). '"'
              . ', addressbook "' . $musician->getAddressBookUri() . '"'
              . ', uuid "' . $musician->getUuid() . '".',
            );
            unset($this->contactSynchronizations[$musician->getId()]);
            return null;
          }
          $newContact = $this->mergeMusician($oldContact, $musician);
          if ($addressBookUri != $musician->getAddressBookUri()) {
            $addressBook = $this->addressBookByUri($musician->getAddressBookUri());
          } else {
            $addressBook = $oldAddressBook;
          }
          $oldState = $listener->setEnabled(false);
          $addressBook->createOrUpdate($newContact);
          $listener->setEnabled($oldState);
          if ($oldAddressBook !== $addressBook) {
            $oldAddressBook->delete($oldContact['id']);
          }
          unset($this->contactSynchronizations[$musician->getId()]);
          return [ $addressBook, $newContact, $oldAddressBook, $oldContact ];
        },
        function (?array $oldData) use ($musician, $listener) {
          if ($oldData !== null) {
            $oldState = $listener->setEnabled(false);
            list($newAddressBook, $newContact, $oldAddressBook, $oldContact) = $oldData;
            if ($newAddressBook !== $oldAddressBook) {
              $newAddressBook->delete($newContact['id']);
            }
            $oldAddressBook->createOrUpdate($oldContact);
            $listener->setEnabled($oldState);
            $this->contactSynchronizations[$musician->getId()] = $musician;
            // the rollback should restore the addressbook link.
          }
        }
      ),
    );
    return true;
  }

  /**
   * Find an associated addressbook contact if there is one. The search is
   * performed by UID (== $musician->getUuid()).
   *
   * @param Entities\Musician $musician
   *
   * @param null|string $addressBookUri If given use this instead of $musician->getAddressBookUri()
   *
   * @return null|array
   */
  public function findMusicianContact(Entities\Musician $musician, ?string $addressBookUri = null):?array
  {
    $addressBook = $this->addressBookByUri($addressBookUri ?? $musician->getAddressBookUri());
    if ($addressBook === null) {
      return null;
    }
    $result = $addressBook->search(
      pattern: (string)$musician->getUuid(),
      searchProperties: [ 'UID' ],
      options: [
        'types' => true,
        'wildcard' => false,
      ],
    );
    if (count($result) !== 1) {
      $this->logError('Found no or more than one contact ' . print_r($result, true));
      return null;
    }
    return $result[0];
  }
}
