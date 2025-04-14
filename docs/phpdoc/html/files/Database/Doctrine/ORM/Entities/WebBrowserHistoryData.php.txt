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
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping as MediaMonks;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

/**
 * Generic directory entry for a database-backed file.
 */
#[ORM\Table(name: 'WebBrowserHistoryData')]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WebBrowserHistoryData implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  /**
   * @var int
   */
  #[ORM\Column(type: 'string', length: 64, nullable: false, options: ['fixed' => true, 'collation' => 'ascii_general_ci'])]
  #[ORM\Id]
  protected $hash;

  #[ORM\OneToMany(targetEntity: WebBrowserHistoryEntry::class, mappedBy: 'data', cascade: ['persist'], orphanRemoval: true, indexBy: 'key', fetch: 'EXTRA_LAZY')]
  protected Collection $entries;

  #[MediaMonks\Transformable(name: 'encrypt', override: true, context: 'encryptionContext')]
  #[ORM\Column(type: 'blob', nullable: false, options: ['comment' => 'JSON encrypted'])]
  protected $data;

  /**
   * @var array
   *
   * In memory encryption context to support multi user encryption. This is a
   * multi-field encryption context indexed by the property name.
   */
  protected array $encryptionContext = [];

  /** {@inheritdoc} */
  public function __construct(string $hash, array $data)
  {
    $this->setData($data);
    $this->hash = $hash;
    $this->entries = new ArrayCollection;
  }

  /** @return null|string */
  public function getHash():?string
  {
    return $this->hash;
  }

  /**
   * @param null|string $hash
   *
   * @return WebBrowserHistoryData
   */
  public function setHash(?string $hash):WebBrowserHistoryData
  {
    $this->hash = $hash;

    return $this;
  }

  /** @return array */
  public function getData():array
  {
    return json_decode($this->data, true);
  }

  /**
   * @param array $data
   *
   * @return WebBrowserHistoryData
   */
  public function setData(array $data):WebBrowserHistoryData
  {
    $this->data = json_encode($data, JSON_FORCE_OBJECT);

    return $this;
  }

  /** @return Collection */
  public function getEntries():Collection
  {
    return $this->entries;
  }

  /**
   * @param Collection $entries
   *
   * @return WebBrowserHistoryData
   */
  public function setEntries(Collection $entries):WebBrowserHistoryData
  {
    $this->entries = $entries;

    return $this;
  }

  /**
   * @param WebBrowserHistoryEntry $entry
   *
   * @return WebBrowserHistoryData
   */
  public function addToEntry(WebBrowserHistoryEntry $entry):WebBrowserHistoryData
  {
    $this->entries->set($entry->getKey(), $entry);
    $this->addEncryptionIdentity($entry->getState()->getUserId());

    return $this;
  }

  /**
   * @param WebBrowserHistoryEntry $entry
   *
   * @return WebBrowserHistoryData
   */
  public function removeFromEntry(WebBrowserHistoryEntry $entry):WebBrowserHistoryData
  {
    if ($this->entries->containsKey($entry->getKey())) {
      $this->entries->remove($entry->getKey());
    }
    return $this;
  }

  /**
   * Add a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return WebBrowserHistoryData
   */
  public function addEncryptionIdentity(string $personality):WebBrowserHistoryData
  {
    if (empty($this->encryptionContext)) {
      $this->encryptionContext = [];
    }
    if (!in_array($personality, $this->encryptionContext)) {
      $this->encryptionContext[] = $personality;
    }
    return $this;
  }

  /**
   * Remove a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return WebBrowserHistoryData
   */
  public function removeEncryptionIdentity(string $personality):WebBrowserHistoryData
  {
    $pos = array_search($personality, $this->encryptionContext??[]);
    if ($pos !== false) {
      unset($this->encryptionContext[pos]);
      $this->encryptionContext = array_values($this->encryptionContext);
    }
    return $this;
  }

  /**
   * Ensure that the encryptionContext contains the user-id of the history owner.
   *
   * @return void
   */
  private function sanitizeEncryptionContext()
  {
    /** @var WebBrowserHistoryEntry $entry */
    foreach ($this->entries as $entry) {
      $this->addEncryptionIdentity($entry->getState()->getUserId());
    }
  }

  /**
   * {@inheritdoc}
   */
  #[ORM\PostLoad]
  #[ORM\PrePersist]
  public function handleLifecycleEvent(
    Event\PostLoadEventArgs|Event\PrepersistEventArgs $eventArgs,
  ) {
    $this->sanitizeEncryptionContext();
  }
}
