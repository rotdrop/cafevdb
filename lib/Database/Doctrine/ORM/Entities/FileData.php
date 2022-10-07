<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * FileData
 *
 * Simple data table for image blobs.
 *
 * @ORM\Table(name="FileData")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="EnumFileType")
 * @ORM\DiscriminatorMap({"identity"="FileData", "image"="ImageFileData", "encrypted"="EncryptedFileData"})
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class FileData implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var File
   *
   * As ORM still does not support lazy one-to-one associations from the
   * inverse side we use a OneToMany - ManyToOne trick which inserts a lazy
   * association in between.
   *
   * @ORM\Id
   * @ORM\ManyToOne(targetEntity="File", inversedBy="fileData", cascade={"all"})
   */
  protected $file;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed"=true})
   * @Gedmo\Slug(fields={"data"}, updatable=true, unique=false, handlers={
   *   @Gedmo\SlugHandler(class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler"),
   *   @Gedmo\SlugHandler(
   *     class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\AssociationSlugHandler",
   *     options={
   *       @Gedmo\SlugHandlerOption(name="associationSlug", value="file.dataHash")
   *     })
   * })
   *
   */
  protected $dataHash;

  /**
   * @var string
   *
   * @ORM\Column(type="blob", nullable=false)
   */
  protected $data;

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
  public function setData($data, string $format = 'binary')
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
    if (is_resource($this->data)) {
      rewind($this->data);
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
    } else {
      switch ($format) {
      case 'base64':
        return base64_encode($this->data);
      case 'resource':
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $this->data);
        rewind($stream);
        return $stream;
      case 'binary':
        return $this->data;
      default:
        return $this->data;
      }
    }
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
