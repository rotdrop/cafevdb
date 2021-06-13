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
use OCA\CAFEVDB\Wrapped\CJH\Doctrine\Extensions\Mapping\Annotation as CJH;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * InsuranceRate
 *
 * @ORM\Table(name="InsuranceRates")
 * @ORM\Entity
 */
class InsuranceRate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="InsuranceBroker", inversedBy="insuranceRates", cascade={"persist", "merge"}, fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(referencedColumnName="short_name")
   * @ORM\Id
   * @CJH\ForeignKey(targetEntity="InsuranceBroker", referencedColumnName="short_name", onUpdate="cascade")
   */
  private $broker;

  /**
   * @var Types\EnumGeographicalScope
   *
   * @ORM\Column(type="EnumGeographicalScope", nullable=false, options={"default"="Germany"})
   * @ORM\Id
   */
  private $geographicalScope;

  /**
   * @var float
   *
   * @ORM\Column(type="float", precision=10, scale=0, nullable=false, options={"comment"="fraction, not percentage, excluding taxes"})
   */
  private $rate;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable", nullable=false, options={"comment"="start of the yearly insurance period"})
   */
  private $dueDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $policyNumber;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set broker.
   *
   * @param string $broker
   *
   * @return InsuranceRate
   */
  public function setBroker($broker):InsuranceRate
  {
    $this->broker = $broker;

    return $this;
  }

  /**
   * Get broker.
   *
   * @return string
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
   * @return InsuranceRate
   */
  public function setGeographicalScope($geographicalScope):InsuranceRate
  {
    $this->geographicalScope = new Types\EnumGeographicalScope($geographicalScope);

    return $this;
  }

  /**
   * Get geographicalScope.
   *
   * @return array
   */
  public function getGeographicalScope():Types\EnumGeographicalScope
  {
    return $this->geographicalScope;
  }

  /**
   * Set rate.
   *
   * @param float $rate
   *
   * @return InsuranceRate
   */
  public function setRate(float $rate):InsuranceRate
  {
    $this->rate = $rate;

    return $this;
  }

  /**
   * Get rate.
   *
   * @return float
   */
  public function getRate():float
  {
    return $this->rate;
  }

  /**
   * Set dueDate.
   *
   * @param string|\DateTimeInterface $dueDate
   *
   * @return InsuranceRate
   */
  public function setDueDate($dueDate):InsuranceRate
  {
    $this->dueDate = self::convertToDateTime($dueDate);
    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTimeImmutable
   */
  public function getDueDate():\DateTimeImmutable
  {
    return $this->dueDate;
  }

  /**
   * Set policyNumber.
   *
   * @param string $policyNumber
   *
   * @return InsuranceRate
   */
  public function setPolicyNumber(?string $policyNumber):InsuranceRate
  {
    $this->policyNumber = $policyNumber;

    return $this;
  }

  /**
   * Get policyNumber.
   *
   * @return string
   */
  public function getPolicyNumber():?string
  {
    return $this->policyNumber;
  }
}
