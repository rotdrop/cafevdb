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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * InsuranceBroker
 *
 * @ORM\Table(name="InsuranceBrokers")
 * @ORM\Entity
 */
class InsuranceBroker implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=40, nullable=false)
   * @ORM\Id
   */
  private $shortName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512, nullable=false)
   */
  private $longName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512, nullable=false)
   */
  private $address;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="InsuranceRate", mappedBy="broker", fetch="EXTRA_LAZY")
   */
  private $insuranceRates;

  public function __construct() {
    $this->arrayCTOR();
    $this->insuranceRates = new ArrayCollection();
  }

  /**
   * Set shortName.
   *
   * @param string $shortName
   *
   * @return InsuranceBroker
   */
  public function setShortName(string $shortName):InsuranceBroker
  {
    $this->shortName = $shortName;

    return $this;
  }

  /**
   * Get shortName.
   *
   * @return string
   */
  public function getShortName():string
  {
    return $this->shortName;
  }

  /**
   * Set longName.
   *
   * @param string $longName
   *
   * @return InsuranceBroker
   */
  public function setLongName(string $longName):InsuranceBroker
  {
    $this->longName = $longName;

    return $this;
  }

  /**
   * Get longName.
   *
   * @return string
   */
  public function getLongName():string
  {
    return $this->longName;
  }

  /**
   * Set address.
   *
   * @param string $address
   *
   * @return InsuranceBroker
   */
  public function setAddress(string $address):InsuranceBroker
  {
    $this->address = $address;

    return $this;
  }

  /**
   * Get address.
   *
   * @return string
   */
  public function getAddress():string
  {
    return $this->address;
  }

  /**
   * Set insuranceRates.
   *
   * @param ArrayCollection $insuranceRates
   *
   * @return InsuranceBroker
   */
  public function setInsuranceRates(Collection $insuranceRates):InsuranceBroker
  {
    $this->insuranceRates = $insuranceRates;

    return $this;
  }

  /**
   * Get insuranceRates.
   *
   * @return ArrayCollection
   */
  public function getInsuranceRates():Collection
  {
    return $this->insuranceRates;
  }

  /**
   * Get instrumentInsurances.
   *
   * @return ArrayCollection
   */
  public function getInstrumentInsurances():Collection
  {
    $insurances = [];
    /** @var InsuranceRate $rate */
    foreach ($this->insuranceRates as $rate) {
      $insurances = array_merge($insurances, $rate->toArray());
    }
    return new ArrayCollection($insurances);
  }
}
