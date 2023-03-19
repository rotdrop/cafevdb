<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

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
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

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
   * A possibly different person which is the owner of the instrument. If NULL
   * we assume that the instrument holder is also the instrument owner.
   *
   * @ORM\ManyToOne(targetEntity="Musician", fetch="EXTRA_LAZY")
   */
  private $instrumentOwner;

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
   * @var InsuranceRate
   *
   * @ORM\ManyToOne(targetEntity="InsuranceRate", inversedBy="instrumentInsurances", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="broker_id", referencedColumnName="broker_id", nullable=false),
   *   @ORM\JoinColumn(name="geographical_scope", referencedColumnName="geographical_scope", nullable=false)
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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return InstrumentInsurance
   */
  public function setId(?int $id):InstrumentInsurance
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return null|int
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set instrumentHolder.
   *
   * @param null|int|Musician $instrumentHolder
   *
   * @return InstrumentInsurance
   */
  public function setInstrumentHolder(mixed $instrumentHolder):InstrumentInsurance
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
   * Set instrumentOwner.
   *
   * @param null|int|Musician $instrumentOwner
   *
   * @return InstrumentInsurance
   */
  public function setInstrumentOwner(mixed $instrumentOwner):InstrumentInsurance
  {
    $this->instrumentOwner = $instrumentOwner;

    return $this;
  }

  /**
   * Get instrumentOwner.
   *
   * @return Musician
   */
  public function getInstrumentOwner():?Musician
  {
    return $this->instrumentOwner;
  }

  /**
   * Get broker.
   *
   * @return InsuranceBroker
   */
  public function getBroker():InsuranceBroker
  {
    return $this->insuranceRate->getBroker();
  }

  /**
   * Get geographicalScope.
   *
   * @return Types\EnumGeographicalScope
   */
  public function getGeographicalScope():Types\EnumGeographicalScope
  {
    return $this->insuranceRate->getGeographicalScope();
  }

  /**
   * Set object.
   *
   * @param string $object
   *
   * @return InstrumentInsurance
   */
  public function setObject(string $object):InstrumentInsurance
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
   * @param null|bool $accessory
   *
   * @return InstrumentInsurance
   */
  public function setAccessory(?bool $accessory):InstrumentInsurance
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
    return !empty($this->accessory);
  }

  /**
   * Set manufacturer.
   *
   * @param string $manufacturer
   *
   * @return InstrumentInsurance
   */
  public function setManufacturer(string $manufacturer):InstrumentInsurance
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
   * Set yearOfConstruction. This is a string as it may either "unknnow" or
   * something else instead of the 4-digit year.
   *
   * @param string $yearOfConstruction
   *
   * @return InstrumentInsurance
   */
  public function setYearOfConstruction(string $yearOfConstruction):InstrumentInsurance
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
  public function setInsuranceAmount(int $insuranceAmount):InstrumentInsurance
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
   * @param null|int|Musician $billToParty
   *
   * @return InstrumentInsurance
   */
  public function setBillToParty(mixed $billToParty):InstrumentInsurance
  {
    $this->billToParty = $billToParty;

    return $this;
  }

  /**
   * Get billToParty.
   *
   * @return Musician
   */
  public function getBillToParty():?Musician
  {
    return $this->billToParty;
  }

  /**
   * Internal helper, ensure that bill-to is non-empty.
   *
   * @return void
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   * @ORM\PreFlush
   */
  public function ensureBillToParty():void
  {
    if (empty($this->billToParty) && !empty($this->instrumentHolder)) {
      $this->billToParty = $this->instrumentHolder;
    }
  }

  /**
   * Set startOfInsurance.
   *
   * @param string|DateTimeInterface $startOfInsurance
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
   * @param InsuranceRate $insuranceRate
   *
   * @return InstrumentInsurance
   */
  public function setInsuranceRate(InsuranceRate $insuranceRate):InstrumentInsurance
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
