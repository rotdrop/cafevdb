<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

/**
 * ProjectInstrumentationNumber
 *
 * This is almost only a pivot table (i.e. a join table between
 * instruments and projects) but for the "Quantity" column which
 * states how many instruments are needed.
 */
#[ORM\Table(name: DatabaseTables::PROJECT_INSTRUMENTATION_NUMBERS_TABLE)]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectInstrumentationNumbersRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProjectInstrumentationNumber implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'instrumentationNumbers', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Project $project;

  #[ORM\ManyToOne(targetEntity: Instrument::class, inversedBy: 'projectInstrumentationNumbers', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Instrument $instrument;

  #[ORM\Column(type: 'integer', options: ['default' => ProjectInstrument::UNVOICED, 'comment' => 'Voice specification if applicable, set to 0 if separation by voice is not needed'])]
  #[ORM\Id]
  private int $voice = ProjectInstrument::UNVOICED;

  #[ORM\Column(type: 'integer', nullable: false, options: ['default' => 1, 'comment' => 'Number of required musicians for this instrument'])]
  private int $quantity = 1;

  /**
   * @var Collection<ProjectInstrument>
   */
  #[ORM\OneToMany(targetEntity: ProjectInstrument::class, mappedBy: 'instrumentationNumber', fetch: 'EXTRA_LAZY', indexBy: 'musician_id')]
  private Collection $projectInstruments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(?Project $project = null, ?Instrument $instrument = null, int $voice = ProjectInstrument::UNVOICED)
  {
    $this->arrayCTOR();
    $this->projectInstruments = new ArrayCollection();
    if ($project !== null) {
      $this->project = $project;
    }
    if ($instrument !== null) {
      $this->instrument = $instrument;
    }
    $this->voice = $voice;
  }
  // phpcs:enable

  /**
   * Set instrument.
   *
   * @param null|Instrument $instrument
   *
   * @return ProjectInstrumentationNumber
   */
  public function setInstrument($instrument):ProjectInstrumentationNumber
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
   * Set project.
   *
   * @param null|Project $project
   *
   * @return ProjectInstrumentationNumber
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
  public function getProject():Project
  {
    return $this->project;
  }

  /**
   * Set voice.
   *
   * @param int $voice
   *
   * @return ProjectInstrumentationNumber
   */
  public function setVoice(int $voice):ProjectInstrumentationNumber
  {
    $this->voice = $voice;

    return $this;
  }

  /**
   * Get voice.
   *
   * @return int
   */
  public function getVoice():int
  {
    return $this->voice;
  }

  /**
   * Set quantity
   *
   * @param int $quantity
   *
   * @return ProjectInstrumentationNumber
   */
  public function setQuantity(int $quantity):ProjectInstrumentationNumber
  {
    $this->quantity = $quantity;

    return $this;
  }

  /**
   * Get quantity.
   *
   * @return int
   */
  public function getQuantity():int
  {
    return $this->quantity;
  }

  /**
   * Set projectInstruments
   *
   * @param Collection $projectInstruments
   *
   * @return ProjectInstrumentationNumber
   */
  public function setProjectInstruments(Collection $projectInstruments):ProjectInstrumentationNumber
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get instruments.
   *
   * @return int
   */
  public function getProjectInstruments():Collection
  {
    return $this->projectInstruments;
  }
}
