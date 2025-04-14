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
use DateTimeInterface;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

/**
 * Generic directory entry for a database-backed file.
 */
#[ORM\Table(name: 'WebBrowserHistoryStates')]
#[ORM\UniqueConstraint(columns: ['user_id', 'created'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WebBrowserHistoryState implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;
  use CAFEVDB\Traits\CreatedAt;

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

  #[ORM\Column(type: 'string', length: 256, nullable: false)]
  protected string $userId;

  #[ORM\Column(type: 'datetime_immutable', nullable: false)]
  protected ?DateTimeInterface $created;

  #[ORM\OneToMany(targetEntity: WebBrowserHistoryEntry::class, mappedBy: 'state', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'key', fetch: 'EXTRA_LAZY')]
  #[ORM\OrderBy(['key' => 'ASC'])]
  protected Collection $stack;

  #[ORM\JoinColumn(name: 'pos_state_id', referencedColumnName: 'state_id', nullable: true)]
  #[ORM\JoinColumn(name: 'pos_key', referencedColumnName: 'key', nullable: true)]
  #[ORM\OneToOne(targetEntity: WebBrowserHistoryEntry::class, cascade: ['remove'], fetch: 'EXTRA_LAZY')]
  protected ?WebBrowserHistoryEntry $pos;

  /** {@inheritdoc} */
  public function __construct(mixed $created, string $userId)
  {
    $this->setCreated($created);
    $this->setUserId($userId);

    $this->stack = new ArrayCollection;
  }

  /** @return null|int */
  public function getId():int
  {
    return $this->id;
  }

  /**
   * @param null|int $id
   *
   * @return WebBrowserHistoryState
   */
  public function setId(int $id):WebBrowserHistoryState
  {
    $this->id = $id;

    return $this;
  }

  /** @return string */
  public function getUserId():string
  {
    return $this->userId;
  }

  /**
   * @param string $userId
   *
   * @return WebBrowserHistoryState
   */
  public function setUserId(string $userId):WebBrowserHistoryState
  {
    $this->userId = $userId;

    return $this;
  }

  /** @return Collection */
  public function getStack():Collection
  {
    return $this->stack;
  }

  /**
   * @param Collection $stack
   *
   * @return WebBrowserHistoryState
   */
  public function setStack(Collection $stack):WebBrowserHistoryState
  {
    $this->stack = $stack;

    return $this;
  }

  /** @return WebBrowserHistoryEntry */
  public function getPos():?WebBrowserHistoryEntry
  {
    return $this->pos;
  }

  /**
   * @param null|WebBrowserHistoryEntry $pos
   *
   * @return WebBrowserHistoryState
   */
  public function setPos(?WebBrowserHistoryEntry $pos):WebBrowserHistoryState
  {
    $this->pos = $pos;

    return $this;
  }

  /**
   * @param WebBrowserHistoryEntry $entry
   *
   * @return WebBrowserHistoryState
   */
  public function addEntry(WebBrowserHistoryEntry $entry):WebBrowserHistoryState
  {
    $entry->setState($this);
    $this->stack->set($entry->getKey(), $entry);

    return $this;
  }

  /**
   * @param ?string $key
   *
   * @return null|WebBrowserHistoryEntry
   */
  public function getEntry(?string $key): ?WebBrowserHistoryEntry
  {
    return ($key !== null && $this->stack->containsKey($key)) ? $this->stack->get($key) : null;
  }

  /**
   * {@inheritdoc}
   */
  #[ORM\PreRemove]
  public function preRemove(Event\PreRemoveEventArgs $event)
  {
    // $this->setPos(null);
  }
}
