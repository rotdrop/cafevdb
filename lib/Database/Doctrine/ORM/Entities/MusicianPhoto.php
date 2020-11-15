<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * MusicianPhoto
 *
 * @ORM\Table(name="MusicianPhoto", uniqueConstraints={@ORM\UniqueConstraint(name="owner_image", columns={"owner_id", "image_id"})})
 * @ORM\Entity
 */
class MusicianPhoto
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="owner_id", type="integer", nullable=false)
   */
  private $ownerId;

  /**
   * @var int
   *
   * @ORM\Column(name="image_id", type="integer", nullable=false)
   */
  private $imageId;

  /**
   * @ORM\OneToOne(targetEntity="Musician", inversedBy="photo", cascade="all", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="owner_id", referencedColumnName="Id")
   */
  private $owner;

  /**
   * @ORM\OneToOne(targetEntity="Image", cascade="all", orphanRemoval=true)
   * @ORM\JoinColumn(name="image_id", referencedColumnName="id")
   */
  private $image;

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
