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
 * InsuranceBrokers
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
   * @ORM\OneToMany(targetEntity="InsuranceRate", mappedBy="broker")
   */
  private $insuranceRates;

  /**
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="broker")
   */
  private $instrumentInsurances;

  public function __construct() {
    $this->arrayCTOR();
    $this->insuranceRates = new ArrayCollection();
    $this->instrumentInsurance = new ArrayCollection();
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
   * Set shortName.
   *
   * @param string $shortName
   *
   * @return InsuranceBrokers
   */
  public function setShortName($shortName)
  {
    $this->shortName = $shortName;

    return $this;
  }

  /**
   * Get shortName.
   *
   * @return string
   */
  public function getShortName()
  {
    return $this->shortName;
  }

  /**
   * Set longName.
   *
   * @param string $longName
   *
   * @return InsuranceBrokers
   */
  public function setLongName($longName)
  {
    $this->longName = $longName;

    return $this;
  }

  /**
   * Get longName.
   *
   * @return string
   */
  public function getLongName()
  {
    return $this->longName;
  }

  /**
   * Set address.
   *
   * @param string $address
   *
   * @return InsuranceBrokers
   */
  public function setAddress($address)
  {
    $this->address = $address;

    return $this;
  }

  /**
   * Get address.
   *
   * @return string
   */
  public function getAddress()
  {
    return $this->address;
  }
}
