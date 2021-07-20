<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Settings\Personal;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;
use OCA\RoundCube\Service\Config as RoundCubeConfig;

class PersonalSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

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

  /** @var Personal */
  private $personalSettings;

  /** @var ConfigCheckService */
  private $configCheckService;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var CalDavService */
  private $calDavService;

  /** @var TranslationService */
  private $translationService;

  /** @var WikiRPC */
  private $wikiRPC;

  /** @var WebPagesRPC */
  private $webPagesRPC;

  /** @var PhoneNumberService */
  private $phoneNumberService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  private $projectService;

  /** @var IAppContainer */
  private $appContainer;

  /** @var UserStorage */
  private $userStorage;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , RequestParameterService $parameterService
    , ConfigService $configService
    , Personal $personalSettings
    , ConfigCheckService $configCheckService
    , PhoneNumberService $phoneNumberService
    , FinanceService $financeService
    , ProjectService $projectService
    , CalDavService $calDavService
    , TranslationService $translationService
    , UserStorage $userStorage
    , WikiRPC $wikiRPC
    , WebPagesRPC $webPagesRPC
  ) {

    parent::__construct($appName, $request);
    $this->appContainer = $appContainer;
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->configCheckService = $configCheckService;
    $this->personalSettings = $personalSettings;
    $this->phoneNumberService = $phoneNumberService;
    $this->financeService = $financeService;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->translationService = $translationService;
    $this->userStorage = $userStorage;
    $this->wikiRPC = $wikiRPC;
    $this->webPagesRPC = $webPagesRPC;
    $this->l = $this->l10N();
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function form() {
    return $this->personalSettings->getForm();
  }

  /**
   * Store user settings.
   *
   * @NoAdminRequired
   */
  public function set($parameter, $value) {
    switch ($parameter) {
    case 'tooltips':
    case 'filtervisibility':
    case 'directchange':
    case 'showdisabled':
    case 'expertmode':
      $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      if ($realValue === null) {
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not convertible to boolean.', [$value, $parameter]));
      }
      $stringValue = $realValue ? 'on' : 'off';
      $this->setUserValue($parameter, $stringValue);
      return self::response($this->l->t('Switching %2$s %1$s', [$stringValue, $parameter]));
    case 'pagerows':
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => -1]);
      if ($realValue === false) {
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $parameter]));
      }
      $this->setUserValue($parameter, $realValue);
      return self::response($this->l->t('Setting %2$s to %1$s', [$realValue, $parameter]));
    case 'debugmode':
      if (!is_array($value)) {
        $debugModes = [];
      } else {
        $debugModes = $value;
      }
      $debug = 0;
      foreach ($debugModes as $item) {
        $debug |= $item['value'];
      }
      if ($debug > ConfigService::DEBUG_ALL) {
        return grumble($this->l->t('Unknown debug modes in request: %s$s', [print_r($debugModes, true)]));
      }
      $this->setConfigValue('debugmode', $debug);
      if ($debug & ConfigService::DEBUG_CSP) {
        // generate a random magic key for sort-of authentication
        $this->setAppValue('cspfailuretoken', $this->generateRandomBytes(128));
      } else {
        $this->deleteAppValue('cspfailuretoken');
      }
      return new DataResponse([
        'message' => $this->l->t('Setting %2$s to %1$s', [$debug, 'debug']),
        'value' => $debug
      ]);
    case 'wysiwygEditor':
      if (!isset(ConfigService::WYSIWYG_EDITORS[$value])) {
        return grumble($this->l->t('Unknown WYSIWYG-editor: %s$s', [ $value ]));
      }
      $this->setUserValue($parameter, $value);
      return self::response($this->l->t('Setting %2$s to %1$s', [$value, $parameter]));
    case 'encryptionkey':
      // Get data
      if (!is_array($value) || !isset($value['encryptionkey']) || !isset($value['loginpassword'])) {
        return self::grumble($this->l->t('Invalid request data: "%s".',[print_r($value, true)]));
      }
      $password = $value['loginpassword'];
      $encryptionkey = $value['encryptionkey'];

      // Re-validate the user
      if ($this->userManager()->checkPassword($this->userId(), $password) === false) {
        return self::grumble($this->l->t('Invalid password for "%s".', [$this->userId()]));
      }

      // Then check whether the key is correct
      if (!$this->encryptionKeyValid($encryptionkey)) {
        return self::grumble($this->l->t('Invalid encryption key.'));
      }

      // So generate a new key-pair and store the key. This will only
      // change the user's preferences.
      // @todo If we ever should encrypt anything else with the user's
      // SSL key-pair then we would need to be more careful about the
      // key-pair.
      try {
        $this->encryptionService()->initUserKeyPair(true);
        $this->encryptionService()->setUserEncryptionKey($encryptionkey);
        $this->encryptionService()->setAppEncryptionKey($encryptionkey);
      } catch (\Throwable $t) {
         $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }
      return self::response($this->l->t('Encryption key stored.'));
    case 'email-draft-auto-save':
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => 0]);
      if ($realValue === false) {
        $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($realValue === true) {
          $realValue = ConfigService::DEFAULT_AUTOSAVE_INTERVAL;
        } else if ($realValue === false) {
          $realValue = 0;
        } else {
          return self::grumble($this->l->t('Value "%1$s" for set "%2$s" must be a non-negative integer or false.', [$value, $parameter]));
        }
      }
      $this->setUserValue($parameter, $realValue);
      return self::response($this->l->t('Setting %2$s to %1$s', [$realValue, $parameter]));
    default:
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Store app settings.
   *
   * @NoAdminRequired
   * @SubAdminRequired
   * @UseSession
   *
   * @bug This function is too big.
   */
  public function setApp($parameter, $value) {
    switch ($parameter) {
    case 'orchestra':
    case 'dbserver': // could check for valid hostname
    case 'dbname':
    case 'dbuser':
      $realValue = trim($value);
      $this->setConfigValue($parameter, $realValue);
      return self::valueResponse($realValue, $this->l->t('"%s" set to "%s".', [$parameter,$realValue]));
    case 'dbpassword':
      try {
        if (!empty($value)) {
          if ($this->configCheckService->databaseAccessible(['password' => $value])) {
            $this->setConfigValue('dbpassword', $value);
            return self::response($this->l->t('DB-test passed and DB-password set.'));
          } else {
            return self::grumble($this->l->t('DB-test failed. Check the account settings. Check was performed with the new password.'));
          }
        } else {
          // Check with the stored password
          if ($this->configCheckService->databaseAccessible()) {
            return self::response($this->l->t('DB-test passed with stored password (empty input ignored).'));
          } else {
            return self::grumble($this->l->t('DB-test failed with stored password (empty input ignored).'));
          }
        }
      } catch(\Exception $e) {
        return self::grumble($this->l->t('DB-test failed with exception "%s".', [$e->getMessage()]));
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

      $storedKeyHash = $encryptionService->getConfigValue(EncryptionService::APP_ENCRYPTION_KEY_HASH_KEY);
      if (!$encryptionService->verifyHash($oldKey, $hash)) {
        return self::grumble($this->l->t('Wrong old encryption key'));
      }

      // install old encryption key
      $encryptionService->setAppEncryptionKey($oldKey);

      // do some rudimentary locking
      $configLock = $this->getAppValue('configlock');
      if (!empty($configLock)) {
        return self::grumble($this->l->t('Configuration locked, refusing to change encryption key.'));
      }

      $configLock = $this->generateRandomBytes(32);
      $this->setAppValue('configlock', $configLock);
      if ($configLock !== $this->getAppValue('configlock')) {
        return self::grumble($this->l->t('Configuration locked, refusing to change encryption key.'));
      }

      // Still: this does ___NOT___ hack the worst-case scenario, but should suffice for our purposes.

      try {
        // load all config values and decrypt with the old key
        $configValues = $this->configService->decryptConfigValues();
      } catch (\Throwable $t) {
        $this->logException($t);
        $this->deleteAppValue('configlock');
        return self::grumble($this->exceptionChainData($t));
      }

      //$this->logInfo(print_r($configValues, true));

      // make a backup by just copying plain values which can be
      // restore disregarding any encryption key.
      $backupSuffix = '::'.(new \DateTime())->format('YmdHis');
      try {
        foreach (array_keys($configValues) as $configKey) {
          $backupConfigKey = $configKey . $backupSuffix;
          $this->setAppValue($backupConfigKey, $this->getAppValue($configKey));
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        foreach (array_keys($configValues) as $configKey) {
          $backupConfigKey = $configKey . $backupSuffix;
          try {
            $this->deleteAppValue($backupConfigKey);
          } catch (\Throwable $t1) {
            //$this->logException($t1);
          }
        }
        $this->deleteAppValue('configlock');
        return self::grumble($this->exceptionChainData($t));
      }

      try {

        // re-crypt the config-space
        $encryptionService->setAppEncryptionKey($systemKey);
        $this->configService->encryptConfigValues([
          EncryptionService::APP_ENCRYPTION_KEY_HASH_KEY => (empty($systemKey) ? '' : $this->computeHash($systemKey)),
        ]);

        // re-crypt the data-base columns. Changing the data-base
        // values is wrapped into a transaction, so it should clean-up
        // after itself unless the data-base connection breaks down in
        // between.
        /** @var EntityManager $entityManager */
        $entityManager = $this->di(EntityManager::class);
        $entityManager->recryptEncryptedProperties($systemKey, $oldKey);

      } catch (\Throwable $t) {
        $this->logException($t);
        $encryptionService->setAppEncryptionKey($oldKey);
        $responseData = $this->exceptionChainData($t);
        $messages = [ $responseData['message'] ];
        $failed = [];
        foreach (array_keys($configValues) as $configKey) {
          $backupConfigKey = $configKey . $backupSuffix;
          try {
            $this->setAppValue($configKey, $this->getAppValue($backupConfigKey));
          } catch (\Throwable $t1) {
            // $this->logException($t1);
            $failed[] = $configKey;
          }
        }
        if (!empty($failed)) {
          $responseData['message'] =
          $messages[] = $this->l->t('Failed to restore config-values %s, keeping all backup values with suffix "%s".', [ implode(',', $failed), $backupSuffix ]);
        } else {
          $failed = [];
          foreach (array_keys($configValues) as $configKey) {
            $backupConfigKey = $configKey . $backupSuffix;
            try {
              $this->deleteAppValue($backupConfigKey);
            } catch (\Throwable $t2) {
              // $this->logException($t2);
              $failed = [];
            }
          }
          if (!empty($failed)) {
            $messages[] = $this->l->t('Failed to remove backups for config-values %s.', implode(',', $failed));
          }
        }
        $responseData['message'] = $messages;
        return self::grumble($responseData);
      }

      $messages = [];
      $failed = [];
      foreach (array_keys($configValues) as $configKey) {
        $backupConfigKey = $configKey . $backupSuffix;
        try {
          $this->deleteAppValue($backupConfigKey);
        } catch (\Throwable $t2) {
          // $this->logException($t2);
          $failed = [];
        }
      }
      if (!empty($failed)) {
        $messages[] = $this->l->t('Failed to remove backups for config-values %s.', implode(',', $failed));
      }

      $this->logInfo('Deleting config-lock');
      $this->deleteAppValue('configlock');

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
      return self::dataResponse([ 'message' => $messages ], $distributeStatus);
    case 'streetAddressName01':
    case 'streetAddressName02':
    case 'streetAddressStreet':
    case 'streetAddressHouseNumber':
    case 'streetAddressCity':
    case 'streetAddressZIP':
    case 'streetAddressCountry':
      $realValue = trim($value);
      $this->setConfigValue($parameter, $realValue);
      return self::valueResponse($realValue, $this->l->t(' "%s" set to "%s".', [$parameter, $realValue]));
      break;
    case 'shareowner':
      if (!isset($value['shareowner'])
          || !isset($value['shareowner-saved'])
          || !isset($value['shareowner-force'])) {
        return self::grumble($this->l->t('Invalid request parameters: ') . print_r($value, true));
      }
      $uid = $value['shareowner'];
      $savedUid = $value['shareowner-saved'];
      $force = filter_var($value['shareowner-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);

      // first check consistency of $savedUid with stored UID.
      $confUid = $this->getConfigValue('shareowner', '');
      if ($confUid != $savedUid) {
        return self::grumble($this->l->t('Submitted "%s" != "%s" (stored)',
                                         [$savedUid, $confUid]));
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
      } else if ($savedUid != $uid) {
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
      if ($this->phoneNumberService->validate($realValue)) {
        $number['number'] = $this->phoneNumberService->format();
        $number['meta'] = $this->phoneNumberService->metaData();
        $number['isMobile'] = $this->phoneNumberService->isMobile();
        $number['valid'] = true;
        $this->setConfigValue($parameter, $number['number']);
        return self::dataResponse(array_merge($number, [
          'message' => $this->l->t('Orchestra Phone Number set to %s', $number['number']),
        ]));
      } else {
        return self::grumble($this->l->t('The phone number %s does not appear to be a valid phone number. ',
                                         [ $realValue, ]));
      }
      break;
    case 'bankAccountOwner':
    case 'bankAccountBLZ':
    case 'bankAccountIBAN':
    case 'bankAccountBIC':
    case 'bankAccountCreditorIdentifier':
    {
      $realValue = Util::normalizeSpaces($value);
      $data = [
        'bankAccountIBAN' => $this->getConfigValue('bankAccountIBAN'),
        'bankAccountBLZ' => $this->getConfigValue('bankAccountBLZ'),
        'bankAccountBIC' => $this->getConfigValue('bankAccountBIC'),
        'bankAccountCreditorIdentifier' => $this->getConfigValue('bankAccountCreditorIdentifer'),
        'bankAccountOwner' => $this->getConfigValue('bankAccountOwner'),
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
      case 'bankAccountOwner':
        $address = $this->getConfigValue('streetAddressName01');
        if ($realValue !== $address) {
          $data['suggestions'] = [ $address, ];
        }
        if (!empty($realValue)) {
          $this->setConfigValue($parameter, $realValue);
          $data[$parameter] = $realValue;
          $data['message'] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
        }
        return self::dataResponse($data);
      case 'bankAccountCreditorIdentifier':
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
      case 'bankAccountIBAN':
        if (empty($realValue)) {
          return self::response('');
        }
        $iban = new \PHP_IBAN\IBAN($realValue);
        if (!$iban->Verify() && is_numeric($realValue)) {
          // maybe simlpy the bank account number, if we have a BLZ,
          // then compute the IBAN
          $blz = $data['bankAccountBLZ'];
          $bav = new \malkusch\bav\BAV;
          if ($bav->isValidBank($blz)) {
            $realValue = $this->financeService->makeIBAN($blz, $realValue);
            $iban = new \PHP_IBAN\IBAN($realValue);
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
          $bav = new \malkusch\bav\BAV;
          if ($bav->isValidBank($blz)) {
            $realValue = $blz;
            $parameter = 'bankAccountBLZ';
            $this->setConfigValue($parameter, $realValue);
            if ($data[$parameter] != $realValue) {
              $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;

            $bic = $bav->getMainAgency($blz)->getBIC();
            $realValue = $bic;
            $parameter = 'bankAccountBIC';
            $this->setConfigValue($parameter, $realValue);
            if ($data[$parameter] != $realValue) {
              $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;
          } else {
            unset($data['bankAccountBLZ']);
            unset($data['bankAccountBIC']);
          }
          return self::dataResponse($data);
        } else {
          $data['message'] = $this->l->t('Invalid IBAN: "%s".', [ $value ]);
          $suggestion = '';
          $suggestions = $iban->MistranscriptionSuggestions();
          $data['suggestions'] = [];
          while (count($suggestions) > 0) {
            $alternative = array_shift($suggestions);
            if ($iban->Verify($alternative)) {
              $alternative = $iban->MachineFormat($alternative);
              $alternative = $iban->HumanFormat($alternative);
              $data['suggestions'][] = $alternative;
            }
          }
          return self::grumble($data);
        }
        break;
      case 'bankAccountBLZ':
        if (empty($realValue)) {
          return self::response('');
        }
        $bav = new \malkusch\bav\BAV;
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
            $parameter = 'bankAccountBIC';
            $realValue = $bic;
            $this->setConfigValue($parameter, $realValue);
            if ($data[$parameter] != $realValue) {
              $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;
          } else {
            unset($data['bankAccountBIC']);
          }
          return self::dataResponse($data);
        }
        break;
      case 'bankAccountBIC':
        if (empty($realValue)) {
          return self::response('');
        }
        $data['message'] = [];
        if (!$this->financeService->validateSWIFT($realValue)) {
          // maybe a BLZ
          $bav = new \malkusch\bav\BAV;
          if ($bav->isValidBank($realValue)) {
            $parameter = 'bankAccountBLZ';
            $this->setConfigValue($parameter, $realValue);
            if ($data[$parameter] != $realValue) {
              $data['message'][] = $this->l->t('Value for "%s" set to "%s".', [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;
            $agency = $bav->getMainAgency($realValue);
            $realValue = $agency->getBIC();
            $parameter = 'bankAccountBIC';
            // Set also the BIC
          } else {
            unset($data['bankAccountBLZ']);
          }
        }
        if ($this->financeService->validateSWIFT($realValue)) {
          $parameter = 'bankAccountBIC';
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
    }
    case 'memberProject':
    case 'executiveBoardProject':
      $realValue = Util::normalizeSpaces($value);
      // fetch existing values
      $currentProjectName = $this->getConfigValue($parameter, '');
      $currentProjectId = $this->getConfigValue($parameter.'Id', -1);
      $data = [
        'message' => [],
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
        if ($currentProjectId != -1
            && !empty($this->projectService->findById($currentProjectId))) {
          $data['feedback']['Delete'] = [
            'title' => $this->l->t('Delete old Project?'),
            'message' => $this->l->t(
              'Delete old project "%s" (%d) and all its associated data?',
              [ $currentProjectName, $currentProjectId ]),
          ];
        } else {
          $data['project'] = '';
          $data['projectId'] = -1;
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
          $newProject['type'] = Types\EnumProjectTemporalType::PERMANENT();
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
      $newProjectName = $value['newProjectName'];

      $projectParameter = preg_replace('/Validate$/', '', $parameter);

      $currentProjectName = $this->getConfigValue($projectParameter, '');
      $currentProjectId = $this->getConfigValue($projectParameter.'Id', 0);

      $data = [ 'message' => [] ];

      if ((int)$currentProjectId != (int)$projectId) {
        return self::grumble($this->l->t('Configured project-id %d and submitted project id %d differ, please reload the page.',
                                         [ $currentProjectId, $projectId ]));
      }

      if ((int)$projectId <= 0) {
        try {
          $projectName = $value['newProjectName'];
          $project = $this->projectService->createProject($projectName, null, Types\EnumProjectTemporalType::PERMANENT);
          if (!empty($project)) {
            $this->setConfigValue($projectParameter, $project['name']);
            $this->setConfigValue($projectParameter.'Id', $project['id']);
          }
        } catch (\Throwable $t) {
          throw new \Exception($this->l->t(
            'Unable to create project with name "%s".', $projectName), $t->getCode(), $t);
        }

        $data = [
          'message' => [ $this->l->t('Created Project "%s" with id "%d".',
                                     [ $project['name'], $project['id'] ]) ],
          'suggestions' => $this->projectService->projectOptions([ 'type' => 'permanent' ]),
        ];

      } else {

        $project = $this->projectService->findById($projectId);

        if (empty($project)) {
          return self::grumble($this->l->t('Unable to find project with id %d.', $projectId));
        }

        try {
          $this->projectService->sanitizeProject($project);
        } catch (\Throwable $t) {
          return self::grumble($this->exceptionChainData($t));
        }

        $data['message'][] = $this->l->t('Project "%s" successfully validated.', $project->getName());
      }

      return self::dataResponse($data);
    case 'memberProjectDelete':
    case 'executiveBoardProjectDelete':
      try {
        $projectId = $value['projectId'];
        $projectName = $value['project'];
        $project = $this->projectService->deleteProject($projectId);
        $data = [
          'suggestions' => $this->projectService->projectOptions([ 'type' => 'permanent' ]),
          'message' => (empty($project)
                        ? $this->l->t('Deleted project "%s" with id "%d".',
                                      [ $projectName, $projectId ])
                        : $this->l->t('Project "%s", id "%d" has been marked as disabled as it is still needed for financial book-keeping.',
                                      [ $projectName, $projectId ])),
        ];
        return self::dataResponse($data);
      } catch (\Throwable $t) {
        throw new \Exception($this->l->t(
          'Failed to remove project "%s", id "%d".', [ $projectName, $projectId ]),
                             $t->getCode(),
                             $t);
      }
      break;
    case 'memberProjectRename':
    case 'executiveBoardProjectRename':
      $projectId = -1;
      $projectName = '';
      $newName = '';
      try  {
        $projectId = $value['projectId'];
        $projectName = $value['project'];
        $newName = $value['newName'];
        $project = $this->projectService->renameProject($projectId, $newName);
        if (!empty($project)) {
          $projectParameter = preg_replace('/Rename$/', '', $parameter);
          $this->setConfigValue($projectParameter, $project['name']);
          $this->setConfigValue($projectParameter.'Id', $project['id']);
        } else {
          throw new \Exception($this->l->t('Result of rename is empty without throwing an exception.'));
        }

        $data = [
          'message' => $this->l->t(
            'Renamed project "%s" (%d) to "%s".',
            [ $projectName, $project['id'], $newName ]),
          'project' => $newName,
          'projectId' => $projectId,
        ];
        return self::dataResponse($data);
      } catch (\Throwable $t) {
        throw new \Exception($this->l->t(
          'Failed to rename project "%s", id "%d" to new name "%s".',
          [ $projectName, $projectId, $newName ]),
                             $t->getCode(),
                             $t);
      }
      break;
    case 'presidentUserId':
    case 'secretaryUserId':
    case 'treasurerUserId':
    case 'presidentId':
    case 'secretaryId':
    case 'treasurerId':
    case 'presidentGroupId':
    case 'secretaryGroupId':
    case 'treasurerGroupId':
      $executiveBoardMembers = ['president', 'secretary', 'treasurer'];
      foreach ($executiveBoardMembers as $prefix) {
        foreach(['Id', 'UserId', 'GroupId'] as $postfix) {
          $official = $prefix.$postfix;
          if ($parameter === $official) {
            // @todo validate
            return $this->setSimpleConfigValue($parameter, $value);
          }
        }
      }
      return self::grumble($this->l->t('SETTING %s NOT YET IMPLEMENTED', $parameter));
      break;

    case 'shareownerpassword':
      $shareOwnerUid = $this->getConfigValue('shareowner');
      if (empty($shareOwnerUid)) {
        return self::grumble($this->l->t('Please create the share-owner user first.'));
      }
      $shareOwner = $this->user($shareOwnerUid);
      if (empty($shareOwner)) {
        return self::grumble($this->l->t('Share-owner does not seem to exist, please recreate.'));
      }
      if (!$shareOwner->canChangePassword()) {
        return self::grumble($this->l->t('Authentication backend does not support changing passwords.'));
      }
      $realValue = trim($value); // @@todo: check for valid password chars.
      if (empty($realValue)) {
        return self::grumble($this->l->t('Password must not be empty'));
      }
      if (!$shareOwner->setPassword($realValue)) {
        return self::grumble($this->l->t('Unable to set password for "%s".', [$shareOwnerUid]));
      }
      $this->setConfigValue($parameter, $realValue); // remember for remote API perhaps
      return self::response($this->l->t('Successfully changed passsword for "%s".', [$shareOwnerUid]));
    case (!empty(ConfigService::DOCUMENT_TEMPLATES[substr($parameter, 0, -strlen('Delete'))]) ? $parameter : null):
      // Delete config value and file. The file can be undeleted in the cloud, if necessary.

      // Bit unclean, as a relict of previous implementation the
      // "setter" code also handles deletion, so we can just fall
      // through to it.
      $parameter = substr($parameter, 0, -strlen('Delete'));
      $value = '';
      // fallthrough
    case (!empty(ConfigService::DOCUMENT_TEMPLATES[$parameter]) ? $parameter : null):
      $oldFileName = $this->getConfigValue($parameter);
      $sharedFolder = $this->getConfigValue('sharedfolder');
      if (empty($sharedFolder)) {
        return self::grumble($this->l->t(
          'Shared folder is not configured, cannot store templates.'));
      }
      $templatesFolder = $this->getConfigValue('documenttemplatesfolder');
      if (empty($templatesFolder)) {
        return self::grumble($this->l->t(
          'Document template folder is not configured, cannot store templates.'));
      }
      $templatesFolder = UserStorage::PATH_SEP
                       . $sharedFolder . UserStorage::PATH_SEP
                       . $templatesFolder . UserStorage::PATH_SEP;
      if (empty($value)) {
        $this->deleteConfigValue($parameter);
        $messages[] = $this->l->t(
          'Removed setting for document-template "%s".', $parameter);
      } else {
        try {
          $this->userStorage->get($templatesFolder . $value);
        } catch (\Throwable $t) {
          return self::grumble($this->l->t('Unable to find file "%s".', $value));
        }
        $this->setConfigValue($parameter, $value);
        $messages[] = $this->l->t(
          'Document-template "%s" successfully set to "%s".', [ $parameter, $value ]);
      }
      if (!empty($oldFileName) && $oldFileName != $value) {
        $this->logInfo('TRY DELETED OLD '.$templatesFolder . $oldFileName);
        try {
          /** @var \OCP\Files\File $oldFile */
          if (!empty($oldFile = $this->userStorage->getFile($templatesFolder . $oldFileName))) {
            $oldFile->delete();
            $messages[] = $this->l->t(
              'Successfully deleted old document-template "%s".', [ $oldFileName ]);
          }
        } catch (\Throwable $t) {
          $this->logException($t);
        }
      }
      return self::dataResponse([
        'message' => $messages,
      ]);
    case 'sharedfolder':
      $appGroup = $this->getConfigValue('usergroup');
      if (empty($appGroup)) {
        return self::grumble($this->l->t('App user-group is not set.'));
      }
      $shareOwner = $this->getConfigValue('shareowner');
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
            } catch (\Throwable $t) {
              // don't care
            }
            return self::dataResponse([
              'value' => $real,
              'message' => $this->l->t('Created and shared new folder "%s".', [$real]),
              'folderLink' => $folderLink,
            ]);
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder"%s".', [$real]));
          }
        } else if ($real != $saved) {
          return self::grumble($saved . ' != ' . $real);
        } else if ($this->configCheckService->checkSharedFolder($actual)) {
          try {
            $folderLink = $this->userStorage->getFilesAppLink($real);
          } catch (\Throwable $t) {
            // don't care
          }
          return self::dataResponse([
            'value' => $actual,
            'message' => $this->l->t('"%s" which is configured as "%s" exists and is usable.', [$parameter, $actual]),
            'folderLink' => $folderLink,
            ]);
        } else {
          return self::grumble($this->l->t('"%s" does not exist or is unaccessible.', [$actual]));
        }
      } catch(\Exception $e) {
        return self::grumble(
          $this->l->t('Failure checking folder "%s", caught an exception "%s".',
                      [$real, $e->getMessage()]));
      }
      // return self::valueResponse('hello', print_r($value, true)); unreached
    case ConfigService::POSTBOX_FOLDER:
    case ConfigService::DOCUMENT_TEMPLATES_FOLDER:
    case ConfigService::PROJECT_PARTICIPANTS_FOLDER:
    case ConfigService::PROJECT_POSTERS_FOLDER:
    case ConfigService::PROJECT_BALANCE_FOLDER:
    case ConfigService::PROJECTS_FOLDER:
      $appGroup = $this->getConfigValue('usergroup');
      if (empty($appGroup)) {
        return self::grumble($this->l->t('App user-group is not set.'));
      }
      $shareOwner = $this->getConfigValue('shareowner');
      if (empty($shareOwner)) {
        return self::grumble($this->l->t('Share-owner is not set.'));
      }
      $sharedFolder = $this->getConfigValue('sharedfolder');
      if (empty($sharedFolder)) {
        return self::grumble($this->l->t('Shared folder is not set.'));
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
      // shortcut for participants and posters folder, which only exist as subdirectory
      switch ($parameter) {
      case ConfigService::PROJECT_PARTICIPANTS_FOLDER:
        $this->setConfigValue($parameter, $real);
        return self::valueResponse($real, $this->l->t('Participants-folder set to "%s".', $real));
      case ConfigService::PROJECT_POSTERS_FOLDER:
        $this->setConfigValue($parameter, $real);
        return self::valueResponse($real, $this->l->t('Posters-folder set to "%s".', $real));
      }
      try {
        $url = null;
        if (empty($saved) || $force) {
          if ($this->configCheckService->checkProjectFolder($real)) {
            $this->setConfigValue($parameter, $real);
            try {
              $folderLink = $this->userStorage->getFilesAppLink($real);
            } catch (\Throwable $t) {
              // don't care
              $this->logException($t);
            }
            if ($parameter == ConfigService::POSTBOX_FOLDER) {
              try {
                $url = $this->configCheckService->checkLinkSharedFolder(
                  $sharedFolder . UserStorage::PATH_SEP . $real
                );
                $this->setConfigValue($parameter . 'ShareLink', $url);
              } catch (\Throwable $t) {
                $this->logException($t);
              }
            }
            return self::dataResponse([
              'value' => $real,
              'url' => $url,
              'message' => $this->l->t('Created and shared new folder "%s".', [$real]),
              'folderLink' => $folderLink,
            ]);
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder "%s".', [$real]));
          }
        } else if ($real != $saved) {
          return self::grumble($saved . ' != ' . $real);
        } else if ($this->configCheckService->checkProjectFolder($actual)) {
          try {
            $folderLink = $this->userStorage->getFilesAppLink($actual);
          } catch (\Throwable $t) {
            // don't care
          }
          if ($parameter == ConfigService::POSTBOX_FOLDER) {
            try {
              $url = $this->configCheckService->checkLinkSharedFolder(
                $sharedFolder . UserStorage::PATH_SEP . $real
              );
              $this->setConfigValue($parameter . 'ShareLink', $url);
            } catch (\Throwable $t) {
              $this->logException($t);
            }
          }
          return self::dataResponse([
            'value' => $actual,
            'url' => $url,
            'message' => $this->l->t('"%s" which is configured as "%s" exists and is usable.', [$parameter, $actual]),
            'folderLink' => $folderLink,
            ]);
        } else {
          return self::grumble($this->l->t('"%s" does not exist or is unaccessible.', [$actual]));
        }
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking folder "%s", caught an exception "%s".',
                      [$real, $e->getMessage()]));
      }
    case 'concertscalendar':
    case 'rehearsalscalendar':
    case 'othercalendar':
    case 'managementcalendar':
    case 'financecalendar':
      $real = trim($value);
      $uri = substr($parameter, 0, -strlen('calendar'));
      //$saved = $value[$parameter.'-saved'];
      //$force = filter_var($value[$parameter.'-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      $actual = $this->getConfigValue($parameter);
      $actualId = $this->getConfigValue($parameter.'id');
      try {
        if (($newId = $this->configCheckService->checkSharedCalendar($uri, $real, $actualId)) > 0) {
          $this->setConfigValue($parameter, $real);
          $this->setConfigValue($parameter.'id', $newId);
          return self::valueResponse(
            ['name' => $real, 'id' => $newId],
            ($newId != $actualId
             ? $this->l->t('Created and shared new calendar "%s".', [$real])
             : $this->l->t('Validated shared calendar "%s".', [$real]))
          );
        } else {
          return self::grumble($this->l->t('Failed to create new shared calendar "%s".', [$real]));
        }
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking calendar "%s", caught an exception "%s".',
                      [$real, $e->getMessage()]));
      }
    case 'generaladdressbook':
      $real = trim($value);
      $uri = substr($parameter, 0, -strlen('addressbook'));
      //$saved = $value[$parameter.'-saved'];
      //$force = filter_var($value[$parameter.'-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      $actual = $this->getConfigValue($parameter);
      $actualId = $this->getConfigValue($parameter.'id');
      try {
        if (($newId = $this->configCheckService->checkSharedAddressBook($uri, $real, $actualId)) <= 0) {
          return self::grumble($this->l->t('Failed to create new shared address book "%s".', [$real]));
        }
        $this->setConfigValue($parameter, $real);
        $this->setConfigValue($parameter.'id', $newId);
        return self::valueResponse(
          ['name' => $real, 'id' => $newId],
          $this->l->t('Created and shared new address book "%s".', $real));
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking address book "%s", caught an exception "%s".',
                      [$real, $e->getMessage()]));
      }
    case 'musiciansaddressbook':
      $real = trim($value);
      $this->setConfigValue($parameter, $real);
      $addressBook = $this->appContainer->query(AddressBookProvider::class)->getContactsAddressBook();
      $this->setConfigValue($parameter.'id', $addressBook->getKey());
      if (empty($real)) {
        $real = $addressBook->getDisplayName();
        $message = $this->l->t('Display name of musicians-addressbook reset to "%s".', $real);
      } else {
        $message = $this->l->t('Display name of musicians-addressbook set to "%s".', $real);
      }
      if ($addressBook->getDisplayName() != $real) {
        return self::grumble($this->l->t('Unable to set display-name of musicians-addressbook to "%s", it remains at "%s".', [ $real, $addressBook->getDisplayName() ]));
      }
      return self::valueResponse(
        [ 'name' => $addressBook->getDisplayName(), 'id' => $addressBook->getKey() ],
        $message);
    case 'eventduration':
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => 0]);
      if ($realValue === false) {
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $parameter]));
      }
      $this->setUserValue($parameter, $realValue);
      return self::response($this->l->t('Setting %2$s to %1$s minutes.', [$realValue, $parameter]));

    case 'keydistribute':
      list('status' => $status, 'messages' => $messages) = $this->distributeEncryptionKey();
      $this->logInfo('STATUS ' . (int)$status . ' ' . print_r($messages, true));
      return self::dataResponse([ 'message' => $messages ], $status);
    case 'emaildistribute':
      $roundCubeConfig = $this->appContainer->get(RoundCubeConfig::class);
      $emailUser = $this->getConfigValue('emailuser');
      $emailPassword = $this->getConfigValue('emailpassword');
      $noEmailUsers = [];
      $fatalUsers = [];
      $modifiedUsers = [];
      foreach ($this->group()->getUsers() as $user)  {
        $userId = $user->getUID();
        try {
          $roundCubeConfig->setEmailCredentials($userId, $emailUser, $emailPassword);
          $modifiedUsers[] = $userId;
        } catch (\Exception $e) {
          $noEmailUsers[] = [ $userId => $e->getMessage() ];
        } catch (\Throwable $t) {
          $fatalUsers[] = [ $userId => $t->getMessage() ];
        }
      }
      $messages = [];
      if (!empty($modifiedUsers)) {
        $messages[] = $this->l->t('Successfully distributed the email credentials for %s.', implode(', ', $modifiedUsers));
      } else {
        $messages[] = $this->l->t('Unable to distribute the email credentials to any user.');
      }
      if (!empty($noEmailUsers)) {
        $messages[] = $this->l->t('Email credentials could not be set for %s.', implode(', ', $noKeyUsers));
      }
      foreach ($fatalUsers as $userId => $message) {
        $messages[] = $this->l->t('Setting the email credentials for %s failed fatally: "%s".', [ $userId, $message ]);
      }
      $status = empty($fatalUsers) && !empty($modifiedUsers)
        ? Http::STATUS_OK
              : Http::STATUS_BAD_REQUEST;
      return self::dataResponse([ 'message' => $messages ], $status);
    case 'emailtest':
      $user = $this->getConfigValue('emailuser');
      $password = $this->getConfigValue('emailpassword');
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
          : ($this->l->t('Unable to establish %s connection to %s@%s:%d',
                         [ strtoupper($proto), $user, $server, $port ]));
      }
      $message = implode(' ', $messages);
      if ($check['smtp'] === true && $check['imap'] === true) {
        return self::response($message);
      } else {
        return self::grumble($message);
      }
    case 'smtpserver':
    case 'imapserver':
    case 'smtpport':
    case 'imapport':
    case 'smtpsecurity':
    case 'imapsecurity':
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
        } else if (filter_var($realValue, FILTER_VALIDATE_INT, [ 'min_range' => 1, 'max_range' => 65535 ]) === false) {
          return self::grumble($this->l->t('"%s" is not an integral number in the range [%d, %d]',
                                           [ $realValue, 1, 65535 ]));
        }
        return $this->setSimpleConfigValue($parameter, $realValue);
      case 'security':
        if (empty($realValue))  {
          return $this->setSimpleConfigValue($parameter, $realValue);
        }
        if (array_search($realValue, self::EMAIL_SECURITY) === false) {
          return self::grumble($this->l->t('Unknown transport security method: "%s".', $realValue));
        }
        $port = self::EMAIL_PORTS[$proto][$realValue];
        $this->setConfigValue($parameter, $realValue);
        $this->setConfigValue($proto.'port', $port);
        return self::dataResponse([
          'message' => $this->l->t('Using transport security "%s" for protocol "%s".',
                                   [ $realValue, $proto ]),
          'proto' => $proto,
          'port' => $port,
        ]);
      }
      break;
    case 'emailtestmode':
      $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      if ($realValue === null) {
        return self::grumble($this->l->t('Value "%s" for set "%s" is not convertible to boolean.', [$value, $parameter]));
      }
      $stringValue = $realValue ? 'on' : 'off';
      return $this->setSimpleConfigValue($parameter, $stringValue);
    case 'emailtestaddress':
    case 'emailfromaddress':
      $realValue = Util::normalizeSpaces($value);
      if (!empty($realValue) && filter_var($realValue, FILTER_VALIDATE_EMAIL) === false) {
        return self::grumble($this->l->t('"%s" does not seem to be a valid email address', $value));
      }
    case 'emailuser':
    case 'emailpassword':
    case 'emailfromname':
      return $this->setSimpleConfigValue($parameter, $value);

    case (in_array($parameter, ConfigService::MAILING_LIST_CONFIG) ? $parameter : null):
      foreach (ConfigService::MAILING_LIST_CONFIG as $listConfig) {
        ${$listConfig} = $this->getConfigValue($listConfig);
      }
      ${$parameter} = Util::normalizeSpaces($value);
      $all = true;
      foreach (ConfigService::MAILING_LIST_CONFIG as $listConfig) {
        if (empty(${$listConfig})) {
          $all = false;
          break;
        }
      }
      if ($all) {
        $oldValue = $this->getConfigValue($parameter);
        $this->setConfigValue($parameter, ${$parameter});
        try {
          $listService = $this->di(MailingListsService::class);
          if (empty($listService->getServerConfiguration())) {
            $this->setConfigValue($parameter, $oldValue);
            return self::grumble(
              $this->l->t('Unable to connect to mailing list service at "%s"', $mailingListURL));
          }
        } catch (\Throwable $t) {
          $this->setConfigValue($parameter, $oldValue);
          $this->logException($t);
          return self::grumble(
            $this->l->t('Unable to connect to mailing list service at "%s"', $mailingListURL));
        }
      }
      return $this->setSimpleConfigValue($parameter, $value);

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
      }
      $this->setConfigValue($parameter, $realValue);
      $key = $parameter;
      $this->logDebug($key . ' => ' . $this->getConfigValue($key));
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
        'RehearsalsModule'
      ];
      $key = str_replace('redaxo', '', $parameter);
      if (array_search($key, $redaxoKeys) === false) {
        return self::grumble($this->l->t('Unknown configuation key %s', [ $parameter ]));
      }
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => 1]);
      if ($realValue === false) {
        return self::grumble($this->l->t('Value "%s" for setting "%s" is not in the allowed range.', [$value, $parameter]));
      }
      $this->setConfigValue($parameter, $realValue);
      return self::valueResponse(
        $realvalue,
        $this->l->t('Redaxo categorie Id for "%s" set to "%s".', [ $key, $realValue ])
      );
    default:
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * Get some stuff
   *
   * @NoAdminRequired
   */
  public function get($parameter) {
    switch ($parameter) {
      case 'passwordgenerate':
      case 'generatepassword':
        return self::valueResponse($this->generateRandomBytes(32));
      case 'test'.str_replace('test', '', $parameter):
        $testKeys = [
          'cspfailurereporting' => $this->urlGenerator()->linkToRouteAbsolute($this->appName().'.csp_violation.post', ['operation' => 'report']),
          'clouddev' => null,
          'sourcedocs' => null,
          'sourcecode' => null,
          'phpmyadmincloud' => null,
          'phpmyadmin' => null,
        ];
        $key = substr($parameter, 4);
        if (array_search($key, array_keys($testKeys)) === false) {
          return self::grumble($this->l->t('Unknown link target %s', [ $parameter ]));
        }
        return self::valueResponse([
          'link' => $this->getConfigValue($key, $testKeys[$key]),
          'target' => $key.':'.$this->appName,
        ]);
      default:
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * Store app settings.
   *
   * @NoAdminRequired
   * @SubAdminRequired
   */
  public function getApp($parameter) {
    switch ($parameter) {
    case 'translation-templates':
      $pot = $this->translationService->generateCatalogueTemplates();

      $fileName = $this->appName().'-'.$this->timeStamp().'.pot';

      $response = new DataDownloadResponse($pot, $fileName, 'text/plain');

      $response->addCookie($cookieName, $cookieValue);

      return $response;
    case 'auto-fill-test':
      $templateName = $this->parameterService->getParam('documentTemplate');
      if (empty(ConfigService::DOCUMENT_TEMPLATES[$templateName])
          ||ConfigService::DOCUMENT_TEMPLATES[$templateName]['type'] != ConfigService::DOCUMENT_TYPE_TEMPLATE ) {
        return self::grumble($this->l->t('Unknown auto-fill template: "%s".', $templateName));
      }
      /** @var InstrumentationService $instrumentationService */
      $instrumentationService = $this->di(InstrumentationService::class);
      $musician = $instrumentationService->getDummyMusician();

      $fileName = implode('-', [ $this->timeStamp(), $templateName, 'auto-fill-test' ]);

      switch ($templateName) {
      case 'projectDebitNoteMandateForm':
        list($fileData, $mimeType, $fileName) = $this->financeService->preFilledDebitMandateForm(
          $musician->getSepaBankAccounts()->first(),
          $this->getExecutiveBoardProjectId());
        break;
      case 'generalDebitNoteMandateForm':
        list($fileData, $mimeType, $fileName) = $this->financeService->preFilledDebitMandateForm(
          $musician->getSepaBankAccounts()->first(),
          $this->getClubMembersProjectId());
        break;
      default:
        return self::grumble(
          $this->l->t('Auto-fill test for template "%s: not yet implemented, sorry.',
                      $templateName));
      }

      $pathInfo = pathinfo($fileName);
      $fileName = implode('-', [
        $this->timeStamp(),
        $pathInfo['filename'],
        'auto-fill-test',
      ]) . '.' . $pathInfo['extension'];

      return new DataDownloadResponse($fileData, $fileName, $mimeType);
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $parameter));
  }

  private function setSimpleConfigValue($key, $value)
  {
    $realValue = Util::normalizeSpaces($value);

    if (empty($realValue)) {
      $this->deleteConfigValue($key);
      return self::dataResponse([
        'message' => $this->l->t('Erased config value for parameter "%s".', $key),
        $key => $value,
      ]);
    } else {
      $this->setConfigValue($key, $realValue);

      if (preg_match('/.*password.*/i', $key)) {
        $realValue = '••••••••';
      }
      return self::dataResponse([
        'message' => $this->l->t('Value for "%s" set to "%s"', [ $key, $realValue ]),
        $key => $value,
      ]);
    }
  }

  /**
   * Distribute the encryption key to all users by storing them in
   * their personal preferences, encrypted with their SSL key-pair.
   */
  private function distributeEncryptionKey()
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
    foreach ($this->group()->getUsers() as $user)  {
      $userId = $user->getUID();
      try {
        $this->encryptionService()->setUserEncryptionKey($appEncryptionKey, $userId);
        $modifiedUsers[] = $userId;
      } catch (Exceptions\EncryptionKeyException $e) {
        $noKeyUsers[$userId] = $e->getMessage();
      } catch (\Throwable $t) {
        $fatalUsers[$userId] = $t->getMessage();
      }
    }
    $messages = [];
    if (!empty($modifiedUsers)) {
      $messages[] = $this->l->t('Successfully distributed the app encryption key to %s.', implode(', ', $modifiedUsers));
    } else {
      $messages[] = $this->l->t('Unable to distribute the app encryptionkey to any user.');
    }
    if (!empty($noKeyUsers)) {
      $message = $this->l->t('Public SSL key missing for %s, key distribution failed.', implode(', ', array_keys($noKeyUsers)));
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
