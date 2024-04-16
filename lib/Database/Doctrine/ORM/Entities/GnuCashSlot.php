<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Link to the Gnucash accounts table.
 *
 * @ORM\Table(name="GnuCashSlots")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashSlot implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;

// CREATE TABLE `slots` (
//   `id` int(11) NOT NULL,
//   `obj_guid` varchar(32) NOT NULL,
//   `name` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
//   `slot_type` int(11) NOT NULL,
//   `int64_val` bigint(20) DEFAULT NULL,
//   `string_val` varchar(4096) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
//   `double_val` double DEFAULT NULL,
//   `timespec_val` datetime DEFAULT '1970-01-01 00:00:00',
//   `guid_val` varchar(32) DEFAULT NULL,
//   `numeric_val_num` bigint(20) DEFAULT NULL,
//   `numeric_val_denom` bigint(20) DEFAULT NULL,
//   `gdate_val` date DEFAULT NULL
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
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
   * @return Musician
   */
  public function setId(int $id):GnuCashSlot
  {
    $this->id = $id;

    return $this;
  }

  /**
   * @return string OBJGUID.
   */
  public function getObjGuid():string
  {
    return $this->objGuid;
  }

  /**
   * @param string $objGuid
   *
   * @return GnuCashAccount $this
   */
  public function setObjGuid(string $objGuid):GnuCashAccount
  {
    $this->objGuid = $objGuid;

    return $this;
  }

  /**
   * @return string NAME.
   */
  public function getName():string
  {
    return $this->name;
  }

  /**
   * @param string $name
   *
   * @return GnuCashAccount $this
   */
  public function setName(string $name):GnuCashAccount
  {
    $this->name = $name;

    return $this;
  }

  /**
   * @return string SLOTTYPE.
   */
  public function getSlotType():GnuCashSlotType
  {
    return GnuCashSlottype($this->slotType);
  }

  /**
   * @param int|GnuCashSlotType $slotType
   *
   * @return GnuCashAccount $this
   */
  public function setSlotType(int|GnuCashSlotType $slotType):GnuCashAccount
  {
    $this->slotType = (int)$slotType;

    return $this;
  }

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   */
  private int $id;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $objGuid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4096, nullable=false)
   * @ORM\Id
   */
  private string $name;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private int $slotType;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=true)
   */
  private int $int64Val;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4096, nullable=true)
   * @ORM\Id
   */
  private string $stringVal;

  /**
   * @var double
   *
   * @ORM\Column(type="float", nullable=true)
   */
  private $doubleVal;

  /**
   * @var \DateTimeImmutable
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private $timespecVal;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"fixed": true, "collation"="ascii_general_ci"})
   */
  private string $guidVal;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=true)
   */
  private int $numericValNum;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=20, nullable=true)
   */
  private int $numericValDenom;

  /**
   * @var \DateTimeImmutable
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $gdateVal;
}
