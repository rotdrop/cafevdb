<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use UnexpectedValueException;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use Sabre\VObject;
use Sabre\VObject\Component\VCard;

use Psr\Container\ContainerInterface;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\Image;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumGender;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Service\ContactsService as TestedService;
use OCA\CAFEVDB\Common\Transliterator;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the ContactsService class. */
#[Attributes\CoversClass(TestedService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\PhoneNumberService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class ContactsServiceTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockProjectsRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockInstrumentsRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\MockEntityManagerTrait;

  private TestedService $service;

  private MockProvider $mockProvider;

  private ContainerInterface $appContainer;

  private GeoCodingService $geoCodingService;

  private IAvatarManager $avatarManager;

  private const COUNTRY_NAMES = [
    'en' => [
      'AQ' => 'Antarctica',
      'US' => 'United States',
    ],
    'de' => [
      'AQ' => 'Antarktis',
      'US' => 'USA',
    ],
  ];

  /** {@inheritdoc} */
  public function setup(): void
  {
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->generateProjectParticipant(persist: false);
    $this->getInstrumentsRepositoryMock();
    $this->getProjectsRepositoryMock();
    $this->getEntityManagerMock();
    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->appContainer = $this->mockProvider->getAppContainer();

    $this->geoCodingService = $this->createStub(GeoCodingService::class);
    $this->geoCodingService
      ->method('getCountryISOFromName')
      ->willReturnCallback(
        function(string $l10nCountry): ?string {
          switch ($l10nCountry) {
            case 'Deutschland':
            case 'Germany':
              return 'DE';
            default:
              return null;
          }
        }
      );
    $this->geoCodingService
      ->method('countryNames')
      ->willReturnCallback(fn(string $language) => self::COUNTRY_NAMES[$language] ?? []);
    $this->mockProvider->registerClassInstance(GeoCodingService::class, $this->geoCodingService, global: true);

    $this->avatarManager = $this->createStub(IAvatarManager::class);
    $image = $this->createStub(Image::class);
    $image->method('mimeType')->willReturn('a string');
    $image->method('data')->willReturn('a data string');
    $avatar = $this->createStub(IAvatar::class);
    $avatar->method('get')->willReturn($image);
    $this->avatarManager->method('getAvatar')->willReturn($avatar);

    $this->service = new TestedService(
      avatarManager: $this->avatarManager,
      contactsManager: $this->createStub(IContactsManager::class),
      transliterator: $this->appContainer->get(Transliterator::class),
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      appContainer: $this->appContainer,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testConstruction(): void
  {
  }

  private const CARD_UID = 'a073db1b-fe3f-40aa-ad53-9c82a309351f';

  // from https://gitlab.com/pwithnall/vcard-test-suite
  private const VCARD_DATA = [
    [
      'expectations' => [
        'adr' => [
          ';;2 Enterprise Avenue;Worktown;NY;01111;USA',
          ';;3 Acacia Avenue;Hoemtown;MA;02222;USA',
        ],
        'adrTyped' => [
          ['type' => 'WORK', 'value' => ';;2 Enterprise Avenue;Worktown;NY;01111;USA'],
          ['type' => 'HOME,pref', 'value' => ';;3 Acacia Avenue;Hoemtown;MA;02222;USA'],
        ],
        'status' => EnumParticipationStatus::ASSOCIATED,
        'uuid' => self::CARD_UID,
      ],
      'data' => 'BEGIN:VCARD
VERSION:3.0
N:Doe;John;;;
FN:John Doe
ORG:Example.com Inc.;
UID:' . self::CARD_UID . '
TITLE:Imaginary test person
EMAIL;type=INTERNET;type=WORK;type=pref:johnDoe@example.org
TEL;type=WORK;type=pref:+1 617 555 1212
TEL;type=WORK:+1 (617) 555-1234
TEL;type=CELL:+1 781 555 1212
TEL;type=HOME:+1 202 555 1212
item1.ADR;type=WORK:;;2 Enterprise Avenue;Worktown;NY;01111;USA
item1.X-ABADR:us
item2.ADR;type=HOME;type=pref:;;3 Acacia Avenue;Hoemtown;MA;02222;USA
item2.X-ABADR:us
NOTE:John Doe has a long and varied history\, being documented on more police files that anyone else. Reports of his death are alas numerous.
item3.URL;type=pref:http\://www.example/com/doe
item3.X-ABLabel:_$!<HomePage>!$_
item4.URL:http\://www.example.com/Joe/foaf.df
item4.X-ABLabel:FOAF
item5.X-ABRELATEDNAMES;type=pref:Jane Doe
item5.X-ABLabel:_$!<Friend>!$_
CATEGORIES:Work,Test group
X-ABUID:5AD380FD-B2DE-4261-BA99-DE1D1DB52FBE\:ABPerson
END:VCARD
'
    ],
    [
      'expectations' => [
        'exception' => UnexpectedValueException::class,
      ],
      'data' => 'BEGIN:VCARD
VERSION:3.0
PRODID:-//Sabre//Sabre VObject 4.5.6//EN
UID:6f4999e1-1f05-4842-b7b4-35942c050953
REV;VALUE=DATE-TIME:20260304T091412Z
FN:Testkontakt L10N-DE
NOTE:Das ist eine Notiz.
ADR;TYPE=HOME:;;Finkenweg 3;Nirgend-Neustadt;Altland;12345;BlahLand
EMAIL;TYPE=HOME:testkontakt@l10n-de.tld
TEL;TYPE=HOME,VOICE:+123456789
GENDER:O
CATEGORIES:Violine,Mensch
END:VCARD
',
    ],
  ];

  /** @return void */
  public function testFlattenVCard(): void
  {
    foreach (self::VCARD_DATA as $vCardData) {
      if ($vCardData['expectations']['exception'] ?? null) {
        continue;
      }
      $vCard = VObject\Reader::read($vCardData['data']);

      $result = $this->service->flattenVCard('uri.vcf', $vCard, withTypes: false);
      $this->assertEquals($vCardData['expectations']['adr'], $result['ADR']);

      $result = $this->service->flattenVCard('uri.vcf', $vCard, withTypes: true);
      $this->assertEquals($vCardData['expectations']['adrTyped'], $result['ADR']);
    }
  }

  private const FLAT_CONTACT_DATA = [
    'URI' => 'uri.vcf',
    'UID' => self::CARD_UID,
    'VERSION' => '3.0',
    'N' => 'Doe;John;;;',
    'FN' => 'John Doe',
    'ORG' => 'Example.com Inc.;',
    'TITLE' => 'Imaginary test person',
    'EMAIL' => [
      0 => [
        'type' => 'INTERNET,WORK,pref',
        'value' => 'johnDoe@example.org',
      ],
    ],
    'TEL' => [
      0 => [
        'type' => 'WORK,pref',
        'value' => '+1 617 555 1212',
      ],
      1 => [
        'type' => 'WORK',
        'value' => '+1 (617) 555-1234',
      ],
      2 => [
        'type' => 'CELL',
        'value' => '+1 781 555 1212',
      ],
      3 => [
        'type' => 'HOME',
        'value' => '+1 202 555 1212',
      ],
    ],
    'ADR' => [
      0 => [
        'type' => 'WORK',
        'value' => ';;2 Enterprise Avenue;Worktown;NY;01111;USA',
      ],
      1 => [
        'type' => 'HOME,pref',
        'value' => ';;3 Acacia Avenue;Hoemtown;MA;02222;USA',
      ],
    ],
    'X-ABADR' => 'us',
    'NOTE' => 'John Doe has a long and varied history, being documented on more police files that anyone else. Reports of his death are alas numerous.',
    'URL' => [
      0 => [
        'type' => 'pref',
        'value' => 'http://www.example/com/doe',
      ],
      1 => [
        'type' => null,
        'value' => 'http://www.example.com/Joe/foaf.df',
      ],
    ],
    'X-ABLABEL' => '_$!<Friend>!$_',
    'X-ABRELATEDNAMES' => 'Jane Doe',
    'CATEGORIES' => 'Work,Test group',
    'X-ABUID' => '5AD380FD-B2DE-4261-BA99-DE1D1DB52FBE\\:ABPerson',
  ];

  private const GENDER_MAPPING = [
    'M' => EnumGender::MALE,
    'F' => EnumGender::FEMALE,
    'O' => EnumGender::DIVERSE,
    'N' => null,
    'U' => null,
  ];

  /** @return void */
  public function testImportCardDataNew(): void
  {
    $contactData = self::FLAT_CONTACT_DATA;
    $entity = $this->service->importCardData(
      entity: null,
      cardData: $contactData,
      preferWork: true,
      keepExisting: false, // does not matter here
    );
    $this->assertEquals(
      EnumParticipationStatus::ASSOCIATED,
      $entity->getDefaultParticipationStatus(),
    );
    unset($contactData['ORG']);
    $contactData['CATEGORIES'] = 'Violine,Kontrabass';
    $entity = $this->service->importCardData(
      entity: null,
      cardData: $contactData,
      preferWork: true,
      keepExisting: false, // does not matter here
    );
    $this->assertEquals(
      EnumParticipationStatus::REGULAR,
      $entity->getDefaultParticipationStatus(),
    );
    $this->assertEquals(2, count($entity->getInstruments()));
    foreach (self::GENDER_MAPPING as $key => $gender) {
      $contactData['GENDER'] = $key;
      $entity = $this->service->importCardData(
        entity: null,
        cardData: $contactData,
        preferWork: true,
        keepExisting: false, // does not matter here
      );
      $this->assertEquals($gender, $entity->getGender());
    }
  }

  /** @return void */
  public function testImportCardDataMergeInstruments(): void
  {
    $contactData = self::FLAT_CONTACT_DATA;
    $contactData['CATEGORIES'] = 'Violine,Kontrabass';
    $instruments = $this->entityManager->getRepository(Entities\Instrument::class)->findBy(['name' => 'Viola']);
    $this->assertEquals(1, count($instruments));
    $this->musician->addInstrument($instruments[0]);

    $entity = $this->service->importCardData(
      entity: $this->musician,
      cardData: $contactData,
      preferWork: true,
      keepExisting: false, // does not matter here
    );
    $this->assertEquals(2, count($entity->getInstruments()));
  }

  /** @return void */
  public function testImportVCard(): void
  {
    foreach (self::VCARD_DATA as $vCardData) {
      try {
        $entity = $this->service->importVCard(
          entity: null,
          vCard: VObject\Reader::read($vCardData['data']),
          preferWork: true,
          keepExisting: false, // does not matter here
        );
      } catch (Throwable $t) {
        if ($vCardData['expectations']['exception'] ?? null) {
          $this->assertInstanceOf($vCardData['expectations']['exception'], $t);
          continue;
        } else {
          throw $t;
        }
      }

      $this->assertEquals(
        $vCardData['expectations']['status'],
        $entity->getDefaultParticipationStatus(),
      );

      $this->assertEquals(
        $vCardData['expectations']['uuid'],
        (string)$entity->getUuid(),
      );
    }
  }

  /** @return void */
  public function testExport(): void
  {
    $vCard = $this->service->export($this->musician);
    $this->assertInstanceOf(VCard::class, $vCard);
    /** @var VCard $vCard */
    $this->assertEqualsCanonicalizing([$this->mockProvider->appName, $this->project->getName()], $vCard->CATEGORIES->getParts());

    $appL10N = $this->appContainer->get(\OCA\CAFEVDB\Service\Registration::APP_L10N);
    $this->participant->setParticipationStatus(EnumParticipationStatus::ASSOCIATED);
    $expectedCategories = [
      $this->mockProvider->appName,
      $this->project->getName() . $appL10N->t(TestedService::ASSOCIATES_SUFFIX),
    ];

    $vCard = $this->service->export($this->musician);

    $this->assertInstanceOf(VCard::class, $vCard);
    $this->assertEqualsCanonicalizing($expectedCategories, $vCard->CATEGORIES->getParts());
    // $flatVCard = $this->service->flattenVCard(cardUri: null, vCard: $vCard);
    // print_r($flatVCard);

    // test L10N country name
    $addressParts = $vCard->ADR->getParts();
    $appLanguage = $this->appContainer->get(Service\Registration::APP_LANGUAGE);
    $this->assertEquals(self::COUNTRY_NAMES[$appLanguage][$this->musician->getCountry()], $addressParts[6]);
  }

  /** @return void */
  public function testMergeMusician(): void
  {
    $projectName = $this->project->getName();
    $contactData = self::FLAT_CONTACT_DATA;
    $contactData['CATEGORIES'] = implode(',', [
      'ACategoryThatShouldBeKept',
      'Violine',
      'Kontrabass',
      $projectName,
      $projectName . 'AnyString',
    ]);
    $this->musician->setDeleted(null);
    $this->participant->setParticipationStatus(EnumParticipationStatus::ASSOCIATED);
    $instruments = $this->entityManager->getRepository(Entities\Instrument::class)->findBy(['name' => 'Viola']);
    $this->assertEquals(1, count($instruments));
    $this->musician->addInstrument($instruments[0]);

    $result = $this->service->mergeMusician($contactData, $this->musician);

    $appL10N = $this->appContainer->get(\OCA\CAFEVDB\Service\Registration::APP_L10N);
    $expectedCategories = [
      'ACategoryThatShouldBeKept',
      'Viola',
      $projectName . $appL10N->t(TestedService::ASSOCIATES_SUFFIX),
      $this->mockProvider->appName,
    ];
    $this->assertEqualsCanonicalizing($expectedCategories, explode(',', $result['CATEGORIES']));
  }
}
