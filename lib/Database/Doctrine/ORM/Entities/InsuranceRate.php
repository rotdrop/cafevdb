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
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use Doctrine\ORM\Mapping as ORM;

/**
 * InsuranceRates
 *
 * @ORM\Table(name="InsuranceRates")
 * @ORM\Entity
 */
class InsuranceRate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="InsuranceBroker", inversedBy="insuranceRates", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(referencedColumnName="short_name")
   * @ORM\Id
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
   * @var \DateTime
   *
   * @ORM\Column(type="date", nullable=false, options={"comment"="start of the yearly insurance period"})
   */
  private $dueDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=255, nullable=false)
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
   * @return InsuranceRates
   */
  public function setBroker($broker)
  {
    $this->broker = $broker;

    return $this;
  }

  /**
   * Get broker.
   *
   * @return string
   */
  public function getBroker()
  {
    return $this->broker;
  }

  /**
   * Set geographicalScope.
   *
   * @param string|Types\EnumGeographicalScope $geographicalScope
   *
   * @return InsuranceRates
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
   * @return InsuranceRates
   */
  public function setRate($rate)
  {
    $this->rate = $rate;

    return $this;
  }

  /**
   * Get rate.
   *
   * @return float
   */
  public function getRate()
  {
    return $this->rate;
  }

  /**
   * Set dueDate.
   *
   * @param \DateTime $dueDate
   *
   * @return InsuranceRates
   */
  public function setDueDate($dueDate)
  {
    $this->dueDate = $dueDate;

    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTime
   */
  public function getDueDate()
  {
    return $this->dueDate;
  }

  /**
   * Set policyNumber.
   *
   * @param string $policyNumber
   *
   * @return InsuranceRates
   */
  public function setPolicyNumber($policyNumber)
  {
    $this->policyNumber = $policyNumber;

    return $this;
  }

  /**
   * Get policyNumber.
   *
   * @return string
   */
  public function getPolicyNumber()
  {
    return $this->policyNumber;
  }
}
