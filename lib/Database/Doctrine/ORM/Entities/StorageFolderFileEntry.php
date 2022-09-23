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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Generic directory entry for a database-backed file.
 *
 * @ORM\Table(name="StorageFolderFileEntries")
 * @ORM\Entity
 */
class StorageFolderFileEntry
{
  /**
   * @var string
   *
   * @ORM\Column(type="string", length=64)
   * @ORM\Id
   */
  private $storageId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2048)
   * @ORM\Id
   */
  private $dirName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256)
   * @ORM\Id
   */
  private $baseName;

  /**
   * @var EncryptedFile
   *
   * @ORM\ManyToOne(targetEntity="EncryptedFile")
   */
  private $file;
}
