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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * InstrumentInsurance
 *
 * @ORM\Table(name="InstrumentInsurances")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentInsurancesRepository")
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 * @ORM\HasLifecycleCallbacks
 */
class InstrumentInsurance implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="instrumentInsurances", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $instrumentHolder;

  /**
   * @var Musician
   *
   * A possibly different person which is responsible for paying the
   * insurance fees.
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payableInsurances", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $billToParty;

  /**
   * @ORM\ManyToOne(targetEntity="InsuranceBroker", inversedBy="instrumentInsurances", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(referencedColumnName="short_name", nullable=false)
   */
  private $broker;

  /**
   * @var Types\EnumGeoraphicalScope
   *
   * @ORM\Column(type="EnumGeographicalScope", nullable=false, options={"default"="Germany"})
   */
  private $geographicalScope;

  /**
   * @ORM\OneToOne(targetEntity="InsuranceRate")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="broker_id", referencedColumnName="broker_id"),
   *   @ORM\JoinColumn(name="geographical_scope", referencedColumnName="geographical_scope")
   * )
   */
  private $insuranceRate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $object;

  /**
   * @var array
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"=false})
   */
  private $accessory = false;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $manufacturer;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $yearOfConstruction;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $insuranceAmount;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $startOfInsurance;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return InstrumentInsurance
   */
  public function setId($id):InstrumentInsurance
  {
    $this->id = $id;

    return $this;
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
   * Set instrumentHolder.
   *
   * @param int $instrumentHolder
   *
   * @return InstrumentInsurance
   */
  public function setInstrumentHolder($instrumentHolder):InstrumentInsurance
  {
    $this->instrumentHolder = $instrumentHolder;

    return $this;
  }

  /**
   * Get instrumentHolder.
   *
   * @return Musician
   */
  public function getInstrumentHolder():Musician
  {
    return $this->instrumentHolder;
  }

  /**
   * Set broker.
   *
   * @param string $broker
   *
   * @return InstrumentInsurance
   */
  public function setBroker($broker):InstrumentInsurance
  {
    $this->broker = $broker;

    return $this;
  }

  /**
   * Get broker.
   *
   * @return InsuranceBroker
   */
  public function getBroker():InsuranceBroker
  {
    return $this->broker;
  }

  /**
   * Set geographicalScope.
   *
   * @param string|Types\EnumGeographicalScope $geographicalScope
   *
   * @return InstrumentInsurance
   */
  public function setGeographicalScope($geographicalScope):InstrumentInsurance
  {
    $this->geographicalScope = new Types\EnumGeographicalScope($geographicalScope);

    return $this;
  }

  /**
   * Get geographicalScope.
   *
   * @return Types\EnumGeographicalScope
   */
  public function getGeographicalScope():Types\EnumGeographicalScope
  {
    return $this->geographicalScope;
  }

  /**
   * Set object.
   *
   * @param string $object
   *
   * @return InstrumentInsurance
   */
  public function setObject($object):InstrumentInsurance
  {
    $this->object = $object;

    return $this;
  }

  /**
   * Get object.
   *
   * @return string
   */
  public function getObject():string
  {
    return $this->object;
  }

  /**
   * Set accessory.
   *
   * @param bool $accessory
   *
   * @return InstrumentInsurance
   */
  public function setAccessory($accessory):InstrumentInsurance
  {
    $this->accessory = $accessory;

    return $this;
  }

  /**
   * Get accessory.
   *
   * @return bool
   */
  public function getAccessory():bool
  {
    return $this->accessory;
  }

  /**
   * Set manufacturer.
   *
   * @param string $manufacturer
   *
   * @return InstrumentInsurance
   */
  public function setManufacturer($manufacturer):InstrumentInsurance
  {
    $this->manufacturer = $manufacturer;

    return $this;
  }

  /**
   * Get manufacturer.
   *
   * @return string
   */
  public function getManufacturer():string
  {
    return $this->manufacturer;
  }

  /**
   * Set yearOfConstruction.
   *
   * @param string $yearOfConstruction
   *
   * @return InstrumentInsurance
   */
  public function setYearOfConstruction($yearOfConstruction):InstrumentInsurance
  {
    $this->yearOfConstruction = $yearOfConstruction;

    return $this;
  }

  /**
   * Get yearOfConstruction.
   *
   * @return string
   */
  public function getYearOfConstruction()
  {
    return $this->yearOfConstruction;
  }

  /**
   * Set insuranceAmount.
   *
   * @param int $insuranceAmount
   *
   * @return InstrumentInsurance
   */
  public function setInsuranceAmount($insuranceAmount):InstrumentInsurance
  {
    $this->insuranceAmount = $insuranceAmount;

    return $this;
  }

  /**
   * Get insuranceAmount.
   *
   * @return int
   */
  public function getInsuranceAmount():int
  {
    return $this->insuranceAmount;
  }

  /**
   * Set billToParty.
   *
   * @param int $billToParty
   *
   * @return InstrumentInsurance
   */
  public function setBillToParty($billToParty):InstrumentInsurance
  {
    $this->billToParty = $billToParty;

    return $this;
  }

  /**
   * Get billToParty.
   *
   * @return Musician
   */
  public function getBillToParty():Musician
  {
    return $this->billToParty;
  }

  /**
   * @ORM\PrePersist
   * @ORM\PreUpdate
   * @ORM\PreFlush
   */
  public function ensureBillToParty()
  {
    if (empty($this->billToParty) && !empty($this->instrumentHolder)) {
      $this->billToParty = $this->instrumentHolder;
    }
  }

  /**
   * Set startOfInsurance.
   *
   * @param string|\DateTimeInterface $submitDate
   *
   * @return InstrumentInsurance
   */
  public function setStartOfInsurance($startOfInsurance):InstrumentInsurance
  {
    $this->startOfInsurance = self::convertToDateTime($startOfInsurance);

    return $this;
  }

  /**
   * Get startOfInsurance.
   *
   * @return \DateTimeInterface
   */
  public function getStartOfInsurance():?\DateTimeInterface
  {
    return $this->startOfInsurance;
  }

  /**
   * Set insuranceRate.
   *
   * @param int $insuranceRate
   *
   * @return InstrumentInsurance
   */
  public function setInsuranceRate($insuranceRate):InstrumentInsurance
  {
    $this->insuranceRate = $insuranceRate;

    return $this;
  }

  /**
   * Get insuranceRate.
   *
   * @return InsuranceRate
   */
  public function getInsuranceRate():InsuranceRate
  {
    return $this->insuranceRate;
  }
}
