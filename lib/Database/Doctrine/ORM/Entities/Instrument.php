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
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $instrument;

  /**
   * @var int
   *
   * @ORM\Column(type="smallint", nullable=false, options={"comment"="Orchestral Ordering"})
   */
  private $sortOrder;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
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
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="instrument")
   */
  private $musicianInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="instrument")
   */
  private $projectInstruments;

  public function __construct() {
    $this->arrayCTOR();
    $this->musicianInstruments = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->families = new ArrayCollection();
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set instrument.
   *
   * @param string $instrument
   *
   * @return Instrumente
   */
  public function setInstrument($instrument)
  {
    $this->instrument = $instrument;

    return $this;
  }

  /**
   * Get instrument.
   *
   * @return string
   */
  public function getInstrument()
  {
    return $this->instrument;
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

  public function usage()
  {
    return $this->musicians->count()
      + $this->projects->count()
      /*+ $this->families->count()*/;
  }
}
