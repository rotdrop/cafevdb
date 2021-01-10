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

/**
 * ProjectInstrumentationNumber
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and projects) but for the "Quantity" column which
 * states how many instruments are needed.
 *
 * @ORM\Table(name="ProjectInstrumentationNumbers")
 * @ORM\Entity
 */
class ProjectInstrumentationNumber implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="projectInstrumentationNumbers", fetch="EXTRA_LAZY")
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
   * @ORM\Column(type="integer", options={"default"="-1","comment"="Voice specification if applicable, set to -1 if separation by voice is not needed"})
   * @ORM\Id
   */
  private $voice = -1;

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
  }

  /**
   * Set instrument.
   *
   * @param int $instrument
   *
   * @return ProjectInstrumentationNumber
   */
  public function setInstrument($instrument)
  {
    $this->instrument = $instrument;

    return $this;
  }

  /**
   * Get instrument.
   *
   * @return int
   */
  public function getInstrument()
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
  public function getProject()
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
  public function setVoice($voice)
  {
    $this->voice = $voice;

    return $this;
  }

  /**
   * Get voice.
   *
   * @return int
   */
  public function getVoice()
  {
    return $this->voice;
  }

  /**
   * Set voice.
   *
   * @param int $quantity
   *
   * @return ProjectInstrumentationNumber
   */
  public function setQuantity($quantity)
  {
    $this->quantity = $quantity;

    return $this;
  }

  /**
   * Get quantity.
   *
   * @return int
   */
  public function getQuantity()
  {
    return $this->quantity;
  }

}
