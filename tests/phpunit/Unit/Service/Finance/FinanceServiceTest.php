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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IL10N;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the SepaBulkTransactionsService */
#[Attributes\CoversClass(FinanceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractEnumType::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitMandate::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::Class)]
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
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class FinanceServiceTest extends TestCase
{
  use EntityGeneratorTrait {
    EntityGeneratorTrait::setup as entitySetup;
  }

  private FinanceService $financeService;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->entitySetup(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $configService = $mockProvider->getConfigService();
    $entityManager = $this->createStub(EntityManager::class);
    $invoicesRepository = $this->createStub(EntityRepository::class);
    $invoicesRepository->method('findLike')->willReturn([]);
    $entityManager->method('getRepository')->willReturnCallback(
      function (string $className) use ($invoicesRepository) {
        switch ($className) {
          case Entities\Invoice::class:
            return $invoicesRepository;
          default:
            return $this->createStub(EntityRepository::class);
        }
      },
    );

    $eventsService = $this->createStub(EventsService::class);

    $organizationalRolesService = $this->createStub(OrganizationalRolesService::class);
    $organizationalRolesService->method('treasurerContact')
      ->willReturnCallback(
        function() use ($organizationalRolesService) {
          return $organizationalRolesService->dedicatedBoardMemberContact(
            OrganizationalRolesService::TREASURER_ROLE,
          );
        },
      );
    $organizationalRolesService->method('dedicatedBoardMemberContact')
      ->willReturnCallback(
        function(string $role, int $musicianId = 0) {
          return [
            'email' => 'role@orchestra.org',
            'name' => 'Board Member ' . ucfirst($role),
            'firstName' => 'Board Member',
            'surName' => ucfirst($role),
            'street' => 'Some Street',
            'streetNumber' => 17,
            'streetAndNumber' => 'Some Street 17',
            'postalCode' => 'Z-12345',
            'city' => 'Unknown City',
            'phone' => '1234567',
            'mobile' => '1234567',
          ];
        },
      );

    $this->financeService = new FinanceService(
      configService: $configService,
      entityManager: $entityManager,
      eventsService: $eventsService,
      rolesService: $organizationalRolesService,
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
    // $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testSepaTranslit(): void
  {
    $result = $this->financeService->sepaTranslit(implode('', array_keys(FinanceService::SEPA_TRANSLIT_EXTRA)));
    $this->assertEquals(implode('', array_values(FinanceService::SEPA_TRANSLIT_EXTRA)), $result);
  }

  /** @return void */
  public function testValidateSepaString(): void
  {
    $good = $this->financeService->validateSepaString('BlahBlah' . implode('', array_values(FinanceService::SEPA_TRANSLIT_EXTRA)));
    $this->assertEquals(true, $good);
    $bad = $this->financeService->validateSepaString(implode('', array_keys(FinanceService::SEPA_TRANSLIT_EXTRA)));
    $this->assertEquals(false, $bad);
  }

  /** @return void */
  public function testGenerateSepaMandateReference(): void
  {
    $sepaDebitMandate = new Entities\SepaDebitMandate()
      ->setProject($this->project)
      ->setMusician($this->musician)
      ->setSepaBankAccount($this->musician->getSepaBankAccounts()->first())
      ->setMandateReference('')
      ->setSequence(1);
    $reference = $this->financeService->generateSepaMandateReference($sepaDebitMandate);
    $this->assertEquals('0001-0001-MM-TESTPROJECTXXXX2099+01', $reference);
  }

  /** @return void */
  public function testValidateSepaAccount(): void
  {
    $account = clone $this->musician->getSepaBankAccounts()->first();
    $result = $this->financeService->validateSepaAccount($account);

    $account = clone $this->musician->getSepaBankAccounts()->first();
    $account->setBankAccountOwner('öäü');
    try {
      $result = $this->financeService->validateSepaAccount($account);
      throw Exception('Code should not be reached');
    } catch (InvalidArgumentException) {
    }

    $account = clone $this->musician->getSepaBankAccounts()->first();
    $account->setIban('DE00XXXXXXXXXXXXXXX');
    try {
      $result = $this->financeService->validateSepaAccount($account);
      throw Exception('Code should not be reached');
    } catch (InvalidArgumentException) {
    }

    $account = clone $this->musician->getSepaBankAccounts()->first();
    $account->setBLZ(12312312);
    try {
      $result = $this->financeService->validateSepaAccount($account);
      throw Exception('Code should not be reached');
    } catch (InvalidArgumentException) {
    }

    $account = clone $this->musician->getSepaBankAccounts()->first();
    $account->setBIC('123');
    try {
      $result = $this->financeService->validateSepaAccount($account);
      throw Exception('Code should not be reached');
    } catch (InvalidArgumentException) {
    }
  }

  private const TEST_IBAN = 'DE02700100800030876808';
  private const IBAN_INFO = [
    'iban' => self::TEST_IBAN,
    'country' => 'Deutschland (DE)',
    'bic' => 'PBNKDEFFXXX',
    'blz' => '70010080',
    'account' => '0030876808',
    'bank' => 'Postbank Ndl der Deutsche Bank',
    'city' => 'München',
  ];

  /** @return void */
  public function testGetIbanInfo(): void
  {
    $ibanInfo = $this->financeService->getIbanInfo($this->musician->getSepaBankAccounts()->first()->getIban());
    $this->assertEqualsCanonicalizing(self::IBAN_INFO, $ibanInfo);
  }

  /** @return void */
  public function testMakeIban(): void
  {
    $this->assertEquals(self::TEST_IBAN, $this->financeService->makeIBAN('70010080', '0030876808'));
  }

  /** @return void */
  public function testValidateSWIFT(): void
  {
    $bic = 'PBNKDEFFXXX';
    $this->assertEquals(true, $this->financeService->validateSWIFT($bic));
    $bic = 'PBNKDEFFXX';
    $this->assertEquals(false, $this->financeService->validateSWIFT($bic));
  }

  /** @return void */
  public function testGenerateInvoiceNumber(): void
  {
    $yearMonth = '2030-12';
    $date = DateTimeImmutable::createFromFormat('Y-m', $yearMonth);
    $invoiceNumber = $this->financeService->generateInvoiceNumber($this->musician, $this->project, $date);
    $this->assertEquals(
      $this->project->getName() . '-' . $this->musician->getInitials() . '-' . $yearMonth . '-1',
      $invoiceNumber,
    );
  }

  /** @return void */
  public function testFinanceEvent(): void
  {
    $this->financeService->financeEvent(
      title: 'Event Title',
      description: 'Event Description',
      project: $this->project,
      start: DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-31'),
      end: null,
      alarm: 3600,
      payments: [$this->generateCompositePayment()],
      related: [],
    );
  }

  /** @return void */
  public function testFinanceTask(): void
  {
    $this->financeService->financeTask(
      title: 'Event Title',
      description: 'Event Description',
      project: $this->project,
      start: DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-01'),
      due: DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-31'),
      alarm: 3600,
      payments: [$this->generateCompositePayment()],
      related: [],
    );
  }
}
