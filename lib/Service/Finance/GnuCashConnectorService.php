<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021-2025 Claus-Justus Heine
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

use Throwable;
use UnexpectedValueException;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\View;

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

  private const GNU_CASH_TABLES = [
    'accounts',
    'books',
    'commodities',
    'slots',
    'splits',
    'transactions',
  ];
  private const INSERT_STMT = 'INSERT INTO %1$s (%3$s) SELECT %3$s FROM %2$s ON DUPLICATE KEY UPDATE %1$s.guid = %2$s.guid';
  private const CREATE_VIEW_STMT = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT *
FROM %2$s';

  /** @var Connection */
  private $connection;

  /** @var string */
  private $appDbHost;

  /** @var string */
  private $appDbUser;

  /** @var string */
  private $appDbName;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private EncryptionService $encryptionService,
    protected IAppContainer $appContainer,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
    if ($this->encryptionService->bound()) {
      $this->connection = $this->appContainer->get(Connection::class);
      $this->appDbName = $this->encryptionService->getConfigValue('dbname');
      $this->appDbUser = $this->encryptionService->getConfigValue('dbuser');
      $this->appDbHost = $this->encryptionService->getConfigValue('dbserver');
    }
  }
  // phpcs:enable

  /**
   * Copy the data out of the given GnuCash data-base.
   *
   * @param string $gnuCashDatabase
   *
   * @param null|string $user
   *
   * @param null|string $password
   *
   * @param null|string $host
   *
   * @return void
   */
  public function copyGnuCashTables(
    string $gnuCashDatabase,
    ?string $user = null,
    ?string $password = null,
    ?string $host = null,
  ):void
  {
    // // $em is your Doctrine\ORM\EntityManager instance
    // $schemaManager = $em->getConnection()->getSchemaManager();
    // // array of Doctrine\DBAL\Schema\Column
    // $columns = $schemaManager->listTableColumns($tableName);

    // $columnNames = [];
    // foreach($columns as $column){
    //   $columnNames[] = $column->getName();
    // }
    // // $columnNames contains all column names

    /** @var SchemaManager $schemaManager */
    $schemaManager = $this->connection->getSchemaManager();

    $gncConnection = $this->connection->bind(
      $gnuCashDatabase,
      $user,
      $password,
      $host,
    );
    /** @var SchemaManager $gncSchemaManager */
    $gncSchemaManager = $gncConnection->getSchemaManager();

    $gncViews = array_keys($gncSchemaManager->listViews());

    foreach (self::GNU_CASH_TABLES as $gncTable) {
      if (in_array($gncTable, $gncViews)) {
        // test is not perfect, but just let's skip it if it is a view already.
        continue;
      }
      $gncColumns = array_map(
        fn($column) => $column->getName(),
        $gncSchemaManager->listTableColumns($gncTable),
      );
      sort($gncColumns);

      $ormTable = 'GnuCash' . ucfirst($gncTable);
      $ormColumns = array_map(
        fn($column) => $column->getName(),
        $schemaManager->listTableColumns($ormTable),
      );
      sort($ormColumns);

      if ($gncColumns != $ormColumns) {
        print_r($ormColumns);
        print_r($gncColumns);
        throw new UnexpectedValueException(
          $this->l->t('%1$s column names differ from expected column-names.', 'GnuCash'),
        );
      }

      $sql = vsprintf(
        'SET FOREIGN_KEY_CHECKS=0;'
        . self::INSERT_STMT, [
          $this->appDbName . '.' . $ormTable,
          $gnuCashDatabase . '.' . $gncTable,
          implode(',', $gncColumns),
        ],
      );
      $this->logInfo('SQL ' . $sql);
      $this->connection->prepare($sql)->execute();

      $gncSchemaManager->renameTable($gncTable, $gncTable . '_old');

      try {
        $sql = vsprintf(
          self::CREATE_VIEW_STMT, [
            $gnuCashDatabase . '.' . $gncTable,
            $this->appDbName . '.' . $ormTable,
          ],
        );
        $this->logInfo('SQL ' . $sql);
        $gncConnection->prepare($sql)->execute();
      } catch (Throwable $t) {
        $this->logException($t);
        $gncSchemaManager->renameTable($gncTable . '_old', $gncTable);
      }
    }
  }

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
   * @param null|Entities\Project $project If non-null the project name will
   * always added as last component to the account name.
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
    /** @var UserStorage $userStorage */
    $userStorage = $this->appContainer->get(UserStorage::class);
    $accountsExportFile = $userStorage->getFile($accountsExport);
    if (empty($accountsExportFile)) {
      $this->logError('UNABLE TO OPEN ACCOUNTS EXPORTS FILE ' . $accountsExport);
      return null;
    }

    /** @var AppStorage $appStorage */
    $appStorage = $this->appContainer->get(AppStorage::class);
    $accountsAutocompleteFile = $appStorage->getFile(self::GNU_CASH_AUTOCOMPLETE_ACCOUNTS_APP_DATA_FILE, throw: false);
    if ($accountsAutocompleteFile !== null && $accountsExportFile->getMTime() <= $accountsExportFile->getMTime()) {
      return json_decode($accountsAutocompleteFile->getContent(), true);
    }

    /** @var EntityManager $entityManager */
    $entityManager = $this->appContainer->get(EntityManager::class);
    $permanentProjects = $entityManager->getRepository(Entities\Project::class)->findNames(onlyType: ProjectType::PERMANENT);

    $leafAccountRe = '/:([^0-9]+[0-9]{4}|'
      . implode('|', array_map(fn(string $name) => preg_quote($name), $permanentProjects))
      . ')$/';

    $autocompleteData = [
      self::GNU_CASH_EXPENSE_KEY => [],
      self::GNU_CASH_INCOME_KEY => [],
    ];

    $exportData = explode("\n", $accountsExportFile->getContent());

    foreach ($exportData as $dataLine) {
      $lineData = str_getcsv($dataLine, ';');
      if ($lineData === false) {
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
      $accountsAutocompleteFile = $appStorage->ensureFile(self::GNU_CASH_AUTOCOMPLETE_ACCOUNTS_APP_DATA_FILE);
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
    if ($project instanceof Entities\Project) {
      $name = $project->getName();
    } else {
      /* @var EntityManager $entityManager */
      $entityManager = $this->appContainer->get(EntityManager::class);
      $name = $entityManager->getRepository(Entities\Project::class)->findName($project);
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
}
