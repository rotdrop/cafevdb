<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * SepaDebitMandates
 *
 * @ORM\Table(name="SepaDebitMandates", uniqueConstraints={@ORM\UniqueConstraint(columns={"mandate_reference"})})
 * @ORM\Entity
 */
class SepaDebitMandate
{
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
     * @ORM\Column(type="string", length=35, nullable=false)
     */
    private $mandateReference;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="date", nullable=false)
     */
    private $mandatedate;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(type="date", nullable=true)
     */
    private $lastUsedDate;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $musicianId;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $projectId;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $nonRecurring;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=256, nullable=false)
     */
    private $iban;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=256, nullable=false)
     */
    private $bic;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $blz;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=false)
     */
    private $bankAccountOwner;

    /**
     * @var bool|null
     *
     * @ORM\Column(type="boolean", nullable=true, options={"default"="1"})
     */
    private $active = '1';



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
     * @return SepaDebitMandates
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
     * Set mandatedate.
     *
     * @param \DateTime $mandatedate
     *
     * @return SepaDebitMandates
     */
    public function setMandatedate($mandatedate)
    {
        $this->mandatedate = $mandatedate;

        return $this;
    }

    /**
     * Get mandatedate.
     *
     * @return \DateTime
     */
    public function getMandatedate()
    {
        return $this->mandatedate;
    }

    /**
     * Set lastUsedDate.
     *
     * @param \DateTime|null $lastUsedDate
     *
     * @return SepaDebitMandates
     */
    public function setLastUsedDate($lastUsedDate = null)
    {
        $this->lastUsedDate = $lastUsedDate;

        return $this;
    }

    /**
     * Get lastUsedDate.
     *
     * @return \DateTime|null
     */
    public function getLastUsedDate()
    {
        return $this->lastUsedDate;
    }

    /**
     * Set musicianId.
     *
     * @param int $musicianId
     *
     * @return SepaDebitMandates
     */
    public function setMusicianId($musicianId)
    {
        $this->musicianId = $musicianId;

        return $this;
    }

    /**
     * Get musicianId.
     *
     * @return int
     */
    public function getMusicianId()
    {
        return $this->musicianId;
    }

    /**
     * Set projectId.
     *
     * @param int $projectId
     *
     * @return SepaDebitMandates
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;

        return $this;
    }

    /**
     * Get projectId.
     *
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Set nonRecurring.
     *
     * @param bool $nonRecurring
     *
     * @return SepaDebitMandates
     */
    public function setNonRecurring($nonRecurring)
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
     * @return SepaDebitMandates
     */
    public function setIban($iban)
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
     * @return SepaDebitMandates
     */
    public function setBic($bic)
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
     * @return SepaDebitMandates
     */
    public function setBlz($blz)
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
     * @return SepaDebitMandates
     */
    public function setBankAccountOwner($bankAccountOwner)
    {
        $this->bankAccountOwner = $bankAccountOwner;

        return $this;
    }

    /**
     * Get bankAccountOwner.
     *
     * @return string
     */
    public function getBankAccountOwner()
    {
        return $this->bankAccountOwner;
    }

    /**
     * Set active.
     *
     * @param bool|null $active
     *
     * @return SepaDebitMandates
     */
    public function setActive($active = null)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active.
     *
     * @return bool|null
     */
    public function getActive()
    {
        return $this->active;
    }
}
