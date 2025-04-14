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
#[ORM\Table(name: 'WebBrowserHistoryEntries')]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WebBrowserHistoryEntry implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  #[ORM\ManyToOne(targetEntity: WebBrowserHistoryState::class, cascade: ['persist'], inversedBy: 'stack')]
  #[ORM\Id]
  protected WebBrowserHistoryState $state;

  /**
   * @var int
   */
  #[ORM\Column(type: 'decimal', precision: 16, scale: 3, nullable: false)]
  #[ORM\GeneratedValue(strategy: 'NONE')]
  #[ORM\Id]
  protected string $key;

  #[ORM\Column(type: 'string', length: 32768, nullable: false, options: ['collation' => 'ascii_bin'])]
  protected string $path;

  #[ORM\JoinColumn(name: 'data_hash', referencedColumnName: 'hash', nullable: false)]
  #[ORM\ManyToOne(targetEntity: WebBrowserHistoryData::class, cascade: ['persist'], inversedBy: 'entries', fetch: 'EXTRA_LAZY')]
  protected WebBrowserHistoryData $data;

  /** {@inheritdoc} */
  public function __construct(
    ?string $key = null,
    ?WebBrowserHistoryState $state = null,
    ?string $path = null,
    ?WebBrowserHistoryData $data = null,
  ) {
    $key && $this->key = $key;
    $state && $this->state = $state;
    $path && $this->path = $path;
    $data && $this->setData($data);
  }

  /** @return WebBrowserHistoryState */
  public function getState():WebBrowserHistoryState
  {
    return $this->state;
  }

  /**
   * @param WebBrowserHistoryState $state
   *
   * @return WebBrowserHistoryEntry
   */
  public function setState(WebBrowserHistoryState $state):WebBrowserHistoryEntry
  {
    $this->state = $state;

    return $this;
  }

  /** @return string */
  public function getKey():string
  {
    return $this->key;
  }

  /**
   * @param string $key
   *
   * @return WebBrowserHistoryEntry
   */
  public function setKey(string $key):WebBrowserHistoryEntry
  {
    $this->key = $key;

    return $this;
  }

  /** @return string */
  public function getPath():string
  {
    return $this->path;
  }

  /**
   * @param string $path
   *
   * @return WebBrowserHistoryEntry
   */
  public function setPath(string $path):WebBrowserHistoryEntry
  {
    $this->path = $path;

    return $this;
  }

  /** @return WebBrowserHistoryData */
  public function getData():WebBrowserHistoryData
  {
    return $this->data;
  }

  /**
   * @param WebBrowserHistoryData $data
   *
   * @return WebBrowserHistoryEntry
   */
  public function setData(WebBrowserHistoryData $data):WebBrowserHistoryEntry
  {
    $this->data = $data;
    $data->addToEntry($this);

    return $this;
  }

  /** @return string */
  public function getDataHash():string
  {
    return $this->data->getHash();
  }

  /**
   * {@inheritdoc}
   */
  #[ORM\PreRemove]
  public function preRemove(Event\PreRemoveEventArgs $event)
  {
    // nope, need not be, would have to traverse the list
    // $this->data->removeEncryptionIdentity($this->state->getUserId());
    $this->data->removeFromEntry($this);
  }
}
