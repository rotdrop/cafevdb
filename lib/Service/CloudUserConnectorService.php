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

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Exceptions;

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

  const VIEW_POSTFIX = 'View';

  const USER_SQL_PREFIX = 'Nextcloud';

  /**
   * @var string
   *
   * The SQL to define the group-connector view for the user_sql
   * user-backend. Only projects with active users show up.
   *
   *
   */
  const USER_SQL_GROUP_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT %2$s AS gid,
       p.name AS display_name,
       0 AS is_admin
FROM Projects p
WHERE p.id IN (SELECT DISTINCT pp.project_id FROM ProjectParticipants pp WHERE pp.deleted IS NULL)
WITH CHECK OPTION';

  const USER_SQL_USER_GROUP_VIEW = 'CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW %1$s AS
SELECT m.user_id_slug AS uid,
       %2$s AS gid
FROM ProjectParticipants pp
LEFT JOIN Musicians m ON m.id = pp.musician_id
LEFT JOIN Projects p ON p.id = pp.project_id';
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

  const GRANT_SELECT = 'GRANT SELECT ON %1$s TO %2$s@\'localhost\'';
  const GRANT_FIELD_UPDATE = 'GRANT UPDATE (%3$s) ON %1$s TO %2$s@\'localhost\'';

  /** @var string */
  private $appName;

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
    , ILogger $logger
    , IL10N $l10n
    , IConfig $cloudConfig
    , EncryptionService $encryptionService
    , Connection $connection
    , IAppManager $appManager
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->cloudConfig = $cloudConfig;
    $this->encryptionService = $encryptionService;
    $this->connection = $connection;
    $this->appManager = $appManager;
    $this->appDbName = $this->encryptionService->getConfigValue('dbname');
    $this->appDbUser = $this->encryptionService->getConfigValue('dbuser');
    $this->appDbHost = $this->encryptionService->getConfigValue('dbserver');
  }

  private function viewName(?string $dataBaseName, string $prefix, string $baseName):string
  {
    $viewName = $prefix . $baseName . self::VIEW_POSTFIX;
    if (!empty($dataBaseName)) {
      $viewName = $dataBaseName . '.' . $viewName;
    }
    return $viewName;
  }

  private function userSqlGid()
  {
    return "CONCAT(_ascii '" . $this->appName . ":', p.id) COLLATE ascii_bin";
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
          sprintf($sql, $viewName, $this->userSqlGid()),
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
    ];
  }

  /**
   * This function hijacks the user_sql app. user_sql has no configuration
   * API, but it is just handy to do some auto-configuration here. Of course,
   * the auto-conf may fail if some things change in the future.
   *
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

};
