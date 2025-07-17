<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * A table for recording possible tax excemption reasons and their legal
 * justification (link to the corresponding law, given country).
 */
#[ORM\Table(name: 'TaxationStatutorySources')]
#[ORM\UniqueConstraint(columns: ['tax_type', 'law'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TaxationStatutorySourcesRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class TaxationStatutorySource implements JsonSerializable, ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @var int
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'IDENTITY')]
  private int $id;

  /**
   * @var Types\EnumTaxType
   */
  #[ORM\Column(type: 'EnumTaxType', nullable: false, options: ['default' => 'corporate income tax'])]
  private Types\EnumTaxType $taxType;

  /**
   * @var float
   *
   * Tax rate. If 0 then this item refers to a tax exemption, a taxation
   * exception. This assumes that governments never issue fractional tax rates
   * ... This not the percentage, but the fraction between 0 and 1.
   */
  #[ORM\Column(type: 'decimal', precision: 2, scale: 2, nullable: false, options: ['default' => '0.00'])]
  private string $rate = '0.00';

  /**
   * @var string
   *
   * Country where this is legally valid.
   */
  #[ORM\Column(type: 'string', length: 2, nullable: false, options: ['fixed' => true, 'collation' => 'ascii_general_ci'])]
  private string $country;

  /**
   * @var string
   *
   * The abbreviated law justifying the taxation, "§15, Abs. 3 UStG" or something like this.
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  private string $law;

  /**
   * @var string
   *
   * A hint, e.g. "Kleinunternehmerregelung".
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: true)]
  private string $hint;

  #[ORM\ManyToMany(targetEntity: TaxExemptionNotice::class, mappedBy: 'taxationStatutorySources')]
  private Collection $taxExemptionNotices;

  #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'taxationStatutorySource')]
  private Collection $invoices;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->taxExemptionNotices = new ArrayCollection;
    $this->invoices = new ArrayCollection;
  }
  // phpcs:enable

  /**
   * Get id.
   *
   * @return null|int
   */
  public function getId():?int
  {
    return $this->id ?? null;
  }

  /**
   * Set taxType as a fraction between 0 and 1 (not the percentage)
   *
   * @param string|Types\EnumTaxType $taxType
   *
   * @return TaxationStatutorySource
   */
  public function setTaxType(string|EnumTaxType $taxType):TaxationStatutorySource
  {
    if (($this->taxType ?? null) != $taxType) {
      $this->taxType = is_string($taxType) ? new Types\EnumTaxType($taxType) : $taxType;
    }

    return $this;
  }

  /**
   * Get taxType.
   *
   * @return null|Types\EnumTaxType
   */
  public function getTaxType():?Types\EnumTaxType
  {
    return $this->taxType ?? null;
  }

  /**
   * Set rate as a fraction between 0 and 1 (not the percentage)
   *
   * @param float $rate
   *
   * @return TaxationStatutorySource
   */
  public function setRate(float $rate):TaxationStatutorySource
  {
    $this->rate = $rate;

    return $this;
  }

  /**
   * Get rate.
   *
   * @return null|float
   */
  public function getRate():?float
  {
    $this->rate ?? null;
  }

  /**
   * Set country of validity two-letter code.
   *
   * @param string $country
   *
   * @return TaxationStatutorySource
   */
  public function setCountry(string $country):TaxationStatutorySource
  {
    $this->country = $country;

    return $this;
  }

  /**
   * Get country.
   *
   * @return null|string
   */
  public function getCountry():?string
  {
    return $this->country ?? null;
  }

  /**
   * Set law as abbreviated text.
   *
   * @param string $law
   *
   * @return TaxationStatutorySource
   */
  public function setLaw(string $law):TaxationStatutorySource
  {
    $this->law = $law;

    return $this;
  }

  /**
   * Get law.
   *
   * @return null|string
   */
  public function getLaw():?string
  {
    return $this->law ?? null;
  }

  /**
   * Set hint.
   *
   * @param string $hint
   *
   * @return TaxationStatutorySource
   */
  public function setHint(string $hint):TaxationStatutorySource
  {
    $this->hint = $hint;

    return $this;
  }

  /**
   * Get hint.
   *
   * @return null|string
   */
  public function getHint():?string
  {
    return $this->hint ?? null;
  }

  /** @return Collection */
  public function getTaxExemptionNotices():Collection
  {
    return $this->taxExemptionNotices;
  }

  /**
   * @param Collection $taxExemptionNotices
   *
   * @return DatabaseStorageDirEntry
   */
  public function setTaxExemptionNotices(Collection $taxExemptionNotices):TaxationStatutorySource
  {
    $this->taxExemptionNotices = $taxExemptionNotices;

    return $this;
  }

  /** @return Collection */
  public function getInvoices():Collection
  {
    return $this->invoices;
  }

  /**
   * @param Collection $invoices
   *
   * @return DatabaseStorageDirEntry
   */
  public function setInvoices(Collection $invoices):TaxationStatutorySource
  {
    $this->invoices = $invoices;

    return $this;
  }

  /**
   * @return int
   */
  public function usage():int
  {
    return $this->invoices->count() + $this->taxExemptionNotices->count();
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }
}
