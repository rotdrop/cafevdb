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
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $dateOfReceipt;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipantFieldDatum", inversedBy="payments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="field_id", referencedColumnName="field_id", nullable=false),
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="receivable_key", referencedColumnName="option_key", nullable=false)
   * )
   */
  private $receivable;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipantFieldDataOption", inversedBy="payments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="field_id", referencedColumnName="field_id", nullable=false),
   *   @ORM\JoinColumn(name="receivable_key", referencedColumnName="key", nullable=false)
   * )
   */
  private $receivableOption;

  /**
   * @var SepaDebitNote
   *
   * @ORM\ManyToOne(targetEntity="SepaDebitNote", inversedBy="projectPayments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(onDelete="CASCADE")
   */
  private $debitNote = null;

  /**
   * @var int
   *
   * This needs to be here, otherwise the default=1 of the referenced
   * column prevents the column to be nullable.
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $mandateSequence;

  /**
   * @ORM\ManyToOne(targetEntity="SepaBankAccount",
   *                inversedBy="projectPayments",
   *                fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="mandate_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaBankAccount;

  /**
   * @var string
   *
   * This is the unique message id from the email sent to the payees.
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  private $debitMessageId;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="payments", fetch="EXTRA_LAZY")
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payments", fetch="EXTRA_LAZY")
   */
  private $musician;

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
   * Set projectParticipant.
   *
   * @param int $projectParticipant
   *
   * @return ProjectPayment
   */
  public function setProjectParticipant($projectParticipant):ProjectPayment
  {
    $this->projectParticipant = $projectParticipant;

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant()
  {
    return $this->projectParticipant;
  }

  /**
   * Set project.
   *
   * @param int $project
   *
   * @return ProjectPayment
   */
  public function setProject($project):ProjectPayment
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set musician.
   *
   * @param int $musician
   *
   * @return ProjectPayment
   */
  public function setMusician($musician):ProjectPayment
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
   * Set amount.
   *
   * @param string $amount
   *
   * @return ProjectPayment
   */
  public function setAmount($amount):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setDateOfReceipt($dateOfReceipt = null):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setSubject($subject):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setDebitNote($debitNote):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount):ProjectPayment
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
   * Set debitMessageId.
   *
   * @param string $debitMessageId
   *
   * @return ProjectPayment
   */
  public function setDebitMessageId($debitMessageId):ProjectPayment
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
