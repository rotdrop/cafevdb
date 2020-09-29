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
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\DatabaseFactory;
use OCA\CAFEVDB\Settings\Personal;

class PersonalSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var IL10N */
  private $l;

  /** @var Personal */
  private $personalSettings;

  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    Personal $personalSettings,
    DatabaseFactory $databaseFactory,
    ConfigCheckService $configCheckService
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->configCheckService = $configCheckService;
    $this->personalSettings = $personalSettings;
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
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not convertible to boolean', [$value, $parameter]));
      }
      $stringValue = $realValue ? 'on' : 'off';
      $this->setUserValue($parameter, $stringValue);
      return self::response($this->l->t('Switching %2$s %1$s', [$stringValue, $parameter]));
    case 'pagerows':
      $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => -1]);
      if ($realValue === false) {
        return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range', [$value, $parameter]));
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
      $this->setUserValue('debug', $debug);
      return new DataResponse([
        'message' => $this->l->t('Setting %2$s to %1$s', [$debug, 'debug']),
        'value' => $debug
      ]);
    case 'wysiwyg':
      if (!isset(ConfigService::WYSIWYG_EDITORS[$value])) {
        return grumble($this->l->t('Unknown WYSIWYG-editor: %s$s', [ $value ]));
      }
      $this->setUserValue($parameter, $value);
      return self::response($this->l->t('Setting %2$s to %1$s', [$value, $parameter]));
    case 'encryptionkey':
      // Get data
      if (!is_array($value) || !isset($value['encryptionkey']) || !isset($value['loginpassword'])) {
        return self::grumble($this->l->t('Invalid request data: `%s\'',[print_r($value, true)]));
      }
      $password = $value['loginpassword'];
      $encryptionkey = $value['encryptionkey'];

      // Re-validate the user
      if ($this->userManager()->checkPassword($this->userId(), $password) === false) {
        return self::grumble($this->l->t('Invalid password for `%s\'', [$this->userId()]));
      }

      // Then check whether the key is correct
      if (!$this->encryptionKeyValid($encryptionkey)) {
        return self::grumble($this->l->t('Invalid encryption key.'));
      }

      // So generate a new key-pair and store the key.
      if (!$this->recryptAppEncryptionKey($this->userId(), $password, $encryptionkey)) {
        return self::grumble($this->l->t('Unable to store encrypted encryption key in user-data.'));
      }

      // Then store the key in the session as it is the valid key
      $this->setAppEncryptionKey($encryptionkey);

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
      return self::valueResponse($realValue, $this->l->t('`%s\' set to `%s\'', [$parameter,$realValue]));
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
        return self::grumble($this->l->t('DB-test failed with exception `%s\'', [$e->getMessage()]));
      }
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
          return self::valueResponse($uid, $this->l->t('New share-owner `%s\'', [$uid]));
        } else {
          return self::grumble($this->l->t('Failure creating account for user-id `%s\'', [$uid]));
        }
      } else if ($savedUid != $uid) {
        return self::grumble($savedUid . ' != ' . $uid);
      }

      if (!$this->configCheckService->checkShareOwner($uid)) {
        return self::grumble($this->l->t('Failure checking account for user-id `%s\'', [$uid]));
      }

      return self::response($this->l->t('Share-owner user `%s\' ok.', [$uid]));

    case 'sharingpassword':
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
      return self::response($this->l->t('Successfully changed passsword for `%s\'.', [$shareOwnerUid]));

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
      $real = $value[$parameter];
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
            return self::valueResponse($real, $this->l->t('Created and shared new folder `%s\'', [$real]));
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder`%s\'', [$real]));
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
          $this->l->t('Failure checking folder `%s\', caught an exception `%s\'',
                      [$real, $e->getMessage()]));
      }
      // return self::valueResponse('hello', print_r($value, true)); unreached
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
      $real = $value[$parameter];
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

          if ($this->configCheckService->checkProjectsFolder($real)) {
            $this->setConfigValue($parameter, $real);
            return self::valueResponse($real, $this->l->t('Created and shared new folder `%s\'', [$real]));
          } else {
            return self::grumble($this->l->t('Failed to create new shared folder`%s\'', [$real]));
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
          $this->l->t('Failure checking folder `%s\', caught an exception `%s\'',
                      [$real, $e->getMessage()]));
      }
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
    default:
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  static private function valueResponse($value, $message = '', $status = Http::STATUS_OK)
  {
    return new DataResponse(['message' => $message, 'value' => $value], $status);
  }

  static private function response($message, $status = Http::STATUS_OK)
  {
    return new DataResponse(['message' => $message], $status);
  }

  static private function grumble($message)
  {
    return self::response($message, Http::STATUS_BAD_REQUEST);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
