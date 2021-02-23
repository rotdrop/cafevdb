<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Doctrine\ORM\Mapping as ORM;

/**
 * MusicianPhoto
 *
 * @ORM\Table(name="MusicianPhoto", uniqueConstraints={@ORM\UniqueConstraint(columns={"owner_id", "image_id"})})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusicianPhotosRepository")
 */
class MusicianPhoto implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UpdatedAt;
  use CAFEVDB\Traits\CreatedAtEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * Doctrine/ORM ignores the nullable attribute on the join column. Why?
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $ownerId;

  /**
   * @ORM\OneToOne(targetEntity="Musician", cascade="persist", inversedBy="photo", fetch="EXTRA_LAZY")
   * @ ORM\JoinColumn(nullable=false)
   */
  private $owner;

  /**
   * @var int
   *
   * Doctrine/ORM ignores the nullable attribute on the join column. Why?
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $imageId;

  /**
   * @ORM\OneToOne(targetEntity="Image", cascade="all", orphanRemoval=true)
   * @ ORM\JoinColumn(nullable=false)
   */
  private $image;

  /**
   * @var \DateTimeImmutable
   * @Gedmo\Timestampable(on={"update","change"}, field={"image.updated"})
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private $updated;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set ownerId.
   *
   * @param int $ownerId
   *
   * @return MusicianPhoto
   */
  public function setOwnerId($ownerId):self
  {
    $this->ownerId = $ownerId;

    return $this;
  }

  /**
   * Get ownerId.
   *
   * @return int
   */
  public function getOwnerId():int
  {
    return $this->ownerId;
  }

  /**
   * Set imageId.
   *
   * @param int $imageId
   *
   * @return MusicianPhoto
   */
  public function setImageId($imageId):self
  {
    $this->imageId = $imageId;

    return $this;
  }

  /**
   * Get imageId.
   *
   * @return int
   */
  public function getImageId():int
  {
    return $this->imageId;
  }

  /**
   * Set owner.
   *
   * @param int $owner
   *
   * @return OwnerPhoto
   */
  public function setOwner($owner):self
  {
    $this->owner = $owner;

    return $this;
  }

  /**
   * Get owner.
   *
   * @return Muscian
   */
  public function getOwner():Owner
  {
    return $this->owner;
  }

  /**
   * Set image.
   *
   * @param int $image
   *
   * @return OwnerPhoto
   */
  public function setImage($image):self
  {
    $this->image = $image;

    return $this;
  }

  /**
   * Get image.
   *
   * @return Image
   */
  public function getImage():Image
  {
    return $this->image;
  }
}
