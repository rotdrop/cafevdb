<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common\Crypto;

use OCP\Security\ICrypto;
use OCA\CAFEVDB\Exceptions;

/** Use the encryption service provided by the ambient cloud software. */
class SealCryptor implements ICryptor
{
  /** @var SealService */
  private $sealService;

  /** @var array */
  private $sealCryptors = [];

  public function __construct(SealService $sealService)
  {
    $this->sealService = $sealService;
  }

  /**
   * Replace the set of seal-cryptors by the given one.
   *
   * @param array<string, ICryptor>  $sealCryptors
   */
  public function setSealCryptors(array $sealCryptors)
  {
    $this->sealCryptors = $sealCryptors;
  }

  /**
   * Return the set of currently used seal-cryptors.
   *
   * @return array<string, ICryptor>
   */
  public function getSealCryptors():array
  {
    return $this->sealCryptors;
  }

  /**
   * Add a new cryptor to the set of seal-cryptors.
   *
   * @param string $userId
   *
   * @param ICryptor $cryptor
   */
  public function addSealCryptor(string $userId, ICryptor $cryptor)
  {
    $this->sealCryptors[$userId] = $cryptor;
  }

  /**
   * Remove the seal-cryptor for the given user-id.
   *
   * @param string $userId
   */
  public function removeSealCryptor(string $userId)
  {
    unset($this->sealCryptors[$userId]);
  }

  /** {@inheritdoc} */
  public function encrypt(?string $data):?string
  {
    if (empty($this->sealCryptors)) {
      return $data;
    }
    return $this->sealService->seal($data, $this->sealCryptors);
  }

  /** {@inheritdoc} */
  public function decrypt(?string $data):?string
  {
    if (!$this->sealService->isSealedData($data)) {
      return $data;
    }
    $sealData = $this->sealService->parseSeal($data);
    $candidates = array_intersect(array_keys($this->sealCryptors), array_keys($sealData['keys']));
    if (empty($candidates)) {
      throw new Exceptions\DecryptionFailedException('Unable to unseal, no valid candidates');
    }
    $user = array_shift($candidates);
    return $this->sealService->unseal($data, $user, $this->sealCryptors[$user]);
  }

  /** {@inheritdoc} */
  public function canEncrypt():bool
  {
    foreach ($this->sealCryptors as $cryptor) {
      if (!$cryptor->canEncrypt()) {
        return false;
      }
    }
    return true;
  }

  /** {@inheritdoc} */
  public function canDecrypt():bool
  {
    foreach ($this->sealCryptors as $cryptor) {
      if (!$cryptor->canDecrypt()) {
        return false;
      }
    }
    return true;
  }

  /** Return the used SealService instance. */
  public function getSealService():SealService
  {
    return $this->sealService;
  }
};
