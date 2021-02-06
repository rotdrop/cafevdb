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

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

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
  private $displayOrder;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var EnumExtraFieldMultiplicity
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
   * @var string|null
   *
   * @ORM\Column(type="json", nullable=true, options={"comment"="Set of allowed values for set and enumerations."})
   */
  private $allowedValues;

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=16777215, nullable=true, options={"comment"="Default value."})
   */
  private $defaultValue;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4096, nullable=true)
   */
  private $toolTip;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Tab to display the field in. If empty, then the projects tab is used."})
   */
  private $tab;

  /**
   * @var bool|null
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $encrypted = '0';

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="If non-empty restrict the visbility to this comma separated list of user-groups."})
   */
  private $readers;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="Empty or comma separated list of groups allowed to change the field."})
   */
  private $writers;

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
   * Set multiplicity.
   *
   * @param EnumExtraFieldMultiplicity $multiplicity
   *
   * @return ProjectExtraField
   */
  public function setMultiplicity($multiplicity)
  {
    $this->multiplicity = $multiplicity;

    return $this;
  }

  /**
   * Get multiplicity.
   *
   * @return EnumExtraFieldMultiplicity
   */
  public function getMultiplicity()
  {
    return $this->multiplicity;
  }

  /**
   * Set dataType.
   *
   * @param EnumExtraFieldDataType $dataType
   *
   * @return ProjectExtraField
   */
  public function setDataType($dataType)
  {
    $this->dataType = $dataType;

    return $this;
  }

  /**
   * Get dataType.
   *
   * @return EnumExtraFieldDataType
   */
  public function getDataType()
  {
    return $this->dataType;
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
