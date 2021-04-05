<?php
/* Orchestra member, musician and project management application.
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

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use MediaMonks\Doctrine\Mapping\Annotation as MediaMonks;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * SepaDebitMandate
 *
 * @ORM\Table(name="SepaDebitMandates", uniqueConstraints={@ORM\UniqueConstraint(columns={"mandate_reference"})})
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaDebitMandatesRepository")
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 */
class SepaDebitMandate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", options={"default"="1"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $sequence = 1;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=35, nullable=false)
   */
  private $mandateReference;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $nonRecurring;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $mandateDate;

  /**
   * @var \DateTimeImmutable|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $lastUsedDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $iban;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $bic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $blz;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512, nullable=false)
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $bankAccountOwner;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPayment",
   *                mappedBy="sepaDebitMandate",
   *                fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  public function __construct() {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection();
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
   * Set mandateReference.
   *
   * @param string $mandateReference
   *
   * @return SepaDebitMandate
   */
  public function setMandateReference($mandateReference)
  {
    $this->mandateReference = $mandateReference;

    return $this;
  }

  /**
   * Get mandateReference.
   *
   * @return string
   */
  public function getMandateReference()
  {
    return $this->mandateReference;
  }

  /**
   * Set mandateDate.
   *
   * @param string|\DateTimeInterface $mandateDate
   *
   * @return SepaDebitMandate
   */
  public function setMandateDate($mandateDate):SepaDebitMandate
  {
    if (is_string($mandateDate)) {
      $this->mandateDate = new \DateTimeImmutable($mandateDate);
    } else {
      $this->mandateDate = \DateTimeImmutable::createFromInterface($mandateDate);
    }
    return $this;
  }

  /**
   * Get mandateDate.
   *
   * @return \DateTime
   */
  public function getMandateDate()
  {
    return $this->mandateDate;
  }

  /**
   * Set lastUsedDate.
   *
   * @param null|string|\DateTimeInterface $lastUsedDate
   *
   * @return SepaDebitMandate
   */
  public function setLastUsedDate($lastUsedDate = null):SepaDebitMandate
  {
    if (is_string($lastUsedDate)) {
      $this->lastUsedDate = new \DateTimeImmutable($lastUsedDate);
    } else {
      $this->lastUsedDate = \DateTimeImmutable::createFromInterface($lastUsedDate);
    }
    return $this;
  }

  /**
   * Get lastUsedDate.
   *
   * @return \DateTimeImmutable|null
   */
  public function getLastUsedDate():\DateTimeImmutable
  {
    return $this->lastUsedDate;
  }

  /**
   * Set nonRecurring.
   *
   * @param bool $nonRecurring
   *
   * @return SepaDebitMandate
   */
  public function setNonRecurring($nonRecurring):SepaDebitMandate
  {
    $this->nonRecurring = $nonRecurring;

    return $this;
  }

  /**
   * Get nonRecurring.
   *
   * @return bool
   */
  public function getNonRecurring()
  {
    return $this->nonRecurring;
  }

  /**
   * Set iban.
   *
   * @param string $iban
   *
   * @return SepaDebitMandate
   */
  public function setIban($iban):SepaDebitMandate
  {
    $this->iban = $iban;

    return $this;
  }

  /**
   * Get iban.
   *
   * @return string
   */
  public function getIban()
  {
    return $this->iban;
  }

  /**
   * Set bic.
   *
   * @param string $bic
   *
   * @return SepaDebitMandate
   */
  public function setBic($bic):SepaDebitMandate
  {
    $this->bic = $bic;

    return $this;
  }

  /**
   * Get bic.
   *
   * @return string
   */
  public function getBic()
  {
    return $this->bic;
  }

  /**
   * Set blz.
   *
   * @param string $blz
   *
   * @return SepaDebitMandate
   */
  public function setBlz($blz):SepaDebitMandate
  {
    $this->blz = $blz;

    return $this;
  }

  /**
   * Get blz.
   *
   * @return string
   */
  public function getBlz()
  {
    return $this->blz;
  }

  /**
   * Set bankAccountOwner.
   *
   * @param string $bankAccountOwner
   *
   * @return SepaDebitMandate
   */
  public function setBankAccountOwner($bankAccountOwner):SepaDebitMandate
  {
    $this->bankAccountOwner = $bankAccountOwner;

    return $this;
  }

  /**
   * Get bankAccountOwner.
   *
   * @return string
   */
  public function getBankAccountOwner():string
  {
    return $this->bankAccountOwner;
  }

  /**
   * Set project.
   *
   * @param Project|null $project
   *
   * @return SepaDebitMandate
   */
  public function setProject($project = null):SepaDebitMandate
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project|null
   */
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set musician.
   *
   * @param Musician|null $musician
   *
   * @return SepaDebitMandate
   */
  public function setMusician($musician = null):SepaDebitMandate
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician|null
   */
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set sequence.
   *
   * @param int $sequence
   *
   * @return SepaDebitMandate
   */
  public function setSequence(int $sequence = 1):SepaDebitMandate
  {
    $this->sequence = $sequence;

    return $this;
  }

  /**
   * Get sequence.
   *
   * @return Sequence|null
   */
  public function getSequence():int
  {
    return $this->sequence;
  }
}
