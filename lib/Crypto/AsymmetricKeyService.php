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
use OCA\CAFEVDB\Exceptions;

/**
 * Support functions encapsulating the underlying encryption framework
 * (currently openssl)
 */
class AsymmetricKeyService
{
  const PUBLIC_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PUBLIC_ENCRYPTION_KEY;
  const PRIVATE_ENCRYPTION_KEY_CONFIG = AsymmetricKeyStorageInterface::PRIVATE_ENCRYPTION_KEY;

  /** @var IL10N */
  private $l;

  /** @var AsymmetricKeyStorageInterface */
  private $keyStorage;

  public function __construct(
    IL10N $l10n
    , AsymmetricKeyStorageInterface $keyStorage
  ) {
    $this->l = $l10n;
    $this->keyStorage = $keyStorage;
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
  public function initEncryptionKeyPair(?string $ownerId, ?string $keyPassphrase, bool $forceNewKeyPair = false)
  {
    if (empty($ownerId) || empty($keyPassphrase)) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize SSL key-pair without user and password'));
    }

    return $this->keyStorage->initializeKeyPair($ownerId, $keyPassphrase, $forceNewKeyPair);
  }
}
