<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;
use OCP\App\IAppManager;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Common\Util;

/**
 * Manage database-views and grants in order to selectively provide only the
 * neccessary information to the ambient cloud system.
 *
 * Integrates with the cafevdbmembers and the user_sql app.
 */
class CloudUserConnectorService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const REQUIREMENTS_OK = true;
  const REQUIREMENTS_MISSING = false;

  const CLOUD_USER_BACKEND = 'user_sql';
  const CLOUD_USER_GROUP_ID = self::CLOUD_USER_BACKEND;

  const VIEW_POSTFIX = 'View';

  const USER_SQL_PREFIX = 'Nextcloud';
  const PERSONALIZED_PREFIX = 'Personalized';

  const GROUP_ID_PREFIX = '%2$s:';

  /**
   * @var string
   *
   * The SQL to define the group-connector view for the user_sql
   * user-backend. Only projects with active users show up.
   *
   * %1$s is the view-name
   * %2$s is the app-name
   */
  const USER_SQL_GROUP_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT CONCAT(_ascii "' . self::GROUP_ID_PREFIX. '" , p.id) COLLATE ascii_bin AS gid,
       p.name AS display_name,
       0 AS is_admin
FROM Projects p
WHERE p.id IN (SELECT DISTINCT pp.project_id FROM ProjectParticipants pp WHERE pp.deleted IS NULL)
WITH CHECK OPTION';

  const USER_SQL_USER_GROUP_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT m.user_id_slug AS uid,
       CONCAT(_ascii "%2$s:", p.id) COLLATE ascii_bin AS gid
FROM ProjectParticipants pp
LEFT JOIN Musicians m ON m.id = pp.musician_id
LEFT JOIN Projects p ON p.id = pp.project_id
WHERE pp.musician_id in
    (SELECT pp1.musician_id
     FROM ProjectParticipants pp1
     LEFT JOIN Projects p1 ON pp1.project_id = p1.id
     WHERE p1.type = "permanent")';
  // WITH CHECK OPTION. But view is not updatable. Ok.

  /**
   * @var string
   *
   * The SQL query to define the user-connector view for the user_sql
   * user-backend. Note that active/inactive could be omitted as this status
   * is maintained by the cloud itself in the user preferences table. The
   * "disabled" switch -- if set -- prevents the user to show up in the cloud
   * at all.
   */
  const USER_SQL_USER_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT m.id AS id,
       CAST(m.user_id_slug AS CHAR CHARACTER SET utf8mb4) AS uid,
       CAST(m.user_id_slug AS CHAR CHARACTER SET utf8mb4) AS username,
       m.user_passphrase AS password,
       CONCAT_WS(" ", IF(m.nick_name IS NULL
                         OR m.nick_name = "", m.first_name, m.nick_name), m.sur_name) AS name,
       m.email AS email,
       NULL AS quota,
       NULL AS home,
       m.cloud_account_deactivated AS inactive,
       IF(m.deleted IS NOT NULL OR m.cloud_account_disabled = 1, 1, 0) AS disabled,
       0 AS avatar,
       NULL AS salt
FROM Musicians m
WHERE m.id in
    (SELECT pp.musician_id
     FROM ProjectParticipants pp
     LEFT JOIN Projects p ON pp.project_id = p.id
     WHERE p.type = "permanent")
WITH CHECK OPTION';

  const USER_SQL_VIEWS = [
    'User' => self::USER_SQL_USER_VIEW,
    'Group' => self::USER_SQL_GROUP_VIEW,
    'UserGroup' => self::USER_SQL_USER_GROUP_VIEW,
  ];

  const MUSICIAN_ID_TABLES = [
    'SepaBankAccounts' => 'musician_id',
    'SepaDebitMandates' => 'musician_id',
    'ProjectParticipants' => 'musician_id',
    'MusicianInstruments' => 'musician_id',
    'ProjectInstruments' => 'musician_id',
    // 'ProjectParticipantFieldsData' => 'musician_id', needs extra access controls
    'ProjectPayments' => 'musician_id',
    'CompositePayments' => 'musician_id',
    'MusicianPhoto' => 'owner_id',
    'EncryptedFileOwners' => 'musician_id',
  ];
  const PARTICIPANT_FIELD_ID_TABLES = [
    'ProjectParticipantFields' => [
      'joinField' => 'id',
      'groupBy' => [
        'id',
      ],
    ],
    'ProjectParticipantFieldsDataOptions' => [
      'joinField' => 'field_id',
      'groupBy' => [
        'field_id', 'key',
      ],
    ],
  ];
  const UNRESTRICTED_TABLES = [
    'Instruments',
    'InstrumentFamilies',
    'instrument_instrument_family',
    'TableFieldTranslations',
    'GeoContinents',
    'GeoCountries',
    'GeoPostalCodes',
    'GeoPostalCodeTranslations',
    'InsuranceBrokers',
    'InsuranceRates',
  ];

  const GRANT_SELECT = 'GRANT SELECT ON %1$s TO %2$s@\'localhost\'';
  const GRANT_FIELD_UPDATE = 'GRANT UPDATE (%3$s) ON %1$s TO %2$s@\'localhost\'';

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  /** @var IL10N */
  private $l;

  /** @var IConfig */
  private $cloudConfig;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var Connection */
  private $connection;

  /** @var IAppManager */
  private $appManager;

  /** @var string */
  private $appDbHost;

  /** @var string */
  private $appDbUser;

  /** @var string */
  private $appDbName;

  public function __construct(
    $appName
    , IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
    , IConfig $cloudConfig
    , EncryptionService $encryptionService
    , IAppManager $appManager
  ) {
    $this->appName = $appName;
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->cloudConfig = $cloudConfig;
    $this->appManager = $appManager;
    $this->encryptionService = $encryptionService;
    if ($this->encryptionService->bound()) {
      $this->connection = $this->appContainer->get(Connection::class);
      $this->appDbName = $this->encryptionService->getConfigValue('dbname');
      $this->appDbUser = $this->encryptionService->getConfigValue('dbuser');
      $this->appDbHost = $this->encryptionService->getConfigValue('dbserver');
    }
  }

  private function viewName(?string $dataBaseName, string $prefix, string $baseName):string
  {
    $viewName = $prefix . Util::dashesToCamelCase($baseName, true, '_') . self::VIEW_POSTFIX;
    if (!empty($dataBaseName)) {
      $viewName = $dataBaseName . '.' . $viewName;
    }
    return $viewName;
  }

  private function personalizedViewName(?string $dataBaseName, string $baseName)
  {
    return $this->viewName($dataBaseName, self::PERSONALIZED_PREFIX, $baseName);
  }

  private function checkAndGetCloudDbUser():string
  {
    $cloudDbHost = $this->cloudConfig->getSystemValue('dbhost');
    $cloudDbUser = $this->cloudConfig->getSystemValue('dbuser');

    if ($cloudDbHost !== $this->appDbHost) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Cloud database server "%s" and app database server "%s" must coincide.',  [ $cloudDbHost, $appDbHost ])
      );
    }

    return $cloudDbUser;
  }

  /**
   * Check the requirements for this affair, in particular whether the
   * user_sql backend is enabled, and return an error with status and hints.
   *
   * @return array
   */
  public function checkRequirements(?string $dataBaseName)
  {
    $status = self::REQUIREMENTS_OK;

    $hints = [];
    $userBackendEnabled = $this->appManager->isInstalled(self::CLOUD_USER_BACKEND);
    $userBackendRestrictions = $this->appManager->getAppRestriction(self::CLOUD_USER_BACKEND);
    if (!$userBackendEnabled) {
      $status = self::REQUIREMENTS_MISSING;
      $hints[] = $this->l->t('In order to be able to import the orchestra club-members as cloud-users the
"%1$s"-app needs to be enabled. Please ask the administrator of this cloud-instance to install and unconditionally enable this app.', self::CLOUD_USER_BACKEND);
    } else if (!empty($userBackendRestrictions)) {
      $status = self::REQUIREMENTS_MISSING;
      $hints[] = $this->l->t('The requird user-backend "%1$s" seems to be installed and enabled, however, the following app-restriction have been imposed on the app: "%2$s".', [ self::CLOUD_USER_BACKEND, implode(', ', $userBackendRestrictions), ]);
    }

    if (!empty($dataBaseName) && $dataBaseName != $this->appDbName) {
      $hints[] = $this->l->t(
        'Please make sure that the user "%1$s@%2$s" has all -- and in particular: GRANT -- privileges on the database "%3$s".', [
          $this->appDbUser, $this->appDbHost, $dataBaseName
        ]);
    }

    return [
      'status' => $status,
      'hints' => $hints,
    ];
  }

  /**
   * Return the group-id for the given numeric project-id. Note that the
   * display-name is just the project-name.
   *
   * @param int $projectId
   *
   * @return string
   */
  public function projectGroupId(int $projectId):string
  {
    return sprintf(self::GROUP_ID_PREFIX . '%1$s', $projectId, $this->appName);
  }

  /**
   * Update the views interacting with the Nextcloud user_sql backend and
   * update their grants.
   *
   * @param string|null $dataBaseName The name of the database where the views
   * will be created. The cafevdb database user must have GRANT rights on the
   * databse. If null the views are created in the standard databse.
   */
  public function updateUserSqlViews(?string $dataBaseName)
  {
    $cloudDbUser = $this->checkAndGetCloudDbUser();
    $currentStatement = null;
    try {
      foreach (self::USER_SQL_VIEWS as $name => $sql) {
        $viewName = $this->viewName($dataBaseName, self::USER_SQL_PREFIX, $name);
        $statements = [
          sprintf($sql, $viewName, $this->appName),
          sprintf(self::GRANT_SELECT, $viewName, $cloudDbUser),
        ];
        if ($name === 'User') {
          // allow changing the password from the cloud
          $statements[] = sprintf(self::GRANT_FIELD_UPDATE, $viewName, $cloudDbUser, 'password');
          // allow deactivation of users from the cloud
          $statements[] = sprintf(self::GRANT_FIELD_UPDATE, $viewName, $cloudDbUser, 'inactive');
        }
        foreach ($statements as $sql) {
          $currentStatement = $sql;
          $this->logDebug('SQL ' . $currentStatement);
          $this->connection->prepare($sql)->execute();
        }
      }
    } catch (\Throwable $t) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Unable to create or update the user-sql cloud-connector views: %s.', $currentStatement),
        $t->getCode(),
        $t
      );
    }
  }

  /**
   * Delete the user-sql views.
   *
   * @param string|null $dataBaseName The name of the database where the views
   * will be created. The cafevdb database user must have GRANT rights on the
   * databse. If null the views are created in the standard databse.
   */
  public function removeUserSqlViews(?string $dataBaseName)
  {
    $currentStatement = null;
    try {
      foreach (self::USER_SQL_VIEWS as $name => $sql) {
        $viewName = $this->viewName($dataBaseName, self::USER_SQL_PREFIX, $name);
        $currentStatement = sprintf('DROP VIEW IF EXISTS %1$s', $viewName);
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->execute();
      }
    } catch (\Throwable $t) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Unable to delete the user-sql cloud-connector views: %s.', $currentStatement),
        $t->getCode(),
        $t
      );
    }
  }

  /**
   * Generate a config array for the user_sql app "as of now".
   *
   * @bug Uses the internal structure of an app which is not under our
   * controle.
   */
  private function generateUserSqlConfig(?string $dataBaseName = null, bool $withDbAuth = true)
  {
    $cloudDbHost = $withDbAuth ? $this->cloudConfig->getSystemValue('dbhost') : '%system:dbhost%';
    $cloudDbUser = $withDbAuth ? $this->cloudConfig->getSystemValue('dbuser') : '%system:dbuser%';
    $cloudDbPass = $withDbAuth ? $this->cloudConfig->getSystemValue('dbpassword') : '%system:dbpassword%';

    // Just use Argon2
    $cryptoClass = \OCA\UserSQL\Crypto\CryptArgon2id::class;
    $cryptoThreads = max($this->cloudConfig->getSystemValueInt('hashingThreads', PASSWORD_ARGON2_DEFAULT_THREADS), 1);
    $cryptoMemoryCost = max($this->cloudConfig->getSystemValueInt('hashingMemoryCost', PASSWORD_ARGON2_DEFAULT_MEMORY_COST), $cryptoThreads * 8);
    $cryptoTimeCost = max($this->cloudConfig->getSystemValueInt('hashingTimeCost', PASSWORD_ARGON2_DEFAULT_TIME_COST), 1);

    $catchAllGroup = $this->encryptionService->getConfigValue('musiciansaddressbook');
    if (empty($catchAllGroup)) {
      $orchestraName = ucfirst($this->encryptionService->getConfigValue('orchestra'));
      $catchAllGroup = $orchestraName . ' ' . $this->l->t('Musicians');
    }

    return [
      'db.database' => $dataBaseName??$this->appDbName,
      'db.driver' => $this->connection->getDriver()->getDatabasePlatform()->getName(),
      'db.hostname' => $cloudDbHost,
      'db.password' => $cloudDbPass,
      'db.username' => $cloudDbUser,
      'db.ssl_ca' => null,
      'db.ssl_cert' => null,
      'db.ssl_key' => null,
      'db.table.group' => $this->viewName(null, self::USER_SQL_PREFIX, 'Group'),
      'db.table.group.column.admin' => 'is_admin',
      'db.table.group.column.gid' => 'gid',
      'db.table.group.column.name' => 'display_name',
      'db.table.user' => $this->viewName(null, self::USER_SQL_PREFIX, 'User'),
      'db.table.user.column.active' => 'inactive',
      'opt.reverse_active' => true,
      'db.table.user.column.avatar' => null,
      'db.table.user.column.disabled' => 'disabled',
      'db.table.user.column.email' => 'email',
      'db.table.user.column.home' => 'home',
      'db.table.user.column.name' => 'name',
      'db.table.user.column.password' => 'password',
      'db.table.user.column.quota' => 'quota',
      'db.table.user.column.salt' => null,
      'db.table.user.column.uid' => 'uid',
      'db.table.user.column.username' => 'username',
      'db.table.user_group' => $this->viewName(null, self::USER_SQL_PREFIX, 'UserGroup'),
      'db.table.user_group.column.gid' => 'gid',
      'db.table.user_group.column.uid' => 'uid',
      'opt.case_insensitive_username' => true,
      'opt.password_change' => true,
      'opt.crypto_class' => $cryptoClass,
      'opt.crypto_param_0' => $cryptoMemoryCost,
      'opt.crypto_param_1' => $cryptoTimeCost,
      'opt.crypto_param_2' => $cryptoThreads,
      'opt.default_group' => null,
      'opt.email_login' => true,
      'opt.email_sync' => 'force_sql',
      'opt.home_location' => null,
      'opt.home_mode' => null,
      'opt.name_sync' => 'force_sql',
      'opt.provide_avatar' => true,
      'opt.quota_sync' => null,
      'opt.safe_store' => false,
      'opt.use_cache' => true,
      'opt.name_change' => false,
      'opt.default_group' => $catchAllGroup,
    ];
  }

  /**
   * For the moment this just fills in our own app-config. Idea is to have the
   * admin-settings actually flush this data to the config space, either
   * directly or by using a call to set settings route of the user_sql app.
   */
  public function writeUserSqlConfig(?string $dataBaseName = null, bool $delete = false)
  {
    $config = $this->generateUserSqlConfig($dataBaseName, withDbAuth: false);
    if ($delete) {
      foreach (array_keys($config) as $key) {
        $this->cloudConfig->deleteAppValue($this->appName, self::CLOUD_USER_BACKEND . ':' . $key);
      }
    } else {
      foreach ($config as $key => $value) {
        $this->cloudConfig->setAppValue($this->appName, self::CLOUD_USER_BACKEND . ':' . $key, $value);
      }
    }
  }

  /**
   * Hijack the user-sql backend by flushing pre-computed values into its
   * config-space. This variant uses the routes of the user_sql app. Hence it
   * will only work if the logged-in user is allowed to write to the user-sql
   * config space.
   */
  public function configureCloudUserBackend(bool $erase = false)
  {
    /** @var RequestService $requestService */
    $requestService = $this->appContainer->get(RequestService::class);

    $configKeys = $this->cloudConfig->getAppKeys($this->appName);
    $prefix = self::CLOUD_USER_BACKEND . ':';
    $prefixLen = strlen($prefix);
    $cloudUserBackendKeys = array_map(function($key) use ($prefixLen) {
      return substr($key, $prefixLen);
    }, array_filter($configKeys, function($key) use ($prefix) {
      return str_starts_with($key, $prefix);
    }));

    $this->logDebug('USER SQL KEYS ' . print_r($cloudUserBackendKeys, true));

    $cloudUserBackendParams = [];
    foreach ($cloudUserBackendKeys as $cloudUserBackendKey) {
      $cloudUserBackendValue = $erase ? '' : $this->cloudConfig->getAppValue($this->appName, $prefix . $cloudUserBackendKey);
      if (preg_match('/%system:(\w+)%/', $cloudUserBackendValue, $matches)) {
        $cloudUserBackendValue = $this->cloudConfig->getSystemValue($matches[1]);
      }
      // $this->cloudConfig->setAppValue(self::CLOUD_USER_BACKEND, $cloudUserBackendKey, $cloudUserBackendValue);
      $cloudUserBackendParams[str_replace('.', '-', $cloudUserBackendKey)] = $cloudUserBackendValue;
    }
    $this->logInfo('USER SQL POST PARAMS ' . print_r($cloudUserBackendParams, true));

    $messages = [];

    /** @var RequestService $requestService */
    $requestService = $this->appContainer->get(RequestService::class);

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache($requestService, $messages);

    $route = implode('.', [
      self::CLOUD_USER_BACKEND,
      'settings',
      'saveProperties',
    ]);
    $result = $requestService->postToRoute($route, postData: $cloudUserBackendParams, type: RequestService::URL_ENCODED);
    $messages[] = $result['message']??$this->l->t('"%s" configuration may have succeeded.', self::CLOUD_USER_BACKEND);

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache($requestService, $messages);

    return $messages;
  }

  /**
   * Clear the backend cache, for use in controllers, back-reportings messages.
   */
  private function clearUserBackendCache(?RequestService $requestService = null, ?array &$messages = null)
  {
    // /** @var RequestService $requestService */
    // $requestService = $this->appContainer->get(RequestService::class);
    // $route = implode('.', [
    //   self::CLOUD_USER_BACKEND,
    //   'settings',
    //   'clearCache',
    // ]);
    // try {
    //   $result = $requestService->postToRoute($route);
    //   $messages[] = $result['message']??$this->l->t('Clearing "%s"\'s cache may have succeeded.', self::CLOUD_USER_BACKEND);
    // } catch (\Throwable $t) {
    //   // essentially ignore ...
    //   $this->logError($t);
    //   $messages[] = $this->l->t('An attempt to clear the cache of the "%1$s"-app has failed: %2$s.', [
    //     self::CLOUD_USER_BACKEND,
    //     $t->getMessage(),
    //   ]);
    // }
    $messages = $messages ?? [];
    /** @var \OCA\UserSQL\Cache $userBackendCache */
    $userBackendCache = $this->appContainer->get(\OCA\UserSQL\Cache::class);
    $userBackendCache->clear();
  }

  /**
   * In particular flush potential data-caches after changing data of
   * the orchestra app.
   *
   * @return array<int, string> Diagnostic messages.
   */
  public function synchronizeCloud()
  {
    $this->clearUserBackendCache();
  }

  public function setCloudUserSubAdmins(bool $delete = false)
  {
    /** @var ConfigService $configService */
    $configService = $this->appContainer->get(ConfigService::class);

    // finally add all sub-admins of the orchestra group to the catch-all-group of the backend
    $subAdmins = $configService->getGroupSubAdmins();
    $catchAllGroup = $configService->getGroup(self::CLOUD_USER_BACKEND); // same name as backend
    if (!empty($catchAllGroup)) {
      $subAdminManager = $configService->getSubAdminManager();
      foreach ($subAdmins as $subAdmin) {
        $isSubAdmin = $subAdminManager->isSubAdminOfGroup($subAdmin, $catchAllGroup);
        if ($delete && $isSubAdmin) {
          $configService->getSubAdminManager()->deleteSubAdmin($subAdmin, $catchAllGroup);
        } else if (!($delete || $isSubAdmin)) {
          $configService->getSubAdminManager()->createSubAdmin($subAdmin, $catchAllGroup);
        }
      }
    }
  }

  /** Check for cached cloud user-backend config */
  public function haveCloudUserBackendConfig()
  {
    return !empty(array_filter(
      $this->cloudConfig->getAppKeys($this->appName),
      function($value) {
        return str_starts_with($value, self::CLOUD_USER_BACKEND . ':');
      }));
  }

  /**
   * Generate the (My-)SQL statements for defining the personalized single-row
   * musician views.
   *
   * @return array<string, string>
   * ```
   * [
   *   VIEWNAME => SQL_STATEMENT
   * ]
   * ```
   */
  private function generateMusicianPersonalizedViewsStatements(?string $dataBaseName)
  {
    $statements = [];

    // fetch the authorized musician-id from the token table by examining the secret.
    $accessFunction = 'ROW_ACCESS_ID';
    if (!empty($dataBaseName)) {
      $accessFunction = $dataBaseName . '.' . $accessFunction;
    }

    $statements[$accessFunction] = "CREATE OR REPLACE FUNCTION " . $accessFunction . "() RETURNS INT(11)
    READS SQL DATA
    SQL SECURITY DEFINER
BEGIN
  DECLARE musician_id INT;
  SET musician_id = 0;
  SELECT t.musician_id INTO musician_id FROM `" . $this->appDbName . "`.MusicianRowAccessTokens t WHERE t.user_id = @CLOUD_USER_ID AND t.access_token_hash = @ROW_ACCESS_TOKEN;
  RETURN musician_id;
END";

    $accessFunction .= '()';

    $musicianViewName = $this->personalizedViewName($dataBaseName, 'Musicians');
    $statements[$musicianViewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $musicianViewName . "
AS
SELECT *
FROM Musicians m
WHERE m.id = " . $accessFunction;

    foreach (self::MUSICIAN_ID_TABLES as $table => $column) {
      $viewName = $this->personalizedViewName($dataBaseName, $table);
      $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* FROM " . $table . " t
    WHERE t." . $column . " = " . $accessFunction;
    }

    $memberProjectId = $this->encryptionService->getConfigValue('memberProjectId', -1);
    $executiveBoardProjectId = $this->encryptionService->getConfigValue('executiveBoardProjectId', -1);

    $table = 'Projects';
    $column = 'id';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*,
  (t.id = " . $memberProjectId . ") AS club_members,
  (t.id = " . $executiveBoardProjectId . ") AS executive_board
  FROM " . $this->personalizedViewName($dataBaseName, 'ProjectParticipants') . " pppv
  INNER JOIN " . $table . " t
    ON t." . $column . " = pppv.project_id
  GROUP BY t.id";

    $table = 'ProjectParticipantFieldsData';
    $column = 'musician_id';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* FROM " . $table . " t
    INNER JOIN ProjectParticipantFields ppf
      ON t.field_id = ppf.id AND ppf.participant_access <> 0
    WHERE t." . $column . " = " . $accessFunction;

    foreach (self::PARTICIPANT_FIELD_ID_TABLES as $table => $joinInfo) {
      $viewName = $this->personalizedViewName($dataBaseName, $table);
      $statement = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->personalizedViewName($dataBaseName, 'ProjectParticipantFieldsData'). " pppfdv
  INNER JOIN " . $table . " t
    ON t." . $joinInfo['joinField'] . " = pppfdv.field_id
  GROUP BY " . implode(', ', array_map(fn($field) => 't.' . $field, $joinInfo['groupBy']));
      $statements[$viewName] = $statement;
    }

    $table = 'InstrumentInsurances';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*,
  at.musician_id AS musician_id,
  (at.musician_id = t.bill_to_party_id) AS is_debitor,
  (at.musician_id = t.instrument_holder_id) AS is_holder
  FROM (SELECT " . $accessFunction . " AS musician_id) at
  INNER JOIN " . $table . " t
    ON t.instrument_holder_id = at.musician_id OR t.bill_to_party_id = musician_id";

    $table = 'Files';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $table . " t
  WHERE t.id in (
    SELECT efov.encrypted_file_id AS file_id FROM " . $this->personalizedViewName($dataBaseName, 'EncryptedFileOwners') . " efov
      UNION
    SELECT mpv.image_id AS file_id FROM " . $this->personalizedViewName($dataBaseName, 'MusicianPhoto') . " mpv)";

    $table = 'FileData';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->personalizedViewName($dataBaseName, 'Files'). " fv
  INNER JOIN " . $table . " t
    ON t.file_id = fv.id";

    foreach (self::UNRESTRICTED_TABLES as $table) {
      $viewName = $this->personalizedViewName($dataBaseName, $table);
      $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* FROM " . $table . " t";
    }
    return $statements;
  }

  /**
   * Update the personalized one-row views which give individual orchestra
   * members access to just their own data.
   *
   * @param string|null $dataBaseName The name of the database where the views
   * will be created. The cafevdb database user must have GRANT rights on the
   * databse. If null the views are created in the standard databse.
   */
  public function updateMusicianPersonalizedViews(?string $dataBaseName)
  {
    $statements = $this->generateMusicianPersonalizedViewsStatements($dataBaseName);

    $cloudDbUser = $this->checkAndGetCloudDbUser();
    $currentStatement = null;
    try {
      foreach ($statements as $viewName => $statement) {
        $currentStatement = $statement;
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->execute();
        if (strpos($statement, 'FUNCTION') !== false) {
          $currentStatement = "GRANT EXECUTE ON FUNCTION " . $viewName . " TO " . $cloudDbUser . "@'localhost'";
        } else {
          $currentStatement = sprintf(self::GRANT_SELECT, $viewName, $cloudDbUser);
        }
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->execute();
      }
    } catch (\Throwable $t) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Unable to create or update the personalized view: %s.', $currentStatement),
        $t->getCode(),
        $t
      );
    }
  }

  /**
   * Delete the personalized one-row views which give individual orchestra
   * members access to just their own data.
   *
   * @param string|null $dataBaseName The name of the database where the views
   * will be created. The cafevdb database user must have GRANT rights on the
   * databse. If null the views are created in the standard databse.
   */
  public function removeMusicianPersonalizedViews(?string $dataBaseName)
  {
    $statements = $this->generateMusicianPersonalizedViewsStatements($dataBaseName);

    $currentStatement = null;
    try {
      foreach ($statements as $viewName => $sql) {
        if (strpos($sql, 'FUNCTION') !== false) {
          $currentStatement = sprintf('DROP FUNCTION IF EXISTS %1$s', $viewName);
        } else {
          $currentStatement = sprintf('DROP VIEW IF EXISTS %1$s', $viewName);
        }
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->execute();
      }
    } catch (\Throwable $t) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Unable to delete personalized view: %s.', $currentStatement),
        $t->getCode(),
        $t
      );
    }
  }

};
