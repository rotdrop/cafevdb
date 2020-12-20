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
 * MusicianInstrument
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and musicians) but for the "ranking" column which codes
 * a loose ranking like "primary instrument", i.e. the preference of
 * instruments of the given musician.
 *
 * @ORM\Table(name="MusicianInstrument", options={"comment":"Join-table Musicians -> Instruments"}, uniqueConstraints={@ORM\UniqueConstraint(columns={"musician_id", "instrument_id"})})
 * @ORM\Entity
 */
class MusicianInstrument
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
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="1","comment"="Ranking of the instrument w.r.t. to the given musician (lower is better)"})
   */
  private $ranking = '1';

  // /**
  //  * Core functionality: a musician (i.e. a natural person not
  //  * necessarily a musician in its proper sense) may be employed for
  //  * more than just one instrument (or organizational role) in each
  //  * project.
  //  *
  //  * @ORM\OneToOne(targetEntity="ProjectInstrument", mappedBy="musicianInstrument")
  //  * @ORM\JoinColumns(
  //  *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id"),
  //  *   @ORM\JoinColumn(name="instrument_id",referencedColumnName="instrument_id")
  //  * )
  //  */
  // private $projectInstruments;

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
   * Set musicianId.
   *
   * @param int $musicianId
   *
   * @return MusicianInstrument
   */
  public function setMusicianId($musicianId)
  {
    $this->musicianId = $musicianId;

    return $this;
  }

  /**
   * Get musicianId.
   *
   * @return int
   */
  public function getMusicianId()
  {
    return $this->musicianId;
  }

  /**
   * Set instrumentId.
   *
   * @param int $instrumentId
   *
   * @return MusicianInstrument
   */
  public function setInstrumentId($instrumentId)
  {
    $this->instrumentId = $instrumentId;

    return $this;
  }

  /**
   * Get instrumentId.
   *
   * @return int
   */
  public function getInstrumentId()
  {
    return $this->instrumentId;
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
}
