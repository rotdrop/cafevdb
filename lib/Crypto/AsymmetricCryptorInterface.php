<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

/** Just there in order to branch depending on container specialization */
interface AsymmetricCryptorInterface extends ICryptor
{
  /**
   * @param mixed $privKey
   *
   * @param null|string $password Maybe ignored depending on the implementation,
   * i.e. the key may have to be unlocked or decrypted before passing it to
   * the cryptor.
   *
   * @return AsymmetricCryptorInterface $this
   */
  public function setPrivateKey(mixed $privKey, ?string $password = null):AsymmetricCryptorInterface;

  /**
   * @param mixed $pubKey
   *
   * @return AsymmetricCryptorInterface $this
   */
  public function setPublicKey(mixed $pubKey):AsymmetricCryptorInterface;

  /** @return mixed Return the currently used public key. */
  public function getPublicKey():mixed;

  /**
   * @param null|string $data
   *
   * @return string
   */
  public function sign(?string $data):string;

  /**
   * @param null|string $data
   *
   * @param string $signature
   *
   * @return string
   */
  public function verify(?string $data, string $signature):bool;

  /** @return bool */
  public function canSign():bool;

  /** @return bool */
  public function canVerify():bool;
}
