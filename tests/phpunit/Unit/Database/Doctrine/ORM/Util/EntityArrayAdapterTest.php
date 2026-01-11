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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Util;

use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityArrayAdapter;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test aspects of the EntityArrayAdapter */
#[Attributes\CoversClass(EntityArrayAdapter::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\UuidType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDataOption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldDatum::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectPayment::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBulkTransaction::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReference::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReferenceCollection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailPersistanceListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\SanitizerRegistration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\AbstractSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\GoogleMailSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class EntityArrayAdapterTest extends TestCase
{
  use EntityGeneratorTrait;

  private EntityArrayAdapter $entityArrayAdapter;

  private Util\EntitySerializer $entitySerializer;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2024-01-01 00:00:00');
    $this->generateProjectParticipant(persist: false, now: $now);

    $this->entityManager = $mockProvider->getEntityManager();

    $this->generateSepaBankTransfer();

    $this->entityManager->persist($this->musician);

    $this->entitySerializer = new Util\EntitySerializer(
      entityManager: $this->entityManager,
      l: $mockProvider->getL10N(),
      logger: $mockProvider->getLoggerInterface(),
    );

    $this->entityArrayAdapter = EntityArrayAdapter::create(
      entity: $this->participant,
      depth: 1,
      entitySerializer: $this->entitySerializer,
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    $this->entityManager->detach($this->musician);
    foreach ($this->musician->getEmailAddresses() as $emailAddress) {
      $this->entityManager->detach($emailAddress);
    }
  }

  /** @return void */
  public function testConstrution(): void
  {
  }

  /** @return void */
  public function testArrayAccess(): void
  {
    $e = null;
    try {
      $this->entityArrayAdapter['registration'] = false;
    } catch (BadMethodCallException $e) {
      // ignore
    }
    $this->assertInstanceOf(BadMethodCallException::class, $e);

    $e = null;
    try {
      unset($this->entityArrayAdapter['registration']);
    } catch (BadMethodCallException $e) {
      // ignore
    }
    $this->assertInstanceOf(BadMethodCallException::class, $e);

    $this->assertEquals(true, isset($this->entityArrayAdapter['registration']));
    $this->assertEquals(false, isset($this->entityArrayAdapter['I_DO_NOT_EXIST']));
    $e = null;
    try {
      $this->entityArrayAdapter['I_DO_NOT_EXIST'];
    } catch (BadMethodCallException $e) {
      // ignore
    }
    $this->assertInstanceOf(BadMethodCallException::class, $e);
  }

  private const NON_NULL_PROPERTIES = [
    'registration' => true,
    'participationStatus' => true,
    'created' => true,
    'updated' => true,
    'deleted' => false,
    'project' => true,
    'musician' => true,
    'participantFieldsData' => true,
    'projectInstruments' => true,
    'payments' => true,
    'invoices' => true,
    'databaseDocuments' => false,
  ];

  /** @return void */
  public function testIterator(): void
  {
    $testResult = [];
    foreach ($this->entityArrayAdapter as $key => $value) {
      $testResult[$key] = $value !== null;
    }
    $this->assertEquals(self::NON_NULL_PROPERTIES, $testResult);

    $testResult = [];
    foreach ($this->entityArrayAdapter as $key => $value) {
      $testResult[$key] = $value !== null;
    }
    $this->assertEquals(self::NON_NULL_PROPERTIES, $testResult);

    $this->assertEquals(false, $this->entityArrayAdapter->current());
  }

  /** @return void */
  public function testParanoyaCtor(): void
  {
    $ctor = new ReflectionMethod($this->entityArrayAdapter, '__construct');
    $e = null;
    try {
      $ctor->invokeArgs($this->entityArrayAdapter, [
        'entity' => $this->projectParticipant,
        'root' => $this->entityArrayAdapter,
      ]);
    } catch (InvalidArgumentException $e) {
      // empty
    }
    $this->assertInstanceOf(InvalidArgumentException::class, $e);

    $e = null;
    try {
      $ctor->invokeArgs($this->entityArrayAdapter, [
        'entity' => 'blah',
        'root' => $this->entityArrayAdapter,
        'flatIdentifier' => null,
      ]);
    } catch (InvalidArgumentException $e) {
      // empty
    }
    $this->assertInstanceOf(InvalidArgumentException::class, $e);
  }

  private const SERIALIZED_ENTITY = [
    0 => '{
    "__DEPTH__": 0,
    "created": "2024-01-01T00:00:00.000000Z",
    "databaseDocuments": null,
    "deleted": null,
    "invoices": [],
    "musician": {
        "flatIdentifier": "1",
        "entityClassName": "Musician"
    },
    "participantFieldsData": [],
    "participationStatus": "regular",
    "payments": {
        "entityClassName": "CompositePayment",
        "entities": [
            {
                "flatIdentifier": "1",
                "entityClassName": null
            }
        ]
    },
    "project": {
        "flatIdentifier": "1",
        "entityClassName": "Project"
    },
    "projectInstruments": [],
    "registration": false,
    "updated": "2024-01-01T00:00:00.000000Z"
}',
    1 => '{
    "__DEPTH__": 1,
    "created": "2024-01-01T00:00:00.000000Z",
    "databaseDocuments": null,
    "deleted": null,
    "invoices": [],
    "musician": {
        "__DEPTH__": 0,
        "addressBookUri": null,
        "addressSupplement": "Igloo 13",
        "birthday": "2024-01-01T00:00:00.000000Z",
        "city": "Nirgends",
        "cloudAccountDeactivated": null,
        "cloudAccountDisabled": true,
        "country": "AQ",
        "created": "2024-01-01T00:00:00.000000Z",
        "defaultParticipationStatus": "regular",
        "deleted": "2024-01-01T00:00:00.000000Z",
        "displayName": "Musterperson, Max",
        "email": "john.doe@nowhere.tld",
        "emailAddresses": {
            "entityClassName": "MusicianEmailAddress",
            "entities": {
                "john.doe@nowhere.tld": {
                    "flatIdentifier": "john.doe@nowhere.tld:1",
                    "entityClassName": null
                }
            }
        },
        "encryptedFiles": [],
        "firstName": "Max",
        "fixedLinePhone": "4711",
        "gender": "male",
        "genderAssumed": true,
        "id": 1,
        "instrumentInsurances": [],
        "instruments": [],
        "invoices": [],
        "jobTitle": null,
        "labelledPOBox": "",
        "language": null,
        "mobile": "0815",
        "mobilePhone": "0815",
        "name": "Max Musterperson",
        "nickName": null,
        "numberAndStreet": "42 Unauffindbarweg",
        "organization": null,
        "originatedInvoices": [],
        "payableInsurances": [],
        "payments": {
            "entityClassName": "CompositePayment",
            "entities": [
                {
                    "flatIdentifier": "1",
                    "entityClassName": null
                }
            ]
        },
        "personalPublicName": "Max Musterperson",
        "phone": "4711",
        "poBox": null,
        "postalCode": "Z-7",
        "projectApplications": [],
        "projectInstruments": [],
        "projectParticipantFieldsData": {
            "entityClassName": "ProjectParticipantFieldDatum",
            "entities": {
                "2b826186-ef29-11f0-a81f-27218343fe72": {
                    "flatIdentifier": "0:1:1:2b826186-ef29-11f0-a81f-27218343fe72",
                    "entityClassName": null
                }
            }
        },
        "projectParticipation": {
            "entityClassName": "ProjectParticipant",
            "entities": {
                "1": {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                }
            }
        },
        "publicName": "Musterperson, Max",
        "remarks": null,
        "rowAccessToken": null,
        "sepaBankAccounts": {
            "entityClassName": "SepaBankAccount",
            "entities": [
                {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                },
                {
                    "flatIdentifier": "1:2",
                    "entityClassName": null
                }
            ]
        },
        "sepaDebitMandates": [],
        "street": "Unauffindbarweg",
        "streetAndNumber": "Unauffindbarweg 42",
        "streetNumber": "42",
        "surName": "Musterperson",
        "updated": "2024-01-01T00:00:00.000000Z",
        "userIdSlug": null,
        "userPassphrase": null,
        "uuid": "00000000-0000-0000-0000-000000000000"
    },
    "participantFieldsData": [],
    "participationStatus": "regular",
    "payments": [
        {
            "__DEPTH__": 0,
            "amount": {},
            "balanceDocumentsFolder": null,
            "created": null,
            "dateOfReceipt": null,
            "donationReceipt": null,
            "id": 1,
            "musician": {
                "flatIdentifier": "1",
                "entityClassName": "Musician"
            },
            "notificationMessageId": null,
            "preNotificationEmail": null,
            "project": {
                "flatIdentifier": "1",
                "entityClassName": "Project"
            },
            "projectParticipant": {
                "flatIdentifier": "1:1",
                "entityClassName": "ProjectParticipant"
            },
            "projectPayments": {
                "entityClassName": "ProjectPayment",
                "entities": [
                    {
                        "flatIdentifier": "1",
                        "entityClassName": null
                    }
                ]
            },
            "sepaBankAccount": {
                "flatIdentifier": "1:2",
                "entityClassName": "SepaBankAccount"
            },
            "sepaDebitMandate": null,
            "sepaTransaction": {
                "flatIdentifier": "1",
                "entityClassName": "SepaBankTransfer"
            },
            "subject": "TestProject \/ Forderungen: ReNr RE25\/01354 Aktenzeichen 25-01258 \u00dcml\u00e4\u00fcte\u00df",
            "supportingDocument": null,
            "updated": null
        }
    ],
    "project": {
        "__DEPTH__": 0,
        "applications": [],
        "calendarEvents": [],
        "compositePayments": [],
        "created": null,
        "deleted": null,
        "financialBalanceDocumentsStorage": null,
        "id": 1,
        "instrumentationNumbers": [],
        "invoices": [],
        "mailingListId": null,
        "name": "TestProject2099",
        "participantFields": [],
        "participantFieldsData": [],
        "participantInstruments": [],
        "participants": {
            "entityClassName": "ProjectParticipant",
            "entities": {
                "1": {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                }
            }
        },
        "payments": [],
        "registrationDeadline": null,
        "registrationStartDate": null,
        "sentEmail": [],
        "sepaDebitMandates": [],
        "type": "temporary",
        "updated": null,
        "webPages": [],
        "year": 2099
    },
    "projectInstruments": [],
    "registration": false,
    "updated": "2024-01-01T00:00:00.000000Z"
}',
    2 => '{
    "__DEPTH__": 2,
    "created": "2024-01-01T00:00:00.000000Z",
    "databaseDocuments": null,
    "deleted": null,
    "invoices": [],
    "musician": {
        "__DEPTH__": 1,
        "addressBookUri": null,
        "addressSupplement": "Igloo 13",
        "birthday": "2024-01-01T00:00:00.000000Z",
        "city": "Nirgends",
        "cloudAccountDeactivated": null,
        "cloudAccountDisabled": true,
        "country": "AQ",
        "created": "2024-01-01T00:00:00.000000Z",
        "defaultParticipationStatus": "regular",
        "deleted": "2024-01-01T00:00:00.000000Z",
        "displayName": "Musterperson, Max",
        "email": "john.doe@nowhere.tld",
        "emailAddresses": {
            "john.doe@nowhere.tld": {
                "__DEPTH__": 0,
                "address": "john.doe@nowhere.tld",
                "created": "2024-01-01T00:00:00.000000Z",
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "updated": "2024-01-01T00:00:00.000000Z"
            }
        },
        "encryptedFiles": [],
        "firstName": "Max",
        "fixedLinePhone": "4711",
        "gender": "male",
        "genderAssumed": true,
        "id": 1,
        "instrumentInsurances": [],
        "instruments": [],
        "invoices": [],
        "jobTitle": null,
        "labelledPOBox": "",
        "language": null,
        "mobile": "0815",
        "mobilePhone": "0815",
        "name": "Max Musterperson",
        "nickName": null,
        "numberAndStreet": "42 Unauffindbarweg",
        "organization": null,
        "originatedInvoices": [],
        "payableInsurances": [],
        "payments": [
            {
                "__DEPTH__": 0,
                "amount": {},
                "balanceDocumentsFolder": null,
                "created": null,
                "dateOfReceipt": null,
                "donationReceipt": null,
                "id": 1,
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "notificationMessageId": null,
                "preNotificationEmail": null,
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "projectParticipant": {
                    "flatIdentifier": "1:1",
                    "entityClassName": "ProjectParticipant"
                },
                "projectPayments": {
                    "entityClassName": "ProjectPayment",
                    "entities": [
                        {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    ]
                },
                "sepaBankAccount": {
                    "flatIdentifier": "1:2",
                    "entityClassName": "SepaBankAccount"
                },
                "sepaDebitMandate": null,
                "sepaTransaction": {
                    "flatIdentifier": "1",
                    "entityClassName": "SepaBankTransfer"
                },
                "subject": "TestProject \/ Forderungen: ReNr RE25\/01354 Aktenzeichen 25-01258 \u00dcml\u00e4\u00fcte\u00df",
                "supportingDocument": null,
                "updated": null
            }
        ],
        "personalPublicName": "Max Musterperson",
        "phone": "4711",
        "poBox": null,
        "postalCode": "Z-7",
        "projectApplications": [],
        "projectInstruments": [],
        "projectParticipantFieldsData": {
            "2b826186-ef29-11f0-a81f-27218343fe72": {
                "__DEPTH__": 0,
                "created": null,
                "dataOption": {
                    "flatIdentifier": "0:2b826186-ef29-11f0-a81f-27218343fe72",
                    "entityClassName": "ProjectParticipantFieldDataOption"
                },
                "deleted": null,
                "deposit": null,
                "field": {
                    "flatIdentifier": "0",
                    "entityClassName": "ProjectParticipantField"
                },
                "invoiceItems": [],
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "optionKey": "2b826186-ef29-11f0-a81f-27218343fe72",
                "optionValue": "12.23",
                "payments": [],
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "projectParticipant": {
                    "flatIdentifier": "1:1",
                    "entityClassName": "ProjectParticipant"
                },
                "supportingDocument": null,
                "updated": null
            }
        },
        "projectParticipation": {
            "1": {
                "__DEPTH__": 0,
                "created": "2024-01-01T00:00:00.000000Z",
                "databaseDocuments": null,
                "deleted": null,
                "invoices": [],
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "participantFieldsData": [],
                "participationStatus": "regular",
                "payments": {
                    "entityClassName": "CompositePayment",
                    "entities": [
                        {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    ]
                },
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "projectInstruments": [],
                "registration": false,
                "updated": "2024-01-01T00:00:00.000000Z"
            }
        },
        "publicName": "Musterperson, Max",
        "remarks": null,
        "rowAccessToken": null,
        "sepaBankAccounts": [
            {
                "__DEPTH__": 0,
                "bankAccountOwner": "Musterperson, Max",
                "bic": "PBNKDEFFXXX",
                "blz": "70010080",
                "created": null,
                "deleted": "2024-01-01T00:00:00.000000Z",
                "iban": "DE02700100800030876808",
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "payments": [],
                "sepaDebitMandates": [],
                "sequence": 1,
                "updated": null
            },
            {
                "__DEPTH__": 0,
                "bankAccountOwner": "Inhaber*in, Konto",
                "bic": "BYLADEM1001",
                "blz": "12030000",
                "created": null,
                "deleted": null,
                "iban": "DE02120300000000202051",
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "payments": [],
                "sepaDebitMandates": [],
                "sequence": 2,
                "updated": null
            }
        ],
        "sepaDebitMandates": [],
        "street": "Unauffindbarweg",
        "streetAndNumber": "Unauffindbarweg 42",
        "streetNumber": "42",
        "surName": "Musterperson",
        "updated": "2024-01-01T00:00:00.000000Z",
        "userIdSlug": null,
        "userPassphrase": null,
        "uuid": "00000000-0000-0000-0000-000000000000"
    },
    "participantFieldsData": [],
    "participationStatus": "regular",
    "payments": [
        {
            "__DEPTH__": 1,
            "amount": {},
            "balanceDocumentsFolder": null,
            "created": null,
            "dateOfReceipt": null,
            "donationReceipt": null,
            "id": 1,
            "musician": {
                "__DEPTH__": 0,
                "addressBookUri": null,
                "addressSupplement": "Igloo 13",
                "birthday": "2024-01-01T00:00:00.000000Z",
                "city": "Nirgends",
                "cloudAccountDeactivated": null,
                "cloudAccountDisabled": true,
                "country": "AQ",
                "created": "2024-01-01T00:00:00.000000Z",
                "defaultParticipationStatus": "regular",
                "deleted": "2024-01-01T00:00:00.000000Z",
                "displayName": "Musterperson, Max",
                "email": "john.doe@nowhere.tld",
                "emailAddresses": {
                    "entityClassName": "MusicianEmailAddress",
                    "entities": {
                        "john.doe@nowhere.tld": {
                            "flatIdentifier": "john.doe@nowhere.tld:1",
                            "entityClassName": null
                        }
                    }
                },
                "encryptedFiles": [],
                "firstName": "Max",
                "fixedLinePhone": "4711",
                "gender": "male",
                "genderAssumed": true,
                "id": 1,
                "instrumentInsurances": [],
                "instruments": [],
                "invoices": [],
                "jobTitle": null,
                "labelledPOBox": "",
                "language": null,
                "mobile": "0815",
                "mobilePhone": "0815",
                "name": "Max Musterperson",
                "nickName": null,
                "numberAndStreet": "42 Unauffindbarweg",
                "organization": null,
                "originatedInvoices": [],
                "payableInsurances": [],
                "payments": {
                    "entityClassName": "CompositePayment",
                    "entities": [
                        {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    ]
                },
                "personalPublicName": "Max Musterperson",
                "phone": "4711",
                "poBox": null,
                "postalCode": "Z-7",
                "projectApplications": [],
                "projectInstruments": [],
                "projectParticipantFieldsData": {
                    "entityClassName": "ProjectParticipantFieldDatum",
                    "entities": {
                        "2b826186-ef29-11f0-a81f-27218343fe72": {
                            "flatIdentifier": "0:1:1:2b826186-ef29-11f0-a81f-27218343fe72",
                            "entityClassName": null
                        }
                    }
                },
                "projectParticipation": {
                    "entityClassName": "ProjectParticipant",
                    "entities": {
                        "1": {
                            "flatIdentifier": "1:1",
                            "entityClassName": null
                        }
                    }
                },
                "publicName": "Musterperson, Max",
                "remarks": null,
                "rowAccessToken": null,
                "sepaBankAccounts": {
                    "entityClassName": "SepaBankAccount",
                    "entities": [
                        {
                            "flatIdentifier": "1:1",
                            "entityClassName": null
                        },
                        {
                            "flatIdentifier": "1:2",
                            "entityClassName": null
                        }
                    ]
                },
                "sepaDebitMandates": [],
                "street": "Unauffindbarweg",
                "streetAndNumber": "Unauffindbarweg 42",
                "streetNumber": "42",
                "surName": "Musterperson",
                "updated": "2024-01-01T00:00:00.000000Z",
                "userIdSlug": null,
                "userPassphrase": null,
                "uuid": "00000000-0000-0000-0000-000000000000"
            },
            "notificationMessageId": null,
            "preNotificationEmail": null,
            "project": {
                "__DEPTH__": 0,
                "applications": [],
                "calendarEvents": [],
                "compositePayments": [],
                "created": null,
                "deleted": null,
                "financialBalanceDocumentsStorage": null,
                "id": 1,
                "instrumentationNumbers": [],
                "invoices": [],
                "mailingListId": null,
                "name": "TestProject2099",
                "participantFields": [],
                "participantFieldsData": [],
                "participantInstruments": [],
                "participants": {
                    "entityClassName": "ProjectParticipant",
                    "entities": {
                        "1": {
                            "flatIdentifier": "1:1",
                            "entityClassName": null
                        }
                    }
                },
                "payments": [],
                "registrationDeadline": null,
                "registrationStartDate": null,
                "sentEmail": [],
                "sepaDebitMandates": [],
                "type": "temporary",
                "updated": null,
                "webPages": [],
                "year": 2099
            },
            "projectParticipant": {
                "__DEPTH__": 0,
                "created": "2024-01-01T00:00:00.000000Z",
                "databaseDocuments": null,
                "deleted": null,
                "invoices": [],
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "participantFieldsData": [],
                "participationStatus": "regular",
                "payments": {
                    "entityClassName": "CompositePayment",
                    "entities": [
                        {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    ]
                },
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "projectInstruments": [],
                "registration": false,
                "updated": "2024-01-01T00:00:00.000000Z"
            },
            "projectPayments": [
                {
                    "__DEPTH__": 0,
                    "amount": {},
                    "balanceDocumentsFolder": null,
                    "compositePayment": {
                        "flatIdentifier": "1",
                        "entityClassName": "CompositePayment"
                    },
                    "created": null,
                    "id": 1,
                    "isDonation": false,
                    "musician": {
                        "flatIdentifier": "1",
                        "entityClassName": "Musician"
                    },
                    "project": {
                        "flatIdentifier": "1",
                        "entityClassName": "Project"
                    },
                    "projectParticipant": {
                        "flatIdentifier": "1:1",
                        "entityClassName": "ProjectParticipant"
                    },
                    "receivable": {
                        "flatIdentifier": "0:1:1:2b826186-ef29-11f0-a81f-27218343fe72",
                        "entityClassName": "ProjectParticipantFieldDatum"
                    },
                    "receivableOption": {
                        "flatIdentifier": "0:2b826186-ef29-11f0-a81f-27218343fe72",
                        "entityClassName": "ProjectParticipantFieldDataOption"
                    },
                    "subject": "Forderungen: ReNr RE25\/01354 Aktenzeichen 25-01258 \u00dcml\u00e4\u00fcte\u00df",
                    "updated": null
                }
            ],
            "sepaBankAccount": {
                "__DEPTH__": 0,
                "bankAccountOwner": "Inhaber*in, Konto",
                "bic": "BYLADEM1001",
                "blz": "12030000",
                "created": null,
                "deleted": null,
                "iban": "DE02120300000000202051",
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "payments": [],
                "sepaDebitMandates": [],
                "sequence": 2,
                "updated": null
            },
            "sepaDebitMandate": null,
            "sepaTransaction": {
                "__DEPTH__": 0,
                "balancingItemsData": [],
                "created": null,
                "dueDate": "2099-01-01T00:00:00.000000Z",
                "dueEventUid": null,
                "dueEventUri": null,
                "id": 1,
                "payments": {
                    "entityClassName": "CompositePayment",
                    "entities": {
                        "1": {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    }
                },
                "preNotificationEmails": [],
                "sepaTransactionData": [],
                "submissionDeadline": null,
                "submissionEventUid": null,
                "submissionEventUri": null,
                "submissionTaskUid": null,
                "submissionTaskUri": null,
                "submitDate": null,
                "updated": null
            },
            "subject": "TestProject \/ Forderungen: ReNr RE25\/01354 Aktenzeichen 25-01258 \u00dcml\u00e4\u00fcte\u00df",
            "supportingDocument": null,
            "updated": null
        }
    ],
    "project": {
        "__DEPTH__": 1,
        "applications": [],
        "calendarEvents": [],
        "compositePayments": [],
        "created": null,
        "deleted": null,
        "financialBalanceDocumentsStorage": null,
        "id": 1,
        "instrumentationNumbers": [],
        "invoices": [],
        "mailingListId": null,
        "name": "TestProject2099",
        "participantFields": [],
        "participantFieldsData": [],
        "participantInstruments": [],
        "participants": {
            "1": {
                "__DEPTH__": 0,
                "created": "2024-01-01T00:00:00.000000Z",
                "databaseDocuments": null,
                "deleted": null,
                "invoices": [],
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "participantFieldsData": [],
                "participationStatus": "regular",
                "payments": {
                    "entityClassName": "CompositePayment",
                    "entities": [
                        {
                            "flatIdentifier": "1",
                            "entityClassName": null
                        }
                    ]
                },
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "projectInstruments": [],
                "registration": false,
                "updated": "2024-01-01T00:00:00.000000Z"
            }
        },
        "payments": [],
        "registrationDeadline": null,
        "registrationStartDate": null,
        "sentEmail": [],
        "sepaDebitMandates": [],
        "type": "temporary",
        "updated": null,
        "webPages": [],
        "year": 2099
    },
    "projectInstruments": [],
    "registration": false,
    "updated": "2024-01-01T00:00:00.000000Z"
}',
  ];

  /** @return void */
  public function testSerialization(): void
  {
    // tests also "deepen"
    foreach (self::SERIALIZED_ENTITY as $depth => $data) {
      $this->entityArrayAdapter->setDepth($depth);
      $this->assertEquals($depth, $this->entityArrayAdapter->getDepth());
      // echo json_encode($this->entityArrayAdapter, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . PHP_EOL;
      $this->assertEquals(
        self::SERIALIZED_ENTITY[$this->entityArrayAdapter->getDepth()],
        json_encode($this->entityArrayAdapter, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),
      );
    }
    // tests also "un-deepen"
    foreach (array_reverse(self::SERIALIZED_ENTITY, preserve_keys: true) as $depth => $data) {
      $this->entityArrayAdapter->setDepth($depth);
      $this->assertEquals($depth, $this->entityArrayAdapter->getDepth());
      $this->assertEquals(
        self::SERIALIZED_ENTITY[$this->entityArrayAdapter->getDepth()],
        json_encode($this->entityArrayAdapter, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),
      );
    }
  }
}
