<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2022-2024 Claus-Justus Heine
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

/**
 * ProjectInstruments
 *
 * One musician may master more than one instrument. Hence one
 * musician may be employed to play more than one instrument in a
 * specific project. Still the ProjectParticipants table just links
 * real persons to projects. This is where this table plugs in: here
 * we record the instruments (where "looking after other's childs" is
 * also an instrument :) ) which are employed in each project for each
 * musician.
 *
 * Of course: the generic case is that a layman just plays one
 * instrument. Still we need to handle the more fabular cases for fun
 * -- and otherwise they imply ugly kludges and conventions in the frontend usage.
 *
 * @ORM\Table(name="ProjectInstruments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class ProjectInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  const UNVOICED = 0;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="participantInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="projectInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @ORM\ManytoOne(targetEntity="Instrument", inversedBy="projectInstruments", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $instrument;

  /**
   * @var int|null
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="0","comment"="Voice specification if applicable, set to 0 if separation by voice is not needed"})
   * @ORM\Id
   */
  private $voice = self::UNVOICED;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private $sectionLeader = false;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="projectInstruments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", onDelete="cascade"),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", onDelete="cascade")
   * )
   */
  private $projectParticipant;

  /**
   * @ORM\ManyToOne(targetEntity="MusicianInstrument", inversedBy="projectInstruments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id"),
   *   @ORM\JoinColumn(name="instrument_id",referencedColumnName="instrument_id")
   * )
   */
  private $musicianInstrument;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectInstrumentationNumber", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *   @ORM\JoinColumn(name="instrument_id", referencedColumnName="instrument_id"),
   *   @ORM\JoinColumn(name="voice", referencedColumnName="voice")
   * )
   */
  private $instrumentationNumber;

  /** {@inheritdoc} */
  public function __construct(?Project $project = null, ?Musician $musician = null, ?Instrument $instrument = null, int $voice = self::UNVOICED)
  {
    $this->arrayCTOR();
    $this->project = $project;
    $this->musician = $musician;
    $this->instrument = $instrument;
    $this->voice = $voice;
  }

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return ProjectInstrument
   */
  public function setProject(mixed $project):ProjectInstrument
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
   * Set musician.
   *
   * @param null|int|Musician $musician
   *
   * @return ProjectInstrument
   */
  public function setMusician($musician):ProjectInstrument
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
   * @param null|int|Instrument $instrument
   *
   * @return ProjectInstrument
   */
  public function setInstrument(mixed $instrument):ProjectInstrument
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
   * Set voice.
   *
   * @param int|null $voice
   *
   * @return ProjectInstrument
   */
  public function setVoice($voice = null)
  {
    $this->voice = $voice;

    return $this;
  }

  /**
   * Get voice.
   *
   * @return int|null
   */
  public function getVoice()
  {
    return $this->voice;
  }

  /**
   * Set sectionLeader.
   *
   * @param bool $sectionLeader
   *
   * @return ProjectInstrument
   */
  public function setSectionLeader(bool $sectionLeader):ProjectInstrument
  {
    $this->sectionLeader = $sectionLeader;

    return $this;
  }

  /**
   * Get sectionLeader.
   *
   * @return bool
   */
  public function getSectionLeader():bool
  {
    return $this->sectionLeader;
  }

  /**
   * Set instrumentationNumber.
   *
   * @param null|ProjectInstrumentationNumber $instrumentationNumber
   *
   * @return ProjectInstrument
   */
  public function setInstrumentationNumber(?ProjectInstrumentationNumber $instrumentationNumber):ProjectInstrument
  {
    $this->instrumentationNumber = $instrumentationNumber;

    return $this;
  }

  /**
   * Get instrumentationNumber.
   *
   * @return ProjectInstrumentationNumber
   */
  public function getInstrumentationNumber():ProjectInstrumentationNumber
  {
    return $this->instrumentationNumber;
  }

  /**
   * Set projectParticipant.
   *
   * @param null|ProjectParticipant $projectParticipant
   *
   * @return ProjectInstrument
   */
  public function setProjectParticipant(?ProjectParticipant $projectParticipant):ProjectInstrument
  {
    $this->projectParticipant = $projectParticipant;

    if (!empty($this->projectParticipant)) {
      if (empty($this->project)) {
        $this->project = $this->projectParticipant->getProject();
      }
      if (empty($this->musician)) {
        $this->musician = $this->projectParticipant->getMusician();
      }
    }

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return null|ProjectParticipant
   */
  public function getProjectParticipant():?ProjectParticipant
  {
    return $this->projectParticipant;
  }

  /**
   * Set musicianInstrument.
   *
   * @param null|MusicianInstrument $musicianInstrument
   *
   * @return ProjectInstrument
   */
  public function setMusicianInstrument(?MusicianInstrument $musicianInstrument):ProjectInstrument
  {
    $this->musicianInstrument = $musicianInstrument;

    if (!empty($this->musicianInstrument)) {
      if (empty($this->instrument)) {
        $this->instrument = $this->musicianInstrument->getInstrument();
      }
      if (empty($this->musician)) {
        $this->musician = $this->musicianInstrument->getMusician();
      }
    }

    return $this;
  }

  /**
   * Get musicianInstrument.
   *
   * @return int
   */
  public function getMusicianInstrument()
  {
    return $this->musicianInstrument;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    $name = (string)$this->instrument;
    if (!empty($this->musician)) {
      $name .= ' | ' . $this->musician->getUserIdSlug();
    }
    if (!empty($this->project)) {
      $name .= '@' . $this->project->getName();
    }
    return $name;
  }
}
