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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 */
class EncryptedFile extends File
{
  /**
   * @var Collection
   *
   * As ORM still does not support lazy one-to-one associations from the
   * inverse side we just use one-directional from both sides here. This
   * works, as the join column is just the key of both sides. So we have no
   * "mappedBy" and "inversedBy".
   *
   * Not that it is not possible to override the targetEntity annotation from
   * the base-class, so it must go here to the leaf-class.
   *
   * @ORM\OneToMany(targetEntity="EncryptedFileData", mappedBy="file", cascade={"all"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  protected $fileData;

  /**
   * @var Collection
   *
   * @ORM\ManyToMany(targetEntity="Musician", mappedBy="encryptedFiles", indexBy="id", fetch="EXTRA_LAZY")
   *
   * The list of owners which in addition to the members of the management
   * group may have access to this file. This is in particular important for
   * encrypted files where the list of owners determines the encryption keys
   * which are used to seal the data.
   */
  private $owners;

  public function __construct($fileName = null, $data = null, $mimeType = null, ?Musician $owner = null) {
    parent::__construct($fileName, null, $mimeType);
    $this->owners = new ArrayCollection;
    $data = $data ?? '';
    $fileData = new EncryptedFileData;
    $fileData
      ->setData($data)
      ->setFile($this);
    $this->setFileData($fileData)
      ->setSize(strlen($data));
    if (!empty($owner)) {
      $this->addOwner($owner);
    }
  }

  /**
   * Set Owners.
   *
   * @param Collection $owners
   *
   * @return EncryptedFile
   */
  public function setOwners(Collection $owners):EncryptedFile
  {
    $this->owners = $owners;

    return $this;
  }

  /**
   * Get Owners.
   *
   * @return Collection
   */
  public function getOwners():Collection
  {
    return $this->owners;
  }

  /**
   * Add the given musician to the list of owners.
   *
   * @param Musician $musician
   *
   * @return EncryptedFile
   */
  public function addOwner(Musician $musician):EncryptedFile
  {
    $musicianId = $musician->getId();
    if (!$this->owners->containsKey($musicianId)) {
      $this->owners->set($musicianId, $musician);
    }
    return $this;
  }

  /**
   * Remove the given musician from the list of owners
   *
   * @param Musician $musician
   *
   * @return EncryptedFile
   */
  public function removeOwner(Musician $musician):EncryptedFile
  {
    $this->owners->remove($musician->getId());
  }
}
