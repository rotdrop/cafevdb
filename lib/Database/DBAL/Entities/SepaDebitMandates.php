<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * SepaDebitMandates
 *
 * @ORM\Table(name="SepaDebitMandates", uniqueConstraints={@ORM\UniqueConstraint(name="mandateReference", columns={"mandateReference"})})
 * @ORM\Entity
 */
class SepaDebitMandates
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="mandateReference", type="string", length=35, nullable=false)
     */
    private $mandatereference;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="mandateDate", type="date", nullable=false)
     */
    private $mandatedate;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="lastUsedDate", type="date", nullable=true)
     */
    private $lastuseddate;

    /**
     * @var int
     *
     * @ORM\Column(name="musicianId", type="integer", nullable=false)
     */
    private $musicianid;

    /**
     * @var int
     *
     * @ORM\Column(name="projectId", type="integer", nullable=false)
     */
    private $projectid;

    /**
     * @var bool
     *
     * @ORM\Column(name="nonrecurring", type="boolean", nullable=false)
     */
    private $nonrecurring;

    /**
     * @var string
     *
     * @ORM\Column(name="IBAN", type="string", length=256, nullable=false)
     */
    private $iban;

    /**
     * @var string
     *
     * @ORM\Column(name="BIC", type="string", length=256, nullable=false)
     */
    private $bic;

    /**
     * @var string
     *
     * @ORM\Column(name="BLZ", type="string", length=128, nullable=false)
     */
    private $blz;

    /**
     * @var string
     *
     * @ORM\Column(name="bankAccountOwner", type="string", length=512, nullable=false)
     */
    private $bankaccountowner;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="active", type="boolean", nullable=true, options={"default"="1"})
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
     * Set mandatereference.
     *
     * @param string $mandatereference
     *
     * @return SepaDebitMandates
     */
    public function setMandatereference($mandatereference)
    {
        $this->mandatereference = $mandatereference;

        return $this;
    }

    /**
     * Get mandatereference.
     *
     * @return string
     */
    public function getMandatereference()
    {
        return $this->mandatereference;
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
     * Set lastuseddate.
     *
     * @param \DateTime|null $lastuseddate
     *
     * @return SepaDebitMandates
     */
    public function setLastuseddate($lastuseddate = null)
    {
        $this->lastuseddate = $lastuseddate;

        return $this;
    }

    /**
     * Get lastuseddate.
     *
     * @return \DateTime|null
     */
    public function getLastuseddate()
    {
        return $this->lastuseddate;
    }

    /**
     * Set musicianid.
     *
     * @param int $musicianid
     *
     * @return SepaDebitMandates
     */
    public function setMusicianid($musicianid)
    {
        $this->musicianid = $musicianid;

        return $this;
    }

    /**
     * Get musicianid.
     *
     * @return int
     */
    public function getMusicianid()
    {
        return $this->musicianid;
    }

    /**
     * Set projectid.
     *
     * @param int $projectid
     *
     * @return SepaDebitMandates
     */
    public function setProjectid($projectid)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int
     */
    public function getProjectid()
    {
        return $this->projectid;
    }

    /**
     * Set nonrecurring.
     *
     * @param bool $nonrecurring
     *
     * @return SepaDebitMandates
     */
    public function setNonrecurring($nonrecurring)
    {
        $this->nonrecurring = $nonrecurring;

        return $this;
    }

    /**
     * Get nonrecurring.
     *
     * @return bool
     */
    public function getNonrecurring()
    {
        return $this->nonrecurring;
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
     * Set bankaccountowner.
     *
     * @param string $bankaccountowner
     *
     * @return SepaDebitMandates
     */
    public function setBankaccountowner($bankaccountowner)
    {
        $this->bankaccountowner = $bankaccountowner;

        return $this;
    }

    /**
     * Get bankaccountowner.
     *
     * @return string
     */
    public function getBankaccountowner()
    {
        return $this->bankaccountowner;
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
