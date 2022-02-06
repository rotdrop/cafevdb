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

interface AsymmetricKeyStorageInterface
{
  const PUBLIC_ENCRYPTION_KEY = 'publicEncryptionKey';
  const PRIVATE_ENCRYPTION_KEY = 'privateEncryptionKey';

  /**
   * Retrieve an existing private/public key pair from the key-storage.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param string $keyPassphrase The passphrase used to protect the private
   * key.
   *
   * @return array<string, string>
   * ```
   * [ 'privateEncryptionKey' => PRIV_KEY, 'publicEncryptionKey' => PUB_KEY ]
   * ```
   */
  public function getKeyPair(string $ownerId, string $keyPassphrase);

  /**
   * Initialize a private/public key-pair by either retreiving it from the
   * config-space or generating a new one. The returned private key is already
   * unlocked.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param string $keyPassphrase The passphrase used to protect the private
   * key.
   *
   * @return array<string, string>
   * ```
   * [ 'privateEncryptionKey' => PRIV_KEY, 'publicEncryptionKey' => PUB_KEY ]
   * ```
   */
  public function generateKeyPair(string $ownerId, string $keyPassphrase);

  /**
   * Retrieve an existing private key from the key-storage.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param string $keyPassphrase The passphrase used to protect the private
   * key.
   *
   * @return mixed|null Unlocked private key or null if not found.
   */
  public function getPrivateKey(string $ownerId, string $keyPassphrase);

  /**
   * Retrieve an existing pulic key from the key-storage.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @return mixed|null Public key or null if none is found.
   */
  public function getPublicKey(string $ownerId);

  /**
   * Change the the passphrase of the given unlocked private-key.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param mixed $privateKey Unlocked private key as returned by
   * initializeKeyPair(), getPrivateKey(), generateKeyPair().
   *
   * @param string $newPassphrase The new passphrase which protects the newly
   * necrypted key in the storage backend.
   */
  public function setPrivateKeyPassphrase(string $ownerId, $privateKey, string $newPassphrase);

  /**
   * Initialize a private/public key-pair by either retreiving it from the
   * config-space or generating a new one. The returned private key is already
   * unlocked.
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
   * [ 'privateEncryptionKey' => PRIV_KEY, 'publicEncryptionKey' => PUB_KEY ]
   * ```
   */
  public function initializeKeyPair(string $ownerId, string $keyPassphrase, bool $forceNewKeypair = false);

  /**
   * Remove the stored key-pair for the given owner.
   *
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   */
  public function wipeKeyPair(string $ownerId);
}
