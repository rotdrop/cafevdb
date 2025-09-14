<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * Store a project application submitted via the project application form of
 * the cafevdbmembers app.
 */
#[ORM\Table(name: 'ProjectApplications')]
#[ORM\Entity]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class ProjectApplication implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var Project
   *
   * The related project.
   */
  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'applications', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Project $project;

  /**
   * @var Musician
   *
   * The related musician (i.e. persons). The applicants are inserted in to
   * the database as Musician entity when they submit their application.
   */
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'projectApplications', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  /**
   * @var array
   *
   * The JSON data submitted by the applicant.
   */
  #[ORM\Column(type: 'json', nullable: false, options: ['default' => '{}'])]
  private array $data;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(Project $project, Musician $musician, array $data = [])
  {
    $this->arrayCTOR();
    $this->project = $project;
    $this->musician = $musician;
    $this->data = $data;
  }
  // phpcs:enable

  /**
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return ProjectApplication
   */
  public function setMusician(Musician $musician):ProjectApplication
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->musician;
  }

  /**
   * Set project.
   *
   * @param Project $project
   *
   * @return ProjectApplication
   */
  public function setProject(Project $project):ProjectApplication
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject():Project
  {
    return $this->project;
  }

  /**
   * Set data.
   *
   * @param array $data
   *
   * @return ProjectApplication
   */
  public function setData(array $data):ProjectApplication
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return array
   */
  public function getData():array
  {
    return $this->data;
  }
}
