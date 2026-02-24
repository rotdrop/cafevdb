<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use NumberFormatter;
use Throwable;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\Registration as ServiceRegistration;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Connect to a GnuCash account book stored in a MariaDB database. This is
 * realized by hijacking the accounts, books, commodities, slots, splits and
 * transactions tables of an existing GnuCash database, moving the tables to
 * the cafevdb database and replacing the original tables by views with
 * security definer.
 */
class GnuCashConnectorService
{
  use \OCA\CAFEVDB\Toolkit\Traits\BracedPlaceholderTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const GNU_CASH_AUTOCOMPLETE_ACCOUNTS_APP_DATA_FILE = 'gnucash/autocomplete-accounts.json';
  public const GNU_CASH_INCOME_KEY = 'income';
  public const GNU_CASH_EXPENSE_KEY = 'expense';
  public const DEFAULT_RECEIVABLES_ACCOUNT_TEMPLATE = 'assets:receivables:participants:{PERSON}:{PROJECT}:{GENERATOR_TAG}';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private AppStorage $appStorage,
    private EncryptionService $encryptionService,
    private EntityManager $entityManager,
    private FinanceService $financeService,
    private UserStorage $userStorage,
    protected IAppContainer $appContainer,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
  }
  // phpcs:enable

  public const PERSON_KEY = 'PERSON';
  public const PROJECT_KEY = 'PROJECT';
  public const GENERATOR_TAG_KEY = 'GENERATOR_TAG';

  /**
   * Generate the GnuCash account for the given receivable if the
   * corresponding template is configured.
   *
   * @param Entities\ProjectParticipantFieldDatum $receivable
   *
   * @return null|string Return \null if no template is defined or the given data is not a receivable or liability.
   */
  public function generateParticipantReceivablesAccount(
    Entities\ProjectParticipantFieldDatum $receivable,
  ):?string {
    $field = $receivable->getField();
    $fieldType = $field->getDataType();
    if ($fieldType != FieldDataType::RECEIVABLES && $fieldType != FieldDataType::LIABILITIES) {
      return null;
    }
    $accountTemplate = $this->encryptionService->getAppValue(AdminSettings::GNU_CASH_PARTICIPANT_RECEIVABLES_ACCOUNT_KEY);
    if (empty($accountTemplate)) {
      return null;
    }
    if ($field->getMultiplicity() == FieldMultiplicity::RECURRING) {
      $generatorOption = $field->getManagementOption();
      $class = $generatorOption->getData();
      $generatorSlug = $class::balancingAccountSlug();
      if ($generatorSlug !== null) {
        $generatorSlug = $this->l->t($generatorSlug);
      }
    }
    $participant = $receivable->getProjectParticipant();
    $values = [
      self::PERSON_KEY => $participant->getMusician()->getPublicName(firstNameFirst: false),
      self::PROJECT_KEY => $participant->getProject()->getName(),
      self::GENERATOR_TAG_KEY => $generatorSlug ?? '',
    ];
    $l10nKeys =[
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      self::PERSON_KEY => $this->l->t(self::PERSON_KEY),
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      self::PROJECT_KEY => $this->l->t(self::PROJECT_KEY),
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      self::GENERATOR_TAG_KEY => $this->l->t(self::GENERATOR_TAG_KEY),
    ];
    $l10nKeys = array_combine(array_keys($values), array_map(fn(string $key) => $this->l->t($key), array_keys($values)));
    $account = str_replace('::', ':', trim($this->replaceBracedPlaceholders($accountTemplate, $values, $l10nKeys), ':'));

    return $account;
  }

  /**
   * Gnerate autocomplete data from an accounts CSV export from GnuCash. Only
   * valid for autocompletion are income and expense accounts.
   *
   * @return null|array
   * ```[ 'income' => [ AC0, AC1, ... ], 'expense' => [ AC0, AC1, ... ] ]```
   */
  public function generateAccountsAutocompleteData(): ?array
  {
    $accountsExport = $this->encryptionService->getAppValue(AdminSettings::GNU_CASH_ACCOUNTS_TREE_DATA_KEY);
    if (empty($accountsExport)) {
      $this->logError('ACCOUNTS EXPORT FILE IS NOT SET');
      return null;
    }
    $accountsExportFile = $this->userStorage->getFile($accountsExport);
    if (empty($accountsExportFile)) {
      $this->logError('UNABLE TO OPEN ACCOUNTS EXPORTS FILE ' . $accountsExport);
      return null;
    }

    $accountsAutocompleteFile = $this->appStorage->getFile(self::GNU_CASH_AUTOCOMPLETE_ACCOUNTS_APP_DATA_FILE, throw: false);
    if ($accountsAutocompleteFile !== null && $accountsExportFile->getMTime() <= $accountsAutocompleteFile->getMTime()) {
      return json_decode($accountsAutocompleteFile->getContent(), true);
    }

    $permanentProjects = $this->entityManager->getRepository(Entities\Project::class)->findNames(onlyType: ProjectType::PERMANENT);

    $leafAccountRe = '/:([^0-9]+[0-9]{4}|'
      . implode('|', array_map(fn(string $name) => preg_quote($name), $permanentProjects))
      . ')$/';

    $autocompleteData = [
      self::GNU_CASH_EXPENSE_KEY => [],
      self::GNU_CASH_INCOME_KEY => [],
    ];

    $exportData = explode("\n", $accountsExportFile->getContent());

    foreach ($exportData as $dataLine) {
      $lineData = str_getcsv($dataLine, ';', escape: '');
      if (empty($lineData) || $lineData[0] === null) {
        break;
      }
      $type = strtolower($lineData[0]);
      if ($type != self::GNU_CASH_EXPENSE_KEY && $type != self::GNU_CASH_INCOME_KEY) {
        continue;
      }
      $account = preg_replace($leafAccountRe, '', $lineData[1]);
      $autocompleteData[$type][] = $account;
    }
    foreach ($autocompleteData as &$accounts) {
      $accounts = array_unique($accounts);
      sort($accounts);
      $accounts = array_values($accounts);

      $count = count($accounts);
      for ($i = 0; $i < $count - 1; ++$i) {
        if (str_starts_with($accounts[$i + 1], $accounts[$i] . ':')) {
          unset($accounts[$i]);
        }
      }
      $accounts = array_values($accounts);
    }

    if (!$accountsAutocompleteFile) {
      $accountsAutocompleteFile = $this->appStorage->ensureFile(self::GNU_CASH_AUTOCOMPLETE_ACCOUNTS_APP_DATA_FILE);
    }
    $accountsAutocompleteFile->putContent(json_encode($autocompleteData));

    return $autocompleteData;
  }

  /**
   * @param array|int|string|Entities\Project $project
   *
   * @return array
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function getAccountsAutocompleteData(int|string|array|Entities\Project $project):array
  {
    $autocompleteData = $this->generateAccountsAutocompleteData();
    if (empty($autocompleteData)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('GnuCash accounts autocompletion data is unavailable, please contact an administrator.'),
      );
    }
    if (is_string($project) && !is_numeric($project)) {
      $name = $project;
    } else {
      try {
        $project = $this->entityManager->getRepository(Entities\Project::class)->ensureProject($project);
      } catch (Exceptions\DatabaseMissingIdentifierException $e) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Unable to fetch the project entity from the database given the id-data "%s"', $project),
          previous: $e,
        );
      }
      $name = $project->getName();
    }

    foreach ($autocompleteData as &$accounts) {
      foreach ($accounts as &$account) {
        $account .= ':' . $name;
      }
    }
    return [
      'projectName' => $name,
      'accounts' => $autocompleteData,
    ];
  }

  /**
   * @return string The currency code used by the orchestra.
   */
  protected function getAppCurrencyCode():string
  {
    $locale = $this->appContainer->get(ServiceRegistration::APP_LOCALE);
    $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $fmt->getTextAttribute(NumberFormatter::CURRENCY_CODE);
  }

  /**
   * Export a bulk transaction to a GnuCash multi-split transactions CSV import.
   *
   * @param Entities\SepaBulkTransaction $bulkTransaction
   *
   * @return array
   */
  public function exportBulkTransactionBalancingEntries(Entities\SepaBulkTransaction $bulkTransaction): array
  {
    $data = [];
    foreach ($bulkTransaction->getPayments() as $compositePayment) {
      $data = array_merge($data, $this->exportCompositePaymentBalancingEntries($compositePayment));
    }
    return $data;
  }

  /**
   * Export a CompositePayment entity to a GnuCash transactions CSV import. We
   * use multi-split mode as the composite payment may contain arbitrarily
   * many splits with different transfer accounts.
   *
   * @param Entities\CompositePayment $compositePayment
   *
   * @return array
   */
  public function exportCompositePaymentBalancingEntries(Entities\CompositePayment $compositePayment): array
  {
    $currencyCode = $this->getAppCurrencyCode();

    $receivableAccounts = [];
    $musician = $compositePayment->getProjectParticipant()->getMusician();
    $description = $compositePayment->getSubject() . '; ' . $musician->getPublicName(false);

    // it need not be the case that a composite payment result in the same
    // balancing account for each splits, though in general this should be the
    // case.
    /** @var Entities\ProjectPayment $projectPayment */
    foreach ($compositePayment->getProjectPayments() as $projectPayment) {
      $receivableAccount = $this->generateParticipantReceivablesAccount($projectPayment->getReceivable());
      if (!isset($receivableAccounts[$receivableAccount])) {
        $receivableAccounts[$receivableAccount] = [
          'payments' => [ $projectPayment ],
        ];
      } else {
        $receivableAccounts[$receivableAccount]['payments'][] = $projectPayment;
      }
    }
    $data = [];
    foreach ($receivableAccounts as $receivableAccount => $accountData) {
      $dueDate = max(
        array_map(
          fn(Entities\ProjectPayment $payment) => $this->financeService->getDueDate($payment->getReceivable()),
          $accountData['payments'],
        ),
      );
      /** @var MonetaryNumberType $subTotals */
      $subTotals = array_reduce(
        $accountData['payments'],
        fn(MonetaryNumberType $carry, Entities\ProjectPayment $payment) => $carry->add($payment->getAmount()),
        MonetaryNumberType::zero(),
      );
      $transactionId = md5($receivableAccount);
      $data[] = [
        'transactionId' => $transactionId,
        'date' => $dueDate->format('d-m-Y'),
        'amount' => $subTotals->toDecimal(2), // + or minus?
        'negativeAmount' => $subTotals->neg()->toDecimal(2), // + or minus?
        'account' => $receivableAccount,
        'description' => $description,
        'currency' => $currencyCode,
        'notes' => '',
        'memo' => '',
      ];
      /** @var Entities\ProjectPayment $projectPayment */
      foreach ($accountData['payments'] as $projectPayment) {
        $receivable = $projectPayment->getReceivable();
        $balancingAccount = $receivable->getBalancingAccount();
        if (empty($balancingAccount)) {
          $field = $receivable->getField();
          if ($field->getMultiplicity() == FieldMultiplicity::RECURRING) {
            $generatorOption = $field->getManagementOption();
            $class = $generatorOption->getData();
            echo $class . PHP_EOL;
            try {
              $generator = $this->appContainer->get($class);
              if (method_exists($generator, 'generateLegacyBalancingAccount')) {
                $balancingAccount = $generator->generateLegacyBalancingAccount($receivable);
              }
            } catch (Throwable $t) {
              echo $t->getMessage() . PHP_EOL;
              // ignore
              $balancingAccount = '';
            }
          }
        }
        /** @var MonetaryNumberType $amount */
        $data[] = [
          'transactionId' => $transactionId,
          'date' => '',
          'amount' => $projectPayment->getAmount()->neg()->toDecimal(2),
          'negativeAmount' => $projectPayment->getAmount()->toDecimal(2),
          'account' => $balancingAccount,
          'subject' => '',
          'description' => '',
          'notes' => '',
          'memo' => $projectPayment->getSubject(),
        ];
      }
    }

    return $data;
  }

  /** @return void */
  protected static function translationInjector(): void
  {
    self::t(self::DEFAULT_RECEIVABLES_ACCOUNT_TEMPLATE);
  }
}
