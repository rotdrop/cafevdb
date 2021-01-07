<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use OCP\ILogger;

/**
 * Instrumente
 *
 * @ORM\Table(name="Instruments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository")
 */
class Instrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var int
   *
   * @ORM\Column(type="smallint", nullable=false, options={"comment"="Orchestral Ordering"})
   */
  private $sortOrder;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $disabled = false;

  /**
   * @ORM\ManyToMany(targetEntity="InstrumentFamily", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   joinColumns={@ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")},
   *   inverseJoinColumns={@ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")}
   * )
   */
  private $families;

  /**
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $musicianInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $projectInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrumentationNumber", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $projectInstrumentationNumbers;

  public function __construct() {
    $this->arrayCTOR();
    $this->families = new ArrayCollection();
    $this->musicianInstruments = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->projectInstrumentationNumbers = new ArrayCollection();
  }

  /**
   * Set id.
   *
   * @param string $id
   *
   * @return Ide
   */
  public function setId($id)
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return Namee
   */
  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set familie.
   *
   * @param array $familie
   *
   * @return Instrumente
   */
  public function setFamilies($families)
  {
    $this->families = $families;

    return $this;
  }

  /**
   * Get familie.
   *
   * @return array
   */
  public function getFamilies()
  {
    return $this->families;
  }

  /**
   * Set sortOrder.
   *
   * @param int $sortOrder
   *
   * @return Instrumente
   */
  public function setSortOrder($sortOrder)
  {
    $this->sortOrder = $sortOrder;

    return $this;
  }

  /**
   * Get sortOrder.
   *
   * @return int
   */
  public function getSortOrder()
  {
    return $this->sortOrder;
  }

  /**
   * Set disabled.
   *
   * @param bool $disabled
   *
   * @return Instrumente
   */
  public function setDisabled($disabled)
  {
    $this->disabled = $disabled;

    return $this;
  }

  /**
   * Get disabled.
   *
   * @return bool
   */
  public function getDisabled()
  {
    return $this->disabled;
  }

  /**
   * Set musicianInstruments.
   *
   * @param bool $musicianInstruments
   *
   * @return Instrumente
   */
  public function setMusicianInstruments($musicianInstruments)
  {
    $this->musicianInstruments = $musicianInstruments;

    return $this;
  }

  /**
   * Get musicianInstruments.
   *
   * @return bool
   */
  public function getMusicianInstruments()
  {
    return $this->musicianInstruments;
  }

  /**
   * Set projectInstruments.
   *
   * @param bool $projectInstruments
   *
   * @return Instrumente
   */
  public function setProjectInstruments($projectInstruments)
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @return bool
   */
  public function getProjectInstruments()
  {
    return $this->projectInstruments;
  }

  /**
   * Set projectInstrumentationNumbers.
   *
   * @param bool $projectInstrumentationNumbers
   *
   * @return Instrumente
   */
  public function setProjectInstrumentationNumbers($projectInstrumentationNumbers)
  {
    $this->projectInstrumentationNumbers = $projectInstrumentationNumbers;

    return $this;
  }

  /**
   * Get projectInstrumentationNumbers.
   *
   * @return bool
   */
  public function getProjectInstrumentationNumbers()
  {
    return $this->projectInstrumentationNumbers;
  }

  public function usage()
  {
    return $this->musicianInstruments->count()
      + $this->projectInstruments->count()
      + $this->projectInstrumentationNumbers->count();
  }
}
