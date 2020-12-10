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
  private $projectid;

  /**
   * @var int
   *
   * @ORM\Column(name="FieldIndex", type="integer", nullable=false, options={"comment"="Extra-field index into Besetzungen table."})
   */
  private $fieldindex;

  /**
   * @var int|null
   *
   * @ORM\Column(name="DisplayOrder", type="integer", nullable=true)
   */
  private $displayorder;

  /**
   * @var string
   *
   * @ORM\Column(name="Name", type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var int
   *
   * @ORM\Column(name="Type", type="integer", nullable=false, options={"default"="1"})
   */
  private $type = '1';

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
  private $defaultvalue;

  /**
   * @var string
   *
   * @ORM\Column(name="ToolTip", type="string", length=4096, nullable=false)
   */
  private $tooltip;

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
   * Set projectid.
   *
   * @param int $projectid
   *
   * @return ProjectExtraFields
   */
  public function setProjectid($projectid)
  {
    $this->projectid = $projectid;

    return $this;
  }

  /**
   * Get projectid.
   *
   * @return int
   */
  public function getProjectid()
  {
    return $this->projectid;
  }

  /**
   * Set fieldindex.
   *
   * @param int $fieldindex
   *
   * @return ProjectExtraFields
   */
  public function setFieldindex($fieldindex)
  {
    $this->fieldindex = $fieldindex;

    return $this;
  }

  /**
   * Get fieldindex.
   *
   * @return int
   */
  public function getFieldindex()
  {
    return $this->fieldindex;
  }

  /**
   * Set displayorder.
   *
   * @param int|null $displayorder
   *
   * @return ProjectExtraFields
   */
  public function setDisplayorder($displayorder = null)
  {
    $this->displayorder = $displayorder;

    return $this;
  }

  /**
   * Get displayorder.
   *
   * @return int|null
   */
  public function getDisplayorder()
  {
    return $this->displayorder;
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
   * Set allowedvalues.
   *
   * @param string|null $allowedvalues
   *
   * @return ProjectExtraFields
   */
  public function setAllowedvalues($allowedvalues = null)
  {
    $this->allowedvalues = $allowedvalues;

    return $this;
  }

  /**
   * Get allowedvalues.
   *
   * @return string|null
   */
  public function getAllowedvalues()
  {
    return $this->allowedvalues;
  }

  /**
   * Set defaultvalue.
   *
   * @param string $defaultvalue
   *
   * @return ProjectExtraFields
   */
  public function setDefaultvalue($defaultvalue)
  {
    $this->defaultvalue = $defaultvalue;

    return $this;
  }

  /**
   * Get defaultvalue.
   *
   * @return string
   */
  public function getDefaultvalue()
  {
    return $this->defaultvalue;
  }

  /**
   * Set tooltip.
   *
   * @param string $tooltip
   *
   * @return ProjectExtraFields
   */
  public function setTooltip($tooltip)
  {
    $this->tooltip = $tooltip;

    return $this;
  }

  /**
   * Get tooltip.
   *
   * @return string
   */
  public function getTooltip()
  {
    return $this->tooltip;
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
