<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * File-name entry for a database-backed file.
 *
 * @ORM\Entity
 */
class DatabaseStorageFile extends DatabaseStorageDirEntry
{
  /**
   * @var null|DatabaseStorageDirectory
   *
   * @ORM\ManyToOne(targetEntity="EncryptedFile", inversedBy="databaseStorageDirEntries")
   */
  protected $file;

  /** {@inheritdoc} */
  public function __construct()
  {
    parent::__construct();
  }

  /** @return null|EncryptedFile */
  public function getFile():?EncryptedFile
  {
    return $this->file;
  }

  /**
   * @param null|EncryptedFile $file
   *
   * @return DatabaseStorageDirectory
   */
  public function setFile(?EncryptedFile $file):DatabaseStorageDirectory
  {
    $this->file = $file;

    return $this;
  }
}
