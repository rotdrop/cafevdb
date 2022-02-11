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

use OCP\IL10N;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\Event;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\IUserSession;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;

/**
 * Support functions encapsulating the underlying encryption framework
 * (currently openssl)
 */
class AsymmetricKeyService
{
  const PUBLIC_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PUBLIC_ENCRYPTION_KEY;
  const PRIVATE_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PRIVATE_ENCRYPTION_KEY;

  /** @var IUserSession */
  private $userSession;

  /** @var ICredentialsStore */
  private $credentialsStore;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var IL10N */
  private $l;

  /** @var AsymmetricKeyStorageInterface */
  private $keyStorage;

  /** @var array */
  static private $keyPairs = [];

  public function __construct(
    IUserSession $userSession
    , ICredentialsStore $credentialsStore
    , IEventDispatcher $eventDispatcher
    , IL10N $l10n
    , AsymmetricKeyStorageInterface $keyStorage
  ) {
    $this->userSession = $userSession;
    $this->credentialsStore = $credentialsStore;
    $this->eventDispatcher = $eventDispatcher;
    $this->l = $l10n;
    $this->keyStorage = $keyStorage;
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
   * @throws Exceptions\EncryptionKeyException
   *
   * @return array<string, string>
   * ```
   * [
   *   self::PRIVATE_ENCRYPTION_KEY_CONFIG => PRIV_KEY,
   *   self::PUBLIC_ENCRYPTION_KEY_CONFIG => PUB_KEY,
   * ]
   * ```
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

    $keyPair = $forceNewKeyPair ? null : $this->keyStorage->getKeyPair($ownerId, $keyPassphrase);
    if (empty($keyPair[self::PRIVATE_ENCRYPTION_KEY_CONFIG]) || empty($keyPair[self::PUBLIC_ENCRYPTION_KEY_CONFIG])) {

      $oldKeyPair = self::$keyPairs[$ownerId] ?? null;
      if (empty($oldKeyPair) && $ownerId == $this->getSessionUserId()) {
        $loginPassword = $this->getLoginPassword($ownerId);
        if (!empty($loginPassword)) {
          $oldKeyPair = $this->keyStorage->getKeyPair($ownerId, $loginPassword);
        }
      }

      $this->eventDispatcher->dispatchTyped(new Events\BeforeEncryptionKeyPairChanged($ownerId, $oldKeyPair));

      $keyPair = $this->keyStorage->generateKeyPair($ownerId, $keyPassphrase);

      $this->eventDispatcher->dispatchTyped(new Events\AfterEncryptionKeyPairChanged($ownerId, $oldKeyPair, $keyPair));
    }

    self::$keyPairs[$ownerId] = $keyPair;

    return $keyPair;
  }

  private function getSessionUserId()
  {
    $user = $this->userSession->getUser();
    return empty($user) ? null : $user->getUID();
  }

  private function getLoginPassword(string $ownerId)
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
}
