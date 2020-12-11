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
use Doctrine\Common\Collections\ArrayCollection;

/**
 * ProjectExtraFields
 *
 * @ORM\Table(name="ProjectExtraFields", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectFieldIndex", columns={"ProjectId", "FieldIndex"})}, indexes={@ORM\Index(name="ProjectId", columns={"ProjectId"})})
 * @ORM\Entity
 */
class ProjectExtraField implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="Id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="ProjectId", type="integer", nullable=false)
   */
  private $projectId;

  /**
   * @var int
   *
   * @ORM\Column(name="FieldIndex", type="integer", nullable=false, options={"comment"="Extra-field index into Besetzungen table."})
   */
  private $fieldIndex;

  /**
   * @var int|null
   *
   * @ORM\Column(name="DisplayOrder", type="integer", nullable=true)
   */
  private $displayOrder;

  /**
   * @var string
   *
   * @ORM\Column(name="Name", type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var int
   *
   * @ORM\Column(name="TypeId", type="integer", nullable=false, options={"default"="1","comment"="Link to ProjectExtraFieldTypes"})
   */
  private $typeId = '1';

  /**
   * @var string|null
   *
   * @ORM\Column(name="AllowedValues", type="string", length=1024, nullable=true, options={"comment"="Set of allowed values for set and enumerations."})
   */
  private $allowedvalues;

  /**
   * @var string
   *
   * @ORM\Column(name="DefaultValue", type="string", length=1024, nullable=false, options={"comment"="Default value."})
   */
  private $defaultValue;

  /**
   * @var string
   *
   * @ORM\Column(name="ToolTip", type="string", length=4096, nullable=false)
   */
  private $toolTip;

  /**
   * @var string
   *
   * @ORM\Column(name="Tab", type="string", length=256, nullable=false, options={"comment"="Tab to display the field in. If empty, then teh projects tab is used."})
   */
  private $tab;

  /**
   * @var bool|null
   *
   * @ORM\Column(name="Encrypted", type="boolean", nullable=true, options={"default"="0"})
   */
  private $encrypted = '0';

  /**
   * @var string|null
   *
   * @ORM\Column(name="Readers", type="string", length=1024, nullable=true, options={"comment"="If non-empty restrict the visbility to this comma separated list of user-groups."})
   */
  private $readers;

  /**
   * @var string|null
   *
   * @ORM\Column(name="Writers", type="string", length=1024, nullable=true, options={"comment"="Empty or comma separated list of groups allowed to change the field."})
   */
  private $writers;

  /**
   * @var bool
   *
   * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * @ORM\ManyToOne(targetEntity="ProjectExtraFieldType")
   * @ORM\JoinColumn(name="TypeId", referencedColumnName="Id")
   */
  private $type;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="extraFields", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="ProjectId", referencedColumnName="Id")
   */
  private $project;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldValueOption", mappedBy="field")
   */
  private $valueOptions;

  public function __construct() {
    $this->arrayCTOR();
    $this->valueOptions = new ArrayCollection();
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
   * @return ProjectExtraFields
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
   * Set fieldIndex.
   *
   * @param int $fieldIndex
   *
   * @return ProjectExtraFields
   */
  public function setFieldIndex($fieldIndex)
  {
    $this->fieldIndex = $fieldIndex;

    return $this;
  }

  /**
   * Get fieldIndex.
   *
   * @return int
   */
  public function getFieldIndex()
  {
    return $this->fieldIndex;
  }

  /**
   * Set displayOrder.
   *
   * @param int|null $displayOrder
   *
   * @return ProjectExtraFields
   */
  public function setDisplayOrder($displayOrder = null)
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
   * @return ProjectExtraFields
   */
  public function setName($name)
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
   * Set type.
   *
   * @param int $type
   *
   * @return ProjectExtraFields
   */
  public function setType($type)
  {
    $this->type = $type;

    return $this;
  }

  /**
   * Get type.
   *
   * @return int
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * Set allowedValues.
   *
   * @param string|null $allowedValues
   *
   * @return ProjectExtraFields
   */
  public function setAllowedValues($allowedValues = null)
  {
    $this->allowedValues = $allowedValues;

    return $this;
  }

  /**
   * Get allowedValues.
   *
   * @return string|null
   */
  public function getAllowedValues()
  {
    return $this->allowedValues;
  }

  /**
   * Set defaultValue.
   *
   * @param string $defaultValue
   *
   * @return ProjectExtraFields
   */
  public function setDefaultValue($defaultValue)
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
   * @return ProjectExtraFields
   */
  public function setToolTip($toolTip)
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
   * @return ProjectExtraFields
   */
  public function setTab($tab)
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
   * @return ProjectExtraFields
   */
  public function setEncrypted($encrypted = null)
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
   * @return ProjectExtraFields
   */
  public function setReaders($readers = null)
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
   * @return ProjectExtraFields
   */
  public function setWriters($writers = null)
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
   * @return ProjectExtraFields
   */
  public function setDisabled($disabled)
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
