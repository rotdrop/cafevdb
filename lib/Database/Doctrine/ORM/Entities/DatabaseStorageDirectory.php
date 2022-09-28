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
 * Generic directory entry for a database-backed file.
 *
 * @ORM\Table(
 *   name="DatabaseStorageDirectories",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"storage_id", "parent_id", "name"})
 *   },
 * )
 * @ORM\Entity
 */
class DatabaseStorageDirectory implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  protected $id;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  protected $storageId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256)
   */
  protected $name;

  /**
   * @var null|DatabaseStorageDirectory
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageDirectory", inversedBy="databaseStorageDirectories")
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  protected $parent;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="DatabaseStorageDirectory", mappedBy="parent")
   */
  protected $databaseStorageDirectories;

  /**
   * @var Collection
   *
   * @ORM\ManyToMany(targetEntity="EncryptedFile", inversedBy="databaseStorageDirectories", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @ORM\JoinTable
   */
  protected $documents;
}
