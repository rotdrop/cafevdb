<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * InsuranceRate
 *
 * @ORM\Table(name="InsuranceClaims")
 * @ORM\Entity
 */
class InsuranceClaim implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
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
   * @var InstrumentInsurance
   *
   * @ORM\ManyToOne(targetEntity="InstrumentInsurance", inversedBy="insuranceClaims")
   */
  private $instrumentInsurance;

  /**
   * @var \DateTimeImmutable
   *
   * The date when the damage occurred
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dateOfDamage;

  /**
   * @var \DateTimeImmutable
   *
   * The date when the damage was either refunded of rejected.
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dateSettled;

  /**
   * @var float
   *
   * The amount spent to settle the damage.
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $expenses;

  /**
   * @var float
   *
   * The amount paid by the insurance.
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $reimbursement;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $remarks;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return InsuranceClaim
   */
  public function setId($id):InsuranceClaim
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
   * Set instrumentInsurance.
   *
   * @param InstrumentInsurance $instrumentInsurance
   *
   * @return InsuranceClaim
   */
  public function setInstrumentInsurance(InstrumentInsurance $instrumentInsurance):InsuranceClaim
  {
    $this->instrumentInsurance = $instrumentInsurance;

    return $this;
  }

  /**
   * Get instrumentInsurance.
   *
   * @return InstrumentInsurance
   */
  public function getInstrumentInsurance():InstrumentInsurance
  {
    return $this->instrumentInsurance;
  }

  /**
   * Set dateOfDamage.
   *
   * @param string|int|\DateTimeInterface $dateOfDamage
   *
   * @return InsuranceClaim
   */
  public function setDateOfDamage($dateOfDamage):InsuranceClaim
  {
    $this->dateOfDamage = self::convertToDateTime($dateOfDamage);

    return $this;
  }

  /**
   * Get dateOfDamage.
   *
   * @return \DateTimeInterface
   */
  public function getDateOfDamage():\DateTimeInterface
  {
    return $this->dateOfDamage;
  }

  /**
   * Set dateSettled.
   *
   * @param string|int|\DateTimeInterface $dateSettled
   *
   * @return InsuranceClaim
   */
  public function setDateSettled($dateSettled):InsuranceClaim
  {
    $this->dateSettled = self::convertToDateTime($dateSettled);

    return $this;
  }

  /**
   * Get dateSettled.
   *
   * @return \DateTimeInterface
   */
  public function getDateSettled():\DateTimeInterface
  {
    return $this->dateSettled;
  }

  /**
   * Set expenses.
   *
   * @param float $expenses
   *
   * @return InsuranceClaim
   */
  public function setExpenses($expenses):InsuranceClaim
  {
    $this->expenses = $expenses;

    return $this;
  }

  /**
   * Get expenses.
   *
   * @return \DateTimeInterface
   */
  public function getExpenses():float
  {
    return $this->expenses;
  }

  /**
   * Set reimbursement.
   *
   * @param float $reimbursement
   *
   * @return InsuranceClaim
   */
  public function setReimbursement($reimbursement):InsuranceClaim
  {
    $this->reimbursement = $reimbursement;

    return $this;
  }

  /**
   * Get reimbursement.
   *
   * @return \DateTimeInterface
   */
  public function getReimbursement():float
  {
    return $this->reimbursement;
  }

  /**
   * Set remarks.
   *
   * @param null|string $remarks
   *
   * @return InsuranceClaim
   */
  public function setRemarks($remarks):InsuranceClaim
  {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * Get remarks.
   *
   * @return \DateTimeInterface
   */
  public function getRemarks():?string
  {
    return $this->remarks;
  }
}
