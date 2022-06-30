<?php
/**
 * Orchestra member, musicion and project management application.
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

namespace OCA\CAFEVDB\Crypto;

use OCP\ILogger;
use OCP\IL10N;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\Event;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\AppFramework\IAppContainer;
use OCP\Notification\INotification;
use OCP\Notification\IManager as NotificationManager;
use OCP\AppFramework\Utility\ITimeFactory;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Notifications\Notifier;

/**
 * Support functions encapsulating the underlying encryption framework
 * (currently openssl)
 */
class AsymmetricKeyService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const PUBLIC_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PUBLIC_ENCRYPTION_KEY;
  const PRIVATE_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PRIVATE_ENCRYPTION_KEY;

  const CONFIG_KEY_PREFIX = 'private:';

  const RECRYPTION_REQUEST_KEY = Notifier::RECRYPT_USER_SUBJECT;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  /** @var IUserSession */
  private $userSession;

  /** @var ICredentialsStore */
  private $credentialsStore;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var IConfig */
  private $cloudConfig;

  /** @var IL10N */
  private $l;

  /** @var AsymmetricKeyStorageInterface */
  private $keyStorage;

  /** @var AsymmetricCryptorInterface */
  private $cryptorPrototype;

  /** @var array<string, AsymmetricCryptorInterface> */
  static private $cryptors = [];

  /** @var array<string, array> */
  static private $keyPairs = [];

  /**
   * @todo We might want to use the \OCP\IAppContainer instead and only fetch
   * the needed service-classes on demand.
   */
  public function __construct(
    string $appName
    , IAppContainer $appContainer
    , IUserSession $userSession
    , ICredentialsStore $credentialsStore
    , IConfig $cloudConfig
    , IEventDispatcher $eventDispatcher
    , IL10N $l10n
    , ILogger $logger
    , AsymmetricKeyStorageInterface $keyStorage
    , AsymmetricCryptorInterface $cryptorPrototype
  ) {
    $this->appName = $appName;
    $this->appContainer = $appContainer;
    $this->userSession = $userSession;
    $this->credentialsStore = $credentialsStore;
    $this->cloudConfig = $cloudConfig;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->keyStorage = $keyStorage;
    $this->cryptorPrototype = $cryptorPrototype;
  }

  /**
   * Initialize a private/public key-pair by either retreiving it from the
   * config-space or generating a new one. If a new key-pair has to be
   * generated the two events Events\BeforeEncryptionKeyPairChanged and
   * Events\AfterEncryptionKeyPairChanged are fired. The old key-pair may be
   * missing if the password used to secure the old private key is not
   * available.
   *
   * @param null|string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   *
   * @param null|string $keyPassphrase The passphrase used to protect the
   * private key. If null then the currently logged in user's password is used
   * if the cloud's credentials store is able to provide the password.
   *
   * @param bool $forceNewKeyPair Generate a new key pair even if an
   * old one is found.
   *
   * @return array<string, string>
   * ```
   * [
   *   self::PRIVATE_ENCRYPTION_KEY_CONFIG => PRIV_KEY,
   *   self::PUBLIC_ENCRYPTION_KEY_CONFIG => PUB_KEY,
   * ]
   * ```
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function initEncryptionKeyPair(?string $ownerId = null, ?string $keyPassphrase = null, bool $forceNewKeyPair = false)
  {
    if (empty($ownerId)) {
      $ownerId = $this->getSessionUserId();
    }

    if (empty($keyPassphrase)) {
      $keyPassphrase = $this->getLoginPassword($ownerId);
    }

    if (empty($ownerId) || empty($keyPassphrase)) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize SSL key-pair without user and password'));
    }

    if (!$forceNewKeyPair && !empty(self::$keyPairs[$ownerId])) {
      return self::$keyPairs[$ownerId];
    }

    try {
      $keyPair = $forceNewKeyPair ? null : $this->keyStorage->getKeyPair($ownerId, $keyPassphrase);
    } catch (\Throwable $t) {
      $this->logException($t, $this->l->t('Unable to retrieve old key-pair for "%s".', $ownerId));
      $keyPair = [];
    }
    if (empty($keyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG]) || empty($keyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG])) {

      $oldKeyPair = self::$keyPairs[$ownerId] ?? null;
      if (empty($oldKeyPair) && $ownerId == $this->getSessionUserId()) {
        $loginPassword = $this->getLoginPassword($ownerId);
        if (!empty($loginPassword)) {
          try {
            $oldKeyPair = $this->keyStorage->getKeyPair($ownerId, $loginPassword);
          } catch (\Throwable $t) {
            $this->logException($t, 'Unable to fetch old encryption key pair for "' . $ownerId . '".');
          }
        }
      }

      // make sure the old key-pair cache is set
      self::$keyPairs[$ownerId] = $oldKeyPair;

      $this->eventDispatcher->dispatchTyped(new Events\BeforeEncryptionKeyPairChanged($ownerId, $oldKeyPair));

      $this->keyStorage->backupKeyPair($ownerId); // make a backup-copy if old key exists
      $keyPair = $this->keyStorage->generateKeyPair($ownerId, $keyPassphrase);

      $this->eventDispatcher->dispatchTyped(new Events\AfterEncryptionKeyPairChanged($ownerId, $oldKeyPair, $keyPair));
    }

    self::$keyPairs[$ownerId] = $keyPair;

    // ensure that the cached cryptor has the correct key
    $cryptor = $this->getCryptor($ownerId)
      ->setPrivateKey($keyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG] ?? null)
      ->setPublicKey($keyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG] ?? null);

    return $keyPair;
  }

  /**
   * Remove the key pair and all config-data for the given id.
   *
   * @param string $ownerId
   */
  public function deleteEncryptionKeyPair(string $ownerId)
  {
    $this->keyStorage->wipeKeyPair($ownerId);
    $this->removeSharedPrivateData($ownerId);
    unset(self::$cryptors[$ownerId]);
    unset(self::$keyPairs[$ownerId]);
  }

  /**
   * Restore the key-pair from a potential backup e.g. in case of a failure
   * while changing the key. This is desctructive, the current key is
   * overwritten.
   */
  public function restoreEncryptionKeyPair(string $ownerId)
  {
    $this->keyStorage->restoreKeyPair($ownerId);
  }

  /**
   * Fetch the user-id for the currently logged in user.
   *
   * @return null|string
   */
  private function getSessionUserId():?string
  {
    $user = $this->userSession->getUser();
    return empty($user) ? null : $user->getUID();
  }

  /**
   * Fetch the password for the given user-id from the credentials store.
   *
   * @param string $ownerId
   *
   * @return null|string
   *
   * @throws Exceptions\EncryptionKeyException
   */
  private function getLoginPassword(string $ownerId):?string
  {
    /** @var ICredentials */
    $loginCredentials = $this->credentialsStore->getLoginCredentials();
    if (!empty($loginCredentials)) {
      $password = $loginCredentials->getPassword();
      $credentialsUid = $loginCredentials->getUID();
      if ($credentialsUid != $ownerId) {
        throw new Exceptions\EncryptionKeyException(
          $this->l->t(
            'Given user id "%1$s" and user-id "%2$s" from login-credentials differ.', [
              $ownerId, $credentialsUid
            ])
        );
      }
    }
    return $password ?? null;
  }

  /**
   * Get a suitable asymmetric cryptor for the given user and used backend.
   *
   * @param string $ownerId
   *
   * @return AsymmetricCryptorInterface
   */
  public function getCryptor(string $ownerId):AsymmetricCryptorInterface
  {
    /** @var AsymmetricCryptorInterface $cryptor */
    $cryptor = self::$cryptors[$ownerId] ?? null;
    if (empty($cryptor)) {
      $keyPair = self::$keyPairs[$ownerId] ?? null;
      $privKey = $keyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG] ?? null;
      $pubKey = $keyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG] ?? $this->keyStorage->getPublicKey($ownerId);
      $cryptor = clone $this->cryptorPrototype;
      $cryptor->setPrivateKey($privKey);
      $cryptor->setPublicKey($pubKey);
      self::$cryptors[$ownerId] = $cryptor;
    }
    return $cryptor;
  }

  /**
   * Encrypt and set one private value
   *
   * @param string $ownerId
   *
   * @param string $key
   *
   * @param mixed $value Must be convertible to string.
   *
   * @throws Exceptions\CannotEncryptException
   */
  public function setSharedPrivateValue(string $ownerId, string $key, mixed $value)
  {
    $value = (string)$value;
    $configKey = self::CONFIG_KEY_PREFIX . $key;
    if (empty($value)) {
      $this->cloudConfig->deleteUserValue($ownerId, $this->appName, $configKey);
      return;
    }
    $cryptor = $this->getCryptor($ownerId);
    if (!$cryptor->canEncrypt()) {
      throw new Exceptions\CannotEncryptException($this->l->t('Cannot encrypt personal value "%1$s" for "%2$s".', [ $key, $ownerId ]));
    }
    $this->cloudConfig->setUserValue($ownerId, $this->appName, $configKey, $cryptor->encrypt($value));
  }

  /**
   * Fetch and decrypt one private value
   *
   * @param string $ownerId
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return string|null
   *
   * @throws Exceptions\CannotDecryptException
   */
  public function getSharedPrivateValue(string $ownerId, string $key, mixed $default = null):?string
  {
    $configKey = self::CONFIG_KEY_PREFIX . $key;
    $value = $this->cloudConfig->getUserValue($ownerId, $this->appName, $configKey, $default);
    if (empty($value) || $value === $default) { // allow empty and default values
      return $value === null ? null : (string)$value;
    }
    $cryptor = $this->getCryptor($ownerId);
    if (!$cryptor->canDecrypt()) {
      throw new Exceptions\CannotDecryptException($this->l->t('Cannot decrypt personal value "%1$s" for "%2$s".', [ $key, $ownerId ]));
    }
    return $cryptor->decrypt($value);
  }

  /**
   * Return the entire encrypted config-space.
   *
   * @param string $ownerId
   *
   * @return array<string, string> Configs as KEY => DECRYPTED_VALUE
   */
  public function getSharedPrivateData(string $ownerId):array
  {
    $privateConfigKeys = array_filter(
      $this->cloudConfig->getUserKeys($ownerId, $this->appName),
      function($configKey) {
        return str_starts_with($configKey, self::CONFIG_KEY_PREFIX);
      });
    $privateConfig = [];
    foreach ($privateConfigKeys as $configKey) {
      $configKey = substr($configKey, strlen(self::CONFIG_KEY_PREFIX));
      $privateConfig[$configKey] = $this->getSharedPrivateValue($ownerId, $configKey);
    }
    return $privateConfig;
  }

  /**
   * Remove all shared private values, e.g. after a password was lost.
   *
   * @param string $ownerId
   */
  public function removeSharedPrivateData(string $ownerId)
  {
    foreach ($this->cloudConfig->getUserKeys($ownerId, $this->appName) as $configKey) {
      if (str_starts_with($configKey, self::CONFIG_KEY_PREFIX)) {
        $this->cloudConfig->deleteUserValue($ownerId, $this->appName, $configKey);
      }
    }
  }

  /**
   * Recrypt all shared private values with a new key.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   *
   * @param array<string, string> $oldKeyPair
   * ```
   * [
   *   self::PRIVATE_ENCRYPTION_KEY_CONFIG => PRIV_KEY,
   *   self::PUBLIC_ENCRYPTION_KEY_CONFIG => PUB_KEY,
   * ]
   * ```
   *
   * @param array<string, string> $newKeyPair
   * ```
   * [
   *   self::PRIVATE_ENCRYPTION_KEY_CONFIG => PRIV_KEY,
   *   self::PUBLIC_ENCRYPTION_KEY_CONFIG => PUB_KEY,
   * ]
   * ```
   */
  public function recryptSharedPrivateData(string $ownerId, array $oldKeyPair, array $newKeyPair)
  {
    $cryptor = $this->getCryptor($ownerId)
      ->setPrivateKey($oldKeyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG])
      ->setPublicKey($oldKeyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG]);
    $configValues = $this->getSharedPrivateData($ownerId);
    $cryptor = $this->getCryptor($ownerId)
      ->setPrivateKey($newKeyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG])
      ->setPublicKey($newKeyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG]);
    foreach ($configValues as $configKey => $configValue)  {
      $this->setSharedPrivateValue($ownerId, $configKey, $configValue);
    }
  }

  /**
   * Fetch the list of all or a matching encryption request.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   *
   * @return array<string, string> USER_ID => REQUEST_VALUE
   */
  public function getRecryptionRequests(?string $ownerId = null):array
  {
    if (!empty($ownerId)) {
      $requestValue = $this->cloudConfig->getUserValue($ownerId, $this->appName, self::RECRYPTION_REQUEST_KEY);
      $requests[$ownerId] = $requestValue;
    } else  {
      $recryptionUsers = $this->cloudConfig->getUsersForUserValue($this->appName, self::RECRYPTION_REQUEST_KEY);
      $requests = $this->cloudConfig->getUserValueForUsers($this->appName, self::RECRYPTION_REQUEST_KEY, $recryptionUsers);
    }
    return $requests;
  }

  /*
   * Push a new recryption-request notification and record the request int the user preferences.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   */
  public function pushRecryptionRequestNotification(string $ownerId):INotification
  {
    $requestData = $this->appContainer->get(ITimeFactory::class)->getTime();
    $this->cloudConfig->setUserValue($ownerId, $this->appName, self::RECRYPTION_REQUEST_KEY, $requestData);

    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setDateTime(new \DateTime)
      ->setObject('owner_id', $ownerId)
      ->setSubject(Notifier::RECRYPT_USER_SUBJECT, [ 'timestamp' => $requestData ])
      ->addAction($notification->createAction()
        ->setLabel(Notifier::ACCEPT_ACTION)
        ->setLink('user_recrypt_request', 'POST'))
      ->addAction($notification->createAction()
        ->setLabel(Notifier::DECLINE_ACTION)
        ->setLink('user_recrypt_request', 'DELETE'));

    /** @var OrganizationalRolesService $organizationalRoles */
    $organizationalRoles = $this->appContainer->get(OrganizationalRolesService::class);
    /** @var \OCP\IUser $groupAdmin */
    foreach ($organizationalRoles->getGroupAdmins() as $groupAdmin) {
      $groupAdminUid = $groupAdmin->getUID();
      if ($groupAdminUid == $ownerId) {
        $this->logInfo('Omitting key-owner from recryption notification.');
        continue;
      }
      $this->logInfo('Notifying ' . $groupAdminUid);
      $notification->setUser($groupAdminUid);
      $notificationManager->notify($notification);
    }

    return $notification;
  }

  /**
   * Remove a recryption request notification, e.g. after processing the
   * request. This actually also removes the request itself from the queue.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   */
  public function removeRecryptionRequestNotification(string $ownerId)
  {
    $this->cloudConfig->deleteUserValue($ownerId, $this->appName, self::RECRYPTION_REQUEST_KEY);

    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setSubject(Notifier::RECRYPT_USER_SUBJECT)
      ->setObject('owner_id', $ownerId);
    $notificationManager->markProcessed($notification);
  }

  /**
   * Push a new recryption-request-denied notification to the requesting party.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   *
   * @param bool $allowProtest Add a button for re-requesting recryption.
   *
   * @throws Exceptions\RecryptionRequestNotFoundException
   */
  public function pushRecryptionRequestDeniedNotification($ownerId, bool $allowProtest = true)
  {
    $requestData = $this->cloudConfig->getUserValue($ownerId, $this->appName, self::RECRYPTION_REQUEST_KEY);

    if (empty($requestData)) {
      throw new Exceptions\RecryptionRequestNotFoundException($this->l->t('No pending recryption-request for user "%s".', $ownerId));
    }

    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setUser($ownerId)
      ->setDateTime(new \DateTime)
      ->setObject('owner_id', $ownerId)
      ->setSubject(Notifier::RECRYPT_USER_DENIED_SUBJECT, [ 'timestamp' => $requestData ]);
    if ($allowProtest) {
      $notification->addAction($notification->createAction()
        ->setLabel(Notifier::PROTEST_ACTION)
        ->setLink('user_recrypt_request', 'PUT'));
    }

    $this->logInfo('PUSHING RECRYPTION DENIED ' . $ownerId . ', VALID ' . (int)$notification->isValid());

    $notificationManager->notify($notification);

    return $notification;
  }

  /**
   * Remove the "denied" notification, for example after accepting a "veto"
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   */
  public function removeRecryptionRequestDeniedNotification($ownerId)
  {
    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setUser($ownerId)
      ->setSubject(Notifier::RECRYPT_USER_DENIED_SUBJECT)
      ->setObject('owner_id', $ownerId);
    $notificationManager->markProcessed($notification);
  }

  /**
   * Push a new recryption-request-handled notification to the requesting party.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   *
   * @throws Exceptions\RecryptionRequestNotFoundException
   */
  public function pushRecryptionRequestHandledNotification($ownerId)
  {
    $requestData = $this->cloudConfig->getUserValue($ownerId, $this->appName, self::RECRYPTION_REQUEST_KEY);

    if (empty($requestData)) {
      throw new Exceptions\RecryptionRequestNotFoundException($this->l->t('No pending recryption-request for user "%s".', $ownerId));
    }

    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setUser($ownerId)
      ->setDateTime(new \DateTime)
      ->setObject('owner_id', $ownerId)
      ->setSubject(Notifier::RECRYPT_USER_HANDLED_SUBJECT, [ 'timestamp' => $requestData ]);

    $notificationManager->notify($notification);

    return $notification;
  }

  /**
   * Remove the "handled" notification, for example after things have been
   * messed up, a new encryption request was posted etc.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'. If null then the currently logged in user is used.
   */
  public function removeRecryptionRequestHandledNotification($ownerId)
  {
    /** @var NotificationManager $notificationManager */
    $notificationManager = $this->appContainer->get(NotificationManager::class);
    $notification = $notificationManager->createNotification();

    $notification->setApp($this->appName)
      ->setUser($ownerId)
      ->setSubject(Notifier::RECRYPT_USER_HANDLED_SUBJECT)
      ->setObject('owner_id', $ownerId);
    $notificationManager->markProcessed($notification);
  }
}
