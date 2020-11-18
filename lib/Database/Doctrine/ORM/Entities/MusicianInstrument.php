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
 * @ORM\Table(name="MusicianInstrument", uniqueConstraints={@ORM\UniqueConstraint(name="MusicianInstrument", columns={"musician_id", "instrument_id"})})
 * @ORM\Entity
 */
class MusicianInstrument
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="musician_id", type="integer", nullable=false, options={"comment"="Link into table Musicians"})
   */
  private $musicianId;

  /**
   * @var int
   *
   * @ORM\Column(name="instrument_id", type="integer", nullable=false, options={"comment"="Link into table Instruments"})
   */
  private $instrumentId;

  /**
   * @var int
   *
   * @ORM\Column(name="ranking", type="integer", nullable=false, options={"default"="1","comment"="Ranking of the instrument w.r.t. to the given musician (lower is better)"})
   */
  private $ranking = '1';

  /**
   * Many-One as each musician may play multiple instruments, but each
   * instrument-id given here points to exactly one musician.
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="musician_id", referencedColumnName="Id")
   */
  private $musician;

  /**
   * Many-One unidirectional. So the non-owning side just does not matter.
   *
   * @ORM\ManyToOne(targetEntity="Instrument", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="instrument_id", referencedColumnName="Id")
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
