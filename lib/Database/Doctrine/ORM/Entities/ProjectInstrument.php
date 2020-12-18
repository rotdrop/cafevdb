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
 * @ORM\Table(name="ProjectInstruments", uniqueConstraints={@ORM\UniqueConstraint(columns={"project_id", "musician_id", "instrument_id"}), @ORM\UniqueConstraint(columns={"instrumentation_id", "instrument_id"})})
 * @ORM\Entity
 */
class ProjectInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Projects"})
   */
  private $projectId;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Musicians"})
   */
  private $musicianId;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table ProjectParticipants"})
   */
  private $instrumentationId;

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
   * @ORM\JoinColumn(name="instrumentationId", referencedColumnName="id")
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
   * Set instrumentationId.
   *
   * @param int $instrumentationId
   *
   * @return ProjectInstruments
   */
  public function setInstrumentationId($instrumentationId)
  {
    $this->instrumentationId = $instrumentationId;

    return $this;
  }

  /**
   * Get instrumentationId.
   *
   * @return int
   */
  public function getInstrumentationId()
  {
    return $this->instrumentationId;
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
