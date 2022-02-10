<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * An entity which modesl a file-system file. While it is not always
 * advisable to store file-system data in a data-base, we do so
 * nevertheless for selected small files.
 *
 * @ORM\Table(name="Files",
 *   indexes={
 *     @ORM\Index(columns={"file_name"}),
 *   })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="EnumFileType")
 * @ORM\DiscriminatorMap({"generic"="File","encrypted"="EncryptedFile","image"="Image"})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\FilesRepository")
 */
class File implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UpdatedAt;
  use CAFEVDB\Traits\CreatedAtEntity;

  const PATH_SEPARATOR = '/';

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  private $fileName;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $mimeType;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=-1})
   */
  private $size = -1;

  /**
   * @var FileData
   *
   * @ORM\OneToOne(targetEntity="FileData", mappedBy="file", cascade={"all"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $fileData;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"fixed"=true})
   */
  private $dataHash;

  /**
   * @var \DateTimeImmutable
   * @Gedmo\Timestampable(on={"update","change"}, field="fileData")
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private $updated;

  public function __construct($fileName = null, $data = null, $mimeType = null) {
    $this->arrayCTOR();
    $this->setFileName($fileName);
    $this->setMimeType($mimeType);
    if (!empty($data)) {
      $fileData = new FileData;
      $fileData->setData($data);
      $fileData->setFile($this);
      $this->setFileData($fileData)
           ->setSize(strlen($data));
    }
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
   * Set mimeType.
   *
   * @param string|null $mimeType
   *
   * @return File
   */
  public function setMimeType($mimeType = null):File
  {
    $this->mimeType = $mimeType;

    return $this;
  }

  /**
   * Get mimeType.
   *
   * @return string|null
   */
  public function getMimeType()
  {
    return $this->mimeType;
  }

  /**
   * Set fileName.
   *
   * @param string|null $fileName
   *
   * @return File
   */
  public function setFileName($fileName = null):File
  {
    $this->fileName = $fileName;

    return $this;
  }

  /**
   * Get fileName.
   *
   * @return string|null
   */
  public function getFileName()
  {
    return $this->fileName;
  }

  /**
   * Set only the dir-name
   */
  public function setDirName(string $dirName):File
  {
    $this->fileName = trim($dirName, self::PATH_SEPARATOR) . self::PATH_SEPARATOR . ($this->getBaseName()??'');
    return $this;
  }

  /**
   * Get the dir-part of the file-name
   */
  public function getDirName():?string
  {
    return is_string($this->fileName) ? dirname($this->fileName) : null;
  }

  /**
   * Set only the base-name
   */
  public function setBaseName(string $baseName):File
  {
    $this->fileName = ($this->getDirName()??'') . self::PATH_SEPARATOR . trim($baseName, self::PATH_SEPARATOR);
    return $this;
  }

  /**
   * Get the dir-part of the file-name
   */
  public function getBaseName(?string $extension = null):?string
  {
    return is_string($this->fileName) ? basename($this->fileName, $extension) : null;
  }

  /**
   * Set dataHash.
   *
   * @param string|null $dataHash
   *
   * @return File
   */
  public function setDataHash($dataHash = null):File
  {
    $this->dataHash = $dataHash;

    return $this;
  }

  /**
   * Get dataHash.
   *
   * @return string|null
   */
  public function getDataHash()
  {
    return $this->dataHash;
  }

  /**
   * Set $size.
   *
   * @param int $fileData
   *
   * @return File
   */
  public function setSize(int $size= -1):File
  {
    $this->size = $size;

    return $this;
  }

  /**
   * Get $size.
   *
   * @return int
   */
  public function getSize():int
  {
    return $this->size;
  }

  /**
   * Set FileData.
   *
   * @param FileData|null $data
   *
   * @return File
   */
  public function setFileData($fileData = null)
  {
    $this->fileData = $fileData;

    return $this;
  }

  /**
   * Get FileData.
   *
   * @return FileData|null
   */
  public function getFileData():?FileData
  {
    return $this->fileData;
  }
}
