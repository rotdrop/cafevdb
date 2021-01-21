<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPayments
 *
 * @ORM\Table(
 *    name="ProjectPayments",
 *    uniqueConstraints={@ORM\UniqueConstraint(columns={"debit_message_id"})}
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectPaymentsRepository")
 */
class ProjectPayment implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $amount = '0.00';

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="date", nullable=true)
   */
  private $dateOfReceipt;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @ORM\OneToOne(targetEntity="DebitNote", fetch="EXTRA_LAZY")
   */
  private $debitNote;

  /**
   * @var int
   *
   * This needs to be here, otherwise the nullable stuff from the
   * association does not work.
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $mandate_sequence;

  /**
   * @ORM\ManyToOne(targetEntity="SepaDebitMandate")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="mandate_sequence", referencedColumnName="sequence", nullable=true)
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
  private $debitMessageId;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false)
   * )
   */
  private $projectParticipant;

  public function __construct() {
    $this->arrayCTOR();
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
   * Set projectParticipantId.
   *
   * @param int $projectParticipantId
   *
   * @return ProjectPayments
   */
  public function setProjectParticipantId($projectParticipantId)
  {
    $this->projectParticipantId = $projectParticipantId;

    return $this;
  }

  /**
   * Get projectParticipantId.
   *
   * @return int
   */
  public function getProjectParticipantId()
  {
    return $this->projectParticipantId;
  }

  /**
   * Set amount.
   *
   * @param string $amount
   *
   * @return ProjectPayments
   */
  public function setAmount($amount)
  {
    $this->amount = $amount;

    return $this;
  }

  /**
   * Get amount.
   *
   * @return string
   */
  public function getAmount()
  {
    return $this->amount;
  }

  /**
   * Set dateOfReceipt.
   *
   * @param \DateTime|null $dateOfReceipt
   *
   * @return ProjectPayments
   */
  public function setDateOfReceipt($dateOfReceipt = null)
  {
    $this->dateOfReceipt = $dateOfReceipt;

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
   * @return ProjectPayments
   */
  public function setSubject($subject)
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
   * Set debitNoteId.
   *
   * @param int|null $debitNoteId
   *
   * @return ProjectPayments
   */
  public function setDebitNoteId($debitNoteId = null)
  {
    $this->debitNoteId = $debitNoteId;

    return $this;
  }

  /**
   * Get debitNoteId.
   *
   * @return int|null
   */
  public function getDebitNoteId()
  {
    return $this->debitNoteId;
  }

  /**
   * Set mandateReference.
   *
   * @param string|null $mandateReference
   *
   * @return ProjectPayments
   */
  public function setMandateReference($mandateReference = null)
  {
    $this->mandateReference = $mandateReference;

    return $this;
  }

  /**
   * Get mandateReference.
   *
   * @return string|null
   */
  public function getMandateReference()
  {
    return $this->mandateReference;
  }

  /**
   * Set debitMessageId.
   *
   * @param string $debitMessageId
   *
   * @return ProjectPayments
   */
  public function setDebitMessageId($debitMessageId)
  {
    $this->debitMessageId = $debitMessageId;

    return $this;
  }

  /**
   * Get debitMessageId.
   *
   * @return string
   */
  public function getDebitMessageId()
  {
    return $this->debitMessageId;
  }
}
