<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * FileData
 *
 * Simple data table for image blobs.
 *
 * @ORM\Table(name="FileData")
 * @ORM\Entity
 */
class FileData implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var File
   *
   * @ORM\Id
   * @ORM\OneToOne(targetEntity="File", inversedBy="fileData", cascade="all")
   */
  private $file;

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
   * Set data.
   *
   * @param string $data
   *
   * @return FileData
   */
  public function setData($data)
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
    rewind($this->data);
    return $this->data;
  }

  /**
   * Set file.
   *
   * @param $file
   *
   * @return FileData
   */
  public function setFile(File $file):FileData
  {
    $this->file = $file;

    return $this;
  }

  /**
   * Get file.
   *
   * @return File
   */
  public function getFile():File
  {
    return $this->file;
  }
}
