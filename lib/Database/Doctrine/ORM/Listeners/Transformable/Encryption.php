<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;

use OCA\CAFEVDB\Common\Crypto\ICryptor;

class Encryption implements Transformable\Transformer\TransformerInterface
{
  /** @var string */
  private $encryptionKey;

  /** @var ICryptor */
  private $cryptor;

  /** @var bool */
  private $cachable;

  public function __construct(ICryptor $cryptor)
  {
    $this->cryptor = $cryptor;
    $this->cachable = true;
  }

  /**
   * @param ICryptor $cryptor
   */
  public function setCryptor(ICryptor $cryptor)
  {
    $this->cryptor = $cryptor;
  }

  /**
   * @return ICryptor
   */
  public function getCryptor():ICryptor
  {
    return $this->cryptor;
  }

  /**
   * @param bool $isCachable Set cachable status of transformer.
   */
  public function setCachable(bool $isCachable)
  {
    $this->cachable = $isCachable;
  }

  /**
   * @param string $value Unencrypted data.
   *
   * @return string Encrypted data.
   */
  public function transform($value)
  {
    return $this->cryptor->encrypt($value);
  }

  /**
   * @param string $value Encrypted data.
   *
   * @return string Decrypted data.
   */
  public function reverseTransform($value)
  {
    return $this->cryptor->decrypt($value);
  }

  /**
   * Disable caching while changing the encryption key.
   *
   * @return bool
   */
  public function isCachable()
  {
    return $this->cachable;
  }
};
