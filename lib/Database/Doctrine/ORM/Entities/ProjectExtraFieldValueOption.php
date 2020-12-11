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
 * ProjectExtraFieldValueOptions
 *
 * @ORM\Table(name="ProjectExtraFieldValueOptions")
 * @ORM\Entity
 */
class ProjectExtraFieldValueOption implements \ArrayAccess
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
   * @ORM\Column(name="FieldId", type="integer", nullable=false)
   */
  private $fieldId;

  /**
   * @var string
   *
   * @ORM\Column(name="Label", type="string", length=128, nullable=false)
   */
  private $label;

  /**
   * @var string
   *
   * @ORM\Column(name="Data", type="string", length=4096, options={"default"=""})
   */
  private $data;

  /**
   * @var int
   *
   * @ORM\Column(name="Limit", type="integer")
   */
  private $limit;

  /**
   * @var string
   *
   * @ORM\Column(name="ToolTip", type="string", length=4096, nullable=false)
   */
  private $toolTip;

  /**
   * @var bool
   *
   * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * @ORM\ManyToOne(targetEntity="ProjectExtraField", inversedBy="valueOptions", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="FieldId", referencedColumnName="Id")
   */
  private $field;

  public function __construct() {
    $this->arrayCTOR();
  }

}
