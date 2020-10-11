<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPayments
 *
 * @ORM\Table(name="ProjectPayments")
 * @ORM\Entity
 */
class ProjectPayments
{
    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="InstrumentationId", type="integer", nullable=false, options={"comment"="Link to Besetzungen.Id"})
     */
    private $instrumentationid;

    /**
     * @var string
     *
     * @ORM\Column(name="Amount", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
     */
    private $amount = '0.00';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="DateOfReceipt", type="date", nullable=true)
     */
    private $dateofreceipt;

    /**
     * @var string
     *
     * @ORM\Column(name="Subject", type="string", length=1024, nullable=false)
     */
    private $subject;

    /**
     * @var int|null
     *
     * @ORM\Column(name="DebitNoteId", type="integer", nullable=true, options={"comment"="Link to the ProjectDirectDebit table."})
     */
    private $debitnoteid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MandateReference", type="string", length=35, nullable=true, options={"comment"="Link into the SepaDebitMandates table, this is not the ID but the mandate Id."})
     */
    private $mandatereference;

    /**
     * @var string
     *
     * @ORM\Column(name="DebitMessageId", type="string", length=1024, nullable=false)
     */
    private $debitmessageid;



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
     * Set instrumentationid.
     *
     * @param int $instrumentationid
     *
     * @return ProjectPayments
     */
    public function setInstrumentationid($instrumentationid)
    {
        $this->instrumentationid = $instrumentationid;

        return $this;
    }

    /**
     * Get instrumentationid.
     *
     * @return int
     */
    public function getInstrumentationid()
    {
        return $this->instrumentationid;
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
     * Set dateofreceipt.
     *
     * @param \DateTime|null $dateofreceipt
     *
     * @return ProjectPayments
     */
    public function setDateofreceipt($dateofreceipt = null)
    {
        $this->dateofreceipt = $dateofreceipt;

        return $this;
    }

    /**
     * Get dateofreceipt.
     *
     * @return \DateTime|null
     */
    public function getDateofreceipt()
    {
        return $this->dateofreceipt;
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
     * Set debitnoteid.
     *
     * @param int|null $debitnoteid
     *
     * @return ProjectPayments
     */
    public function setDebitnoteid($debitnoteid = null)
    {
        $this->debitnoteid = $debitnoteid;

        return $this;
    }

    /**
     * Get debitnoteid.
     *
     * @return int|null
     */
    public function getDebitnoteid()
    {
        return $this->debitnoteid;
    }

    /**
     * Set mandatereference.
     *
     * @param string|null $mandatereference
     *
     * @return ProjectPayments
     */
    public function setMandatereference($mandatereference = null)
    {
        $this->mandatereference = $mandatereference;

        return $this;
    }

    /**
     * Get mandatereference.
     *
     * @return string|null
     */
    public function getMandatereference()
    {
        return $this->mandatereference;
    }

    /**
     * Set debitmessageid.
     *
     * @param string $debitmessageid
     *
     * @return ProjectPayments
     */
    public function setDebitmessageid($debitmessageid)
    {
        $this->debitmessageid = $debitmessageid;

        return $this;
    }

    /**
     * Get debitmessageid.
     *
     * @return string
     */
    public function getDebitmessageid()
    {
        return $this->debitmessageid;
    }
}
