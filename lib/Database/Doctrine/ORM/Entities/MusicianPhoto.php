<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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
  protected $ownerId;

  /**
   * @var Musician
   *
   * @ORM\OneToOne(targetEntity="Musician", cascade={"persist"}, inversedBy="photo", fetch="EXTRA_LAZY")
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
   * @ORM\OneToOne(targetEntity="Image", cascade={"all"}, orphanRemoval=true)
   */
  private $image;

  /**
   * @var \DateTimeImmutable
   * @Gedmo\Timestampable(on={"update","change"}, field={"image.updated"})
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  protected $updated;


  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return MusicianPhoto
   */
  public function setId(?int $id):MusicianPhoto
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set imageId.
   *
   * @param int $imageId
   *
   * @return MusicianPhoto
   */
  public function setImageId(?int $imageId):MusicianPhoto
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
   * @param null|int|Musician $owner
   *
   * @return OwnerPhoto
   */
  public function setOwner(mixed $owner):MusicianPhoto
  {
    $this->owner = $owner;

    return $this;
  }

  /**
   * Get owner.
   *
   * @return null|Muscian
   */
  public function getOwner():?Musician
  {
    return $this->owner;
  }

  /**
   * Set image.
   *
   * @param null|Image $image
   *
   * @return OwnerPhoto
   */
  public function setImage(?Image $image):MusicianPhoto
  {
    $this->image = $image;

    return $this;
  }

  /**
   * Get image.
   *
   * @return Image
   */
  public function getImage():?Image
  {
    return $this->image;
  }
}
