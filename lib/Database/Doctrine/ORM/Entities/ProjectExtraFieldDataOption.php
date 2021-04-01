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

use Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Common\Uuid;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ProjectExtraFieldsDataOptions
 *
 * @ORM\Table(name="ProjectExtraFieldsDataOptions")
 * @ORM\Entity
 * @Gedmo\SoftDeleteable
 */
class ProjectExtraFieldDataOption implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * Link back to ProjectExtraField
   *
   * @ORM\ManyToOne(targetEntity="ProjectExtraField", inversedBy="dataOptions", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $field;

  /**
   * @var \Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(type="uuid_binary")
   * @ORM\Id
   */
  private $key;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $label;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $data;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $tooltip;

  /**
   * @ORM\Column(type="integer", nullable=true)
   */
  private $limit;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDatum", mappedBy="dataOption", fetch="EXTRA_LAZY")
   */
  private $fieldData;

  public function __construct() {
    $this->arrayCTOR();
    $this->fieldData = new ArrayCollection();
  }

  /**
   * Set field.
   *
   * @param ProjectExtraField $field
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setField($field):ProjectExtraFieldDataOption
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
   * Set key.
   *
   * @param string|UuidInterface $key
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setKey($key):ProjectExtraFieldDataOption
  {
    if (empty($key = Uuid::asUuid($key))) {
      throw new \Exception("UUID DATA: ".$key);
    }
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return UuidInterface
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * Set label.
   *
   * @param string $label
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setLabel($label):ProjectExtraFieldDataOption
  {
    $this->label = $label;

    return $this;
  }

  /**
   * Get label.
   *
   * @return string|null
   */
  public function getLabel()
  {
    return $this->label;
  }

  /**
   * Set data.
   *
   * @param string $data
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setData($data):ProjectExtraFieldDataOption
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return int
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * Set toolTip.
   *
   * @param string|null $toolTip
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setToolTip($toolTip):ProjectExtraFieldDataOption
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
   * Set limit.
   *
   * @param int $limit
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setLimit($limit):ProjectExtraFieldDataOption
  {
    $this->limit = $limit;

    return $this;
  }

  /**
   * Get limit.
   *
   * @return int
   */
  public function getLimit()
  {
    return $this->limit;
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectExtraFieldDataOption
   */
  public function setFieldData($fieldData):ProjectExtraFieldDataOption
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
}
