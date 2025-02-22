<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use DateTimeImmutable;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Constants;

/**
 * Generic directory entry for a database-backed file.
 */
#[ORM\Table(name: 'WebBrowserHistoryState')]
#[ORM\UniqueConstraint(columns: ['user_id', 'created'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
class WebBrowserHistoryState implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;

  /**
   * @var int
   *
   * We could use $userId and $created as ids, but that would make the
   * chaining between the entries and the state much more complicated.
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'IDENTITY')]
  protected $id;

  #[ORM\Column(type: 'string', length: 256)]
  protected string $userId;

  #[ORM\Column(type: 'datetime_immutable', nullable: false)]
  protected DateTimeImmutable $created;

  #[ORM\OneToMany(targetEntity: WebBrowserHistoryEntry::class, mappedBy: 'state', cascade: ['persist'], orphanRemoval: true, indexBy: 'key')]
  protected ?Collection $chain;

  #[ORM\JoinColumn(name: 'pos_state_id', referencedColumnName: 'state_id', nullable: false)]
  #[ORM\JoinColumn(name: 'pos_key', referencedColumnName: 'key', nullable: false)]
  #[ORM\OneToOne(targetEntity: WebBrowserHistoryEntry::class)]
  protected ?WebBrowserHistoryEntry $pos;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->chain = new ArrayCollection;
  }

  /** @return null|int */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * @param null|int $id
   *
   * @return DatabaseStorageDirEntry
   */
  public function setId(?int $id):WebBrowserHistoryState
  {
    $this->id = $id;

    return $this;
  }

  /** @return null|string */
  public function getUserId():?string
  {
    return $this->userId;
  }

  /**
   * @param null|string $userId
   *
   * @return DatabaseStorageDirEntry
   */
  public function setUserId(?string $userId):WebBrowserHistoryState
  {
    $this->userId = $userId;

    return $this;
  }
}
