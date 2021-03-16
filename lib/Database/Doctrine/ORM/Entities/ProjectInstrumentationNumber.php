<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use Doctrine\Common\Collections\Collection;

/**
 * ProjectInstrumentationNumber
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and projects) but for the "Quantity" column which
 * states how many instruments are needed.
 *
 * @ORM\Table(name="ProjectInstrumentationNumbers")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectInstrumentationNumbersRepository")
 */
class ProjectInstrumentationNumber implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="instrumentationNumbers", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Instrument", inversedBy="projectInstrumentationNumbers", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $instrument;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", options={"default"="0","comment"="Voice specification if applicable, set to 0 if separation by voice is not needed"})
   * @ORM\Id
   */
  private $voice = 0;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="1","comment"="Number of required musicians for this instrument"})
   */
  private $quantity = '1';

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="instrumentationNumber", fetch="EXTRA_LAZY", indexBy="musician")
   */
  private $instruments;

  public function __construct() {
    $this->arrayCTOR();
    $this->instruments = new ArrayCollection();
  }

  /**
   * Set instrument.
   *
   * @param int $instrument
   *
   * @return ProjectInstrumentationNumber
   */
  public function setInstrument($instrument):ProjectInstrumentationNumber
  {
    $this->instrument = $instrument;

    return $this;
  }

  /**
   * Get instrument.
   *
   * @return Instrument
   */
  public function getInstrument():Instrument
  {
    return $this->instrument;
  }

  /**
   * Set project.
   *
   * @param int $project
   *
   * @return ProjectInstrumentationNumber
   */
  public function setProject($project)
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return int
   */
  public function getProject():Project
  {
    return $this->project;
  }

  /**
   * Set voice.
   *
   * @param int $voice
   *
   * @return ProjectInstrumentationNumber
   */
  public function setVoice(int $voice):ProjectInstrumentationNumber
  {
    $this->voice = $voice;

    return $this;
  }

  /**
   * Get voice.
   *
   * @return int
   */
  public function getVoice():int
  {
    return $this->voice;
  }

  /**
   * Set quantity
   *
   * @param int $quantity
   *
   * @return ProjectInstrumentationNumber
   */
  public function setQuantity(int $quantity):ProjectInstrumentationNumber
  {
    $this->quantity = $quantity;

    return $this;
  }

  /**
   * Get quantity.
   *
   * @return int
   */
  public function getQuantity():int
  {
    return $this->quantity;
  }

  /**
   * Set instruments
   *
   * @param int $instruments
   *
   * @return ProjectInstrumentationNumber
   */
  public function setInstruments(Collection $instruments):ProjectInstrumentationNumber
  {
    $this->instruments = $instruments;

    return $this;
  }

  /**
   * Get instruments.
   *
   * @return int
   */
  public function getInstruments():Collection
  {
    return $this->instruments;
  }

}
