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
 * ImageData
 *
 * Simple data table for image blobs.
 *
 * @ORM\Table(name="ImageData")
 * @ORM\Entity
 */
class ImageData implements \ArrayAccess
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
   * @ORM\Column(name="image_id", type="integer", nullable=false)
   */
  private $imageId;

  /**
   * @var string|null
   *
   * @ORM\Column(name="data", type="text", length=0, nullable=false)
   */
  private $data;

  /**
   * @var Image
   *
   * Inverse side.
   *
   * @ORM\OneToOne(targetEntity="Image", mappedBy="imageData")
   * @ORM\JoinColumn(name="image_id", unique=true, referencedColumnName="id")
   */
  private $image;

  public function __construct() {
    $this->arrayCTOR();
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
   * Set data.
   *
   * @param string|null $data
   *
   * @return ImageData
   */
  public function setData($data = null)
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return string|null
   */
  public function getData()
  {
    return $this->data;
  }
}
