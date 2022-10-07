<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping as MediaMonks;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event\LifecycleEventArgs;

/**
 * EncryptedFileData
 *
 * @ORM\Entity
 *
 * @ORM\HasLifecycleCallbacks
 */
class EncryptedFileData extends FileData
{
  /**
   * @var EncryptedFile
   *
   * {@inheritdoc}
   *
   * @ORM\Id
   * @ORM\ManyToOne(targetEntity="EncryptedFile", inversedBy="fileData", cascade={"all"})
   */
  protected $file;

  /**
   * @MediaMonks\Transformable(name="encrypt", override=true, context="encryptionContext")
   */
  protected $data;

  /**
   * @var array
   *
   * In memory encryption context to support multi user encryption.
   */
  private $encryptionContext;

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
    if (!in_array($personality, $this->encryptionContext)) {
      $this->encryptionContext[] = $personality;
    }
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

  /**
   * @ORM\PostLoad
   * @ORM\PrePersist
   * _AT_ORM\PreUpdate
   *
   * Ensure that the encryptionContext contains the user-id of the owning musician.
   */
  public function sanitizeEncryptionContext(LifecycleEventArgs $eventArgs)
  {
    /** @var Musician $owner */
    foreach (($this->file->getOwners()??[]) as $owner) {
      $userIdSlug = $owner->getUserIdSlug();
      if (!empty($userIdSlug) && !in_array($userIdSlug, $this->encryptionContext ?? [])) {
        $this->encryptionContext[] = $userIdSlug;
      }
    }
  }
}
