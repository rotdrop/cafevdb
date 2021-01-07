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

/**
 * MusicianInstrument
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and musicians) but for the "ranking" column which codes
 * a loose ranking like "primary instrument", i.e. the preference of
 * instruments of the given musician.
 *
 * @ORM\Table(name="MusicianInstrument", options={"comment":"Join-table Musicians -> Instruments"})
 * @ORM\Entity
 */
class MusicianInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @ORM\ManyToOne(targetEntity="Instrument", inversedBy="musicianInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $instrument;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="musicianInstrument")
   */
  private $projectInstruments;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="1","comment"="Ranking of the instrument w.r.t. to the given musician (lower is better)"})
   */
  private $ranking = 1;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $disabled = false;

  public function __construct() {
    $this->arrayCTOR();
    $this->projectInstruments = new ArrayCollection();
  }

  /**
   * Set musician.
   *
   * @param int $musician
   *
   * @return MusicianInstrument
   */
  public function setMusician($musician)
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return int
   */
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set instrument.
   *
   * @param int $instrument
   *
   * @return InstrumentInstrument
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
   * Set ranking.
   *
   * @param int $ranking
   *
   * @return MusicianInstrument
   */
  public function setRanking($ranking)
  {
    $this->ranking = $ranking;

    return $this;
  }

  /**
   * Get ranking.
   *
   * @return int
   */
  public function getRanking()
  {
    return $this->ranking;
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

  public function usage()
  {
    return $this->projectInstruments->count();
  }

}
