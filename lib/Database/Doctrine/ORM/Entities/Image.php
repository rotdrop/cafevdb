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
 * Image -- Meta data for images stored in the data-base.
 *
 * The actual image data is stored in ImageData for performance reasons.
 *
 * @ORM\Table(name="Images")
 * @ORM\Entity
 */
class Image implements \ArrayAccess
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
   * @var string|null
   *
   * @ORM\Column(name="mime_type", type="string", length=128, nullable=true)
   */
  private $mimeType;

  /**
   * @var int
   *
   * @ORM\Column(name="width", type="integer", nullable=false, options={"default"=-1})
   */
  private $width;

  /**
   * @var int
   *
   * @ORM\Column(name="height", type="integer", nullable=false, options={"default"=-1})
   */
  private $height;

  /**
   * @var string|null
   *
   * @ORM\Column(name="md5", type="string", length=32, nullable=true, options={"fixed"=true})
   */
  private $md5;

  /**
   * @var int
   *
   * @ORM\Column(name="image_data_id", type="integer", nullable=false, options={"default"=-1})
   */
  private $imageDataId;

  /**
   * @var ImageData
   *
   * @ORM\OneToOne(targetEntity="ImageData", mappedBy="image", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="image_data_id", referencedColumnName="id")
   */
  private $imageData;

  /**
   * @var Musician[]
   *
   * @ORM\ManyToMany(targetEntity="Musician", mappedBy="photo", fetch="EXTRA_LAZY")
   */
  private $musicians;

  /**
   * @var Project[]
   *
   * @ORM\ManyToMany(targetEntity="Project", mappedBy="posters", fetch="EXTRA_LAZY")
   */
  private $posterProjects;

  /**
   * @var Project[]
   *
   * @ORM\ManyToMany(targetEntity="Project", mappedBy="flyers", fetch="EXTRA_LAZY")
   */
  private $flyerProjects;

  public function __construct() {
    $this->arrayCTOR();
    $this->musicians = new ArrayCollection();
    $this->flyerProjects = new ArrayCollection();
    $this->posterProjects = new ArrayCollection();
    $this->imageData = null;
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set mimetype.
   *
   * @param string|null $mimetype
   *
   * @return Image
   */
  public function setMimetype($mimeType = null)
  {
    $this->mimetype = $mimeType;

    return $this;
  }

  /**
   * Get mimetype.
   *
   * @return string|null
   */
  public function getMimetype()
  {
    return $this->mimeType;
  }

  /**
   * Set md5.
   *
   * @param string|null $md5
   *
   * @return Image
   */
  public function setMd5($md5 = null)
  {
    $this->md5 = $md5;

    return $this;
  }

  /**
   * Get md5.
   *
   * @return string|null
   */
  public function getMd5()
  {
    return $this->md5;
  }

  /**
   * Set $width.
   *
   * @param int $imageData
   *
   * @return Image
   */
  public function setWidth(int $imageData = -1)
  {
    $this->width = $width;

    return $this;
  }

  /**
   * Get $width.
   *
   * @return int
   */
  public function getWidth():int
  {
    return $this->width;
  }

  /**
   * Set $height.
   *
   * @param int $imageData
   *
   * @return Image
   */
  public function setHeight(int $imageData = -1)
  {
    $this->height = $height;

    return $this;
  }

  /**
   * Get $height.
   *
   * @return int
   */
  public function getHeight():int
  {
    return $this->height;
  }

  /**
   * Set $imageDataId.
   *
   * @param int $imageData
   *
   * @return Image
   */
  public function setImageDataId(int $imageData = -1)
  {
    $this->imageDataId = $imageDataId;

    return $this;
  }

  /**
   * Get $imageDataId.
   *
   * @return int
   */
  public function getImageDataId():int
  {
    return $this->imageDataId;
  }

  /**
   * Set ImageData.
   *
   * @param ImageData|null $data
   *
   * @return Image
   */
  public function setImageData($imageData = null)
  {
    $this->imageData = $imageData;

    return $this;
  }

  /**
   * Get ImageData.
   *
   * @return ImageData|null
   */
  public function getImageData()
  {
    return $this->imageData;
  }
}
