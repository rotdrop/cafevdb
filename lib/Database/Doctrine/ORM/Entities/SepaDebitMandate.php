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
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaDebitMandatesRepository")
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 */
class SepaDebitMandate
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @var SepaBankAccount
   *
   * Debit-mandates can expire, so many debit-mandates may refer the
   * same bank-account.
   *
   * @ORM\ManyToOne(targetEntity="SepaBankAccount", inversedBy="sepaDebitMandates")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="bank_account_sequence", referencedColumnName="sequence", nullable=false)
   * )
   */
  private $sepaBankAccount;

  /**
   * Optional project this mandate is tied to. If null then the
   * mandate does not belong to a specific project but may be used for
   * all receivables.
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="id", nullable=true)
   * )
   */
  private $project = null;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=35, options={"collation"="ascii_general_ci"})
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
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $mandateDate;

  /**
   * @var \DateTimeImmutable|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $lastUsedDate;

  /**
   * @var EncryptedFile
   *
   * @ORM\OneToOne(targetEntity="EncryptedFile")
   */
  private $writtenMandate;

  /**
   * @var ProjectPayment
   *
   * Linke to the payments table.
   *
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
   * Get nonRecurring.
   *
   * @return bool
   */
  public function getNonRecurring()
  {
    return $this->nonRecurring;
  }
}
