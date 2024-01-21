<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Exceptions;

/** Use the encryption service provided by the ambient cloud software. */
class SealCryptor implements ICryptor
{
  /** @var array */
  private $sealCryptors = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private SealService $sealService,
  ) {
  }
  // phpcs:enable

  /**
   * Replace the set of seal-cryptors by the given one.
   *
   * @param array<string, ICryptor>  $sealCryptors
   *
   * @return void
   */
  public function setSealCryptors(array $sealCryptors):void
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
   *
   * @return void
   */
  public function addSealCryptor(string $userId, ICryptor $cryptor):void
  {
    $this->sealCryptors[$userId] = $cryptor;
  }

  /**
   * Remove the seal-cryptor for the given user-id.
   *
   * @param string $userId
   *
   * @return void
   */
  public function removeSealCryptor(string $userId):void
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
    $candidates = array_filter($candidates, fn($id) => $this->sealCryptors[$id]->canDecrypt());
    if (empty($candidates)) {
      throw new Exceptions\DecryptionFailedException('Unable to unseal, no valid candidates');
    }
    $id = array_shift($candidates);
    return $this->sealService->unseal($data, $id, $this->sealCryptors[$id]);
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
    // it is enough to have one cryptor which can decrypt the data
    foreach ($this->sealCryptors as $cryptor) {
      if ($cryptor->canDecrypt()) {
        return true;
      }
    }
    return false;
  }

  /** {@inheritdoc} */
  public function isEncrypted(?string $data):?bool
  {
    return $this->sealService->isSealedData($data);
  }

  /** @return SealService The used SealService instance. */
  public function getSealService():SealService
  {
    return $this->sealService;
  }
}
