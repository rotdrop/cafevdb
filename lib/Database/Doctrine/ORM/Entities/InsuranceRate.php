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

use DateTimeImmutable;
use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\CJH\Doctrine\Extensions\Mapping\Annotation as CJH;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

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
  use \OCA\RotDrop\Toolkit\Traits\DateTimeTrait;

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
   * @ORM\Column(type="date_immutable", nullable=true, options={"comment"="start of the yearly insurance period"})
   */
  private $dueDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $policyNumber;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="insuranceRate", fetch="EXTRA_LAZY")
   */
  private $instrumentInsurances;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->instrumentInsurances = new ArrayCollection();
  }
  // phpcs:enable

  /**
   * Set broker.
   *
   * @param null|int|InsuranceBroker $broker
   *
   * @return InsuranceRate
   */
  public function setBroker(mixed $broker):InsuranceRate
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
   * @param null|string|DateTimeInterface $dueDate
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
   * @return DateTimeInterface
   */
  public function getDueDate():?DateTimeInterface
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

  /**
   * Set instrumentInsurances.
   *
   * @param ArrayCollection $instrumentInsurances
   *
   * @return InsuranceBroker
   */
  public function setInstrumentInsurances(Collection $instrumentInsurances):InsuranceRate
  {
    $this->instrumentInsurances = $instrumentInsurances;

    return $this;
  }

  /**
   * Get instrumentInsurances.
   *
   * @return ArrayCollection
   */
  public function getInstrumentInsurances():Collection
  {
    return $this->instrumentInsurances;
  }
}
