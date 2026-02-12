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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\Finance;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\GnuCashConnectorService;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the GnuCashConnectorService */
#[Attributes\CoversClass(AppStorage::class)]
#[Attributes\CoversClass(Finance\AbstractReceivablesGenerator::class)]
#[Attributes\CoversClass(Finance\FinanceService::class)]
#[Attributes\CoversClass(Finance\ManuallyGeneratedReceivablesGenerator::class)]
#[Attributes\CoversClass(Finance\ReceivablesGeneratorFactory::class)]
#[Attributes\CoversClass(GnuCashConnectorService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\DoNothingProgressStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\TranslatableTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class GnuCashConnectorServiceTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockProjectsRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\MockEntityManagerTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\GetAppStorageTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;

  private GnuCashConnectorService $service;

  private MockProvider $mockProvider;

  private EncryptionService $encryptionService;

  private Entities\CompositePayment $compositePayment;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->generateProjectParticipant(persist: false);
    $this->compositePayment = $this->generateCompositePayment(persist: false);
    $this->getEntityManagerMock();
    $this->getProjectsRepositoryMock();
    $this->getUserStorageStub();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->encryptionService = $this->mockProvider->getEncryptionService();

    $appContainer = $this->mockProvider->getAppContainer();

    $this->service = new GnuCashConnectorService(
      appContainer: $this->mockProvider->getAppContainer(),
      appStorage: $this->getAppStorage(),
      encryptionService: $this->encryptionService,
      entityManager: $this->entityManager,
      financeService: $appContainer->get(FinanceService::class),
      l: $this->mockProvider->getL10N(),
      logger: $this->mockProvider->getLoggerInterface(),
      userStorage: $this->userStorage,
    );

    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');
  }

  /** {@inheritdoc} */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /**
   * Provide exmaple GNC accounts export file.
   *
   * @return void
   */
  private function generateGNCExportFile(): void
  {
    $gncAccountsFile = '/foo/bar/gnc-acocunts.csv';
    $this->encryptionService->setAppValue(AdminSettings::GNU_CASH_ACCOUNTS_TREE_DATA_KEY, $gncAccountsFile);
    $result = $this->service->generateAccountsAutocompleteData();
    $this->assertNull($result);

    $gncAccounts = file_get_contents(__DIR__ . '/gnc-accounts.csv');
    $this->userStorage->putContent($gncAccountsFile, $gncAccounts);
    $checkContent = $this->userStorage->get($gncAccountsFile)->getContent();
    $this->assertEquals($gncAccounts, $checkContent);
  }

  /** @return void */
  public function testGenerateAccountsAutocompleteData(): void
  {
    $result = $this->service->generateAccountsAutocompleteData();
    $this->assertNull($result);

    $this->generateGNCExportFile();

    $result = $this->service->generateAccountsAutocompleteData();
    $this->assertArrayHasKey(GnuCashConnectorService::GNU_CASH_EXPENSE_KEY, $result);
    $this->assertArrayHasKey(GnuCashConnectorService::GNU_CASH_INCOME_KEY, $result);
  }

  /** @return void */
  public function testGetAccountsAutocompleteData(): void
  {
    try {
      $this->service->getAccountsAutocompleteData($this->project->getId());
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }

    $this->generateGNCExportFile();
    $result = $this->service->getAccountsAutocompleteData($this->project->getId());
    $this->assertArrayHasKey('projectName', $result);
    $projectName = $this->project->getName();
    $this->assertEquals($projectName, $result['projectName']);
    $this->assertArrayHasKey('accounts', $result);
    $accounts = $result['accounts'];
    $this->assertArrayHasKey(GnuCashConnectorService::GNU_CASH_EXPENSE_KEY, $accounts);
    $this->assertArrayHasKey(GnuCashConnectorService::GNU_CASH_INCOME_KEY, $accounts);
    foreach ([GnuCashConnectorService::GNU_CASH_EXPENSE_KEY, GnuCashConnectorService::GNU_CASH_INCOME_KEY] as $type) {
      $typeAccounts = $accounts[$type];
      $this->assertTrue(array_is_list($typeAccounts));
      foreach ($typeAccounts as $account) {
        $this->assertStringEndsWith(':' . $projectName, $account);
      }
    }
  }

  private const COMPOSITE_EXPORT_KEYS = [
    'account',
    'amount',
    'currency',
    'date',
    'description',
    'memo',
    'negativeAmount',
    'notes',
    'transactionId',
  ];

  /** @return void */
  public function testExportCompositePaymentBalancingEntries(): void
  {
    $this->encryptionService->setAppValue(
      AdminSettings::GNU_CASH_PARTICIPANT_RECEIVABLES_ACCOUNT_KEY,
      GnuCashConnectorService::DEFAULT_RECEIVABLES_ACCOUNT_TEMPLATE,
    );
    $balancingAccount = 'balancing:account';
    /** @var Entities\ProjectPayment $payment */
    foreach ($this->compositePayment->getProjectPayments() as $payment) {
      $payment->getReceivable()->getDataOption()->setBalancingAccount($balancingAccount);
    }
    $result = $this->service->exportCompositePaymentBalancingEntries($this->compositePayment);
    $this->assertEquals($this->compositePayment->getProjectPayments()->count() + 1, count($result));
    // print_r($result);
    $leadingRecord = array_shift($result);
    $this->assertEqualsCanonicalizing(self::COMPOSITE_EXPORT_KEYS, array_keys($leadingRecord));
    $projectName = $this->project->getName();
    $this->assertStringEndsWith(':' . $projectName, $leadingRecord['account']);
    $expectedKeys = array_diff([...self::COMPOSITE_EXPORT_KEYS, 'subject'], ['currency']);
    $this->assertTrue(
      ($leadingRecord['amount'] == '-' . $leadingRecord['negativeAmount'])
      ||
      ('-' . $leadingRecord['amount'] == $leadingRecord['negativeAmount'])
    );
    foreach ($result as $record) {
      $this->assertEqualsCanonicalizing($expectedKeys, array_keys($record));
      $this->assertEquals($balancingAccount, $record['account']);
      $this->assertEmpty($record['data']);
      $this->assertTrue(
        ($record['amount'] == '-' . $record['negativeAmount'])
        ||
        ('-' . $record['amount'] == $record['negativeAmount'])
      );
      $this->assertTrue(
        ($record['amount'] == '-' . $leadingRecord['amount'])
        ||
        ('-' . $record['amount'] == $leadingRecord['amount'])
      );
    }
  }
}
