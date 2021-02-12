<?php
/* Orchestra member, musician and project management application.
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
use Doctrine\Common\Collections\ArrayCollection;

/**
 * SepaDebitNoteData
 *
 * @ORM\Table(name="SepaDebitNoteData")
 * @ORM\Entity
 */
class SepaDebitNoteData implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\OneToOne(targetEntity="SepaDebitNote", inversedBy="debitNoteData", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $debitNote;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $fileName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $mimeType;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=16777215, nullable=false)
   */
  private $data;

  /**
   * Set debitNote.
   *
   * @param SepaDebitNote $debitNote
   *
   * @return SepaDebitNoteData
   */
  public function setDebitNote($debitNote)
  {
    $this->debitNote = $debitNote;

    return $this;
  }

  /**
   * Get debitNote.
   *
   * @return SepaDebitNote
   */
  public function getDebitNote()
  {
    return $this->debitNote;
  }

  /**
   * Set fileName.
   *
   * @param string $fileName
   *
   * @return SepaDebitNoteData
   */
  public function setFileName($fileName)
  {
    $this->fileName = $fileName;

    return $this;
  }

  /**
   * Get fileName.
   *
   * @return string
   */
  public function getFileName()
  {
    return $this->fileName;
  }

  /**
   * Set mimeType.
   *
   * @param string $mimeType
   *
   * @return SepaDebitNoteData
   */
  public function setMimeType($mimeType)
  {
    $this->mimeType = $mimeType;

    return $this;
  }

  /**
   * Get mimeType.
   *
   * @return string
   */
  public function getMimeType()
  {
    return $this->mimeType;
  }

  /**
   * Set data.
   *
   * @param string $data
   *
   * @return SepaDebitNoteData
   */
  public function setData($data)
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return string
   */
  public function getData()
  {
    return $this->data;
  }
}
