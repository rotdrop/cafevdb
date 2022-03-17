<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Annotation as MediaMonks;

/**
 * EncryptedFileData
 *
 * @ORM\Entity
 */
class EncryptedFileData extends FileData
{
  /**
   * @MediaMonks\Transformable(name="encrypt", override=true, context="encryptionContext")
   */
  private $data;

  /**
   * @var array
   *
   * @ORM\Column(type="json")
   *
   * In memory encryption context to support multi user encryption.
   */
  private $encryptionContext;

  /**
   * Return the encryption context which is an array of user-ids.
   *
   * @return null|array<int, string>
   */
  public function getEncryptionContext():?array
  {
    return $this->encryptionContext;
  }

  /**
   * Set the array of authorized users.
   *
   * @param array<int, string> $context
   *
   * @return EncryptedFileData
   */
  public function setEncryptionContext(?array $context):EncryptedFileData
  {
    $this->encryptionContext = $context;
    return $this;
  }

  /**
   * Add a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return EncryptedFileData
   */
  public function addEncryptionIdentity(string $personality):EncryptedFileData
  {
    if (empty($this->encryptionContext)) {
      $this->encryptionContext = [];
    }
    $this->encryptionContext[] = $personality;
    return $this;
  }

  /**
   * Remove a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return EncryptedFileData
   */
  public function removeEncryptionIdentity(string $personality):EncryptedFileData
  {
    $pos = array_search($personality, $this->encryptionContext??[]);
    if ($pos !== false) {
      unset($this->encryptionContext[pos]);
      $this->encryptionContext = array_values($this->encryptionContext);
    }
    return $this;
  }
}
