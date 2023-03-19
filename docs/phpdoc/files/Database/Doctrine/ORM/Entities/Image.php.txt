<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\Image as CloudImage;

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
   * {@inheritdoc}
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
   * Allow construction from a cloud image obejct.
   *
   * @param null|string $fileName
   *
   * @param null|CloudImage $image
   */
  public function __construct(?string $fileName = null, ?CloudImage $image = null)
  {
    parent::__construct($fileName, $image->data(), $image->mimeType());
    $this->setWidth($image->width())
      ->setHeight($image->height());
  }

  /**
   * Set $width.
   *
   * @param int $width
   *
   * @return Image
   */
  public function setWidth(int $width = -1):Image
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
   * @param int $height
   *
   * @return Image
   */
  public function setHeight(int $height = -1):Image
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
