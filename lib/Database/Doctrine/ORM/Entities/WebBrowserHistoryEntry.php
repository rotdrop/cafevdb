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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Constants;

/**
 * Generic directory entry for a database-backed file.
 */
#[ORM\Table(name: 'WebBrowserHistoryEntry')]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
class WebBrowserHistoryEntry implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  #[ORM\ManyToOne(targetEntity: WebBrowserHistoryState::class, cascade: ['persist'], inversedBy: 'chain')]
  #[ORM\Id]
  protected WebBrowserHistoryEntry $state;

  /**
   * @var int
   */
  #[ORM\Column(type: 'string', length: 16, nullable: false, options: ['collation' => 'ascii_general_ci'])]
  #[ORM\GeneratedValue(strategy: 'NONE')]
  #[ORM\Id]
  protected $key;

  #[ORM\JoinColumn(name: 'next_state_id', referencedColumnName: 'state_id', nullable: true)]
  #[ORM\JoinColumn(name: 'next_key', referencedColumnName: 'key', nullable: true)]
  #[ORM\OneToOne(targetEntity: WebBrowserHistoryEntry::class)]
  protected $next;

  #[ORM\JoinColumn(name: 'prev_state_id', referencedColumnName: 'state_id', nullable: true)]
  #[ORM\JoinColumn(name: 'prev_key', referencedColumnName: 'key', nullable: true)]
  #[ORM\OneToOne(targetEntity: WebBrowserHistoryEntry::class)]
  protected $prev;


  #[ORM\JoinColumn(name: 'data_hash', referencedColumnName: 'hash', nullable: false)]
  #[ORM\ManyToOne(targetEntity: WebBrowserHistoryData::class, cascade: ['persist'], inversedBy: 'entries')]
  protected ?WebBrowserHistoryData $data;

  /** @return null|string */
  public function getKey():?string
  {
    return $this->key;
  }

  /**
   * @param null|string $key
   *
   * @return DatabaseStorageDirEntry
   */
  public function setKey(?string $key):WebBrowserHistoryEntry
  {
    $this->key = $key;

    return $this;
  }

  /** @return null|string */
  public function getPrev():?WebBrowserHistoryEntry
  {
    return $this->prev;
  }

  /**
   * @param null|WebBrowserHistoryEntry $prev
   *
   * @return DatabaseStorageDirEntry
   */
  public function setPrev(?WebBrowserHistoryEntry $prev):WebBrowserHistoryEntry
  {
    $this->prev = $prev;

    return $this;
  }

  /** @return null|string */
  public function getNext():?WebBrowserHistoryEntry
  {
    return $this->next;
  }

  /**
   * @param null|WebBrowserHistoryEntry $next
   *
   * @return DatabaseStorageDirEntry
   */
  public function setNext(?WebBrowserHistoryEntry $next):WebBrowserHistoryEntry
  {
    $this->next = $next;

    return $this;
  }

  /** @return null|string */
  public function getData():?WebBrowserHistoryData
  {
    return $this->data;
  }

  /**
   * @param WebBrowserHistoryData $data
   *
   * @return DatabaseStorageDirEntry
   */
  public function setData(WebBrowserHistoryData $data):WebBrowserHistoryEntry
  {
    $this->data = $data;

    return $this;
  }

  /** @return null|string */
  public function getState():?WebBrowserHistoryState
  {
    return $this->state;
  }

  /**
   * @param WebBrowserHistoryState $state
   *
   * @return StatebaseStorageDirEntry
   */
  public function setState(WebBrowserHistoryState $state):WebBrowserHistoryEntry
  {
    $this->state = $state;

    return $this;
  }
}
