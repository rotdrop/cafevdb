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
 * @ORM\Table(name="ProjectExtraFieldsData", uniqueConstraints={@ORM\UniqueConstraint(name="BesetzungenId", columns={"BesetzungenId", "FieldId"})})
 * @ORM\Entity
 */
class ProjectExtraFieldDatum implements \ArrayAccess
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
   * @ORM\Column(name="BesetzungenId", type="integer", nullable=false)
   */
  private $besetzungenid;

  /**
   * @var int
   *
   * @ORM\Column(name="FieldId", type="integer", nullable=false)
   */
  private $fieldid;

  /**
   * @var string
   *
   * @ORM\Column(name="FieldValue", type="text", length=16777215, nullable=false)
   */
  private $fieldvalue;

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
   * Set besetzungenid.
   *
   * @param int $besetzungenid
   *
   * @return ProjectExtraFieldsData
   */
  public function setBesetzungenid($besetzungenid)
  {
    $this->besetzungenid = $besetzungenid;

    return $this;
  }

  /**
   * Get besetzungenid.
   *
   * @return int
   */
  public function getBesetzungenid()
  {
    return $this->besetzungenid;
  }

  /**
   * Set fieldid.
   *
   * @param int $fieldid
   *
   * @return ProjectExtraFieldsData
   */
  public function setFieldid($fieldid)
  {
    $this->fieldid = $fieldid;

    return $this;
  }

  /**
   * Get fieldid.
   *
   * @return int
   */
  public function getFieldid()
  {
    return $this->fieldid;
  }

  /**
   * Set fieldvalue.
   *
   * @param string $fieldvalue
   *
   * @return ProjectExtraFieldsData
   */
  public function setFieldvalue($fieldvalue)
  {
    $this->fieldvalue = $fieldvalue;

    return $this;
  }

  /**
   * Get fieldvalue.
   *
   * @return string
   */
  public function getFieldvalue()
  {
    return $this->fieldvalue;
  }
}
