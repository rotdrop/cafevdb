<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\TranslationService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Service\FinanceService;
use OCA\CAFEVDB\Common\Util;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

class PersonalSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var Personal */
  private $personalSettings;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\CalDavService */
  private $calDavService;

  /** @var OCA\CAFEVDB\Service\TranslationService */
  private $translationService;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPC;

  /** @var PhoneNumberService */
  private $phoneNumberService;

  /** @var FinanceService */
  private $financeService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , Personal $personalSettings
    , ConfigCheckService $configCheckService
    , PhoneNumberService $phoneNumberService
    , FinanceService $financeService
    , CalDavService $calDavService
    , TranslationService $translationService
    , WikiRPC $wikiRPC
    , WebPagesRPC $webPagesRPC
  ) {

    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;

    $this->configService = $configService;
    $this->configCheckService = $configCheckService;
    $this->personalSettings = $personalSettings;
    $this->phoneNumberService = $phoneNumberService;
    $this->financeService = $financeService;
    $this->calDavService = $calDavService;
    $this->translationService = $translationService;
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
        return self::grumble($this->l->t('Invalid request data: `%s\'.',[print_r($value, true)]));
      }
      $password = $value['loginpassword'];
      $encryptionkey = $value['encryptionkey'];

      // Re-validate the user
      if ($this->userManager()->checkPassword($this->userId(), $password) === false) {
        return self::grumble($this->l->t('Invalid password for `%s\'.', [$this->userId()]));
      }

      // Then check whether the key is correct
      if (!$this->encryptionKeyValid($encryptionkey) ) {
        return self::grumble($this->l->t('Invalid encryption key.'));
      }

      // So generate a new key-pair and store the key. This will only
      // change the user's preferences.
      // @TODO If we ever should encrypt anything else with the user's
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
    default:
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Store app settings.
   *
   * @NoAdminRequired
   * @SubAdminRequired
   */
  public function setApp($parameter, $value) {
    switch ($parameter) {
    case 'orchestra':
    case 'dbserver': // could check for valid hostname
    case 'dbname':
    case 'dbuser':
      $realValue = trim($value);
      $this->setConfigValue($parameter, $realValue);
      return self::valueResponse($realValue, $this->l->t('`%s\' set to `%s\'.', [$parameter,$realValue]));
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
        return self::grumble($this->l->t('DB-test failed with exception `%s\'.', [$e->getMessage()]));
      }
    case 'systemkey':
      foreach (['systemkey', 'oldkey'] as $key) {
        if (!isset($value[$key])) {
          return self::grumble($this->l->t("Missing parameter `%s'.", $key));
        }
        $this->logInfo($key.' '.$value[$key]);
      }

      $oldKey = $value['oldkey'];
      $systemKey = $value['systemkey'];

      $encryptionService = $this->encryptionService();
      $currentKey = $encryptionService->getAppEncryptionKey();

      $encryptionService->setAppEncryptionKey($oldKey);
      $storedKey = $encryptionService->getConfigValue('encryptionkey', '');

      if ($storedKey !== $oldKey) {
        return self::grumble($this->l->t('Wrong old encryption key'));
      }

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

      // make a backup
      $backupSuffix = '::'.(new \DateTime())->format('YmdHis');

      try {
        foreach ($configValues as $key => $value) {
          $encryptionService->setConfigValue($key.$backSuffix, $value);
        }
      } catch (\Throwable $t) {
        $this->logException($t);
        foreach ($configValues as $key => $value) {
          try {
            $this->deleteConfigValue($key.$backupSuffix);
          } catch (\Throwable $t1) {
            //$this->logException($t1);
          }
        }
        $this->deleteAppValue('configlock');
        return self::grumble($this->exceptionChainData($t));
      }

      $encryptionService->setAppEncryptionKey($systemKey);
      try {
        $this->configService->encryptConfigValues([ 'encryptionkey' => $systemKey ]);
      } catch (\Throwable $t) {
        // Ok, at least it is possible to recover the old values by
        // direct data-base manipulation. This is all for now. In
        // principle one would have to use data-base transactions.
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      }

      foreach ($configValues as $key => $value) {
        try {
          $this->deleteConfigValue($key.$backupSuffix);
        } catch (\Throwable $t1) {
          //$this->logException($t1);
        }
      }

      $this->logInfo('Deleting config-lock');
      $this->deleteAppValue('configlock');

      // this should be it: the new encryption key is stored in the
      // config space, encrypted with itself.

      // Shouldn't we distribute the key as well?

      return self::response($this->l->t('Stored new encryption key'));
    case 'streetAddressName01':
    case 'streetAddressName02':
    case 'streetAddressStreet':
    case 'streetAddressHouseNumber':
    case 'streetAddressCity':
    case 'streetAddressZIP':
    case 'streetAddressCountry':
      $realValue = trim($value);
      $this->setConfigValue($parameter, $realValue);
      return self::valueResponse($realValue, $this->l->t(' `%s\' set to `%s\'.', [$parameter, $realValue]));
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
        return self::grumble($this->l->t('Submitted `%s\' != `%s\' (stored)',
                                         [$savedUid, $confUid]));
      }
      if (empty($uid)) {
        return self::grumble($this->l->t('Share-owner user id must not be empty.'));
      }
      if (empty($savedUid) || $force) {
        if ($this->configCheckService->checkShareOwner($uid)) {
          $this->setConfigValue($parameter, $uid);
          return self::valueResponse($uid, $this->l->t('New share-owner `%s\'.', [$uid]));
        } else {
          return self::grumble($this->l->t('Failure creating account for user-id `%s\'.', [$uid]));
        }
      } else if ($savedUid != $uid) {
        return self::grumble($savedUid . ' != ' . $uid);
      }

      if (!$this->configCheckService->checkShareOwner($uid)) {
        return self::grumble($this->l->t('Failure checking account for user-id `%s\'.', [$uid]));
      }

      return self::response($this->l->t('Share-owner user `%s\' ok.', [$uid]));

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
      $this->logInfo('REAL '.$realValue.' / '.print_r($data, true));
      if (empty($realValue) && !empty($data[$parameter])) {
        // allow erasing
        $this->setConfigValue($parameter, $realValue);
        $data[$parameter] = $realValue;
        $data['message'] = $this->l->t("Erased config value for parameter `%s'.", $parameter);
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
          $data['message'] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
        }
        return self::dataResponse($data);
      case 'bankAccountCreditorIdentifier':
        if (empty($realValue)) {
          return self::response('');
        }
        if ($this->financeService->testCI($realValue)) {
          $this->setConfigValue($parameter, $realValue);
          $data[$parameter] = $realValue;
          $data['message'] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
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
            $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
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
              $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;

            $bic = $bav->getMainAgency($blz)->getBIC();
            $realValue = $bic;
            $parameter = 'bankAccountBIC';
            $this->setConfigValue($parameter, $realValue);
            if ($data[$parameter] != $realValue) {
              $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
            }
            $data[$parameter] = $realValue;
          } else {
            unset($data['bankAccountBLZ']);
            unset($data['bankAccountBIC']);
          }
          return self::dataResponse($data);
        } else {
          $data['message'] = $this->l->t("Invalid IBAN: `%s'.", [ $value ]);
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
            $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
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
              $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
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
              $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
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
            $data['message'][] = $this->l->t("Value for `%s' set to `%s'.", [ $parameter, $realValue ]);
          }
          $data[$parameter] = $realValue;
          return self::dataResponse($data);
        }
        break; // error
      }
      $data['message'] = $this->l->t("Value for `%s' invalid: `%s'.", [ $parameter, $value ]);
      return self::grumble($data);
    }
    case 'memberProjectCreate':
    case 'executiveBoardProjectCreate':
    case 'memberProject':
    case 'executiveBoardProject':
    case 'presidentUserId':
    case 'secretaryUserId':
    case 'treasurerUserId':
    case 'presidentId':
    case 'secretaryId':
    case 'treaserId':
    case 'presidentGroupId':
    case 'secretaryGroupId':
    case 'treasurerGroupId':
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
      $realValue = trim($value); // @@TODO: check for valid password chars.
      if (empty($realValue)) {
        return self::grumble($this->l->t('Password must not be empty'));
      }
      if (!$shareOwner->setPassword($realValue)) {
        return self::grumble($this->l->t('Unable to set password for `%s\'.', [$shareOwnerUid]));
      }
      $this->setConfigValue($parameter, $realValue); // remember for remote API perhaps
      return self::response($this->l->t('Successfully changed passsword for `%s\'.', [$shareOwnerUid]));

    case 'sharedfolder':
      $this->calDavService->createCalendar('TestTestTest');

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
        return self::grumble($this->l->t('Submitted `%s\' != `%s\' (stored)', [$saved, $actual]));
      }
      try {
        if (empty($saved) || $force) {

          if ($this->configCheckService->checkSharedFolder($real)) {
            $this->setConfigValue($parameter, $real);
            return self::valueResponse($real, $this->l->t('Created and shared new folder `%s\'.', [$real]));
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder`%s\'.', [$real]));
          }
        } else if ($real != $saved) {
          return self::grumble($saved . ' != ' . $real);
        } else if ($this->configCheckService->checkSharedFolder($actual)) {
          return self::valueResponse($actual, $this->l->t('`%s\' which is configured as `%s\' exists and is usable.', [$parameter, $actual]));
        } else {
          return self::grumble($this->l->t('`%s\' does not exist or is unaccessible.', [$actual]));
        }
      } catch(\Exception $e) {
        return self::grumble(
          $this->l->t('Failure checking folder `%s\', caught an exception `%s\'.',
                      [$real, $e->getMessage()]));
      }
      // return self::valueResponse('hello', print_r($value, true)); unreached
    case 'projectsbalancefolder':
    case 'projectsfolder':
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
        return self::grumble($this->l->t('Submitted `%s\' != `%s\' (stored)', [$saved, $actual]));
      }
      try {
        if (empty($saved) || $force) {

          if ($this->configCheckService->checkProjectFolder($real)) {
            $this->setConfigValue($parameter, $real);
            return self::valueResponse($real, $this->l->t('Created and shared new folder `%s\'.', [$real]));
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder `%s\'.', [$real]));
          }
        } else if ($real != $saved) {
          return self::grumble($saved . ' != ' . $real);
        } else if ($this->configCheckService->checkSharedFolder($actual)) {
          return self::valueResponse($actual, $this->l->t('`%s\' which is configured as `%s\' exists and is usable.', [$parameter, $actual]));
        } else {
          return self::grumble($this->l->t('`%s\' does not exist or is unaccessible.', [$actual]));
        }
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking folder `%s\', caught an exception `%s\'.',
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
            $this->l->t('Created and shared new calendar `%s\'.', [$real]));
        } else {
          return self::grumble($this->l->t('Failed to create new shared calendar `%s\'.', [$real]));
        }
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking calendar `%s\', caught an exception `%s\'.',
                      [$real, $e->getMessage()]));
      }
    case 'generaladdressbook':
    case 'musiciansaddressbook':
      $real = trim($value);
      $uri = substr($parameter, 0, -strlen('addressbook'));
      //$saved = $value[$parameter.'-saved'];
      //$force = filter_var($value[$parameter.'-force'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      $actual = $this->getConfigValue($parameter);
      $actualId = $this->getConfigValue($parameter.'id');
      try {
        if (($newId = $this->configCheckService->checkSharedAddressBook($uri, $real, $actualId)) > 0) {
          $this->setConfigValue($parameter, $real);
          $this->setConfigValue($parameter.'id', $newId);
          return self::valueResponse(
            ['name' => $real, 'id' => $newId],
            $this->l->t('Created and shared new address book `%s\'.', [$real]));
        } else {
          return self::grumble($this->l->t('Failed to create new shared address book `%s\'.', [$real]));
        }
      } catch(\Exception $e) {
        $this->logError('Exception ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        return self::grumble(
          $this->l->t('Failure checking address book `%s\', caught an exception `%s\'.',
                      [$real, $e->getMessage()]));
      }
    case 'eventduration':
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => 0]);
      if ($realValue === false) {
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $parameter]));
      }
      $this->setUserValue($parameter, $realValue);
      return self::response($this->l->t('Setting %2$s to %1$s minutes.', [$realValue, $parameter]));
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
      return self::response($this->l->t("Successfully recorded the given translation for the language `%s'", $language));
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
      return self::valueResponse($realValue, $this->l->t(' `%s\' set to `%s\'.', [$parameter, $realValue]));
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
        $this->l->t("Redaxo categorie Id for `%s' set to %s", [ $key, $realValue ])
      );
    default:
    }
    return self::grumble($this->l->t('Unknown Request'));
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
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Store app settings.
   *
   * @NoAdminRequired
   * @SubAdminRequired
   */
  public function getApp($parameter) {
    $this->logInfo('PARAM '.$parameter);
    switch ($parameter) {
    case 'translation-templates':
      $pot = $this->translationService->generateCatalogueTemplates();
      $cookieName = $this->parameterService['DownloadCookieName'];
      $cookieValue = $this->parameterService['DownloadCookieValue'];

      if (empty($cookieName) || empty($cookieValue)) {
        return self::grumble($this->l->t('Download-cookies have not been submitted'));
      }

      $fileName = $this->appName().'-'.$this->timeStamp().'.pot';

      $response = new DataDownloadResponse($pot, $fileName, 'text/plain');

      $response->addCookie($cookieName, $cookieValue);

      return $response;
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
