<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

  public const DATA_FORMAT_BINARY = 'binary';
  public const DATA_FORMAT_BASE64 = 'base64';
  public const DATA_FORMAT_RESOURCE = 'resource';
  public const DATA_FORMAT_URI = 'uri';

  public const DATA_FORMATS = [
    self::DATA_FORMAT_BINARY,
    self::DATA_FORMAT_BASE64,
    self::DATA_FORMAT_RESOURCE,
    self::DATA_FORMAT_URI,
  ];

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
   */
  protected $dataHash;

  /**
   * @var string
   *
   * @ORM\Column(type="blob", nullable=false)
   */
  protected $data;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set data.
   *
   * @param mixed $data
   *
   * @param string $format The input format of the data
   *
   * @return FileData
   */
  public function setData(mixed $data, string $format = self::DATA_FORMAT_BINARY)
  {
    switch ($format) {
      case self::DATA_FORMAT_URI:
        $data =  substr($data, strpos($data, ',') + 1);
        // fallthrough
      case self::DATA_FORMAT_BASE64:
        $data = base64_decode($data);
        // fallthrough
      default:
      case self::DATA_FORMAT_RESOURCE:
      case self::DATA_FORMAT_BINARY:
        $this->data = $data;
        break;
    }

    return $this;
  }

  /**
   * Get data.
   *
   * @param string $format
   *
   * @return mixed
   */
  public function getData(string $format = self::DATA_FORMAT_BINARY):mixed
  {
    if (is_resource($this->data)) {
      rewind($this->data);
      switch ($format) {
        case self::DATA_FORMAT_URI:
          $data = base64_encode(stream_get_contents($this->data));
          return 'data:' . $this->file->getMimeType() . ';base64,' . $data;
        case self::DATA_FORMAT_BASE64:
          return base64_encode(stream_get_contents($this->data));
        case self::DATA_FORMAT_RESOURCE:
          return $this->data;
        case self::DATA_FORMAT_BINARY:
          return stream_get_contents($this->data);
        default:
          return $this->data;
      }
    } else {
      switch ($format) {
        case self::DATA_FORMAT_URI:
          return 'data:' . $this->file->getMimeType() . ';base64,' . base64_encode($this->data);
        case self::DATA_FORMAT_BASE64:
          return base64_encode($this->data);
        case self::DATA_FORMAT_RESOURCE:
          $stream = fopen('php://memory', 'r+');
          fwrite($stream, $this->data);
          rewind($stream);
          return $stream;
        case self::DATA_FORMAT_BINARY:
          return $this->data;
        default:
          return $this->data;
      }
    }
  }

  /**
   * Set file.
   *
   * @param File $file
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
