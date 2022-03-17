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
   * As ORM still does not support lazy one-to-one associations from the
   * inverse side we just use one-directional from both sides here. This
   * works, as the join column is just the key of both sides. So we have no
   * "mappedBy" and "inversedBy".
   *
   * @ORM\Id
   * @ORM\OneToOne(targetEntity="EncryptedFile", cascade={"all"})
   */
  private $file;

  /**
   * @MediaMonks\Transformable(name="encrypt", override=true, context="encryptionContext")
   */
  private $data;

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
   * _AT_ORM\PostLoad -- this would fetch the entire musician entity on load
   * @ORM\PrePersist
   * @ORM\PreUpdate
   *
   * Ensure that the encryptionContext contains the user-id of the owning musician.
   */
  public function sanitizeEncryptionContext(LifecycleEventArgs $eventArgs)
  {
    /** @var Musician $owner */
    foreach (($this->file->owners??[]) as $owner) {
      $userIdSlug = $owner->getUserIdSlug();
      if (!in_array($userIdSlug, $this->encryptionContext ?? [])) {
        $this->encryptionContext[] = $userIdSlug;
      }
    }
  }
}
