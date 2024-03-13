<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * InstrumentInsurance
 *
 * @ORM\Table(name="MusicianEmailAddresses")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\EntityListeners({"\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener"})
 * @ORM\HasLifecycleCallbacks
 */
class MusicianEmailAddress implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=254, nullable=false, options={"collation"="ascii_general_ci"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $address;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="emailAddresses", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @param null|string $address
   *
   * @param null|Musician $musician
   */
  public function __construct(?string $address = null, Musician $musician = null)
  {
    $this->arrayCTOR();
    $this->setMusician($musician);
    $this->setAddress($address);
  }

  /**
   * @return string
   */
  public function getAddress():string
  {
    return strtolower($this->address);
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->musician->getPublicName(firstNameFirst: true) . ' <' . $this->address . '>';
  }

  /**
   * Set the email address. Address validation is done in a pre-persist
   * listener. Pre-update is not needed as the address is the key and hence
   * cannot be updated ;).
   *
   * @param null|string $address
   *
   * @return MusicianEmailAddress
   */
  public function setAddress(?string $address):MusicianEmailAddress
  {
    $this->address = $address;
    return $this;
  }

  /**
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->musician;
  }

  /**
   * @param int|Musician $musician
   *
   * @return MusicianEmailAddress
   */
  public function setMusician($musician):MusicianEmailAddress
  {
    $this->musician = $musician;
    return $this;
  }

  /**
   * Check whether this is the primary address of $this->musician.
   *
   * @return bool
   */
  public function isPrimaryAddress():bool
  {
    return $this->musician->getEmail() == $this->address;
  }
}
