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
 * @ORM\Table(
 *   name="GnuCashSlots",
 *   indexes={
 *     @ORM\Index(name="slots_guid_index", columns={"obj_guid"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
 */
class GnuCashSlot implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
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
   * @ORM\Column(type="integer", length=20, nullable=true, name="int64_val")
   */
  private int $int64Val;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4096, nullable=true)
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
   * @return GnuCashSlot $this
   */
  public function setObjGuid(string $objGuid):GnuCashSlot
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
   * @return GnuCashSlot $this
   */
  public function setName(string $name):GnuCashSlot
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
   * @return GnuCashSlot $this
   */
  public function setSlotType(int|GnuCashSlotType $slotType):GnuCashSlot
  {
    $this->slotType = (int)$slotType;

    return $this;
  }

  /**
   * @return string GUIDVAL.
   */
  public function getGuidVal():string
  {
    return $this->guidVal;
  }

  /**
   * @param string $guidVal
   *
   * @return GnuCashSlot $this
   */
  public function setGuidVal(string $guidVal):GnuCashSlot
  {
    $this->guidVal = $guidVal;

    return $this;
  }

  /**
   * Get gdateVal.
   *
   * @return \DateTime|null
   */
  public function getGdateVal():?DateTimeInterface
  {
    return $this->gdateVal;
  }

  /**
   * Set gdateVal.
   *
   * @param \DateTime|null $gdateVal
   *
   * @return CompositePayment
   */
  public function setGdateVal($gdateVal = null):CompositePayment
  {
    $this->gdateVal = self::convertToDateTime($gdateVal);

    return $this;
  }

  /**
   * @return int INT64VAL.
   */
  public function getInt64Val():int
  {
    return $this->int64Val;
  }

  /**
   * @param int $int64Val
   *
   * @return GnuCashSlot $this
   */
  public function setInt64Val(int $int64Val):GnuCashSlot
  {
    $this->int64Val = $int64Val;

    return $this;
  }

  /**
   * @return string STRINGVAL.
   */
  public function getStringVal():string
  {
    return $this->stringVal;
  }

  /**
   * @param string $stringVal
   *
   * @return GnuCashSlot $this
   */
  public function setStringVal(string $stringVal):GnuCashSlot
  {
    $this->stringVal = $stringVal;

    return $this;
  }

  /**
   * @return float
   */
  public function getDoubleVal():float
  {
    return $this->doubleVal;
  }

  /**
   * @param float $doubleVal
   *
   * @return GnuCashSlot $this
   */
  public function setDoubleVal(float $doubleVal):GnuCashSlot
  {
    $this->doubleVal = $doubleVal;

    return $this;
  }

  /**
   * @return int NUMERICVALNUM.
   */
  public function getNumericValNum():int
  {
    return $this->numericValNum;
  }

  /**
   * @param int $numericValNum
   *
   * @return GnuCashSlot $this
   */
  public function setNumericValNum(int $numericValNum):GnuCashSlot
  {
    $this->numericValNum = $numericValNum;

    return $this;
  }

  /**
   * @return int NUMERICVALDENOM.
   */
  public function getNumericValDenom():int
  {
    return $this->numericValDenom;
  }

  /**
   * @param int $numericValDenom
   *
   * @return GnuCashSlot $this
   */
  public function setNumericValDenom(int $numericValDenom):GnuCashSlot
  {
    $this->numericValDenom = $numericValDenom;

    return $this;
  }

  /**
   * Get timespecVal.
   *
   * @return \DateTime|null
   */
  public function getTimespecVal():?DateTimeInterface
  {
    return $this->timespecVal;
  }

  /**
   * Set timespecVal.
   *
   * @param \DateTime|null $timespecVal
   *
   * @return CompositePayment
   */
  public function setTimespecVal($timespecVal = null):CompositePayment
  {
    $this->timespecVal = self::convertToDateTime($timespecVal);

    return $this;
  }
}
