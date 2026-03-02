<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use Carbon\Carbon as DateTime;
use Carbon\CarbonInterval as DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PHP_IBAN;
use Throwable;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use Psr\Container\ContainerInterface;
use OCP\IRequest;

use OCA\BAV\Service\BAV as BankAccountValidator;

use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Common\NumberFormatter;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EmailAddressService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\Personal;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Toolkit\Doctrine\ORM\EntitySerializer\EntityArrayAdapter;

use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo\Service\RPC as WebPagesRPC;
use OCA\RoundCube\Service\Config as RoundCubeConfig;

/** AJAX end-points for personal settings. */
#[TSAttributes\TypeScript]
class PersonalSettingsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  public const EMAIL_PROTO = [ 'smtp', 'imap' ];
  public const EMAIL_SECURITY = [ 'insecure', 'starttls', 'ssl' ];
  private const EMAIL_PORTS = [
    'smtp' => [
      'insecure' => 587,
      'starttls' => 587,
      'ssl' => 465,
    ],
    'imap' => [
      'insecure' => 143,
      'starttls' => 143,
      'ssl' => 993,
    ],
  ];

  public const BASE_PATH = 'settings';
  public const END_POINT_GET = 'get';
  public const END_POINT_APP_GET = 'app/get';
  public const END_POINT_APP_SET = 'app/set';
  public const END_POINT_PERSONAL_SET = 'personal/set';
  public const END_POINT_PERSONAL_FORM = 'personal/form';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private CalDavService $calDavService,
    private ConfigCheckService $configCheckService,
    private EmailAddressService $emailAddressService,
    private FinanceService $financeService,
    private FuzzyInputService $fuzzyInputService,
    private Personal $personalSettings,
    private PhoneNumberService $phoneNumberService,
    private ProjectService $projectService,
    private TranslationService $translationService,
    private UserStorage $userStorage,
    private WebPagesRPC $webPagesRPC,
    private WikiRPC $wikiRPC,
    protected ConfigService $configService,
    protected ContainerInterface $appContainer,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @return Http\Response Return settings form.
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'GET',
    url: '/' . self::BASE_PATH . '/' . self::END_POINT_PERSONAL_FORM,
  )]
  public function form(): Http\Response
  {
    return $this->personalSettings->getForm();
  }

  /**
   * Store user settings.
   *
   * @param string $parameter
   *
   * @param mixed $value
   *
   * @return Http\Response
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::BASE_PATH . '/' . self::END_POINT_PERSONAL_SET . '/{parameter}',
)]
  public function set(string $parameter, mixed $value):Http\Response
  {
    $parameter = Util::dashesToCamelCase($parameter);
    try {
      $key = EnumPersonalSettingsKey::get($parameter);
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unknown configuration key "%s".', $parameter),
      );
    }
    switch ($key) {
      case EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS:
      case EnumPersonalSettingsKey::DIRECT_CHANGE:
      case EnumPersonalSettingsKey::EXPERT_MODE:
      case EnumPersonalSettingsKey::FINANCE_MODE:
      case EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY:
      case EnumPersonalSettingsKey::RESTORE_HISTORY:
      case EnumPersonalSettingsKey::SHOW_DISABLED:
      case EnumPersonalSettingsKey::TOOL_TIPS_ENABLED:
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === null) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Value "%1$s" for set "%2$s" is not convertible to boolean.', [$value, $parameter]),
          );
        }
        $stringValue = $realValue ? 'on' : 'off';
        $this->setUserValue($parameter, $stringValue);
        return (new DTO\MessagesResponse([
          $this->l->t('Switching %2$s %1$s', [ $this->l->t($stringValue), $this->l->t($parameter), ]),
        ]))->response();

      case EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT:
        $realValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]]);
        if ($realValue === false) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $parameter]),
          );
        }
        $this->setUserValue($parameter, $realValue);
        return (new DTO\MessagesResponse([
          $this->l->t('Setting %2$s to %1$s', [$realValue, $parameter]),
        ]))->respones();

      case EnumPersonalSettingsKey::DEBUG_MODE:
        if (!is_array($value)) {
          $debugModes = [];
        } else {
          $debugModes = $value;
        }
        $debug = 0;
        foreach ($debugModes as $item) {
          $debug |= $item['value'];
        }
        if ($debug > ConfigConstants::DEBUG_ALL) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unknown debug modes in request: %s$s', [print_r($debugModes, true)]),
          );
        }
        $this->setUserValue(EnumPersonalSettingsKey::DEBUG_MODE->value, $debug);
        if ($debug & ConfigConstants::DEBUG_CSP) {
          // generate a random magic key for sort-of authentication
          $this->setUserValue(ConfigConstants::CSP_FAILURE_TOKEN_KEY, $this->generateRandomBytes(128));
        } else {
          $this->deleteUserValue(ConfigConstants::CSP_FAILURE_TOKEN_KEY);
        }
        return (new DTO\ValueResponse(
          messages: [
            $this->l->t('Setting %2$s to %1$d', [$debug, $key->value]),
          ],
          value: $debug
        ))->response();

      case EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER:
        $realValue = Util::normalizeSpaces($value);
        $this->setUserValue(EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER, $realValue);
        return (new DTO\ValueResponse(
          messages: [$this->l->t('Setting %2$s to %1$s', [$realValue, $key->value])],
          value: $realValue,
        ))->response();

      case EnumPersonalSettingsKey::WYSIWYG_EDITOR:
        if (!isset(ConfigConstants::WYSIWYG_EDITORS[$value])) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unknown WYSIWYG-editor: %s$s', [ $value ]),
          );
        }
        $this->setUserValue($parameter, $value);
        return (new DTO\ValueResponse(
          messages: [$this->l->t('Setting %2$s to %1$s', [$value, $key->value])],
          value: $value,
        ))->response();

      case EnumPersonalSettingsKey::ENCRYPTION_KEY:
        // Get data
        if (!is_array($value) || !isset($value[EnumPersonalSettingsKey::ENCRYPTION_KEY->value]) || !isset($value['loginpassword'])) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Invalid request data: "%s".', [ print_r($value, true) ]),
          );
        }
        $password = $value['loginpassword'];
        $encryptionKey = $value[EnumPersonalSettingsKey::ENCRYPTION_KEY->value];

        // Re-validate the user
        if ($this->userManager()->checkPassword($this->userId(), $password) === false) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Invalid password for "%s".', [$this->userId()]),
          );
        }

        // Then check whether the key is correct
        if (!$this->encryptionKeyValid($encryptionKey)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Invalid encryption key.'),
          );
        }

        // So generate a new key-pair and store the key. This will only
        // change the user's preferences.
        // @todo If we ever should encrypt anything else with the user's
        // SSL key-pair then we would need to be more careful about the
        // key-pair.
        try {
          $this->encryptionService()->initUserKeyPair(true);
          $this->encryptionService()->setUserEncryptionKey($encryptionKey);
          $this->encryptionService()->setAppEncryptionKey($encryptionKey);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Unable to store the app encryption key for user "%s".', $this->userId()),
            previous: $t,
          );
        }
        return self::response($this->l->t('Encryption key stored.'));
      case EnumPersonalSettingsKey::EMAIL_DRAFT_AUTO_SAVE:
        $realValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($realValue === false) {
          $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
          if ($realValue === true) {
            $realValue = ConfigConstants::DEFAULT_AUTOSAVE_INTERVAL;
          } elseif ($realValue === false) {
            $realValue = 0;
          } else {
            throw new Exceptiosn\EnduserNotificationException(
              $this->l->t('Value "%1$s" for set "%2$s" must be a non-negative integer or false.', [$value, $parameter]),
            );
          }
        }
        $this->setUserValue($parameter, $realValue);
        return (new DTO\MessagesResponse(
          [$this->l->t('Setting %2$s to %1$s', [$realValue, $parameter])],
        ))->response();

      default:
    }
    throw new Exceptions\EnduserNotificationException($this->l->t('Unknown Request'));
  }

  /**
   * Store app settings.
   *
   * @param string $parameter
   *
   * @param mixed $value
   *
   * @return Http\Response
   *
   * @bug This function is too big.
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   */
  #[Attributes\SubAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::BASE_PATH . '/' . self::END_POINT_APP_SET . '/{parameter}',
  )]
  #[CoreAttributes\NoAdminRequired]
  public function setApp(string $parameter, mixed $value): Http\Response
  {
    switch ($parameter) {
      case ConfigConstants::ORCHESTRA_LOCALE_KEY: // could check for valid locale ...
        $realValue = trim($value);
        $this->setConfigValue($parameter, $realValue);
        return (new OrchestraLocaleResponse(
          value: $realValue,
          message: $this->l->t('"%s" set to "%s".', [$parameter, $realValue]),
          localeInfo: $this->generateLocaleInfo('app'),
        ))->response();

      case ConfigConstants::ORCHESTRA_NAME_KEY:
        $value = strtolower(Util::removeSpaces($value));
        // fall through
      case ConfigConstants::APP_DB_SERVER: // could check for valid hostname
      case ConfigConstants::APP_DB_NAME:
      case ConfigConstants::APP_DB_USER:
        $realValue = trim($value);
        $this->setConfigValue($parameter, $realValue);
        return (new DTO\ValueResponse(
          value: $realValue,
          messages: [$this->l->t('"%s" set to "%s".', [$parameter, $realValue])],
        ))->response();

      case ConfigConstants::APP_DB_PASSWORD:
        try {
          if (!empty($value)) {
            $oldDbPassword = $this->getConfigValue(ConfigConstants::APP_DB_PASSWORD);
            $this->setConfigValue(ConfigConstants::APP_DB_PASSWORD, $value);
            if ($this->configCheckService->databaseAccessible(['password' => $value])) {
              return (new DTO\MessagesResponse([$this->l->t('DB-test passed and DB-password set.')]))->response();
            } else {
              $this->setConfigValue(ConfigConstants::APP_DB_PASSWORD, $oldDbPassword);
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('DB-test failed. Check the account settings. Check was performed with the new password.'),
              );
            }
          } else {
            // Check with the stored password
            if ($this->configCheckService->databaseAccessible()) {
              return (new DTO\MessagesResponse([$this->l->t('DB-test passed with stored password (empty input ignored).')]))->response();
            } else {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('DB-test failed with stored password (empty input ignored).'),
              );
            }
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('DB-test failed with an exception'),
            previous: $t,
          );
        }

      case 'systemkey':
        foreach (['systemkey', 'oldkey'] as $key) {
          if (!isset($value[$key])) {
            return self::grumble($this->l->t('Missing parameter "%s".', $key));
          }
        }

        $oldKey = $value['oldkey'];
        $systemKey = $value['systemkey'];

        $encryptionService = $this->encryptionService();

        $storedKeyHash = $encryptionService->getConfigValue(ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY);
        if (!$encryptionService->verifyHash($oldKey, $storedKeyHash)) {
          return self::grumble($this->l->t('Wrong old encryption key'));
        }

        // install old encryption key
        $encryptionService->setAppEncryptionKey($oldKey);

        // do some rudimentary locking
        $configLock = $this->getAppValue(ConfigConstants::CONFIG_LOCK_KEY);
        if (!empty($configLock)) {
          return self::grumble($this->l->t('Configuration locked, refusing to change encryption key.'));
        }

        $configLock = $this->generateRandomBytes(32);
        $this->setAppValue(ConfigConstants::CONFIG_LOCK_KEY, $configLock);
        if ($configLock !== $this->getAppValue(ConfigConstants::CONFIG_LOCK_KEY)) {
          return self::grumble($this->l->t('Configuration locked, refusing to change encryption key.'));
        }

        // Still: this does ___NOT___ hack the worst-case scenario, but should suffice for our purposes.

        try {
          // load all config values and decrypt with the old key
          $configValues = $this->configService->decryptConfigValues();
        } catch (Throwable $t) {
          $this->deleteAppValue(ConfigConstants::CONFIG_LOCK_KEY);
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Unable to decrypt the config values with the old encryptionkey.'),
            previous: $t,
          );
        }

        //$this->logInfo(print_r($configValues, true));

        // make a backup by just copying plain values which can be
        // restored disregarding any encryption key.
        $backupSuffix = '::'.(new DateTime())->format('YmdHis');
        try {
          foreach (array_keys($configValues) as $configKey) {
            $backupConfigKey = $configKey . $backupSuffix;
            $this->setAppValue($backupConfigKey, $this->getAppValue($configKey));
          }
        } catch (Throwable $t) {
          $this->logException($t);
          foreach (array_keys($configValues) as $configKey) {
            $backupConfigKey = $configKey . $backupSuffix;
            try {
              $this->deleteAppValue($backupConfigKey);
            } catch (Throwable $t1) {
              //$this->logException($t1);
            }
          }
          $this->deleteAppValue(ConfigConstants::CONFIG_LOCK_KEY);
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Unable to take a backup of the decrypted config values.'),
            previouws: $t,
          );
        }

        try {

          $encryptionService->initAppKeyPair();
          $oldDatabaseCryptor = $encryptionService->getAppAsymmetricCryptor();
          if (!empty($oldDatabaseCryptor)) {
            $oldDatabaseCryptor = clone $oldDatabaseCryptor;
          }

          $encryptionService->setAppEncryptionKey($systemKey);

          // re-crypt the config-space
          $this->configService->encryptConfigValues([
            ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY => (empty($systemKey) ? '' : $this->computeHash($systemKey)),
          ]);

          // re-generate the private/public key pair
          $encryptionService->initAppKeyPair(forceNewKeyPair: true);
          $newDatabaseCryptor = $encryptionService->getAppAsymmetricCryptor();

          // re-crypt the data-base columns. Changing the data-base
          // values is wrapped into a transaction, so it should clean-up
          // after itself unless the data-base connection breaks down in
          // between.
          /** @var EntityManager $entityManager */
          $entityManager = $this->di(EntityManager::class);
          $entityManager->recryptEncryptedProperties($newDatabaseCryptor, $oldDatabaseCryptor);

        } catch (Throwable $t) {
          $encryptionService->setAppEncryptionKey($oldKey);
          $encryptionService->restoreAppKeyPair();
          $messages = [];
          $failed = [];
          foreach (array_keys($configValues) as $configKey) {
            $backupConfigKey = $configKey . $backupSuffix;
            try {
              $this->setAppValue($configKey, $this->getAppValue($backupConfigKey));
            } catch (Throwable $t1) {
              // $this->logException($t1);
              $failed[] = $configKey;
            }
          }
          if (!empty($failed)) {
            $messages[] = $this->l->t('Failed to restore config-values %s, keeping all backup values with suffix "%s".', [ implode(', ', $failed), $backupSuffix ]);
          } else {
            $failed = [];
            foreach (array_keys($configValues) as $configKey) {
              $backupConfigKey = $configKey . $backupSuffix;
              try {
                $this->deleteAppValue($backupConfigKey);
              } catch (Throwable $t2) {
                // $this->logException($t2);
                $failed[] = $configKey;
              }
            }
            if (!empty($failed)) {
              $messages[] = $this->l->t('Failed to remove backups for config-values %s.', implode(', ', $failed));
            } else {
              $this->logInfo('Deleting config-lock');
              $this->deleteAppValue(ConfigConstants::CONFIG_LOCK_KEY);
            }
          }
          throw new Exceptions\EnduserNotificationException(
            message: implode(' ', $messages),
            previous: $t,
          );
        }

        $messages = [];
        $failed = [];
        foreach (array_keys($configValues) as $configKey) {
          $backupConfigKey = $configKey . $backupSuffix;
          try {
            $this->deleteAppValue($backupConfigKey);
          } catch (Throwable $t2) {
            // $this->logException($t2);
            $failed[] = $configKey;
          }
        }
        if (!empty($failed)) {
          $messages[] = $this->l->t('Failed to remove backups for config-values %s.', implode(',', $failed));
        }

        $this->logInfo('Deleting config-lock');
        $this->deleteAppValue(ConfigConstants::CONFIG_LOCK_KEY);

        // this should be it: the new encryption key is stored in the
        // config space, encrypted with itself.

        // Shouldn't we distribute the key as well? YES.
        list('status' => $distributeStatus, 'messages' => $distributeMessages) = $this->distributeEncryptionKey();
        $messages = array_merge($distributeMessages, $messages);

        if ($distributeStatus == Http::STATUS_OK) {
          $messages[] = $this->l->t('Stored new encryption key.');
        } else {
          $messages[] = $this->l->t('Stored the new encryption key, however, distributing the new encryption key failed for at least some of the users.');
        }
        return self::dataResponse(
          [
            'message' => $messages,
            'distributeStatus' => $distributeStatus,
            'keyStatus' => Http::STATUS_OK,
          ],
          Http::STATUS_OK);
      case ConfigConstants::STREET_ADDRESS_NAME_01:
      case ConfigConstants::STREET_ADDRESS_NAME_02:
      case ConfigConstants::STREET_ADDRESS_STREET:
      case ConfigConstants::STREET_ADDRESS_HOUSE_NUMBER:
      case ConfigConstants::STREET_ADDRESS_CITY:
      case ConfigConstants::STREET_ADDRESS_ZIP:
      case ConfigConstants::STREET_ADDRESS_COUNTRY:
      case 'registerName':
      case 'registerNumber':
        $realValue = trim($value);
        $this->setConfigValue($parameter, $realValue);
        return self::valueResponse($realValue, $this->l->t(' "%s" set to "%s".', [$parameter, $realValue]));
        break;
      case ConfigConstants::SHARE_OWNER_KEY:
        if (!isset($value[ConfigConstants::SHARE_OWNER_KEY])
            || !isset($value['shareowner-saved'])
            || !isset($value['shareowner-force'])) {
          return self::grumble($this->l->t('Invalid request parameters: ') . print_r($value, true));
        }
        $uid = $value[ConfigConstants::SHARE_OWNER_KEY];
        $savedUid = $value['shareowner-saved'];
        $force = filter_var($value['shareowner-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);

        // first check consistency of $savedUid with stored UID.
        $confUid = $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY, '');
        if ($confUid != $savedUid) {
          return self::grumble($this->l->t(
            'Submitted "%s" != "%s" (stored)', [ $savedUid, $confUid ]));
        }
        if (empty($uid)) {
          return self::grumble($this->l->t('Share-owner user id must not be empty.'));
        }
        if (empty($savedUid) || $force) {
          if ($this->configCheckService->checkShareOwner($uid)) {
            $this->setConfigValue($parameter, $uid);
            return self::valueResponse($uid, $this->l->t('New share-owner "%s".', [$uid]));
          } else {
            return self::grumble($this->l->t('Failure creating account for user-id "%s".', [$uid]));
          }
        } elseif ($savedUid != $uid) {
          return self::grumble($savedUid . ' != ' . $uid);
        }

        if (!$this->configCheckService->checkShareOwner($uid)) {
          return self::grumble($this->l->t('Failure checking account for user-id "%s".', [$uid]));
        }

        return self::response($this->l->t('Share-owner user "%s" ok.', [$uid]));

      case 'phoneNumber':
        $realValue = Util::normalizeSpaces($value);
        if (empty($realValue)) {
          return self::response('');
        }
        $number = [];
        if ($this->phoneNumberService->validate($realValue)) {
          $number['number'] = $this->phoneNumberService->format();
          $number['meta'] = $this->phoneNumberService->metaData();
          $number['isMobile'] = $this->phoneNumberService->isMobile();
          $number['valid'] = true;
          $this->setConfigValue($parameter, $number['number']);
          return self::dataResponse(array_merge($number, [
            'messages' => $this->l->t('Orchestra Phone Number set to %s', $number['number']),
          ]));
        } else {
          return self::grumble($this->l->t(
            'The phone number %s does not appear to be a valid phone number. ', [ $realValue, ]));
        }
        break;
      case EnumSimpleSettingsKey::BANK_ACCOUNT_BANK_HOLIDAYS->value:
        return $this->setSimpleConfigValue(
          EnumSimpleSettingsKey::BANK_ACCOUNT_BANK_HOLIDAYS,
          $value,
        );
      case ConfigConstants::BANK_ACCOUNT_OWNER:
      case ConfigConstants::BANK_ACCOUNT_BLZ:
      case ConfigConstants::BANK_ACCOUNT_IBAN:
      case ConfigConstants::BANK_ACCOUNT_BIC:
      case ConfigConstants::BANK_ACCOUNT_BANK_NAME:
      case ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER:
        $realValue = Util::normalizeSpaces($value);
        $data = [
          ConfigConstants::BANK_ACCOUNT_IBAN => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_IBAN),
          ConfigConstants::BANK_ACCOUNT_BLZ => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_BLZ),
          ConfigConstants::BANK_ACCOUNT_BIC => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_BIC),
          ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER),
          ConfigConstants::BANK_ACCOUNT_OWNER => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_OWNER),
          ConfigConstants::BANK_ACCOUNT_BANK_NAME => $this->getConfigValue(ConfigConstants::BANK_ACCOUNT_BANK_NAME),
          'message' => '',
        ];
        if (empty($realValue) && !empty($data[$parameter])) {
          // allow erasing
          $this->setConfigValue($parameter, $realValue);
          $data[$parameter] = $realValue;
          $data['message'] = $this->l->t('Erased config value for parameter "%s".', $parameter);
          return self::dataResponse($data);
        }
        switch ($parameter) {
          case ConfigConstants::BANK_ACCOUNT_OWNER:
            $address = $this->getConfigValue(ConfigConstants::STREET_ADDRESS_NAME_01);
            if ($realValue !== $address) {
              $data['suggestions'] = [ $address, ];
            }
            if (!empty($realValue)) {
              $this->setConfigValue($parameter, $realValue);
              $data[$parameter] = $realValue;
              $data['message'] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            return self::dataResponse($data);
          case ConfigConstants::BANK_ACCOUNT_BANK_NAME:
            if (!empty($realValue)) {
              $this->setConfigValue($parameter, $realValue);
              $data[$parameter] = $realValue;
              $data['message'] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            return self::dataResponse($data);
          case ConfigConstants::BANK_ACCOUNT_CREDITOR_IDENTIFIER:
            if (empty($realValue)) {
              return self::response('');
            }
            if ($this->financeService->testCI($realValue)) {
              $this->setConfigValue($parameter, $realValue);
              $data[$parameter] = $realValue;
              $data['message'] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
              return self::dataResponse($data);
            }
            break;
          case ConfigConstants::BANK_ACCOUNT_IBAN:
            if (empty($realValue)) {
              return self::response('');
            }
            $iban = new PHP_IBAN\IBAN($realValue);
            $bav = $this->appContainer->get(BankAccountValidator::class);
            if (!$iban->Verify() && is_numeric($realValue)) {
              // maybe simlpy the bank account number, if we have a BLZ,
              // then compute the IBAN
              $blz = $data[ConfigConstants::BANK_ACCOUNT_BLZ];
              if ($bav->isValidBank($blz)) {
                $realValue = $this->financeService->makeIBAN($blz, $realValue);
                $iban = new PHP_IBAN\IBAN($realValue);
              }
            }
            $data['message'] = [];
            if ($iban->Verify()) {
              $realValue = $iban->MachineFormat();
              $this->setConfigValue($parameter, $realValue);
              if ($data[$parameter] != $realValue) {
                $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
              }
              $data[$parameter] = $realValue;

              // Compute as well the BLZ and the BIC
              $blz = $iban->Bank();
              if ($bav->isValidBank($blz)) {
                $realValue = $blz;
                $parameter = ConfigConstants::BANK_ACCOUNT_BLZ;
                $this->setConfigValue($parameter, $realValue);
                if ($data[$parameter] != $realValue) {
                  $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
                }
                $data[$parameter] = $realValue;

                $bic = $bav->getMainAgency($blz)->getBIC();
                $realValue = $bic;
                $parameter = ConfigConstants::BANK_ACCOUNT_BIC;
                $this->setConfigValue($parameter, $realValue);
                if ($data[$parameter] != $realValue) {
                  $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
                }
                $data[$parameter] = $realValue;

                $parameter = ConfigConstants::BANK_ACCOUNT_BANK_NAME;
                $suggestedBankName = $bav->getMainAgency($blz)->getName();
                $realValue = $this->getConfigValue($parameter);
                if (empty($realValue) || $realValue != $suggestedBankName) {
                  $realValue = $suggestedBankName;
                  $this->setConfigValue($parameter, $realValue);
                  $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
                  $data[$parameter] = $realValue;
                }
              } else {
                unset($data[ConfigConstants::BANK_ACCOUNT_BLZ]);
                unset($data[ConfigConstants::BANK_ACCOUNT_BIC]);
              }
              return self::dataResponse($data);
            } else {
              $data['message'] = $this->l->t('Invalid IBAN: "%s".', [ $value ]);
              $suggestions = $iban->MistranscriptionSuggestions();
              $data['suggestions'] = [];
              foreach ($suggestions as $alternative) {
                if ($iban->Verify($alternative)) {
                  $alternative = $iban->MachineFormat($alternative);
                  $alternative = $iban->HumanFormat($alternative);
                  $data['suggestions'][] = $alternative;
                }
              }
              return self::grumble($data);
            }
            break;
          case ConfigConstants::BANK_ACCOUNT_BLZ:
            if (empty($realValue)) {
              return self::response('');
            }
            $bav = $this->appContainer->get(BankAccountValidator::class);
            if ($bav->isValidBank($realValue)) {
              $data['message'] = [];
              $this->setConfigValue($parameter, $realValue);
              if ($data[$parameter] != $realValue) {
                $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
              }
              $data[$parameter] = $realValue;

              // set also the BIC
              $agency = $bav->getMainAgency($realValue);
              $bic = $agency->getBIC();
              if ($this->financeService->validateSWIFT($bic)) {
                $parameter = ConfigConstants::BANK_ACCOUNT_BIC;
                $realValue = $bic;
                $this->setConfigValue($parameter, $realValue);
                if ($data[$parameter] != $realValue) {
                  $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
                }
                $data[$parameter] = $realValue;
              } else {
                unset($data[ConfigConstants::BANK_ACCOUNT_BIC]);
              }
              return self::dataResponse($data);
            }
            break;
          case ConfigConstants::BANK_ACCOUNT_BIC:
            if (empty($realValue)) {
              return self::response('');
            }
            $data['message'] = [];
            if (!$this->financeService->validateSWIFT($realValue)) {
              // maybe a BLZ
              $bav = $this->appContainer->get(BankAccountValidator::class);
              if ($bav->isValidBank($realValue)) {
                $parameter = ConfigConstants::BANK_ACCOUNT_BLZ;
                $this->setConfigValue($parameter, $realValue);
                if ($data[$parameter] != $realValue) {
                  $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
                }
                $data[$parameter] = $realValue;
                $agency = $bav->getMainAgency($realValue);
                $realValue = $agency->getBIC();
                $parameter = ConfigConstants::BANK_ACCOUNT_BIC;
                // Set also the BIC
              } else {
                unset($data[ConfigConstants::BANK_ACCOUNT_BLZ]);
              }
            }
            if ($this->financeService->validateSWIFT($realValue)) {
              $parameter = ConfigConstants::BANK_ACCOUNT_BIC;
              $this->setConfigValue($parameter, $realValue);
              if ($data[$parameter] != $realValue) {
                $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
              }
              $data[$parameter] = $realValue;
              return self::dataResponse($data);
            }
            break; // error
        }
        $data['message'] = $this->l->t('Value for "%s" invalid: "%s".', [ $parameter, $value ]);
        return self::grumble($data);
      case 'memberProject':
      case 'executiveBoardProject':
        $realValue = Util::normalizeSpaces($value);
        // fetch existing values
        $currentProjectName = $this->getConfigValue($parameter, '');
        $currentProjectId = $this->getConfigValue($parameter.'Id', null);
        $data = [
          'messages' => [],
          'project' =>  $currentProjectName,
          'projectId' => $currentProjectId,
          'feedback' => false,
          'newName' => '',
          'suggestions' => $this->projectService->projectOptions([ 'type' => 'permanent' ]),
        ];
        if (!empty($currentProjectName) && empty($value)) {
          // erase current setting
          $this->deleteConfigValue($parameter);
          $this->deleteConfigValue($parameter.'Id');
          $data['message'][] = $this->l->t('Erased config value for parameter "%s".', $parameter);

          // ask to also remove the project if applicable
          if (!empty($currentProjectId)
              && !empty($this->projectService->findById($currentProjectId))) {
            $data['feedback']['Delete'] = [
              'title' => $this->l->t('Delete old Project?'),
              'message' => $this->l->t(
                'Delete old project "%s" (%d) and all its associated data?',
                [ $currentProjectName, $currentProjectId ]),
            ];
          } else {
            $data['project'] = '';
            $data['projectId'] = null;
          }
          return self::dataResponse($data);
        }
        if (empty($realValue)) {
          // silently ignore, just keep unconfigured
          return self::response('');
        }
        $newName = $this->projectService->sanitizeName($realValue);
        if ($newName !== $realValue) {
          $data['message'][] = $this->l->t(
            'Sanitized project name from "%s" to "%s".', [ $value, $newName ]);
        }
        $newProject = $this->projectService->findByName($newName);
        $currentProject = $this->projectService->findByName($currentProjectName);
        $haveOldProject = (int)$currentProject['id'] === (int)$currentProjectId;
        $data['newName'] = $newName;

        if ($newName !== $currentProjectName) {
          $this->setConfigValue($parameter, $newName);
          $data['message'][] = $this->l->t(
            '"%s" set to "%s".', [$parameter, $newName]);
        }

        if (empty($newProject)) {
          $this->deleteConfigValue($parameter.'Id');
        }

        if ($haveOldProject
            && empty($newProject)
            && $newName !== $currentProjectName) {
          $data['feedback']['Rename'] = [
            'title' => $this->l->t('Rename Project?'),
            'message' => $this->l->t(
              '"%s" project already exists, rename it from "%s" to "%s?',
              [ $this->l->t($parameter), $currentProjectName, $newName ]),
          ];
          return self::dataResponse($data);
        }

        if (!empty($newProject)) {
          $data['project'] = $newName;
          $data['projectId'] = $newProject['id'];
          $this->data['message'][] = $this->l->t(
            '"%s" set to "%s".', [$parameter.'Id', $newProject['id'] ]);
          $this->setConfigValue($parameter.'Id', $newProject['id']);
          if ($newProject['type'] != Types\EnumProjectTemporalType::PERMANENT) {
            $newProject['type'] = Types\EnumProjectTemporalType::PERMANENT;
            $this->projectService->persistProject($newProject);
            $this->data['message'][] = $this->l->t(
              'Type of project "%s" set to "%s".', Types\EnumProjectTemporalType::PERMANENT);
          }
          return self::dataResponse($data);
        } else {
          $data['feedback']['Create'] = [
            'title' => $this->l->t('Create project?'),
            'message' => $this->l->t(
              'A project with name "%s" does not exist, shall we create it?', $newName),
          ];
          return self::dataResponse($data);
        }
        break;
      case 'memberProjectValidate':
      case 'executiveBoardProjectValidate':
        $projectName = $value['projectName'];
        $projectId = $value['projectId'];

        $projectParameter = preg_replace('/Validate$/', '', $parameter);

        $currentProjectName = $this->getConfigValue($projectParameter, '');
        $currentProjectId = $this->getConfigValue($projectParameter.'Id', null);

        $data = [ 'messages' => [] ];

        if ((int)$currentProjectId != (int)$projectId) {
          return self::grumble(
            $this->l->t('Configured project-id %d and submitted project id %d differ, please reload the page.', [
              $currentProjectId, $projectId ]));
        }

        if ((int)$projectId <= 0) {
          try {
            $projectName = $value['newProjectName'];
            $project = $this->projectService->createProject($projectName, null, Types\EnumProjectTemporalType::PERMANENT);
            if (!empty($project)) {
              $this->setConfigValue($projectParameter, $project['name']);
              $this->setConfigValue($projectParameter.'Id', $project['id']);
            }
          } catch (Throwable $t) {
            throw new Exceptions\EnduserNotificationException(
              message: $this->l->t('Unable to create project with name "%s".', $projectName),
              previous: $t,
            );
          }

          $data = [
            'project' => $projectName,
            'projectId' => $project->getId(),
            'newName' => $projectName,
            'messages' => [
              $this->l->t('Created Project "%s" with id "%d".', [
                $project['name'], $project['id'] ])
            ],
            'suggestions' => $this->projectService->projectOptions([ 'type' => 'permanent' ]),
          ];

        } else {

          $project = $this->projectService->findById($projectId);

          if (empty($project)) {
            return self::grumble($this->l->t('Unable to find the project with id %d.', $projectId));
          }

          try {
            $this->projectService->sanitizeProject($project);
          } catch (Throwable $t) {
            throw new Exceptions\EnduserNotificationException(
              message: $this->l->t('Unable to sanitize the project "%s".', $projectName),
              previous: $t,
            );
          }

          $data = [
            'project' => $project->getName(),
            'projectId' => $project->getId(),
            'messages' => [
              $this->l->t('Project "%s" successfully validated.', $project->getName()),
            ],
          ];
        }

        return DTO\SpecialProjectsResponse::fromArray($data)->response();
      case 'memberProjectDelete':
      case 'executiveBoardProjectDelete':
        try {
          $projectId = $value['projectId'];
          $projectName = $value['project'];
          $project = $this->projectService->deleteProject($projectId);
          $data = [
            'suggestions' => $this->projectService->projectOptions([ 'type' => 'permanent' ]),
            'message' => (empty($project)
                          ? $this->l->t('Deleted project "%s" with id "%d".', [
                            $projectName, $projectId ])
                          : $this->l->t('Project "%s", id "%d" has been marked as disabled as it is still needed for financial book-keeping.', [
                            $projectName, $projectId ])),
          ];
          return self::dataResponse($data);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Failed to remove project "%s", id "%d".', [ $projectName, $projectId ]),
            prevous: $t,
          );
        }
        break;
      case 'memberProjectRename':
      case 'executiveBoardProjectRename':
        $projectId = null;
        $projectName = '';
        $newName = '';
        try {
          $projectId = $value['projectId'];
          $projectName = $value['project'];
          $newName = $value['newName'];
          $project = $this->projectService->renameProject($projectId, $newName);
          if (!empty($project)) {
            $projectParameter = preg_replace('/Rename$/', '', $parameter);
            $this->setConfigValue($projectParameter, $project['name']);
            $this->setConfigValue($projectParameter.'Id', $project['id']);
          } else {
            throw new Exceptions\EnduserNotificationException($this->l->t('Result of rename is empty without throwing an exception.'));
          }

          $data = [
            'message' => $this->l->t(
              'Renamed project "%s" (%d) to "%s".',
              [ $projectName, $project['id'], $newName ]),
            'project' => $newName,
            'projectId' => $projectId,
          ];
          return self::dataResponse($data);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t(
              'Failed to rename project "%s", id "%d" to new name "%s".',
              [ $projectName, $projectId, $newName ],
            ),
            previous: $t,
          );
        }
        break;
      case EnumSimpleSettingsKey::PRESIDENT_USER_ID->value:
      case EnumSimpleSettingsKey::SECRETARY_USER_ID->value:
      case EnumSimpleSettingsKey::TREASURER_USER_ID->value:
      case EnumSimpleSettingsKey::PRESIDENT_ID->value:
      case EnumSimpleSettingsKey::SECRETARY_ID->value:
      case EnumSimpleSettingsKey::TREASURER_ID->value:
      case EnumSimpleSettingsKey::PRESIDENT_GROUP_ID->value:
      case EnumSimpleSettingsKey::SECRETARY_GROUP_ID->value:
      case EnumSimpleSettingsKey::TREASURER_GROUP_ID->value:
      case EnumSimpleSettingsKey::PRESIDENT_EMAIL->value:
      case EnumSimpleSettingsKey::SECRETARY_EMAIL->value:
      case EnumSimpleSettingsKey::TREASURER_EMAIL->value:
        $executiveBoardMembers = ['president', 'secretary', 'treasurer'];
        foreach ($executiveBoardMembers as $prefix) {
          foreach (['Id', 'UserId', 'GroupId', 'Email'] as $postfix) {
            $official = $prefix.$postfix;
            if ($parameter === $official) {
              // @todo validate
              return $this->setSimpleConfigValue($parameter, $value);
            }
          }
        }
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('SETTING %s NOT YET IMPLEMENTED', $parameter),
        );

      case ConfigConstants::SHARE_OWNER_PASSWORD_KEY:
        $shareOwnerUid = $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY);
        if (empty($shareOwnerUid)) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Please create the share-owner user first.'));
        }
        $shareOwner = $this->user($shareOwnerUid);
        if (empty($shareOwner)) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Share-owner does not seem to exist, please recreate.'));
        }
        if (!$shareOwner->canChangePassword()) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Authentication backend does not support changing passwords.'));
        }
        $realValue = trim($value); // @@todo: check for valid password chars.
        if (empty($realValue)) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Password must not be empty'));
        }
        if (!$shareOwner->setPassword($realValue)) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Unable to set password for "%s".', [$shareOwnerUid]));
        }
        $this->setConfigValue($parameter, $realValue); // remember for remote API perhaps
        return (new DTO\MessagesResponse([$this->l->t('Successfully changed passsword for "%s".', [$shareOwnerUid])]))->response();

      case (!empty(ConfigConstants::DOCUMENT_TEMPLATES[substr($parameter, 0, -strlen('Delete'))]) ? $parameter : null):
        // Delete config value and file. The file can be undeleted in the cloud, if necessary.

        // Bit unclean, as a relict of previous implementation the
        // "setter" code also handles deletion, so we can just fall
        // through to it.
        $parameter = substr($parameter, 0, -strlen('Delete'));
        $value = '';
        // fallthrough
      case (!empty(ConfigConstants::DOCUMENT_TEMPLATES[$parameter]) ? $parameter : null):
        $oldFileName = $this->getConfigValue($parameter);
        $sharedFolder = $this->getConfigValue(ConfigConstants::SHARED_FOLDER);
        if (empty($sharedFolder)) {
          return self::grumble($this->l->t(
            'Shared folder is not configured, cannot store templates.'));
        }
        $templatesFolder = $this->getConfigValue(ConfigConstants::DOCUMENT_TEMPLATES_FOLDER);
        if (empty($templatesFolder)) {
          return self::grumble($this->l->t(
            'Document template folder is not configured, cannot store templates.'));
        }
        $templatesFolder = UserStorage::PATH_SEP
          . $sharedFolder . UserStorage::PATH_SEP
          . $templatesFolder . UserStorage::PATH_SEP;
        $subFolder = ConfigConstants::DOCUMENT_TEMPLATES[$parameter]['folder']??null;
        if (!empty($subFolder)) {
          $subFolder = $this->getConfigValue($subFolder);
          if (!empty($subFolder)) {
            $templatesFolder .= $subFolder . UserStorage::PATH_SEP;
          }
        }
        if (empty($value)) {
          $this->deleteConfigValue($parameter);
          $messages[] = $this->l->t(
            'Removed setting for document-template "%s".', $parameter);
        } else {
          try {
            $this->userStorage->get($templatesFolder . $value);
          } catch (Throwable $t) {
            throw new Exceptions\EnduserNotificationException(
              message: $this->l->t('Unable to find the file "%s".', $value),
              previous: $t,
            );
          }
          $this->setConfigValue($parameter, $value);
          $messages[] = $this->l->t(
            'Document-template "%s" successfully set to "%s".', [ $parameter, $value ]);
        }
        if (!empty($oldFileName) && $oldFileName != $value) {
          $this->logInfo('TRY DELETED OLD '.$templatesFolder . $oldFileName);
          try {
            /** @var \OCP\Files\File $oldFile */
            $oldFile = $this->userStorage->getFile($templatesFolder . $oldFileName);
            if (!empty($oldFile)) {
              $oldFile->delete();
              $messages[] = $this->l->t(
                'Successfully deleted old document-template "%s".', [ $oldFileName ]);
            }
          } catch (Throwable $t) {
            $this->logException($t);
          }
        }
        return new DTO\MessagesResponse(messages: $messages)->response();

      case ConfigConstants::SHARED_FOLDER:
        $appGroup = $this->getConfigValue(ConfigConstants::USER_GROUP_KEY);
        if (empty($appGroup)) {
          return self::grumble($this->l->t('App user-group is not set.'));
        }
        $shareOwner = $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY);
        if (empty($shareOwner)) {
          return self::grumble($this->l->t('Share-owner is not set.'));
        }
        if (!isset($value[$parameter])
            || !isset($value[$parameter.'-saved'])
            || !isset($value[$parameter.'-force'])) {
          return self::grumble($this->l->t('Invalid request parameters: ') . print_r($value, true));
        }
        $real = trim($value[$parameter]);
        $saved = $value[$parameter.'-saved'];
        $force = filter_var($value[$parameter.'-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        $actual = $this->getConfigValue($parameter);
        if (empty($real)) {
          return self::grumble($this->l->t('Folder must not be empty.'));
        }
        if ($actual != $saved) {
          return self::grumble($this->l->t('Submitted "%s" != "%s" (stored)', [$saved, $actual]));
        }
        try {
          if (empty($saved) || $force) {

            if ($this->configCheckService->checkSharedFolder($real)) {
              $this->setConfigValue($parameter, $real);
              try {
                $folderLink = $this->userStorage->getFilesAppLink($real);
              } catch (Throwable $t) {
                // don't care
              }
              return DTO\FolderValueResponse::fromArray([
                'message' => $this->l->t('Created and shared new folder "%s".', [$real]),
                'value' => $real,
                'folderLink' => $folderLink,
              ])->response();
            } else {
              return self::grumble($this->l->t('Failed to create new shared folder "%s".', [$real]));
            }
          } elseif ($real != $saved) {
            return self::grumble($saved . ' != ' . $real);
          } elseif ($this->configCheckService->checkSharedFolder($actual)) {
            try {
              $folderLink = $this->userStorage->getFilesAppLink($real);
            } catch (Throwable $t) {
              // don't care
            }
            return DTO\FolderValueResponse::fromArray([
              'message' => $this->l->t('"%s" which is configured as "%s" exists and is usable.', [$parameter, $actual]),
              'value' => $actual,
              'folderLink' => $folderLink,
            ])->response();
          } else {
            return self::grumble($this->l->t('"%s" does not exist or is unaccessible.', [$actual]));
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Failure checking folder "%s", caught an exception.', $real),
            previous: $t
          );
        }
        // return self::valueResponse('hello', print_r($value, true)); unreached
      case ConfigConstants::POSTBOX_FOLDER:
      case ConfigConstants::OUTBOX_FOLDER:
      case ConfigConstants::DOCUMENT_TEMPLATES_FOLDER:
      case ConfigConstants::PROJECT_PARTICIPANTS_FOLDER:
      case ConfigConstants::PROJECT_POSTERS_FOLDER:
      case ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER:
      case ConfigConstants::FINANCE_FOLDER:
      case ConfigConstants::TRANSACTIONS_FOLDER:
      case ConfigConstants::BALANCES_FOLDER:
      case ConfigConstants::PROJECTS_FOLDER:
        $appGroup = $this->getConfigValue(ConfigConstants::USER_GROUP_KEY);
        if (empty($appGroup)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('App user-group is not set.'),
          );
        }
        $shareOwner = $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY);
        if (empty($shareOwner)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Share-owner is not set.'),
          );
        }
        $sharedFolder = $this->getConfigValue(ConfigConstants::SHARED_FOLDER);
        if (empty($sharedFolder)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Shared folder is not set.'),
          );
        }
        $sharedFolder .= UserStorage::PATH_SEP;
        if (!isset($value[$parameter])
            || !isset($value[$parameter.'-saved'])
            || !isset($value[$parameter.'-force'])) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Invalid request parameters: ') . print_r($value, true),
          );
        }
        $real = trim($value[$parameter]);
        $saved = $value[$parameter.'-saved'];
        $force = filter_var($value[$parameter.'-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        $actual = $this->getConfigValue($parameter);
        if (empty($real)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Folder must not be empty.'),
          );
        }
        if ($actual != $saved) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Submitted "%s" != "%s" (stored)', [$saved, $actual]),
          );
        }
        // shortcut for participants and posters folder, which only exist as subdirectory
        $okMessage = null;
        switch ($parameter) {
          case ConfigConstants::PROJECT_PARTICIPANTS_FOLDER:
            $okMessage = $this->l->t('Participants-folder set to "%s".', $real);
            break;
          case ConfigConstants::PROJECT_POSTERS_FOLDER:
            $okMessage = $this->l->t('Posters-folder set to "%s".', $real);
            break;
          case ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER:
            $okMessage = $this->l->t('Participants downloads-folder set to "%s".', $real);
            break;
          case ConfigConstants::BALANCES_FOLDER:
          case ConfigConstants::TRANSACTIONS_FOLDER:
            $prefixFolder = $this->getConfigValue(ConfigConstants::FINANCE_FOLDER);
            if (empty($prefixFolder)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t(
                  '"%s" has to be defined first before defining "%s".',
                  [ ConfigConstants::FINANCE_FOLDER, $parameter ]),
              );
            }
            $prefixFolder .= UserStorage::PATH_SEP;
            break;
          default:
            $prefixFolder = '';
            break;
        }
        if ($okMessage !== null) {
          $this->setConfigValue($parameter, $real);
          return (new DTO\ValueResponse(value: $real, messages: [$okMessage]))->response();
        }
        try {
          $url = null;
          if (empty($saved) || $force) {
            if ($this->configCheckService->checkProjectFolder($prefixFolder . $real)) {
              $this->setConfigValue($parameter, $real);
              try {
                $folderLink = $this->userStorage->getFilesAppLink($sharedFolder . $prefixFolder . $real);
              } catch (Throwable $t) {
                // don't care
                $this->logException($t);
              }
              switch ($parameter) {
                case ConfigConstants::POSTBOX_FOLDER:
                  try {
                    $url = $this->configCheckService->checkLinkSharedFolder(
                      $sharedFolder . $prefixFolder . $real
                    );
                    $this->setConfigValue($parameter . 'ShareLink', $url);
                  } catch (Throwable $t) {
                    $this->logException($t);
                  }
                  break;
                case ConfigConstants::DOCUMENT_TEMPLATES_FOLDER:
                  $skeletonPaths = $this->projectService->getProjectSkeletonPaths();
                  foreach ($skeletonPaths as $skeletonPath) {
                    $this->configCheckService->checkProjectFolder($skeletonPath);
                  }
                  break;
                case ConfigConstants::FINANCE_FOLDER:
                  $taxOfficeFolder = $this->getTaxAuthoritiesPath();
                  $this->configCheckService->checkProjectFolder($taxOfficeFolder);
                  break;
              }
              return DTO\FolderValueResponse::fromArray([
                'value' => $real,
                'url' => $url,
                'message' => $this->l->t('Created and shared new folder "%s".', $prefixFolder . $real),
                'folderLink' => $folderLink,
              ])->response();
            } else {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Failed to create new shared folder "%s".', $prefixFolder . $real),
              );
            }
          } elseif ($real != $saved) {
            throw new Exceptions\EnduserNotificationException(
              $saved . ' != ' . $real,
            );
          } elseif ($this->configCheckService->checkProjectFolder($prefixFolder . $actual)) {
            try {
              $folderLink = $this->userStorage->getFilesAppLink($sharedFolder . $prefixFolder . $actual);
            } catch (Throwable $t) {
              // don't care
            }

            switch ($parameter) {
              case ConfigConstants::POSTBOX_FOLDER:
                try {
                  $url = $this->configCheckService->checkLinkSharedFolder(
                    $sharedFolder . UserStorage::PATH_SEP . $prefixFolder . $real
                  );
                  $this->setConfigValue($parameter . 'ShareLink', $url);
                } catch (Throwable $t) {
                  $this->logException($t);
                }
                break;
              case ConfigConstants::DOCUMENT_TEMPLATES_FOLDER:
                $skeletonPaths = $this->projectService->getProjectSkeletonPaths();
                foreach ($skeletonPaths as $skeletonPath) {
                  $this->configCheckService->checkProjectFolder($skeletonPath);
                }
                break;
              case ConfigConstants::FINANCE_FOLDER:
                $taxOfficeFolder = $this->getTaxAuthoritiesPath();
                $this->configCheckService->checkProjectFolder($taxOfficeFolder);
                break;
            }
            return DTO\FolderValueResponse::fromArray([
              'value' => $actual,
              'url' => $url,
              'message' => $this->l->t('"%s" which is configured as "%s" exists and is usable.', [$parameter, $prefixFolder . $actual]),
              'folderLink' => $folderLink,
            ])->response();
          } else {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('"%s" does not exist or is unaccessible.', $prefixFolder . $actual),
            );
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Failure checking folder "%s", caught an exception.', $prefixFolder . $real),
            previous: $t,
          );
        }
      case (array_filter(
        array_keys(ConfigConstants::CALENDARS),
        fn(string $uri) => $uri . ConfigConstants::CALENDAR_KEY_POSTFIX == $parameter,
      )[0] ?? '') . ConfigConstants::CALENDAR_KEY_POSTFIX:
        $real = trim($value);
        $uri = substr($parameter, 0, -strlen(ConfigConstants::CALENDAR_KEY_POSTFIX));
        //$saved = $value[$parameter . '-saved'];
        //$force = filter_var($value[$parameter . '-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        $actual = $this->getConfigValue($parameter);
        $actualId = $this->getConfigValue($uri . ConfigConstants::CALENDAR_ID_KEY_POSTFIX);
        try {
          $newId = $this->configCheckService->checkSharedCalendar($uri, $real, $actualId);
          if ($newId > 0) {
            $this->setConfigValue($parameter, $real);
            $this->setConfigValue($uri . ConfigConstants::CALENDAR_ID_KEY_POSTFIX, $newId);
            return (new DTO\NameIdValueResponse(
              message: $newId != $actualId
              ? $this->l->t('Created and shared new calendar "%s".', [$real])
              : $this->l->t('Validated shared calendar "%s".', [$real]),
              value: ['name' => $real, 'id' => $newId],
            ))->response();
          } else {
            return self::grumble($this->l->t('Failed to create new shared calendar "%s".', [$real]));
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Failure checking calendar "%s", caught an exception.', $real),
            previous: $t,
          );
        }
      case ConfigConstants::GENERAL_ADDRESS_BOOK_KEY:
        $real = trim($value);
        $uri = substr($parameter, 0, -strlen(ConfigConstants::ADDRESS_BOOK_POSTFIX));
        //$saved = $value[$parameter . '-saved'];
        //$force = filter_var($value[$parameter . '-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        $actual = $this->getConfigValue($parameter);
        $actualId = $this->getConfigValue($parameter . ConfigConstants::ID_POSTFIX);
        try {
          $newId = $this->configCheckService->checkSharedAddressBook($uri, $real, $actualId);
          if ($newId <= 0) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Failed to create new shared address book "%s".', [$real]),
            );
          }
          $this->setConfigValue($parameter, $real);
          $this->setConfigValue($parameter.'id', $newId);
          return (new DTO\NameIdValueResponse(
            message: $this->l->t('Created and shared new address book "%s".', $real),
            value: ['name' => $real, 'id' => $newId],
          ))->response();
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Failure checking address book "%s", caught an exception.', $real),
            previous: $t,
          );
        }

      case ConfigConstants::MUSICIANS_ADDRESS_BOOK_KEY:
        $real = trim($value);
        $this->setConfigValue($parameter, $real);
        $addressBook = $this->appContainer->query(AddressBookProvider::class)->getContactsAddressBook();
        $this->setConfigValue($parameter . ConfigConstants::ID_POSTFIX, $addressBook->getKey());
        if (empty($real)) {

          $real = $addressBook->getDisplayName();
          $message = $this->l->t('Display name of musicians-addressbook reset to "%s".', $real);
        } else {
          $message = $this->l->t('Display name of musicians-addressbook set to "%s".', $real);
        }
        if ($addressBook->getDisplayName() != $real) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Unable to set display-name of musicians-addressbook to "%s", it remains at "%s".',
              [ $real, $addressBook->getDisplayName() ],
            ),
          );
        }
        return (new DTO\NameIdValueResponse(
          message: $message,
          value: [ 'name' => $addressBook->getDisplayName(), 'id' => $addressBook->getKey() ],
        ))->response();

      case ConfigConstants::EVENT_DURATION_KEY:
        $realValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($realValue === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $parameter]));
        }
        $this->setUserValue($parameter, $realValue);
        return (new DTO\MessagesResponse([$this->l->t('Setting %2$s to %1$s minutes.', [$realValue, $parameter])]))->response();

      case EnumSimpleSettingsKey::IMPORT_CLUB_MEMBERS_AS_CLOUD_USERS->value:
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === null) {
          return self::grumble($this->l->t('Value "%s" for set "%s" is not convertible to boolean.', [$value, $parameter]));
        }
        $stringValue = $realValue ? 'on' : 'off';
        $hints = '';
        try {
          $cloudUserViewsDatabase = $this->getConfigValue('cloudUserViewsDatabase', null);
          /** @var CloudUserConnectorService $userConnectorService */
          $userConnectorService = $this->di(CloudUserConnectorService::class);
          list('hints' => $hints ,) = $userConnectorService->checkRequirements($cloudUserViewsDatabase);
          if ($realValue) {
            $userConnectorService->updateUserSqlViews($cloudUserViewsDatabase);
            $userConnectorService->writeUserSqlConfig($cloudUserViewsDatabase);
            // following can fail
            try {
              $userConnectorService->configureCloudUserBackend();
              $userConnectorService->setCloudUserSubAdmins();
            } catch (Throwable $t) {
              $this->logException($t, 'Unable to configure "' . CloudUserConnectorService::CLOUD_USER_BACKEND . '".');
            }
          } else {
            // following can fail
            try {
              $userConnectorService->setCloudUserSubAdmins(delete: true);
              $userConnectorService->configureCloudUserBackend(erase: true);
            } catch (Throwable $t) {
              $this->logException($t, 'Perhaps unable to deconfigure "' . CloudUserConnectorService::CLOUD_USER_BACKEND . '".');
            }
            // remove remnants
            $userConnectorService->removeUserSqlViews($cloudUserViewsDatabase);
            $userConnectorService->writeUserSqlConfig($cloudUserViewsDatabase, delete: true);
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $realValue
            ? $this->l->t('Unable to configure the club-member\'s cloud user backend.')
            : $this->l->t('Unable to disable the club-member\'s cloud user backend.'),
            previous: $t,
          );
        }
        return $this->setSimpleConfigValue($parameter, $stringValue, furtherData: [
          'messages' => $hints,
          'hints' => $hints,
        ]);

      case 'userSqlBackendRecreateViews':
        $hints = '';
        try {
          $cloudUserViewsDatabase = $this->getConfigValue('cloudUserViewsDatabase', null);
          /** @var CloudUserConnectorService $userConnectorService */
          $userConnectorService = $this->di(CloudUserConnectorService::class);
          list('hints' => $hints,) = $userConnectorService->checkRequirements($cloudUserViewsDatabase);
          $userConnectorService->updateUserSqlViews($cloudUserViewsDatabase);
          $userConnectorService->writeUserSqlConfig($cloudUserViewsDatabase);

          // also try to re-grant the sub-admin right and reconfigure user-sql
          try {
            $userConnectorService->configureCloudUserBackend();
            $userConnectorService->setCloudUserSubAdmins();
          } catch (Throwable $t) {
            throw new Exceptions\EnduserNotificationException(
              // TRANSLATORS: Parameter is the name of a user backend.
              message: $this->l->t('Unable to configure "%1$s".', CloudUserConnectorService::CLOUD_USER_BACKEND),
              previous: $t,
            );
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Unable to recreate the views for the cloud user backend.'),
            previous: $t,
          );
        }
        $messages = array_merge(
          [ $this->l->t('Cloud-user-views have been regenerated successfully.'), ],
          $hints,
        );
        return (new DTO\MessagesResponse(messages: $messages, hints: $hints))->response();

      case EnumSimpleSettingsKey::CLOUD_USER_VIEWS_DATABASE->value:
        $newValue = Util::normalizeSpaces($value);
        $oldValue = $this->getConfigValue($parameter, null);
        $this->logInfo('OLD / NEW ' . $oldValue . ' / ' . $newValue);
        $hints = '';
        if ($newValue != $oldValue) {
          try {
            /** @var CloudUserConnectorService $userConnectorService */
            $userConnectorService = $this->di(CloudUserConnectorService::class);
            list('hints' => $hints,) = $userConnectorService->checkRequirements($newValue);
            $userConnectorService->removeUserSqlViews($oldValue);
            $userConnectorService->updateUserSqlViews($newValue);
            $userConnectorService->writeUserSqlConfig($newValue);

            $userConnectorService->removeMusicianPersonalizedViews($oldValue);
            $userConnectorService->updateMusicianPersonalizedViews($newValue);
          } catch (Throwable $t) {
            throw new Exceptions\EnduserNotificationException(
              message: $this->l->t('Unable to reconfigure the database for the cloud - orchestra member interaction.'),
              previous: $t,
            );
          }
        }
        return $this->setSimpleConfigValue(
          $parameter,
          $value,
          messages: $hints,
          hints: $hints,
        );

      case EnumSimpleSettingsKey::MUSICIAN_PERSONALIZED_VIEWS->value:
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === null) {
          return self::grumble($this->l->t('Value "%s" for set "%s" is not convertible to boolean.', [$value, $parameter]));
        }
        $stringValue = $realValue ? 'on' : 'off';
        $hints = '';
        try {
          $cloudUserViewsDatabase = $this->getConfigValue('cloudUserViewsDatabase', null);
          /** @var CloudUserConnectorService $userConnectorService */
          $userConnectorService = $this->di(CloudUserConnectorService::class);
          list('hints' => $hints,) = $userConnectorService->checkRequirements($cloudUserViewsDatabase);
          if ($realValue) {
            $userConnectorService->updateMusicianPersonalizedViews($cloudUserViewsDatabase);
          } else {
            $userConnectorService->removeMusicianPersonalizedViews($cloudUserViewsDatabase);
          }
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $realValue
            ? $this->l->t('Unable to regenerate the views for the personal access of club-members to their data.')
            : $this->l->t('Unable to remove the view for the personal access of club-members to their data.'),
            previous: $t,
          );
        }
        return $this->setSimpleConfigValue(
          $parameter,
          value: $stringValue,
          messages: $hints,
          hints: $hints,
        );

      case 'musicianPersonalizedViewsRecreateViews':
        $hints = '';
        try {
          $cloudUserViewsDatabase = $this->getConfigValue('cloudUserViewsDatabase', null);
          /** @var CloudUserConnectorService $userConnectorService */
          $userConnectorService = $this->di(CloudUserConnectorService::class);
          list('hints' => $hints,) = $userConnectorService->checkRequirements($cloudUserViewsDatabase);
          $userConnectorService->updateMusicianPersonalizedViews($cloudUserViewsDatabase);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Unable to regenerate the views for the personal access of club-members to their data.'),
            previous: $t,
          );
        }
        $messages = array_merge(
          [ $this->l->t('Personalized single-row database-views have been regenerated successfully.'), ],
          $hints,
        );
        return (new DTO\MessagesResponse(
          messages: $messages,
          hints: $hints,
        ))->response();

      case 'keydistribute':
        list('status' => $status, 'messages' => $messages) = $this->distributeEncryptionKey();
        $this->logInfo('STATUS ' . (int)$status . ' ' . print_r($messages, true));
        return (new DTO\MessagesResponse($messages))->response($status);

      case 'emailtest':
        $user = $this->getConfigValue(ConfigConstants::EMAIL_USER);
        $password = $this->getConfigValue(ConfigConstants::EMAIL_PASSWORD);
        $messages = [];
        $check = [];
        foreach (self::EMAIL_PROTO as $proto) {
          $server = $this->getConfigValue($proto.'server');
          $port = $this->getConfigValue($proto.'port');
          $security = $this->getConfigValue($proto.'security');

          $method = 'check'.ucfirst($proto).'Server';
          $check[$proto] = $this->configCheckService->$method(
            $server, $port, $security, $user, $password);
          $messages[$proto] = ($check[$proto] === true)
            ? $this->l->t('%s connection seems functional.', strtoupper($proto))
            : ($this->l->t('Unable to establish %s connection to %s@%s:%d', [ strtoupper($proto), $user, $server, $port ]));
        }
        $message = implode(' ', $messages);
        if ($check['smtp'] === true && $check['imap'] === true) {
          return self::response($message);
        } else {
          return self::grumble($message);
        }
      case EnumSimpleSettingsKey::SMTP_SERVER->value:
      case EnumSimpleSettingsKey::IMAP_SERVER->value:
      case EnumSimpleSettingsKey::SMTP_PORT->value:
      case EnumSimpleSettingsKey::IMAP_PORT->value:
      case EnumSimpleSettingsKey::SMTP_SECURITY->value:
      case EnumSimpleSettingsKey::IMAP_SECURITY->value:
        $realValue = Util::normalizeSpaces($value);
        $proto = substr($parameter, 0, 4);
        $key = substr($parameter, 4);
        switch ($key) {
          case 'server':
            if (!empty($realValue) && !checkdnsrr($realValue, 'A') && !checkdnsrr($realValue, 'AAAA')) {
              return self::grumble($this->l->t('Server name "%s" has neither an IPV4 nor an IPV6 address', $realValue));
            }
            return $this->setSimpleConfigValue($parameter, $realValue);

          case 'port':
            if (empty($realValue)) {
              $security = $this->getConfigValue($proto.'security');
              if (!empty($security)) {
                // just some port is needed
                $realValue = self::EMAIL_PORTS[$proto][$security];
              }
            } elseif (filter_var($realValue, FILTER_VALIDATE_INT, ['options' => [ 'min_range' => 1, 'max_range' => 65535]]) === false) {
              return self::grumble(
                $this->l->t(
                  '"%s" is not an integral number in the range [%d, %d]', [ $realValue, 1, 65535 ]));
            }
            return $this->setSimpleConfigValue($parameter, $realValue);

          case 'security':
            if (empty($realValue)) {
              return $this->setSimpleConfigValue($parameter, $realValue);
            }
            if (array_search($realValue, self::EMAIL_SECURITY) === false) {
              return self::grumble($this->l->t('Unknown transport security method: "%s".', $realValue));
            }
            $port = self::EMAIL_PORTS[$proto][$realValue];
            $this->setConfigValue($parameter, $realValue);
            $this->setConfigValue($proto.'port', $port);
            return self::dataResponse([
              'messages' => [
                $this->l->t(
                  'Using transport security "%s" for protocol "%s".',
                  [ $realValue, $proto ],
                )],
              'proto' => $proto,
              'port' => $port,
            ]);
        }
        break;
      case EnumSimpleSettingsKey::EMAIL_TEST_MODE->value:
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === null) {
          return self::grumble($this->l->t('Value "%s" for set "%s" is not convertible to boolean.', [$value, $parameter]));
        }
        $stringValue = $realValue ? 'on' : 'off';
        return $this->setSimpleConfigValue($parameter, $stringValue);

      case 'announcementsMailingListAutoconf':
        /** @var MailingListsService $listsService */
        $listsService = $this->di(MailingListsService::class);
        $announcementsMailingList = $this->getConfigValue(ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY);
        if (empty($announcementsMailingList)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Please configure the mailing-list address first, otherwise I do not know which list I have to work on.'),
          );
        }
        $announcementsMailingListName = $this->getConfigValue(ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY, null);
        $owners = array_filter([ $this->getConfigValue(ConfigConstants::MAILING_LIST_CONFIG['owner']) ]);
        $moderators = array_filter([ $this->getConfigValue(ConfigConstants::MAILING_LIST_CONFIG['moderator']) ]);
        $posters = $moderators;
        $listInfo = $listsService->getListInfo($announcementsMailingList);
        if ($listInfo === null) {
          $listsService->createList($announcementsMailingList);
        }
        $listsService->configureAnnouncementsList($announcementsMailingList, $announcementsMailingListName, $owners, $moderators, $posters);

        // install message templates
        $templates = $listsService->installListTemplates($announcementsMailingList, MailingListsService::TEMPLATE_TYPE_ANNOUNCEMENTS);

        return self::response(
          $this->l->t('Autoconfiguration of the mailing list "%1$s" successful, owner set to "%2$s", moderator and allowed poster set to "%3$s", specialized auto-responses: %4$s.', [
            $announcementsMailingList, $owners[0] ?? '', $posters[0] ?? '',
            empty($templates) ? $this->l->t('none') : implode(', ', $templates),
          ])
        );

      case EnumSimpleSettingsKey::ANNOUNCEMENTS_MAILING_LIST_KEY->value:
      case EnumSimpleSettingsKey::EMAIL_TEST_ADDRESS_KEY->value:
      case EnumSimpleSettingsKey::EMAIL_FROM_ADDRESS_KEY->value:
        $realValue = Util::normalizeSpaces($value);
        try {
          $parsedEmail = $this->emailAddressService->parseAddressString($realValue);
        } catch (Exceptions\EnduserNotificationException $e) {
          return self::grumble($this->l->t('Unable to parse email address "%1$s": %2$s', [ $value, $e->getMessage() ]));
        }
        if (count($parsedEmail) !== 1) {
          return self::grumble($this->l->t('"%s" seems to contain multiple email addresses, only a single address is allowed here.', $value));
        }
        $realValue = array_key_first($parsedEmail);
        $displayName = reset($parsedEmail);
        $messages = [];
        if (!empty($displayName)) {
          $humanValue = $displayName . ' <' . $realValue . '>';
          switch ($parameter) {
            case EnumSimpleSettingsKey::ANNOUNCEMENTS_MAILING_LIST_KEY->value:
              $this->setConfigValue(ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY, $displayName);
              break;
            case EnumSimpleSettingsKey::EMAIL_TEST_ADDRESS_KEY->value:
              $this->setConfigValue(ConfigConstants::EMAIL_TEST_NAME_KEY, $displayName);
              break;
            case EnumSimpleSettingsKey::EMAIL_FROM_ADDRESS_KEY->value:
              $this->setConfigValue(ConfigConstants::EMAIL_FROM_NAME_KEY, $displayName);
              break;
          }
        }
        if ($parameter === ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY) {
          /** @var MailingListsService $listsService */
          $listsService = $this->di(MailingListsService::class);
          try {
            $listInfo = $listsService->getListInfo($realValue);
            $this->logInfo('LIST INFO ' . print_r($listInfo, true));
          } catch (Throwable $t) {
            $logMessage = $this->l->t(
              'The Mailing list "%1$s" does not seem to exist on the configured mailing-list service.',
              $realValue,
            );
            $this->logException($t, $logMessage);
            /** @var MailingListsService $listsService */
            $listsService = $this->di(MailingListsService::class);
            $messages[] = $logMessage;
          }
          // try to create the template folder even if the list does not exist
          $shareUri = $listsService->ensureTemplateFolder($this->l->t('announcements'));

          $messages = $this->l->t('Link-shared auto-responses directory for the announcements mailing list is "%s".', $shareUri);
          $this->logInfo('SHARE URI ' . $shareUri);
        }
        $key = EnumSimpleSettingsKey::get($parameter);
        $this->setSimpleConfigValue($key, $realValue, $humanValue, $messages);
        return (new DTO\SimpleSetValueResponse(
          key: $key,
          value: $humanValue,
          messages: $messages,
        ))->response();

      case EnumSimpleSettingsKey::EMAIL_FROM_DOMAIN_KEY->value:
        $emailFromDomain = $this->getConfigValue(ConfigConstants::EMAIL_FROM_DOMAIN_KEY);
        if (empty($emailFromDomain)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Please first configure the email domain you intend to send messages form.'),
          );
        }
        $messages = [];
        // Nowadays we use SSO, so make sure RCmail has the correct domain and
        // is configured to use SSO.
        $roundCubeConfig = $this->appContainer->get(RoundCubeConfig::class);
        $roundCubeConfig->setAppValue(
          RoundCubeConfig::EMAIL_ADDRESS_CHOICE,
          RoundCubeConfig::EMAIL_ADDRESS_CHOICE_USER_ID,
        );
        $roundCubeConfig->setAppValue(
          RoundCubeConfig::EMAIL_DEFAULT_DOMAIN,
          $emailFromDomain,
        );
        $messages[] = $this->l->t(
          'Configured the Roundcube mailer for SSO using the current user\'s user-id and credentials with the configure email-domain "%1$s".',
          $emailFromDomain,
        );
        // fallthrough
      case EnumSimpleSettingsKey::ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY->value:
      case EnumSimpleSettingsKey::BULK_EMAIL_SUBJECT_TAG->value:
      case EnumSimpleSettingsKey::EMAIL_USER->value:
      case EnumSimpleSettingsKey::EMAIL_PASSWORD->value:
        $messages = $messages ?? [];
        return $this->setSimpleConfigValue($parameter, $realValue ?? $value, humanValue: $humanValue ?? null, messages: $messages);

      case EnumSimpleSettingsKey::BULK_EMAIL_PRIVACY_NOTICE->value:
        $value = $this->fuzzyInputService->purifyHTML($value);
        return $this->setSimpleConfigValue($parameter, $value);

      case ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS:
      case ConfigConstants::PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY:
      case ConfigConstants::CLOUD_ATTACHMENT_ALWAYS_LINK:
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === null) {
          return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not convertible to boolean.', [$value, $parameter]));
        }
        $stringValue = $realValue ? 'on' : 'off';
        $this->setUserValue($parameter, $stringValue);
        return self::response($this->l->t('Switching %2$s %1$s', [
          $this->l->t($stringValue),
          $this->l->t($parameter),
        ]));

      case EnumSimpleSettingsKey::ATTACHMENT_LINK_EXPIRATION_LIMIT->value:
        $interval = $this->fuzzyInputService->dateIntervalValue($value);
        if (!empty($interval)) {
          // try to at least have some slightly useful number of days for things
          // like "one year"
          $now = new DateTimeImmutable;
          $realValue = $now->diff($now->add($interval))->format('%a');
          // $realValue = $interval->total('days');
          $humanValue = $this->l->t('%d days', $realValue);
        } else {
          $realValue = $humanValue = null;
        }
        return $this->setSimpleConfigValue($parameter, $realValue, $humanValue);

      case EnumSimpleSettingsKey::ATTACHMENT_LINK_SIZE_LIMIT->value:
        $realValue = $this->fuzzyInputService->storageValue($value);
        $humanValue = empty($realValue)
          ? null
          : $this->humanFileSize($realValue);
        return $this->setSimpleConfigValue($parameter, $realValue, $humanValue);

      case (in_array($parameter, ConfigConstants::MAILING_LIST_CONFIG) ? $parameter : null):
        return $this->setSimpleConfigValue($parameter, $value);

      case (in_array($parameter, ConfigConstants::MAILING_LIST_REST_CONFIG) ? $parameter : null):
        /** @var string $mailingListRestUrl */
        foreach (ConfigConstants::MAILING_LIST_REST_CONFIG as $listConfig) {
          ${$listConfig} = $this->getConfigValue($listConfig);
        }
        ${$parameter} = Util::normalizeSpaces($value);
        $all = true;
        foreach (ConfigConstants::MAILING_LIST_REST_CONFIG as $listConfig) {
          if (empty(${$listConfig})) {
            $all = false;
            break;
          }
        }
        $messages = [];
        if ($all) {
          $oldValue = $this->getConfigValue($parameter);
          $this->setConfigValue($parameter, ${$parameter});
          try {
            /** @var MailingListsService $listsService */
            $listsService = $this->di(MailingListsService::class);
            if (empty($listsService->getServerConfig())) {
              $this->setConfigValue($parameter, $oldValue);
              throw new Exceptions\EnduserNotificationException(
                message: $this->l->t('Unable to connect to mailing list service at "%s"', $mailingListRestUrl),
              );
            }
          } catch (Throwable $t) {
            $this->setConfigValue($parameter, $oldValue);
            throw new Exceptions\EnduserNotificationException(
              message: $this->l->t('Unable to connect to mailing list service at "%s"', $mailingListRestUrl),
              previous: $t,
            );
          }

          // try to generate the template directories
          // try to create the template folder even if the list does not exist
          $shareUri = $listsService->ensureTemplateFolder($this->l->t('announcements'));
          $messages[] = $this->l->t('Link-shared auto-responses directory for the announcements mailing list is "%s".', $shareUri);
          $this->logInfo('SHARE URI ' . $shareUri);

          $shareUri = $listsService->ensureTemplateFolder($this->l->t('projects'));
          $messages[] = $this->l->t('Link-shared auto-responses directory for project mailing lists is "%s".', $shareUri);
          $this->logInfo('SHARE URI ' . $shareUri);
        }
        return $this->setSimpleConfigValue($parameter, $value, messages: $messages);

      case 'translation':
        if (empty($value['key']) || empty($value['language'])) {
          return self::grumble($this->l->t('Empty translation phrase or language'));
        }
        if (!isset($value['translation'])) {
          return self::grumble($this->l->t('Missing translation'));
        }
        $translation = Util::htmlEscape(Util::normalizeSpaces($value['translation']));
        if (empty($translation)) {
          return self::grumble($this->l->t('Empty translation for phrase "%s".', $key));
        }
        $language = $value['language'];
        if (strlen($language) < 2 || strlen($language) > 5) {
          return self::grumble($this->l->t('Language specifier must between 2 and 5 chars (e.g. de or en_US), got %s', $language));
        }
        $key = $value['key'];
        if (!$this->translationService->recordTranslation($key, $translation, $language)) {
          return self::grumble($this->l->t('Recording the translation failed'));
        }
        return self::response($this->l->t('Successfully recorded the given translation for the language "%s"', $language));
      case 'erase-translations':
        if (!$this->translationService->eraseTranslationKeys('*')) {
          return self::grumble($this->l->t('Failed to erase all recorded translations.'));
        } else {
          return self::response($this->l->t('All recorded translations have been erased.'));
        }
        break;
      case 'clouddev':
      case 'sourcedocs':
      case 'sourcecode':
      case 'phpmyadmincloud':
      case 'phpmyadmin':
      case 'cspfailurereporting':
        if (!empty($value)) {
          $realValue = filter_var($value, FILTER_VALIDATE_URL);
          if ($realValue == null) {
            return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not a valid URL.', [$value, $parameter]));
          }
          $components = parse_url($realValue);
          if ($components['scheme'] != 'http' && $components['scheme'] != 'https') {
            return self::grumble($this->l->t('"%1$s" must be a http(s) URL, scheme "%2$s" not supported.', [$value, $components['scheme']]));
          }
          $this->setConfigValue($parameter, $realValue);
        } else {
          $this->deleteConfigValue($parameter);
          $realValue = null;
        }
        $key = $parameter;
        $this->logDebug($key . ' => ' . $this->getConfigValue($key, null));
        return self::valueResponse($realValue, $this->l->t(' "%s" set to "%s".', [$parameter, $realValue]));
        // link to CMS, currently Redaxo4
      case 'redaxo'.str_replace('redaxo', '', $parameter):
        $redaxoKeys = [
          'Preview',
          'Archive',
          'Rehearsals',
          'Trashbin',
          'Template',
          'ConcertModule',
          'RehearsalsModule',
          'SubPageTemplate',
        ];
        $key = str_replace('redaxo', '', $parameter);
        if (array_search($key, $redaxoKeys) === false) {
          return self::grumble($this->l->t('Unknown configuation key %s', [ $parameter ]));
        }
        $realValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($realValue === false) {
          return self::grumble($this->l->t('Value "%s" for setting "%s" is not in the allowed range.', [$value, $parameter]));
        }
        $this->setConfigValue($parameter, $realValue);
        return self::valueResponse(
          $realValue,
          $this->l->t('Redaxo categorie Id for "%s" set to "%s".', [ $key, $realValue ])
        );
      default:
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * Get some stuff.
   *
   * @param string $parameter
   *
   * @return Http\Response
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::BASE_PATH . '/' . self::END_POINT_GET . '/{parameter}',

  )]
  public function get(string $parameter):Http\Response
  {
    switch ($parameter) {
      case 'locale-info':
        $localeInfo = $this->generateLocaleInfo($this->request->getParam('scope'));
        return self::dataResponse([
          'contents' => $localeInfo,
        ]);
      case 'passwordgenerate':
      case 'generatepassword':
        return self::valueResponse($this->generateRandomBytes(32));
      case ConfigConstants::DOCUMENT_TEMPLATES_FOLDER:
        $sharedFolder = $this->getConfigValue(ConfigConstants::SHARED_FOLDER);
        if (empty($sharedFolder)) {
          return self::grumble($this->l->t('Shared folder is not configured.'));
        }
        $templatesFolder = $this->getConfigValue(ConfigConstants::DOCUMENT_TEMPLATES_FOLDER);
        if (empty($templatesFolder)) {
          return self::grumble($this->l->t('Document template folder is not configured.'));
        }
        $templatesFolder = UserStorage::PATH_SEP
          . $sharedFolder . UserStorage::PATH_SEP;
        return self::dataResponse($templatesFolder);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * @param null|string $scope
   *
   * @return string HTML fragment.
   */
  private function generateLocaleInfo(?string $scope = null):string
  {
    $scope = $scope ?? 'personal';
    $localeSymbol = $scope == 'personal' ? $this->getLocale() : $this->appLocale();
    $templateParameters = [
      'dateTimeZone' => $this->getDateTimeZone(),
      'localeSymbol' => $localeSymbol,
      'currencyCode' => $this->currencyCode($localeSymbol),
      'currencySymbol' => $this->currencySymbol($localeSymbol),
      'l10n' => $scope == 'personal' ? $this->l : $this->appL10n(),
      'dateTimeFormatter' => $this->dateTimeFormatter(),
    ];

    $tmpl = $this->templateResponse(
      'settings/part.locale-info',
      $templateParameters,
    );
    return $tmpl->render();
  }

  /**
   * Get app settings.
   *
   * @param string|EnumSettingsGetApp $parameter
   *
   * @return Http\Response
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[Attributes\SubAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::BASE_PATH . '/' . self::END_POINT_APP_GET . '/{parameter}',
  )]
  #[CoreAttributes\NoAdminRequired]
  public function getApp(string|EnumSettingsGetApp $parameter): Http\Response
  {
    $parameter = EnumSettingsGetApp::get($parameter);

    switch ($parameter) {
      case EnumSettingsGetApp::LOCALE_INFO:
        $localeInfo = $this->generateLocaleInfo($this->request->getParam('scope'));
        return new Http\DataResponse([
          'contents' => $localeInfo,
        ]);

      case EnumSettingsGetApp::TRANSLATION_TEMPLATES:
        $pot = $this->translationService->generateCatalogueTemplates();

        $fileName = $this->appName().'-'.$this->timeStamp().'.pot';

        return new Http\DataDownloadResponse($pot, $fileName, 'text/plain');

      case EnumSettingsGetApp::AUTO_FILL_TEST:
      case EnumSettingsGetApp::AUTO_FILL_TEST_DATA:
        $templateName = $this->request->getParam('documentTemplate');
        if (empty(ConfigConstants::DOCUMENT_TEMPLATES[$templateName])
            || ConfigConstants::DOCUMENT_TEMPLATES[$templateName]['type'] != ConfigConstants::DOCUMENT_TYPE_TEMPLATE) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unknown auto-fill template: "%s".', $templateName),
          );
        }
        $format = $this->request->getParam('format');

        $project = $this->projectService->findById($this->getClubMembersProjectId());
        $this->entityManager = $this->di(EntityManager::class);
        $flatProject = EntityArrayAdapter::create($project, depth: 1);

        /** @var InstrumentationService $instrumentationService */
        $instrumentationService = $this->di(InstrumentationService::class);
        $dummyRecipient = $instrumentationService->getDummyMusician(project: $project);
        $flatRecipient = EntityArrayAdapter::create($dummyRecipient, depth: 1);

        /** @var OpenDocumentFiller $documentFiller */
        $documentFiller = $this->di(OpenDocumentFiller::class);

        /** @var \OCA\CAFEVDB\Common\NumberFormatter $numberFormatter */
        $numberFormatter = new NumberFormatter($this->appLocale());

        $templateData = null;
        $blocks = [
          'sender' => 'org.treasurer',
        ];

        $makePayment = fn(float $value, string $toolTip) => [
          'amount' => $value,
          'isIncome' => (int)($value > 0),
          'subject' => $this->toolTipsService()['mailmerge:examples:finance:' . $toolTip],
          'l10n' => [
            'locale' => $this->appLocale(),
            'amount' => $numberFormatter->formatCurrency($value),
            'amountText' => $numberFormatter->currencyToWords($value),
            'absAmount' => $numberFormatter->formatCurrency(abs($value)),
            'absAmountText' => $numberFormatter->currencyToWords(abs($value)),
          ],
        ];
        $makeCompositePayment = function(
          DateTimeInterface $dateOfReceipt,
          array $payments,
        ) use (
          $numberFormatter,
        ):array {
          $amount = round(
            array_reduce($payments, fn(float $carry, array $payment) => $payment['amount'] + $carry, 0.0),
            2,
          );
          return [
            'dateOfReceipt' => $dateOfReceipt,
            'amount' => $amount,
            'l10n' => [
              'locale' => $this->appLocale(),
              'amount' => $numberFormatter->formatCurrency($amount),
              'amountText' => $numberFormatter->currencyToWords($amount),
              'absAmount' => $numberFormatter->formatCurrency(abs($amount)),
              'absAmountText' => $numberFormatter->currencyToWords(abs($amount)),
            ],
            'payments' => $payments,
          ];
        };

        switch ($templateName) {
          case ConfigConstants::DOCUMENT_TEMPLATE_STANDARD_LETTER:
            $templateData = [
              'project' => $flatProject,
              'recipient' => $flatRecipient,
            ];
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_DONATION_RECEIPT:
            $income = 13.57;
            $expenses = -13.57;
            $amount = round($income + $expenses, 2);
            $dateOfReceipt = (new DateTime('- 17 days'));
            $templateData = [
              'recipient' => $flatRecipient,
              'project' => $flatProject,
              'donation' => [
                'amount' => $income,
                'dateOfReceipt' => $dateOfReceipt,
                'isWaivingOfReimbursement' => (int)($amount == 0),
                'l10n' => [
                  'locale' => $this->appLocale(),
                  'amount' => $numberFormatter->formatCurrency($income),
                  'amountText' => $numberFormatter->currencyToWords($income),
                ],
              ],
              'payment' => $makeCompositePayment(
                $dateOfReceipt, [
                  $makePayment($expenses, 'donation:expensesSubject'),
                  $makePayment($income, 'donation:incomeSubject'),
                ],
              ),
            ];
            $blocks['corporateIncomeTaxExemption'] = 'org.taxAuthorities.exemptionNotices.corporateIncomeTax';
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_STANDARD_RECEIPT:
            $income = 42.57;
            $expenses = -12.13;
            $amount = round($income + $expenses, 2);
            $dateOfReceipt = (new DateTime('- 17 days'));
            $templateData = [
              'recipient' => $flatRecipient,
              'project' => $flatProject,
              'payment' => $makeCompositePayment(
                $dateOfReceipt, [
                  $makePayment($expenses, 'receipt:expensesSubject'),
                  $makePayment($income, 'receipt:incomeSubject'),
                ],
              ),
            ];
            $blocks['corporateIncomeTaxExemption'] = 'org.taxAuthorities.exemptionNotices.corporateIncomeTax';
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_INVOICE:
            $amount = 13.57;
            $gracePeriodDays = 14;
            DateInterval::setLocale($this->getLanguage($this->appLocale()));
            $gracePeriodText = (string)DateInterval::days($gracePeriodDays);
            // für das Engagement unseres Orchesters für die Konzerte am
            // 22.10.2022 (Maria Hilf Kirche Freiburg), 23.10.2022
            // (Ev. Pfarrkirche Ihringen) und am 29.10.2022 (Grand Kursaal
            // Besançon)
            $subject = $this->toolTipsService()['mailmerge:examples:finance:invoice:subject'];
            $purpose = $this->toolTipsService()['mailmerge:examples:finance:invoice:purpose'];
            $templateData = [
              'project' => $flatProject,
              'recipient' => $flatRecipient,
              'invoice' => [
                'amount' => $amount,
                'gracePeriodDays' => $gracePeriodDays,
                'number' => $this->financeService->generateInvoiceNumber($dummyRecipient, $project),
                'purpose' => $purpose,
                'subject' => $subject,
                'l10n' => [
                  'locale' => $this->appLocale(),
                  'amount' => $numberFormatter->formatCurrency($amount),
                  'amountText' => $numberFormatter->currencyToWords($amount),
                  'gracePeriod' => $gracePeriodText,
                ],
              ],
            ];
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE:
            if ($format == 'pdf') {
              list($fileData, $mimeType, $fileName) = $this->financeService->preFilledDebitMandateForm(
                $dummyRecipient->getSepaBankAccounts()->first(),
                $this->getExecutiveBoardProjectId(),
                musicianOrId: $dummyRecipient,
                formName: $templateName
              );
            } else {
              $templateData = [];
            }
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE:
            if ($format == 'pdf') {
              list($fileData, $mimeType, $fileName) = $this->financeService->preFilledDebitMandateForm(
                $dummyRecipient->getSepaBankAccounts()->first(),
                $this->getClubMembersProjectId(),
                musicianOrId: $dummyRecipient,
                formName: $templateName,
              );
            } else {
              $templateData = [];
            }
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE:
            if ($format == 'pdf') {
              list($fileData, $mimeType, $fileName) = $this->financeService->preFilledDebitMandateForm(
                $dummyRecipient->getSepaBankAccounts()->first(),
                $this->getClubMembersProjectId(),
                musicianOrId: $dummyRecipient,
                formName: $templateName
              );
            } else {
              $templateData = [];
            }
            break;
          case ConfigConstants::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD:
            /** @var InstrumentInsuranceService $insuranceService */
            $insuranceService = $this->di(InstrumentInsuranceService::class);
            $dummyRecipient = $insuranceService->getDummyMusician();
            $insuranceOverview = $insuranceService->musicianOverview($dummyRecipient);

            // Prepare the data doing some translations first
            foreach ($insuranceOverview['musicians'] as &$insurance) {
              foreach ($insurance['items'] as &$item) {
                $item['scope'] = $this->l->t($item['scope']);
              }
            }

            $templateData = [
              'instins' => $insuranceOverview,
            ];
            $blocks['recipient'] = 'instins.billTo';
            break;
          default:
            throw new Exceptions\EnduserNotificationException(
              $this->l->t(
                'Auto-fill test for template "%s: not yet implemented, sorry.', $templateName,
              ),
            );
        }

        if ($parameter == EnumSettingsGetApp::AUTO_FILL_TEST) {
          if ($templateData !== null) {
            /** @var OpenDocumentFiller $documentFiller */
            $documentFiller = $this->di(OpenDocumentFiller::class);
            $templateFileName = $this->getDocumentTemplatesPath($templateName);
            if (empty($templateFileName)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t(
                  'There is no template file for template "%s"', $templateName,
                ),
              );
            }

            list($fileData, $mimeType, $fileName) = $documentFiller->fill(
              templateFileName: $templateFileName,
              templateData: $templateData,
              blocks: $blocks,
              asPdf: $format == str_contains($format, 'pdf'),
            );
          }

          $pathInfo = pathinfo($fileName);
          $fileName = implode('-', [
            $this->timeStamp(),
            $pathInfo['filename'],
            EnumSettingsGetApp::AUTO_FILL_TEST->value,
          ]) . '.' . $pathInfo['extension'];

          return new Http\DataDownloadResponse($fileData, $fileName, $mimeType);
        } else {
          /** @var OpenDocumentFiller $documentFiller */
          $documentFiller = $this->di(OpenDocumentFiller::class);
          $fillData = $documentFiller->fillData($templateData);

          $fillData['__blocks__'] = $blocks;

          $fileData = json_encode($fillData);
          $fileName = implode('-', [
            $this->timeStamp(),
            $templateName,
            EnumSettingsGetApp::AUTO_FILL_TEST_DATA->value,
          ])
            . '.' . 'json';
          $mimeType = 'application/json';

          return new Http\DataDownloadResponse($fileData, $fileName, $mimeType);
        }
    }
  }

  /**
   * @param string|EnumSimpleSettingsKey $key Config key to set.
   *
   * @param null|string $value Value to set. If empty config entry will be deleted.
   *
   * @param null|string $humanValue Human readable value for display in the
   * frontend (e.g. formatted floating point value, or boolean as text.
   *
   * @param array $messages "First-level" messages, to be displayed by toasts.
   *
   * @param array $hints Instructions, further info.
   *
   * @return Http\DataResponse|Http\JSONResponse
   */
  private function setSimpleConfigValue(
    string|EnumSimpleSettingsKey $key,
    ?string $value,
    ?string $humanValue = null,
    ?array &$messages = null,
    ?array $hints = null,
  ): Http\DataResponse|Http\JSONResponse {
    try {
      $key = EnumSimpleSettingsKey::get($key);
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Configuration key "%1$s" is not known. This is an internal error, please contact the server administrator.', $key),
        previous: $t,
        httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
      );
    }

    $realValue = Util::normalizeSpaces($value);
    if (empty($humanValue)) {
      $humanValue = $realValue;
    }

    if ($messages === null) {
      $messages = [];
    }

    if (empty($realValue)) {
      $this->deleteConfigValue($key->value);
      array_unshift($messages, $this->l->t('Erased config value for parameter "%s".', $key->value));
      return (new DTO\SimpleSetValueResponse(
        key: $key,
        value: null,
        messages: $messages,
        hints: $hints,
      ))->response();
    } else {
      $this->setConfigValue($key->value, $realValue);

      if (preg_match('/.*password.*/i', $key->value)) {
        $humanValue = $realValue = $realValue[0] . '••••••••';
      }
      $humanValue = htmlspecialchars($humanValue);
      array_unshift($messages, $this->l->t('Value for "%1$s" set to "%2$s"', [ $key->value, $humanValue ]));
      return (new DTO\SimpleSetValueResponse(
        key: $key,
        messages: $messages,
        hints: $hints,
        value: $realValue,
        humanValue: $humanValue,
      ))->response();
    }
  }

  /**
   * Distribute the encryption key to all users by storing them in
   * their personal preferences, encrypted with their SSL key-pair.
   *
   * @return array
   */
  private function distributeEncryptionKey():array
  {
    if (!$this->encryptionKeyValid()) {
      return [
        'status' => Http::STATUS_BAD_REQUEST,
        'messages' => [
          $this->l->t('App encryption key is invalid, will not distribute it.'),
        ],
      ];
    }
    $appEncryptionKey = $this->getAppEncryptionKey();
    $noKeyUsers = [];
    $fatalUsers = [];
    $modifiedUsers = [];
    foreach ($this->group()->getUsers() as $user) {
      $userId = $user->getUID();
      try {
        $this->encryptionService()->setUserEncryptionKey($appEncryptionKey, $userId);
        $modifiedUsers[] = $userId;
      } catch (Exceptions\CannotEncryptException $e) {
        $this->logException($e, 'Unable to distribute key to user ' . $userId);
        $noKeyUsers[$userId] = $e->getMessage();
      } catch (Throwable $t) {
        $this->logException($t, 'Unable to distribute key to user ' . $userId);
        $fatalUsers[$userId] = $t->getMessage();
      }
    }
    $messages = [];
    if (!empty($modifiedUsers)) {
      $messages[] = $this->l->t('Successfully distributed the app encryption key to %s.', implode(', ', $modifiedUsers));
    } else {
      $messages[] = $this->l->t('Unable to distribute the app encryption key to any user.');
    }
    if (!empty($noKeyUsers)) {
      $message = $this->l->t('Public key missing for %s, key distribution failed.', implode(', ', array_keys($noKeyUsers)));
      $this->logError($message);
      $messages[] = $message;
    }
    foreach ($fatalUsers as $userId => $message) {
      $logMsg = $this->l->t('Setting the app encryption key for %s failed fatally: "%s".', [ $userId, $message ]);
      $this->logError($logMsg);
      $messages[] = $logMsg;
    }
    $status = empty($fatalUsers) && !empty($modifiedUsers)
      ? Http::STATUS_OK
      : Http::STATUS_BAD_REQUEST;
    return [ 'status' => $status, 'messages' => $messages ];
  }
}
