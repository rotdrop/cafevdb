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

use OCP\IConfig;
use OCP\IL10N;

/**
 * Support functions encapsulating the underlying encryption framework
 * (currently openssl)
 */
class AsymmetricKeyService
{
  const PUBLIC_SSL_KEY_CONFIG = 'publicSSLKey';
  const PRIVATE_SSL_KEY_CONFIG = 'privateSSLKey';

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $cloudConfig;

  /** @var IL10N */
  private $l;

  public function __construct(
    string $appName
    , IConfig $cloudConfig
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->cloudConfig = $cloudConfig;
    $this->l = $l10n;
  }

  /**
   * Initialize a private/public key-pair by either retreiving it from the
   * config-space or generating a new one.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param string $keyPassphrase The passphrase used to protect the private
   * key.
   *
   * @param bool $forceNewKeyPair Generate a new key pair even if an
   * old one is found.
   *
   * @throws Exceptions\EncryptionKeyException
   *
   * @return array<string, string>
   * ```
   * [ 'privateSSLKey' => PRIV_KEY, 'publicSSLKey' => PUB_KEY ]
   * ```
   */
  public function initSSLKeyPair(?string $ownerId, ?string $keyPassphrase, bool $forceNewKeyPair = false)
  {
    if (empty($ownerId) || empty($keyPassphrase)) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize SSL key-pair without user and password'));
    }

    if (!$forceNewKeyPair) {
      $privKey = $this->getUserValue($ownerId, self::PRIVATE_SSL_KEY_CONFIG, null);
      $pubKey = $this->getUserValue($ownerId, self::PUBLIC_SSL_KEY_CONFIG, null);
    }
    if (empty($privKey) || empty($pubKey)) {
      // Ok, generate one. But this also means that we have not yet
      // access to the data-base encryption key.
      $keys = $this->generateSSLKeyPair($ownerId, $keyPassphrase);
      if ($keys === false) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Unable to generate SSL key pair for user "%s".', [ $ownerId ]));
      }
      list($privKey, $pubKey) = $keys;
    }

    $privKey = openssl_pkey_get_private($privKey, $keyPassphrase);

    if ($privKey === false) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Unable to unlock private key for user "%s"', [ $ownerId ]));
    }

    return [ self::PRIVATE_SSL_KEY_CONFIG => $privKey, self::PUBLIC_SSL_KEY_CONFIG => $pubKey ];
  }

  /**
   * Generate a new SSL key-pair and store it in the user-config-space for the
   * app.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param string $keyPassphrase The passphrase used to protect the private
   * key.
   *
   * @return array<string, string>
   * ```
   * [ 'privateSSLKey' => PRIV_KEY, 'publicSSLKey' => PUB_KEY ]
   * ```
   */
  private function generateSSLKeyPair(string $ownerId, string $keyPassphrase)
  {
    /* Create the private and public key */
    $res = openssl_pkey_new();

    /* Extract the private key from $res to $privKey */
    if (!openssl_pkey_export($res, $privKey, $keyPassphrase)) {
      return false;
    }

    /* Extract the public key from $res to $pubKey */
    $pubKey = openssl_pkey_get_details($res);

    if ($pubKey === false) {
      return false;
    }

    $pubKey = $pubKey['key'];

    // We now store the public key unencrypted in the user preferences.
    // The private key already is encrypted with the user's password,
    // so there is no need to encrypt it again.

    $this->setUserValue($ownerId, self::PUBLIC_SSL_KEY_CONFIG, $pubKey);
    $this->setUserValue($ownerId, self::PRIVATE_SSL_KEY_CONFIG, $privKey);

    return [ self::PRIVATE_SSL_KEY_CONFIG => $privKey, self::PUBLIC_SSL_KEY_CONFIG => $pubKey ];
  }

  private function getUserValue(string $ownerId, string $key, mixed $default)
  {
    return $this->cloudConfig->getUserValue($ownerId, $this->appName, $key, $default);
  }

  private function setUserValue(string $ownerId, string $key, mixed $value)
  {
    return $this->cloudConfig->setUserValue($ownerId, $this->appName, $key, $value);
  }
}
