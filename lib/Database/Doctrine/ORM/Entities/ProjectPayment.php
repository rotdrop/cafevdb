<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPayments
 *
 * @ORM\Table(name="ProjectPayments")
 * @ORM\Entity
 */
class ProjectPayment implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(name="instrumentation_id", type="integer", nullable=false, options={"comment"="Link to Besetzungen.Id"})
   */
  private $instrumentationId;

  /**
   * @var string
   *
   * @ORM\Column(name="amount", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $amount = '0.00';

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(name="date_of_receipt", type="date", nullable=true)
   */
  private $dateOfReceipt;

  /**
   * @var string
   *
   * @ORM\Column(name="subject", type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @var int|null
   *
   * @ORM\Column(name="debit_note_id", type="integer", nullable=true, options={"comment"="Link to the ProjectDirectDebit table."})
   */
  private $debitNoteId;

  /**
   * @var string|null
   *
   * @ORM\Column(name="mandate_reference", type="string", length=35, nullable=true, options={"comment"="Link into the SepaDebitMandates table, this is not the ID but the mandate Id."})
   */
  private $mandateReference;

  /**
   * @var string
   *
   * @ORM\Column(name="debit_message_id", type="string", length=1024, nullable=false)
   */
  private $debitMessageId;

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
   * Set instrumentationId.
   *
   * @param int $instrumentationId
   *
   * @return ProjectPayments
   */
  public function setInstrumentationId($instrumentationId)
  {
    $this->instrumentationId = $instrumentationId;

    return $this;
  }

  /**
   * Get instrumentationId.
   *
   * @return int
   */
  public function getInstrumentationId()
  {
    return $this->instrumentationId;
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
