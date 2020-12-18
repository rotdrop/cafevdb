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
   * @ORM\JoinColumn(name="field_id", referencedColumnName="id"),
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
   * @var string
   *
   * @ORM\Column(type="text", length=16777215, nullable=false)
   */
  private $fieldValue;

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
   * Get id.
   *
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set projectParticipantId.
   *
   * @param int $projectParticipantId
   *
   * @return ProjectExtraFieldsData
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
   * Set fieldId.
   *
   * @param int $fieldId
   *
   * @return ProjectExtraFieldsData
   */
  public function setFieldId($fieldId)
  {
    $this->fieldId = $fieldId;

    return $this;
  }

  /**
   * Get fieldId.
   *
   * @return int
   */
  public function getFieldId()
  {
    return $this->fieldId;
  }

  /**
   * Set fieldValue.
   *
   * @param string $fieldValue
   *
   * @return ProjectExtraFieldsData
   */
  public function setFieldValue($fieldValue)
  {
    $this->fieldValue = $fieldValue;

    return $this;
  }

  /**
   * Get fieldValue.
   *
   * @return string
   */
  public function getFieldValue()
  {
    return $this->fieldValue;
  }
}
