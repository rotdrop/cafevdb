<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * MusicianInstruments
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and musicians) but for the "ranking" column which codes
 * a loose ranking like "primary instrument", i.e. the preference of
 * instruments of the given musician.
 *
 * @ORM\Table(name="MusicianInstruments", options={"comment":"Join-table Musicians -> Instruments"})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 */
class MusicianInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @var Musician
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var Instrument
   * @ORM\ManyToOne(targetEntity="Instrument", inversedBy="musicianInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $instrument;

  /**
   * @var Collection
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="musicianInstrument")
   */
  private $projectInstruments;

  /**
   * @var int
   * @ORM\Column(type="integer", nullable=false, options={"default"="1","comment"="Ranking of the instrument w.r.t. to the given musician (lower is better)"})
   */
  private $ranking = 1;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->projectInstruments = new ArrayCollection();
  }

  /**
   * Set musician.
   *
   * @param null|int|Musician $musician
   *
   * @return MusicianInstrument
   */
  public function setMusician(mixed $musician):MusicianInstrument
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return null|Musician
   */
  public function getMusician():?Musician
  {
    return $this->musician;
  }

  /**
   * Set instrument.
   *
   * @param null|Instrument $instrument
   *
   * @return MusicianInstrument
   */
  public function setInstrument($instrument):MusicianInstrument
  {
    $this->instrument = $instrument;

    return $this;
  }

  /**
   * Get instrument.
   *
   * @return null|Instrument
   */
  public function getInstrument():?Instrument
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
  public function setRanking(int $ranking):MusicianInstrument
  {
    $this->ranking = $ranking;

    return $this;
  }

  /**
   * Get ranking.
   *
   * @return int
   */
  public function getRanking():int
  {
    return $this->ranking;
  }

  /**
   * Set projectInstruments.
   *
   * @param Collection $projectInstruments
   *
   * @return Instrumente
   */
  public function setProjectInstruments(Collection $projectInstruments):MusicianInstrument
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @return Collection
   */
  public function getProjectInstruments():Collection
  {
    return $this->projectInstruments;
  }

  /**
   * Return the number of project instrumentation slots the associated
   * musician is registered with.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->projectInstruments->count();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    $name = (string)$this->instrument;
    if (!empty($this->musician)) {
      $name .= '@' . $this->musician->getUserIdSlug();
    }
    return $name;
  }
}
