<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use OCP\AppFramework\IAppContainer;
use OCP\Security\ICrypto;

/** Factory using the Nextcloud crypto routines as backend. */
class CloudCryptoFactory implements CryptoFactoryInterface
{
  /** @var IAppContainer */
  private $appContainer;

  /** @var ICrypto */
  private $crypto;

  /**
   * @param IAppContainer $appContainer
   *
   * @param ICrypto $crypto
   */
  public function __construct(
    IAppContainer $appContainer,
    ICrypto $crypto,
  ) {
    $this->appContainer = $appContainer;
    $this->crypto = $crypto;
  }

  /** {@inheritdoc} */
  public function getSymmetricCryptor(?string $encryptionKey = null):SymmetricCryptorInterface
  {
    return new CloudSymmetricCryptor($this->crypto, $encryptionKey);
  }

  /** {@inheritdoc} */
  public function getAsymmetricCryptor(mixed $privateKey = null):AsymmetricCryptorInterface
  {
    return new OpenSSLAsymmetricCryptor($privateKey);
  }

  /** {@inheritdoc} */
  public function getAsymmetricKeyStorage():AsymmetricKeyStorageInterface
  {
    return $this->appContainer->get(OpenSSLAsymmetricKeyStorage::class);
  }
}
