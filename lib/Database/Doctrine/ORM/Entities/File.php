<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Exceptions\DatabaseException;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

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
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
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
  protected $id;

  /**
   * @var int
   *
   * The number of links pointing to this file.
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=0,"unsigned"=true})
   */
  protected $numberOfLinks = 0;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  protected $fileName;

  /**
   * @var string|null
   *
   * Optional original file-name, e.g. if this file generated from an upload
   * or was copied from a cloud folder.
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  protected $originalFileName;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  protected $mimeType;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=-1})
   */
  protected $size = -1;

  /**
   * @var FileData
   *
   * As ORM still does not support lazy one-to-one associations from the
   * inverse side we use a OneToMany - ManyToOne trick which inserts a lazy
   * association in between.
   *
   * @ORM\OneToMany(targetEntity="FileData", mappedBy="file", cascade={"all"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  protected $fileData;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"fixed"=true})
   */
  protected $dataHash;

  /**
   * @var \DateTimeImmutable
   * @Gedmo\Timestampable(on={"update","change"}, field="fileData")
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  protected $updated;

  /** {@inheritdoc} */
  public function __construct($fileName = null, $data = null, $mimeType = null)
  {
    $this->arrayCTOR();
    $this->setFileName($fileName);
    $this->setMimeType($mimeType);
    $data = $data ?? '';
    $fileData = new FileData;
    $fileData
      ->setFile($this)
      ->setData($data);
    $this
      ->setFileData($fileData)
      ->setSize(strlen($data));
  }

  /**
   * Get id.
   *
   * @return integer
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set numberOfLinks.
   *
   * @param int $numberOfLinks
   *
   * @return File
   *
   * @throws DatabaseException
   */
  public function setNumberOfLinks(int $numberOfLinks):File
  {
    if ($numberOfLinks < 0) {
      throw new DatabaseException(
        'Number of links ' . $numberOfLinks
          . ' has to be non negative. '
          . 'File ' . $this->fileName . '@' . $this->id);
    }

    $this->numberOfLinks = $numberOfLinks;

    return $this;
  }

  /**
   * Get numberOfLinks.
   *
   * @return int
   */
  public function getNumberOfLinks():int
  {
    return $this->numberOfLinks;
  }

  /**
   * Increment the link-count.
   *
   * @return File
   */
  public function link():File
  {
    ++$this->numberOfLinks;

    return $this;
  }

  /**
   * Decrement the link-count
   *
   * @return File
   *
   * @throws DatabaseException
   */
  public function unlink():File
  {
    if ($this->numberOfLinks <= 0) {
      throw new DatabaseException(
        'Number of links ' . $this->numberOfLinks . ' is already zero or less '
          . ' but has to be non-negative. '
          . 'File ' . $this->fileName . '@' . $this->id);
    }
    --$this->numberOfLinks;

    return $this;
  }

  /**
   * Set mimeType.
   *
   * @param string|null $mimeType
   *
   * @return File
   */
  public function setMimeType(?string $mimeType = null):File
  {
    $this->mimeType = $mimeType;

    return $this;
  }

  /**
   * Get mimeType.
   *
   * @return string|null
   */
  public function getMimeType():?string
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
   *
   * @param string $dirName
   *
   * @return File
   */
  public function setDirName(string $dirName):File
  {
    $this->fileName = trim($dirName, self::PATH_SEPARATOR) . self::PATH_SEPARATOR . ($this->getBaseName()??'');
    return $this;
  }

  /**
   * Get the dir-part of the file-name
   *
   * @return null|string
   */
  public function getDirName():?string
  {
    return is_string($this->fileName) ? dirname($this->fileName) : null;
  }

  /**
   * Set only the base-name.
   *
   * @param string $baseName
   *
   * @return File
   */
  public function setBaseName(string $baseName):File
  {
    $pathInfo = pathinfo($this->fileName);

    $this->fileName = trim(
      $pathInfo['dirname'] .  self::PATH_SEPARATOR
      . trim($baseName, self::PATH_SEPARATOR),
      self::PATH_SEPARATOR
    );

    return $this;
  }

  /**
   * Get the dir-part of the file-name.
   *
   * @param null|string $extension
   *
   * @return null|string
   */
  public function getBaseName(?string $extension = null):?string
  {
    return is_string($this->fileName) ? basename($this->fileName, $extension) : null;
  }

  /**
   * Set only the extension.
   *
   * @param string $extension
   *
   * @return File
   */
  public function setExtension(string $extension):File
  {
    $pathInfo = pathinfo($this->fileName);
    $this->fileName = $pathInfo['dirname'] . self::PATH_SEPARATOR . $pathInfo['filename'] . '.' . $extension;

    return $this;
  }

  /**
   * Get the extension-part of the file-name.
   *
   * @param null|string $extension
   *
   * @return null|string
   */
  public function getExtension(?string $extension = null):?string
  {
    return is_string($this->fileName) ? pathinfo($this->fileName, PATHINFO_EXTENSION) : null;
  }

  /**
   * Set originalFileName.
   *
   * @param string|null $originalFileName
   *
   * @return File
   */
  public function setOriginalFileName(?string $originalFileName = null):File
  {
    $this->originalFileName = $originalFileName;

    return $this;
  }

  /**
   * Get originalFileName.
   *
   * @return string|null
   */
  public function getOriginalFileName():?string
  {
    return $this->originalFileName;
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
   * @param int $size
   *
   * @return File
   */
  public function setSize(int $size = -1):File
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
   * @param FileData $fileData
   *
   * @return File
   */
  public function setFileData(FileData $fileData):File
  {
    $this->fileData = new ArrayCollection([ $fileData ]);
    $fileData->setFile($this);
    return $this;
  }

  /**
   * Get FileData.
   *
   * @return FileData
   */
  public function getFileData():FileData
  {
    return $this->fileData->first();
  }
}
