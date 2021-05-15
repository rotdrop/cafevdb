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
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * SepaBulkTransaction
 *
 * This actually models a batch collection
 *
 * @ORM\Table(name="SepaBulkTransactions")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="sepa_transaction", type="EnumSepaTransaction")
 * @ORM\DiscriminatorMap({null="SepaBulkTransaction","debit_note"="SepaDebitNote", "bank_transfer"="SepaBankTransfer"})
 * @ORM\Entity
 */
class SepaBulkTransaction implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var EncryptedFile
   *
   * @ORM\ManyToMany(targetEntity="EncryptedFile", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   name="SepaBulkTransactionData",
   *   inverseJoinColumns={
   *     @ORM\JoinColumn(unique=true)
   *   }
   * )
   */
  private $sepaTransactionData;

  /**
   * @var \DateTimeImmutable
   *
   * Latest date before which the debit notes have to be submitted to
   * the bank in order to match the $dueDate.
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $submissionDeadline;

  /**
   * @var \DateTime|null
   * The date when the bulk-transfer data actually was submitted to the bank.
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $submitDate;

  /**
   * @var \DateTimeImmutable
   * The date when the money should arrive.
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dueDate;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionEventUri;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionTaskUri;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $dueEventUri;

  /**
   * @var ArrayCollection
   *
   * @ORM\OneToMany(targetEntity="CompositePayment", indexBy="musician", mappedBy="sepaTransaction", orphanRemoval=true, cascade={"all"}, fetch="EXTRA_LAZY")
   */
  private $payments;

  public function __construct() {
    $this->arrayCTOR();
    $this->sepaTransactionData = new ArrayCollection();
    $this->payments = new ArrayCollection();
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
   * Set sepaTransactionData.
   *
   * @param int $sepaTransactionData
   *
   * @return SepaBulkTransaction
   */
  public function setSepaTransactionData($sepaTransactionData):SepaBulkTransaction
  {
    $this->sepaTransactionData = $sepaTransactionData;

    return $this;
  }

  /**
   * Get sepaTransactionData.
   *
   * @return Collection
   */
  public function getSepaTransactionData():Collection
  {
    return $this->sepaTransactionData;
  }

  /**
   * Set submissionDeadline.
   *
   * @param \DateTime $submissionDeadline
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionDeadline($submissionDeadline):SepaBulkTransaction
  {
    $this->submissionDeadline = $submissionDeadline;

    return $this;
  }

  /**
   * Get submissionDeadline.
   *
   * @return \DateTime
   */
  public function getSubmissionDeadline()
  {
    return $this->submissionDeadline;
  }

  /**
   * Set submitDate.
   *
   * @param string|\DateTimeInterface $submitDate
   *
   * @return SepaBulkTransaction
   */
  public function setSubmitDate($submitDate):SepaBulkTransaction
  {
    $this->submitDate = self::convertToDateTime($submitDate);

    return $this;
  }

  /**
   * Get submitDate.
   *
   * @return \DateTimeInterface|null
   */
  public function getSubmitDate():?\DateTimeInterface
  {
    return $this->submitDate;
  }

  /**
   * Set dueDate.
   *
   * @param \DateTime $dueDate
   *
   * @return SepaBulkTransaction
   */
  public function setDueDate($dueDate):SepaBulkTransaction
  {
    $this->dueDate = self::convertToDateTime($dueDate);

    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTime
   */
  public function getDueDate():?\DateTimeInterface
  {
    return $this->dueDate;
  }

  /**
   * Set submissionEventUri.
   *
   * @param int $submissionEventUri
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionEventUri($submissionEventUri):SepaBulkTransaction
  {
    $this->submissionEventUri = $submissionEventUri;

    return $this;
  }

  /**
   * Get submissionEventUri.
   *
   * @return string
   */
  public function getSubmissionEventUri()
  {
    return $this->submissionEventUri;
  }

  /**
   * Set submissionTaskUri.
   *
   * @param int $submissionTaskUri
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionTaskUri($submissionTaskUri):SepaBulkTransaction
  {
    $this->submissionTaskUri = $submissionTaskUri;

    return $this;
  }

  /**
   * Get submissionTaskUri.
   *
   * @return string
   */
  public function getSubmissionTaskUri()
  {
    return $this->submissionTaskUri;
  }

  /**
   * Set dueEventUri.
   *
   * @param int $dueEventUri
   *
   * @return SepaBulkTransaction
   */
  public function setDueEventUri($dueEventUri):SepaBulkTransaction
  {
    $this->dueEventUri = $dueEventUri;

    return $this;
  }

  /**
   * Get dueEventUri.
   *
   * @return string
   */
  public function getDueEventUri()
  {
    return $this->dueEventUri;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return SepaBulkTransaction
   */
  public function setPayments(Collection $payments):SepaBulkTransaction
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return Collection
   */
  public function getPayments():Collection
  {
    return $this->payments;
  }

  /**
   * Return the number of related ProjectPayment entities.
   */
  public function usage():int
  {
    return $this->payments->count();
  }
}
