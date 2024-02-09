<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine
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

/**
 * This is a placeholder which appears as top-level directory if the storage
 * does not hold any
 * entries.
 */
class EmptyRootNode
{
  /**
   * @param string $name
   *
   * @param  null|DateTimeInterface $minimalModificationTime
   *
   * @param string $storageId
   */
  public function __construct(
    protected string $name,
    protected DateTimeInterface $updated,
    protected string $storageId,
  ) {
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
  public function getParent():?DatabaseStorageFolder
  {
    return null;
  }

  /**
   * @return string
   */
  public function getMimeType():string
  {
    return 'httpd/unix-directory';
  }

  /**
   * @return EmptyRootNode
   */
  public function getStorage():EmptyRootNode
  {
    return $this;
  }

  /**
   * @return string
   */
  public function getStorageId():string
  {
    return $this->storageId;
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
  public function setUpdated(DateTimeInterface $updated):EmptyRootNode
  {
    $this->update = $updated;

    return $this;
  }
}
