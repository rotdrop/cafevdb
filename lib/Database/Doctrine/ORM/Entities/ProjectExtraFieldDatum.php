<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectExtraFieldsData
 *
 * @ORM\Table(name="ProjectExtraFieldsData")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectExtraFieldDataRepository")
 */
class ProjectExtraFieldDatum implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectExtraField", inversedBy="fieldData", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $field;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="extraFieldsData", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="projectExtraFieldsData", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var \Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(type="uuid_binary")
   * @ORM\Id
   */
  private $optionKey;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=16777215, nullable=true, options={"default"=null})
   */
  private $optionValue = null;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="extraFieldsData", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id")
   * )
   */
  private $projectParticipant;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set project.
   *
   * @param Project $project
   *
   * @return ProjectExtraProjectsData
   */
  public function setProject($project):ProjectExtraFieldDatum
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return ProjectExtraFieldDatum
   */
  public function setMusician($musician):ProjectExtraFieldDatum
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set field.
   *
   * @param int $field
   *
   * @return ProjectExtraFieldDatum
   */
  public function setField($field):ProjectExtraFieldDatum
  {
    $this->field = $field;

    return $this;
  }

  /**
   * Get field.
   *
   * @return int
   */
  public function getField()
  {
    return $this->field;
  }

  /**
   * Set optionValue.
   *
   * @param string $optionValue
   *
   * @return ProjectExtraFieldDatum
   */
  public function setOptionValue($optionValue):ProjectExtraFieldDatum
  {
    $this->optionValue = $optionValue;

    return $this;
  }

  /**
   * Get optionValue.
   *
   * @return string
   */
  public function getOptionValue()
  {
    return $this->optionValue;
  }

  /**
   * Set optionKey.
   *
   * @param string|UuidInterface $optionKey
   *
   * @return ProjectExtraFieldDatum
   */
  public function setOptionKey($optionKey):ProjectExtraFieldDatum
  {
    if (is_string($optionKey)) {
      if (strlen($optionKey) == 36) {
        $optionKey = Uuid::fromString($optionKey);
      } else if (strlen($optionKey) == 16) {
        $optionKey = OptionKey::fromBytes($optionKey);
      } else {
        throw new \Exception("OPTIONKEY DATA: ".$optionKey);
      }
    }
    $this->optionKey = $optionKey;

    return $this;
  }

  /**
   * Get optionKey.
   *
   * @return string
   */
  public function getOptionKey()
  {
    return $this->optionKey;
  }
}
