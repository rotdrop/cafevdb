<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage\Database;

use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;

/**
 * Dummy file node which only resides in memory. Its use currently is to provide automatically generated
 * ReadMe files.
 */
class InMemoryFileNode
{
  /**
   * @param DatabaseStorageFolder|EmptyDirectoryNode $parent
   *
   * @param string $name
   *
   * @param string $data
   *
   * @param string $mimeType
   *
   * @param  null|DateTimeInterface $updated
   */
  public function __construct(
    protected DatabaseStorageFolder|EmptyDirectoryNode $parent,
    protected string $name,
    protected string $data,
    protected string $mimeType,
    protected DateTimeInterface $updated,
  ) {
    if ($parent->getUpdated() < $updated)  {
      $parent->setUpdated($updated);
    }
  }

  /**
   * @return string
   */
  public function getName():string
  {
    return $this->name;
  }

  /**
   * @param string $name
   *
   * @return InMemoryFileNode
   */
  public function setName(string $name):InMemoryFileNode
  {
    $this->name = $name;

    return $this;
  }

  /**
   * @return string
   */
  public function getParent():DatabaseStorageFolder|EmptyDirectoryNode
  {
    return $this->parent;
  }

  /**
   * @param DatabaseStorageFolder $parent
   *
   * @return InMemoryFileNode
   */
  public function setParent(DatabaseStorageFolder $parent):InMemoryFileNode
  {
    $this->parent = $parent;

    return $this;
  }

  /**
   * @return InMemoryFileNode
   */
  public function getFileData():InMemoryFileNode
  {
    return $this;
  }

  /**
   * @return string
   */
  public function getData():string
  {
    return $this->data;
  }

  /**
   * @param string $data
   *
   * @return InMemoryFileNode
   */
  public function setData(string $data):InMemoryFileNode
  {
    $this->data = $data;

    return $this;
  }

  /**
   * @return DateTimeInterface
   */
  public function getUpdated():DateTimeInterface
  {
    return $this->updated;
  }

  /**
   * @param DateTimeInterface $updated
   *
   * @return InMemoryFileNode $this.
   */
  public function setUpdated(DateTimeInterface $updated):InMemoryFileNode
  {
    $this->update = $updated;

    return $this;
  }

  /**
   * @return InMemoryFileNode
   */
  public function setSize():InMemoryFileNode
  {
    // nothing, we always use strlen
    return $this;
  }

  /**
   * @return int
   */
  public function getSize():int
  {
    return  strlen($this->data);
  }

  /**
   * @param string $mimeType
   *
   * @return InMemoryFileNode
   */
  public function setMimeType(string $mimeType):InMemoryFileNode
  {
    $this->mimeType = $mimeType;

    return $this;
  }

  /**
   * @return string
   */
  public function getMimeType():string
  {
    return $this->mimeType;
  }
}
