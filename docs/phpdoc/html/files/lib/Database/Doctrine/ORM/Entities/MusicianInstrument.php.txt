<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * MusicianInstruments
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and musicians) but for the "ranking" column which codes
 * a loose ranking like "primary instrument", i.e. the preference of
 * instruments of the given musician.
 */
#[ORM\Table(name: DatabaseTables::MUSICIAN_INSTRUMENTS_TABLE, options: ['comment' => 'Join-table Musicians -> Instruments'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\MusicianInstrumentEntityListener::class])]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class MusicianInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'instruments', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  #[ORM\ManyToOne(targetEntity: Instrument::class, inversedBy: 'musicianInstruments', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Instrument $instrument;

  /** @var Collection<ProjectInstrument> */
  #[ORM\OneToMany(targetEntity: ProjectInstrument::class, mappedBy: 'musicianInstrument')]
  private Collection $projectInstruments;

  #[ORM\Column(type: 'integer', nullable: false, options: ['default' => '1', 'comment' => 'Ranking of the instrument w.r.t. to the given musician (lower is better)'])]
  private int $ranking = 1;

  /** {@inheritdoc} */
  public function __construct(?Musician $musician = null, ?Instrument $instrument = null, ?int $ranking = null)
  {
    $this->arrayCTOR();
    $this->projectInstruments = new ArrayCollection();
    if ($musician !== null) {
      $this->musician = $musician;
    }
    if ($instrument !== null) {
      $this->instrument = $instrument;
    }
    if ($ranking !== null) {
      $this->ranking = $ranking;
    }
  }

  /**
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return MusicianInstrument
   */
  public function setMusician(Musician $musician): MusicianInstrument
  {
    $this->musician = $musician;
    $this->updateInverseSides();
    return $this;
  }

  /**
   * Get musician.
   *
   * @return null|Musician
   */
  public function getMusician(): ?Musician
  {
    return $this->musician ?? null;
  }

  /**
   * Set instrument.
   *
   * @param null|Instrument $instrument
   *
   * @return MusicianInstrument
   */
  public function setInstrument(Instrument $instrument): MusicianInstrument
  {
    $this->instrument = $instrument;
    $this->updateInverseSides();
    return $this;
  }

  /**
   * Get instrument.
   *
   * @return null|Instrument
   */
  public function getInstrument(): ?Instrument
  {
    return $this->instrument ?? null;
  }

  /**
   * Convenience forward to Instrument::getName().
   *
   * @return string
   */
  public function getName(): string
  {
    return $this->instrument->getName();
  }

  /**
   * Set ranking.
   *
   * @param int $ranking
   *
   * @return MusicianInstrument
   */
  public function setRanking(int $ranking): MusicianInstrument
  {
    $this->ranking = $ranking;

    return $this;
  }

  /**
   * Get ranking.
   *
   * @return int
   */
  public function getRanking(): int
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
  public function setProjectInstruments(Collection $projectInstruments): MusicianInstrument
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @return Collection
   */
  public function getProjectInstruments(): Collection
  {
    return $this->projectInstruments;
  }

  /**
   * Check whether this is not a real instrument, but belongs to
   * ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY.
   *
   * @return bool
   */
  public function isNotAnInstrument(): bool
  {
    return $this->instrument->isNotAnInstrument();
  }

  /**
   * Return the number of project instrumentation slots the associated
   * musician is registered with.
   *
   * @return int
   */
  public function usage(): int
  {
    return $this->projectInstruments->count();
  }

  /** {@inheritdoc} */
  public function __toString(): string
  {
    $name = (string)$this->instrument;
    if (!empty($this->musician)) {
      $name .= '@' . $this->musician->getUserIdSlug();
    }
    return $name;
  }

  /**
   * Update indexed associations.
   *
   * @return void
   */
  private function updateInverseSides(): void
  {
    $instrument = $this->getInstrument();
    $musician = $this->getMusician();
    if ($musician) {
      $indexByValue = $instrument?->getId();
      $musicianInstruments = $musician->getInstruments();
      if (!$musicianInstruments->contains($this)) {
        if ($indexByValue) {
          $musicianInstruments->set($indexByValue, $this);
        } else {
          $musicianInstruments->add($this);
        }
      } elseif ($indexByValue && !$musicianInstruments->get($indexByValue)) {
        $musicianInstruments->removeElement($this);
        $musicianInstruments->set($indexByValue, $this);
      }
    }
    if ($instrument) {
      $indexByValue = $musician?->getId();
      $musicianInstruments = $instrument->getMusicianInstruments();
      if (!$musicianInstruments->contains($this)) {
        if ($indexByValue) {
          $musicianInstruments->set($indexByValue, $this);
        } else {
          $musicianInstruments->add($this);
        }
      } elseif ($indexByValue && !$musicianInstruments->get($indexByValue)) {
        $musicianInstruments->removeElement($this);
        $musicianInstruments->set($indexByValue, $this);
      }
    }
  }
}
