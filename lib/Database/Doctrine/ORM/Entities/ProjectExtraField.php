<?php
/* Orchestra member, musician and project management application.
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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ProjectExtraFields
 *
 * @ORM\Table(name="ProjectExtraFields")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectExtraFieldsRepository")
 */
class ProjectExtraField implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue
   */
  private $id = null;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="extraFields", fetch="EXTRA_LAZY")
   */
  private $project;

  /**
   * @var int|null
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $displayOrder = null;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var Types\EnumExtraFieldMultiplicity
   *
   * @ORM\Column(type="EnumExtraFieldMultiplicity", nullable=false)
   */
  private $multiplicity;

  /**
   * @var EnumExtraFieldDataType
   *
   * @ORM\Column(type="EnumExtraFieldDataType", nullable=false, options={"default"="text"})
   */
  private $dataType = 'text';

  /**
   * @var ProjectExtraFieldMetaDatum
   *
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDataOption", mappedBy="field", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @ORM\OrderBy({"label" = "ASC"})
   */
  private $dataOptions;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable", nullable=true, options={"comment"="Due-date for financial fields."})
   */
  private $dueDate = null;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=16777215, nullable=true, options={"comment"="Default value."})
   */
  private $defaultValue = null;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4096, nullable=true)
   */
  private $tooltip = null;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Tab to display the field in. If empty, then the projects tab is used."})
   */
  private $tab = null;

  /**
   * @var bool|null
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $encrypted = false;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="If non-empty restrict the visbility to this comma separated list of user-groups."})
   */
  private $readers = null;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="Empty or comma separated list of groups allowed to change the field."})
   */
  private $writers = null;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $disabled = false;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDatum", mappedBy="field", fetch="EXTRA_LAZY")
   */
  private $fieldData;

  public function __construct() {
    $this->arrayCTOR();
    $this->fieldData = new ArrayCollection();
    $this->dataOptions = new ArrayCollection();
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():int
  {
    return $this->id;
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return ProjectExtraField
   */
  public function setId(int $id):ProjectExtraField
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Set project.
   *
   * @param Project $project
   *
   * @return ProjectExtraField
   */
  public function setProject($project):ProjectExtraField
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
   * Set dataOption.
   *
   * @param Collection $dataOptions
   *
   * @return ProjectExtraField
   */
  public function setDataOptions($dataOptions):ProjectExtraField
  {
    $this->dataOptions = $dataOptions;

    return $this;
  }

  /**
   * Get dataOption.
   *
   * @return Collection
   */
  public function getDataOptions():Collection
  {
    return $this->dataOptions;
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectExtraField
   */
  public function setFieldData($fieldData):ProjectExtraField
  {
    $this->fieldData = $fieldData;

    return $this;
  }

  /**
   * Get fieldData.
   *
   * @return Collection
   */
  public function getFieldData():Collection
  {
    return $this->fieldData;
  }

  /**
   * Set displayOrder.
   *
   * @param int|null $displayOrder
   *
   * @return ProjectExtraField
   */
  public function setDisplayOrder($displayOrder):ProjectExtraField
  {
    $this->displayOrder = $displayOrder;

    return $this;
  }

  /**
   * Get displayOrder.
   *
   * @return int|null
   */
  public function getDisplayOrder()
  {
    return $this->displayOrder;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return ProjectExtraField
   */
  public function setName($name):ProjectExtraField
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set multiplicity.
   *
   * @param EnumExtraFieldMultiplicity|string $multiplicity
   *
   * @return ProjectExtraField
   */
  public function setMultiplicity($multiplicity):ProjectExtraField
  {
    $this->multiplicity = new Types\EnumExtraFieldMultiplicity($multiplicity);

    return $this;
  }

  /**
   * Get multiplicity.
   *
   * @return EnumExtraFieldMultiplicity
   */
  public function getMultiplicity():Types\EnumExtraFieldMultiplicity
  {
    return $this->multiplicity;
  }

  /**
   * Set dataType.
   *
   * @param EnumExtraFieldDataType|string $dataType
   *
   * @return ProjectExtraField
   */
  public function setDataType($dataType):ProjectExtraField
  {
    $this->dataType = new Types\EnumExtraFieldDataType($dataType);

    return $this;
  }

  /**
   * Get dataType.
   *
   * @return EnumExtraFieldDataType
   */
  public function getDataType():Types\EnumExtraFieldDataType
  {
    return $this->dataType;
  }

  /**
   * Set dueDate.
   *
   * @param string|null|\DateTimeInterface $dueDate
   *
   * @return ProjectExtraField
   */
  public function setDueDate($dueDate):ProjectExtraField
  {
    if (is_string($dueDate)) {
      $this->dueDate = new \DateTimeImmutable($dueDate);
    } else {
      $this->dueDate = \DateTimeImmutable::createFromInterface($dueDate);
    }
    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTimeImmutable|null
   */
  public function getDueDate():?\DateTimeImmutable
  {
    return $this->dueDate;
  }

  /**
   * Set defaultValue.
   *
   * @param string $defaultValue
   *
   * @return ProjectExtraField
   */
  public function setDefaultValue($defaultValue):ProjectExtraField
  {
    $this->defaultValue = $defaultValue;

    return $this;
  }

  /**
   * Get defaultValue.
   *
   * @return string
   */
  public function getDefaultValue()
  {
    return $this->defaultValue;
  }

  /**
   * Set toolTip.
   *
   * @param string $toolTip
   *
   * @return ProjectExtraField
   */
  public function setToolTip($toolTip):ProjectExtraField
  {
    $this->toolTip = $toolTip;

    return $this;
  }

  /**
   * Get toolTip.
   *
   * @return string
   */
  public function getToolTip()
  {
    return $this->toolTip;
  }

  /**
   * Set tab.
   *
   * @param string $tab
   *
   * @return ProjectExtraField
   */
  public function setTab($tab):ProjectExtraField
  {
    $this->tab = $tab;

    return $this;
  }

  /**
   * Get tab.
   *
   * @return string
   */
  public function getTab()
  {
    return $this->tab;
  }

  /**
   * Set encrypted.
   *
   * @param bool|null $encrypted
   *
   * @return ProjectExtraField
   */
  public function setEncrypted($encrypted):ProjectExtraField
  {
    $this->encrypted = $encrypted;

    return $this;
  }

  /**
   * Get encrypted.
   *
   * @return bool|null
   */
  public function getEncrypted()
  {
    return $this->encrypted;
  }

  /**
   * Set readers.
   *
   * @param string|null $readers
   *
   * @return ProjectExtraField
   */
  public function setReaders($readers):ProjectExtraField
  {
    $this->readers = $readers;

    return $this;
  }

  /**
   * Get readers.
   *
   * @return string|null
   */
  public function getReaders()
  {
    return $this->readers;
  }

  /**
   * Set writers.
   *
   * @param string|null $writers
   *
   * @return ProjectExtraField
   */
  public function setWriters($writers):ProjectExtraField
  {
    $this->writers = $writers;

    return $this;
  }

  /**
   * Get writers.
   *
   * @return string|null
   */
  public function getWriters()
  {
    return $this->writers;
  }

  /**
   * Set disabled.
   *
   * @param bool $disabled
   *
   * @return ProjectExtraField
   */
  public function setDisabled($disabled):ProjectExtraField
  {
    $this->disabled = $disabled;

    return $this;
  }

  /**
   * Get disabled.
   *
   * @return bool
   */
  public function getDisabled()
  {
    return $this->disabled;
  }
}
