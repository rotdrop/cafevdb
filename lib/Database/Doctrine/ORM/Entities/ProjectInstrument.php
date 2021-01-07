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
 * @ORM\Entity
 */
class ProjectInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

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
   * @ORM\Column(type="integer", nullable=false, options={"default"="-1","comment"="Voice specification if applicable, set to -1 if separation by voice is not needed"})
   */
  private $voice = -1;

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
   * @ORM\OneToOne(targetEntity="MusicianInstrument", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id"),
   *   @ORM\JoinColumn(name="instrument_id",referencedColumnName="instrument_id")
   * )
   */
  private $musicianInstrument;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set project.
   *
   * @param int $project
   *
   * @return ProjectInstrument
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
   * Set musician.
   *
   * @param int $musician
   *
   * @return ProjectInstrument
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
   * @return ProjectInstrument
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
  public function setSectionLeader($sectionLeader)
  {
    $this->sectionLeader = $sectionLeader;

    return $this;
  }

  /**
   * Get sectionLeader.
   *
   * @return bool
   */
  public function getSectionLeader()
  {
    return $this->sectionLeader;
  }

  /**
   * Set projectParticipant.
   *
   * @param int $projectParticipant
   *
   * @return ProjectInstrument
   */
  public function setProjectParticipant($projectParticipant)
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
   * @return int
   */
  public function getProjectParticipant()
  {
    return $this->projectParticipant;
  }

  /**
   * Set musicianInstrument.
   *
   * @param int $musicianInstrument
   *
   * @return ProjectInstrument
   */
  public function setMusicianInstrument($musicianInstrument)
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

}
