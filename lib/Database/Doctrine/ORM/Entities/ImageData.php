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
   * @var Image
   *
   * @ORM\Id
   * @ORM\OneToOne(targetEntity="Image", inversedBy="imageData", cascade="all")
   */
  private $image;

  /**
   * @var string|null
   *
   * @ORM\Column(type="blob", nullable=false)
   */
  private $data;

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
  public function setData($data = null, string $format = 'binary')
  {
    switch ($format) {
      case 'base64':
        $this->data = base64_decode($data);
      default:
      case 'resource':
      case 'binary':
        $this->data = $data;
        break;
    }

    return $this;
  }

  /**
   * Get data.
   *
   * @return string|null
   */
  public function getData(string $format = 'binary')
  {
    switch ($format) {
      case 'base64':
        return base64_encode(stream_get_contents($this->data));
      case 'resource':
        return $this->data;
      case 'binary':
        return stream_get_contents($this->data);
      default:
        return $this->data;
    }
  }

  /**
   * Set image.
   *
   * @param $image
   *
   * @return ImageData
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
