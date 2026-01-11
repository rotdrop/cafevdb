<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2026 Claus-Justus Heine
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


use ReflectionClass;
use Throwable;
use UnexpectedValueException;

use OCP\AppFramework\IAppContainer;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Database\Constants as DBConstants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Toolkit\Service\RequestService;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\DriverException as DBALDriverException;

/**
 * Manage database-views and grants in order to selectively provide only the
 * neccessary information to the ambient cloud system.
 *
 * Integrates with the cafevdbmembers and the user_sql app.
 */
class CloudUserConnectorService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const REQUIREMENTS_OK = true;
  const REQUIREMENTS_MISSING = false;

  const CLOUD_USER_BACKEND = 'user_sql';
  const CLOUD_USER_GROUP_ID = self::CLOUD_USER_BACKEND;

  const VIEW_POSTFIX = 'View';

  const USER_SQL_PREFIX = 'Nextcloud';
  const PERSONALIZED_PREFIX = 'Personalized';

  const GROUP_ID_SEPARATOR = '_'; // more or less the only unsuspicies character ...

  const GROUP_ID_PREFIX = '%2$s' . self::GROUP_ID_SEPARATOR;

  private const CREATE_FUNCTION_PREFIX = 'CREATE OR REPLACE FUNCTION';

  private const CREATE_FUNCTION_REGEXP = '/^' . self::CREATE_FUNCTION_PREFIX . '/';

  private const CREATE_VIEW_REGEXP = '/^CREATE OR REPLACE.*VIEW/s';

  private const CHECK_OPTION_ON_NON_UPDATABLE_VIEW_ERROR = 1368;

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
SELECT CONVERT((CONCAT(_ascii "' . self::GROUP_ID_PREFIX. '" , p.id) COLLATE ascii_bin) USING ' . DBConstants::CHARACTER_SET . ') AS gid,
       p.name AS display_name,
       0 AS is_admin
FROM Projects p
WHERE p.type IN ("temporary", "permanent") AND p.deleted IS NULL
';

  const USER_SQL_USER_GROUP_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT CONVERT(m.user_id_slug USING ' . DBConstants::CHARACTER_SET . ') AS uid,
       CONVERT((CONCAT(_ascii "%2$s' . self::GROUP_ID_SEPARATOR . '", p.id) COLLATE ascii_bin) USING ' . DBConstants::CHARACTER_SET . ') AS gid
FROM ProjectParticipants pp
LEFT JOIN Musicians m ON m.id = pp.musician_id
LEFT JOIN Projects p ON p.id = pp.project_id
WHERE pp.deleted IS NULL
';

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
SELECT CONVERT(m.user_id_slug USING ' . DBConstants::CHARACTER_SET . ') AS uid,
       m.user_passphrase AS password,
       CONCAT_WS(" ", IF(m.nick_name IS NULL
                         OR m.nick_name = "", m.first_name, m.nick_name), m.sur_name) AS name,
       CONVERT(m.email USING ' . DBConstants::CHARACTER_SET . ') AS email,
       NULL AS quota,
       NULL AS home,
       COALESCE(m.cloud_account_deactivated, 0) AS inactive,
       IF(m.deleted IS NOT NULL OR m.cloud_account_disabled = 1, 1, 0) AS disabled,
       1 AS avatar,
       NULL AS salt
FROM Musicians m
WHERE m.email IS NOT NULL AND m.email <> ""
';

  const USER_SQL_VIEWS = [
    'User' => self::USER_SQL_USER_VIEW,
    'Group' => self::USER_SQL_GROUP_VIEW,
    'UserGroup' => self::USER_SQL_USER_GROUP_VIEW,
  ];

  const MUSICIAN_ID_TABLES = [
    'SepaBankAccounts' => 'musician_id',
    'SepaDebitMandates' => 'musician_id',
    // 'MusicianRowAccessTokens' => 'musician_id',
    // 'ProjectApplications' => 'musician_id',
    'ProjectParticipants' => 'musician_id',
    'MusicianInstruments' => 'musician_id',
    'ProjectInstruments' => 'musician_id',
    // 'ProjectParticipantFieldsData' => 'musician_id', needs extra access controls
    'ProjectPayments' => 'musician_id',
    'CompositePayments' => 'musician_id',
    'EncryptedFileOwners' => 'musician_id',
    'MusicianEmailAddresses' => 'musician_id',
  ];

  const UNRESTRICTED_TABLES = [
    'Instruments',
    'InstrumentFamilies',
    'instrument_instrument_family',
    'GeoContinents',
    'GeoCountries',
    'GeoPostalCodes',
    'GeoPostalCodeTranslations',
    'InsuranceBrokers',
    'InsuranceRates',
    'ProjectInstrumentationNumbers',
    'TableFieldTranslations',
  ];

  const GRANT_EXECUTE = 'GRANT EXECUTE ON FUNCTION %1$s TO %2$s@\'localhost\'';
  const REVOKE_EXECUTE = 'REVOKE EXECUTE ON FUNCTION %1$s FROM %2$s@\'localhost\'';
  const GRANT_INSERT = 'GRANT INSERT ON %1$s TO %2$s@\'localhost\'';
  const REVOKE_INSERT = 'REVOKE INSERT ON %1$s FROM %2$s@\'localhost\'';
  const GRANT_SELECT = 'GRANT SELECT ON %1$s TO %2$s@\'localhost\'';
  const REVOKE_SELECT = 'REVOKE SELECT ON %1$s FROM %2$s@\'localhost\'';
  const GRANT_FIELD_UPDATE = 'GRANT UPDATE (%3$s) ON %1$s TO %2$s@\'localhost\'';
  const REVOKE_FIELD_UPDATE = 'REVOKE IF EXISTS UPDATE (%3$s) ON %1$s FROM %2$s@\'localhost\'';

  const PRIVILEGES = [
    'ProjectApplications' => [
      self::GRANT_INSERT => true,
      self::GRANT_FIELD_UPDATE => [
        'password_hash',
        'musician_id',
        'data',
        'created',
        'updated',
        'deleted',
      ],
    ],
  ];

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
    private string $appName,
    protected IAppContainer $appContainer,
    protected ILogger $logger,
    private IL10N $l,
    private IConfig $cloudConfig,
    private EncryptionService $encryptionService,
    private IAppManager $appManager,
  ) {
    if ($this->encryptionService->bound()) {
      $this->connection = $this->appContainer->get(Connection::class);
      $this->appDbName = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_NAME);
      $this->appDbUser = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_USER);
      $this->appDbHost = $this->encryptionService->getConfigValue(ConfigConstants::APP_DB_SERVER);
    }
  }
  // phpcs:enable

  /**
   * @param null|string $dataBaseName
   *
   * @param string $prefix
   *
   * @param string $baseName
   *
   * @return string
   */
  private function viewName(?string $dataBaseName, string $prefix, string $baseName):string
  {
    $viewName = $prefix . Util::dashesToCamelCase($baseName, true, '_') . self::VIEW_POSTFIX;
    if (!empty($dataBaseName)) {
      $viewName = $dataBaseName . '.' . $viewName;
    }
    return $viewName;
  }

  /**
   * @param null|string $dataBaseName
   *
   * @param string $baseName
   *
   * @return string
   */
  private function personalizedViewName(?string $dataBaseName, string $baseName):string
  {
    return $this->viewName($dataBaseName, self::PERSONALIZED_PREFIX, $baseName);
  }

  /**
   * @return string
   *
   * @throws Exceptions\DatabaseCloudConnectorViewException
   */
  private function checkAndGetCloudDbUser():string
  {
    $cloudDbHost = $this->cloudConfig->getSystemValue('dbhost');
    $cloudDbUser = $this->cloudConfig->getSystemValue(ConfigConstants::APP_DB_USER);

    if ($cloudDbHost !== $this->appDbHost) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Cloud database server "%s" and app database server "%s" must coincide.', [ $cloudDbHost, $this->appDbHost ])
      );
    }

    return $cloudDbUser;
  }

  /**
   * Check the requirements for this affair, in particular whether the
   * user_sql backend is enabled, and return an error with status and hints.
   *
   * @param null|string $dataBaseName
   *
   * @return array
   * ```
   * [
   *   'status' => true/false,
   *   'hints' => [ HINT0, HINT1, ... ],
   * ]
   * ```
   */
  public function checkRequirements(?string $dataBaseName):array
  {
    $status = self::REQUIREMENTS_OK;

    $hints = [];
    $userBackendEnabled = $this->appManager->isInstalled(self::CLOUD_USER_BACKEND);
    $userBackendRestrictions = $this->appManager->getAppRestriction(self::CLOUD_USER_BACKEND);
    if (!$userBackendEnabled) {
      $status = self::REQUIREMENTS_MISSING;
      $hints[] = $this->l->t('In order to be able to import the orchestra club-members as cloud-users the
"%1$s"-app needs to be enabled. Please ask the administrator of this cloud-instance to install and unconditionally enable this app.', self::CLOUD_USER_BACKEND);
    } elseif (!empty($userBackendRestrictions)) {
      $status = self::REQUIREMENTS_MISSING;
      $hints[] = $this->l->t(
        'The requird user-backend "%1$s" seems to be installed and enabled,'
        . ' however, the following app-restriction have been imposed on the app: "%2$s".', [
          self::CLOUD_USER_BACKEND, implode(', ', $userBackendRestrictions),
        ]);
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
   * databse. If null the views are created in the standard database.
   *
   * @return void
   *
   * @throws Exceptions\DatabaseCloudConnectorViewException
  */
  public function updateUserSqlViews(?string $dataBaseName):void
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
          if (str_starts_with($sql, 'CREATE')) {
            try {
              $this->connection->prepare($sql . ' WITH CHECK OPTION')->executeQuery();
            } catch (DBALDriverException $e) {
              if ($e->getCode() != self::CHECK_OPTION_ON_NON_UPDATABLE_VIEW_ERROR) {
                throw $e;
              }
              $this->connection->prepare($sql)->executeQuery();
            }
          } else {
            $this->connection->prepare($sql)->executeQuery();
          }
        }
      }
    } catch (Throwable $t) {
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
   * databse. If null the views are created in the standard database.
   *
   * @return void
   */
  public function removeUserSqlViews(?string $dataBaseName):void
  {
    $currentStatement = null;
    try {
      foreach (array_keys(self::USER_SQL_VIEWS) as $name) {
        $viewName = $this->viewName($dataBaseName, self::USER_SQL_PREFIX, $name);
        $currentStatement = sprintf('DROP VIEW IF EXISTS %1$s', $viewName);
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->executeQuery();
      }
    } catch (Throwable $t) {
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
   * @param null|string $dataBaseName
   *
   * @param bool $withDbAuth
   *
   * @return array
   *
   * @bug Uses the internal structure of an app which is not under our
   * control.
   *
   * @throw UnexpectedValueException if the current database platform is neither MySQL nor PostrgreSQL.
   */
  private function generateUserSqlConfig(?string $dataBaseName = null, bool $withDbAuth = true):array
  {
    $cloudDbHost = $withDbAuth ? $this->cloudConfig->getSystemValue('dbhost') : '%system:dbhost%';
    $cloudDbUser = $withDbAuth ? $this->cloudConfig->getSystemValue(ConfigConstants::APP_DB_USER) : '%system:dbuser%';
    $cloudDbPass = $withDbAuth ? $this->cloudConfig->getSystemValue(ConfigConstants::APP_DB_PASSWORD) : '%system:dbpassword%';

    // Just use Argon2
    $cryptoClass = \OCA\UserSQL\Crypto\CryptArgon2id::class;
    $cryptoThreads = max($this->cloudConfig->getSystemValueInt('hashingThreads', PASSWORD_ARGON2_DEFAULT_THREADS), 1);
    $cryptoMemoryCost = max($this->cloudConfig->getSystemValueInt('hashingMemoryCost', PASSWORD_ARGON2_DEFAULT_MEMORY_COST), $cryptoThreads * 8);
    $cryptoTimeCost = max($this->cloudConfig->getSystemValueInt('hashingTimeCost', PASSWORD_ARGON2_DEFAULT_TIME_COST), 1);

    $catchAllGroup = $this->encryptionService->getConfigValue(ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY);
    if (empty($catchAllGroup)) {
      $orchestraName = ucfirst($this->encryptionService->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY));
      $catchAllGroup = $orchestraName . ' ' . $this->l->t('Musicians');
    }

    $platform = $this->connection->getDatabasePlatform();
    if ($platform instanceof Platforms\AbstractMySQLPlatform) {
      $driver = 'mysql';
    } elseif ($platform instanceof Platforms\PostgreSQLPlatform) {
      $driver = 'pgsql';
    } else {
      throw new UnexpectedValueException(
        $this->l->t(
          'Only MySQL and PostgreSQL are supported, but the database platform in use is "%s".',
          new ReflectionClass($platform)->getShortName(),
        )
      );
    }

    return [
      'db.database' => $dataBaseName ?? $this->appDbName,
      'db.driver' => $driver,
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
      'db.table.user.column.avatar' => 'avatar',
      'db.table.user.column.disabled' => 'disabled',
      'db.table.user.column.email' => 'email',
      'db.table.user.column.home' => 'home',
      'db.table.user.column.name' => 'name',
      'db.table.user.column.password' => 'password',
      'db.table.user.column.quota' => 'quota',
      'db.table.user.column.salt' => null,
      'db.table.user.column.uid' => 'uid',
      'db.table.user.column.username' => null,
      'db.table.user_group' => $this->viewName(null, self::USER_SQL_PREFIX, 'UserGroup'),
      'db.table.user_group.column.gid' => 'gid',
      'db.table.user_group.column.uid' => 'uid',
      'opt.case_insensitive_username' => true,
      'opt.password_change' => true,
      'opt.crypto_class' => $cryptoClass,
      'opt.crypto_param_0' => $cryptoMemoryCost,
      'opt.crypto_param_1' => $cryptoTimeCost,
      'opt.crypto_param_2' => $cryptoThreads,
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
   *
   * @param null|string $dataBaseName
   *
   * @param bool $delete
   *
   * @return void
   */
  public function writeUserSqlConfig(?string $dataBaseName = null, bool $delete = false):void
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
   *
   * @param bool $erase
   *
   * @return array
   */
  public function configureCloudUserBackend(bool $erase = false):array
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
    // $this->logInfo('USER SQL POST PARAMS ' . print_r($cloudUserBackendParams, true));

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache();

    $messages = [];

    /** @var RequestService $requestService */
    $requestService = $this->appContainer->get(RequestService::class);

    $route = implode('.', [
      self::CLOUD_USER_BACKEND,
      'settings',
      'saveProperties',
    ]);
    $result = $requestService->postToRoute($route, requestData: $cloudUserBackendParams, postType: RequestService::URL_ENCODED);
    $messages[] = $result['message'] ?? $this->l->t('"%s" configuration may have succeeded.', self::CLOUD_USER_BACKEND);

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache();

    return $messages;
  }

  /**
   * Clear the backend cache, for use in controllers, back-reportings messages.
   *
   * @param null|RequestService $requestService
   *
   * @param null|array $messages
   *
   * @return void
   */
  private function clearUserBackendCache():void
  {
    /** @var \OCA\UserSQL\Cache $userBackendCache */
    $userBackendCache = $this->appContainer->get(\OCA\UserSQL\Cache::class);
    $userBackendCache->clear();
  }

  /**
   * In particular flush potential data-caches after changing data of
   * the orchestra app.
   *
   * @return void
   */
  public function synchronizeCloud():void
  {
    $this->clearUserBackendCache();
  }

  /**
   * @param bool $delete
   *
   * @return void
   */
  public function setCloudUserSubAdmins(bool $delete = false):void
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
        } elseif (!($delete || $isSubAdmin)) {
          $configService->getSubAdminManager()->createSubAdmin($subAdmin, $catchAllGroup);
        }
      }
    }
  }

  /** @return bool Check for cached cloud user-backend config */
  public function haveCloudUserBackendConfig():bool
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
   * @param null|string $dataBaseName
   *
   * @return array<string, string>
   * ```
   * [
   *   VIEWNAME => SQL_STATEMENT
   * ]
   * ```
   */
  private function generateMusicianPersonalizedViewsStatements(?string $dataBaseName):array
  {
    $functionPrefix = empty($dataBaseName) ? '' : $dataBaseName . '.';
    $functions = [
      'ROW_ACCESS_TOKEN' => "()
  RETURNS CHAR(128) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN @" . Constants::SQL_ROW_ACCESS_TOKEN . ";
END",
      'PROJECT_APPLICATION_ROW_ACCESS_TOKEN' => "()
  RETURNS CHAR(128) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN @" . Constants::SQL_PROJECT_APPLICATION_ROW_ACCESS_TOKEN . ";
END",
      'CLOUD_USER_ID' => "()
  RETURNS VARCHAR(256) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN @" . Constants::SQL_CLOUD_USER_ID . ";
END",
      'CLOUD_USER_MUSICIAN_ID' => "()
  RETURNS INT(11)
  READS SQL DATA
  SQL SECURITY DEFINER
BEGIN
  DECLARE musician_id INT;
  SET musician_id = 0;
  SELECT t.musician_id INTO musician_id FROM
      `" . $this->appDbName . "`.MusicianRowAccessTokens t
  WHERE
    (t.user_id = " . $functionPrefix . "CLOUD_USER_ID()
      AND t.access_token_hash = " . $functionPrefix . "ROW_ACCESS_TOKEN());
  RETURN musician_id;
END",
      'PROJECT_APPLICATION_PROJECT_NAME' => "()
  RETURNS VARCHAR(1024) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN @" . Constants::SQL_PROJECT_APPLICATION_PROJECT_NAME . ";
END",
      'PROJECT_APPLICATION_PROJECT_ID' => "()
  RETURNS INT(11)
  DETERMINISTIC
  READS SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE project_id INT;
  SET project_id = 0;
  SELECT p.id INTO project_id FROM
    `" . $this->appDbName . "`.Projects p
  WHERE
    p.name = " . $functionPrefix . "PROJECT_APPLICATION_PROJECT_NAME();
  RETURN project_id;
END",
      'PROJECT_APPLICATION_SHARE_TOKENS' => "()
  RETURNS VARCHAR(1024) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN @" . Constants::SQL_PROJECT_APPLICATION_SHARE_TOKENS . ";
END",
      'PROJECT_APPLICATION_MUSICIAN_ID' => "()
  RETURNS INT(11)
  READS SQL DATA
  SQL SECURITY DEFINER
BEGIN
  DECLARE musician_id INT;
  SET musician_id = 0;
  SELECT t.musician_id INTO musician_id FROM
      `" . $this->appDbName . "`.ProjectApplications t
    WHERE
      (FIND_IN_SET(SHA2(t.email, 256), " . $functionPrefix . "PROJECT_APPLICATION_SHARE_TOKENS()) > 0
        AND t.password_hash = " . $functionPrefix . "PROJECT_APPLICATION_ROW_ACCESS_TOKEN());
  RETURN musician_id;
END",
      'AUTHORIZED_MUSICIAN_ID' => "()
  RETURNS INT(11)
  READS SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE musician_id INT;
  SET musician_id = " . $functionPrefix . "CLOUD_USER_MUSICIAN_ID();
  IF musician_id > 0 THEN
    RETURN musician_id;
  END IF;
  RETURN " . $functionPrefix . "PROJECT_APPLICATION_MUSICIAN_ID();
END",
      'BIN_TO_UUID' => "(`b` BINARY(16), `f` BOOLEAN)
  RETURNS CHAR(36) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  DECLARE hexStr CHAR(32);
  SET hexStr = HEX(b);
  RETURN LOWER(CONCAT(
           IF(f,SUBSTR(hexStr, 9, 8),SUBSTR(hexStr, 1, 8)), '-',
           IF(f,SUBSTR(hexStr, 5, 4),SUBSTR(hexStr, 9, 4)), '-',
           IF(f,SUBSTR(hexStr, 1, 4),SUBSTR(hexStr, 13, 4)), '-',
           SUBSTR(hexStr, 17, 4), '-',
           SUBSTR(hexStr, 21)
        ));
END",
      'BIN2UUID' => "(`b` BINARY(16))
  RETURNS CHAR(36) CHARSET ascii
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN BIN_TO_UUID(b, 0);
END",
      'UUID_TO_BIN' => "(`uuid` CHAR(36), `f` BOOLEAN)
  RETURNS BINARY(16)
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN UNHEX(CONCAT(
  IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
  SUBSTRING(uuid, 10, 4),
  IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
  SUBSTRING(uuid, 20, 4),
  SUBSTRING(uuid, 25))
  );
END",
      'UUID2BIN' => "(`uuid` CHAR(36))
  RETURNS BINARY(16)
  DETERMINISTIC
  NO SQL
  SQL SECURITY INVOKER
BEGIN
  RETURN UUID_TO_BIN(uuid, 0);
END",
    ];

    $statements = [];

    // fetch the authorized musician-id from the token table by examining the secret.
    $accessFunction = $functionPrefix . 'AUTHORIZED_MUSICIAN_ID' . '()';

    foreach ($functions as $name => $definition) {
      $statements[$functionPrefix . $name] = self::CREATE_FUNCTION_PREFIX . " " . $functionPrefix . $name . $definition;
    }

    $cloudDbUser = $this->checkAndGetCloudDbUser();

    $musicianViewName = $this->personalizedViewName($dataBaseName, 'Musicians');
    $statements[$musicianViewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $musicianViewName . "
AS
SELECT *
FROM Musicians m
WHERE m.id = " . $accessFunction;

    // Grant access to the one row of the row access tokens table
    $tableName = 'MusicianRowAccessTokens';
    $viewName = $this->personalizedViewName($dataBaseName, $tableName);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT *
FROM " . $tableName . " t
WHERE t.access_token_hash = " . $functionPrefix . "ROW_ACCESS_TOKEN()
  AND t.user_id = " . $functionPrefix . "CLOUD_USER_ID()";

    // Grant access to all relevant rows in the project application
    // table. Grant access also if there is a related user account and the
    // user is logged in. The fancy FIND_IN_SET() is there to handle the case
    // of email aliases resp. multiple difference emails.
    $tableName = 'ProjectApplications';
    $viewName = $this->personalizedViewName($dataBaseName, $tableName);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
FROM " . $tableName . " t
WHERE
  (FIND_IN_SET(SHA2(t.email, 256), " . $functionPrefix . "PROJECT_APPLICATION_SHARE_TOKENS()) > 0
    AND t.project_id = " . $functionPrefix . "PROJECT_APPLICATION_PROJECT_ID())
    OR t.musician_id = " . $functionPrefix . "CLOUD_USER_MUSICIAN_ID()";
    foreach (self::PRIVILEGES[$tableName] as $privilege => $columns) {
      if (is_array($columns)) {
        foreach ($columns as $column) {
          $statements[] = sprintf($privilege, $viewName, $cloudDbUser, $column);
        }
      } else {
        $statements[] = sprintf($privilege, $viewName, $cloudDbUser);
      }
    }

    foreach (self::MUSICIAN_ID_TABLES as $table => $column) {
      $viewName = $this->personalizedViewName($dataBaseName, $table);
      $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* FROM " . $table . " t
    WHERE t." . $column . " = " . $accessFunction;
      foreach ((self::PRIVILEGES[$table] ?? []) as $privilege => $columns) {
        if (is_array($columns)) {
          foreach ($columns as $column) {
            $statements[] = sprintf($privilege, $viewName, $cloudDbUser, $column);
          }
        } else {
          $statements[] = sprintf($privilege, $viewName, $cloudDbUser);
        }
      }
    }

    $memberProjectId = $this->encryptionService->getConfigValue('memberProjectId', -1);
    $executiveBoardProjectId = $this->encryptionService->getConfigValue('executiveBoardProjectId', -1);

    // for the sake of the project-registration page all projects are
    // exported, they are not so secret BTW.
    $table = 'Projects';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*,
  (t.id = " . $memberProjectId . ") AS club_members,
  (t.id = " . $executiveBoardProjectId . ") AS executive_board
  FROM " . $table . " t
  WHERE t.type = '" . ProjectType::TEMPORARY->value . "' OR t.type = '" . ProjectType::PERMANENT->value . "'";

    // Export also the mapping to the web-pages maintained in the CMS.
    $table = 'ProjectWebPages';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " .  $this->personalizedViewName($dataBaseName, 'Projects') . " p
  INNER JOIN " . $table . " t
    ON  t.project_id = p.id
  GROUP BY t.project_id, t.article_id";

    // Unconditionally add all fields which are configured to be exposed. This
    // is needed by the project-registration form which exposes those fields
    // to the participants in spe.
    $table = 'ProjectParticipantFields';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " .  $this->personalizedViewName($dataBaseName, 'Projects') . " p
  INNER JOIN " . $table . " t
    ON  t.project_id = p.id
  WHERE t.participant_access <> 'none'
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
      ON t.field_id = ppf.id AND ppf.participant_access <> 'none'
    WHERE t." . $column . " = " . $accessFunction;

    $table = 'ProjectParticipantFieldsDataOptions';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->personalizedViewName($dataBaseName, 'ProjectParticipantFields') . " ppf
  INNER JOIN " . $table . " t
    ON t.field_id = ppf.id
  GROUP BY t.field_id, t.key";

    // we also need the project events, however, only rehearsals and concerts

    $table = 'ProjectEvents';
    $calendarUris = array_keys(array_filter(ConfigConstants::CALENDARS, fn($info) => $info['public'] == true));
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* FROM " . $table . " t
WHERE t.calendar_uri IN ('" . implode("','", $calendarUris) . "')";

    $table = 'InstrumentInsurances';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*,
  at.musician_id AS musician_id,
  (t.bill_to_party_id IS NULL OR at.musician_id = t.bill_to_party_id) AS is_debitor,
  (at.musician_id = t.instrument_holder_id) AS is_holder,
  (t.instrument_owner_id IS NULL OR at.musician_id = t.instrument_owner_id) AS is_owner
  FROM (SELECT " . $accessFunction . " AS musician_id) at
  INNER JOIN " . $table . " t
    ON t.instrument_holder_id = at.musician_id
    OR t.bill_to_party_id = musician_id
    OR t.instrument_owner_id = musician_id ";

    $table = 'Files';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $table . " t
  WHERE t.id IN (SELECT efov.encrypted_file_id AS file_id FROM " . $this->personalizedViewName($dataBaseName, 'EncryptedFileOwners') . " efov)";

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

    $table = 'DatabaseStorageDirEntries';
    $viewName = $this->personalizedViewName($dataBaseName, $table);
    $statements[$viewName] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $table . " t
  WHERE t.file_id IS NULL OR t.file_id IN (
    SELECT efov.encrypted_file_id AS file_id FROM " . $this->personalizedViewName($dataBaseName, 'EncryptedFileOwners') . " efov
)";

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
   * database. If null the views are created in the standard databse.
   *
   * @return void
   *
   * @throws Exceptions\DatabaseCloudConnectorViewException
   */
  public function updateMusicianPersonalizedViews(?string $dataBaseName):void
  {
    $statements = $this->generateMusicianPersonalizedViewsStatements($dataBaseName);

    $cloudDbUser = $this->checkAndGetCloudDbUser();
    $currentStatement = null;
    try {
      foreach ($statements as $viewName => $statement) {
        $currentStatement = $statement;
        if (preg_match(self::CREATE_FUNCTION_REGEXP, $statement)) {
          $this->logInfo('SQL ' . $currentStatement);
          $this->connection->prepare($currentStatement)->executeQuery();
          $currentStatement = sprintf(self::GRANT_EXECUTE, $viewName, $cloudDbUser);
        } elseif (preg_match(self::CREATE_VIEW_REGEXP, $statement)) {
          $this->logInfo('SQL ' . $currentStatement);
          try {
            $this->connection->prepare($currentStatement . ' WITH CHECK OPTION')->executeQuery();
          } catch (DBALDriverException $e) {
            if ($e->getCode() != self::CHECK_OPTION_ON_NON_UPDATABLE_VIEW_ERROR) {
              throw $e;
            }
            $this->connection->prepare($currentStatement)->executeQuery();
          }
          $currentStatement = sprintf(self::GRANT_SELECT, $viewName, $cloudDbUser);
        }
        $this->logInfo('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->executeQuery();
      }
    } catch (Throwable $t) {
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
   *
   * @return void
   *
   * @throws Exceptions\DatabaseCloudConnectorViewException
   */
  public function removeMusicianPersonalizedViews(?string $dataBaseName):void
  {
    $statements = $this->generateMusicianPersonalizedViewsStatements($dataBaseName);

    $cloudDbUser = $this->checkAndGetCloudDbUser();
    $currentStatement = null;
    try {
      foreach ($statements as $key => $sql) {
        if (preg_match(self::CREATE_FUNCTION_REGEXP, $sql)) {
          $currentStatement = sprintf(self::REVOKE_EXECUTE, $key, $cloudDbUser);
          $this->logDebug('SQL ' . $currentStatement);
          $this->connection->prepare($currentStatement)->executeQuery();
          $currentStatement = sprintf('DROP FUNCTION IF EXISTS %1$s', $key);
        } elseif (preg_match(self::CREATE_VIEW_REGEXP, $sql)) {
          $currentStatement = sprintf(self::REVOKE_SELECT, $key, $cloudDbUser);
          $this->logDebug('SQL ' . $currentStatement);
          $this->connection->prepare($currentStatement)->executeQuery();
          $currentStatement = sprintf('DROP VIEW IF EXISTS %1$s', $key);
        } else {
          $matches = null;
          if (preg_match('/GRANT\s*([^ ]+)\s*([^ ]+)?\s*ON\s*([^ ]+)\s*TO\s*([^ ]+)/', $sql, $matches)) {
            array_shift($matches);
            $currentStatement = sprintf('REVOKE %1$s %2$s ON %3$s FROM %4$s', ...$matches);
            $this->logDebug('SQL ' . $currentStatement);
            $this->connection->prepare($currentStatement)->executeQuery();
          }
          continue;
        }
        $this->logDebug('SQL ' . $currentStatement);
        $this->connection->prepare($currentStatement)->executeQuery();
      }
    } catch (Throwable $t) {
      throw new Exceptions\DatabaseCloudConnectorViewException(
        $this->l->t('Unable to delete personalized view: %s.', $currentStatement),
        $t->getCode(),
        $t
      );
    }
  }
}
