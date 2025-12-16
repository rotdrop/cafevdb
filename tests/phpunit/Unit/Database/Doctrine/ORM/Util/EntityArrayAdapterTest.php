<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

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
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractEnumType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
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
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\LoggerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class EntityArrayAdapterTest extends TestCase
{
  use EntityGeneratorTrait {
    EntityGeneratorTrait::setup as entitySetup;
  }

  private EntityArrayAdapter $entityArrayAdapter;

  private Util\EntitySerializer $entitySerializer;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $appContainer = $mockProvider->getAppContainer();

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2024-01-01 00:00:00');
    $this->entitySetup(persist: false, now: $now);

    $this->entityManager = $mockProvider->getEntityManager();

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
    }
    $this->assertInstanceOf(InvalidArgumentException::class, $e);
  }

  private const SERIALIZED_ENTITY = [
    1 => '{
    "__DEPTH__": 1,
    "registration": false,
    "participationStatus": "regular",
    "created": "2024-01-01T00:00:00.000000Z",
    "updated": "2024-01-01T00:00:00.000000Z",
    "deleted": null,
    "project": {
        "__DEPTH__": 0,
        "id": 1,
        "year": 2099,
        "name": "TestProject2099",
        "type": "temporary",
        "mailingListId": null,
        "registrationStartDate": null,
        "registrationDeadline": null,
        "created": null,
        "updated": null,
        "deleted": null,
        "instrumentationNumbers": [],
        "webPages": [],
        "participantFields": [],
        "participantFieldsData": [],
        "participants": {
            "entityClassName": "ProjectParticipant",
            "entities": {
                "1": {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                }
            }
        },
        "applications": [],
        "sepaDebitMandates": [],
        "compositePayments": [],
        "payments": [],
        "invoices": [],
        "participantInstruments": [],
        "calendarEvents": [],
        "sentEmail": [],
        "financialBalanceDocumentsStorage": null
    },
    "musician": {
        "__DEPTH__": 0,
        "id": 1,
        "surName": "Musterperson",
        "firstName": "Max",
        "nickName": null,
        "displayName": null,
        "gender": null,
        "userIdSlug": null,
        "userPassphrase": null,
        "city": "Nirgends",
        "street": "Unauffindbarweg",
        "streetNumber": "42",
        "addressSupplement": "Igloo 13",
        "poBox": null,
        "country": "AQ",
        "postalCode": "Z-7",
        "language": null,
        "mobilePhone": "0815",
        "fixedLinePhone": "4711",
        "birthday": "2024-01-01T00:00:00.000000Z",
        "email": "john.doe@nowhere.tld",
        "defaultParticipationStatus": "regular",
        "remarks": null,
        "cloudAccountDeactivated": null,
        "cloudAccountDisabled": true,
        "updated": "2024-01-01T00:00:00.000000Z",
        "addressBookUri": null,
        "organization": null,
        "jobTitle": null,
        "uuid": "00000000-0000-0000-0000-000000000000",
        "created": "2024-01-01T00:00:00.000000Z",
        "deleted": "2024-01-01T00:00:00.000000Z",
        "rowAccessToken": null,
        "emailAddresses": {
            "entityClassName": "MusicianEmailAddress",
            "entities": {
                "john.doe@nowhere.tld": {
                    "flatIdentifier": "john.doe@nowhere.tld:1",
                    "entityClassName": null
                }
            }
        },
        "instruments": [],
        "projectApplications": [],
        "projectParticipation": {
            "entityClassName": "ProjectParticipant",
            "entities": {
                "1": {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                }
            }
        },
        "projectInstruments": [],
        "projectParticipantFieldsData": [],
        "instrumentInsurances": [],
        "payableInsurances": [],
        "sepaBankAccounts": {
            "entityClassName": "SepaBankAccount",
            "entities": [
                {
                    "flatIdentifier": "1:1",
                    "entityClassName": null
                }
            ]
        },
        "sepaDebitMandates": [],
        "payments": [],
        "encryptedFiles": [],
        "invoices": [],
        "originatedInvoices": []
    },
    "participantFieldsData": [],
    "projectInstruments": [],
    "payments": [],
    "invoices": [],
    "databaseDocuments": null
}',
    2 => '{
    "__DEPTH__": 2,
    "registration": false,
    "participationStatus": "regular",
    "created": "2024-01-01T00:00:00.000000Z",
    "updated": "2024-01-01T00:00:00.000000Z",
    "deleted": null,
    "project": {
        "__DEPTH__": 1,
        "id": 1,
        "year": 2099,
        "name": "TestProject2099",
        "type": "temporary",
        "mailingListId": null,
        "registrationStartDate": null,
        "registrationDeadline": null,
        "created": null,
        "updated": null,
        "deleted": null,
        "instrumentationNumbers": [],
        "webPages": [],
        "participantFields": [],
        "participantFieldsData": [],
        "participants": {
            "1": {
                "__DEPTH__": 0,
                "registration": false,
                "participationStatus": "regular",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "deleted": null,
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "participantFieldsData": [],
                "projectInstruments": [],
                "payments": [],
                "invoices": [],
                "databaseDocuments": null
            }
        },
        "applications": [],
        "sepaDebitMandates": [],
        "compositePayments": [],
        "payments": [],
        "invoices": [],
        "participantInstruments": [],
        "calendarEvents": [],
        "sentEmail": [],
        "financialBalanceDocumentsStorage": null
    },
    "musician": {
        "__DEPTH__": 1,
        "id": 1,
        "surName": "Musterperson",
        "firstName": "Max",
        "nickName": null,
        "displayName": null,
        "gender": null,
        "userIdSlug": null,
        "userPassphrase": null,
        "city": "Nirgends",
        "street": "Unauffindbarweg",
        "streetNumber": "42",
        "addressSupplement": "Igloo 13",
        "poBox": null,
        "country": "AQ",
        "postalCode": "Z-7",
        "language": null,
        "mobilePhone": "0815",
        "fixedLinePhone": "4711",
        "birthday": "2024-01-01T00:00:00.000000Z",
        "email": "john.doe@nowhere.tld",
        "defaultParticipationStatus": "regular",
        "remarks": null,
        "cloudAccountDeactivated": null,
        "cloudAccountDisabled": true,
        "updated": "2024-01-01T00:00:00.000000Z",
        "addressBookUri": null,
        "organization": null,
        "jobTitle": null,
        "uuid": "00000000-0000-0000-0000-000000000000",
        "created": "2024-01-01T00:00:00.000000Z",
        "deleted": "2024-01-01T00:00:00.000000Z",
        "rowAccessToken": null,
        "emailAddresses": {
            "john.doe@nowhere.tld": {
                "__DEPTH__": 0,
                "address": "john.doe@nowhere.tld",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                }
            }
        },
        "instruments": [],
        "projectApplications": [],
        "projectParticipation": {
            "1": {
                "__DEPTH__": 0,
                "registration": false,
                "participationStatus": "regular",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "deleted": null,
                "project": {
                    "flatIdentifier": "1",
                    "entityClassName": "Project"
                },
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "participantFieldsData": [],
                "projectInstruments": [],
                "payments": [],
                "invoices": [],
                "databaseDocuments": null
            }
        },
        "projectInstruments": [],
        "projectParticipantFieldsData": [],
        "instrumentInsurances": [],
        "payableInsurances": [],
        "sepaBankAccounts": [
            {
                "__DEPTH__": 0,
                "sequence": 1,
                "iban": "DE02700100800030876808",
                "bic": "PBNKDEFFXXX",
                "blz": "70010080",
                "bankAccountOwner": "Musterperson, Max",
                "deleted": "2024-01-01T00:00:00.000000Z",
                "created": null,
                "updated": null,
                "musician": {
                    "flatIdentifier": "1",
                    "entityClassName": "Musician"
                },
                "sepaDebitMandates": [],
                "payments": []
            }
        ],
        "sepaDebitMandates": [],
        "payments": [],
        "encryptedFiles": [],
        "invoices": [],
        "originatedInvoices": []
    },
    "participantFieldsData": [],
    "projectInstruments": [],
    "payments": [],
    "invoices": [],
    "databaseDocuments": null
}',
    3 => '{
    "__DEPTH__": 3,
    "registration": false,
    "participationStatus": "regular",
    "created": "2024-01-01T00:00:00.000000Z",
    "updated": "2024-01-01T00:00:00.000000Z",
    "deleted": null,
    "project": {
        "__DEPTH__": 2,
        "id": 1,
        "year": 2099,
        "name": "TestProject2099",
        "type": "temporary",
        "mailingListId": null,
        "registrationStartDate": null,
        "registrationDeadline": null,
        "created": null,
        "updated": null,
        "deleted": null,
        "instrumentationNumbers": [],
        "webPages": [],
        "participantFields": [],
        "participantFieldsData": [],
        "participants": {
            "1": {
                "__DEPTH__": 1,
                "registration": false,
                "participationStatus": "regular",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "deleted": null,
                "project": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "year": 2099,
                    "name": "TestProject2099",
                    "type": "temporary",
                    "mailingListId": null,
                    "registrationStartDate": null,
                    "registrationDeadline": null,
                    "created": null,
                    "updated": null,
                    "deleted": null,
                    "instrumentationNumbers": [],
                    "webPages": [],
                    "participantFields": [],
                    "participantFieldsData": [],
                    "participants": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "applications": [],
                    "sepaDebitMandates": [],
                    "compositePayments": [],
                    "payments": [],
                    "invoices": [],
                    "participantInstruments": [],
                    "calendarEvents": [],
                    "sentEmail": [],
                    "financialBalanceDocumentsStorage": null
                },
                "musician": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "surName": "Musterperson",
                    "firstName": "Max",
                    "nickName": null,
                    "displayName": null,
                    "gender": null,
                    "userIdSlug": null,
                    "userPassphrase": null,
                    "city": "Nirgends",
                    "street": "Unauffindbarweg",
                    "streetNumber": "42",
                    "addressSupplement": "Igloo 13",
                    "poBox": null,
                    "country": "AQ",
                    "postalCode": "Z-7",
                    "language": null,
                    "mobilePhone": "0815",
                    "fixedLinePhone": "4711",
                    "birthday": "2024-01-01T00:00:00.000000Z",
                    "email": "john.doe@nowhere.tld",
                    "defaultParticipationStatus": "regular",
                    "remarks": null,
                    "cloudAccountDeactivated": null,
                    "cloudAccountDisabled": true,
                    "updated": "2024-01-01T00:00:00.000000Z",
                    "addressBookUri": null,
                    "organization": null,
                    "jobTitle": null,
                    "uuid": "00000000-0000-0000-0000-000000000000",
                    "created": "2024-01-01T00:00:00.000000Z",
                    "deleted": "2024-01-01T00:00:00.000000Z",
                    "rowAccessToken": null,
                    "emailAddresses": {
                        "entityClassName": "MusicianEmailAddress",
                        "entities": {
                            "john.doe@nowhere.tld": {
                                "flatIdentifier": "john.doe@nowhere.tld:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "instruments": [],
                    "projectApplications": [],
                    "projectParticipation": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "projectInstruments": [],
                    "projectParticipantFieldsData": [],
                    "instrumentInsurances": [],
                    "payableInsurances": [],
                    "sepaBankAccounts": {
                        "entityClassName": "SepaBankAccount",
                        "entities": [
                            {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        ]
                    },
                    "sepaDebitMandates": [],
                    "payments": [],
                    "encryptedFiles": [],
                    "invoices": [],
                    "originatedInvoices": []
                },
                "participantFieldsData": [],
                "projectInstruments": [],
                "payments": [],
                "invoices": [],
                "databaseDocuments": null
            }
        },
        "applications": [],
        "sepaDebitMandates": [],
        "compositePayments": [],
        "payments": [],
        "invoices": [],
        "participantInstruments": [],
        "calendarEvents": [],
        "sentEmail": [],
        "financialBalanceDocumentsStorage": null
    },
    "musician": {
        "__DEPTH__": 2,
        "id": 1,
        "surName": "Musterperson",
        "firstName": "Max",
        "nickName": null,
        "displayName": null,
        "gender": null,
        "userIdSlug": null,
        "userPassphrase": null,
        "city": "Nirgends",
        "street": "Unauffindbarweg",
        "streetNumber": "42",
        "addressSupplement": "Igloo 13",
        "poBox": null,
        "country": "AQ",
        "postalCode": "Z-7",
        "language": null,
        "mobilePhone": "0815",
        "fixedLinePhone": "4711",
        "birthday": "2024-01-01T00:00:00.000000Z",
        "email": "john.doe@nowhere.tld",
        "defaultParticipationStatus": "regular",
        "remarks": null,
        "cloudAccountDeactivated": null,
        "cloudAccountDisabled": true,
        "updated": "2024-01-01T00:00:00.000000Z",
        "addressBookUri": null,
        "organization": null,
        "jobTitle": null,
        "uuid": "00000000-0000-0000-0000-000000000000",
        "created": "2024-01-01T00:00:00.000000Z",
        "deleted": "2024-01-01T00:00:00.000000Z",
        "rowAccessToken": null,
        "emailAddresses": {
            "john.doe@nowhere.tld": {
                "__DEPTH__": 1,
                "address": "john.doe@nowhere.tld",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "musician": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "surName": "Musterperson",
                    "firstName": "Max",
                    "nickName": null,
                    "displayName": null,
                    "gender": null,
                    "userIdSlug": null,
                    "userPassphrase": null,
                    "city": "Nirgends",
                    "street": "Unauffindbarweg",
                    "streetNumber": "42",
                    "addressSupplement": "Igloo 13",
                    "poBox": null,
                    "country": "AQ",
                    "postalCode": "Z-7",
                    "language": null,
                    "mobilePhone": "0815",
                    "fixedLinePhone": "4711",
                    "birthday": "2024-01-01T00:00:00.000000Z",
                    "email": "john.doe@nowhere.tld",
                    "defaultParticipationStatus": "regular",
                    "remarks": null,
                    "cloudAccountDeactivated": null,
                    "cloudAccountDisabled": true,
                    "updated": "2024-01-01T00:00:00.000000Z",
                    "addressBookUri": null,
                    "organization": null,
                    "jobTitle": null,
                    "uuid": "00000000-0000-0000-0000-000000000000",
                    "created": "2024-01-01T00:00:00.000000Z",
                    "deleted": "2024-01-01T00:00:00.000000Z",
                    "rowAccessToken": null,
                    "emailAddresses": {
                        "entityClassName": "MusicianEmailAddress",
                        "entities": {
                            "john.doe@nowhere.tld": {
                                "flatIdentifier": "john.doe@nowhere.tld:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "instruments": [],
                    "projectApplications": [],
                    "projectParticipation": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "projectInstruments": [],
                    "projectParticipantFieldsData": [],
                    "instrumentInsurances": [],
                    "payableInsurances": [],
                    "sepaBankAccounts": {
                        "entityClassName": "SepaBankAccount",
                        "entities": [
                            {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        ]
                    },
                    "sepaDebitMandates": [],
                    "payments": [],
                    "encryptedFiles": [],
                    "invoices": [],
                    "originatedInvoices": []
                }
            }
        },
        "instruments": [],
        "projectApplications": [],
        "projectParticipation": {
            "1": {
                "__DEPTH__": 1,
                "registration": false,
                "participationStatus": "regular",
                "created": "2024-01-01T00:00:00.000000Z",
                "updated": "2024-01-01T00:00:00.000000Z",
                "deleted": null,
                "project": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "year": 2099,
                    "name": "TestProject2099",
                    "type": "temporary",
                    "mailingListId": null,
                    "registrationStartDate": null,
                    "registrationDeadline": null,
                    "created": null,
                    "updated": null,
                    "deleted": null,
                    "instrumentationNumbers": [],
                    "webPages": [],
                    "participantFields": [],
                    "participantFieldsData": [],
                    "participants": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "applications": [],
                    "sepaDebitMandates": [],
                    "compositePayments": [],
                    "payments": [],
                    "invoices": [],
                    "participantInstruments": [],
                    "calendarEvents": [],
                    "sentEmail": [],
                    "financialBalanceDocumentsStorage": null
                },
                "musician": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "surName": "Musterperson",
                    "firstName": "Max",
                    "nickName": null,
                    "displayName": null,
                    "gender": null,
                    "userIdSlug": null,
                    "userPassphrase": null,
                    "city": "Nirgends",
                    "street": "Unauffindbarweg",
                    "streetNumber": "42",
                    "addressSupplement": "Igloo 13",
                    "poBox": null,
                    "country": "AQ",
                    "postalCode": "Z-7",
                    "language": null,
                    "mobilePhone": "0815",
                    "fixedLinePhone": "4711",
                    "birthday": "2024-01-01T00:00:00.000000Z",
                    "email": "john.doe@nowhere.tld",
                    "defaultParticipationStatus": "regular",
                    "remarks": null,
                    "cloudAccountDeactivated": null,
                    "cloudAccountDisabled": true,
                    "updated": "2024-01-01T00:00:00.000000Z",
                    "addressBookUri": null,
                    "organization": null,
                    "jobTitle": null,
                    "uuid": "00000000-0000-0000-0000-000000000000",
                    "created": "2024-01-01T00:00:00.000000Z",
                    "deleted": "2024-01-01T00:00:00.000000Z",
                    "rowAccessToken": null,
                    "emailAddresses": {
                        "entityClassName": "MusicianEmailAddress",
                        "entities": {
                            "john.doe@nowhere.tld": {
                                "flatIdentifier": "john.doe@nowhere.tld:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "instruments": [],
                    "projectApplications": [],
                    "projectParticipation": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "projectInstruments": [],
                    "projectParticipantFieldsData": [],
                    "instrumentInsurances": [],
                    "payableInsurances": [],
                    "sepaBankAccounts": {
                        "entityClassName": "SepaBankAccount",
                        "entities": [
                            {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        ]
                    },
                    "sepaDebitMandates": [],
                    "payments": [],
                    "encryptedFiles": [],
                    "invoices": [],
                    "originatedInvoices": []
                },
                "participantFieldsData": [],
                "projectInstruments": [],
                "payments": [],
                "invoices": [],
                "databaseDocuments": null
            }
        },
        "projectInstruments": [],
        "projectParticipantFieldsData": [],
        "instrumentInsurances": [],
        "payableInsurances": [],
        "sepaBankAccounts": [
            {
                "__DEPTH__": 1,
                "sequence": 1,
                "iban": "DE02700100800030876808",
                "bic": "PBNKDEFFXXX",
                "blz": "70010080",
                "bankAccountOwner": "Musterperson, Max",
                "deleted": "2024-01-01T00:00:00.000000Z",
                "created": null,
                "updated": null,
                "musician": {
                    "__DEPTH__": 0,
                    "id": 1,
                    "surName": "Musterperson",
                    "firstName": "Max",
                    "nickName": null,
                    "displayName": null,
                    "gender": null,
                    "userIdSlug": null,
                    "userPassphrase": null,
                    "city": "Nirgends",
                    "street": "Unauffindbarweg",
                    "streetNumber": "42",
                    "addressSupplement": "Igloo 13",
                    "poBox": null,
                    "country": "AQ",
                    "postalCode": "Z-7",
                    "language": null,
                    "mobilePhone": "0815",
                    "fixedLinePhone": "4711",
                    "birthday": "2024-01-01T00:00:00.000000Z",
                    "email": "john.doe@nowhere.tld",
                    "defaultParticipationStatus": "regular",
                    "remarks": null,
                    "cloudAccountDeactivated": null,
                    "cloudAccountDisabled": true,
                    "updated": "2024-01-01T00:00:00.000000Z",
                    "addressBookUri": null,
                    "organization": null,
                    "jobTitle": null,
                    "uuid": "00000000-0000-0000-0000-000000000000",
                    "created": "2024-01-01T00:00:00.000000Z",
                    "deleted": "2024-01-01T00:00:00.000000Z",
                    "rowAccessToken": null,
                    "emailAddresses": {
                        "entityClassName": "MusicianEmailAddress",
                        "entities": {
                            "john.doe@nowhere.tld": {
                                "flatIdentifier": "john.doe@nowhere.tld:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "instruments": [],
                    "projectApplications": [],
                    "projectParticipation": {
                        "entityClassName": "ProjectParticipant",
                        "entities": {
                            "1": {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        }
                    },
                    "projectInstruments": [],
                    "projectParticipantFieldsData": [],
                    "instrumentInsurances": [],
                    "payableInsurances": [],
                    "sepaBankAccounts": {
                        "entityClassName": "SepaBankAccount",
                        "entities": [
                            {
                                "flatIdentifier": "1:1",
                                "entityClassName": null
                            }
                        ]
                    },
                    "sepaDebitMandates": [],
                    "payments": [],
                    "encryptedFiles": [],
                    "invoices": [],
                    "originatedInvoices": []
                },
                "sepaDebitMandates": [],
                "payments": []
            }
        ],
        "sepaDebitMandates": [],
        "payments": [],
        "encryptedFiles": [],
        "invoices": [],
        "originatedInvoices": []
    },
    "participantFieldsData": [],
    "projectInstruments": [],
    "payments": [],
    "invoices": [],
    "databaseDocuments": null
}',
  ];

  /** @return void */
  public function testSerialization(): void
  {
    foreach (self::SERIALIZED_ENTITY as $depth => $data) {
      $this->entityArrayAdapter->setDepth($depth);
      $this->assertEquals($depth, $this->entityArrayAdapter->getDepth());
      $this->assertEquals(
        self::SERIALIZED_ENTITY[$this->entityArrayAdapter->getDepth()],
        json_encode($this->entityArrayAdapter, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),
      );
    }
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
