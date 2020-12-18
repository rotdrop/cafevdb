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
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Instruments"})
   */
  private $instrumentId;

  /**
   * @var int|null
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $voice;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private $sectionLeader = '0';

  /**
   * Core functionality: a musician (i.e. a natural person not
   * necessarily a musician in its proper sense) may be employed for
   * more than just one instrument (or organizational role) in each
   * project.
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="projectInstruments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id")
   * )
   */
  private $participant;

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
   * Set projectId.
   *
   * @param int $projectId
   *
   * @return ProjectInstruments
   */
  public function setProjectId($projectId)
  {
    $this->projectId = $projectId;

    return $this;
  }

  /**
   * Get projectId.
   *
   * @return int
   */
  public function getProjectId()
  {
    return $this->projectId;
  }

  /**
   * Set musicianId.
   *
   * @param int $musicianId
   *
   * @return ProjectInstruments
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
   * Set projectParticipantId.
   *
   * @param int $projectParticipantId
   *
   * @return ProjectInstruments
   */
  public function setProjectParticipantId($projectParticipantId)
  {
    $this->projectParticipantId = $projectParticipantId;

    return $this;
  }

  /**
   * Get projectParticipantId.
   *
   * @return int
   */
  public function getProjectParticipantId()
  {
    return $this->projectParticipantId;
  }

  /**
   * Set instrumentId.
   *
   * @param int $instrumentId
   *
   * @return ProjectInstruments
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
   * Set voice.
   *
   * @param int|null $voice
   *
   * @return ProjectInstruments
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
   * @return ProjectInstruments
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
}
