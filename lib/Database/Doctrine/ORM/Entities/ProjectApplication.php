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
   * The email address used for registration.
   */
  #[ORM\Column(type: 'string', length: 254, nullable: false, options: ['collation' => 'ascii_general_ci'])]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'NONE')]
  private string $email;

  /**
   * In order to revisit their registration data people have to provide a
   * password or -- if they have cloud account -- have to be logged in.
   */
  #[ORM\Column(type: 'string', length: 254, nullable: true, options: ['collation' => 'ascii_general_ci'])]
  private ?string $passwordHash =  null;

  /**
   * @var Musician
   *
   * The related musician (i.e. person). Maybe null. As we do not know how
   * people try to register themselves we need a manual review of the
   * registation data. For the case that applicants first logged into the
   * cloud this field will be set and accurate.
   */
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'projectApplications', fetch: 'EXTRA_LAZY')]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'id', nullable: true)]
  private ?Musician $musician = null;

  /**
   * @var array
   *
   * The JSON data submitted by the applicant.
   */
  #[ORM\Column(type: 'json', nullable: false, options: ['default' => '{}'])]
  private array $data;

  /**
   * @param Project $project Mandatory.
   *
   * @param string $email Mandatory.
   *
   * @param null|Musician $musician Optional already existing person.
   *
   * @param array $data Registration data. Maybe empty in the CTOR, but of course is mandatory.
   */
  public function __construct(Project $project, string $email, ?Musician $musician = null, array $data = [])
  {
    $this->arrayCTOR();
    $this->project = $project;
    $this->email = $email;
    $this->musician = $musician;
    $this->data = $data;
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
   * Set email.
   *
   * @param string $email
   *
   * @return ProjectApplication
   */
  public function setEmail(string $email):ProjectApplication
  {
    $this->email = $email;

    return $this;
  }

  /**
   * Get email.
   *
   * @return string
   */
  public function getEmail():string
  {
    return $this->email;
  }

  /**
   * Set passwordHash.
   *
   * @param null|string $passwordHash
   *
   * @return ProjectApplication
   */
  public function setPasswordHash(?string $passwordHash):ProjectApplication
  {
    $this->passwordHash = $passwordHash;

    return $this;
  }

  /**
   * Get passwordHash.
   *
   * @return null|string
   */
  public function getPasswordHash():?string
  {
    return $this->passwordHash;
  }

  /**
   * Set musician.
   *
   * @param null|Musician $musician
   *
   * @return ProjectApplication
   */
  public function setMusician(?Musician $musician):ProjectApplication
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
