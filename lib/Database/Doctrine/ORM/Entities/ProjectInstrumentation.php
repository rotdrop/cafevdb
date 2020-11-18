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
 * ProjectInstrumentation
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and projects) but for the "Quantity" column which
 * states how many instruments are needed.
 *
 * @todo "Quantity" should probably be augmented by "voice" for
 * multi-voice instruments. Although this almost only affects violins
 * ...
 *
 * @ORM\Table(name="ProjectInstrumentation", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "InstrumentId"})})
 * @ORM\Entity
 */
class ProjectInstrumentation
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="Id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="ProjectId", type="integer", nullable=false, options={"comment"="Link into table Projekte"})
   */
  private $projectid;

  /**
   * @var int
   *
   * @ORM\Column(name="InstrumentId", type="integer", nullable=false, options={"comment"="Link into table Instrumente"})
   */
  private $instrumentid;

  /**
   * @var int
   *
   * @ORM\Column(name="Quantity", type="integer", nullable=false, options={"default"="1","comment"="Number of required musicians for this instrument"})
   */
  private $quantity = '1';

  /**
   * Many-One as each project may have multiple instruments, but each
   * instrument-id given here points to exactly one project
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="instrumentation", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="ProjectId", referencedColumnName="Id")
   */
  private $project;

  /**
   * Many-One unidirectional. So the non-owning side just does not matter.
   *
   * @ORM\ManyToOne(targetEntity="Instrument", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="InstrumentId", referencedColumnName="Id")
   */
  private $instrument;

  public function __construct() {
    $this->arrayCTOR();
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
   * Set projectid.
   *
   * @param int $projectid
   *
   * @return ProjectInstrumentation
   */
  public function setProjectid($projectid)
  {
    $this->projectid = $projectid;

    return $this;
  }

  /**
   * Get projectid.
   *
   * @return int
   */
  public function getProjectid()
  {
    return $this->projectid;
  }

  /**
   * Set instrumentid.
   *
   * @param int $instrumentid
   *
   * @return ProjectInstrumentation
   */
  public function setInstrumentid($instrumentid)
  {
    $this->instrumentid = $instrumentid;

    return $this;
  }

  /**
   * Get instrumentid.
   *
   * @return int
   */
  public function getInstrumentid()
  {
    return $this->instrumentid;
  }

  /**
   * Set quantity.
   *
   * @param int $quantity
   *
   * @return ProjectInstrumentation
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

  /**
   * Set instrument.
   *
   * @param int $instrument
   *
   * @return ProjectInstrumentation
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
   * @return ProjectInstrumentation
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

}
