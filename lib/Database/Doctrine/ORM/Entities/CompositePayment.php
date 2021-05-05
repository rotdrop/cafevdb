<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * CompositePayments collect a couple of ProjectPayments of the same
 * Musician. In GnuCash this would be a "split transactions". The
 * transaction parts are ProjectPayment entities.
 *
 * @ORM\Table(name="CompositePayments",
 *    uniqueConstraints={@ORM\UniqueConstraint(columns={"notification_message_id"})}
 * )
 * @ORM\Entity
 */
class CompositePayment implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\DateTimeTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var float
   *
   * The total amount for the bank transaction. This must equal the
   * sum of the self:$projectPayments collection.
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $amount = '0.00';

  /**
   * @var \DateTimeImmutable|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $dateOfReceipt;

  /**
   * @var string
   * Subject of the bank transaction.
   *
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="compositePayment", fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  /**
   * @var SepaBulkTransaction
   *
   * @ORM\ManyToOne(targetEntity="SepaBulkTransaction", inversedBy="payments", fetch="EXTRA_LAZY")
   */
  private $sepaTransaction = null;

  /**
   * @ORM\ManyToOne(targetEntity="SepaBankAccount", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="bank_account_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaBankAccount;

  /**
   * @ORM\ManyToOne(targetEntity="SepaDebitMandate",
   *                inversedBy="payments",
   *                fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="debit_mandate_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaDebitMandate;

  /**
   * @var string
   *
   * This is the unique message id from the email sent to the payees.
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  private $notificationMessageId;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payments", fetch="EXTRA_LAZY")
   */
  private $musician;

  public function __construct() {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection;
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
   * Set projectPayments.
   *
   * @param Collection $projectPayments
   *
   * @return CompositePayment
   */
  public function setProjectPayments(Collection $projectPayments):CompositePayment
  {
    $this->projectPayments = $projectPayments;

    return $this;
  }

  /**
   * Get projectPayments.
   *
   * @return Collection
   */
  public function getProjectPayments():Collection
  {
    return $this->projectPayments;
  }

  /**
   * Set amount.
   *
   * @param float|null $amount
   *
   * @return ProjectPayment
   */
  public function setAmount(?float $amount):CompositePayment
  {
    $this->amount = $amount;

    return $this;
  }

  /**
   * Get amount.
   *
   * @return float
   */
  public function getAmount():float
  {
    return $this->amount;
  }

  /**
   * Return the sum of the amounts of the individual payments, which
   * should sum up to $this->amount, of course.
   */
  public function sumPaymentsAmount():float
  {
    $totalAmount = 0.0;
    /** @var ProjectPayment $payment */
    foreach ($this->payments as $payment) {
      $totalAmount += $payment->getAmount();
    }
    return $totalAmount;
  }

  /**
   * Set musician.
   *
   * @param int $musician
   *
   * @return CompositePayment
   */
  public function setMusician($musician):CompositePayment
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set dateOfReceipt.
   *
   * @param \DateTime|null $dateOfReceipt
   *
   * @return CompositePayment
   */
  public function setDateOfReceipt($dateOfReceipt = null):CompositePayment
  {
    $this->dateOfReceipt = self::convertToDateTime($mandateDate);

    return $this;
  }

  /**
   * Get dateOfReceipt.
   *
   * @return \DateTime|null
   */
  public function getDateOfReceipt()
  {
    return $this->dateOfReceipt;
  }

  /**
   * Set subject.
   *
   * @param string $subject
   *
   * @return CompositePayment
   */
  public function setSubject($subject):CompositePayment
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject()
  {
    return $this->subject;
  }

  /**
   * Set debitNote.
   *
   * @param SepaDebitNote|null $debitNote
   *
   * @return CompositePayment
   */
  public function setDebitNote($debitNote):CompositePayment
  {
    $this->debitNote = $debitNote;

    return $this;
  }

  /**
   * Get debitNote.
   *
   * @return SepaDebitNote|null
   */
  public function getDebitNote()
  {
    return $this->debitNote;
  }

  /**
   * Set sepaBankAccount.
   *
   * @param string|null $sepaBankAccount
   *
   * @return CompositePayment
   */
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount):CompositePayment
  {
    $this->sepaBankAccount = $sepaBankAccount;

    return $this;
  }

  /**
   * Get sepaBankAccount.
   *
   * @return SepaBankAccount|null
   */
  public function getSepaBankAccount():?SepaBankAccount
  {
    return $this->sepaBankAccount;
  }

  /**
   * Set sepaDebitMandate.
   *
   * @param string|null $sepaDebitMandate
   *
   * @return CompositePayment
   */
  public function setSepaDebitMandate(?SepaDebitMandate $sepaDebitMandate):CompositePayment
  {
    $this->sepaDebitMandate = $sepaDebitMandate;

    return $this;
  }

  /**
   * Get sepaDebitMandate.
   *
   * @return SepaDebitMandate|null
   */
  public function getSepaDebitMandate():?SepaDebitMandate
  {
    return $this->sepaDebitMandate;
  }

  /**
   * Set sepaTransaction.
   *
   * @param string|null $sepaTransaction
   *
   * @return CompositePayment
   */
  public function setSepaTransaction(?SepaTransaction $sepaTransaction):CompositePayment
  {
    $this->sepaTransaction = $sepaTransaction;

    return $this;
  }

  /**
   * Get sepaTransaction.
   *
   * @return SepaTransaction|null
   */
  public function getSepaTransaction():?SepaTransaction
  {
    return $this->sepaTransaction;
  }

  /**
   * Set notificationMessageId.
   *
   * @param string $notificationMessageId
   *
   * @return CompositePayment
   */
  public function setNotificationMessageId($notificationMessageId):CompositePayment
  {
    $this->notificationMessageId = $notificationMessageId;

    return $this;
  }

  /**
   * Get notificationMessageId.
   *
   * @return string
   */
  public function getNotificationMessageId()
  {
    return $this->notificationMessageId;
  }
}
