<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\EncryptionService;

class Encryption implements Transformable\Transformer\TransformerInterface
{
  /** @var string */
  private $encryptionKey;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var bool */
  private $cachable;

  public function __construct(EncryptionService $encryptionService)
  {
    $this->encryptionService = $encryptionService;
    $this->encryptionKey = $this->encryptionService->getAppEncryptionKey();
    $this->cachable = true;
  }

  /**
   * @param string encryptionKey The new encryption-key
   */
  public function setEncryptionKey(string $encryptionKey)
  {
    $oldKey = $this->encryptionKey;
    $this->encryptionKey = $encryptionKey;
    return $oldKey;
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
    return $this->encryptionService->encrypt($value, $this->encryptionKey);
  }

  /**
   * @param string $value Encrypted data.
   *
   * @return string Decrypted data.
   */
  public function reverseTransform($value)
  {
     return $this->encryptionService->decrypt($value, $this->encryptionKey);
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
