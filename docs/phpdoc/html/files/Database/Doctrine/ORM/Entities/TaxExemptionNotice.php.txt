<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use JsonSerializable;
use ArrayAccess;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Record notices of tax exemption from the corporate income tax (or other
 * taxes).
 *
 * @ORM\Table(
 *   name="TaxExemptionNotices",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"tax_type", "assessment_period_start", "assessment_period_end"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TaxExemptionNoticesRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\NeverHardDelete"
 * )
 */
class TaxExemptionNotice implements JsonSerializable, ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private ?int $id = null;

  /**
   * @var Types\EnumTaxType
   *
   * @ORM\Column(type="EnumTaxType", nullable=false, options={"default"="corporate income tax"})
   */
  private ?Types\EnumTaxType $taxType;

  /**
   * @var int
   *
   * First year of assessment period.
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private ?int $assessmentPeriodStart;

  /**
   * @var int
   *
   * Last year of assessment period.
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private ?int $assessmentPeriodEnd;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   *
   * The tax office which issued the the notice of excemption.
   */
  private ?string $taxOffice;

  /**
   * @var string
   *
   * The tax identification number used by $taxOffice.
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   */
  private ?string $taxNumber;

  /**
   * @var DateTimeInterface
   *
   * The date at of the notice of exemption
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private ?DateTimeInterface $dateIssued;

  /**
   * @var string
   *
   * The purpose which led to the notice of exemption as noted on that very
   * notice.
   *
   * @ORM\Column(type="string", length=4096, nullable=false)
   */
  private string $beneficiaryPurpose;

  /**
   * @var bool
   *
   * Whether the orchester is allowed to treat membership fees as donations.
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private bool $membershipFeesAreDonations = false;

  /**
   * @var DatabaseStorageFile
   *
   * Virtul "hard copy" of the letter the tax offic
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFile", cascade={"all"}, orphanRemoval=true)
   */
  private $writtenNotice;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return Instrument
   */
  public function setId(int $id):TaxExemptionNotice
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
   * @return null|Types\EnumTaxType
   */
  public function getTaxType():?Types\EnumTaxType
  {
    return $this->taxType;
  }

  /**
   * @param Types\EnumTaxType $taxType
   *
   * @return TaxExemptionNotice
   */
  public function setTaxType(Types\EnumTaxType $taxType):TaxExemptionNotice
  {
    $this->taxType = $taxType;

    return $this;
  }

  /**
   * @return null|int
   */
  public function getAssessmentPeriodStart():?int
  {
    return $this->assessmentPeriodStart;
  }

  /**
   * @param int $assessmentPeriodStart
   *
   * @return TaxExemptionNotice
   */
  public function setAssessmentPeriodStart(int $assessmentPeriodStart):TaxExemptionNotice
  {
    $this->assessmentPeriodStart = $assessmentPeriodStart;

    return $this;
  }

  /**
   * @return null|int
   */
  public function getAssessmentPeriodEnd():?int
  {
    return $this->assessmentPeriodEnd;
  }

  /**
   * @param int $assessmentPeriodEnd
   *
   * @return TaxExemptionNotice
   */
  public function setAssessmentPeriodEnd(int $assessmentPeriodEnd):TaxExemptionNotice
  {
    $this->assessmentPeriodEnd = $assessmentPeriodEnd;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getTaxOffice():?string
  {
    return $this->taxOffice;
  }

  /**
   * @param string $taxOffice
   *
   * @return TaxExemptionNotice
   */
  public function setTaxOffice(string $taxOffice):TaxExemptionNotice
  {
    $this->taxOffice = $taxOffice;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getTaxNumber():?string
  {
    return $this->taxNumber;
  }

  /**
   * @param string $taxNumber
   *
   * @return TaxExemptionNotice
   */
  public function setTaxNumber(string $taxNumber):TaxExemptionNotice
  {
    $this->taxNumber = $taxNumber;

    return $this;
  }

  /**
   * @return null|DateTimeInterface
   */
  public function getDateIssued():?DateTimeInterface
  {
    return $this->dateIssued;
  }

  /**
   * @param string|int|DateTimeInterface $dateIssued
   *
   * @return TaxExemptionNotice
   */
  public function setDateIssued(mixed $dateIssued):TaxExemptionNotice
  {
    $this->dateIssued = self::convertToDateTime($dateIssued);

    return $this;
  }

  /**
   * @return null|string
   */
  public function getBeneficiaryPurpose():?string
  {
    return $this->beneficiaryPurpose;
  }

  /**
   * @param string $beneficiaryPurpose
   *
   * @return TaxExemptionNotice
   */
  public function setBeneficiaryPurpose(string $beneficiaryPurpose):TaxExemptionNotice
  {
    $this->beneficiaryPurpose = $beneficiaryPurpose;

    return $this;
  }

  /**
   * @return null|bool
   */
  public function getMembershipFeesAreDonations():?bool
  {
    return $this->membershipFeesAreDonations;
  }

  /**
   * @param bool $membershipFeesAreDonations
   *
   * @return TaxExemptionNotice
   */
  public function setMembershipFeesAreDonations(bool $membershipFeesAreDonations):TaxExemptionNotice
  {
    $this->membershipFeesAreDonations = $membershipFeesAreDonations;

    return $this;
  }

  /**
   * @return null|DateTimeInterface
   */
  public function getWrittenNotice():?DatabaseStorageFile
  {
    return $this->writtenNotice;
  }

  /**
   * @param null|DatabaseStorageFile
   *
   * @return TaxExemptionNotice
   */
  public function setWrittenNotice(?DatabaseStorageFile $writtenNotice):TaxExemptionNotice
  {
    $this->writtenNotice = $writtenNotice;

    return $this;
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    $this->toArray();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return 'notice('
      . $this->assessmentPeriodStart . '-' . $this->assessmentPeriodEnd
      . '@'
      . $this->taxType . ' tax'
      . ')';
  }
}
