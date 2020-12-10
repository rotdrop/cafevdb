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
 * ProjectExtraFieldTypes
 *
 * @ORM\Table(name="ProjectExtraFieldTypes")
 * @ORM\Entity
 */
class ProjectExtraFieldType implements \ArrayAccess
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
   * @var string
   *
   * @ORM\Column(name="Name", type="string", length=256, nullable=false)
   */
  private $name;

  /**
   * @var enumextrafieldmultiplicity
   *
   * @ORM\Column(name="Multiplicity", type="enumextrafieldmultiplicity", nullable=false)
   */
  private $multiplicity;

  /**
   * @var enumextrafieldkind
   *
   * @ORM\Column(name="Kind", type="enumextrafieldkind", nullable=false, options={"default"="general"})
   */
  private $kind = 'general';

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
   * Set name.
   *
   * @param string $name
   *
   * @return ProjectExtraFieldTypes
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
   * @param enumextrafieldmultiplicity $multiplicity
   *
   * @return ProjectExtraFieldTypes
   */
  public function setMultiplicity($multiplicity)
  {
    $this->multiplicity = $multiplicity;

    return $this;
  }

  /**
   * Get multiplicity.
   *
   * @return enumextrafieldmultiplicity
   */
  public function getMultiplicity()
  {
    return $this->multiplicity;
  }

  /**
   * Set kind.
   *
   * @param enumextrafieldkind $kind
   *
   * @return ProjectExtraFieldTypes
   */
  public function setKind($kind)
  {
    $this->kind = $kind;

    return $this;
  }

  /**
   * Get kind.
   *
   * @return enumextrafieldkind
   */
  public function getKind()
  {
    return $this->kind;
  }
}
