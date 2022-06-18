<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Image -- Meta data for images stored in the data-base.
 *
 * The actual image data is stored in FileData for performance reasons.
 *
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ImagesRepository")
 */
class Image extends File
{
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var FileData
   *
   * As ORM still does not support lazy one-to-one associations from the
   * inverse side we just use one-directional from both sides here. This
   * works, as the join column is just the key of both sides. So we have no
   * "mappedBy" and "inversedBy".
   *
   * Also: it is not possible to override the targetEntity from a bass-class
   * annotation, so the OneToOne annotation must got to the
   * leave-class. Further: in "single table inheritance" only leave-classes
   * can be loaded lazily. So we need this artificial ImageFileData class
   * which is just there to provide a lazy-loadable leaf-class.
   *
   * @ORM\OneToMany(targetEntity="ImageFileData", mappedBy="file", cascade={"all"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  protected $fileData;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=-1})
   */
  private $width;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=-1})
   */
  private $height;

  /**
   * Allow construction from a cloud image obejct
   */
  public function __construct($fileName = null, ?\OCP\Image $image = null)
  {
    parent::__construct($fileName, $image->data(), $image->mimeType());
    $this->setWidth($image->width())
      ->setHeight($image->height());
  }

  /**
   * Set $width.
   *
   * @param int $imageData
   *
   * @return Image
   */
  public function setWidth(int $width = -1)
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
  public function setHeight(int $height= -1)
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
}
