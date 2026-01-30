<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2022-2026 Claus-Justus Heine
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
 */
#[ORM\Table(name: 'ProjectInstruments')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\ProjectInstrumentEntityListener::class])]
class ProjectInstrument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  public const UNVOICED = 0;
  public const NOT_AN_INSTRUMENT_FAMILY = 'not an instrument';
  public const NON_INSTRUMENT_ASSOCIATE = 'associate';
  public const NON_INSTRUMENT_BUSINESS_PARTNER = 'business partner';
  public const NON_INSTRUMENTS = [
    self::NON_INSTRUMENT_ASSOCIATE,
    self::NON_INSTRUMENT_BUSINESS_PARTNER,
  ];

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'participantInstruments', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Project $project;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'projectInstruments', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  #[ORM\ManyToOne(targetEntity: Instrument::class, inversedBy: 'projectInstruments', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Instrument $instrument;

  #[ORM\Column(type: 'integer', nullable: false, options: ['default' => '0', 'comment' => 'Voice specification if applicable, set to 0 if separation by voice is not needed'])]
  #[ORM\Id]
  private int $voice = self::UNVOICED;

  #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => '0'])]
  private bool $sectionLeader       = false;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', onDelete: 'cascade')]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', onDelete: 'cascade')]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, inversedBy: 'projectInstruments', fetch: 'EXTRA_LAZY')]
  private ProjectParticipant $projectParticipant;

  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id')]
  #[ORM\JoinColumn(name: 'instrument_id', referencedColumnName: 'instrument_id')]
  #[ORM\ManyToOne(targetEntity: MusicianInstrument::class, inversedBy: 'projectInstruments', fetch: 'EXTRA_LAZY')]
  private MusicianInstrument $musicianInstrument;

  #[ORM\ManyToOne(targetEntity: ProjectInstrumentationNumber::class, inversedBy: 'projectInstruments', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id')]
  #[ORM\JoinColumn(name: 'instrument_id', referencedColumnName: 'instrument_id')]
  #[ORM\JoinColumn(name: 'voice', referencedColumnName: 'voice')]
  private ProjectInstrumentationNumber $instrumentationNumber;

  /** {@inheritdoc} */
  public function __construct(
    ?ProjectParticipant $projectParticipant = null,
    ?MusicianInstrument $musicianInstrument = null,
    int $voice = self::UNVOICED,
  ) {
    $this->arrayCTOR();
    if ($projectParticipant !== null) {
      $this->setProjectParticipant($projectParticipant);
    }
    if ($musicianInstrument !== null) {
      $this->setMusicianInstrument($musicianInstrument);
    }
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
  public function getProject():Project
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
  public function setInstrument(int|Instrument $instrument):ProjectInstrument
  {
    $this->instrument = $instrument;
    return $this;
  }

  /**
   * Get instrument.
   *
   * @return Instrument
   */
  public function getInstrument():Instrument
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
  public function getVoice():int
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
   * @param ProjectInstrumentationNumber $instrumentationNumber
   *
   * @return ProjectInstrument
   */
  public function setInstrumentationNumber(ProjectInstrumentationNumber $instrumentationNumber):ProjectInstrument
  {
    $this->instrumentationNumber = $instrumentationNumber;

    return $this;
  }

  /**
   * Get instrumentationNumber.
   *
   * @return null|ProjectInstrumentationNumber
   */
  public function getInstrumentationNumber():?ProjectInstrumentationNumber
  {
    return $this->instrumentationNumber ?? null;
  }

  /**
   * Set the principal parent entity.
   *
   * @param null|ProjectParticipant $projectParticipant
   *
   * @return ProjectInstrument
   */
  public function setProjectParticipant(ProjectParticipant $projectParticipant):ProjectInstrument
  {
    $this->projectParticipant = $projectParticipant;

    $this->project = $this->projectParticipant->getProject();
    $this->musician = $this->projectParticipant->getMusician();
    if (!$projectParticipant->getProjectInstruments()->contains($this)) {
      $projectParticipant->getProjectInstruments()->add($this);
    }
    if (!$this->musician->getProjectInstruments()->contains($this)) {
      $this->musician->getProjectInstruments()->add($this);
    }
    if (!$this->project->getParticipantInstruments()->contains($this)) {
      $this->project->getParticipantInstruments()->add($this);
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
    return $this->projectParticipant ?? null;
  }

  /**
   * Set musicianInstrument.
   *
   * @param MusicianInstrument $musicianInstrument
   *
   * @return ProjectInstrument
   */
  public function setMusicianInstrument(MusicianInstrument $musicianInstrument):ProjectInstrument
  {
    $this->musicianInstrument = $musicianInstrument;

    $this->instrument = $this->musicianInstrument->getInstrument();
    $this->musician = $this->musicianInstrument->getMusician();
    if (!$this->instrument->getProjectInstruments()->contains($this)) {
      $this->instrument->getProjectInstruments()->add($this);
    }
    if (!$this->musician->getProjectInstruments()->contains($this)) {
      $this->musician->getProjectInstruments()->add($this);
    }
    if (!$musicianInstrument->getProjectInstruments()->contains($this)) {
      $musicianInstrument->getProjectInstruments()->add($this);
    }

    return $this;
  }

  /**
   * Get musicianInstrument.
   *
   * @return null|MusicianInstrument
   */
  public function getMusicianInstrument():?MusicianInstrument
  {
    return $this->musicianInstrument ?? null;
  }

  /**
   * Convenience forward to MusicianInstrument::getName().
   *
   * @return string
   */
  public function getName():string
  {
    return $this->musicianInstrument->getName();
  }

  /**
   * Check whether this is not a real instrument, but belongs to
   * ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY.
   *
   * @return bool
   */
  public function isNotAnInstrument():bool
  {
    return $this->instrument->isNotAnInstrument();
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

  /** @return void */
  protected static function translationHack():void
  {
    self::t(self::NON_INSTRUMENT_ASSOCIATE);
    self::t(self::NON_INSTRUMENT_BUSINESS_PARTNER);
  }
}
